<?php

namespace App\Services;

use App\Services\NetworkGraph\RouteException;
use App\Services\TraceExport\LineTraceExport;
use Illuminate\Support\Facades\DB;
use SplPriorityQueue;

/**
 * Itinéraires historiques sur le graphe gare-à-gare d'une ère (`station_edges`,
 * construit par vff:build-network-graph à partir des seules portions en
 * service et des gares ouvertes à cette ère).
 *
 *   - année quelconque → ère : celle de l'année si elle existe, sinon la
 *     décennie précédente (ère Y = état au 31/12/Y) ; à partir de l'année en
 *     cours, l'état actuel ;
 *   - départ / arrivée : slug, identifiant, nom exact, ou nom de ville
 *     (« Paris » = toutes les gares « Paris-… » ouvertes : départ multiple) ;
 *   - coût = longueur, les voies sans service voyageurs (fret seul, réseaux
 *     militaire ou particulier) comptant `NON_PASSENGER_FACTOR` fois (mode
 *     voyageurs, par défaut) ; voies associatives exclues sauf demande.
 */
class PathfindingService
{
    public const NON_PASSENGER_FACTOR = 4.0;

    /** Écart maximal entre les gares d'un même nom de ville (km). */
    private const CITY_SPREAD_KM = 30.0;

    /** Morceau de ligne trop court pour être cité seul (m) : fondu dans le voisin. */
    private const MIN_LEG_M = 1000;

    /**
     * Section gare à gare retenue (règle des droits) : elle longe un tronçon non redistribuable,
     * à BUFFER près (degrés, environ 3 m), sur plus de OVERLAP mètres.
     */
    private const WITHHELD_BUFFER_DEG = 0.00003;

    private const WITHHELD_OVERLAP_M = 10;

    public function __construct(private readonly LineTraceExport $traceRights) {}

    /**
     * Compatibilité (GraphQL) : itinéraire ou null.
     *
     * @return array<string, mixed>|null
     */
    public function shortestPathBetweenStations(string $fromSlug, string $toSlug, ?int $eraYear = null): ?array
    {
        try {
            return $this->route($fromSlug, $toSlug, $eraYear);
        } catch (RouteException) {
            return null;
        }
    }

    /**
     * @param  array{heritage?: bool, traffic?: string, display?: bool}  $options  `display` : tracé complet
     *                                                                             pour la carte du site (jamais par l'API)
     * @return array<string, mixed>
     *
     * @throws RouteException
     */
    public function route(string $from, string $to, ?int $year = null, array $options = []): array
    {
        ['era' => $era, 'build' => $build, 'path' => $path, 'heritage' => $heritage, 'factor' => $factor] = $this->findPath($from, $to, $year, $options);

        return $this->describe($era, $build, $path, $year, $heritage, $factor, (bool) ($options['display'] ?? false));
    }

    /**
     * Plus court chemin brut (gares et arêtes gare à gare dans l'ordre du trajet), sans description : sert à
     * route() et au livret de parcours (Routebook), qui relit lui-même les arêtes.
     *
     * @param  array{heritage?: bool, traffic?: string}  $options
     * @return array{era: object, build: object, path: array{cost: float, stations: list<int>, edges: list<int>}, heritage: bool, factor: float}
     *
     * @throws RouteException
     */
    public function findPath(string $from, string $to, ?int $year = null, array $options = []): array
    {
        $era = $this->resolveEra($year);
        $build = DB::table('network_graph_builds')->where('era_id', $era->id)->first();
        if (! $build) {
            throw new RouteException('graph_not_built', 409,
                "Le graphe ferroviaire de {$era->year} n'est pas encore construit (vff:build-network-graph --era={$era->year}).",
                ['era_year' => (int) $era->year]);
        }

        $heritage = (bool) ($options['heritage'] ?? false);
        $factor = ($options['traffic'] ?? 'passenger') === 'all' ? 1.0 : self::NON_PASSENGER_FACTOR;
        $adj = $this->loadAdjacency((int) $era->id, $heritage, $factor);

        $origin = $this->resolveEndpoint($from, $adj, 'from', (int) $era->id);
        $destination = $this->resolveEndpoint($to, $adj, 'to', (int) $era->id);
        foreach (['from' => $origin, 'to' => $destination] as $side => $stations) {
            $inGraph = array_filter($stations, fn ($s) => isset($adj[$s->id]));
            if (! $inGraph) {
                $s = reset($stations);
                $status = DB::table('station_eras')->where('era_id', $era->id)->where('station_id', $s->id)->value('status');
                throw new RouteException('station_not_in_network', 404,
                    "{$s->name} n'est pas desservie par le réseau en service en {$era->year}.",
                    ['side' => $side, 'station' => ['slug' => $s->slug, 'name' => $s->name], 'status' => $status, 'era_year' => (int) $era->year]);
            }
        }
        $sources = array_values(array_filter(array_keys($origin), fn ($id) => isset($adj[$id])));
        $targets = array_values(array_filter(array_keys($destination), fn ($id) => isset($adj[$id])));
        if (array_intersect($sources, $targets)) {
            throw new RouteException('same_station', 422, 'Départ et arrivée désignent la même gare.');
        }

        $path = self::dijkstra($adj, $sources, $targets);
        if ($path === null) {
            throw new RouteException('no_path', 404,
                sprintf('Aucun itinéraire entre %s et %s sur le réseau en service en %d.',
                    reset($origin)->name, reset($destination)->name, $era->year),
                ['era_year' => (int) $era->year]);
        }

        return ['era' => $era, 'build' => $build, 'path' => $path, 'heritage' => $heritage, 'factor' => $factor];
    }

    /**
     * Année demandée → année de l'ère (fonction pure).
     *
     * @param  list<int>  $eraYears
     */
    public static function eraYearFor(array $eraYears, int $year, int $currentEraYear, int $todayYear): ?int
    {
        if (in_array($year, $eraYears, true)) {
            return $year;
        }
        if ($year >= $todayYear) {
            return $currentEraYear;
        }
        $best = null;
        foreach ($eraYears as $y) {
            if ($y <= $year && ($best === null || $y > $best)) {
                $best = $y;
            }
        }

        return $best;
    }

    /**
     * Dijkstra multi-départs / multi-arrivées (fonction pure).
     *
     * @param  array<int, list<array{0:int, 1:float, 2:int}>>  $adj  gare => [[voisine, coût, arête], …]
     * @param  list<int>  $sources
     * @param  list<int>  $targets
     * @return array{cost: float, stations: list<int>, edges: list<int>}|null
     */
    public static function dijkstra(array $adj, array $sources, array $targets): ?array
    {
        $isTarget = array_fill_keys($targets, true);
        $dist = [];
        $prev = [];
        $pq = new SplPriorityQueue;
        $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        foreach ($sources as $s) {
            $dist[$s] = 0.0;
            $pq->insert($s, 0.0);
        }
        $reached = null;
        while (! $pq->isEmpty()) {
            $item = $pq->extract();
            $u = $item['data'];
            $du = -$item['priority'];
            if ($du > ($dist[$u] ?? INF)) {
                continue;
            }
            if (isset($isTarget[$u])) {
                $reached = $u;
                break;
            }
            foreach ($adj[$u] ?? [] as [$v, $w, $eid]) {
                $alt = $du + $w;
                if ($alt < ($dist[$v] ?? INF)) {
                    $dist[$v] = $alt;
                    $prev[$v] = [$u, $eid];
                    $pq->insert($v, -$alt);
                }
            }
        }
        if ($reached === null) {
            return null;
        }
        $stations = [$reached];
        $edges = [];
        for ($n = $reached; isset($prev[$n]); $n = $prev[$n][0]) {
            $edges[] = $prev[$n][1];
            $stations[] = $prev[$n][0];
        }

        return ['cost' => $dist[$reached], 'stations' => array_reverse($stations), 'edges' => array_reverse($edges)];
    }

    /**
     * Lignes empruntées, dans l'ordre (fonction pure). Chaque pas = une arête
     * gare-à-gare orientée dans le sens du trajet, avec ses portions par ligne.
     * Les portions consécutives d'une même ligne sont fusionnées ; une portion
     * de moins de MIN_LEG_M (raccordement, aiguillage) est fondue dans la
     * précédente. from_index / to_index = rang de la gare de début / fin dans
     * le trajet quand la ligne y commence / finit, sinon null (jonction).
     *
     * @param  list<list<array{0:int, 1:int}>>  $steps  pas k (gare k → k+1) => [[ligne, longueur], …]
     * @return list<array{line_id:int, length_m:int, from_index:?int, to_index:?int}>
     */
    public static function legs(array $steps, int $minLegM = self::MIN_LEG_M): array
    {
        $runs = [];
        foreach ($steps as $k => $parts) {
            $last = count($parts) - 1;
            foreach (array_values($parts) as $j => [$line, $len]) {
                $runs[] = ['line_id' => (int) $line, 'length_m' => (int) $len,
                    'from_index' => $j === 0 ? $k : null, 'to_index' => $j === $last ? $k + 1 : null];
            }
        }
        // Fusion des petits morceaux dans le voisin (précédent, sinon suivant).
        $out = [];
        foreach ($runs as $r) {
            if ($out && $r['length_m'] < $minLegM) {
                $out[count($out) - 1]['length_m'] += $r['length_m'];
                $out[count($out) - 1]['to_index'] = $r['to_index'];

                continue;
            }
            if ($out && $out[count($out) - 1]['length_m'] < $minLegM && count($out) === 1) {
                $r['length_m'] += $out[0]['length_m'];
                $r['from_index'] = $out[0]['from_index'];
                $out = [];
            }
            $out[] = $r;
        }
        // Fusion des morceaux consécutifs d'une même ligne.
        $legs = [];
        foreach ($out as $r) {
            $i = count($legs) - 1;
            if ($i >= 0 && $legs[$i]['line_id'] === $r['line_id']) {
                $legs[$i]['length_m'] += $r['length_m'];
                $legs[$i]['to_index'] = $r['to_index'];
            } else {
                $legs[] = $r;
            }
        }

        return $legs;
    }

    /**
     * Changements d'écartement aux gares (fonction pure) : écartement connu et
     * unique de part et d'autre de la gare, et différent.
     *
     * @param  list<list<int>>  $gaugesByStep
     * @return list<array{index:int, from_mm:int, to_mm:int}>
     */
    public static function gaugeChanges(array $gaugesByStep): array
    {
        $out = [];
        for ($k = 1; $k < count($gaugesByStep); $k++) {
            $a = $gaugesByStep[$k - 1];
            $b = $gaugesByStep[$k];
            if (count($a) === 1 && count($b) === 1 && $a[0] !== $b[0]) {
                $out[] = ['index' => $k, 'from_mm' => $a[0], 'to_mm' => $b[0]];
            }
        }

        return $out;
    }

    /**
     * Ère dont le réseau sert au calcul pour une année demandée (null : réseau actuel).
     *
     * @return object{id: int, year: int, is_current: bool}
     *
     * @throws RouteException era_not_found
     */
    public function resolveEra(?int $year): object
    {
        $eras = DB::table('eras')->orderBy('year')->get(['id', 'year', 'is_current']);
        $current = $eras->firstWhere('is_current', true) ?? $eras->last();
        if (! $current) {
            throw new RouteException('era_not_found', 404, 'Aucune ère définie.');
        }
        if ($year === null) {
            return $current;
        }
        $eraYear = self::eraYearFor($eras->pluck('year')->map(fn ($y) => (int) $y)->all(), $year, (int) $current->year, (int) date('Y'));
        if ($eraYear === null) {
            throw new RouteException('era_not_found', 404, "Pas de carte du réseau pour {$year} (première ère : {$eras->first()->year}).",
                ['first_year' => (int) $eras->first()->year]);
        }

        return $eras->firstWhere('year', $eraYear);
    }

    /**
     * @return array<int, list<array{0:int, 1:float, 2:int}>>
     */
    private function loadAdjacency(int $eraId, bool $heritage, float $factor): array
    {
        $rows = DB::select(
            'SELECT id, from_station_id, to_station_id, length_m, non_passenger_m FROM station_edges
             WHERE era_id = ?'.($heritage ? '' : ' AND heritage_m = 0'),
            [$eraId],
        );
        $adj = [];
        foreach ($rows as $r) {
            $a = (int) $r->from_station_id;
            $b = (int) $r->to_station_id;
            $w = (float) $r->length_m + ($factor - 1.0) * (float) $r->non_passenger_m;
            $adj[$a][] = [$b, $w, (int) $r->id];
            $adj[$b][] = [$a, $w, (int) $r->id];
        }

        return $adj;
    }

    /**
     * Gares désignées par `$query` : slug, identifiant, nom exact, puis nom de
     * ville (gares « <ville>-… » / « <ville> … »). Plusieurs gares d'un même
     * nom ne sont acceptées que si elles sont proches (une même ville) ; parmi
     * elles, on garde les gares ouvertes aux voyageurs à l'ère s'il y en a.
     *
     * @param  array<int, mixed>  $adj
     * @return array<int, object> station_id => {id, slug, name, lat, lng}
     */
    private function resolveEndpoint(string $query, array $adj, string $side, int $eraId): array
    {
        $query = trim($query);
        $select = 'SELECT id, slug, name, ST_Y(geom) AS lat, ST_X(geom) AS lng FROM stations WHERE ';
        $found = DB::select($select.'slug = ?', [$query]);
        if (! $found && ctype_digit($query)) {
            $found = DB::select($select.'id = ?', [(int) $query]);
        }
        if (! $found) {
            $found = DB::select($select.'unaccent(lower(name)) = unaccent(lower(?))', [$query]);
        }
        if (! $found && mb_strlen($query) >= 3) {
            $like = addcslashes($query, '%_\\');
            $found = DB::select($select.<<<'SQL'
                unaccent(lower(name)) LIKE unaccent(lower(?)) || '-%'
                OR unaccent(lower(name)) LIKE unaccent(lower(?)) || ' %'
                SQL, [$like, $like]);
            if (! $found) {
                $found = DB::select($select.'unaccent(lower(city)) = unaccent(lower(?))', [$query]);
            }
        }
        if (! $found) {
            throw new RouteException('station_not_found', 404, "Gare inconnue : « {$query} ».", ['side' => $side, 'query' => $query]);
        }

        $byId = [];
        foreach ($found as $s) {
            $byId[(int) $s->id] = $s;
        }
        $inGraph = array_filter($byId, fn ($s) => isset($adj[$s->id]));
        $candidates = $inGraph ?: $byId;
        if (count($candidates) > 1) {
            $passenger = DB::table('station_eras')->where('era_id', $eraId)->where('status', 'active')
                ->whereIn('station_id', array_keys($candidates))->pluck('station_id')->all();
            if ($passenger) {
                $candidates = array_intersect_key($candidates, array_flip($passenger));
            }
        }
        if (count($candidates) > 1 && self::spreadKm($candidates) > self::CITY_SPREAD_KM) {
            throw new RouteException('ambiguous_station', 422, "Plusieurs gares correspondent à « {$query} » : précisez.", [
                'side' => $side,
                'query' => $query,
                'candidates' => array_values(array_map(fn ($s) => ['slug' => $s->slug, 'name' => $s->name],
                    array_slice($candidates, 0, 12, true))),
            ]);
        }

        return $candidates;
    }

    /** @param  array<int, object>  $stations */
    private static function spreadKm(array $stations): float
    {
        $lats = array_map(fn ($s) => (float) $s->lat, $stations);
        $lngs = array_map(fn ($s) => (float) $s->lng, $stations);
        $dLat = (max($lats) - min($lats)) * 111.0;
        $dLng = (max($lngs) - min($lngs)) * 111.0 * cos(deg2rad((max($lats) + min($lats)) / 2));

        return sqrt($dLat ** 2 + $dLng ** 2);
    }

    /**
     * @param  array{cost: float, stations: list<int>, edges: list<int>}  $path
     * @param  bool  $display  tracé complet pour la carte du site (règle des droits non appliquée)
     * @return array<string, mixed>
     */
    private function describe(object $era, object $build, array $path, ?int $requestedYear, bool $heritage, float $factor, bool $display): array
    {
        $stationIds = $path['stations'];
        $stationRows = collect(DB::select(<<<'SQL'
            SELECT s.id, s.slug, s.name, ST_Y(s.geom) AS lat, ST_X(s.geom) AS lng, se.status
            FROM stations s LEFT JOIN station_eras se ON se.station_id = s.id AND se.era_id = ?
            WHERE s.id = ANY(?::bigint[])
            SQL, [$era->id, self::pgArray($stationIds)]))->keyBy('id');
        $edgeRows = collect(DB::select(<<<'SQL'
            SELECT id, from_station_id, to_station_id, line_id, length_m, non_passenger_m, heritage_m, skipped_count,
                   gap_joins, line_ids, line_lengths, gauges
            FROM station_edges WHERE id = ANY(?::bigint[])
            SQL, [self::pgArray($path['edges'])]))->keyBy('id');

        $stations = [];
        foreach ($stationIds as $sid) {
            $s = $stationRows[$sid];
            $stations[] = ['slug' => $s->slug, 'name' => $s->name, 'lat' => round((float) $s->lat, 6),
                'lng' => round((float) $s->lng, 6), 'status' => $s->status];
        }

        $edges = [];
        $steps = [];
        $gauges = [];
        $totals = ['length' => 0, 'non_passenger' => 0, 'heritage' => 0, 'skipped' => 0, 'gaps' => 0];
        foreach ($path['edges'] as $k => $eid) {
            $e = $edgeRows[$eid];
            $forward = (int) $e->from_station_id === $stationIds[$k];
            $lineIds = self::parsePgArray($e->line_ids);
            $lineLengths = self::parsePgArray($e->line_lengths);
            $parts = array_map(null, $lineIds, $lineLengths);
            if (! $forward) {
                $parts = array_reverse($parts);
            }
            $steps[] = $parts ?: [[(int) $e->line_id, (int) $e->length_m]];
            $gauges[] = self::parsePgArray($e->gauges);
            $totals['length'] += (int) $e->length_m;
            $totals['non_passenger'] += (int) $e->non_passenger_m;
            $totals['heritage'] += (int) $e->heritage_m;
            $totals['skipped'] += (int) $e->skipped_count;
            $totals['gaps'] += (int) $e->gap_joins;
            $edges[] = [
                'from_slug' => $stations[$k]['slug'],
                'to_slug' => $stations[$k + 1]['slug'],
                'line_id' => (int) $e->line_id,
                'line_ids' => array_map(fn ($p) => $p[0], $parts),
                'length_m' => (int) $e->length_m,
                'skipped_count' => (int) $e->skipped_count,
                'non_passenger_m' => (int) $e->non_passenger_m,
                'heritage_m' => (int) $e->heritage_m,
            ];
        }

        $legs = self::legs($steps);
        $lines = DB::table('lines')->whereIn('id', array_column($legs, 'line_id'))->get(['id', 'slug', 'name', 'code'])->keyBy('id');
        $lineList = array_map(function ($leg) use ($lines, $stations) {
            $l = $lines[$leg['line_id']] ?? null;
            $from = $leg['from_index'] !== null ? $stations[$leg['from_index']] : null;
            $to = $leg['to_index'] !== null ? $stations[$leg['to_index']] : null;

            return [
                'line_slug' => $l?->slug, 'line_name' => $l?->name, 'line_code' => $l?->code,
                'length_m' => $leg['length_m'], 'length_km' => round($leg['length_m'] / 1000, 1),
                'from_slug' => $from['slug'] ?? null, 'from_name' => $from['name'] ?? null,
                'to_slug' => $to['slug'] ?? null, 'to_name' => $to['name'] ?? null,
            ];
        }, $legs);

        $gaugeChanges = array_map(fn ($g) => [
            'station_slug' => $stations[$g['index']]['slug'], 'station_name' => $stations[$g['index']]['name'],
            'from_mm' => $g['from_mm'], 'to_mm' => $g['to_mm'],
        ], self::gaugeChanges($gauges));

        // Règle des droits des tracés (LineTraceExport) : une section gare à gare dont le tracé
        // reprend, sur plus de WITHHELD_OVERLAP_M, un tronçon d'une source qui n'autorise pas la
        // redistribution est retirée de `geometry` (chiffrée dans `geometry_withheld_m`), sauf pour
        // l'affichage de la carte du site (option `display`, comme les tuiles vectorielles).
        $withheldSql = $display ? 'FALSE' : 'EXISTS (
                SELECT 1 FROM line_segments ls
                WHERE ls.era_id = e.era_id
                  AND ls.line_id = ANY(COALESCE(e.line_ids, ARRAY[e.line_id]))
                  AND '.$this->traceRights->sqlWithheld('ls.source').'
                  AND ls.geom && ST_Expand(e.geom, 0.0001)
                  AND ST_Length(ST_Intersection(e.geom, ST_Buffer(ls.geom, '.self::WITHHELD_BUFFER_DEG.'))::geography) > '.self::WITHHELD_OVERLAP_M.')';
        $shape = DB::selectOne(
            "WITH e AS (
                SELECT e.id, e.geom, e.length_m, {$withheldSql} AS withheld
                FROM station_edges e WHERE e.id = ANY(?::bigint[]))
             SELECT ST_AsGeoJSON(ST_SimplifyPreserveTopology(ST_LineMerge(ST_Collect(geom) FILTER (WHERE NOT withheld)), 0.00005), 5) AS g,
                    COALESCE(SUM(length_m) FILTER (WHERE withheld), 0) AS withheld_m
             FROM e",
            [self::pgArray($path['edges'])],
        );
        $geometry = $shape?->g;
        $geometryWithheldM = (int) ($shape->withheld_m ?? 0);

        $warnings = [];
        if ($totals['non_passenger'] > 0) {
            $warnings[] = 'non_passenger';
        }
        if ($totals['heritage'] > 0) {
            $warnings[] = 'heritage';
        }
        if ($gaugeChanges) {
            $warnings[] = 'gauge_change';
        }
        if ($totals['gaps'] > 0) {
            $warnings[] = 'gap_joins';
        }
        if ($build->stations_source !== 'station_eras') {
            $warnings[] = 'stations_from_chronology';
        }

        return [
            'from' => ['slug' => $stations[0]['slug'], 'name' => $stations[0]['name']],
            'to' => ['slug' => $stations[count($stations) - 1]['slug'], 'name' => $stations[count($stations) - 1]['name']],
            'requested_year' => $requestedYear,
            'era_year' => (int) $era->year,
            'era_is_current' => (bool) $era->is_current,
            'distance_m' => $totals['length'],
            'distance_km' => round($totals['length'] / 1000, 2),
            // Indicatif (100 km/h) : pas de vitesses historiques fiables par ère.
            'duration_h' => round(($totals['length'] / 1000.0) / 100.0, 2),
            'hops' => count($edges),
            'stations' => $stations,
            'lines' => $lineList,
            'edges' => $edges,
            'gauge_changes' => $gaugeChanges,
            'non_passenger_m' => $totals['non_passenger'],
            'heritage_m' => $totals['heritage'],
            'closed_stations_passed' => $totals['skipped'],
            'gap_joins' => $totals['gaps'],
            'warnings' => $warnings,
            'options' => ['heritage' => $heritage, 'traffic' => $factor > 1.0 ? 'passenger' : 'all'],
            'geometry' => $geometry ? json_decode($geometry, true) : null,
            // Parties du trajet retirées de `geometry` (règle des droits des tracés), en mètres.
            'geometry_withheld' => $geometryWithheldM > 0,
            'geometry_withheld_m' => $geometryWithheldM,
            'graph' => [
                'built_at' => $build->built_at,
                'stations_source' => $build->stations_source,
                'components' => (int) $build->components,
                'station_nodes' => (int) $build->station_nodes,
            ],
        ];
    }

    /** @param  list<int>  $values */
    private static function pgArray(array $values): string
    {
        return '{'.implode(',', array_map('intval', $values)).'}';
    }

    /** @return list<int> */
    private static function parsePgArray(?string $value): array
    {
        $value = trim((string) $value, '{}');

        return $value === '' ? [] : array_map('intval', explode(',', $value));
    }
}
