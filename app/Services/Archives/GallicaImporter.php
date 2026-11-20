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
 * 3. Enregistrement notice par notice (store) : les rattachements de chaque notice relue sont remplacés par
 *    le nouveau jugement, sur toutes les lignes. Une notice que la passe n'a pas relue n'est jamais retirée,
 *    sauf à l'import complet sans erreur (elle n'est alors plus trouvée par aucune requête). Le résultat d'un
 *    import partiel (--line, --limit) ne dépend donc pas des lignes choisies : il ne retire jamais un
 *    document qu'une autre ligne a trouvé.
 *
 * Une ligne dont une requête a échoué (erreur du serveur, réponse qui n'est pas du SRU, page absente du
 * cache hors ligne) est « en erreur » : ses notices ne sont pas rejugées et ses documents ne bougent pas.
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
     * `documents` : documents retenus, par ligne (toutes les lignes, pas seulement celles qui sont traitées) ;
     * `arks` : notices relues et jugées ; `failed` : lignes en erreur (slug → identifiant), qui ne bougent pas.
     *
     * @param  list<LinePlaces>  $lines  lignes traitées
     * @param  (callable(LinePlaces): void)|null  $onLine  appelé après chaque ligne (barre de progression)
     * @return array{
     *     documents: array<int, list<array<string, mixed>>>,
     *     arks: list<string>,
     *     failed: array<string, int>,
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
        $failed = [];
        $queries = 0;
        $truncated = 0;
        foreach ($lines as $line) {
            $found = [];
            foreach ($line->queryPairs as [$a, $b]) {
                $queries++;
                try {
                    $result = $this->client->search(self::cql($a, $b));
                } catch (RuntimeException $e) {
                    $errors[] = "{$line->slug} ({$a} / {$b}) : {$e->getMessage()}";
                    $failed[$line->slug] = $line->id;

                    continue;
                }
                $truncated += $result['truncated'] ? 1 : 0;
                foreach ($result['records'] as $record) {
                    $found[$record->gallicaArk] ??= ['record' => $record, 'fetched_at' => $result['fetched_at']];
                }
            }
            // Une ligne en erreur ne fait rejuger aucune notice : son résultat serait incomplet.
            if (! isset($failed[$line->slug])) {
                $records += $found;
            }
            if ($onLine !== null) {
                $onLine($line);
            }
        }

        $frozen = array_fill_keys($failed, true);
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
                $accepted = in_array($match->lineId, $winnerIds, true) && ! isset($frozen[$match->lineId]);
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
            'arks' => array_keys($records),
            'failed' => $failed,
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
     * Enregistre le résultat de run(), notice par notice :
     *   - chaque notice relue (`arks`) perd ses anciens rattachements, sur toutes les lignes, et reçoit les
     *     nouveaux (`documents`) ;
     *   - `$complete` (import de toutes les lignes, sans ligne en erreur) : les documents d'une notice qu'aucune
     *     requête n'a plus trouvée sont aussi retirés ;
     *   - les lignes en erreur (`failed`) ne perdent ni ne reçoivent rien.
     *
     * @param  array{documents: array<int, list<array<string, mixed>>>, arks: list<string>, failed: array<string, int>}  $result
     * @return array{written: int, before: int, after: int} documents écrits ; documents de la source avant et après
     */
    public function store(array $result, bool $complete): array
    {
        $frozen = array_values($result['failed']);
        $complete = $complete && $frozen === [];

        return DB::transaction(function () use ($result, $frozen, $complete): array {
            $all = fn () => LineArchiveDocument::query()->where('source', self::SOURCE);
            $movable = fn () => $all()->when($frozen !== [], fn ($q) => $q->whereNotIn('line_id', $frozen));
            $before = $all()->count();
            if ($complete) {
                $movable()->whereNotIn('gallica_ark', $result['arks'] === [] ? [''] : $result['arks'])->delete();
            }
            foreach (array_chunk($result['arks'], 500) as $chunk) {
                $movable()->whereIn('gallica_ark', $chunk)->delete();
            }
            $now = now();
            $written = 0;
            foreach ($result['documents'] as $lineId => $rows) {
                if (in_array($lineId, $frozen, true)) {
                    continue;
                }
                foreach ($rows as $row) {
                    (new LineArchiveDocument)->forceFill($row + ['line_id' => $lineId, 'created_at' => $now, 'updated_at' => $now])->save();
                    $written++;
                }
            }

            return ['written' => $written, 'before' => $before, 'after' => $all()->count()];
        });
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
