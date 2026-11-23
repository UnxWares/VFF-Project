<?php

namespace App\Services;

use App\Enums\AccidentKind;
use App\Support\SourceRights;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Ce que sait l'encyclopédie » (page /network/coverage et `GET /api/v1/stats/coverage`) : l'état des données,
 * mesuré, en nombre et en pourcentage, pour les lignes, les gares, les accidents et le matériel.
 *
 * Les agrégats sont calculés par `vff:compute-coverage-figures` (aucune planification) et rangés dans le cache
 * sans expiration avec leur date de calcul. Lecture seule : aucune écriture en base. Les tables facultatives
 * (fréquentation, dessertes, patrimoine, accessibilité) peuvent manquer : la mesure est alors absente
 * (`available` à faux), jamais une erreur. Chaque requête est bornée à 30 s.
 *
 * Mesures (chaque `metric` donne `total` : l'ensemble concerné, `done` : ce qui est renseigné, `share` : la part,
 * 0 à 1, et `open` : ce qui est renseigné à partir de sources sous licence ouverte écrite, ou `null` si la mesure
 * ne sait pas le dire). Une source non déclarée dans le registre des droits n'est jamais comptée ouverte.
 *
 *   - ouverture et fermeture des lignes : la meilleure précision d'une date d'événement de la ligne (jour,
 *     mois, année, aucune) ; fermetures : lignes dont une partie est fermée ou déposée aujourd'hui ;
 *   - tracé précis : kilomètres dont la note de tracé de l'audit est « bonne » (source à moins de 3 m ou tracé
 *     recalé sur une voie de référence), sur tous les kilomètres audités de l'ère ;
 *   - électrification, signalisation, vitesses, ouvrages : lignes concernées aujourd'hui et lignes documentées.
 */
final class CoverageFigures
{
    public const CACHE_KEY = 'coverage_figures:v1';

    private const OPEN = "('in_service','single_track')";

    private const CLOSED_OR_GONE = "('abandoned','service_track','dismantled','razed')";

    private const OPENINGS = "('opening','opening_passenger','opening_freight')";

    private const CLOSINGS = "('closure','closure_passenger','closure_freight','dismantling')";

    public function __construct(private readonly SourceRights $rights) {}

    /**
     * Dernier calcul rangé dans le cache, ou calcul si le cache est vide (une requête à la fois).
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        return Cache::lock(self::CACHE_KEY.':lock', 240)->block(120, function (): array {
            $cached = Cache::get(self::CACHE_KEY);

            return is_array($cached) ? $cached : $this->refresh();
        });
    }

    /** Recalcule et range le résultat (sans expiration). */
    public function refresh(): array
    {
        $figures = $this->compute();
        Cache::forever(self::CACHE_KEY, $figures);

        return $figures;
    }

    /** Calcul complet, sans toucher au cache. */
    public function compute(): array
    {
        DB::statement('SET statement_timeout = 30000');
        try {
            $eras = DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']);
            $current = $eras->firstWhere('is_current', true) ?? $eras->last();

            return [
                'computed_at' => now()->toIso8601String(),
                'lines' => $this->lines($current?->id === null ? 0 : (int) $current->id),
                'stations' => $this->stations(),
                'accidents' => $this->accidents(),
                'materials' => $this->materials(),
                'eras' => $this->eras($eras->all()),
            ];
        } finally {
            DB::statement('RESET statement_timeout');
        }
    }

    /** @return array<string, mixed> */
    private function metric(string $key, string $unit, int $total, int $done, ?int $open = null, bool $available = true): array
    {
        return [
            'key' => $key,
            'unit' => $unit,
            'available' => $available,
            'total' => $total,
            'done' => $done,
            'share' => $total > 0 ? round($done / $total, 4) : null,
            'open' => $open,
        ];
    }

    /** @return array<string, mixed> mesure d'une table facultative absente */
    private function missing(string $key, string $unit): array
    {
        return $this->metric($key, $unit, 0, 0, null, false);
    }

    private function one(string $sql): int
    {
        return (int) (DB::selectOne($sql)->n ?? 0);
    }

    /**
     * Ventilation par précision de date (jour, mois, année, aucune) d'une liste de lignes ou de gares.
     *
     * @param  array<string, array{0: int, 1: int}>  $rows  niveau => [nombre, dont sources ouvertes]
     * @return array<string, mixed>
     */
    private function levels(string $key, string $unit, array $rows): array
    {
        $total = array_sum(array_map(fn (array $r): int => $r[0], $rows));
        $levels = [];
        $dated = 0;
        $open = 0;
        foreach ($rows as $level => [$count, $openCount]) {
            $levels[] = [
                'level' => $level,
                'count' => $count,
                'share' => $total > 0 ? round($count / $total, 4) : null,
                'open' => $level === 'none' ? null : $openCount,
            ];
            if ($level !== 'none') {
                $dated += $count;
                $open += $openCount;
            }
        }
        $metric = $this->metric($key, $unit, $total, $dated, $open);
        $metric['levels'] = $levels;

        return $metric;
    }

    /** @return array{metrics: list<array<string, mixed>>} */
    private function lines(int $currentEra): array
    {
        $openCond = $this->rights->sqlCondition('line_events', 'sur', 'e.source', 'e.source_url');
        $rank = "CASE e.date_precision WHEN 'day' THEN 1 WHEN 'month' THEN 2 ELSE 3 END";
        $types = [
            'opening' => [self::OPENINGS, 'SELECT id AS line_id FROM lines'],
            'closure' => [self::CLOSINGS, 'SELECT DISTINCT line_id FROM line_segments WHERE era_id = '.$currentEra.' AND status IN '.self::CLOSED_OR_GONE],
        ];
        $metrics = [];
        foreach ($types as $key => [$list, $universe]) {
            $sql = "WITH universe AS ({$universe}), best AS (
                    SELECT e.line_id, MIN({$rank}) AS r_all,
                           MIN({$rank}) FILTER (WHERE {$openCond}) AS r_open
                    FROM line_events e JOIN universe u ON u.line_id = e.line_id
                    WHERE e.type IN {$list} AND e.event_date IS NOT NULL GROUP BY e.line_id
                )
                SELECT (SELECT COUNT(*) FROM universe) AS total,
                       COUNT(*) FILTER (WHERE r_all = 1) AS day, COUNT(*) FILTER (WHERE r_all = 1 AND r_open = 1) AS day_open,
                       COUNT(*) FILTER (WHERE r_all = 2) AS month, COUNT(*) FILTER (WHERE r_all = 2 AND r_open = 2) AS month_open,
                       COUNT(*) FILTER (WHERE r_all = 3) AS year, COUNT(*) FILTER (WHERE r_all = 3 AND r_open = 3) AS year_open
                FROM best";
            $r = DB::selectOne($sql);
            $none = max(0, (int) $r->total - (int) $r->day - (int) $r->month - (int) $r->year);
            $metrics[] = $this->levels($key.'_dated', 'lines', [
                'day' => [(int) $r->day, (int) $r->day_open],
                'month' => [(int) $r->month, (int) $r->month_open],
                'year' => [(int) $r->year, (int) $r->year_open],
                'none' => [$none, 0],
            ]);
        }

        $metrics[] = $this->trace($currentEra);

        // Électrification datée : lignes électrifiées aujourd'hui, avec une année de mise sous tension ou un événement daté.
        $metrics[] = $this->metric('electrification_dated', 'lines',
            $this->one("SELECT COUNT(DISTINCT line_id) AS n FROM line_segments WHERE era_id = {$currentEra} AND electrified AND status IN ".self::OPEN),
            $this->one("SELECT COUNT(*) AS n FROM (
                SELECT DISTINCT s.line_id FROM line_segments s
                WHERE s.era_id = {$currentEra} AND s.electrified AND s.status IN ".self::OPEN.' AND (s.electrified_year IS NOT NULL OR EXISTS (
                    SELECT 1 FROM line_events e WHERE e.line_id = s.line_id AND e.type = \'electrification\' AND e.event_date IS NOT NULL))
            ) t'));

        $signalling = $this->rights->sqlCondition('line_signalling_events', 'sur', 'source', 'source_url');
        $metrics[] = $this->metric('signalling_dated', 'lines',
            $this->one("SELECT COUNT(DISTINCT line_id) AS n FROM line_signalling WHERE era_id = {$currentEra} AND signalling IS NOT NULL"),
            $this->one("SELECT COUNT(DISTINCT s.line_id) AS n FROM line_signalling s WHERE s.era_id = {$currentEra} AND s.signalling IS NOT NULL
                AND EXISTS (SELECT 1 FROM line_signalling_events v WHERE v.line_id = s.line_id AND v.event_date IS NOT NULL)"),
            $this->one("SELECT COUNT(DISTINCT s.line_id) AS n FROM line_signalling s WHERE s.era_id = {$currentEra} AND s.signalling IS NOT NULL
                AND EXISTS (SELECT 1 FROM line_signalling_events v WHERE v.line_id = s.line_id AND v.event_date IS NOT NULL AND {$signalling})"));

        $inService = "SELECT DISTINCT line_id FROM line_segments WHERE era_id = {$currentEra} AND status IN ".self::OPEN;
        $metrics[] = $this->metric('speeds', 'lines',
            $this->one("SELECT COUNT(*) AS n FROM ({$inService}) t"),
            $this->one("SELECT COUNT(*) AS n FROM ({$inService}) t WHERE EXISTS (SELECT 1 FROM line_speed_ranges r WHERE r.line_id = t.line_id)
                OR EXISTS (SELECT 1 FROM line_segments s WHERE s.line_id = t.line_id AND s.era_id = {$currentEra} AND s.status IN ".self::OPEN.' AND s.max_speed_kmh IS NOT NULL)'));
        $metrics[] = $this->metric('structures', 'lines',
            $this->one("SELECT COUNT(*) AS n FROM ({$inService}) t"),
            $this->one("SELECT COUNT(*) AS n FROM ({$inService}) t WHERE EXISTS (SELECT 1 FROM line_structures x WHERE x.line_id = t.line_id)"));

        return ['metrics' => $metrics];
    }

    /** Tracé précis : kilomètres audités dont la note de tracé est « bonne », à l'ère courante. */
    private function trace(int $currentEra): array
    {
        $open = $this->rights->sqlCondition('source_traces', 'sur', 'trace_source', null);
        $r = DB::selectOne("SELECT COALESCE(SUM(length_m), 0) AS total, COALESCE(SUM(length_m) FILTER (WHERE trace_grade = 'good'), 0) AS good,
                COALESCE(SUM(length_m) FILTER (WHERE trace_grade = 'good' AND {$open}), 0) AS good_open
            FROM segment_audits WHERE era_id = {$currentEra}");

        return $this->metric('trace_precise', 'km', (int) round($r->total / 1000), (int) round($r->good / 1000), (int) round($r->good_open / 1000));
    }

    /** @return array{metrics: list<array<string, mixed>>} */
    private function stations(): array
    {
        $openCond = $this->rights->sqlCondition('station_events', 'sur', 'e.source', 'e.source_url');
        $metrics = [];

        $r = DB::selectOne("SELECT
            COUNT(*) FILTER (WHERE s.opened_at IS NOT NULL AND s.opened_precision = 'day') AS day,
            COUNT(*) FILTER (WHERE s.opened_at IS NOT NULL AND s.opened_precision = 'month') AS month,
            COUNT(*) FILTER (WHERE s.opened_at IS NOT NULL AND COALESCE(s.opened_precision, 'year') NOT IN ('day','month')) AS year,
            COUNT(*) FILTER (WHERE s.opened_at IS NULL) AS none,
            COUNT(*) FILTER (WHERE s.opened_at IS NOT NULL AND s.opened_precision = 'day' AND EXISTS (SELECT 1 FROM station_events e WHERE e.station_id = s.id AND e.type = 'opening' AND e.date_precision = 'day' AND {$openCond})) AS day_open,
            COUNT(*) FILTER (WHERE s.opened_at IS NOT NULL AND s.opened_precision = 'month' AND EXISTS (SELECT 1 FROM station_events e WHERE e.station_id = s.id AND e.type = 'opening' AND e.date_precision = 'month' AND {$openCond})) AS month_open,
            COUNT(*) FILTER (WHERE s.opened_at IS NOT NULL AND COALESCE(s.opened_precision, 'year') NOT IN ('day','month') AND EXISTS (SELECT 1 FROM station_events e WHERE e.station_id = s.id AND e.type = 'opening' AND {$openCond})) AS year_open
            FROM stations s");
        $metrics[] = $this->levels('opening_dated', 'stations', [
            'day' => [(int) $r->day, (int) $r->day_open],
            'month' => [(int) $r->month, (int) $r->month_open],
            'year' => [(int) $r->year, (int) $r->year_open],
            'none' => [(int) $r->none, 0],
        ]);

        $r = DB::selectOne("SELECT
            COUNT(*) FILTER (WHERE s.closed_at IS NOT NULL AND s.closed_precision = 'day') AS day,
            COUNT(*) FILTER (WHERE s.closed_at IS NOT NULL AND s.closed_precision = 'month') AS month,
            COUNT(*) FILTER (WHERE s.closed_at IS NOT NULL AND COALESCE(s.closed_precision, 'year') NOT IN ('day','month')) AS year,
            COUNT(*) FILTER (WHERE s.closed_at IS NULL) AS none,
            COUNT(*) FILTER (WHERE s.closed_at IS NOT NULL AND s.closed_precision = 'day' AND EXISTS (SELECT 1 FROM station_events e WHERE e.station_id = s.id AND e.type LIKE 'closure%' AND e.date_precision = 'day' AND {$openCond})) AS day_open,
            COUNT(*) FILTER (WHERE s.closed_at IS NOT NULL AND s.closed_precision = 'month' AND EXISTS (SELECT 1 FROM station_events e WHERE e.station_id = s.id AND e.type LIKE 'closure%' AND e.date_precision = 'month' AND {$openCond})) AS month_open,
            COUNT(*) FILTER (WHERE s.closed_at IS NOT NULL AND COALESCE(s.closed_precision, 'year') NOT IN ('day','month') AND EXISTS (SELECT 1 FROM station_events e WHERE e.station_id = s.id AND e.type LIKE 'closure%' AND {$openCond})) AS year_open
            FROM stations s WHERE s.lifecycle <> 'active'");
        $metrics[] = $this->levels('closure_dated', 'stations', [
            'day' => [(int) $r->day, (int) $r->day_open],
            'month' => [(int) $r->month, (int) $r->month_open],
            'year' => [(int) $r->year, (int) $r->year_open],
            'none' => [(int) $r->none, 0],
        ]);

        $all = $this->one('SELECT COUNT(*) AS n FROM stations');
        $active = $this->one("SELECT COUNT(*) AS n FROM stations WHERE lifecycle = 'active'");

        $mediaOpen = "m.rights_status IN ('open_license','public_domain') AND m.commercial_ok";
        $photo = "m.mediable_type = 'App\\Models\\Station' AND m.type = 'photo'";
        $metrics[] = $this->metric('photo', 'stations', $all,
            $this->one("SELECT COUNT(DISTINCT m.mediable_id) AS n FROM media m WHERE {$photo}"),
            $this->one("SELECT COUNT(DISTINCT m.mediable_id) AS n FROM media m WHERE {$photo} AND {$mediaOpen}"));

        $metrics[] = Schema::hasTable('station_attendances')
            ? $this->metric('attendance', 'stations', $active,
                $this->one("SELECT COUNT(DISTINCT a.station_id) AS n FROM station_attendances a JOIN stations s ON s.id = a.station_id WHERE s.lifecycle = 'active'"))
            : $this->missing('attendance', 'stations');

        $metrics[] = Schema::hasTable('station_services')
            ? $this->metric('services', 'stations', $active,
                $this->one("SELECT COUNT(DISTINCT v.station_id) AS n FROM station_services v JOIN stations s ON s.id = v.station_id WHERE s.lifecycle = 'active' AND v.service_class <> 'unknown'"))
            : $this->missing('services', 'stations');

        $metrics[] = Schema::hasTable('station_heritage')
            ? $this->metric('heritage', 'stations', $all, $this->one('SELECT COUNT(DISTINCT station_id) AS n FROM station_heritage'))
            : $this->missing('heritage', 'stations');

        $metrics[] = Schema::hasTable('station_accessibility')
            ? $this->metric('accessibility', 'stations', $active,
                $this->one("SELECT COUNT(DISTINCT x.station_id) AS n FROM station_accessibility x JOIN stations s ON s.id = x.station_id WHERE s.lifecycle = 'active'"))
            : $this->missing('accessibility', 'stations');

        $metrics[] = $this->metric('former_names', 'stations', $all,
            $this->one("SELECT COUNT(*) AS n FROM stations WHERE jsonb_typeof(alt_names) = 'array' AND jsonb_array_length(alt_names) > 0"));

        return ['metrics' => $metrics];
    }

    /** @return array{metrics: list<array<string, mixed>>, by_decade: list<array<string, int>>} */
    private function accidents(): array
    {
        // Les accidents que le site publie (même filtre que App\Models\Scopes\PublicAccidents).
        $base = 'FROM accidents WHERE alias_of_id IS NULL AND '.AccidentKind::publishableSql('accidents');
        $total = $this->one("SELECT COUNT(*) AS n {$base}");
        $metrics = [
            $this->metric('reviewed', 'accidents', $total, $this->one("SELECT COUNT(*) AS n {$base} AND review_status = 'ok'")),
        ];
        $byDecade = [];
        foreach (DB::select("SELECT (EXTRACT(YEAR FROM occurred_on)::int / 10) * 10 AS decade, COUNT(*) AS total,
                COUNT(*) FILTER (WHERE review_status = 'ok') AS reviewed {$base} AND occurred_on IS NOT NULL GROUP BY 1 ORDER BY 1") as $r) {
            $byDecade[] = ['decade' => (int) $r->decade, 'total' => (int) $r->total, 'reviewed' => (int) $r->reviewed];
        }

        return ['metrics' => $metrics, 'by_decade' => $byDecade];
    }

    /** @return array{metrics: list<array<string, mixed>>, by_decade: list<array<string, int>>} */
    private function materials(): array
    {
        $total = $this->one('SELECT COUNT(*) AS n FROM materials');
        $metrics = [
            $this->metric('dated', 'series', $total, $this->one('SELECT COUNT(*) AS n FROM materials WHERE in_service_from IS NOT NULL')),
            $this->metric('linked', 'series', $total, $this->one('SELECT COUNT(DISTINCT material_id) AS n FROM line_materials')),
            $this->metric('image', 'series', $total, $this->one('SELECT COUNT(*) AS n FROM materials WHERE image IS NOT NULL')),
        ];
        $byDecade = [];
        foreach (DB::select('SELECT (in_service_from / 10) * 10 AS decade, COUNT(*) AS total FROM materials WHERE in_service_from IS NOT NULL GROUP BY 1 ORDER BY 1') as $r) {
            $byDecade[] = ['decade' => (int) $r->decade, 'total' => (int) $r->total];
        }

        return ['metrics' => $metrics, 'by_decade' => $byDecade];
    }

    /**
     * Par époque : lignes ouvertes dont l'ouverture est datée au jour ou au mois, kilomètres au tracé précis,
     * gares ouvertes dont l'ouverture est datée au jour ou au mois.
     *
     * @param  list<object>  $eras
     * @return list<array<string, mixed>>
     */
    private function eras(array $eras): array
    {
        $rank = "CASE e.date_precision WHEN 'day' THEN 1 WHEN 'month' THEN 2 ELSE 3 END";
        $lineRows = [];
        foreach (DB::select("WITH best AS (
                SELECT e.line_id, MIN({$rank}) AS r FROM line_events e
                WHERE e.type IN ".self::OPENINGS.' AND e.event_date IS NOT NULL GROUP BY e.line_id
            )
            SELECT s.era_id, COUNT(DISTINCT s.line_id) AS lines,
                   COUNT(DISTINCT s.line_id) FILTER (WHERE b.r = 1) AS day, COUNT(DISTINCT s.line_id) FILTER (WHERE b.r = 2) AS month
            FROM line_segments s LEFT JOIN best b ON b.line_id = s.line_id
            WHERE s.status IN '.self::OPEN.' GROUP BY s.era_id') as $r) {
            $lineRows[(int) $r->era_id] = $r;
        }
        $traceRows = [];
        foreach (DB::select("SELECT era_id, COALESCE(SUM(length_m), 0) AS total, COALESCE(SUM(length_m) FILTER (WHERE trace_grade = 'good'), 0) AS good
            FROM segment_audits GROUP BY era_id") as $r) {
            $traceRows[(int) $r->era_id] = $r;
        }
        $stationRows = [];
        foreach (DB::select("SELECT se.era_id, COUNT(*) AS stations,
                COUNT(*) FILTER (WHERE s.opened_at IS NOT NULL AND s.opened_precision = 'day') AS day,
                COUNT(*) FILTER (WHERE s.opened_at IS NOT NULL AND s.opened_precision = 'month') AS month
            FROM station_eras se JOIN stations s ON s.id = se.station_id WHERE se.status = 'active' GROUP BY se.era_id") as $r) {
            $stationRows[(int) $r->era_id] = $r;
        }

        $out = [];
        foreach ($eras as $era) {
            $id = (int) $era->id;
            $l = $lineRows[$id] ?? null;
            $t = $traceRows[$id] ?? null;
            $s = $stationRows[$id] ?? null;
            $out[] = [
                'year' => (int) $era->year,
                'current' => (bool) $era->is_current,
                'lines' => $l === null ? 0 : (int) $l->lines,
                'lines_precise_opening' => $l === null ? 0 : (int) $l->day + (int) $l->month,
                'trace_km' => $t === null ? 0 : (int) round($t->total / 1000),
                'trace_precise_km' => $t === null ? 0 : (int) round($t->good / 1000),
                'stations' => $s === null ? 0 : (int) $s->stations,
                'stations_precise_opening' => $s === null ? 0 : (int) $s->day + (int) $s->month,
            ];
        }

        return $out;
    }
}
