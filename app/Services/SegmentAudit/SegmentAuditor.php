<?php

namespace App\Services\SegmentAudit;

use App\Enums\AuditGrade;
use App\Enums\SegmentStatus;
use App\Services\EraTimeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Passe automatique de l'audit des tronçons (docs/AUDIT-TRONCONS.md) pour une ligne : mesure les
 * faits de chaque tronçon de chaque ère (sources datées, écart à la voie réelle, défauts de
 * géométrie, gares, source du tracé), les note avec AuditGrader et prépare les lignes de la table
 * `segment_audits`. Lecture seule sur les tables du moteur et les sources déjà en base ; aucun
 * appel réseau. L'écriture (remplacement des lignes d'audit de la ligne) est dans write().
 *
 * Sources du classement :
 *   - événements datés de la ligne (line_events), une chronologie EraTimeline par source (rang
 *     EraTimeline::rankedSource), avec les mêmes règles de portée que le moteur : événement de
 *     toute la ligne, ou portion localisée à moins de COVER_TOL_M du milieu du tronçon ;
 *   - ère courante seulement : statut SNCF Réseau (formes à moins de SNCF_TOL_M), état BD TOPO de
 *     la voie sous le tronçon (moins de BDTOPO_TOL_M), couche OSM la plus proche (moins de
 *     OSM_TOL_M), état actuel relevé par lignes-oubliées (present_states, moins de PRESENT_TOL_M) ;
 *   - règle métier : voyageurs seuls réservé aux LGV.
 */
final class SegmentAuditor
{
    public const COVER_TOL_M = 30;

    public const SNCF_TOL_M = 200;

    public const BDTOPO_TOL_M = 5;

    public const OSM_TOL_M = 10;

    public const PRESENT_TOL_M = 15;

    /**
     * Rang des sources d'état actuel, sur l'échelle de EraTimeline::SOURCE_PRIORITY. Les états
     * relevés par lignes-oubliées ne sont rattachés à aucune ligne (une ligne voisine fermée peut
     * passer à moins de 15 m) : rang bas et avis faible, comme une voie OSM rattachée à une autre ligne.
     */
    private const CURRENT_PRIORITY = ['present-lignes-oubliees' => 7, 'sncf' => 3, 'bdtopo' => 3, 'osm' => 5];

    /** Couches OSM qui disent l'état de la voie. */
    private const OSM_OPEN = ['rail', 'narrow_gauge', 'light_rail'];

    private const OSM_CLOSED = ['disused', 'disused:railway'];

    private const OSM_GONE = ['abandoned', 'abandoned:railway', 'razed', 'razed:railway', 'dismantled', 'demolished:railway', 'removed:railway', 'was:railway'];

    /** Voies réelles du placement (mêmes couches que le chantier géométrie propre). */
    private const OSM_TRACK_LAYERS = ['rail', 'abandoned', 'disused', 'razed', 'narrow_gauge', 'light_rail', 'construction',
        'abandoned:railway', 'razed:railway', 'disused:railway', 'dismantled', 'historic'];

    private const BUILD_DEFAULTS = ['principal' => 'PF', 'secondaire' => 'PF', 'particulier' => 'F', 'militaire' => ''];

    /**
     * Audit d'une ligne : une entrée par ère auditée (synthèse et tronçons).
     *
     * @param  list<int>|null  $eraYears  ères à auditer (toutes celles qui ont des tronçons si null)
     * @return list<array{era_id: int, year: int, line: array<string, mixed>, segments: list<array<string, mixed>>}>
     */
    public function auditLine(int $lineId, ?array $eraYears = null, ?string $asOf = null): array
    {
        $line = DB::selectOne('SELECT id, code, slug, name, is_lgv, network, length_official_m FROM lines WHERE id = ?', [$lineId]);
        if ($line === null) {
            return [];
        }
        $asOf ??= Carbon::today()->toDateString();
        $eras = DB::select('SELECT id, year, is_current FROM eras ORDER BY year');
        $previous = [];
        $prevYear = null;
        foreach ($eras as $e) {
            $previous[(int) $e->id] = $prevYear;
            $prevYear = (int) $e->year;
        }
        $withSegments = array_map('intval', array_column(DB::select('SELECT DISTINCT era_id FROM line_segments WHERE line_id = ?', [$lineId]), 'era_id'));
        $events = $this->events($line);
        $lineFacts = $this->lineFacts($line);

        $out = [];
        foreach ($eras as $era) {
            $eraId = (int) $era->id;
            if (! in_array($eraId, $withSegments, true) || ($eraYears !== null && ! in_array((int) $era->year, $eraYears, true))) {
                continue;
            }
            $out[] = $this->auditEra($line, $era, $previous[$eraId] ?? null, $events, $lineFacts, $asOf);
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array{reference_length_m: ?int, official_length_m: ?int, official_source: ?string}  $lineFacts
     * @return array{era_id: int, year: int, line: array<string, mixed>, segments: list<array<string, mixed>>}
     */
    private function auditEra(object $line, object $era, ?int $prevYear, array $events, array $lineFacts, string $asOf): array
    {
        $eraId = (int) $era->id;
        $year = (int) $era->year;
        $current = (bool) $era->is_current;
        $segments = $this->segmentFacts((int) $line->id, $eraId, (string) $line->code, $current);
        $placement = $this->placementSamples((int) $line->id, $eraId);
        $turns = $this->turns((int) $line->id, $eraId);
        $ends = $this->ends((int) $line->id, $eraId);
        [$stations, $inversions] = $this->stations((int) $line->id, $eraId);
        $defaults = $line->is_lgv ? 'P' : (self::BUILD_DEFAULTS[$line->network ?? 'principal'] ?? 'PF');
        // Ère passée, ligne sans aucune fin datée : tronçons en service et sûrs à l'ère courante.
        $continuous = ! $current && ! self::hasEnd($events) ? $this->continuousToday((int) $line->id, $eraId) : [];

        $rows = [];
        foreach ($segments as $s) {
            $id = (int) $s->id;
            $verdicts = $this->eventVerdicts($s, $events, $year, $current, $prevYear, $asOf, $defaults, ! $line->is_lgv);
            if ($s->is_heritage) {
                // Train touristique : une source qui dit la ligne fermée parle du trafic commercial.
                $verdicts = array_map(fn (array $v): array => ! $v['agrees'] && in_array($v['expected']['class'], ['closed', 'closed_in_place', 'in_place'], true)
                    ? ['agrees' => true, 'why' => null, 'heritage' => true] + $v : $v, $verdicts);
            }
            $observed = [];
            if ($current) {
                $observed = $this->currentVerdicts($s);
                $verdicts = self::outvoted([...self::outweighed($verdicts, $observed), ...$observed]);
            }
            $rules = [];
            if ($current && $s->status === 'abandoned' && ! $s->is_heritage && self::oldClosureUnconfirmed($verdicts, $observed, $asOf)) {
                $rules[] = 'track_unconfirmed';
            }
            if ($s->traffic_type === 'passenger' && ! $line->is_lgv && SegmentStatus::from($s->status)->isActive()) {
                $rules[] = 'passenger_only_off_lgv';
            }
            $classification = AuditGrader::classification(array_map(fn (array $v): array => [
                'source' => $v['source'], 'agrees' => $v['agrees'], 'weak' => $v['weak'], 'near_date' => $v['near_date'], 'priority' => $v['priority'],
                'outvoted' => $v['outvoted'] ?? false,
            ], $verdicts), $rules, isset($continuous[$id]) && AuditGrader::statusClass($s->status) === 'open');

            $refs = $placement[$id] ?? [];
            // Une voie en service aujourd'hui existe forcément : sans voie réelle à côté, le tracé
            // est faux. Pour une ère passée, la voie a pu disparaître depuis.
            $activeToday = $current && (SegmentStatus::from($s->status)->isActive() || $s->status === 'service_track');
            $place = AuditGrader::placement($refs, $s->source, $activeToday, abroad: (bool) $s->abroad, bestMatch: ! $current);

            $segEnds = $ends[$id] ?? ['junction_gaps' => [], 'holes' => []];
            $accFacts = [
                'max_turn_deg' => $turns[$id]['max_turn_deg'] ?? null,
                'hairpins' => $turns[$id]['hairpins'] ?? 0,
                'kinks' => $turns[$id]['kinks'] ?? 0,
                'junction_gaps' => $segEnds['junction_gaps'],
                'holes' => $segEnds['holes'],
                'stations' => $stations[$id] ?? [],
            ];
            $accuracy = AuditGrader::accuracy($accFacts);

            $precise = max(AuditGrader::coverage($refs['bdtopo'] ?? ['points' => 0, 'found' => 0]), AuditGrader::coverage($refs['osm'] ?? ['points' => 0, 'found' => 0]));
            $unusedInGap = array_filter($segEnds['holes'], fn (array $h): bool => $h['unused_traces'] !== []) !== [];
            $trace = AuditGrader::trace($s->source, $precise, $place['grade'] === AuditGrade::Good, $unusedInGap, AuditGrader::confirmedBy($refs, $s->source));

            $grades = [$classification['grade'], $place['grade'], $accuracy['grade'], $trace['grade']];
            $rows[] = [
                'segment_id' => $id,
                'status' => $s->status,
                'traffic_type' => $s->traffic_type,
                'trace_source' => $s->source,
                'length_m' => (int) $s->len,
                'classification_grade' => $classification['grade']->value,
                'placement_grade' => $place['grade']->value,
                'accuracy_grade' => $accuracy['grade']->value,
                'trace_grade' => $trace['grade']->value,
                'worst_grade' => AuditGrade::worst($grades)->value,
                'score' => AuditGrader::score(...$grades),
                'evidence' => [
                    'classification' => [
                        'reasons' => $classification['reasons'],
                        'actual' => ['status' => $s->status, 'traffic' => $s->traffic_type, 'heritage' => (bool) $s->is_heritage],
                        'verdicts' => $verdicts,
                        'rules' => $rules,
                    ],
                    'placement' => ['reasons' => $place['reasons'], 'reference' => $place['reference'], 'same_source' => $place['same_source'],
                        'coverage' => $place['coverage'], 'median_m' => self::round($place['median_m']), 'p95_m' => self::round($place['p95_m']),
                        'references' => array_map(fn (array $r): array => ['points' => $r['points'], 'found' => $r['found'],
                            'median_m' => self::round($r['median_m']), 'p95_m' => self::round($r['p95_m']),
                            'all' => ['found' => $r['all']['found'], 'median_m' => self::round($r['all']['median_m']), 'p95_m' => self::round($r['all']['p95_m'])],
                            'own' => ['found' => $r['own']['found'], 'median_m' => self::round($r['own']['median_m'])]], $refs)],
                    'accuracy' => ['reasons' => $accuracy['reasons']] + $accFacts,
                    'trace' => ['reasons' => $trace['reasons'], 'source' => $s->source, 'precision_m' => $trace['precision_m'],
                        'precision_class' => $trace['precision_class'], 'precise_coverage' => round($precise, 3)],
                ],
            ];
        }

        return ['era_id' => $eraId, 'year' => $year, 'line' => $this->lineRow($rows, $lineFacts, $inversions), 'segments' => $rows];
    }

    /**
     * Synthèse d'une ligne pour une ère : note de chaque contrôle d'après les longueurs de ses
     * tronçons (AuditGrader::lineGrade), plus les contrôles propres à la ligne (longueur, ordre des gares).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array{reference_length_m: ?int, official_length_m: ?int, official_source: ?string}  $lineFacts
     * @return array<string, mixed>
     */
    private function lineRow(array $rows, array $lineFacts, int $inversions): array
    {
        $grades = [];
        $shares = [];
        $total = max(1, array_sum(array_column($rows, 'length_m')));
        foreach (['classification', 'placement', 'accuracy', 'trace'] as $check) {
            $byGrade = [];
            foreach ($rows as $r) {
                $byGrade[$r[$check.'_grade']] = ($byGrade[$r[$check.'_grade']] ?? 0) + max(1, $r['length_m']);
            }
            $grades[$check] = AuditGrader::lineGrade($byGrade);
            $shares[$check] = array_map(fn (int $m): float => round($m / $total, 3), $byGrade);
        }
        $lineAccuracy = AuditGrader::lineAccuracy($lineFacts['reference_length_m'], $lineFacts['official_length_m'], $inversions,
            $lineFacts['official_source'] === 'lignes-oubliees-pk');
        $grades['accuracy'] = AuditGrade::worst([$grades['accuracy'], $lineAccuracy['grade']]);
        $length = array_sum(array_column($rows, 'length_m'));
        $score = $length > 0
            ? (int) round(array_sum(array_map(fn (array $r): float => $r['score'] * $r['length_m'], $rows)) / $length)
            : 0;

        return [
            'segment_id' => null,
            'status' => null,
            'traffic_type' => null,
            'trace_source' => null,
            'length_m' => $length,
            'classification_grade' => $grades['classification']->value,
            'placement_grade' => $grades['placement']->value,
            'accuracy_grade' => $grades['accuracy']->value,
            'trace_grade' => $grades['trace']->value,
            'worst_grade' => AuditGrade::worst($grades)->value,
            'score' => $score,
            'evidence' => [
                'segments' => count($rows),
                'shares' => $shares,
                'accuracy' => ['reasons' => $lineAccuracy['reasons'], 'length_ratio' => $lineAccuracy['length_ratio'],
                    'station_order_inversions' => $inversions] + $lineFacts,
            ],
        ];
    }

    /**
     * Remplace les lignes d'audit des ères auditées de la ligne (une transaction par ligne).
     *
     * @param  list<array{era_id: int, year: int, line: array<string, mixed>, segments: list<array<string, mixed>>}>  $audit
     */
    public function write(int $lineId, array $audit): int
    {
        $now = Carbon::now();
        $count = 0;
        DB::transaction(function () use ($lineId, $audit, $now, &$count): void {
            foreach ($audit as $era) {
                DB::delete('DELETE FROM segment_audits WHERE line_id = ? AND era_id = ?', [$lineId, $era['era_id']]);
                // Un tronçon déjà audité sous une autre ligne ou une autre ère (identifiant recyclé) : remplacé.
                $ids = array_column($era['segments'], 'segment_id');
                if ($ids !== []) {
                    DB::delete("DELETE FROM segment_audits WHERE scope = 'segment' AND segment_id = ANY(?::bigint[])", ['{'.implode(',', $ids).'}']);
                }
                $rows = [['scope' => 'line'] + $era['line'], ...array_map(fn (array $r): array => ['scope' => 'segment'] + $r, $era['segments'])];
                foreach (array_chunk($rows, 200) as $chunk) {
                    $payload = json_encode(array_map(fn (array $r): array => [...$r, 'evidence' => $r['evidence']], $chunk), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    $count += DB::affectingStatement(<<<'SQL'
                        INSERT INTO segment_audits (era_id, line_id, segment_id, scope, status, traffic_type, trace_source, length_m,
                            classification_grade, placement_grade, accuracy_grade, trace_grade, worst_grade, score, evidence, geom,
                            rule_version, audited_at, created_at, updated_at)
                        SELECT ?, ?, r.segment_id, r.scope, r.status, r.traffic_type, r.trace_source, r.length_m,
                               r.classification_grade, r.placement_grade, r.accuracy_grade, r.trace_grade, r.worst_grade, r.score, r.evidence,
                               (SELECT s.geom FROM line_segments s WHERE s.id = r.segment_id), ?, ?, ?, ?
                        FROM jsonb_to_recordset(?::jsonb) AS r(segment_id bigint, scope text, status text, traffic_type text, trace_source text,
                            length_m int, classification_grade text, placement_grade text, accuracy_grade text, trace_grade text,
                            worst_grade text, score int, evidence jsonb)
                        SQL, [$era['era_id'], $lineId, AuditGrader::RULE_VERSION, $now, $now, $now, $payload]);
                }
            }
        });

        return $count;
    }

    /**
     * Retire les audits périmés : couples (ligne, ère) qui n'ont plus aucun tronçon, par exemple une
     * ligne qui a perdu tous ses tronçons d'une ère depuis la passe précédente (synthèse et tronçons).
     *
     * @param  list<int>|null  $lineIds  lignes visées (toutes si null)
     * @param  array{0: int, 1: int}|null  $shard  morceau [i, n] : lignes dont id % n = i - 1
     */
    public function pruneStale(?array $lineIds = null, ?array $shard = null): int
    {
        $sql = 'DELETE FROM segment_audits a WHERE NOT EXISTS (SELECT 1 FROM line_segments s WHERE s.line_id = a.line_id AND s.era_id = a.era_id)';
        $bindings = [];
        if ($lineIds !== null) {
            $sql .= ' AND a.line_id = ANY(?::bigint[])';
            $bindings[] = '{'.implode(',', $lineIds).'}';
        }
        if ($shard !== null) {
            $sql .= ' AND a.line_id % ? = ?';
            $bindings[] = $shard[1];
            $bindings[] = $shard[0] - 1;
        }

        return DB::delete($sql, $bindings);
    }

    /**
     * Événements de la ligne retenus comme par le moteur (BuildEraSegments::lineEvents) : portée
     * « toute la ligne » ou portion localisée, rang de la source, rien avant la grande vitesse sur une
     * LGV. Les fins de service dont la portion n'a pas été localisée sont gardées à part (`unlocated`) :
     * elles ne servent qu'à ne pas contredire un tronçon fermé (cf. eventVerdicts).
     *
     * @return array<int, array<string, mixed>>
     */
    private function events(object $line): array
    {
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT id, type, reuse, heritage_kind, event_date, source, source_url, confidence, section_resolution,
                   (section_resolution = 'whole_line' OR (section_resolution IS NULL AND section_from IS NULL AND section_pk_start_m IS NULL)) AS whole,
                   section_geom IS NOT NULL AND section_resolution IN ('pk', 'places', 'trace') AS localized
            FROM line_events WHERE line_id = ? ORDER BY event_date, id
            SQL, [$line->id]) as $e) {
            $unlocated = ! $e->whole && ! $e->localized;
            if ($unlocated && ! (str_starts_with($e->type, 'closure') || in_array($e->type, ['declassification', 'dismantling'], true))) {
                continue;
            }
            if ($line->is_lgv && EraTimeline::predatesHighSpeed($e->type, $e->event_date)) {
                continue;
            }
            $out[(int) $e->id] = [
                'id' => (int) $e->id,
                'type' => $e->type,
                'date' => $e->event_date,
                'source' => EraTimeline::rankedSource($e->source, $e->source_url, $e->confidence) ?? 'unknown',
                'reuse' => $e->reuse,
                'heritage_kind' => $e->heritage_kind,
                'whole' => (bool) $e->whole,
                'unlocated' => $unlocated,
            ];
        }

        return $out;
    }

    /**
     * Avis de chaque source d'événements sur un tronçon à une ère. Une source qui contredit un
     * tronçon fermé mais date aussi une fermeture non localisée sur la ligne n'est plus comptée
     * contre lui : son avis devient un accord faible (`unlocated_end`).
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function eventVerdicts(object $s, array $events, int $year, bool $current, ?int $prevYear, string $asOf, string $defaults, bool $passengerImpliesFreight): array
    {
        $local = array_flip(array_map('intval', array_filter(explode(',', trim((string) $s->evs, '{}')))));
        $limit = $current ? $asOf : sprintf('%04d-12-31', $year);
        $bySource = [];
        $unlocated = [];
        foreach ($events as $id => $e) {
            if ($e['unlocated']) {
                if ($e['date'] <= $limit) {
                    $unlocated[$e['source']][] = ['whole' => true] + $e;
                }
            } elseif ($e['whole'] || isset($local[$id])) {
                $bySource[$e['source']][] = $e;
            }
        }
        $closedNow = in_array(AuditGrader::statusClass($s->status), ['closed', 'gone'], true);
        $verdicts = [];
        foreach (array_unique([...array_keys($bySource), ...($closedNow ? array_keys($unlocated) : [])]) as $source) {
            $list = $bySource[$source] ?? [];
            $judged = $this->judge($s, $list, $year, $current, $prevYear, $limit, $defaults, $passengerImpliesFreight);
            $unlocatedEnd = false;
            if ($closedNow && ($judged === null || ! $judged['agrees']) && isset($unlocated[$source])) {
                $retry = $this->judge($s, [...$list, ...$unlocated[$source]], $year, $current, $prevYear, $limit, $defaults, $passengerImpliesFreight);
                if ($retry !== null && $retry['agrees']) {
                    $judged = $retry;
                    $unlocatedEnd = true;
                }
            }
            if ($judged === null) {
                continue;
            }
            $applicable = array_values(array_filter([...$list, ...($unlocatedEnd ? $unlocated[$source] : [])], fn (array $e): bool => $e['date'] <= $limit
                && (bool) preg_match('/^(re)?opening|^closure|^declassification|^dismantling|^conversion|^heritage/', $e['type'])));
            usort($applicable, fn (array $a, array $b): int => $a['date'] <=> $b['date']);
            $verdicts[] = [
                'source' => $source,
                'kind' => 'events',
                'expected' => $judged['expected'],
                'agrees' => $judged['agrees'],
                'why' => $judged['why'],
                'near_date' => $judged['near_date'],
                'weak' => $unlocatedEnd || in_array($source, AuditGrader::WEAK_SOURCES, true),
                'unlocated_end' => $unlocatedEnd,
                'priority' => EraTimeline::SOURCE_PRIORITY[$source] ?? 10,
                'events' => array_map(fn (array $e): array => ['id' => $e['id'], 'type' => $e['type'], 'date' => $e['date']],
                    array_slice($applicable, -4)),
                'first_opening_year' => $judged['first_opening_year'],
            ];
        }

        return $verdicts;
    }

    /**
     * Avis d'une liste d'événements d'une source, ou null si elle ne dit rien de cette date.
     *
     * @param  list<array<string, mixed>>  $list
     * @return array{expected: array{class: string, traffic: ?string}, agrees: bool, why: ?string, near_date: bool, first_opening_year: ?int}|null
     */
    private function judge(object $s, array $list, int $year, bool $current, ?int $prevYear, string $limit, string $defaults, bool $passengerImpliesFreight): ?array
    {
        $openings = array_filter($list, fn (array $e): bool => (bool) preg_match('/^(re)?opening|^heritage_start/', $e['type']));
        $firstOpening = $openings ? min(array_map(fn (array $e): int => (int) substr($e['date'], 0, 4), $openings)) : null;
        $speaks = array_filter($list, fn (array $e): bool => $e['date'] <= $limit) !== [] || ($firstOpening !== null && $firstOpening > $year);
        if (! $speaks) {
            return null;
        }
        // Source qui ne date que des fermetures : ouverture supposée, seules ses fins comptent.
        $timeline = new EraTimeline($openings ? $list : [['type' => 'opening', 'date' => '1800-01-01', 'source' => 'assumed', 'whole' => true], ...$list],
            $defaults, $passengerImpliesFreight);
        $expect = function (int $y, bool $isCurrent, ?int $prev, ?string $asOf) use ($timeline, $firstOpening, $openings): array {
            $expected = AuditGrader::expectedFromState($timeline->stateAt($y, $isCurrent, $prev, $asOf), $firstOpening, $y);
            if (! $openings) {
                $expected['traffic'] = null;
            }

            return $expected;
        };
        $expected = $expect($year, $current, $prevYear, $current ? $limit : null);
        if (! $openings && $expected['class'] === 'open') {
            // Rien n'est encore fermé selon elle : elle ne dit rien de l'état à cette date.
            return null;
        }
        [$agrees, $why] = AuditGrader::compare($s->status, $s->traffic_type, $expected);
        $knowsEnds = array_filter($list, fn (array $e): bool => (bool) preg_match('/^closure|^declassification|^dismantling|^conversion/', $e['type'])) !== [];
        if (! $agrees && ! $knowsEnds && $expected['class'] === 'open') {
            // Source qui ne date que des ouvertures : son silence sur la fermeture ne la contredit pas.
            return null;
        }
        $nearDate = false;
        if (! $agrees) {
            foreach ([-AuditGrader::NEAR_DATE_YEARS, AuditGrader::NEAR_DATE_YEARS] as $shift) {
                $shifted = $expect($year + $shift, false, ($prevYear ?? $year - 10) + $shift, null);
                $nearDate = $nearDate || AuditGrader::compare($s->status, $s->traffic_type, $shifted)[0];
            }
        }

        return ['expected' => $expected, 'agrees' => $agrees, 'why' => $why, 'near_date' => $nearDate, 'first_opening_year' => $firstOpening];
    }

    /**
     * Avis des sources d'état actuel (ère courante) : SNCF Réseau, BD TOPO, OSM, lignes-oubliées.
     *
     * @return list<array<string, mixed>>
     */
    private function currentVerdicts(object $s): array
    {
        $out = [];
        $add = function (string $source, array $expected, array $observed, bool $weak = false) use (&$out, $s): void {
            [$agrees, $why] = AuditGrader::compare($s->status, $s->traffic_type, $expected);
            $out[] = ['source' => $source, 'kind' => 'current', 'expected' => $expected, 'agrees' => $agrees, 'why' => $why,
                'near_date' => false, 'weak' => $weak, 'priority' => self::CURRENT_PRIORITY[$source], 'observed' => $observed];
        };
        if ($s->sncf !== null) {
            $status = SegmentStatus::fromSncfLibelle($s->sncf);
            $libelle = mb_strtolower($s->sncf);
            $class = match (true) {
                $status === SegmentStatus::InService, $status === SegmentStatus::ServiceTrack => 'open',
                $status === SegmentStatus::Dismantled => 'gone',
                // Voie dite en place par SNCF Réseau : fermée, mais pas déposée.
                str_contains($libelle, 'maintien'), str_contains($libelle, 'non déposée'), str_contains($libelle, 'neutralis') => 'closed_in_place',
                // « Fermée », « Déclassée », « Retranchée » : la dépose reste possible.
                default => 'closed',
            };
            // Une voie de service ne porte jamais de voyageurs.
            $add('sncf', ['class' => $class, 'traffic' => $status === SegmentStatus::ServiceTrack ? 'freight' : null], ['statut' => $s->sncf]);
        }
        if ($s->bd_layer !== null) {
            $open = $s->bd_state === 'En service' && $s->bd_layer !== 'Sans objet' && $s->bd_usage !== 'Vélo-rail';
            $traffic = match ($s->bd_usage) {
                'Fret' => 'freight',
                'Voyageur et fret', 'Voyageur' => 'mixed',
                default => null,
            };
            $class = match (true) {
                $s->bd_state === 'En construction', $s->bd_state === 'En projet' => 'not_open',
                $open => 'open',
                default => 'closed',
            };
            $expected = ['class' => $class, 'traffic' => $open ? $traffic : null];
            // Voie BD TOPO non rattachée à la ligne : elle peut appartenir à une ligne voisine ; son
            // désaccord n'est qu'un avis faible, son accord compte (contrôle à la main du 21/11).
            $weak = ! $s->bd_attached && ! AuditGrader::compare($s->status, $s->traffic_type, $expected)[0];
            $add('bdtopo', $expected, ['nature' => $s->bd_layer, 'etat' => $s->bd_state, 'usage' => $s->bd_usage, 'attached' => (bool) $s->bd_attached], $weak);
        }
        if ($s->osm_layer !== null) {
            $class = match (true) {
                in_array($s->osm_layer, self::OSM_OPEN, true) => 'in_place',
                in_array($s->osm_layer, self::OSM_CLOSED, true) => 'closed',
                in_array($s->osm_layer, self::OSM_GONE, true) => 'gone',
                default => null,
            };
            // Une voie OSM « rail » dit que la voie est en place, pas qu'elle est exploitée.
            if ($class !== null) {
                $add('osm', ['class' => $class, 'traffic' => null], ['layer' => $s->osm_layer, 'attached' => (bool) $s->osm_attached], ! $s->osm_attached);
            }
        }
        if ($s->lo_state !== null) {
            $add('present-lignes-oubliees', ['class' => $s->lo_state === 'track_in_place' ? 'in_place' : 'gone', 'traffic' => null], ['state' => $s->lo_state], true);
        }

        return $out;
    }

    /**
     * Ère courante : quand au moins deux observations de l'état actuel (SNCF Réseau, BD TOPO, OSM
     * rattachées à la ligne) sont d'accord et qu'aucune ne contredit, l'avis contraire d'une
     * chronologie devient faible (un événement mal localisé ou un ancien tracé voisin l'explique).
     *
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $observed
     * @return list<array<string, mixed>>
     */
    private static function outweighed(array $events, array $observed): array
    {
        $strong = array_filter($observed, fn (array $v): bool => ! $v['weak']);
        $agree = array_filter($strong, fn (array $v): bool => $v['agrees']);
        if (count($agree) < 2 || count($agree) !== count($strong)) {
            return $events;
        }

        return array_map(fn (array $v): array => $v['agrees'] ? $v : ['weak' => true, 'outweighed' => true] + $v, $events);
    }

    /**
     * Ère courante (T3) : une seule contradiction forte, venue de la BD TOPO ou d'un statut SNCF
     * « déclassée vendue » (voie cédée, par exemple à la RATP), alors qu'au moins deux observations
     * actuelles fortes sont d'accord : elle devient un avis faible (`outvoted`). Une voie OSM
     * « abandoned » ou « razed » reste une vraie contradiction.
     *
     * @param  list<array<string, mixed>>  $verdicts
     * @return list<array<string, mixed>>
     */
    public static function outvoted(array $verdicts): array
    {
        $contra = array_keys(array_filter($verdicts, fn (array $v): bool => ! $v['agrees'] && ! $v['weak'] && ! $v['near_date']));
        $agree = array_filter($verdicts, fn (array $v): bool => $v['kind'] === 'current' && $v['agrees'] && ! $v['weak']);
        if (count($contra) !== 1 || count($agree) < 2) {
            return $verdicts;
        }
        $v = $verdicts[$contra[0]];
        $lone = $v['kind'] === 'current' && ($v['source'] === 'bdtopo'
            || ($v['source'] === 'sncf' && str_contains(mb_strtolower((string) ($v['observed']['statut'] ?? '')), 'déclassée vendue')));
        if ($lone) {
            $verdicts[$contra[0]] = ['weak' => true, 'outvoted' => true] + $v;
        }

        return $verdicts;
    }

    /**
     * La ligne a-t-elle une fin datée (fermeture, déclassement, dépose), localisée ou non ?
     *
     * @param  array<int, array<string, mixed>>  $events
     */
    public static function hasEnd(array $events): bool
    {
        foreach ($events as $e) {
            if (preg_match('/^closure|^declassification|^dismantling/', (string) $e['type'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tronçons d'une ère passée dont le milieu est à moins de COVER_TOL_M d'un tronçon de la même
     * ligne en service et noté sûr pour le classement à l'ère courante (audit déjà enregistré).
     *
     * @return array<int, true>
     */
    private function continuousToday(int $lineId, int $eraId): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT s.id FROM line_segments s
            WHERE s.line_id = :line AND s.era_id = :era
              AND EXISTS (SELECT 1 FROM segment_audits a JOIN eras e ON e.id = a.era_id AND e.is_current
                          WHERE a.line_id = :line2 AND a.scope = 'segment' AND a.classification_grade = 'good'
                            AND a.status IN ('in_service', 'single_track', 'service_track')
                            AND a.geom && ST_Expand(ST_LineInterpolatePoint(s.geom, 0.5), 0.0005)
                            AND ST_DWithin(ST_Transform(a.geom, 2154), ST_Transform(ST_LineInterpolatePoint(s.geom, 0.5), 2154), :tol))
            SQL, ['line' => $lineId, 'era' => $eraId, 'line2' => $lineId, 'tol' => self::COVER_TOL_M]);

        return array_fill_keys(array_map(fn (object $r): int => (int) $r->id, $rows), true);
    }

    /**
     * Tronçon « fermé, voie en place » aujourd'hui sans aucune observation de voie en place, alors
     * que sa dernière fermeture datée remonte à plus de OLD_CLOSURE_YEARS ans : la dépose est probable.
     *
     * @param  list<array<string, mixed>>  $verdicts
     * @param  list<array<string, mixed>>  $observed
     */
    private static function oldClosureUnconfirmed(array $verdicts, array $observed, string $asOf): bool
    {
        foreach ($observed as $v) {
            if (in_array($v['expected']['class'], ['open', 'closed', 'closed_in_place', 'in_place'], true)) {
                return false;
            }
        }
        $lastEnd = null;
        foreach ($verdicts as $v) {
            foreach ($v['events'] ?? [] as $e) {
                if (preg_match('/^closure|^declassification/', $e['type']) && ($lastEnd === null || $e['date'] > $lastEnd)) {
                    $lastEnd = $e['date'];
                }
            }
        }

        return $lastEnd !== null && (int) substr($lastEnd, 0, 4) <= (int) substr($asOf, 0, 4) - AuditGrader::OLD_CLOSURE_YEARS;
    }

    /** @return list<object> */
    private function segmentFacts(int $lineId, int $eraId, string $code, bool $current): array
    {
        $flag = $current ? 'true' : 'false';
        $osmClosed = '{'.implode(',', array_map(fn (string $l): string => '"'.$l.'"', [...self::OSM_OPEN, ...self::OSM_CLOSED, ...self::OSM_GONE])).'}';

        return DB::select(<<<'SQL'
            WITH m AS MATERIALIZED (
                SELECT s.id, s.status, s.traffic_type, s.source, s.is_heritage, ST_Length(ST_Transform(s.geom, 2154)) AS len,
                       ST_Transform(ST_LineInterpolatePoint(s.geom, 0.5), 2154) AS mid, ST_LineInterpolatePoint(s.geom, 0.5) AS mid4
                FROM line_segments s WHERE s.line_id = :line AND s.era_id = :era
            )
            SELECT m.id, m.status, m.traffic_type, m.source, m.is_heritage, round(m.len) AS len,
                   COALESCE((SELECT array_agg(e.id ORDER BY e.id) FROM line_events e
                             WHERE e.line_id = :line2 AND e.section_geom IS NOT NULL AND e.section_resolution IN ('pk', 'places', 'trace')
                               AND e.section_geom && ST_Expand(m.mid4, 0.0005)
                               AND ST_DWithin(ST_Transform(e.section_geom, 2154), m.mid, :cover)), '{}')::text AS evs,
                   CASE WHEN CAST(:current AS boolean) THEN (SELECT g.libelle FROM sncf_line_geometries g
                        WHERE g.code_ligne = :code AND g.geom && ST_Expand(m.mid4, 0.003) AND ST_DWithin(ST_Transform(g.geom, 2154), m.mid, :stol)
                        ORDER BY ST_Distance(ST_Transform(g.geom, 2154), m.mid) LIMIT 1) END AS sncf,
                   bd.layer AS bd_layer, bd.etat AS bd_state, bd.usage AS bd_usage, bd.attached AS bd_attached,
                   -- Hors de France (aucune commune sous le milieu) : ni BD TOPO ni OSM France n'y voient la voie.
                   NOT EXISTS (SELECT 1 FROM commune_contours cc WHERE cc.geom && m.mid4 AND ST_Intersects(cc.geom, m.mid4)) AS abroad,
                   osm.layer AS osm_layer, osm.attached AS osm_attached,
                   CASE WHEN CAST(:current3 AS boolean) THEN (SELECT ps.state FROM present_states ps
                        WHERE ps.source = 'lignes-oubliees' AND ps.geom && ST_Expand(m.mid4, 0.0003)
                          AND ST_DWithin(ST_Transform(ps.geom, 2154), m.mid, :ptol)
                        ORDER BY (ps.state = 'track_in_place') DESC LIMIT 1) END AS lo_state
            FROM m
            -- Voie BD TOPO sous le tronçon : celle de la ligne d'abord, comme pour OSM.
            LEFT JOIN LATERAL (
                SELECT st.layer, st.properties->>'etat_de_l_objet' AS etat, NULLIF(st.properties->>'usage', '') AS usage,
                       (st.line_id = :line5 OR EXISTS (SELECT 1 FROM source_trace_lines stl
                                                       WHERE stl.source_trace_id = st.id AND stl.line_id = :line6)) AS attached
                FROM source_traces st
                WHERE CAST(:current4 AS boolean) AND st.source = 'bdtopo' AND st.layer <> 'Funiculaire ou crémaillère' AND st.geom && ST_Expand(m.mid4, 0.0001)
                  AND ST_DWithin(ST_Transform(st.geom, 2154), m.mid, :btol)
                ORDER BY 4 DESC, ST_Distance(ST_Transform(st.geom, 2154), m.mid) LIMIT 1
            ) bd ON true
            -- Voie OSM sous le tronçon : celle de la ligne d'abord (une voie voisine peut être d'une autre ligne).
            LEFT JOIN LATERAL (
                SELECT st.layer, (st.line_id = :line3 OR EXISTS (SELECT 1 FROM source_trace_lines stl
                                                                WHERE stl.source_trace_id = st.id AND stl.line_id = :line4)) AS attached
                FROM source_traces st
                WHERE CAST(:current2 AS boolean) AND st.source = 'osm' AND st.layer = ANY(CAST(:osm AS text[])) AND st.geom && ST_Expand(m.mid4, 0.0002)
                  AND ST_DWithin(ST_Transform(st.geom, 2154), m.mid, :otol)
                ORDER BY 2 DESC, ST_Distance(ST_Transform(st.geom, 2154), m.mid) LIMIT 1
            ) osm ON true
            ORDER BY m.id
            SQL, ['line' => $lineId, 'era' => $eraId, 'line2' => $lineId, 'line3' => $lineId, 'line4' => $lineId, 'line5' => $lineId, 'line6' => $lineId, 'cover' => self::COVER_TOL_M,
            'current' => $flag, 'current2' => $flag, 'current3' => $flag, 'current4' => $flag, 'code' => $code,
            'stol' => self::SNCF_TOL_M, 'osm' => $osmClosed, 'otol' => self::OSM_TOL_M, 'ptol' => self::PRESENT_TOL_M, 'btol' => self::BDTOPO_TOL_M]);
    }

    /**
     * Écart à la voie réelle : points répartis le long de chaque tronçon (un tous les
     * SAMPLE_STEP_M mètres, entre 3 et MAX_SAMPLES), distance à la voie la plus proche de chaque
     * référence à moins de REFERENCE_RADIUS_M, sans les voies rattachées à une autre ligne ; plus,
     * sur les mêmes points, l'écart à toutes les voies (`all`) et aux voies de la ligne (`own`, à
     * moins de OWN_TRACK_MAX_M), pour reconnaître un tronçon posé sur la voie d'une autre ligne.
     *
     * @return array<int, array<string, array{points: int, found: int, median_m: ?float, p95_m: ?float, all: array{found: int, median_m: ?float, p95_m: ?float}, own: array{found: int, median_m: ?float}}>>
     */
    private function placementSamples(int $lineId, int $eraId): array
    {
        $osmLayers = '{'.implode(',', array_map(fn (string $l): string => '"'.$l.'"', self::OSM_TRACK_LAYERS)).'}';
        $rows = DB::select(<<<'SQL'
            WITH seg AS MATERIALIZED (
                SELECT s.id, ST_Transform(s.geom, 2154) AS g FROM line_segments s WHERE s.line_id = :line AND s.era_id = :era
            ),
            pts AS MATERIALIZED (
                SELECT seg.id, p.q, ST_Transform(p.q, 4326) AS q4
                FROM seg
                CROSS JOIN LATERAL (SELECT LEAST(:maxn, GREATEST(3, CEIL(ST_Length(seg.g) / :step)))::int AS n) k
                CROSS JOIN LATERAL generate_series(1, k.n) AS i
                CROSS JOIN LATERAL (SELECT ST_LineInterpolatePoint(seg.g, (i - 0.5) / k.n) AS q) p
            ),
            ref(name) AS (VALUES ('bdtopo'), ('osm'), ('ign-historique')),
            -- Trois écarts par point : aux voies qui ne sont pas rattachées à une autre ligne (dist), à
            -- toutes les voies (dist_all) et aux voies rattachées à la ligne (dist_own, jusqu'à 60 m).
            d AS (
                SELECT pts.id, ref.name, x.dist, x.dist_all, x.dist_own
                FROM pts CROSS JOIN ref
                CROSS JOIN LATERAL (
                    SELECT MIN(t.m) FILTER (WHERE t.free) AS dist, MIN(t.m) AS dist_all, MIN(t.m) FILTER (WHERE t.own) AS dist_own
                    FROM (SELECT ST_Distance(ST_Transform(st.geom, 2154), pts.q) AS m,
                                 (st.line_id IS NULL OR st.line_id = :line3) AS free, COALESCE(st.line_id = :line4, false) AS own
                          FROM source_traces st
                          WHERE st.source = ref.name AND st.geom && ST_Expand(pts.q4, 0.001)
                            AND (ref.name <> 'osm' OR st.layer = ANY(CAST(:osm AS text[])))
                            AND (ref.name <> 'bdtopo' OR st.layer <> 'Funiculaire ou crémaillère')) t
                ) x
            )
            SELECT id, name, COUNT(*) AS points, COUNT(*) FILTER (WHERE dist <= :radius) AS found,
                   percentile_cont(0.5) WITHIN GROUP (ORDER BY dist) FILTER (WHERE dist <= :radius2) AS median_m,
                   percentile_cont(0.95) WITHIN GROUP (ORDER BY dist) FILTER (WHERE dist <= :radius3) AS p95_m,
                   COUNT(*) FILTER (WHERE dist_all <= :radius4) AS found_all,
                   percentile_cont(0.5) WITHIN GROUP (ORDER BY dist_all) FILTER (WHERE dist_all <= :radius5) AS median_all,
                   percentile_cont(0.95) WITHIN GROUP (ORDER BY dist_all) FILTER (WHERE dist_all <= :radius6) AS p95_all,
                   COUNT(*) FILTER (WHERE dist_own <= :own) AS found_own,
                   percentile_cont(0.5) WITHIN GROUP (ORDER BY dist_own) FILTER (WHERE dist_own <= :own2) AS median_own
            FROM d GROUP BY id, name
            SQL, ['line' => $lineId, 'era' => $eraId, 'maxn' => AuditGrader::MAX_SAMPLES, 'step' => AuditGrader::SAMPLE_STEP_M,
            'line3' => $lineId, 'line4' => $lineId,
            'osm' => $osmLayers, 'radius' => AuditGrader::REFERENCE_RADIUS_M, 'radius2' => AuditGrader::REFERENCE_RADIUS_M, 'radius3' => AuditGrader::REFERENCE_RADIUS_M,
            'radius4' => AuditGrader::REFERENCE_RADIUS_M, 'radius5' => AuditGrader::REFERENCE_RADIUS_M, 'radius6' => AuditGrader::REFERENCE_RADIUS_M,
            'own' => AuditGrader::OWN_TRACK_MAX_M, 'own2' => AuditGrader::OWN_TRACK_MAX_M]);
        $f = fn (mixed $v): ?float => $v !== null ? (float) $v : null;
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r->found_all === 0 && (int) $r->found_own === 0 && $r->name === 'ign-historique') {
                continue;
            }
            $out[(int) $r->id][$r->name] = ['points' => (int) $r->points, 'found' => (int) $r->found,
                'median_m' => $f($r->median_m), 'p95_m' => $f($r->p95_m),
                'all' => ['found' => (int) $r->found_all, 'median_m' => $f($r->median_all), 'p95_m' => $f($r->p95_all)],
                'own' => ['found' => (int) $r->found_own, 'median_m' => $f($r->median_own)]];
        }

        return $out;
    }

    /**
     * Virages de chaque tronçon : un point tous les 10 m, cap mesuré sur des cordes de 20 m ;
     * un aller-retour ou un coude compte une fois par groupe de points voisins au-dessus du seuil.
     *
     * @return array<int, array{max_turn_deg: float, hairpins: int, kinks: int}>
     */
    private function turns(int $lineId, int $eraId): array
    {
        $rows = DB::select(<<<'SQL'
            WITH seg AS (
                SELECT s.id, ST_Transform(s.geom, 2154) AS g FROM line_segments s
                WHERE s.line_id = :line AND s.era_id = :era AND ST_Length(ST_Transform(s.geom, 2154)) >= 50
            ),
            q AS (
                SELECT seg.id, d.path[1] AS i, d.geom AS p
                FROM seg, LATERAL ST_DumpPoints(ST_LineInterpolatePoints(seg.g, GREATEST(10 / ST_Length(seg.g), 1.0 / 5000), true)) d
            ),
            t AS (
                SELECT id, i, degrees(abs(atan2(sin(a2 - a1), cos(a2 - a1)))) AS turn
                FROM (SELECT id, i, ST_Azimuth(lag(p, 2) OVER w, p) AS a1, ST_Azimuth(p, lead(p, 2) OVER w) AS a2
                      FROM q WINDOW w AS (PARTITION BY id ORDER BY i)) x
                WHERE a1 IS NOT NULL AND a2 IS NOT NULL
            ),
            f AS (
                SELECT id, i, turn, turn > :hairpin AS h, turn > :kink AS k,
                       lag(turn > :hairpin2) OVER w AS ph, lag(turn > :kink2) OVER w AS pk
                FROM t WINDOW w AS (PARTITION BY id ORDER BY i)
            )
            SELECT id, MAX(turn) AS max_turn,
                   COUNT(*) FILTER (WHERE h AND NOT COALESCE(ph, false)) AS hairpins,
                   COUNT(*) FILTER (WHERE k AND NOT h AND NOT COALESCE(pk, false)) AS kinks
            FROM f GROUP BY id
            SQL, ['line' => $lineId, 'era' => $eraId, 'hairpin' => AuditGrader::HAIRPIN_DEG, 'kink' => AuditGrader::KINK_DEG,
            'hairpin2' => AuditGrader::HAIRPIN_DEG, 'kink2' => AuditGrader::KINK_DEG]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = ['max_turn_deg' => round((float) $r->max_turn, 1), 'hairpins' => (int) $r->hairpins, 'kinks' => (int) $r->kinks];
        }

        return $out;
    }

    /**
     * Bouts libres des tronçons (aucun autre tronçon de la ligne à moins de HOLE_MIN_M) : écart au
     * tracé d'une autre ligne (jonction, entre JUNCTION_MIN_M et JUNCTION_MAX_M), et trou face à
     * un autre bout libre de la ligne (HOLE_MIN_M à HOLE_MAX_M, chacun tourné vers l'autre à 45°
     * près, milieu non couvert par un autre tronçon de l'ère), avec les traces non rattachées qui y passent.
     *
     * @return array<int, array{junction_gaps: list<array<string, mixed>>, holes: list<array<string, mixed>>}>
     */
    private function ends(int $lineId, int $eraId): array
    {
        $rows = DB::select(<<<'SQL'
            WITH seg AS MATERIALIZED (
                SELECT s.id, ST_Transform(s.geom, 2154) AS g FROM line_segments s WHERE s.line_id = :line AND s.era_id = :era
            ),
            e AS MATERIALIZED (
                SELECT seg.id, x.k, x.p,
                       ST_Azimuth(ST_LineInterpolatePoint(seg.g, CASE WHEN x.k = 0 THEN LEAST(0.5, 15 / GREATEST(ST_Length(seg.g), 0.01))
                                                                   ELSE GREATEST(0.5, 1 - 15 / GREATEST(ST_Length(seg.g), 0.01)) END), x.p) AS az
                FROM seg, LATERAL (VALUES (0, ST_StartPoint(seg.g)), (1, ST_EndPoint(seg.g))) x(k, p)
                WHERE NOT EXISTS (SELECT 1 FROM seg o WHERE o.id <> seg.id AND ST_DWithin(o.g, x.p, :hmin))
            ),
            j AS (
                SELECT e.id, e.k, o.d, o.slug, ST_Transform(e.p, 4326) AS p4
                FROM e
                CROSS JOIN LATERAL (
                    SELECT ST_Distance(ST_Transform(s.geom, 2154), e.p) AS d, l.slug
                    FROM line_segments s JOIN lines l ON l.id = s.line_id
                    WHERE s.era_id = :era2 AND s.line_id <> :line2 AND s.geom && ST_Expand(ST_Transform(e.p, 4326), 0.0002)
                    ORDER BY ST_Distance(ST_Transform(s.geom, 2154), e.p) LIMIT 1
                ) o
                WHERE o.d > :jmin AND o.d <= :jmax
            ),
            pairs AS (
                SELECT DISTINCT ON (a.id, a.k) a.id, a.k, b.id AS other, ST_Distance(a.p, b.p) AS gap,
                       ST_LineInterpolatePoint(ST_MakeLine(a.p, b.p), 0.5) AS mid
                FROM e a JOIN e b ON b.id <> a.id AND ST_DWithin(a.p, b.p, :hmax) AND NOT ST_DWithin(a.p, b.p, :hmin2)
                WHERE degrees(abs(atan2(sin(ST_Azimuth(a.p, b.p) - a.az), cos(ST_Azimuth(a.p, b.p) - a.az)))) <= 45
                  AND degrees(abs(atan2(sin(ST_Azimuth(b.p, a.p) - b.az), cos(ST_Azimuth(b.p, a.p) - b.az)))) <= 45
                ORDER BY a.id, a.k, ST_Distance(a.p, b.p)
            ),
            h AS (
                SELECT pairs.*, ST_Transform(pairs.mid, 4326) AS mid4 FROM pairs
                WHERE NOT EXISTS (SELECT 1 FROM line_segments s WHERE s.era_id = :era3 AND s.geom && ST_Expand(ST_Transform(pairs.mid, 4326), 0.0001)
                                  AND ST_DWithin(ST_Transform(s.geom, 2154), pairs.mid, 3))
            )
            SELECT 'junction' AS kind, j.id, j.k, j.d AS m, j.slug, NULL::bigint AS other, ST_X(j.p4) AS lon, ST_Y(j.p4) AS lat, NULL::text AS unused
            FROM j
            UNION ALL
            SELECT 'hole', h.id, h.k, h.gap, NULL, h.other, ST_X(h.mid4), ST_Y(h.mid4),
                   (SELECT string_agg(st.source || ':' || st.id, ',' ORDER BY st.id) FROM (
                        SELECT st.id, st.source FROM source_traces st
                        WHERE st.source IN ('osm', 'bdtopo') AND st.line_id IS NULL AND st.geom && ST_Expand(h.mid4, 0.0007)
                          AND ST_DWithin(ST_Transform(st.geom, 2154), h.mid, 50)
                          AND NOT EXISTS (SELECT 1 FROM source_trace_lines stl WHERE stl.source_trace_id = st.id)
                        LIMIT 5) st)
            FROM h
            SQL, ['line' => $lineId, 'era' => $eraId, 'hmin' => AuditGrader::HOLE_MIN_M, 'era2' => $eraId, 'line2' => $lineId,
            'jmin' => AuditGrader::JUNCTION_MIN_M, 'jmax' => AuditGrader::JUNCTION_MAX_M, 'hmax' => AuditGrader::HOLE_MAX_M,
            'hmin2' => AuditGrader::HOLE_MIN_M, 'era3' => $eraId]);
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            $out[$id] ??= ['junction_gaps' => [], 'holes' => []];
            $point = ['lon' => round((float) $r->lon, 6), 'lat' => round((float) $r->lat, 6)];
            if ($r->kind === 'junction') {
                $out[$id]['junction_gaps'][] = ['m' => round((float) $r->m, 2), 'other_line' => $r->slug] + $point;
            } else {
                $out[$id]['holes'][] = ['m' => round((float) $r->m, 1), 'other_segment' => (int) $r->other,
                    'unused_traces' => $r->unused !== null ? explode(',', $r->unused) : []] + $point;
            }
        }

        return $out;
    }

    /**
     * Gares de la ligne à l'ère (line_stops) : distance au tronçon le plus proche de la ligne, et
     * nombre d'inversions de l'ordre des PK le long du tracé assemblé (s'il forme une seule pièce).
     *
     * @return array{0: array<int, list<array<string, mixed>>>, 1: int}
     */
    private function stations(int $lineId, int $eraId): array
    {
        $rows = DB::select(<<<'SQL'
            WITH seg AS MATERIALIZED (
                SELECT s.id, ST_Transform(s.geom, 2154) AS g FROM line_segments s
                WHERE s.line_id = :line AND s.era_id = :era AND s.status <> 'service_track'
            ),
            merged AS (SELECT ST_LineMerge(ST_Collect(g)) AS g FROM seg)
            SELECT st.slug, st.name, ls.pk_m, n.id AS segment_id, n.d,
                   CASE WHEN GeometryType(merged.g) = 'LINESTRING' AND n.d <= :far
                        THEN ST_LineLocatePoint(merged.g, ST_Transform(st.geom, 2154)) * ST_Length(merged.g) END AS along_m
            FROM line_stops ls
            JOIN stations st ON st.id = ls.station_id
            CROSS JOIN merged
            CROSS JOIN LATERAL (SELECT seg.id, ST_Distance(seg.g, ST_Transform(st.geom, 2154)) AS d FROM seg
                                ORDER BY seg.g <-> ST_Transform(st.geom, 2154) LIMIT 1) n
            WHERE ls.line_id = :line2 AND ls.era_id = :era2
            ORDER BY ls.pk_m, ls.position
            SQL, ['line' => $lineId, 'era' => $eraId, 'far' => AuditGrader::STATION_FAR_M, 'line2' => $lineId, 'era2' => $eraId]);
        $bySegment = [];
        $along = [];
        foreach ($rows as $r) {
            $bySegment[(int) $r->segment_id][] = ['slug' => $r->slug, 'name' => $r->name, 'pk_m' => (int) $r->pk_m, 'distance_m' => round((float) $r->d, 1)];
            if ($r->along_m !== null) {
                $along[] = (float) $r->along_m;
            }
        }

        return [$bySegment, self::orderInversions($along)];
    }

    /**
     * Inversions de l'ordre des gares : positions le long du tracé, gares rangées par PK ; le sens
     * général est celui de la première à la dernière, un recul de plus de 200 m compte une inversion.
     *
     * @param  list<float>  $along
     */
    public static function orderInversions(array $along): int
    {
        if (count($along) < 3) {
            return 0;
        }
        $direction = end($along) >= $along[0] ? 1 : -1;
        $inversions = 0;
        for ($i = 1, $n = count($along); $i < $n; $i++) {
            if ($direction * ($along[$i] - $along[$i - 1]) < -200) {
                $inversions++;
            }
        }

        return $inversions;
    }

    /**
     * Faits propres à la ligne : longueur du tracé de référence et longueur officielle (fiche, à
     * défaut étendue des PK des événements de lignes-oubliées).
     *
     * @return array{reference_length_m: ?int, official_length_m: ?int, official_source: ?string}
     */
    private function lineFacts(object $line): array
    {
        $ref = DB::selectOne('SELECT SUM(length_m) AS m FROM line_reference_parts WHERE line_id = ?', [$line->id]);
        $official = $line->length_official_m !== null ? (int) $line->length_official_m : null;
        $source = $official !== null ? 'line' : null;
        if ($official === null) {
            $pk = DB::selectOne("SELECT MAX(GREATEST(section_pk_start_m, section_pk_end_m)) - MIN(LEAST(section_pk_start_m, section_pk_end_m)) AS m
                                 FROM line_events WHERE line_id = ? AND source = 'lignes-oubliees' AND section_pk_start_m IS NOT NULL AND section_pk_end_m IS NOT NULL", [$line->id]);
            if ($pk !== null && $pk->m !== null && (int) $pk->m > 1000) {
                $official = (int) $pk->m;
                $source = 'lignes-oubliees-pk';
            }
        }

        return ['reference_length_m' => $ref?->m !== null ? (int) $ref->m : null, 'official_length_m' => $official, 'official_source' => $source];
    }

    private static function round(?float $v): ?float
    {
        return $v === null ? null : round($v, 2);
    }
}
