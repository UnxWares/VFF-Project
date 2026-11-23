<?php

namespace App\Services;

use App\Models\Accident;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Le réseau en chiffres » : le réseau décennie par décennie, de 1830 à aujourd'hui (page /network et
 * `GET /api/v1/stats/network`).
 *
 * Les agrégats sont calculés par `vff:compute-network-figures` (aucune planification), rangés dans le
 * cache sans expiration avec leur date de calcul. Une lecture sans calcul préalable (cache vidé,
 * premier affichage) les calcule une fois. Lecture seule : aucune écriture en base.
 *
 * Une ligne par ère de la carte (instantané au 31 décembre de l'année, l'ère courante est aujourd'hui).
 * Les flux (événements datés, accidents) couvrent la période qui MÈNE à l'instantané : la ligne 1930 porte
 * 1921 à 1930, la ligne 2030 porte 2021 à aujourd'hui, pour lire un flux et le stock qu'il explique sur
 * la même ligne.
 *
 * Mesures :
 *   - longueurs : somme de `length_meters` des tronçons de la carte (sinon longueur géographique), voies
 *     parallèles comprises, donc un peu au-dessus des longueurs d'axe publiées par l'exploitant ;
 *   - ouvert = tronçon « en service » ou « voie unique » ; voyageurs = trafic voyageurs ou mixte (règle du
 *     site : voyageurs implique mixte, sauf ligne à grande vitesse) ; fret seul ; non précisé ;
 *   - fermé = « abandonné » ou « voie de service » ; déposé = « déposé » ou « arasé » (les tronçons en
 *     construction ne sont dans aucune des deux) ;
 *   - retiré du service / remis ou mis en service : écart de longueur ouverte, ligne par ligne, entre deux ères
 *     (une ligne qui perd 10 km compte 10 km retirés, une ligne nouvelle compte tous ses km mis en service) ;
 *   - événements : ouvertures, réouvertures, fermetures et dépôts datés des chronologies de lignes, sans
 *     ceux dont la date n'est qu'une année tirée du nom d'un tracé (un lot de 373 fermetures en 1918 venait de là).
 */
final class NetworkFigures
{
    public const CACHE_KEY = 'network_figures:v1';

    private const OPEN = "('in_service','single_track')";

    private const CLOSED = "('abandoned','service_track')";

    private const GONE = "('dismantled','razed')";

    /** Types d'événements comptés. */
    private const EVENT_GROUPS = [
        'opened' => ['opening', 'opening_passenger', 'opening_freight'],
        'reopened' => ['reopening', 'reopening_passenger', 'reopening_freight'],
        'closed' => ['closure', 'closure_passenger', 'closure_freight'],
        'closed_passenger' => ['closure_passenger'],
        'closed_freight' => ['closure_freight'],
        'dismantled' => ['dismantling'],
    ];

    /** Dernier calcul rangé dans le cache, ou calcul si le cache est vide. */
    public function get(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : $this->refresh();
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
        $eras = DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']);
        $rows = [];
        $previousYear = null;
        foreach ($eras as $era) {
            $rows[(int) $era->year] = [
                'year' => (int) $era->year,
                'current' => (bool) $era->is_current,
                'from_year' => $previousYear === null ? null : $previousYear + 1,
                'open_km' => 0, 'open_passenger_km' => 0, 'open_freight_km' => 0, 'open_unknown_km' => 0,
                'closed_km' => 0, 'gone_km' => 0,
                'electrified_km' => 0, 'electrified_share' => null,
                'removed_km' => 0, 'added_km' => 0,
                'stations' => ['active' => 0, 'tourist' => 0, 'no_commercial' => 0, 'declassified' => 0],
                'events' => array_fill_keys(array_keys(self::EVENT_GROUPS), 0),
                'accidents' => ['count' => 0, 'deaths' => 0, 'deaths_known' => 0],
            ];
            $previousYear = (int) $era->year;
        }
        $yearOf = $eras->pluck('year', 'id')->map(fn ($y): int => (int) $y)->all();

        $this->lengths($rows, $yearOf);
        $this->movements($rows, $yearOf);
        $this->stations($rows, $yearOf);
        $this->events($rows);
        $this->accidents($rows);

        foreach ($rows as &$row) {
            $row['electrified_share'] = $row['open_km'] > 0 ? round($row['electrified_km'] / $row['open_km'], 4) : null;
        }
        unset($row);
        $list = array_values($rows);

        return [
            'computed_at' => now()->toIso8601String(),
            'decades' => $list,
            'tracks' => $this->tracks(),
            'waves' => $this->waves($list),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $yearOf
     */
    private function lengths(array &$rows, array $yearOf): void
    {
        $len = 'COALESCE(length_meters, ST_Length(geom::geography))';
        $open = self::OPEN;
        $closed = self::CLOSED;
        $gone = self::GONE;
        $sql = "SELECT era_id,
                SUM(CASE WHEN status IN {$open} THEN {$len} ELSE 0 END) AS open_m,
                SUM(CASE WHEN status IN {$open} AND traffic_type IN ('passenger','mixed') THEN {$len} ELSE 0 END) AS passenger_m,
                SUM(CASE WHEN status IN {$open} AND traffic_type = 'freight' THEN {$len} ELSE 0 END) AS freight_m,
                SUM(CASE WHEN status IN {$open} AND traffic_type NOT IN ('passenger','mixed','freight') THEN {$len} ELSE 0 END) AS unknown_m,
                SUM(CASE WHEN status IN {$closed} THEN {$len} ELSE 0 END) AS closed_m,
                SUM(CASE WHEN status IN {$gone} THEN {$len} ELSE 0 END) AS gone_m,
                SUM(CASE WHEN status IN {$open} AND electrified THEN {$len} ELSE 0 END) AS electrified_m
            FROM line_segments GROUP BY era_id";
        foreach (DB::select($sql) as $r) {
            $year = $yearOf[(int) $r->era_id] ?? null;
            if ($year === null) {
                continue;
            }
            $rows[$year]['open_km'] = $this->km($r->open_m);
            $rows[$year]['open_passenger_km'] = $this->km($r->passenger_m);
            $rows[$year]['open_freight_km'] = $this->km($r->freight_m);
            $rows[$year]['open_unknown_km'] = $this->km($r->unknown_m);
            $rows[$year]['closed_km'] = $this->km($r->closed_m);
            $rows[$year]['gone_km'] = $this->km($r->gone_m);
            $rows[$year]['electrified_km'] = $this->km($r->electrified_m);
        }
    }

    /**
     * Kilomètres retirés du service et mis en service entre deux ères, ligne par ligne.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $yearOf
     */
    private function movements(array &$rows, array $yearOf): void
    {
        $open = self::OPEN;
        $len = 'COALESCE(length_meters, ST_Length(geom::geography))';
        $sql = "WITH per AS (
                SELECT era_id, line_id, COALESCE(SUM({$len}) FILTER (WHERE status IN {$open}), 0) AS open_m
                FROM line_segments GROUP BY era_id, line_id
            ), lagged AS (
                SELECT p.era_id, p.open_m,
                       COALESCE(LAG(p.open_m) OVER (PARTITION BY p.line_id ORDER BY e.year), 0) AS prev_m
                FROM per p JOIN eras e ON e.id = p.era_id
            )
            SELECT era_id, SUM(GREATEST(prev_m - open_m, 0)) AS removed_m, SUM(GREATEST(open_m - prev_m, 0)) AS added_m
            FROM lagged GROUP BY era_id";
        foreach (DB::select($sql) as $r) {
            $year = $yearOf[(int) $r->era_id] ?? null;
            if ($year !== null) {
                $rows[$year]['removed_km'] = $this->km($r->removed_m);
                $rows[$year]['added_km'] = $this->km($r->added_m);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $yearOf
     */
    private function stations(array &$rows, array $yearOf): void
    {
        foreach (DB::select('SELECT era_id, status, COUNT(*) AS n FROM station_eras GROUP BY era_id, status') as $r) {
            $year = $yearOf[(int) $r->era_id] ?? null;
            if ($year !== null && array_key_exists($r->status, $rows[$year]['stations'])) {
                $rows[$year]['stations'][$r->status] = (int) $r->n;
            }
        }
    }

    /**
     * Événements datés par période. Une date qui n'est qu'une année tirée du nom d'un tracé n'est pas comptée.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function events(array &$rows): void
    {
        $types = array_values(array_unique(array_merge(...array_values(self::EVENT_GROUPS))));
        $marks = implode(',', array_fill(0, count($types), '?'));
        $last = max(array_keys($rows));
        $first = min(array_keys($rows));
        $sql = "SELECT GREATEST(LEAST(CEIL(EXTRACT(YEAR FROM event_date) / 10.0) * 10, {$last}), {$first})::int AS period, type, COUNT(*) AS n
            FROM line_events
            WHERE type IN ({$marks}) AND NOT (source = 'archeoferroviaire' AND date_precision = 'year')
            GROUP BY 1, 2";
        foreach (DB::select($sql, $types) as $r) {
            if (! isset($rows[(int) $r->period])) {
                continue;
            }
            foreach (self::EVENT_GROUPS as $group => $members) {
                if (in_array($r->type, $members, true)) {
                    $rows[(int) $r->period]['events'][$group] += (int) $r->n;
                }
            }
        }
        // closed contient déjà closed_passenger et closed_freight : ils s'y lisent comme « dont ».
    }

    /**
     * Accidents publiés (mêmes règles que la liste : sans doublons ni heurts non confirmés) par période.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function accidents(array &$rows): void
    {
        $last = max(array_keys($rows));
        $first = min(array_keys($rows));
        $found = Accident::query()
            ->selectRaw("GREATEST(LEAST(CEIL(EXTRACT(YEAR FROM occurred_on) / 10.0) * 10, {$last}), {$first})::int AS period")
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(deaths), 0) AS deaths, COUNT(deaths) AS known')
            ->groupBy('period')->toBase()->get();
        foreach ($found as $r) {
            if (isset($rows[(int) $r->period])) {
                $rows[(int) $r->period]['accidents'] = ['count' => (int) $r->n, 'deaths' => (int) $r->deaths, 'deaths_known' => (int) $r->known];
            }
        }
    }

    /**
     * Voies principales du réseau d'aujourd'hui (régime d'exploitation de SNCF Réseau) : null tant que le jeu n'est pas importé.
     *
     * @return array{double_km: int, single_km: int, unspecified_km: int, double_share: float|null}|null
     */
    private function tracks(): ?array
    {
        if (! Schema::hasTable('line_track_portions')) {
            return null;
        }
        $r = DB::selectOne(
            'SELECT COALESCE(SUM(pk_end_m - pk_start_m) FILTER (WHERE tracks = 2), 0) AS double_m,
                    COALESCE(SUM(pk_end_m - pk_start_m) FILTER (WHERE tracks = 1), 0) AS single_m,
                    COALESCE(SUM(pk_end_m - pk_start_m) FILTER (WHERE tracks IS NULL), 0) AS unspecified_m
             FROM line_track_portions WHERE pk_start_m IS NOT NULL AND pk_end_m > pk_start_m',
        );
        $double = $this->km($r->double_m);
        $single = $this->km($r->single_m);
        if ($double + $single === 0) {
            return null;
        }

        return [
            'double_km' => $double,
            'single_km' => $single,
            'unspecified_km' => $this->km($r->unspecified_m),
            'double_share' => round($double / ($double + $single), 4),
        ];
    }

    /**
     * Les trois plus fortes vagues de retrait du service (km), et les périodes qui comptent le plus de
     * réouvertures datées.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{closures: list<array{year: int, removed_km: int, closed_events: int}>, reopenings: list<array{year: int, reopened_events: int, added_km: int}>}
     */
    private function waves(array $rows): array
    {
        $closures = collect($rows)->sortByDesc('removed_km')->filter(fn (array $r): bool => $r['removed_km'] > 0)->take(3)
            ->map(fn (array $r): array => ['year' => $r['year'], 'removed_km' => $r['removed_km'], 'closed_events' => $r['events']['closed']])->values()->all();
        $reopenings = collect($rows)->sortByDesc(fn (array $r): int => $r['events']['reopened'])->filter(fn (array $r): bool => $r['events']['reopened'] > 0)->take(3)
            ->map(fn (array $r): array => ['year' => $r['year'], 'reopened_events' => $r['events']['reopened'], 'added_km' => $r['added_km']])->values()->all();

        return ['closures' => $closures, 'reopenings' => $reopenings];
    }

    private function km(mixed $meters): int
    {
        return (int) round(((float) $meters) / 1000);
    }
}
