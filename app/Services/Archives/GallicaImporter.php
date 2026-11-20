<?php

namespace App\Services\Archives;

use App\Models\LineArchiveDocument;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Import des documents de Gallica rattachés aux lignes (vff:import-gallica).
 *
 * 1. Pour chaque ligne traitée, une requête par couple d'extrémités (nom actuel, anciens noms, gares
 *    terminus ; 4 au plus) : titre contenant les deux lieux et un mot du rail, document numérisé en accès
 *    libre dans Gallica, notice de monographie (la presse et les périodiques sont exclus), type texte
 *    imprimé, carte, image fixe ou manuscrit.
 * 2. Chaque notice trouvée est jugée pour TOUTES les lignes dont son titre nomme deux lieux
 *    (ArchiveMatcher) : elle va à la ligne au meilleur score, s'il atteint le seuil, et à deux lignes au
 *    plus à égalité ; sinon elle est ambiguë et n'est rattachée à aucune.
 * 3. Pour les lignes traitées, les documents de la source `gallica` sont remplacés (relance sans doublon).
 */
final class GallicaImporter
{
    /** Mots du rail dont l'un au moins doit figurer dans le titre (filtre large ; le jugement fin est local). */
    public const RAIL_TERMS = 'chemin chemins fer ferree ferrees ligne lignes tramway tramways railway embranchement gare gares voie voies train trains';

    public const SOURCE = 'gallica';

    public function __construct(
        private readonly CatalogueSruClient $client,
        private readonly ArchiveMatcher $matcher = new ArchiveMatcher,
    ) {}

    /** Requête CQL pour un couple de lieux. */
    public static function cql(string $a, string $b): string
    {
        $term = fn (string $s): string => trim((string) preg_replace('/["\\\\]+/', ' ', $s));

        return sprintf(
            'bib.title all "%s" and bib.title all "%s" and bib.title any "%s" and bib.digitized any "freeAccess" and bib.recordtype any "mon" and bib.doctype any "a e i t"',
            $term($a), $term($b), self::RAIL_TERMS,
        );
    }

    /**
     * Interroge le catalogue pour les lignes données et juge chaque notice trouvée.
     *
     * @param  list<LinePlaces>  $lines  lignes traitées
     * @param  (callable(LinePlaces): void)|null  $onLine  appelé après chaque ligne (barre de progression)
     * @return array{
     *     documents: array<int, list<array<string, mixed>>>,
     *     candidates: list<array<string, mixed>>,
     *     errors: list<string>,
     *     stats: array{lines: int, queries: int, truncated: int, records: int, accepted: int, lines_with_documents: int, ambiguous: int}
     * }
     */
    public function run(LinePlaceIndex $index, array $lines, int $threshold = ArchiveMatcher::THRESHOLD, ?callable $onLine = null): array
    {
        /** @var array<string, array{record: ArchiveRecord, fetched_at: ?int}> $records */
        $records = [];
        $errors = [];
        $queries = 0;
        $truncated = 0;
        foreach ($lines as $line) {
            foreach ($line->queryPairs as [$a, $b]) {
                $queries++;
                try {
                    $result = $this->client->search(self::cql($a, $b));
                } catch (RuntimeException $e) {
                    $errors[] = "{$line->slug} ({$a} / {$b}) : {$e->getMessage()}";

                    continue;
                }
                $truncated += $result['truncated'] ? 1 : 0;
                foreach ($result['records'] as $record) {
                    $records[$record->gallicaArk] ??= ['record' => $record, 'fetched_at' => $result['fetched_at']];
                }
            }
            if ($onLine !== null) {
                $onLine($line);
            }
        }

        $processed = array_fill_keys(array_map(fn (LinePlaces $l): int => $l->id, $lines), true);
        $documents = [];
        $candidates = [];
        $ambiguous = 0;
        foreach ($records as ['record' => $record, 'fetched_at' => $fetchedAt]) {
            $judged = $this->matcher->candidates($record, $index);
            $winners = $this->matcher->winners($judged, $threshold);
            $winnerIds = array_map(fn (ArchiveMatch $m): int => $m->lineId, $winners);
            if ($winners === [] && count(array_filter($judged, fn (ArchiveMatch $m): bool => $m->passes($threshold))) > ArchiveMatcher::MAX_TIED_LINES) {
                $ambiguous++;
            }
            foreach ($judged as $match) {
                $line = $index->line($match->lineId);
                $accepted = in_array($match->lineId, $winnerIds, true) && isset($processed[$match->lineId]);
                $candidates[] = [
                    'line' => $line?->slug, 'line_name' => $line?->name, 'gallica_ark' => $record->gallicaArk,
                    'title' => $record->title, 'date' => $record->date->label, 'year' => $record->date->year,
                    'type' => $record->docType, 'score' => $match->score,
                    'reasons' => implode(' ', array_map(fn (array $r): string => "{$r['code']}+{$r['points']}", $match->reasons)),
                    'places' => implode(' ; ', $match->places), 'rejected' => $match->rejected, 'accepted' => $accepted,
                ];
                if ($accepted) {
                    $documents[$match->lineId][] = $this->row($record, $match, $fetchedAt);
                }
            }
        }

        return [
            'documents' => $documents,
            'candidates' => $candidates,
            'errors' => $errors,
            'stats' => [
                'lines' => count($lines),
                'queries' => $queries,
                'truncated' => $truncated,
                'records' => count($records),
                'accepted' => array_sum(array_map('count', $documents)),
                'lines_with_documents' => count($documents),
                'ambiguous' => $ambiguous,
            ],
        ];
    }

    /**
     * Remplace les documents de la source `gallica` des lignes traitées.
     *
     * @param  array<int, list<array<string, mixed>>>  $documents  par ligne
     * @param  list<int>  $lineIds  lignes traitées (celles sans document perdent les anciens)
     */
    public function store(array $documents, array $lineIds): int
    {
        $written = 0;
        DB::transaction(function () use ($documents, $lineIds, &$written): void {
            foreach (array_chunk($lineIds, 500) as $chunk) {
                LineArchiveDocument::query()->where('source', self::SOURCE)->whereIn('line_id', $chunk)->delete();
            }
            $now = now();
            foreach ($documents as $lineId => $rows) {
                if (! in_array($lineId, $lineIds, true)) {
                    continue;
                }
                foreach ($rows as $row) {
                    (new LineArchiveDocument)->forceFill($row + ['line_id' => $lineId, 'created_at' => $now, 'updated_at' => $now])->save();
                    $written++;
                }
            }
        });

        return $written;
    }

    /** @return array<string, mixed> colonnes de line_archive_documents (sans line_id) */
    private function row(ArchiveRecord $record, ArchiveMatch $match, ?int $fetchedAt): array
    {
        return [
            'source' => self::SOURCE,
            'gallica_ark' => $record->gallicaArk,
            'catalogue_ark' => $record->catalogueArk,
            'title' => $record->title,
            'doc_type' => $record->docType,
            'authors' => $record->authors,
            'date_label' => $record->date->label,
            'year' => $record->date->year,
            'year_to' => $record->date->yearTo,
            'exact_date' => $record->date->exact,
            'date_precision' => $record->date->precision,
            'shelfmark' => $record->shelfmark,
            'score' => $match->score,
            'score_reasons' => $match->reasons,
            'matched_places' => $match->places,
            'record_updated_on' => $record->recordUpdatedOn,
            'fetched_at' => date('Y-m-d H:i:s', $fetchedAt ?? time()),
        ];
    }
}
