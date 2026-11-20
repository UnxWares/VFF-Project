<?php

namespace App\Services\NetworkGraph;

use App\Support\Lgv;
use Illuminate\Support\Facades\DB;

/**
 * Construit le graphe ferroviaire d'UNE ère, à partir des seules portions EN
 * SERVICE à cette ère (line_segments, moteur décennal) et des gares ouvertes
 * à cette ère (station_eras). Rejouable : tout le graphe de l'ère est remplacé
 * en une transaction ; les autres ères ne sont pas touchées (parallélisable).
 *
 * 1. TOPOLOGIE (network_nodes / network_edges), en Lambert-93 (mètres) :
 *    - nœuds = extrémités de portions regroupées à moins de `snap_m` ;
 *    - jonctions en T : un nœud à moins de `snap_m` de l'INTÉRIEUR d'une autre
 *      portion la coupe à cet endroit ;
 *    - lacunes : une extrémité sans suite sur sa propre ligne est raccordée
 *      au tronçon le plus proche de la même ligne à moins de `gap_same_m` ;
 *      une extrémité isolée (aucune voie à moins de `snap_m`) à la voie la
 *      plus proche à moins de `gap_any_m` : arête « connector » comptée ;
 *      enfin les composantes connexes encore séparées par une lacune de
 *      cette taille sont pontées (bridgeComponents) ;
 *    - gares : chaque gare ouverte est projetée sur les voies de ses lignes à
 *      cette ère (line_stops, à défaut station_lines) à moins de
 *      `attach_line_m`, sur toute voie non LGV à moins de `attach_near_m`, à
 *      défaut sur la voie la plus proche à moins de `attach_fallback_m` : un
 *      nœud par gare, les projections deviennent des coupures des portions ;
 *      les gares FERMÉES à l'ère sont projetées aussi, comme simples nœuds de
 *      passage (comptées dans skipped_count) ;
 *    - arêtes = morceaux de portions entre deux coupures consécutives.
 * 2. GARE-À-GARE (station_edges) : App\Services\NetworkGraph\StationGraph.
 * 3. MESURES (network_graph_builds) : temps, volumes, composantes connexes,
 *    gares non reliées.
 *
 * Portions retenues : status in_service / single_track. Exploitation
 * associative (is_heritage) : gardée et signalée (heritage_m), sauf vélorail
 * (pas de train). Fret seul et réseaux militaire / particulier : gardés,
 * signalés (non_passenger_m) et pénalisés dans le choix des chemins.
 */
final class EraGraphBuilder
{
    public const DEFAULTS = [
        'snap_m' => 15.0,
        'gap_any_m' => 60.0,
        'gap_same_m' => 300.0,
        'attach_near_m' => 30.0,
        'attach_line_m' => 1000.0,
        'attach_fallback_m' => 300.0,
        'non_passenger_factor' => 4.0,
    ];

    private const OPEN_STATUSES = "('active', 'tourist', 'no_commercial')";

    /** @var array<string, float> */
    private array $params;

    /** @var array<string, int> durée de chaque phase (ms) */
    private array $timings = [];

    private float $phaseStart = 0.0;

    /** @param  array<string, float|int|string|null>  $params */
    public function __construct(array $params = [])
    {
        $clean = [];
        foreach (self::DEFAULTS as $key => $default) {
            $clean[$key] = isset($params[$key]) && $params[$key] !== '' ? (float) $params[$key] : $default;
        }
        $this->params = $clean;
    }

    /**
     * @return array<string, mixed> mesures de la construction (cf. network_graph_builds)
     */
    public function build(int $eraId, int $year): array
    {
        $started = microtime(true);
        $this->timings = [];
        $this->phaseStart = $started;
        $p = $this->params;
        $nodeSeq = "pg_get_serial_sequence('network_nodes', 'id')";
        $edgeSeq = "pg_get_serial_sequence('network_edges', 'id')";

        DB::statement("SET work_mem = '128MB'");
        DB::unprepared('DROP TABLE IF EXISTS g_seg, g_endc, g_node, g_cut, g_gap, g_st, g_stline, g_att, g_snode, g_merge, g_piece, g_se, g_bnode, g_tnode, g_nodecomp, g_pg, g_bridge');

        // ── 1. Portions en service de l'ère (Lambert-93) ──────────────────────
        $lgv = Lgv::sql('l', 'ls');
        DB::statement(<<<SQL
            CREATE TEMP TABLE g_seg AS
            SELECT ls.id, ls.line_id, ST_Transform(ls.geom, 2154) AS g, ls.is_heritage, ls.traffic_type,
                   ls.gauge_mm, l.network, {$lgv} AS is_lgv,
                   (ls.traffic_type = 'freight' OR COALESCE(l.network, '') IN ('militaire', 'particulier')) AS non_passenger
            FROM line_segments ls
            JOIN lines l ON l.id = ls.line_id
            WHERE ls.era_id = ? AND ls.status IN ('in_service', 'single_track')
              AND NOT (ls.is_heritage AND COALESCE(ls.heritage_kind, '') = 'velorail')
              AND ST_NPoints(ls.geom) >= 2
            SQL, [$eraId]);
        DB::unprepared('CREATE INDEX ON g_seg USING gist (g); CREATE INDEX ON g_seg (id); CREATE INDEX ON g_seg (line_id); ANALYZE g_seg');
        $segments = (int) DB::scalar('SELECT count(*) FROM g_seg');
        $this->phase('segments');

        // ── 2. Nœuds : extrémités regroupées à moins de snap_m ────────────────
        DB::statement(<<<SQL
            CREATE TEMP TABLE g_endc AS
            SELECT e.seg_id, e.line_id, e.f, e.p, ST_ClusterDBSCAN(e.p, eps := {$p['snap_m']}, minpoints := 1) OVER () AS cid
            FROM (
                SELECT id AS seg_id, line_id, 0::float8 AS f, ST_StartPoint(g) AS p FROM g_seg
                UNION ALL
                SELECT id, line_id, 1::float8, ST_EndPoint(g) FROM g_seg
            ) e
            SQL);
        DB::statement(<<<SQL
            CREATE TEMP TABLE g_node AS
            SELECT nextval({$nodeSeq}) AS id, cid, ST_Centroid(ST_Collect(p)) AS p, count(*)::int AS n_end
            FROM g_endc GROUP BY cid
            SQL);
        DB::unprepared('CREATE INDEX ON g_endc (seg_id); CREATE INDEX ON g_endc (cid); CREATE INDEX ON g_node USING gist (p); CREATE INDEX ON g_node (cid); ANALYZE g_endc; ANALYZE g_node');
        $this->phase('nodes');

        // ── 3. Coupures : extrémités propres + jonctions en T ─────────────────
        DB::unprepared('CREATE TEMP TABLE g_cut (seg_id bigint, f float8, node_id bigint, src char(1))');
        DB::statement("INSERT INTO g_cut SELECT e.seg_id, e.f, n.id, 'e' FROM g_endc e JOIN g_node n ON n.cid = e.cid");
        DB::statement(<<<SQL
            INSERT INTO g_cut
            SELECT s.id, ST_LineLocatePoint(s.g, n.p), n.id, 't'
            FROM g_node n
            JOIN g_seg s ON ST_DWithin(s.g, n.p, {$p['snap_m']})
            WHERE NOT EXISTS (SELECT 1 FROM g_endc e WHERE e.seg_id = s.id AND e.cid = n.cid)
            SQL);
        DB::unprepared('CREATE INDEX ON g_cut (node_id); CREATE INDEX ON g_cut (seg_id); ANALYZE g_cut');
        $this->phase('t_junctions');

        // ── 4. Lacunes ────────────────────────────────────────────────────────
        //  a) continuité d'une ligne : extrémité sans suite sur SA ligne (ni autre
        //     extrémité, ni jonction en T de la même ligne au nœud) → tronçon le plus
        //     proche de la même ligne à moins de gap_same_m (même si l'extrémité
        //     touche déjà une autre ligne : bout de voie d'une gare, faisceau…) ;
        //  b) extrémité isolée (rien à moins de snap_m) sans raccord a) → voie
        //     la plus proche, toutes lignes, à moins de gap_any_m.
        DB::statement(<<<SQL
            CREATE TEMP TABLE g_gap AS
            WITH dang AS (
                SELECT DISTINCT ON (n.id, e.line_id) n.id AS node_id, n.p, e.seg_id, e.line_id
                FROM g_endc e
                JOIN g_node n ON n.cid = e.cid
                WHERE NOT EXISTS (SELECT 1 FROM g_endc e2 WHERE e2.cid = e.cid AND e2.line_id = e.line_id AND e2.seg_id <> e.seg_id)
                  AND NOT EXISTS (SELECT 1 FROM g_cut c JOIN g_seg s2 ON s2.id = c.seg_id
                                  WHERE c.node_id = n.id AND c.src = 't' AND s2.line_id = e.line_id)
            ),
            same_line AS (
                SELECT d.node_id, d.p, x.id, x.g, x.d
                FROM dang d
                CROSS JOIN LATERAL (
                    SELECT s.id, s.g, ST_Distance(s.g, d.p) AS d
                    FROM g_seg s
                    WHERE s.line_id = d.line_id AND s.id <> d.seg_id AND ST_DWithin(s.g, d.p, {$p['gap_same_m']})
                      AND NOT EXISTS (SELECT 1 FROM g_cut c WHERE c.seg_id = s.id AND c.node_id = d.node_id)
                    ORDER BY s.g <-> d.p
                    LIMIT 1
                ) x
            ),
            any_line AS (
                SELECT DISTINCT ON (n.id) n.id AS node_id, n.p, x.id, x.g, x.d
                FROM g_node n
                JOIN g_endc e ON e.cid = n.cid
                CROSS JOIN LATERAL (
                    SELECT s.id, s.g, ST_Distance(s.g, n.p) AS d
                    FROM g_seg s
                    WHERE s.id <> e.seg_id AND ST_DWithin(s.g, n.p, {$p['gap_any_m']})
                    ORDER BY s.g <-> n.p
                    LIMIT 1
                ) x
                WHERE n.n_end = 1
                  AND NOT EXISTS (SELECT 1 FROM g_cut c WHERE c.node_id = n.id AND c.src = 't')
                  AND NOT EXISTS (SELECT 1 FROM same_line sl WHERE sl.node_id = n.id)
            )
            SELECT u.node_id, u.p, u.id AS seg_id, ST_LineLocatePoint(u.g, u.p) AS f, ST_ClosestPoint(u.g, u.p) AS q,
                   u.d, nextval({$nodeSeq}) AS gap_node
            FROM (SELECT * FROM same_line UNION ALL SELECT * FROM any_line) u
            SQL);
        DB::statement("INSERT INTO g_cut SELECT seg_id, f, gap_node, 'g' FROM g_gap");
        $gapJoins = (int) DB::scalar('SELECT count(*) FROM g_gap');
        $this->phase('gaps');

        // ── 5. Gares de l'ère et rattachement aux voies ───────────────────────
        $stationsSource = DB::table('station_eras')->where('era_id', $eraId)->exists() ? 'station_eras' : 'chronology';
        if ($stationsSource === 'station_eras') {
            DB::statement(<<<SQL
                CREATE TEMP TABLE g_st AS
                SELECT s.id AS station_id, ST_Transform(s.geom, 2154) AS p, se.status IN {$this->openStatuses()} AS is_open
                FROM station_eras se JOIN stations s ON s.id = se.station_id
                WHERE se.era_id = ?
                SQL, [$eraId]);
        } else {
            // Repli (station_eras pas encore calculé pour cette ère) : dates propres des gares.
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE g_st AS
                SELECT s.id AS station_id, ST_Transform(s.geom, 2154) AS p,
                       (s.closed_at IS NULL OR s.closed_at > make_date(?, 12, 31)) AS is_open
                FROM stations s
                WHERE s.opened_at IS NULL OR s.opened_at <= make_date(?, 12, 31)
                SQL, [$year, $year]);
        }
        if (DB::table('line_stops')->where('era_id', $eraId)->exists()) {
            DB::statement('CREATE TEMP TABLE g_stline AS SELECT DISTINCT station_id, line_id FROM line_stops WHERE era_id = ?', [$eraId]);
        } else {
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE g_stline AS
                SELECT DISTINCT station_id, line_id FROM station_lines
                WHERE (first_year IS NULL OR first_year <= ?) AND (last_year IS NULL OR last_year > ?)
                SQL, [$year, $year]);
        }
        DB::unprepared('CREATE INDEX ON g_st USING gist (p); CREATE INDEX ON g_st (station_id); CREATE INDEX ON g_stline (station_id, line_id); ANALYZE g_st; ANALYZE g_stline');

        DB::statement(<<<SQL
            CREATE TEMP TABLE g_att AS
            SELECT st.station_id, s.id AS seg_id, ST_LineLocatePoint(s.g, st.p) AS f
            FROM g_st st
            JOIN g_seg s ON ST_DWithin(s.g, st.p, {$p['attach_near_m']})
            WHERE NOT s.is_lgv
               OR EXISTS (SELECT 1 FROM g_stline sl WHERE sl.station_id = st.station_id AND sl.line_id = s.line_id)
            SQL);
        DB::statement(<<<SQL
            INSERT INTO g_att
            SELECT DISTINCT ON (st.station_id, s.line_id) st.station_id, s.id, ST_LineLocatePoint(s.g, st.p)
            FROM g_st st
            JOIN g_stline sl ON sl.station_id = st.station_id
            JOIN g_seg s ON s.line_id = sl.line_id AND ST_DWithin(s.g, st.p, {$p['attach_line_m']})
            ORDER BY st.station_id, s.line_id, ST_Distance(s.g, st.p)
            SQL);
        DB::statement(<<<SQL
            INSERT INTO g_att
            SELECT st.station_id, x.id, ST_LineLocatePoint(x.g, st.p)
            FROM g_st st
            CROSS JOIN LATERAL (
                SELECT s.id, s.g FROM g_seg s
                WHERE ST_DWithin(s.g, st.p, {$p['attach_fallback_m']})
                ORDER BY s.g <-> st.p LIMIT 1
            ) x
            WHERE st.is_open AND NOT EXISTS (SELECT 1 FROM g_att a WHERE a.station_id = st.station_id)
            SQL);
        DB::statement(<<<SQL
            CREATE TEMP TABLE g_snode AS
            SELECT nextval({$nodeSeq}) AS id, st.station_id, st.p, st.is_open
            FROM g_st st
            WHERE EXISTS (SELECT 1 FROM g_att a WHERE a.station_id = st.station_id)
            SQL);
        DB::statement(<<<'SQL'
            INSERT INTO g_cut
            SELECT a.seg_id, a.f, sn.id, CASE WHEN sn.is_open THEN 's' ELSE 'c' END
            FROM g_att a JOIN g_snode sn ON sn.station_id = a.station_id
            SQL);
        // Jonction ou extrémité à moins de snap_m d'une gare sur le même tronçon :
        // elle devient le nœud de la gare (sinon une branche qui part de la gare
        // pourrait la contourner par le nœud voisin, confondu avec elle).
        DB::statement(<<<SQL
            CREATE TEMP TABLE g_merge AS
            SELECT DISTINCT ON (c.node_id) c.node_id AS old_id, sc.node_id AS new_id
            FROM g_cut sc
            JOIN g_snode sn ON sn.id = sc.node_id
            JOIN g_seg s ON s.id = sc.seg_id
            JOIN g_cut c ON c.seg_id = sc.seg_id AND c.src IN ('e', 't', 'g')
            WHERE abs(c.f - sc.f) * ST_Length(s.g) <= {$p['snap_m']}
            ORDER BY c.node_id, abs(c.f - sc.f), sn.is_open DESC, sc.node_id
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE INDEX ON g_merge (old_id); ANALYZE g_merge;
            UPDATE g_cut c SET node_id = m.new_id FROM g_merge m WHERE c.node_id = m.old_id;
            UPDATE g_gap g SET node_id = m.new_id FROM g_merge m WHERE g.node_id = m.old_id;
            UPDATE g_gap g SET gap_node = m.new_id FROM g_merge m WHERE g.gap_node = m.old_id;
            SQL);
        $openStations = (int) DB::scalar('SELECT count(*) FROM g_st WHERE is_open');
        $this->phase('stations');

        // ── 6. Arêtes topologiques : morceaux entre coupures consécutives ─────
        DB::statement(<<<SQL
            CREATE TEMP TABLE g_piece AS
            WITH c AS (SELECT DISTINCT seg_id, f, node_id FROM g_cut),
            o AS (
                SELECT seg_id, f, node_id,
                       lead(f) OVER w AS f2, lead(node_id) OVER w AS n2
                FROM c WINDOW w AS (PARTITION BY seg_id ORDER BY f, node_id)
            ),
            cut AS (
                SELECT o.seg_id, o.node_id AS from_node, o.n2 AS to_node,
                       CASE WHEN o.f2 > o.f THEN ST_Transform(ST_LineSubstring(s.g, o.f, o.f2), 4326) END AS geom,
                       s.line_id, s.is_heritage, s.traffic_type, s.network, s.gauge_mm, s.non_passenger
                FROM o JOIN g_seg s ON s.id = o.seg_id
                WHERE o.n2 IS NOT NULL AND o.n2 <> o.node_id
            )
            SELECT nextval({$edgeSeq}) AS id, seg_id, from_node, to_node, geom,
                   COALESCE(ROUND(ST_Length(geom::geography)), 0)::int AS length_m,
                   line_id, is_heritage, traffic_type, network, gauge_mm, non_passenger, false AS is_connector
            FROM cut
            SQL);
        DB::statement(<<<SQL
            INSERT INTO g_piece
            SELECT nextval({$edgeSeq}), NULL, node_id, gap_node, ST_Transform(ST_MakeLine(p, q), 4326),
                   ROUND(d)::int, NULL, false, NULL, NULL, NULL, false, true
            FROM g_gap
            WHERE node_id <> gap_node
            SQL);
        // Coupures quasi confondues : ST_LineSubstring rend un point → pas de géométrie.
        DB::statement("UPDATE g_piece SET geom = NULL WHERE geom IS NOT NULL AND GeometryType(geom) <> 'LINESTRING'");
        DB::unprepared('CREATE INDEX ON g_piece (id); ANALYZE g_piece');
        $this->phase('pieces');

        $bridges = $this->bridgeComponents();
        $this->phase('bridges');

        // ── 7. Gare-à-gare (PHP) ─────────────────────────────────────────────
        $stationNodes = [];
        $closedNodes = [];
        foreach (DB::select('SELECT id, station_id, is_open FROM g_snode') as $r) {
            if ($r->is_open) {
                $stationNodes[(int) $r->id] = (int) $r->station_id;
            } else {
                $closedNodes[(int) $r->id] = true;
            }
        }
        $pieces = (function () {
            foreach (DB::cursor('SELECT id, from_node, to_node, length_m, line_id, is_heritage, non_passenger, is_connector, gauge_mm FROM g_piece') as $r) {
                yield [
                    'id' => (int) $r->id, 'from' => (int) $r->from_node, 'to' => (int) $r->to_node,
                    'length_m' => (int) $r->length_m, 'line_id' => $r->line_id !== null ? (int) $r->line_id : null,
                    'heritage' => (bool) $r->is_heritage, 'non_passenger' => (bool) $r->non_passenger,
                    'connector' => (bool) $r->is_connector, 'gauge_mm' => $r->gauge_mm !== null ? (int) $r->gauge_mm : null,
                ];
            }
        })();
        $graph = new StationGraph($pieces, $stationNodes, $closedNodes, $p['non_passenger_factor']);
        $edges = $graph->stationEdges();
        $commercial = array_filter($edges, fn ($e) => $e['heritage_m'] === 0);
        // Connexité du réseau ouvert à l'ère, lignes associatives comprises.
        $components = StationGraph::components($edges, array_values($stationNodes));
        $this->phase('station_graph');

        DB::unprepared(<<<'SQL'
            CREATE TEMP TABLE g_se (
                from_id bigint, to_id bigint, line_id bigint, length_m int, non_passenger_m int, heritage_m int,
                skipped int, gaps int, line_ids bigint[], line_lengths int[], gauges smallint[], piece_ids bigint[]
            )
            SQL);
        foreach (array_chunk($edges, 500) as $chunk) {
            DB::table('g_se')->insert(array_map(fn ($e) => [
                'from_id' => $e['from_station_id'], 'to_id' => $e['to_station_id'], 'line_id' => $e['line_id'],
                'length_m' => $e['length_m'], 'non_passenger_m' => $e['non_passenger_m'], 'heritage_m' => $e['heritage_m'],
                'skipped' => $e['skipped_count'], 'gaps' => $e['gap_joins'],
                'line_ids' => self::pgArray($e['line_ids']), 'line_lengths' => self::pgArray($e['line_lengths']),
                'gauges' => self::pgArray($e['gauges']), 'piece_ids' => self::pgArray($e['piece_ids']),
            ], $chunk));
        }
        $this->phase('station_edges_prepare');

        // ── 8. Remplacement atomique du graphe de l'ère ──────────────────────
        $counts = DB::transaction(function () use ($eraId) {
            // Graphe dérivé : pas de vérification des clés étrangères pendant l'écriture
            // (session seulement). Sinon chaque arête pose un verrou FOR KEY SHARE sur sa
            // ligne et sa gare, et l'écriture bloque, voire interbloque, les traitements
            // qui modifient lines / stations au même moment (dédoublonnage, gares).
            DB::statement("SET LOCAL session_replication_role = 'replica'");
            DB::table('station_edges')->where('era_id', $eraId)->delete();
            DB::table('network_edges')->where('era_id', $eraId)->delete();
            DB::table('network_nodes')->where('era_id', $eraId)->delete();

            $nodes = DB::affectingStatement(<<<'SQL'
                INSERT INTO network_nodes (id, era_id, station_id, geom, kind, created_at, updated_at)
                SELECT n.id, ?, n.station_id, ST_Transform(n.p, 4326),
                       COALESCE(n.kind, CASE WHEN d.deg = 1 THEN 'end' WHEN d.deg = 2 THEN 'link' ELSE 'junction' END),
                       now(), now()
                FROM (
                    SELECT id, p, NULL::bigint AS station_id, NULL::text AS kind FROM g_node
                    UNION ALL
                    SELECT id, p, station_id, CASE WHEN is_open THEN 'station' ELSE 'closed_station' END FROM g_snode
                    UNION ALL
                    SELECT gap_node, q, NULL, 'gap' FROM g_gap
                    WHERE NOT EXISTS (SELECT 1 FROM g_snode sn WHERE sn.id = g_gap.gap_node) -- fusionné dans une gare
                    UNION ALL
                    SELECT id, q, NULL, 'gap' FROM g_bnode
                ) n
                JOIN (
                    SELECT node, count(*) AS deg
                    FROM (SELECT from_node AS node FROM g_piece UNION ALL SELECT to_node FROM g_piece) x
                    GROUP BY node
                ) d ON d.node = n.id
                SQL, [$eraId]);

            $edges = DB::affectingStatement(<<<'SQL'
                INSERT INTO network_edges (id, era_id, from_node_id, to_node_id, segment_id, line_id, length_m, status,
                                           geom, is_heritage, is_connector, traffic_type, network, gauge_mm, created_at, updated_at)
                SELECT id, ?, from_node, to_node, seg_id, line_id, length_m,
                       CASE WHEN is_connector THEN 'connector' ELSE 'in_service' END,
                       geom, is_heritage, is_connector, traffic_type, network, gauge_mm, now(), now()
                FROM g_piece
                SQL, [$eraId]);

            $stationEdges = DB::affectingStatement(<<<'SQL'
                INSERT INTO station_edges (era_id, from_station_id, to_station_id, line_id, length_m, skipped_count,
                                           non_passenger_m, heritage_m, gap_joins, line_ids, line_lengths, gauges, geom,
                                           created_at, updated_at)
                SELECT ?, e.from_id, e.to_id, e.line_id, e.length_m, e.skipped, e.non_passenger_m, e.heritage_m, e.gaps,
                       e.line_ids, e.line_lengths, e.gauges,
                       (SELECT ST_Multi(ST_LineMerge(ST_Collect(p.geom ORDER BY u.ord)))
                        FROM unnest(e.piece_ids) WITH ORDINALITY AS u(pid, ord)
                        JOIN g_piece p ON p.id = u.pid),
                       now(), now()
                FROM g_se e
                SQL, [$eraId]);

            return compact('nodes', 'edges', 'stationEdges');
        });
        $this->phase('write');

        $stats = [
            'segments' => $segments,
            'nodes' => $counts['nodes'],
            'edges' => $counts['edges'],
            'station_nodes' => count($stationNodes),
            'closed_station_nodes' => count($closedNodes),
            'station_edges' => $counts['stationEdges'],
            'heritage_station_edges' => count($edges) - count($commercial),
            'gap_joins' => $gapJoins + $bridges,
            'open_stations' => $openStations,
            'unattached_stations' => $openStations - count($stationNodes),
            'isolated_stations' => $components['isolated'],
            'components' => $components['components'],
            'largest_component' => $components['largest'],
            'stations_source' => $stationsSource,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];

        DB::table('network_graph_builds')->updateOrInsert(['era_id' => $eraId], [
            ...$stats,
            'built_at' => now(),
            'params' => json_encode($this->params),
            'timings' => json_encode($this->timings),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::unprepared('DROP TABLE IF EXISTS g_seg, g_endc, g_node, g_cut, g_gap, g_st, g_stline, g_att, g_snode, g_merge, g_piece, g_se, g_bnode, g_tnode, g_nodecomp, g_pg, g_bridge');

        return $stats + ['timings' => $this->timings];
    }

    /**
     * Pontage des composantes connexes de la topologie. Les règles locales de
     * lacunes ne voient pas une coupure entre deux faisceaux de voies
     * parallèles d'une même ligne (chaque bord a l'air « continu ») : tant
     * qu'une composante secondaire a un nœud de voie à moins de gap_any_m
     * d'une autre composante (gap_same_m sur une même ligne), on les relie
     * par un raccord, au point le plus proche (5 passes au plus). Les réseaux
     * réellement séparés (Corse, voie métrique isolée…) restent séparés.
     *
     * @return int nombre de raccords ajoutés
     */
    private function bridgeComponents(): int
    {
        $p = $this->params;
        $nodeSeq = "pg_get_serial_sequence('network_nodes', 'id')";
        $edgeSeq = "pg_get_serial_sequence('network_edges', 'id')";
        DB::unprepared(<<<'SQL'
            CREATE TEMP TABLE g_bnode (id bigint, q geometry);
            CREATE TEMP TABLE g_tnode AS
                SELECT id, p FROM g_node
                UNION ALL
                SELECT gap_node, q FROM g_gap WHERE NOT EXISTS (SELECT 1 FROM g_snode sn WHERE sn.id = g_gap.gap_node);
            CREATE INDEX ON g_tnode (id);
            CREATE TEMP TABLE g_nodecomp (node_id bigint PRIMARY KEY, comp bigint);
            CREATE TEMP TABLE g_pg AS
                SELECT id, line_id, from_node, to_node, ST_Transform(geom, 2154) AS g, 0::bigint AS comp
                FROM g_piece WHERE geom IS NOT NULL;
            CREATE INDEX ON g_pg USING gist (g); CREATE INDEX ON g_pg (id); CREATE INDEX ON g_pg (from_node);
            ANALYZE g_pg;
            SQL);

        $added = 0;
        for ($pass = 0; $pass < 5; $pass++) {
            // Composantes (union-find), la principale = la plus longue.
            $parent = [];
            $find = function (int $x) use (&$parent): int {
                while ($parent[$x] !== $x) {
                    $parent[$x] = $parent[$parent[$x]];
                    $x = $parent[$x];
                }

                return $x;
            };
            $pieces = DB::select('SELECT from_node, to_node, length_m FROM g_piece');
            foreach ($pieces as $r) {
                $a = (int) $r->from_node;
                $b = (int) $r->to_node;
                $parent[$a] ??= $a;
                $parent[$b] ??= $b;
                $ra = $find($a);
                $rb = $find($b);
                if ($ra !== $rb) {
                    $parent[$ra] = $rb;
                }
            }
            $length = [];
            foreach ($pieces as $r) {
                $root = $find((int) $r->from_node);
                $length[$root] = ($length[$root] ?? 0) + (int) $r->length_m;
            }
            if (count($length) < 2) {
                break;
            }
            arsort($length);
            $main = array_key_first($length);
            $rows = [];
            foreach (array_keys($parent) as $node) {
                $root = $find($node);
                if ($root !== $main) {
                    $rows[] = ['node_id' => $node, 'comp' => $root];
                }
            }
            DB::statement('TRUNCATE g_nodecomp');
            foreach (array_chunk($rows, 2000) as $chunk) {
                DB::table('g_nodecomp')->insert($chunk);
            }
            DB::unprepared(<<<'SQL'
                ANALYZE g_nodecomp;
                UPDATE g_pg SET comp = 0 WHERE comp <> 0;
                UPDATE g_pg pg SET comp = nc.comp FROM g_nodecomp nc WHERE nc.node_id = pg.from_node;
                DROP TABLE IF EXISTS g_bridge;
                SQL);

            DB::statement(<<<SQL
                CREATE TEMP TABLE g_bridge AS
                SELECT DISTINCT ON (nc.comp) nc.node_id, t.p, x.id AS piece_id, x.from_node, x.to_node, x.f, x.len,
                       ST_ClosestPoint(x.g, t.p) AS q, x.d, NULL::bigint AS bnode
                FROM g_nodecomp nc
                JOIN g_tnode t ON t.id = nc.node_id
                CROSS JOIN LATERAL (
                    SELECT pg.id, pg.from_node, pg.to_node, pg.g, ST_LineLocatePoint(pg.g, t.p) AS f,
                           ST_Length(pg.g) AS len, ST_Distance(pg.g, t.p) AS d
                    FROM g_pg pg
                    WHERE pg.comp <> nc.comp AND ST_DWithin(pg.g, t.p, {$p['gap_same_m']})
                      AND (ST_DWithin(pg.g, t.p, {$p['gap_any_m']})
                           OR pg.line_id IN (SELECT gp.line_id FROM g_piece gp
                                             WHERE (gp.from_node = t.id OR gp.to_node = t.id) AND gp.line_id IS NOT NULL))
                    ORDER BY pg.g <-> t.p
                    LIMIT 1
                ) x
                ORDER BY nc.comp, x.d
                SQL);
            $count = (int) DB::scalar('SELECT count(*) FROM g_bridge');
            if ($count === 0) {
                break;
            }
            $added += $count;

            // Point d'arrivée : extrémité de la pièce si elle est à moins de snap_m, sinon un nouveau nœud.
            DB::statement(<<<SQL
                UPDATE g_bridge SET bnode = CASE
                    WHEN f * len <= {$p['snap_m']} THEN from_node
                    WHEN (1 - f) * len <= {$p['snap_m']} THEN to_node
                    ELSE nextval({$nodeSeq}) END
                SQL);
            DB::unprepared(<<<SQL
                INSERT INTO g_bnode SELECT bnode, q FROM g_bridge WHERE bnode NOT IN (from_node, to_node);
                INSERT INTO g_tnode SELECT bnode, q FROM g_bridge WHERE bnode NOT IN (from_node, to_node);
                CREATE TEMP TABLE g_split AS
                WITH c AS (
                    SELECT piece_id, 0::float8 AS f, from_node AS node FROM g_bridge WHERE bnode NOT IN (from_node, to_node)
                    UNION SELECT piece_id, 1::float8, to_node FROM g_bridge WHERE bnode NOT IN (from_node, to_node)
                    UNION SELECT piece_id, f, bnode FROM g_bridge WHERE bnode NOT IN (from_node, to_node)
                ),
                o AS (
                    SELECT piece_id, f, node, lead(f) OVER w AS f2, lead(node) OVER w AS n2
                    FROM c WINDOW w AS (PARTITION BY piece_id ORDER BY f, node)
                )
                SELECT nextval({$edgeSeq}) AS id, gp.seg_id, o.node AS from_node, o.n2 AS to_node,
                       CASE WHEN o.f2 > o.f THEN ST_Transform(ST_LineSubstring(pg.g, o.f, o.f2), 4326) END AS geom,
                       gp.line_id, gp.is_heritage, gp.traffic_type, gp.network, gp.gauge_mm, gp.non_passenger
                FROM o JOIN g_piece gp ON gp.id = o.piece_id JOIN g_pg pg ON pg.id = o.piece_id
                WHERE o.n2 IS NOT NULL AND o.n2 <> o.node;
                UPDATE g_split SET geom = NULL WHERE geom IS NOT NULL AND GeometryType(geom) <> 'LINESTRING';
                DELETE FROM g_piece WHERE id IN (SELECT piece_id FROM g_bridge WHERE bnode NOT IN (from_node, to_node));
                DELETE FROM g_pg WHERE id IN (SELECT piece_id FROM g_bridge WHERE bnode NOT IN (from_node, to_node));
                INSERT INTO g_piece
                SELECT id, seg_id, from_node, to_node, geom, COALESCE(ROUND(ST_Length(geom::geography)), 0)::int,
                       line_id, is_heritage, traffic_type, network, gauge_mm, non_passenger, false
                FROM g_split;
                INSERT INTO g_pg SELECT id, line_id, from_node, to_node, ST_Transform(geom, 2154), 0 FROM g_split WHERE geom IS NOT NULL;
                INSERT INTO g_piece
                SELECT nextval({$edgeSeq}), NULL, node_id, bnode, ST_Transform(ST_MakeLine(p, q), 4326), ROUND(d)::int,
                       NULL, false, NULL, NULL, NULL, false, true
                FROM g_bridge WHERE node_id <> bnode;
                DROP TABLE g_split;
                SQL);
        }

        return $added;
    }

    private function openStatuses(): string
    {
        return self::OPEN_STATUSES;
    }

    private function phase(string $name): void
    {
        $now = microtime(true);
        $this->timings[$name] = (int) round(($now - $this->phaseStart) * 1000);
        $this->phaseStart = $now;
    }

    /** @param  list<int>  $values */
    private static function pgArray(array $values): string
    {
        return '{'.implode(',', array_map('intval', $values)).'}';
    }
}
