<?php

namespace App\Services\NetworkGraph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostics du graphe ferroviaire d'une ère (vff:build-network-graph), pour l'espace
 * d'administration (/validate/graph). Lecture seule : rien n'est écrit dans les tables du graphe.
 *
 * Trois couches :
 *   - topologie (network_nodes / network_edges) : composantes connexes (la plus grande est le
 *     réseau principal, mesurée en longueur de voie comme dans EraGraphBuilder ; les autres sont
 *     des « îles »), nœuds sans arête, arêtes de longueur nulle ou anormale, jonctions écartées ;
 *   - gares (station_edges, gares ouvertes de station_eras) : îles du graphe gare-à-gare, gares
 *     ouvertes sans arête (non rattachées aux voies, ou rattachées mais sans voisine), arêtes
 *     gare-à-gare nulles ou détournées ;
 *   - lignes (network_edges par ligne, line_stops) : lignes dont les voies de l'ère forment
 *     plusieurs morceaux, arrêts dont la gare n'est pas dans le graphe.
 *
 * Jonction écartée : nœud dont les extrémités des arêtes qui s'y rejoignent ne sont pas au même
 * point (plus de JUNCTION_TOLERANCE_M entre les deux plus éloignées : seuil du bruit des
 * reprojections), raccord de lacune (arête « connector » posée par le constructeur pour franchir
 * un trou entre deux tracés), et saut (arête sans tracé dont les deux nœuds sont à plus de
 * JUNCTION_TOLERANCE_M l'un de l'autre : rien n'est dessiné entre eux). Sur la carte, deux lignes
 * doivent se toucher à 0 m.
 *
 * Composantes connexes : une arête sans tracé compte comme une liaison, comme un raccord de
 * lacune. Le graphe de l'ère la suit pour les itinéraires ; une île est donc une partie que ces
 * itinéraires ne peuvent pas atteindre. L'écart qu'elle laisse sur la carte est mesuré à part,
 * dans les jonctions (type `jump`). Rien n'est écrit dans le graphe : c'est un diagnostic.
 *
 * Le rapport d'une ère est calculé en une fois (quelques secondes sur une ère de 50 000 arêtes),
 * gardé en cache sous la date de construction du graphe (une reconstruction change la clé) ; les
 * listes sont triées (le plus grave d'abord) et bornées à LIST_CAP éléments, servies par pages.
 */
final class GraphDiagnostics
{
    /** Version du rapport, dans la clé du cache : à changer quand son contenu change. */
    public const FORMAT = 2;

    public const TTL_SECONDS = 7 * 24 * 3600;

    /** Éléments gardés par liste (le total exact reste dans `total`). */
    public const LIST_CAP = 2000;

    public const PER_PAGE = 25;

    /** Écart sous lequel deux extrémités sont confondues (bruit des reprojections), en mètres. */
    public const JUNCTION_TOLERANCE_M = 0.01;

    /** Écart toléré entre la longueur enregistrée d'une arête et celle de son tracé : 5 m ou 1 %. */
    public const LENGTH_TOLERANCE_M = 5;

    public const LENGTH_TOLERANCE_RATIO = 0.01;

    /** Arête gare-à-gare « détournée » : plus de DETOUR_RATIO fois la distance à vol d'oiseau, au-delà de DETOUR_MIN_M. */
    public const DETOUR_RATIO = 4.0;

    public const DETOUR_MIN_M = 5000;

    /** Listes du rapport, servies par pages (GET /validate/graph/{year}/diagnostics/{check}). */
    public const CHECKS = [
        'islands', 'station_islands', 'isolated_nodes', 'stations_without_edge', 'edges', 'junctions', 'fragmented_lines',
    ];

    /** Statuts d'une gare ouverte à l'ère (station_eras), comme EraGraphBuilder. */
    private const OPEN_STATUSES = ['active', 'tourist', 'no_commercial'];

    /**
     * Rapport de l'ère, ou null si son graphe n'est pas construit.
     *
     * @return array{era_id: int, built_at: string, summary: array<string, array<string, mixed>>, lists: array<string, array{total: int, items: list<array<string, mixed>>}>}|null
     */
    public function report(int $eraId, bool $fresh = false): ?array
    {
        $build = DB::table('network_graph_builds')->where('era_id', $eraId)->first(['built_at', 'stations_source']);
        if ($build === null) {
            return null;
        }
        $key = sprintf('graph-diagnostics:%d:%d:%s', self::FORMAT, $eraId, preg_replace('/\D/', '', (string) $build->built_at));
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::TTL_SECONDS, fn (): array => [
            'era_id' => $eraId,
            'built_at' => (string) $build->built_at,
            ...$this->compute($eraId, (string) $build->stations_source),
        ]);
    }

    /**
     * Une page d'une liste du rapport.
     *
     * @param  array{lists: array<string, array{total: int, items: list<array<string, mixed>>}>}  $report
     * @return array{check: string, total: int, listed: int, page: int, per_page: int, last_page: int, items: list<array<string, mixed>>}
     */
    public static function page(array $report, string $check, int $page, int $perPage = self::PER_PAGE): array
    {
        $list = $report['lists'][$check] ?? ['total' => 0, 'items' => []];
        $listed = count($list['items']);
        $last = max(1, (int) ceil($listed / $perPage));
        $page = min(max(1, $page), $last);

        return [
            'check' => $check,
            'total' => $list['total'],
            'listed' => $listed,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $last,
            'items' => array_slice($list['items'], ($page - 1) * $perPage, $perPage),
        ];
    }

    /**
     * @return array{summary: array<string, array<string, mixed>>, lists: array<string, array{total: int, items: list<array<string, mixed>>}>}
     */
    private function compute(int $eraId, string $stationsSource): array
    {
        // Plus de mémoire de tri pour ce calcul seulement : la valeur d'origine est remise après,
        // pour ne pas la laisser à une connexion réutilisée (worker de file, Octane).
        $workMem = (string) DB::scalar("SELECT current_setting('work_mem')");
        DB::select("SELECT set_config('work_mem', '64MB', false)");
        try {
            return $this->computeReport($eraId, $stationsSource);
        } finally {
            DB::select("SELECT set_config('work_mem', ?, false)", [$workMem]);
        }
    }

    /**
     * @return array{summary: array<string, array<string, mixed>>, lists: array<string, array{total: int, items: list<array<string, mixed>>}>}
     */
    private function computeReport(int $eraId, string $stationsSource): array
    {
        // ── Topologie ────────────────────────────────────────────────────────
        $nodes = [];
        $nodeKinds = [];
        foreach (DB::cursor('SELECT id, kind, station_id, ST_X(geom) AS lng, ST_Y(geom) AS lat FROM network_nodes WHERE era_id = ?', [$eraId]) as $n) {
            $nodes[(int) $n->id] = [(float) $n->lng, (float) $n->lat, $n->station_id !== null ? (int) $n->station_id : null, (string) $n->kind];
            $nodeKinds[(string) $n->kind] = ($nodeKinds[(string) $n->kind] ?? 0) + 1;
        }

        $topology = new ConnectedComponents;
        foreach (array_keys($nodes) as $id) {
            $topology->add($id);
        }
        $edgeRows = [];
        $degree = [];
        $connectors = 0;
        $byLine = [];
        foreach (DB::cursor('SELECT id, from_node_id, to_node_id, line_id, length_m, is_connector FROM network_edges WHERE era_id = ?', [$eraId]) as $e) {
            $from = (int) $e->from_node_id;
            $to = (int) $e->to_node_id;
            $line = $e->line_id !== null ? (int) $e->line_id : null;
            $topology->link($from, $to);
            $degree[$from] = true;
            $degree[$to] = true;
            $edgeRows[] = [$from, (int) $e->length_m, $line];
            if ($e->is_connector) {
                $connectors++;
            } elseif ($line !== null) {
                $byLine[$line][] = [$from, $to, (int) $e->length_m];
            }
        }

        [$islands, $largest, $components] = $this->islands($topology, $edgeRows, $nodes);
        $isolated = $this->isolatedNodes($nodes, $degree);
        $edges = $this->edgeAnomalies($eraId);
        $junctions = $this->junctions($eraId, $nodes);
        $fragmented = $this->fragmentedLines($byLine, $nodes);

        // ── Gares ────────────────────────────────────────────────────────────
        [$stationIslands, $stationLargest, $stationComponents, $stationNodes, $stationEdgeCount] = $this->stationIslands($eraId);
        $withoutEdge = $this->stationsWithoutEdge($eraId, $stationsSource);

        // ── Lignes ───────────────────────────────────────────────────────────
        $stops = DB::selectOne(<<<'SQL'
            SELECT count(*) AS stops, count(DISTINCT line_id) AS lines,
                   count(*) FILTER (WHERE NOT EXISTS (
                       SELECT 1 FROM network_nodes n WHERE n.era_id = ls.era_id AND n.station_id = ls.station_id
                   )) AS off_graph
            FROM line_stops ls WHERE ls.era_id = ?
            SQL, [$eraId]);

        $lineIds = [];
        foreach ([...$islands, ...$fragmented] as $item) {
            foreach ($item['line_ids'] ?? [$item['line_id'] ?? null] as $id) {
                if ($id !== null) {
                    $lineIds[$id] = true;
                }
            }
        }
        foreach ($edges as $item) {
            if ($item['line_id'] !== null) {
                $lineIds[$item['line_id']] = true;
            }
        }
        $lines = $this->lines(array_keys($lineIds));
        $islands = array_map(fn (array $i): array => $this->withLines($i, $lines), $islands);
        $fragmented = array_map(fn (array $i): array => $this->withLines($i, $lines), $fragmented);
        $edges = array_map(fn (array $i): array => $this->withLines($i, $lines), $edges);

        $spread = array_values(array_filter($junctions, fn (array $j): bool => $j['type'] === 'spread'));
        $jumps = array_values(array_filter($junctions, fn (array $j): bool => $j['type'] === 'jump'));

        return [
            'summary' => [
                'topology' => [
                    'nodes' => count($nodes),
                    'edges' => count($edgeRows),
                    'node_kinds' => $nodeKinds,
                    'connectors' => $connectors,
                    'components' => $components,
                    'largest' => $largest,
                    'islands' => count($islands),
                    'isolated_nodes' => count($isolated),
                    'edge_anomalies' => count(array_filter($edges, fn (array $e): bool => $e['layer'] === 'topology')),
                    'zero_length_edges' => count(array_filter($edges, fn (array $e): bool => $e['reason'] === 'zero_length' && $e['layer'] === 'topology')),
                    'no_geometry_edges' => count(array_filter($edges, fn (array $e): bool => $e['reason'] === 'no_geometry' && $e['layer'] === 'topology')),
                    'spread_junctions' => count($spread),
                    'spread_over_1m' => count(array_filter($spread, fn (array $j): bool => $j['distance_m'] > 1)),
                    'spread_over_5m' => count(array_filter($spread, fn (array $j): bool => $j['distance_m'] > 5)),
                    'max_spread_m' => $spread === [] ? 0.0 : max(array_column($spread, 'distance_m')),
                    'jumps' => count($jumps),
                    'max_jump_m' => $jumps === [] ? 0.0 : max(array_column($jumps, 'distance_m')),
                ],
                'stations' => [
                    'stations' => $stationNodes,
                    'edges' => $stationEdgeCount,
                    'components' => $stationComponents,
                    'largest' => $stationLargest,
                    'islands' => count($stationIslands),
                    'without_edge' => count($withoutEdge),
                    'unattached' => count(array_filter($withoutEdge, fn (array $s): bool => $s['reason'] === 'unattached')),
                    'edge_anomalies' => count(array_filter($edges, fn (array $e): bool => $e['layer'] === 'stations')),
                ],
                'lines' => [
                    'lines' => count($byLine),
                    'stops' => (int) $stops->stops,
                    'lines_with_stops' => (int) $stops->lines,
                    'stops_off_graph' => (int) $stops->off_graph,
                    'fragmented' => count($fragmented),
                ],
            ],
            'lists' => [
                'islands' => self::capped($islands),
                'station_islands' => self::capped($stationIslands),
                'isolated_nodes' => self::capped($isolated),
                'stations_without_edge' => self::capped($withoutEdge),
                'edges' => self::capped($edges),
                'junctions' => self::capped($junctions),
                'fragmented_lines' => self::capped($fragmented),
            ],
        ];
    }

    /**
     * Îles de la topologie : toutes les composantes sauf la plus longue (réseau principal).
     *
     * @param  list<array{0: int, 1: int, 2: int|null}>  $edgeRows  [nœud de départ, longueur, ligne]
     * @param  array<int, array{0: float, 1: float, 2: int|null, 3: string}>  $nodes
     * @return array{0: list<array<string, mixed>>, 1: array<string, int|float>, 2: int}
     */
    private function islands(ConnectedComponents $cc, array $edgeRows, array $nodes): array
    {
        $groups = $cc->groups();
        $stats = [];
        foreach ($groups as $root => $members) {
            $stats[$root] = ['edges' => 0, 'length_m' => 0, 'lines' => []];
        }
        $total = 0;
        foreach ($edgeRows as [$from, $length, $line]) {
            $root = $cc->find($from);
            $stats[$root]['edges']++;
            $stats[$root]['length_m'] += $length;
            $total += $length;
            if ($line !== null) {
                $stats[$root]['lines'][$line] = ($stats[$root]['lines'][$line] ?? 0) + $length;
            }
        }
        // Plus longue d'abord, puis la plus peuplée, puis le plus petit identifiant (ordre stable).
        $roots = array_keys($groups);
        usort($roots, fn (int $a, int $b): int => [$stats[$b]['length_m'], count($groups[$b]), $groups[$a][0]] <=> [$stats[$a]['length_m'], count($groups[$a]), $groups[$b][0]]);

        $islands = [];
        $largest = ['nodes' => 0, 'edges' => 0, 'length_m' => 0, 'length_share' => 0.0];
        foreach ($roots as $rank => $root) {
            $members = $groups[$root];
            if ($rank === 0) {
                $largest = [
                    'nodes' => count($members),
                    'edges' => $stats[$root]['edges'],
                    'length_m' => $stats[$root]['length_m'],
                    'length_share' => $total > 0 ? round($stats[$root]['length_m'] / $total, 4) : 0.0,
                ];

                continue;
            }
            arsort($stats[$root]['lines']);
            $islands[] = [
                'id' => $members[0],
                'nodes' => count($members),
                'edges' => $stats[$root]['edges'],
                'length_m' => $stats[$root]['length_m'],
                'stations' => count(array_filter($members, fn (int $n): bool => isset($nodes[$n]) && $nodes[$n][2] !== null)),
                'bbox' => self::bbox(array_map(fn (int $n): array => $nodes[$n] ?? [0.0, 0.0], $members)),
                'line_ids' => array_slice(array_keys($stats[$root]['lines']), 0, 3),
            ];
        }

        return [$islands, $largest, count($groups)];
    }

    /**
     * @param  array<int, array{0: float, 1: float, 2: int|null, 3: string}>  $nodes
     * @param  array<int, true>  $degree
     * @return list<array<string, mixed>>
     */
    private function isolatedNodes(array $nodes, array $degree): array
    {
        $isolated = [];
        foreach ($nodes as $id => [$lng, $lat, $station, $kind]) {
            if (! isset($degree[$id])) {
                $isolated[] = ['id' => $id, 'kind' => $kind, 'station_id' => $station, 'lng' => $lng, 'lat' => $lat];
            }
        }

        return $isolated;
    }

    /**
     * Arêtes de longueur nulle ou anormale, topologie puis gare-à-gare.
     *
     * Une arête de la topologie sans tracé est classée `no_geometry` quelle que soit sa longueur
     * enregistrée (le constructeur y écrit 0) ; `measured_m` donne alors la distance entre ses deux
     * nœuds, l'écart qu'elle fait sauter sur la carte.
     *
     * @return list<array<string, mixed>>
     */
    private function edgeAnomalies(int $eraId): array
    {
        $tol = self::LENGTH_TOLERANCE_M;
        $ratio = self::LENGTH_TOLERANCE_RATIO;
        $rows = DB::select(<<<SQL
            SELECT x.*, ST_X(x.p) AS lng, ST_Y(x.p) AS lat
            FROM (
                SELECT e.id, e.line_id, e.length_m, e.is_connector, e.from_node_id, e.to_node_id, e.geom IS NULL AS no_geom,
                       CASE WHEN e.geom IS NOT NULL THEN ST_Length(e.geom::geography)
                            ELSE ST_Distance(a.geom::geography, b.geom::geography) END AS measured_m,
                       COALESCE(ST_LineInterpolatePoint(e.geom, 0.5), ST_Centroid(ST_MakeLine(a.geom, b.geom)), a.geom) AS p
                FROM network_edges e
                LEFT JOIN network_nodes a ON a.id = e.from_node_id
                LEFT JOIN network_nodes b ON b.id = e.to_node_id
                WHERE e.era_id = ?
                  AND (e.length_m <= 0 OR e.geom IS NULL OR e.from_node_id = e.to_node_id
                       OR abs(e.length_m - ST_Length(e.geom::geography)) > greatest({$tol}, {$ratio} * e.length_m))
            ) x
            SQL, [$eraId]);

        $items = [];
        foreach ($rows as $r) {
            // L'absence de tracé passe avant la longueur : une arête sans tracé a une longueur enregistrée nulle.
            $reason = match (true) {
                (bool) $r->no_geom => 'no_geometry',
                (int) $r->length_m <= 0 => 'zero_length',
                (int) $r->from_node_id === (int) $r->to_node_id => 'loop',
                default => 'length_mismatch',
            };
            $items[] = [
                'layer' => 'topology',
                'id' => (int) $r->id,
                'reason' => $reason,
                'line_id' => $r->line_id !== null ? (int) $r->line_id : null,
                'length_m' => (int) $r->length_m,
                'measured_m' => $r->measured_m !== null ? round((float) $r->measured_m, 1) : null,
                'connector' => (bool) $r->is_connector,
                'lng' => $r->lng !== null ? (float) $r->lng : null,
                'lat' => $r->lat !== null ? (float) $r->lat : null,
            ];
        }

        $detourRatio = self::DETOUR_RATIO;
        $detourMin = self::DETOUR_MIN_M;
        $rows = DB::select(<<<SQL
            SELECT x.*, ST_X(x.p) AS lng, ST_Y(x.p) AS lat
            FROM (
                SELECT se.id, se.line_id, se.length_m, se.from_station_id, se.to_station_id, se.geom IS NULL AS no_geom,
                       ST_Distance(a.geom::geography, b.geom::geography) AS direct_m,
                       ST_Centroid(ST_MakeLine(a.geom, b.geom)) AS p
                FROM station_edges se
                JOIN stations a ON a.id = se.from_station_id
                JOIN stations b ON b.id = se.to_station_id
                WHERE se.era_id = ?
            ) x
            WHERE x.length_m <= 0 OR x.no_geom OR x.from_station_id = x.to_station_id
               OR (x.length_m > {$detourMin} AND x.length_m > {$detourRatio} * x.direct_m)
            SQL, [$eraId]);
        foreach ($rows as $r) {
            $reason = match (true) {
                (int) $r->length_m <= 0 => 'zero_length',
                (int) $r->from_station_id === (int) $r->to_station_id => 'loop',
                (bool) $r->no_geom => 'no_geometry',
                default => 'detour',
            };
            $items[] = [
                'layer' => 'stations',
                'id' => (int) $r->id,
                'reason' => $reason,
                'line_id' => $r->line_id !== null ? (int) $r->line_id : null,
                'length_m' => (int) $r->length_m,
                'measured_m' => round((float) $r->direct_m, 1),
                'connector' => false,
                'lng' => (float) $r->lng,
                'lat' => (float) $r->lat,
            ];
        }

        // Le plus grave d'abord : longueur nulle, sans tracé, boucle, puis le plus grand écart.
        $rank = ['zero_length' => 0, 'no_geometry' => 1, 'loop' => 2, 'length_mismatch' => 3, 'detour' => 4];
        usort($items, fn (array $a, array $b): int => [$rank[$a['reason']], -abs($a['length_m'] - (float) $a['measured_m']), $a['id']]
            <=> [$rank[$b['reason']], -abs($b['length_m'] - (float) $b['measured_m']), $b['id']]);

        return $items;
    }

    /**
     * Jonctions écartées : nœuds dont les extrémités d'arêtes ne coïncident pas, raccords de lacunes,
     * et sauts (arêtes sans tracé entre deux nœuds distants de plus de JUNCTION_TOLERANCE_M).
     *
     * @param  array<int, array{0: float, 1: float, 2: int|null, 3: string}>  $nodes
     * @return list<array<string, mixed>>
     */
    private function junctions(int $eraId, array $nodes): array
    {
        $tol = self::JUNCTION_TOLERANCE_M;
        // Écart = plus grande distance entre deux extrémités d'arêtes au nœud, en Lambert-93 (mètres).
        $rows = DB::select(<<<SQL
            WITH ends AS (
                SELECT from_node_id AS node_id, ST_StartPoint(geom) AS p, line_id
                FROM network_edges WHERE era_id = ? AND geom IS NOT NULL
                UNION ALL
                SELECT to_node_id, ST_EndPoint(geom), line_id
                FROM network_edges WHERE era_id = ? AND geom IS NOT NULL
            ),
            agg AS (
                SELECT node_id, count(*) AS ends, count(DISTINCT line_id) AS lines,
                       ST_MaxDistance(ST_Transform(ST_Collect(p), 2154), ST_Transform(ST_Collect(p), 2154)) AS spread
                FROM ends GROUP BY node_id HAVING count(*) > 1
            )
            SELECT node_id, ends, lines, spread FROM agg WHERE spread > {$tol}
            SQL, [$eraId, $eraId]);

        $items = [];
        foreach ($rows as $r) {
            $node = $nodes[(int) $r->node_id] ?? null;
            $items[] = [
                'type' => 'spread',
                'id' => (int) $r->node_id,
                'distance_m' => round((float) $r->spread, 2),
                'kind' => $node[3] ?? null,
                'station_id' => $node[2] ?? null,
                'ends' => (int) $r->ends,
                'lines' => (int) $r->lines,
                'lng' => $node[0] ?? null,
                'lat' => $node[1] ?? null,
            ];
        }
        $rows = DB::select(<<<'SQL'
            SELECT id, length_m, from_node_id, ST_X(ST_StartPoint(geom)) AS lng, ST_Y(ST_StartPoint(geom)) AS lat
            FROM network_edges WHERE era_id = ? AND is_connector
            SQL, [$eraId]);
        foreach ($rows as $r) {
            $node = $nodes[(int) $r->from_node_id] ?? null;
            $items[] = [
                'type' => 'connector',
                'id' => (int) $r->id,
                'distance_m' => (float) $r->length_m,
                'kind' => $node[3] ?? null,
                'station_id' => $node[2] ?? null,
                'ends' => 2,
                'lines' => 0,
                'lng' => $r->lng !== null ? (float) $r->lng : ($node[0] ?? null),
                'lat' => $r->lat !== null ? (float) $r->lat : ($node[1] ?? null),
            ];
        }
        // Sauts : la distance se mesure sur l'ellipsoïde (comme la longueur des arêtes), le point
        // montré est le milieu des deux nœuds.
        $rows = DB::select(<<<SQL
            SELECT x.id, x.from_node_id, x.line_id, x.gap, ST_X(x.p) AS lng, ST_Y(x.p) AS lat
            FROM (
                SELECT e.id, e.from_node_id, e.line_id, ST_Distance(a.geom::geography, b.geom::geography) AS gap,
                       ST_Centroid(ST_MakeLine(a.geom, b.geom)) AS p
                FROM network_edges e
                JOIN network_nodes a ON a.id = e.from_node_id
                JOIN network_nodes b ON b.id = e.to_node_id
                WHERE e.era_id = ? AND e.geom IS NULL
            ) x
            WHERE x.gap > {$tol}
            SQL, [$eraId]);
        foreach ($rows as $r) {
            $node = $nodes[(int) $r->from_node_id] ?? null;
            $items[] = [
                'type' => 'jump',
                'id' => (int) $r->id,
                'distance_m' => round((float) $r->gap, 2),
                'kind' => $node[3] ?? null,
                'station_id' => $node[2] ?? null,
                'ends' => 2,
                'lines' => $r->line_id !== null ? 1 : 0,
                'lng' => (float) $r->lng,
                'lat' => (float) $r->lat,
            ];
        }
        usort($items, fn (array $a, array $b): int => [$b['distance_m'], $a['type'], $a['id']] <=> [$a['distance_m'], $b['type'], $b['id']]);

        return $items;
    }

    /**
     * Lignes dont les voies de l'ère (hors raccords) forment plusieurs morceaux.
     *
     * @param  array<int, list<array{0: int, 1: int, 2: int}>>  $byLine
     * @param  array<int, array{0: float, 1: float, 2: int|null, 3: string}>  $nodes
     * @return list<array<string, mixed>>
     */
    private function fragmentedLines(array $byLine, array $nodes): array
    {
        $items = [];
        foreach ($byLine as $line => $edges) {
            $cc = new ConnectedComponents;
            $length = 0;
            foreach ($edges as [$from, $to, $len]) {
                $cc->link($from, $to);
                $length += $len;
            }
            $pieces = $cc->count();
            if ($pieces < 2) {
                continue;
            }
            $points = [];
            foreach ($edges as [$from, $to]) {
                $points[] = $nodes[$from] ?? [0.0, 0.0];
                $points[] = $nodes[$to] ?? [0.0, 0.0];
            }
            $items[] = ['line_id' => $line, 'pieces' => $pieces, 'edges' => count($edges), 'length_m' => $length, 'bbox' => self::bbox($points)];
        }
        usort($items, fn (array $a, array $b): int => [$b['pieces'], $b['length_m'], $a['line_id']] <=> [$a['pieces'], $a['length_m'], $b['line_id']]);

        return $items;
    }

    /**
     * Îles du graphe gare-à-gare : gares ouvertes rattachées (nœuds « station »), reliées par station_edges.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, int>, 2: int, 3: int, 4: int}
     */
    private function stationIslands(int $eraId): array
    {
        $cc = new ConnectedComponents;
        $stations = [];
        foreach (DB::select(<<<'SQL'
            SELECT DISTINCT s.id, s.slug, s.name, ST_X(s.geom) AS lng, ST_Y(s.geom) AS lat
            FROM network_nodes n JOIN stations s ON s.id = n.station_id
            WHERE n.era_id = ? AND n.kind = 'station'
            SQL, [$eraId]) as $s) {
            $stations[(int) $s->id] = ['slug' => $s->slug, 'name' => $s->name, 'lng' => (float) $s->lng, 'lat' => (float) $s->lat];
            $cc->add((int) $s->id);
        }
        $edgeCount = 0;
        $edgesByRoot = [];
        $pairs = DB::select('SELECT from_station_id AS a, to_station_id AS b, length_m FROM station_edges WHERE era_id = ?', [$eraId]);
        foreach ($pairs as $e) {
            $cc->link((int) $e->a, (int) $e->b);
            $edgeCount++;
        }
        foreach ($pairs as $e) {
            $root = $cc->find((int) $e->a);
            $edgesByRoot[$root] ??= [0, 0];
            $edgesByRoot[$root][0]++;
            $edgesByRoot[$root][1] += (int) $e->length_m;
        }
        $groups = $cc->groups();
        $roots = array_keys($groups);
        usort($roots, fn (int $a, int $b): int => [count($groups[$b]), $edgesByRoot[$b][1] ?? 0, $groups[$a][0]] <=> [count($groups[$a]), $edgesByRoot[$a][1] ?? 0, $groups[$b][0]]);

        $islands = [];
        $largest = ['stations' => 0, 'edges' => 0, 'length_m' => 0];
        foreach ($roots as $rank => $root) {
            $members = $groups[$root];
            [$edges, $length] = $edgesByRoot[$root] ?? [0, 0];
            if ($rank === 0) {
                $largest = ['stations' => count($members), 'edges' => $edges, 'length_m' => $length];

                continue;
            }
            $known = array_values(array_filter($members, fn (int $id): bool => isset($stations[$id])));
            $islands[] = [
                'id' => $members[0],
                'stations' => count($members),
                'edges' => $edges,
                'length_m' => $length,
                'bbox' => self::bbox(array_map(fn (int $id): array => [$stations[$id]['lng'], $stations[$id]['lat']], $known)),
                'sample' => array_map(fn (int $id): array => ['id' => $id, 'slug' => $stations[$id]['slug'], 'name' => $stations[$id]['name']], array_slice($known, 0, 3)),
            ];
        }

        return [$islands, $largest, count($groups), count($stations), $edgeCount];
    }

    /**
     * Gares ouvertes à l'ère sans aucune arête gare-à-gare : non rattachées aux voies (aucun nœud), ou
     * rattachées sans gare voisine.
     *
     * @return list<array<string, mixed>>
     */
    private function stationsWithoutEdge(int $eraId, string $stationsSource): array
    {
        $open = "'".implode("', '", self::OPEN_STATUSES)."'";
        $year = (int) DB::table('eras')->where('id', $eraId)->value('year');
        // Même source des gares ouvertes que le constructeur (station_eras, à défaut dates des gares).
        $from = $stationsSource === 'station_eras'
            ? "station_eras se JOIN stations s ON s.id = se.station_id WHERE se.era_id = :era AND se.status IN ({$open})"
            : 'stations s WHERE (s.opened_at IS NULL OR s.opened_at <= make_date(:y1, 12, 31)) AND (s.closed_at IS NULL OR s.closed_at > make_date(:y2, 12, 31))';
        $bindings = ['era' => $eraId, 'era2' => $eraId, 'era3' => $eraId];
        if ($stationsSource !== 'station_eras') {
            unset($bindings['era']);
            $bindings += ['y1' => $year, 'y2' => $year];
        }
        $rows = DB::select(<<<SQL
            SELECT s.id, s.slug, s.name, ST_X(s.geom) AS lng, ST_Y(s.geom) AS lat,
                   EXISTS (SELECT 1 FROM network_nodes n WHERE n.era_id = :era2 AND n.station_id = s.id) AS attached
            FROM {$from}
              AND NOT EXISTS (SELECT 1 FROM station_edges e WHERE e.era_id = :era3 AND (e.from_station_id = s.id OR e.to_station_id = s.id))
            ORDER BY s.name, s.id
            SQL, $bindings);

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (int) $r->id,
                'slug' => $r->slug,
                'name' => $r->name,
                'reason' => $r->attached ? 'no_neighbour' : 'unattached',
                'lng' => (float) $r->lng,
                'lat' => (float) $r->lat,
            ];
        }
        // Non rattachées d'abord (le constructeur n'a trouvé aucune voie), puis par nom.
        usort($items, fn (array $a, array $b): int => [$a['reason'] === 'unattached' ? 0 : 1, $a['name'], $a['id']] <=> [$b['reason'] === 'unattached' ? 0 : 1, $b['name'], $b['id']]);

        return $items;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{id: int, slug: string, name: string, code: string|null}>
     */
    private function lines(array $ids): array
    {
        $lines = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table('lines')->whereIn('id', $chunk)->get(['id', 'slug', 'name', 'code']) as $l) {
                $lines[(int) $l->id] = ['id' => (int) $l->id, 'slug' => (string) $l->slug, 'name' => (string) $l->name, 'code' => $l->code];
            }
        }

        return $lines;
    }

    /**
     * Remplace les identifiants de lignes d'un élément par leur nom, numéro et adresse de fiche.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, array{id: int, slug: string, name: string, code: string|null}>  $lines
     * @return array<string, mixed>
     */
    private function withLines(array $item, array $lines): array
    {
        if (array_key_exists('line_ids', $item)) {
            $item['lines'] = array_values(array_filter(array_map(fn (int $id): ?array => $lines[$id] ?? null, $item['line_ids'])));
            unset($item['line_ids']);
        }
        if (array_key_exists('line_id', $item)) {
            $item['line'] = $item['line_id'] !== null ? ($lines[$item['line_id']] ?? null) : null;
        }

        return $item;
    }

    /**
     * Emprise [ouest, sud, est, nord] d'une liste de points [lng, lat].
     *
     * @param  list<array{0: float, 1: float}|array<int, mixed>>  $points
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function bbox(array $points): ?array
    {
        if ($points === []) {
            return null;
        }
        $lngs = array_map(fn (array $p): float => (float) $p[0], $points);
        $lats = array_map(fn (array $p): float => (float) $p[1], $points);

        return [round(min($lngs), 6), round(min($lats), 6), round(max($lngs), 6), round(max($lats), 6)];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    private static function capped(array $items): array
    {
        return ['total' => count($items), 'items' => array_slice($items, 0, self::LIST_CAP)];
    }
}
