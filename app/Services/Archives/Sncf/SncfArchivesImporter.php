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

    /** Raison sans point : la notice n'a pas été rattachée par son titre mais par sa description. */
    /** Caractères du début d'une description où la ligne doit être décrite (« Ligne de X à Y : pont… »). */
    public const DESCRIPTION_HEAD = 300;

    public const READ_IN_DESCRIPTION = 'read_in_description';

    public function __construct(
        private readonly SardoParser $parser = new SardoParser,
        private readonly ArchiveMatcher $matcher = new ArchiveMatcher,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  notices de l'export JSON
     * @return array{
     *     documents: array<int, list<array<string, mixed>>>,
     *     candidates: list<array<string, mixed>>,
     *     stats: array{rows: int, kept: int, excluded: int, judged: int, accepted: int, by_description: int, lines_with_records: int, ambiguous: int}
     * }
     */
    public function run(LinePlaceIndex $index, array $rows, int $threshold = ArchiveMatcher::THRESHOLD): array
    {
        $documents = [];
        $candidates = [];
        $kept = 0;
        $judgedRecords = 0;
        $ambiguous = 0;
        $byDescription = 0;
        $seen = [];
        foreach ($rows as $row) {
            $record = $this->parser->parse($row);
            if ($record === null || isset($seen[$record->url])) {
                continue;
            }
            $seen[$record->url] = true;
            $kept++;
            $read = 'title';
            $judged = $this->matcher->candidates($record->toArchiveRecord(), $index);
            // Le titre ne nomme aucune ligne au seuil : la description est lue, avec des règles plus strictes.
            if (! $this->anyPasses($judged, $threshold) && $record->descriptionText !== null) {
                $fromDescription = $this->matcher->candidates($record->toArchiveRecord(description: true), $index);
                if ($fromDescription !== []) {
                    $judged = array_map(fn (ArchiveMatch $m): ArchiveMatch => $m->withReason(self::READ_IN_DESCRIPTION), $fromDescription);
                    $read = 'description';
                }
            }
            if ($judged === []) {
                continue;
            }
            $judgedRecords++;
            $winners = $read === 'description' ? $this->descriptionWinners($judged, $threshold, (string) $record->descriptionText) : $this->matcher->winners($judged, $threshold);
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
                    'places' => implode(' ; ', $match->places), 'rejected' => $match->rejected, 'accepted' => $accepted, 'read' => $read,
                ];
                if ($accepted) {
                    $byDescription += $read === 'description' ? 1 : 0;
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
                'by_description' => $byDescription,
                'lines_with_records' => count($documents),
                'ambiguous' => $ambiguous,
            ],
        ];
    }

    /**
     * Lignes retenues pour une notice jugée sur sa description. Plus strict que pour le titre : la ligne doit y
     * être décrite comme une voie ferrée entre deux lieux (`rail_route`, pas un simple trajet ni deux lieux
     * nommés), la description doit décrire une seule ligne (SardoParser::statedLines), dès son début (DESCRIPTION_HEAD caractères), et VFF n'en reconnaît pas plus de MAX_TIED_LINES (un dossier qui cite dix
     * lignes n'est pas sur l'une d'elles). Les lignes qui passent sont toutes gardées ou aucune : celles que le
     * comparateur écarterait (moins précises, moins bien notées) disent que la description est ambiguë.
     *
     * @param  list<ArchiveMatch>  $judged
     * @return list<ArchiveMatch>
     */
    private function descriptionWinners(array $judged, int $threshold, string $description): array
    {
        $passing = array_values(array_filter($judged, fn (ArchiveMatch $m): bool => $m->passes($threshold) && $m->has('rail_route')));
        if ($passing === [] || count($passing) > ArchiveMatcher::MAX_TIED_LINES) {
            return [];
        }
        $winners = $this->matcher->winners($passing, $threshold);

        // Une seule ligne décrite, dès le début du texte (au-delà, c'est un point d'un inventaire) : une seconde ligne,
        // souvent l'intitulé de la notice suivante tronqué à la fin (« Pont sur la Doller [...] Ligne de Sewen à Cernay »),
        // ne dit pas laquelle des deux est le sujet. Elle peut être reconnue par deux lignes de VFF (variantes d'un trajet).
        $headed = SardoParser::statedLines(mb_substr($description, 0, self::DESCRIPTION_HEAD)) === 1;

        return $headed && count($winners) === count($passing) && SardoParser::statedLines($description) === 1 ? $winners : [];
    }

    /** @param list<ArchiveMatch> $judged */
    private function anyPasses(array $judged, int $threshold): bool
    {
        foreach ($judged as $match) {
            if ($match->passes($threshold)) {
                return true;
            }
        }

        return false;
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
