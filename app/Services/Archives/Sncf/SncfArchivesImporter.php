<?php

namespace App\Services\Archives\Sncf;

use App\Models\LineArchiveRecord;
use App\Services\Archives\ArchiveMatch;
use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\LinePlaceIndex;
use Illuminate\Support\Facades\DB;

/**
 * Rattachement aux lignes des notices du jeu de données « Archives SNCF » (vff:import-sncf-archives).
 *
 * Même méthode que Gallica (ArchiveMatcher) : chaque notice est jugée sur son seul titre, sans les noms de
 * compagnies (« Compagnie du chemin de fer de Paris à Orléans » ne désigne pas la ligne de Paris à Orléans),
 * pour toutes les lignes dont le titre nomme deux lieux ; elle va à la ligne au meilleur score s'il atteint le
 * seuil, à deux lignes au plus à égalité, sinon à aucune (notice ambiguë).
 *
 * Le jeu de données est lu en entier à chaque import : l'enregistrement remplace toutes les notices de la source
 * (store), sans toucher aux autres sources ni aux documents de Gallica (line_archive_documents).
 */
final class SncfArchivesImporter
{
    public const SOURCE = 'sncf-archives';

    public function __construct(
        private readonly SardoParser $parser = new SardoParser,
        private readonly ArchiveMatcher $matcher = new ArchiveMatcher,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  notices de l'export JSON
     * @return array{
     *     documents: array<int, list<array<string, mixed>>>,
     *     candidates: list<array<string, mixed>>,
     *     stats: array{rows: int, kept: int, excluded: int, judged: int, accepted: int, lines_with_records: int, ambiguous: int}
     * }
     */
    public function run(LinePlaceIndex $index, array $rows, int $threshold = ArchiveMatcher::THRESHOLD): array
    {
        $documents = [];
        $candidates = [];
        $kept = 0;
        $judgedRecords = 0;
        $ambiguous = 0;
        $seen = [];
        foreach ($rows as $row) {
            $record = $this->parser->parse($row);
            if ($record === null || isset($seen[$record->url])) {
                continue;
            }
            $seen[$record->url] = true;
            $kept++;
            $judged = $this->matcher->candidates($record->toArchiveRecord(), $index);
            if ($judged === []) {
                continue;
            }
            $judgedRecords++;
            $winners = $this->matcher->winners($judged, $threshold);
            $winnerIds = array_map(fn (ArchiveMatch $m): int => $m->lineId, $winners);
            if ($winners === [] && count(array_filter($judged, fn (ArchiveMatch $m): bool => $m->passes($threshold))) > ArchiveMatcher::MAX_TIED_LINES) {
                $ambiguous++;
            }
            foreach ($judged as $match) {
                $line = $index->line($match->lineId);
                $accepted = in_array($match->lineId, $winnerIds, true);
                $candidates[] = [
                    'line' => $line?->slug, 'line_name' => $line?->name, 'url' => $record->url, 'title' => $record->title,
                    'date' => $record->date->label, 'year' => $record->date->year, 'type' => $record->docType,
                    'score' => $match->score,
                    'reasons' => implode(' ', array_map(fn (array $r): string => "{$r['code']}+{$r['points']}", $match->reasons)),
                    'places' => implode(' ; ', $match->places), 'rejected' => $match->rejected, 'accepted' => $accepted,
                ];
                if ($accepted) {
                    $documents[$match->lineId][] = $this->row($record, $match);
                }
            }
        }

        return [
            'documents' => $documents,
            'candidates' => $candidates,
            'stats' => [
                'rows' => count($rows),
                'kept' => $kept,
                'excluded' => count($rows) - $kept,
                'judged' => $judgedRecords,
                'accepted' => array_sum(array_map('count', $documents)),
                'lines_with_records' => count($documents),
                'ambiguous' => $ambiguous,
            ],
        ];
    }

    /**
     * Remplace toutes les notices de la source par le résultat de run().
     *
     * @param  array{documents: array<int, list<array<string, mixed>>>}  $result
     * @return array{written: int, before: int, after: int}
     */
    public function store(array $result, string $fetchedAt): array
    {
        return DB::transaction(function () use ($result, $fetchedAt): array {
            $all = fn () => LineArchiveRecord::query()->where('source', self::SOURCE);
            $before = $all()->count();
            $all()->delete();
            $now = now();
            $written = 0;
            foreach ($result['documents'] as $lineId => $rows) {
                foreach (array_chunk($rows, 200) as $chunk) {
                    LineArchiveRecord::query()->insert(array_map(fn (array $row): array => [
                        ...$row,
                        'score_reasons' => json_encode($row['score_reasons'], JSON_UNESCAPED_UNICODE),
                        'matched_places' => json_encode($row['matched_places'], JSON_UNESCAPED_UNICODE),
                        'line_id' => $lineId, 'fetched_at' => $fetchedAt, 'created_at' => $now, 'updated_at' => $now,
                    ], $chunk));
                    $written += count($chunk);
                }
            }

            return ['written' => $written, 'before' => $before, 'after' => $all()->count()];
        });
    }

    /** @return array<string, mixed> colonnes de line_archive_records (sans line_id ni fetched_at) */
    private function row(SardoRecord $record, ArchiveMatch $match): array
    {
        return [
            'source' => self::SOURCE,
            'reference' => $record->reference,
            'title' => $record->title,
            'doc_type' => $record->docType,
            'theme' => $record->theme,
            'sub_theme' => $record->subTheme,
            'holder' => $record->holder,
            'box' => $record->box,
            'rights' => $record->rights,
            'date_label' => $record->date->label,
            'year' => $record->date->year,
            'year_to' => $record->date->yearTo,
            'exact_date' => $record->date->exact,
            'date_precision' => $record->date->precision,
            'url' => $record->url,
            'score' => $match->score,
            'score_reasons' => $match->reasons,
            'matched_places' => $match->places,
        ];
    }
}
