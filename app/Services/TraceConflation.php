<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recalage (« conflation ») d'un tracé peu précis sur les géométries précises
 * qui suivent le même axe, RATTACHÉES OU NON à la ligne.
 *
 * Un tracé archeoferroviaire (cordes jusqu'à plusieurs km), OHM ou SNCF
 * (forme généralisée, jusqu'à ~165 m d'écart) est remplacé, portion par
 * portion, par la géométrie réelle d'un tronçon BD TOPO (toutes natures sauf
 * projets, funiculaires ; voies non exploitées comprises), d'un way OSM (y
 * compris abandoned / razed / disused et préfixes de cycle de vie) ou d'une
 * contribution validée. Rien n'est lissé ni inventé : là où aucune géométrie
 * précise ne suit l'axe, le tracé d'origine est gardé tel quel (et signalé).
 *
 * Méthode (appariement de type HMM / Viterbi, cf. Newson & Krumm 2009) :
 *   1. Le tracé grossier est découpé en observations de STEP_M mètres.
 *   2. Candidats = réseau précis du corridor, nœudé et fusionné par famille
 *      (ST_Union + ST_LineMerge) : une arête = une voie entre deux jonctions.
 *      Corridor : ~60 m pour archeo / OHM, élargi au milieu des longues cordes
 *      de la flèche admissible c²/8R ; 250 m pour SNCF.
 *   3. Émission = ½(écart / sigma)² + (écart angulaire / 25°)² + priorité
 *      (BD TOPO principale < OSM < voies de service) ; état « garder le tracé
 *      d'origine » à coût fixe. Transition : nulle en restant sur la même voie
 *      (si la progression le long de la voie suit celle du tracé), faible à une
 *      jonction réelle (arêtes qui se touchent), proportionnelle au saut sinon.
 *      Une double voie OSM reste ainsi sur UN axe, et aux bifurcations la voie
 *      qui continue coûte moins qu'un saut vers la ligne voisine.
 *   4. Contrôles : forme de l'écart par corde (flèche de courbe plausible, pas
 *      de bosse : voir chordTest), puis par sous-tronçon d'1 km : distance de
 *      Hausdorff densifiée ≤ corridor, rapport de longueurs plausible (pas de
 *      détour ni de boucle) et, pour une voie rattachée à une AUTRE ligne,
 *      écart médian ≤ 12 m (sinon c'est une voie parallèle, pas la même ;
 *      la voie candidate suivante est alors essayée : nextBestTrack).
 *   4 bis. Tronc commun aux jonctions (junctionTrunk) : portion sans voie
 *      précise propre qui longe à 12-30 m la voie d'une autre ligne et
 *      converge vers elle : elle prend cette voie (voie partagée).
 *   5. Assemblage : jonctions recousues au nœud commun (ou par un raccord de
 *      quelques mètres entre deux voies précises) ; trou de données < 60 m
 *      franchi par un raccord droit ; ailleurs le tracé d'origine est gardé,
 *      raccordé au point où les deux tracés divergent.
 *
 * Toutes les géométries sont en Lambert-93 (EPSG:2154). Utilise des tables
 * temporaires de session (cf_*) : appeler preparePool() une fois par commande.
 */
class TraceConflation
{
    /** Longueur des observations le long du tracé grossier (m). */
    public const STEP_M = 25.0;

    /**
     * Familles précises candidates : [famille, classe, pénalité de priorité, filtre SQL sur source_traces].
     * Métro, tram urbain actif, funiculaires, miniatures et LGV en projet sont exclus.
     */
    public const CANDIDATES = [
        ['contrib', 'main', -0.3, "source = 'contrib'"],
        ['bdtopo', 'main', 0.0, "source = 'bdtopo' AND layer IN ('LGV', 'Voie ferrée principale', 'Sans objet') AND COALESCE(properties->>'etat_de_l_objet', '') <> 'En projet'"],
        ['osm', 'main', 0.3, "source = 'osm' AND layer NOT IN ('subway', 'tram', 'monorail', 'funicular', 'miniature', 'light_rail', 'proposed') AND (properties->>'service') IS NULL"],
        ['bdtopo', 'service', 1.0, "source = 'bdtopo' AND layer = 'Voie de service' AND COALESCE(properties->>'etat_de_l_objet', '') <> 'En projet'"],
        ['osm', 'service', 1.2, "source = 'osm' AND layer NOT IN ('subway', 'tram', 'monorail', 'funicular', 'miniature', 'light_rail', 'proposed') AND (properties->>'service') IS NOT NULL"],
        // Voie extraite des orthophotos IGN 1950-1965 (ml/rail-extraction) : ~2-5 m, entre OSM et les voies de service.
        ['ign-historique', 'ortho', 0.8, "source = 'ign-historique' AND layer LIKE 'ortho-%' AND match_score >= 0.7"],
    ];

    /** Sources témoins (croisement, jamais candidates au dessin). */
    public const WITNESSES = [
        ['archeoferroviaire', 'coarse', "source = 'archeoferroviaire' AND layer <> 'exploitee'"],
        ['ohm', 'coarse', "source = 'ohm' AND layer NOT IN ('subway', 'tram', 'proposed', 'light_rail')"],
        ['sncf', 'coarse', "source = 'sncf'"],
    ];

    /**
     * Paramètres par source grossière :
     *   w0 = corridor de base (m) ; sigma0 = écart « normal » (m) qui règle la
     *   préférence pour une voie précise ; r_min / sag_cap = flèche admissible
     *   au milieu d'une corde de longueur c : min(c² / 8 r_min, sag_cap), répartie
     *   en parabole entre les sommets (ajoutée au corridor et à sigma) ; c_none =
     *   coût de l'état « garder le tracé d'origine » ; other_line_median = écart
     *   médian max (m) pour accepter une voie rattachée à une autre ligne ;
     *   chord_test = contrôle de forme par corde (voir chordTest()) ; jump_max =
     *   écart max (m) entre voie précise et tracé d'origine à une transition
     *   (au-delà, on recule jusqu'au point de divergence : pas de long biais).
     *
     * Calibrage (285 000 sommets archeo à moins de 15 m d'une voie précise) :
     * écart médian 1 m, p90 5-6 m, p98 10-13 m au milieu des cordes, quelle que
     * soit leur longueur. Mais là où archeo n'a que quelques sommets, la corde
     * coupe la courbe (flèche c²/8R) : la voie précise est alors acceptée si son
     * écart a la FORME d'une flèche (parabole entre deux sommets). Un écart
     * local en bosse (voie verte qui contourne une gare) ne l'a pas : on garde
     * archeo et la divergence est signalée. Les formes SNCF (généralisées,
     * jusqu'à ~165 m d'écart, sommets eux-mêmes décalés) gardent un corridor large.
     */
    public const PROFILES = [
        'archeoferroviaire' => ['w0' => 60.0, 'sigma0' => 10.0, 'r_min' => 250.0, 'sag_cap' => 80.0, 'c_none' => 2.0, 'other_line_median' => 12.0, 'chord_test' => true, 'jump_max' => 40.0, 'junction_m' => 30.0],
        'ohm' => ['w0' => 60.0, 'sigma0' => 10.0, 'r_min' => 250.0, 'sag_cap' => 80.0, 'c_none' => 2.0, 'other_line_median' => 12.0, 'chord_test' => true, 'jump_max' => 40.0, 'junction_m' => 30.0],
        'sncf' => ['w0' => 250.0, 'sigma0' => 80.0, 'r_min' => 0.0, 'sag_cap' => 0.0, 'c_none' => 3.0, 'other_line_median' => null, 'chord_test' => false, 'jump_max' => 100.0, 'junction_m' => null],
    ];

    /** Coût d'entrée / sortie de l'état « pas de géométrie précise ». */
    private const K_NONE = 1.5;

    /** Changement de voie à une jonction réelle / sans jonction (+ saut / JUMP_SCALE). */
    private const K_SWITCH_CONNECTED = 0.3;

    private const K_SWITCH_FREE = 0.8;

    private const JUMP_SCALE = 5.0;

    /** Écart angulaire max entre le tracé grossier et la voie (degrés). */
    private const MAX_ANGLE = 40.0;

    /** Trou de données franchi par un raccord droit entre deux voies précises (m) ; au-delà, le tracé d'origine est gardé. */
    private const SHORT_GAP_M = 60.0;

    /** Portion précise isolée plus courte que ça : ignorée (évite les crochets). */
    private const MIN_ISOLATED_RUN_M = 75.0;

    /** Sous-tronçon de validation (observations) : 40 × 25 m = 1 km. */
    private const VALIDATE_BLOCK = 40;

    /** Contrôle de forme : écart max toléré sans contrôle, décalage max aux sommets (m). */
    private const CHORD_FREE_M = 20.0;

    private const CHORD_VERTEX_M = 40.0;

    /**
     * Tronc commun à une jonction (junctionTrunk). Calibrage sur les portions d'archeo / OHM
     * laissées hors voie précise le long de la voie d'une autre ligne (France, 2026-10) : voir
     * le docblock de junctionTrunk et storage/app/reports/agents/jonctions.md.
     *   JUNCTION_ANGLE : écart angulaire max avec la voie de l'autre ligne (parallèle, pas croisement) ;
     *   JUNCTION_OWN_M : voie principale propre à moins de ça = voie distincte (dispersion archeo p90 5-6 m) ;
     *   JUNCTION_MIN_OBS : portion minimale (observations de STEP_M) ;
     *   TERMINAL_OWN_M : bout de tracé à moins de ça d'une géométrie précise de la ligne = relais, pas terminus ;
     *   PARALLEL_REPORT_M : parallélisme sans convergence signalé à partir de cette longueur.
     */
    private const JUNCTION_ANGLE = 25.0;

    /**
     * Voies qui ne prouvent pas une voie distincte de la ligne au droit d'une jonction : voies de
     * service (faisceau de gare), et voies extraites des orthophotos IGN (ign-historique, classe
     * « ortho ») : l'extraction part du tracé archeo et y trouve toujours une voie du faisceau.
     */
    private const JUNCTION_WEAK_CLASSES = ['service', 'ortho'];

    /**
     * Réseaux qui ne partagent pas la voie d'une ligne « principal » quand l'écartement n'est pas
     * connu (voie métrique, 60 cm, tramway le long de la route) ; « particulier » reste permis
     * (embranchements à voie normale). Écartements connus : ils doivent être égaux.
     */
    public const JUNCTION_NARROW_NETWORKS = ['secondaire', 'militaire'];

    /** Voie propre qui longe un bloc posé sur la voie d'une autre ligne (m) : la voie de l'autre ligne est refusée. */
    private const OWN_PARALLEL_M = 30.0;

    /** Repli sur la voie suivante : passes au plus (voies d'autres lignes refusées l'une après l'autre). */
    private const NEXT_BEST_PASSES = 3;

    private const JUNCTION_OWN_M = 8.0;

    private const JUNCTION_MIN_OBS = 3;

    /** Convergence cherchée sur JOIN_OBS observations après la portion ; croisement sur CROSS_OBS de plus. */
    private const JOIN_OBS = 3;

    private const CROSS_OBS = 6;

    private const TERMINAL_OWN_M = 60.0;

    /** Fin de tracé reprise jusqu'à la gare de l'autre ligne : au plus 6 observations (150 m), gare à 150 m du bout, à 60 m de la voie. */
    private const TERMINAL_TAIL_OBS = 6;

    private const TERMINAL_STATION_M = 150.0;

    private const STATION_TRUNK_M = 60.0;

    private const PARALLEL_REPORT_M = 200.0;

    /** Recherche des tracés repris de l'autre ligne autour d'une portion de tronc commun (m). */
    private const JUNCTION_MAX_BUFFER_M = 40.0;

    /** Voies précises non rattachées du corridor qui portent le nom (ou l'élément Wikidata) de la ligne en cours (pids de cf_pool). */
    private array $namedOwn = [];

    /** Portions de tronc commun (merged_trunk) et parallèles signalés (kept_parallel) du dernier appel à match(). */
    private array $junctions = [];

    private bool $poolReady = false;

    /** Cordes refusées par le contrôle de forme (diagnostic, rempli si $debug). */
    public array $rejectedChords = [];

    public bool $debug = false;

    public array $debugRuns = [];

    /** Durées cumulées par étape (s), pour le diagnostic de performance. */
    public array $timings = [];

    private float $tick = 0;

    private function lap(string $step): void
    {
        $now = microtime(true);
        $this->timings[$step] = ($this->timings[$step] ?? 0) + $now - $this->tick;
        $this->tick = $now;
    }

    /**
     * Crée les tables temporaires de session et charge le pool de géométries
     * (candidates précises + témoins), éventuellement restreint à une emprise
     * (WKT Lambert-93) pour un traitement ligne à ligne.
     */
    public function preparePool(?string $envelopeWkt = null): int
    {
        // Requêtes courtes et nombreuses : la compilation JIT coûte plus qu'elle ne rapporte.
        DB::statement('SET jit = off');
        DB::statement('DROP TABLE IF EXISTS cf_pool');
        DB::statement('CREATE TEMP TABLE cf_pool (pid serial PRIMARY KEY, id bigint, family text, cls text, prio float8, cand boolean, line_id bigint, len float8, g geometry(LineString, 2154))');

        $zone = $envelopeWkt ? 'AND st.geom && ST_Transform(ST_GeomFromText(:env, 2154), 4326)' : '';
        $superseded = "id NOT IN (SELECT (jsonb_array_elements_text(properties->'supersedes'))::bigint
                                  FROM source_traces WHERE source = 'contrib' AND properties->'supersedes' IS NOT NULL)";
        $n = 0;
        $rows = array_merge(
            array_map(fn ($c) => [$c[0], $c[1], $c[2], true, $c[3]], self::CANDIDATES),
            array_map(fn ($w) => [$w[0], $w[1], 9.0, false, $w[2]], self::WITNESSES),
        );
        foreach ($rows as [$family, $cls, $prio, $cand, $filter]) {
            $n += DB::affectingStatement(<<<SQL
                INSERT INTO cf_pool (id, family, cls, prio, cand, line_id, len, g)
                SELECT st.id, :family, :cls, :prio, :cand, st.line_id, ST_Length(x.g), x.g
                FROM source_traces st, LATERAL ST_Dump(ST_Transform(st.geom, 2154)) d,
                     -- Morceaux d'au plus ~1 km : les mesures (distance, direction) restent locales et rapides.
                     LATERAL (SELECT CEIL(ST_Length(d.geom) / 1000)::int AS n) q,
                     LATERAL (SELECT ST_LineSubstring(d.geom, i::float8 / q.n, (i + 1)::float8 / q.n) AS g
                              FROM generate_series(0, q.n - 1) i) x
                WHERE {$filter} AND {$superseded} {$zone} AND ST_Length(d.geom) > 0.5
                SQL, array_filter(['family' => $family, 'cls' => $cls, 'prio' => $prio, 'cand' => $cand ? 't' : 'f', 'env' => $envelopeWkt], fn ($v) => $v !== null));
        }
        DB::statement('CREATE INDEX ON cf_pool USING gist (g)');
        foreach (['contrib', 'bdtopo', 'osm', 'ign-historique', 'archeoferroviaire', 'ohm', 'sncf'] as $f) {
            DB::statement("CREATE INDEX ON cf_pool USING gist (g) WHERE family = '{$f}'");
        }
        DB::statement('ANALYZE cf_pool');

        DB::statement('DROP TABLE IF EXISTS cf_lines');
        DB::statement('CREATE TEMP TABLE cf_lines (k int PRIMARY KEY, len float8, g geometry(LineString, 2154))');
        DB::statement('DROP TABLE IF EXISTS cf_chunks');
        DB::statement('CREATE TEMP TABLE cf_chunks (k int, i int, s geometry(Point, 2154), e geometry(Point, 2154), m geometry(Point, 2154), az float8, PRIMARY KEY (k, i))');
        DB::statement('DROP TABLE IF EXISTS cf_edges');
        DB::statement('CREATE TEMP TABLE cf_edges (eid serial PRIMARY KEY, family text, cls text, prio float8, len float8, g geometry(LineString, 2154))');
        DB::statement('CREATE INDEX ON cf_edges USING gist (g)');
        DB::statement('DROP TABLE IF EXISTS cf_sel');
        DB::statement('CREATE TEMP TABLE cf_sel (pid int PRIMARY KEY)');

        // Écart angulaire (0-90°) entre une direction (radians) et une voie au droit d'un point.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION pg_temp.cf_angle(line geometry, pt geometry, az float8, half float8 DEFAULT 6)
            RETURNS float8 LANGUAGE sql IMMUTABLE AS $$
                SELECT CASE WHEN a IS NULL OR az IS NULL THEN 0
                       ELSE degrees(asin(LEAST(1, abs(sin(a - az))))) END
                FROM (SELECT ST_Azimuth(ST_LineInterpolatePoint(line, GREATEST(0, f - half / NULLIF(ST_Length(line), 0))),
                                        ST_LineInterpolatePoint(line, LEAST(1, f + half / NULLIF(ST_Length(line), 0)))) AS a
                      FROM (SELECT ST_LineLocatePoint(line, pt) AS f) q) r
            $$
            SQL);

        $this->poolReady = true;

        return $n;
    }

    /**
     * Recale un tracé grossier (EWKB hex, Lambert-93, Line ou MultiLine).
     *
     * @return array{pieces: array<int, array{family: ?string, ewkb: string, len: float, trace_ids: array<int, int>}>, stats: array<string, float>, junctions: list<array<string, mixed>>}
     *                                                                                                                                                                                     family = bdtopo | osm | contrib | ign-historique (géométrie précise), connector (raccord), null (tracé d'origine conservé)
     */
    public function match(int $lineId, string $ewkb, string $profile): array
    {
        if (! $this->poolReady) {
            $this->preparePool();
        }
        $p = self::PROFILES[$profile] ?? self::PROFILES['archeoferroviaire'];
        $wMax = $p['w0'] + $p['sag_cap'];
        $step = self::STEP_M;

        $this->tick = microtime(true);
        $this->junctions = [];
        DB::delete('DELETE FROM cf_lines');
        DB::delete('DELETE FROM cf_chunks');
        DB::delete('DELETE FROM cf_edges');

        DB::insert(<<<'SQL'
            INSERT INTO cf_lines (k, len, g)
            SELECT row_number() OVER (), ST_Length(d.geom), d.geom
            FROM ST_Dump(ST_LineMerge(ST_CollectionExtract(ST_GeomFromEWKB(decode(:g, 'hex')), 2))) d
            WHERE ST_Length(d.geom) > 1
            SQL, ['g' => $ewkb]);

        DB::insert(<<<'SQL'
            INSERT INTO cf_chunks (k, i, s, e, m, az)
            SELECT l.k, i, a, b, ST_LineInterpolatePoint(l.g, (i + 0.5) / q.n), ST_Azimuth(a, b)
            FROM cf_lines l,
                 LATERAL (SELECT GREATEST(1, CEIL(l.len / :step))::int AS n) q,
                 LATERAL generate_series(0, q.n - 1) i,
                 LATERAL (SELECT ST_LineInterpolatePoint(l.g, i::float8 / q.n) AS a, ST_LineInterpolatePoint(l.g, (i + 1)::float8 / q.n) AS b) pts
            SQL, ['step' => $step]);

        $this->lap('lines+chunks');
        // Réseau précis du corridor, nœudé et fusionné par famille / classe. Sélection par les
        // observations (index du pool), pas par la ligne entière (emprise trop large).
        DB::delete('DELETE FROM cf_sel');
        DB::insert('INSERT INTO cf_sel SELECT DISTINCT p.pid FROM cf_chunks c JOIN cf_pool p ON p.cand AND ST_DWithin(p.g, c.m, :w)', ['w' => $wMax + 20]);
        DB::insert(<<<'SQL'
            INSERT INTO cf_edges (family, cls, prio, len, g)
            SELECT family, cls, prio, ST_Length(d.geom), d.geom
            FROM (
                SELECT family, cls, MIN(prio) AS prio, ST_LineMerge(ST_Union(p.g)) AS u
                FROM cf_pool p JOIN cf_sel USING (pid)
                GROUP BY family, cls
            ) n, LATERAL ST_Dump(n.u) d
            WHERE ST_Length(d.geom) > 0.5
            SQL);

        $this->namedOwn = $this->namedOwnTracks($lineId);
        $this->lap('edges');
        $lines = [];
        foreach (DB::select('SELECT k, len FROM cf_lines ORDER BY k') as $r) {
            $lines[$r->k] = ['len' => (float) $r->len];
        }
        $empty = ['pieces' => [], 'stats' => ['coarse_m' => 0.0, 'matched_m' => 0.0, 'connector_m' => 0.0, 'kept_m' => 0.0], 'junctions' => []];
        if (! $lines) {
            return $empty;
        }

        $edges = [];
        foreach (DB::select('SELECT eid, family, cls, prio, len FROM cf_edges') as $r) {
            $edges[$r->eid] = ['family' => $r->family, 'cls' => $r->cls, 'prio' => (float) $r->prio, 'len' => (float) $r->len];
        }

        // Sommets du tracé grossier (positions cumulées) : élargissement du corridor au milieu des cordes.
        $vertices = [];
        foreach (DB::select('SELECT k, ST_X(dp.geom) x, ST_Y(dp.geom) y FROM cf_lines, LATERAL ST_DumpPoints(g) dp ORDER BY k, dp.path[1]') as $r) {
            $vertices[$r->k][] = [(float) $r->x, (float) $r->y];
        }
        $chunks = [];
        foreach (DB::select('SELECT k, i, ST_X(s) sx, ST_Y(s) sy FROM cf_chunks ORDER BY k, i') as $r) {
            $chunks[$r->k][$r->i] = ['sx' => (float) $r->sx, 'sy' => (float) $r->sy, 'cands' => []];
        }
        $positions = [];
        foreach ($lines as $k => &$line) {
            $line['n'] = count($chunks[$k]);
            $line['clen'] = $line['len'] / $line['n'];
            $positions[$k] = $this->positions($vertices[$k] ?? []);
            [$line['w'], $line['sigma']] = $this->corridors($positions[$k], $line['n'], $line['clen'], $p);
            $line['c_none'] = $p['c_none'];
            $line['jump_max'] = $p['jump_max'];
            $line['ex'] = end($vertices[$k])[0];
            $line['ey'] = end($vertices[$k])[1];
        }
        unset($line);

        if ($edges) {
            $pairs = DB::select(<<<'SQL'
                SELECT c.k, c.i, e.eid, ST_Distance(c.m, e.g) AS d,
                       pg_temp.cf_angle(e.g, c.m, c.az) AS theta,
                       f.fm, f.fs, f.fe,
                       ST_X(ps) AS qx, ST_Y(ps) AS qy, ST_X(pe) AS px, ST_Y(pe) AS py
                FROM cf_chunks c
                JOIN cf_edges e ON ST_DWithin(e.g, c.m, :w)
                CROSS JOIN LATERAL (SELECT ST_LineLocatePoint(e.g, c.m) AS fm, ST_LineLocatePoint(e.g, c.s) AS fs,
                                           ST_LineLocatePoint(e.g, c.e) AS fe) f
                CROSS JOIN LATERAL (SELECT ST_LineInterpolatePoint(e.g, f.fs) AS ps, ST_LineInterpolatePoint(e.g, f.fe) AS pe) x
                SQL, ['w' => $wMax]);
            foreach ($pairs as $r) {
                $k = $r->k;
                $i = $r->i;
                if ($r->d > $lines[$k]['w'][$i] || $r->theta > self::MAX_ANGLE) {
                    continue;
                }
                $chunks[$k][$i]['cands'][$r->eid] = [
                    'd' => (float) $r->d, 'theta' => (float) $r->theta, 'fm' => (float) $r->fm,
                    'fs' => (float) $r->fs, 'fe' => (float) $r->fe,
                    'qx' => (float) $r->qx, 'qy' => (float) $r->qy, 'px' => (float) $r->px, 'py' => (float) $r->py,
                ];
            }
        }

        $this->lap('pairs');
        // Jonctions réelles : extrémités communes (≤ 2 m) entre deux arêtes.
        $conn = [];
        if ($edges) {
            foreach (DB::select(<<<'SQL'
                SELECT a.eid AS ea, b.eid AS eb, ST_X(t.p) x, ST_Y(t.p) y,
                       ST_LineLocatePoint(a.g, t.p) fa, ST_LineLocatePoint(b.g, t.p) fb
                FROM cf_edges a JOIN cf_edges b ON a.eid < b.eid AND ST_DWithin(a.g, b.g, 2)
                CROSS JOIN LATERAL (
                    SELECT v.p FROM (VALUES (ST_StartPoint(a.g)), (ST_EndPoint(a.g)), (ST_StartPoint(b.g)), (ST_EndPoint(b.g))) v(p)
                    WHERE ST_DWithin(v.p, a.g, 2) AND ST_DWithin(v.p, b.g, 2)
                ) t
                SQL) as $r) {
                $conn[$r->ea][$r->eb][] = ['x' => (float) $r->x, 'y' => (float) $r->y, 'f_self' => (float) $r->fa, 'f_other' => (float) $r->fb];
                $conn[$r->eb][$r->ea][] = ['x' => (float) $r->x, 'y' => (float) $r->y, 'f_self' => (float) $r->fb, 'f_other' => (float) $r->fa];
            }
        }

        $this->lap('conn');
        // Appariement, validation, assemblage.
        $specs = [];
        $stats = ['coarse_m' => 0.0, 'matched_m' => 0.0, 'connector_m' => 0.0, 'kept_m' => 0.0, 'rejected_runs' => 0, 'chord_rejected_m' => 0.0];
        foreach ($lines as $k => $line) {
            $stats['coarse_m'] += $line['len'];
            $states = $this->viterbi($chunks[$k], $line, $edges, $conn);
            if ($p['chord_test']) {
                $states = $this->chordTest($states, $chunks[$k], $line, $positions[$k], $vertices[$k], $p, $stats);
            }
            $runs = $this->runs($states, $chunks[$k], $line, $edges);
            $this->lap('viterbi');
            $rejectedOther = [];
            $states = $this->validate($lineId, $k, $runs, $states, $chunks[$k], $line, $p, $stats, $rejectedOther);
            if ($rejectedOther) {
                $states = $this->nextBestTrack($lineId, $k, $rejectedOther, $states, $chunks[$k], $line, $edges, $conn, $positions[$k], $vertices[$k], $p, $stats);
            }
            $this->lap('validate');
            $locked = [];
            [$states, $locked] = $this->junctionTrunk($lineId, $k, $states, $chunks[$k], $line, $edges, $conn, $p, $stats);
            $this->lap('junction');
            $states = $this->absorbShortDivergences($this->runs($states, $chunks[$k], $line, $edges), $states, $chunks[$k], $line);
            // Le recul peut traverser plusieurs portions précises successives (voies qui se relaient).
            for ($pass = 0; $pass < 4; $pass++) {
                $before = $states;
                $states = $this->backToDivergencePoint($this->runs($states, $chunks[$k], $line, $edges), $states, $chunks[$k], $line['jump_max'], $locked);
                if ($states === $before) {
                    break;
                }
            }
            $states = $this->breakLongJumps($this->runs($states, $chunks[$k], $line, $edges), $states, $chunks[$k], $line, $conn);
            $runs = $this->runs($states, $chunks[$k], $line, $edges);
            $sp = $this->assemble($k, $runs, $chunks[$k], $line, $edges, $conn);
            if ($this->debug) {
                $this->debugRuns[$k] = ['runs' => $runs, 'specs' => $sp, 'n' => $line['n'], 'clen' => $line['clen'],
                    'd' => array_map(fn ($r) => $r['s'] ? [$chunks[$k][$r['i0']]['cands'][$r['s']]['d'] ?? null, $chunks[$k][$r['i1']]['cands'][$r['s']]['d'] ?? null] : null, $runs)];
            }
            array_push($specs, ...$sp);
        }

        $this->lap('assemble');
        $pieces = $this->build($specs);
        $this->lap('build');
        foreach ($pieces as $piece) {
            $key = match ($piece['family']) {
                null => 'kept_m',
                'connector' => 'connector_m',
                default => 'matched_m',
            };
            $stats[$key] += $piece['len'];
        }

        return ['pieces' => $pieces, 'stats' => $stats, 'junctions' => $this->junctionGeometries($lines)];
    }

    /** Positions cumulées (m) des sommets d'un tracé. */
    private function positions(array $vertices): array
    {
        $pos = [0.0];
        for ($v = 1; $v < count($vertices); $v++) {
            $pos[$v] = $pos[$v - 1] + hypot($vertices[$v][0] - $vertices[$v - 1][0], $vertices[$v][1] - $vertices[$v - 1][1]);
        }

        return $pos;
    }

    /** Flèche admissible (m) au milieu d'une corde de longueur $c. */
    private function sagitta(float $c, array $p): float
    {
        return $p['r_min'] > 0 ? min($c * $c / (8 * $p['r_min']), $p['sag_cap']) : 0.0;
    }

    /**
     * Corridor (m) et écart normal sigma (m) de chaque observation : base + flèche
     * admissible au milieu des longues cordes.
     *
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    private function corridors(array $pos, int $n, float $clen, array $p): array
    {
        $w = array_fill(0, $n, $p['w0']);
        $sigma = array_fill(0, $n, $p['sigma0']);
        if ($p['r_min'] <= 0 || count($pos) < 2) {
            return [$w, $sigma];
        }
        $v = 0;
        $last = count($pos) - 1;
        for ($i = 0; $i < $n; $i++) {
            $x = ($i + 0.5) * $clen;
            while ($v < $last - 1 && $pos[$v + 1] < $x) {
                $v++;
            }
            $c = $pos[min($v + 1, $last)] - $pos[$v];
            if ($c <= 0) {
                continue;
            }
            $t = max(0.0, min($c, $x - $pos[$v]));
            $extra = $this->sagitta($c, $p) * 4 * $t * ($c - $t) / ($c * $c);
            $w[$i] = $p['w0'] + $extra;
            $sigma[$i] = $p['sigma0'] + $extra;
        }

        return [$w, $sigma];
    }

    /**
     * Contrôle de forme par corde : entre deux sommets du tracé grossier (corde
     * ≥ 60 m entièrement appariée), si la voie précise s'en écarte de plus de
     * CHORD_FREE_M, son écart signé doit s'expliquer par a(1−τ) + bτ + h·4τ(1−τ)
     * (décalage des sommets + flèche d'une courbe), avec |a|, |b| ≤ 40 m,
     * |h| ≤ flèche admissible et un résidu ≤ max(6 m, 25 % de l'écart max).
     * Sinon (bosse : voie verte qui contourne une gare, chicane) la corde
     * repasse en « garder le tracé d'origine ».
     */
    private function chordTest(array $states, array $chunks, array $line, array $pos, array $vertices, array $p, array &$stats): array
    {
        $n = $line['n'];
        $clen = $line['clen'];
        $last = count($pos) - 1;
        for ($v = 0; $v < $last; $v++) {
            $c = $pos[$v + 1] - $pos[$v];
            if ($c < 60) {
                continue;
            }
            // Observations dont la fin tombe strictement dans la corde.
            $i0 = (int) ceil($pos[$v] / $clen);
            $i1 = min($n - 1, (int) floor($pos[$v + 1] / $clen) - 1);
            if ($i1 - $i0 + 1 < 4) {
                continue;
            }
            $ok = true;
            for ($i = $i0; $i <= $i1; $i++) {
                if ($states[$i] === 0) {
                    $ok = false;
                    break;
                }
            }
            if (! $ok) {
                continue;
            }
            $ux = ($vertices[$v + 1][0] - $vertices[$v][0]) / $c;
            $uy = ($vertices[$v + 1][1] - $vertices[$v][1]) / $c;
            $pts = [];
            $maxO = 0.0;
            for ($i = $i0; $i <= $i1; $i++) {
                $cand = $chunks[$i]['cands'][$states[$i]];
                $ex = $chunks[$i + 1]['sx'] ?? $line['ex'];
                $ey = $chunks[$i + 1]['sy'] ?? $line['ey'];
                $o = $ux * ($cand['py'] - $ey) - $uy * ($cand['px'] - $ex);
                $tau = (($i + 1) * $clen - $pos[$v]) / $c;
                $pts[] = [$tau, $o];
                $maxO = max($maxO, abs($o));
            }
            // Écart dans la dispersion normale d'archeo (p98 ≈ 13 m) : rien à contrôler.
            if ($maxO <= self::CHORD_FREE_M) {
                continue;
            }
            [$a, $b, $h, $res] = $this->fitChord($pts);
            $sag = $this->sagitta($c, $p);
            if (abs($a) <= self::CHORD_VERTEX_M && abs($b) <= self::CHORD_VERTEX_M && abs($h) <= $sag + 5 && $res <= max(6.0, 0.25 * $maxO)) {
                continue;
            }
            $stats['chord_rejected_m'] = ($stats['chord_rejected_m'] ?? 0) + $c;
            $this->debug && $this->rejectedChords[] = ['c' => round($c), 'a' => round($a, 1), 'b' => round($b, 1), 'h' => round($h, 1), 'res' => round($res, 1), 'max' => round($maxO, 1), 'sag' => round($sag, 1),
                'x' => $vertices[$v][0], 'y' => $vertices[$v][1]];
            for ($i = max(0, $i0 - 1); $i <= $i1 + 1 && $i < $n; $i++) {
                $states[$i] = 0;
            }
        }

        return $states;
    }

    /**
     * Moindres carrés de o(τ) = a(1−τ) + bτ + h·4τ(1−τ).
     *
     * @return array{0: float, 1: float, 2: float, 3: float} a, b, h, résidu max
     */
    private function fitChord(array $pts): array
    {
        $m = array_fill(0, 3, array_fill(0, 3, 0.0));
        $r = [0.0, 0.0, 0.0];
        foreach ($pts as [$t, $o]) {
            $f = [1 - $t, $t, 4 * $t * (1 - $t)];
            for ($j = 0; $j < 3; $j++) {
                $r[$j] += $f[$j] * $o;
                for ($k = 0; $k < 3; $k++) {
                    $m[$j][$k] += $f[$j] * $f[$k];
                }
            }
        }
        // Résolution 3×3 (Cramer) ; système dégénéré → pas de modèle.
        $det = fn ($q) => $q[0][0] * ($q[1][1] * $q[2][2] - $q[1][2] * $q[2][1])
            - $q[0][1] * ($q[1][0] * $q[2][2] - $q[1][2] * $q[2][0])
            + $q[0][2] * ($q[1][0] * $q[2][1] - $q[1][1] * $q[2][0]);
        $d = $det($m);
        if (abs($d) < 1e-9) {
            return [0.0, 0.0, 0.0, INF];
        }
        $sol = [];
        for ($j = 0; $j < 3; $j++) {
            $q = $m;
            for ($k = 0; $k < 3; $k++) {
                $q[$k][$j] = $r[$k];
            }
            $sol[$j] = $det($q) / $d;
        }
        $res = 0.0;
        foreach ($pts as [$t, $o]) {
            $res = max($res, abs($o - ($sol[0] * (1 - $t) + $sol[1] * $t + $sol[2] * 4 * $t * (1 - $t))));
        }

        return [$sol[0], $sol[1], $sol[2], $res];
    }

    /** Viterbi : un état par observation = arête précise (eid) ou 0 (pas de géométrie précise). */
    private function viterbi(array $chunks, array $line, array $edges, array $conn): array
    {
        $n = $line['n'];
        $clen = $line['clen'];
        $cost = [];
        $back = [];
        $prev = null;
        for ($i = 0; $i < $n; $i++) {
            $sigma = $line['sigma'][$i];
            $emit = [0 => $line['c_none']];
            foreach ($chunks[$i]['cands'] as $eid => $c) {
                $emit[$eid] = 0.5 * ($c['d'] / $sigma) ** 2 + $edges[$eid]['prio'] + ($c['theta'] / 25) ** 2;
            }
            $cur = [];
            foreach ($emit as $s => $e) {
                if ($prev === null) {
                    $cur[$s] = $e;
                    $back[$i][$s] = null;

                    continue;
                }
                $best = INF;
                $arg = 0;
                foreach ($prev as $q => $pc) {
                    $t = $pc + $this->transition($q, $s, $chunks[$i - 1]['cands'][$q] ?? null, $chunks[$i]['cands'][$s] ?? null, $edges, $conn, $clen);
                    if ($t < $best) {
                        $best = $t;
                        $arg = $q;
                    }
                }
                $cur[$s] = $best + $e;
                $back[$i][$s] = $arg;
            }
            $prev = $cur;
        }
        // Retour arrière.
        $states = array_fill(0, $n, 0);
        $s = array_keys($prev, min($prev))[0];
        for ($i = $n - 1; $i >= 0; $i--) {
            $states[$i] = $s;
            $s = $back[$i][$s] ?? 0;
        }

        return $states;
    }

    private function transition(int $q, int $s, ?array $cq, ?array $cs, array $edges, array $conn, float $clen): float
    {
        if ($q === 0 && $s === 0) {
            return 0.0;
        }
        if ($q === 0 || $s === 0) {
            return self::K_NONE;
        }
        if ($q === $s) {
            // Même voie : la progression le long de la voie doit suivre celle du tracé (pas de saut de lacet).
            $delta = abs($cs['fm'] - $cq['fm']) * $edges[$s]['len'];

            return 4 * max(0.0, $delta - 2 * $clen) / $clen;
        }
        // Projection de la frontière commune sur chacune des deux voies.
        $jump = hypot($cq['px'] - $cs['qx'], $cq['py'] - $cs['qy']);
        foreach ($conn[$q][$s] ?? [] as $t) {
            if (hypot($t['x'] - $cq['px'], $t['y'] - $cq['py']) <= 2 * $clen + 10) {
                return self::K_SWITCH_CONNECTED;
            }
        }

        return self::K_SWITCH_FREE + $jump / self::JUMP_SCALE;
    }

    /**
     * Regroupe les observations consécutives de même état en portions
     * [state, i0, i1]. Une même voie reprise après un saut (lacet) ouvre une
     * nouvelle portion ; une portion précise isolée trop courte est ignorée.
     */
    private function runs(array $states, array $chunks, array $line, array $edges): array
    {
        $runs = [];
        foreach ($states as $i => $s) {
            $last = $runs ? count($runs) - 1 : -1;
            $cont = $last >= 0 && $runs[$last]['s'] === $s;
            if ($cont && $s !== 0) {
                $delta = abs($chunks[$i]['cands'][$s]['fm'] - $chunks[$i - 1]['cands'][$s]['fm']) * $edges[$s]['len'];
                $cont = $delta <= 3 * $line['clen'];
            }
            if ($cont) {
                $runs[$last]['i1'] = $i;
            } else {
                $runs[] = ['s' => $s, 'i0' => $i, 'i1' => $i];
            }
        }

        return $this->dropIsolated($runs, $line);
    }

    private function dropIsolated(array $runs, array $line): array
    {
        foreach ($runs as $r => $run) {
            $len = ($run['i1'] - $run['i0'] + 1) * $line['clen'];
            $prevNone = $r === 0 || $runs[$r - 1]['s'] === 0;
            $nextNone = $r === count($runs) - 1 || $runs[$r + 1]['s'] === 0;
            if ($run['s'] !== 0 && $len < self::MIN_ISOLATED_RUN_M && $prevNone && $nextNone) {
                $runs[$r]['s'] = 0;
            }
        }
        // Refusion des portions « sans géométrie précise » adjacentes.
        $out = [];
        foreach ($runs as $run) {
            $last = count($out) - 1;
            if ($last >= 0 && $run['s'] === 0 && $out[$last]['s'] === 0) {
                $out[$last]['i1'] = $run['i1'];
            } else {
                $out[] = $run;
            }
        }

        return $out;
    }

    /**
     * Validation par sous-tronçon (blocs d'au plus VALIDATE_BLOCK observations
     * d'une même portion précise) : distance de Hausdorff densifiée au tracé
     * grossier ≤ corridor, rapport de longueurs plausible, et écart médian
     * borné pour une voie rattachée à une autre ligne. Un bloc refusé repasse
     * en « pas de géométrie précise ».
     */
    private function validate(int $lineId, int $k, array $runs, array $states, array $chunks, array $line, array $p, array &$stats, array &$rejectedOther = []): array
    {
        $rows = [];
        foreach ($runs as $run) {
            if ($run['s'] === 0) {
                continue;
            }
            $s = $run['s'];
            for ($b0 = $run['i0']; $b0 <= $run['i1']; $b0 += self::VALIDATE_BLOCK) {
                $b1 = min($run['i1'], $b0 + self::VALIDATE_BLOCK - 1);
                // Dernier bloc trop court : rattaché au précédent.
                if ($b1 < $run['i1'] && self::VALIDATE_BLOCK / 4 > $run['i1'] - $b1) {
                    $b1 = $run['i1'];
                }
                $d = [];
                $wMax = 0;
                for ($i = $b0; $i <= $b1; $i++) {
                    $d[] = $chunks[$i]['cands'][$s]['d'];
                    $wMax = max($wMax, $line['w'][$i]);
                }
                sort($d);
                $rows[] = [
                    'r' => count($rows), 'eid' => $s, 'i0' => $b0, 'i1' => $b1,
                    'f0' => $chunks[$b0]['cands'][$s]['fs'], 'f1' => $chunks[$b1]['cands'][$s]['fe'],
                    'a0' => $b0 / $line['n'], 'a1' => ($b1 + 1) / $line['n'],
                    'wmax' => $wMax, 'dmed' => $d[intdiv(count($d), 2)],
                ];
                $b0 = $b1 + 1 - self::VALIDATE_BLOCK;
            }
        }
        if (! $rows) {
            return $states;
        }

        $res = DB::select(<<<'SQL'
            WITH r AS (
                SELECT * FROM unnest(CAST(:r AS int[]), CAST(:eid AS int[]), CAST(:f0 AS float8[]), CAST(:f1 AS float8[]),
                                     CAST(:a0 AS float8[]), CAST(:a1 AS float8[])) AS t(r, eid, f0, f1, a0, a1)
            ),
            g AS (
                SELECT r.r, e.family, ST_LineSubstring(e.g, LEAST(r.f0, r.f1), GREATEST(r.f0, r.f1)) AS pg,
                       ST_LineSubstring(l.g, r.a0, LEAST(1, r.a1)) AS cg
                FROM r JOIN cf_edges e ON e.eid = r.eid JOIN cf_lines l ON l.k = :k
            ),
            gb AS (SELECT * FROM g)
            SELECT gb.r, ST_Length(pg) AS lp, ST_Length(cg) AS lc,
                   CASE WHEN ST_Length(pg) < 1 THEN 0 ELSE ST_HausdorffDistance(pg, cg, 0.2) END AS haus,
                   -- Voies précises empruntées toutes rattachées à une AUTRE ligne ?
                   (SELECT bool_and(p.line_id IS NOT NULL AND p.line_id <> :line)
                    FROM cf_pool p JOIN cf_sel USING (pid)
                    WHERE p.family = gb.family AND ST_DWithin(p.g, gb.pg, 1)
                      AND (ST_DWithin(ST_LineInterpolatePoint(p.g, 0.25), gb.pg, 1.5)::int
                           + ST_DWithin(ST_LineInterpolatePoint(p.g, 0.5), gb.pg, 1.5)::int
                           + ST_DWithin(ST_LineInterpolatePoint(p.g, 0.75), gb.pg, 1.5)::int) >= 2) AS other_line,
                   -- Voie propre de la ligne (rattachée, ou non rattachée mais portant son nom) qui longe le bloc à moins de OWN_PARALLEL_M.
                   (SELECT COALESCE(SUM(ST_Length(ST_Intersection(q.g, ST_Buffer(gb.pg, :own_m)))), 0) >= 0.6 * ST_Length(gb.pg)
                    FROM cf_pool q JOIN cf_sel USING (pid)
                    WHERE q.family IN ('contrib', 'bdtopo', 'osm') AND (q.line_id = :line2 OR q.pid = ANY(CAST(:named AS int[])))
                      AND ST_DWithin(q.g, gb.pg, :own_m2) AND NOT ST_DWithin(q.g, gb.pg, 3)) AS own_alt
            FROM gb
            SQL, [
            'r' => $this->intArray(array_column($rows, 'r')), 'eid' => $this->intArray(array_column($rows, 'eid')),
            'f0' => $this->floatArray(array_column($rows, 'f0')), 'f1' => $this->floatArray(array_column($rows, 'f1')),
            'a0' => $this->floatArray(array_column($rows, 'a0')), 'a1' => $this->floatArray(array_column($rows, 'a1')),
            'k' => $k, 'line' => $lineId, 'line2' => $lineId, 'named' => $this->intArray($this->namedOwn),
            'own_m' => self::OWN_PARALLEL_M, 'own_m2' => self::OWN_PARALLEL_M,
        ]);
        foreach ($res as $v) {
            $row = $rows[$v->r];
            $ok = $v->haus <= $row['wmax'] + 5;
            if ($ok && $v->lc >= 100) {
                $ratio = $v->lp / max($v->lc, 1);
                $ok = $ratio >= 0.67 && $ratio <= 1.5;
            }
            if ($ok && $v->other_line && $p['other_line_median'] !== null) {
                // Voie d'une autre ligne trop loin du tracé, ou alors que la voie propre de la ligne la longe (deux voies
                // distinctes, ex. voie touristique à 14 m de la voie SNCF) : refusée, la voie suivante est essayée.
                $ok = $row['dmed'] <= $p['other_line_median'] && ! $v->own_alt;
                if (! $ok) {
                    $rejectedOther[] = [$row['i0'], $row['i1'], $row['eid']];
                    $v->own_alt && $stats['own_track_preferred'] = ($stats['own_track_preferred'] ?? 0) + 1;
                }
            }
            if (! $ok) {
                $stats['rejected_runs']++;
                for ($i = $row['i0']; $i <= $row['i1']; $i++) {
                    $states[$i] = 0;
                }
            }
        }

        return $states;
    }

    /**
     * Voies précises du corridor non rattachées à une ligne mais qui portent le nom de la ligne en cours (« Ancienne
     * ligne de Caen à Vire », « Ligne de Bettelainville à Waldwisse » pour Bettelainville - Hombourg) ou son article
     * Wikipédia : ce sont ses voies. Nom : tous les lieux du nom de la ligne, ou un lieu qui n'est dans le nom d'aucune
     * autre ligne ayant une voie dans le corridor (Caen, pas Vire, à côté de Vire - Romagny).
     *
     * @return list<int> pids de cf_pool
     */
    private function namedOwnTracks(int $lineId): array
    {
        $line = DB::selectOne('SELECT name, wikipedia_title FROM lines WHERE id = ?', [$lineId]);
        if (! $line) {
            return [];
        }
        $tokens = self::placeTokens((string) $line->name);
        $others = [];
        foreach (DB::select('SELECT DISTINCT l.name FROM cf_pool p JOIN cf_sel USING (pid) JOIN lines l ON l.id = p.line_id WHERE p.line_id <> ?', [$lineId]) as $o) {
            $others = array_merge($others, self::placeTokens((string) $o->name));
        }
        $distinctive = array_values(array_diff($tokens, $others));
        $wiki = $line->wikipedia_title !== null ? 'fr:'.$line->wikipedia_title : null;
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT p.pid, st.properties->>'name' AS name, st.properties->>'wikipedia' AS wp
            FROM cf_pool p JOIN cf_sel USING (pid) JOIN source_traces st ON st.id = p.id
            WHERE p.line_id IS NULL AND p.family IN ('bdtopo', 'osm') AND (st.properties->>'name' IS NOT NULL OR st.properties->>'wikipedia' IS NOT NULL)
            ORDER BY p.pid
            SQL) as $r) {
            $words = $r->name !== null ? self::placeTokens((string) $r->name) : [];
            $named = $words && $tokens && (array_diff($tokens, $words) === [] || array_intersect($distinctive, $words) !== []);
            if ($named || ($wiki !== null && $r->wp === $wiki)) {
                $out[] = (int) $r->pid;
            }
        }

        return $out;
    }

    /**
     * Noms de lieux d'un nom de ligne (minuscules, sans accents, mots d'au moins 3 lettres hors mots génériques).
     *
     * @return list<string>
     */
    public static function placeTokens(string $name): array
    {
        $ascii = strtolower(Str::ascii($name));
        $words = preg_split('/[^a-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['ligne', 'ancienne', 'ancien', 'voie', 'gare', 'les', 'des', 'sur', 'sous', 'embranchement', 'raccordement', 'chemin',
            'fer', 'tramway', 'tram', 'militaire', 'industrielle', 'ville', 'saint', 'sainte', 'aux', 'par', 'via', 'lgv', 'frontiere'];

        return array_values(array_unique(array_filter($words, fn ($w) => strlen($w) >= 3 && ! in_array($w, $stop, true))));
    }

    /**
     * Repli sur la voie suivante : un sous-tronçon refusé parce qu'il empruntait la voie d'une
     * AUTRE ligne trop loin du tracé (écart médian > other_line_median) retombait entièrement
     * sur le tracé d'origine, alors qu'une voie précise propre (voie OSM non rattachée, voie de
     * faisceau…) le suit souvent de plus près : la voie de la BD TOPO, prioritaire, gagnait au
     * Viterbi sous une longue corde (sigma élargi). Le Viterbi est relancé sans cette voie sur
     * ces observations, puis revalidé ; ses états remplacent ceux des observations refusées.
     */
    private function nextBestTrack(int $lineId, int $k, array $rejected, array $states, array $chunks, array $line, array $edges, array $conn, array $pos, array $vertices, array $p, array &$stats): array
    {
        $ranges = $rejected;
        // Plusieurs voies de l'autre ligne peuvent se relayer (BD TOPO, puis OSM de la même voie…) : jusqu'à NEXT_BEST_PASSES.
        $alt = $states;
        for ($pass = 0, $todo = $rejected; $todo && $pass < self::NEXT_BEST_PASSES; $pass++) {
            foreach ($todo as [$i0, $i1, $eid]) {
                for ($i = $i0; $i <= $i1; $i++) {
                    unset($chunks[$i]['cands'][$eid]);
                }
            }
            $alt = $this->viterbi($chunks, $line, $edges, $conn);
            $scratch = ['rejected_runs' => 0];
            if ($p['chord_test']) {
                $alt = $this->chordTest($alt, $chunks, $line, $pos, $vertices, $p, $scratch);
            }
            $todo = [];
            $alt = $this->validate($lineId, $k, $this->runs($alt, $chunks, $line, $edges), $alt, $chunks, $line, $p, $scratch, $todo);
        }
        foreach ($ranges as [$i0, $i1]) {
            for ($i = $i0; $i <= $i1; $i++) {
                if ($alt[$i] !== 0) {
                    $stats['next_best_m'] = ($stats['next_best_m'] ?? 0) + $line['clen'];
                }
                $states[$i] = $alt[$i];
            }
        }

        return $states;
    }

    /**
     * Tronc commun à une jonction : une portion sans géométrie précise propre
     * (tracé d'origine gardé, voie de service / de faisceau, ou voie IGN
     * historique : JUNCTION_WEAK_CLASSES) qui longe, à
     * moins de junction_m (et à moins de JUNCTION_ANGLE), la voie PRINCIPALE
     * d'une AUTRE ligne (trace rattachée à une autre ligne, aucune à celle-ci),
     * et qui CONVERGE vers elle, prend cette voie : voie partagée, que
     * vff:build-shared-track masque ensuite selon App\Support\TrackPriority.
     *
     * Convergence, d'un côté au moins de la portion : juste après elle (≤ 3
     * observations), la ligne est sur cette voie, sur une voie qui la rejoint à
     * une jonction réelle, ou à moins de other_line_median de celle-ci (elle la
     * rejoint) ; ou bien le tracé s'arrête là (gare de jonction, terminus sur
     * l'autre ligne) sans géométrie précise de la ligne à moins de
     * TERMINAL_OWN_M (sinon le bout du tracé n'est qu'un passage de relais).
     *
     * Les deux lignes doivent pouvoir partager la voie (écartements connus égaux ;
     * à défaut, pas de ligne secondaire ou militaire sur une ligne principale :
     * JUNCTION_NARROW_NETWORKS) : sinon la portion est gardée et signalée
     * (kept_parallel, « écartement différent » / « réseau différent »).
     *
     * Une voie principale précise propre à moins de OWN_M du tracé d'origine
     * (BD TOPO, OSM y compris abandoned / razed, contribution : non rattachée à
     * une autre ligne) prouve une voie distincte : l'observation est exclue. Un
     * parallélisme d'au moins PARALLEL_REPORT_M sans convergence est gardé tel
     * quel et signalé (kept_parallel). Calibrage : voir JUNCTION_* ci-dessous.
     *
     * @return array{0: array<int, int>, 1: array<int, bool>} états, observations verrouillées (tronc commun)
     */
    private function junctionTrunk(int $lineId, int $k, array $states, array $chunks, array $line, array $edges, array $conn, array $p, array &$stats): array
    {
        $jm = $p['junction_m'] ?? null;
        $n = $line['n'];
        if ($jm === null || ! $edges) {
            return [$states, []];
        }
        $weak = fn (int $s) => $s === 0 || in_array($edges[$s]['cls'] ?? null, self::JUNCTION_WEAK_CLASSES, true);
        // Couples (observation, voie non « service ») à examiner.
        $pairs = [];
        for ($i = 0; $i < $n; $i++) {
            if (! $weak($states[$i])) {
                continue;
            }
            foreach ($chunks[$i]['cands'] as $eid => $c) {
                if (! in_array($edges[$eid]['cls'] ?? null, self::JUNCTION_WEAK_CLASSES, true) && $c['d'] <= $jm) {
                    $pairs[] = [$i, $eid];
                }
            }
        }
        if (! $pairs) {
            return [$states, []];
        }
        $owner = $this->trackOwners($lineId, $k, $pairs);

        // Par observation : voie de l'autre ligne la plus proche, ou exclusion (voie propre).
        $trunk = array_fill(0, $n, null);
        foreach ($pairs as [$i, $eid]) {
            $c = $chunks[$i]['cands'][$eid];
            $o = $owner[$i][$eid] ?? null;
            if ($o === null) {
                continue;
            }
            if ($o['own'] && $c['d'] <= self::JUNCTION_OWN_M) {
                $trunk[$i] = false;
            } elseif ($trunk[$i] !== false && $o['other'] && ! $o['own'] && $c['theta'] <= self::JUNCTION_ANGLE
                && ($trunk[$i] === null || $this->trunkScore($c, $edges[$eid]) < $this->trunkScore($chunks[$i]['cands'][$trunk[$i]], $edges[$trunk[$i]]))) {
                $trunk[$i] = $eid;
            }
        }

        // Portions : observations éligibles consécutives ; une seule voie par portion (la plus fréquente).
        $locked = [];
        $records = [];
        for ($i = 0; $i < $n;) {
            if (! is_int($trunk[$i])) {
                $i++;

                continue;
            }
            $j = $i;
            while ($j + 1 < $n && is_int($trunk[$j + 1])) {
                $j++;
            }
            $count = array_count_values(array_slice($trunk, $i, $j - $i + 1));
            arsort($count);
            $t = (int) array_key_first($count);
            // Sous-portion où cette voie est candidate (≤ junction_m) de bout en bout.
            foreach ($this->spans($i, $j, fn ($x) => isset($chunks[$x]['cands'][$t]) && $chunks[$x]['cands'][$t]['d'] <= $jm) as [$a, $b]) {
                if ($b - $a + 1 < self::JUNCTION_MIN_OBS) {
                    continue;
                }
                $d = array_map(fn ($x) => $chunks[$x]['cands'][$t]['d'], range($a, $b));
                sort($d);
                $rec = ['k' => $k, 'i0' => $a, 'i1' => $b, 'eid' => $t, 'family' => $edges[$t]['family'], 'gap' => $d[intdiv(count($d), 2)],
                    'len' => ($b - $a + 1) * $line['clen'], 'lines' => $owner[$a][$t]['lines'] ?? [], 'why' => $owner[$a][$t]['why'] ?? null];
                $side = array_sum(array_map(fn ($x) => $this->side($chunks[$x], $t), range($a, $b))) <=> 0;
                // Bout du tracé : la portion y arrive, ou s'arrête à moins de TERMINAL_TAIL_OBS d'une gare de l'autre ligne.
                $head = $this->terminalTail($lineId, $k, $a, -1, $t, $chunks, $line);
                $tail = $this->terminalTail($lineId, $k, $b, 1, $t, $chunks, $line);
                $converges = $this->joinsTrunk($a - 1, -1, $t, $side, $states, $chunks, $line, $conn, $p)
                    || $this->joinsTrunk($b + 1, 1, $t, $side, $states, $chunks, $line, $conn, $p)
                    || $head !== null || $tail !== null;
                if ($converges && $rec['why'] !== null) {
                    // Les deux lignes ne peuvent pas partager la voie : portion gardée telle quelle, signalée.
                    $stats['parallel_kept_m'] = ($stats['parallel_kept_m'] ?? 0) + $rec['len'];
                    $records[] = ['action' => 'kept_parallel'] + $rec;
                } elseif ($converges) {
                    [$a, $b] = [$head ?? $a, $tail ?? $b];
                    $rec['len'] = ($b - $a + 1) * $line['clen'];
                    // Bout de tracé repris : seulement là où la voie est dans le corridor (sinon il reste, court).
                    for ($x = $a; $x <= $b; $x++) {
                        if (isset($chunks[$x]['cands'][$t])) {
                            $states[$x] = $t;
                            $locked[$x] = true;
                        }
                    }
                    $stats['junction_m'] = ($stats['junction_m'] ?? 0) + $rec['len'];
                    $records[] = ['action' => 'merged_trunk'] + $rec;
                } elseif ($rec['len'] >= self::PARALLEL_REPORT_M) {
                    $stats['parallel_kept_m'] = ($stats['parallel_kept_m'] ?? 0) + $rec['len'];
                    $records[] = ['action' => 'kept_parallel'] + $rec;
                }
            }
            $i = $j + 1;
        }
        array_push($this->junctions, ...$records);

        return [$states, $locked];
    }

    /** Choix de la voie de l'autre ligne : la plus proche, BD TOPO d'abord (+10 m par point de priorité : OSM +3 m). */
    private function trunkScore(array $cand, array $edge): float
    {
        return $cand['d'] + 10 * $edge['prio'];
    }

    /**
     * La ligne rejoint la voie $t juste après la portion (de $j vers $dir, ≤ JOIN_OBS observations) :
     * sur cette voie, sur une voie qui la rejoint à une jonction réelle, ou tracé à moins de
     * other_line_median de celle-ci ; et elle ne passe pas de l'autre côté de la voie dans les
     * CROSS_OBS observations suivantes (croisement en biais, pas une jonction).
     */
    private function joinsTrunk(int $j, int $dir, int $t, int $side, array $states, array $chunks, array $line, array $conn, array $p): bool
    {
        $n = $line['n'];
        for ($step = 0; $step < self::JOIN_OBS && $j >= 0 && $j < $n; $step++, $j += $dir) {
            $s = $states[$j];
            $join = $s === $t;
            if (! $join && $s !== 0 && isset($chunks[$j]['cands'][$s])) {
                $c = $chunks[$j]['cands'][$s];
                foreach ($conn[$s][$t] ?? [] as $x) {
                    $join = $join || hypot($x['x'] - $c['px'], $x['y'] - $c['py']) <= 2 * $line['clen'] + 10;
                }
            }
            $join = $join || (isset($chunks[$j]['cands'][$t]) && $chunks[$j]['cands'][$t]['d'] <= ($p['other_line_median'] ?? 0));
            if (! $join) {
                continue;
            }
            for ($x = $j, $m = 0; $m < self::CROSS_OBS && $x >= 0 && $x < $n; $m++, $x += $dir) {
                $c = $chunks[$x]['cands'][$t] ?? null;
                if ($c && $side !== 0 && $c['d'] > ($p['other_line_median'] ?? 0) && $this->side($chunks[$x], $t) === -$side) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /** Côté (-1, 0, 1) du tracé d'origine par rapport à la voie $t au droit de l'observation (0 : sur la voie). */
    private function side(array $chunk, int $t): int
    {
        $c = $chunk['cands'][$t] ?? null;
        if (! $c || $c['d'] < 3) {
            return 0;
        }
        $cross = ($c['px'] - $c['qx']) * ($chunk['sy'] - $c['qy']) - ($c['py'] - $c['qy']) * ($chunk['sx'] - $c['qx']);

        return $cross <=> 0;
    }

    /**
     * Fin du tracé grossier après la portion $i (vers $dir) : rien (la portion touche le bout), ou
     * au plus TERMINAL_TAIL_OBS observations qui finissent à moins de TERMINAL_STATION_M d'une gare située à moins de STATION_TRUNK_M de
     * cette voie (la ligne s'arrête à la gare de l'autre ligne, bâtiment voyageurs un peu à
     * l'écart). Dans les deux cas, pas de géométrie précise de la ligne au bout (endsOnTrunk).
     *
     * @return int|null première / dernière observation à reprendre, ou null
     */
    private function terminalTail(int $lineId, int $k, int $i, int $dir, int $t, array $chunks, array $line): ?int
    {
        $end = $dir > 0 ? $line['n'] - 1 : 0;
        $count = abs($end - $i);
        if ($count > self::TERMINAL_TAIL_OBS) {
            return null;
        }
        $which = $dir > 0 ? 'end' : 'start';
        if ($count > 0 && ! $this->endsAtStation($k, $which, $t)) {
            return null;
        }

        return $this->endsOnTrunk($lineId, $k, $which) ? $end : null;
    }

    /** Extrémité du tracé grossier à moins de TERMINAL_STATION_M d'une gare proche (STATION_TRUNK_M) de la voie $t. */
    private function endsAtStation(int $k, string $end, int $t): bool
    {
        $fn = $end === 'start' ? 'ST_StartPoint' : 'ST_EndPoint';

        return (bool) DB::selectOne(<<<SQL
            SELECT EXISTS (
                SELECT 1 FROM cf_lines l, cf_edges e, stations s
                WHERE l.k = :k AND e.eid = :t
                  AND s.geom && ST_Transform(ST_Expand({$fn}(l.g), :r), 4326)
                  AND ST_DWithin(ST_Transform(s.geom, 2154), {$fn}(l.g), :r2)
                  AND ST_DWithin(ST_Transform(s.geom, 2154), e.g, :m)
            ) AS ok
            SQL, ['k' => $k, 't' => $t, 'r' => self::TERMINAL_STATION_M, 'r2' => self::TERMINAL_STATION_M, 'm' => self::STATION_TRUNK_M])->ok;
    }

    /**
     * Le tracé grossier s'arrête là pour de bon : aucune voie principale précise rattachée à la ligne
     * (hors JUNCTION_WEAK_CLASSES) à moins de TERMINAL_OWN_M de son extrémité (sinon le recalage n'en couvre qu'un morceau, et
     * la suite de la ligne est sur sa propre voie).
     */
    private function endsOnTrunk(int $lineId, int $k, string $end): bool
    {
        $fn = $end === 'start' ? 'ST_StartPoint' : 'ST_EndPoint';

        return ! DB::selectOne(<<<SQL
            SELECT EXISTS (SELECT 1 FROM cf_lines l JOIN cf_pool p ON p.cand AND p.line_id = :line AND ST_DWithin(p.g, {$fn}(l.g), :r)
                                  AND p.cls <> ALL(CAST(:weak AS text[]))
                           WHERE l.k = :k) AS own
            SQL, ['line' => $lineId, 'k' => $k, 'r' => self::TERMINAL_OWN_M, 'weak' => '{'.implode(',', self::JUNCTION_WEAK_CLASSES).'}'])->own;
    }

    /**
     * Propriétaire des voies candidates au droit de chaque observation : rattachée à une AUTRE
     * ligne (other, ids des lignes ; why : raison d'un partage physiquement impossible), ou voie
     * propre (own : non rattachée, ou rattachée à celle-ci).
     *
     * @param  list<array{0: int, 1: int}>  $pairs  [observation, arête]
     * @return array<int, array<int, array{other: bool, own: bool, why: ?string, lines: list<int>}>>
     */
    private function trackOwners(int $lineId, int $k, array $pairs): array
    {
        $rows = DB::select(<<<'SQL'
            WITH r AS (SELECT * FROM unnest(CAST(:i AS int[]), CAST(:eid AS int[])) AS t(i, eid))
            SELECT r.i, r.eid,
                   bool_or(p.line_id IS NOT NULL AND p.line_id <> :line) AS other,
                   -- Partage physique impossible (cf. JUNCTION_* : écartements connus et différents ; à défaut,
                   -- ligne secondaire ou militaire le long d'une ligne principale : voie métrique, 60 cm, tramway).
                   CASE WHEN bool_or(p.line_id <> :line5 AND ga.g IS NOT NULL AND lb.gauge_mm_default IS NOT NULL AND lb.gauge_mm_default <> ga.g)
                             THEN 'écartement différent'
                        WHEN bool_or(p.line_id <> :line6 AND (ga.g IS NULL OR lb.gauge_mm_default IS NULL)
                                     AND ga.n = ANY(CAST(:narrow AS text[])) AND lb.network = 'principal')
                             THEN 'réseau différent' END AS why,
                   bool_or(p.line_id IS NULL OR p.line_id = :line2) AS own,
                   array_to_string(array_agg(DISTINCT p.line_id) FILTER (WHERE p.line_id IS NOT NULL AND p.line_id <> :line3), ',') AS lines
            FROM r
            JOIN cf_chunks c ON c.k = :k AND c.i = r.i
            JOIN cf_edges e ON e.eid = r.eid
            CROSS JOIN LATERAL (SELECT ST_ClosestPoint(e.g, c.m) AS q) q
            JOIN cf_pool p ON p.cand AND p.family = e.family AND p.cls = e.cls AND ST_DWithin(p.g, q.q, 1.5)
            JOIN cf_sel s ON s.pid = p.pid
            LEFT JOIN lines lb ON lb.id = p.line_id
            CROSS JOIN (SELECT gauge_mm_default AS g, network AS n FROM lines WHERE id = :line4) ga
            GROUP BY r.i, r.eid
            SQL, ['i' => $this->intArray(array_column($pairs, 0)), 'eid' => $this->intArray(array_column($pairs, 1)),
            'k' => $k, 'line' => $lineId, 'line2' => $lineId, 'line3' => $lineId, 'line4' => $lineId, 'line5' => $lineId, 'line6' => $lineId,
            'narrow' => '{'.implode(',', self::JUNCTION_NARROW_NETWORKS).'}']);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->i][(int) $r->eid] = ['other' => (bool) $r->other, 'own' => (bool) $r->own, 'why' => $r->why,
                'lines' => $r->lines === null || $r->lines === '' ? [] : array_map('intval', explode(',', $r->lines))];
        }

        return $out;
    }

    /**
     * Intervalles [a, b] de $i à $j où $ok est vrai.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function spans(int $i, int $j, callable $ok): array
    {
        $out = [];
        $a = null;
        for ($x = $i; $x <= $j + 1; $x++) {
            if ($x <= $j && $ok($x)) {
                $a ??= $x;
            } elseif ($a !== null) {
                $out[] = [$a, $x - 1];
                $a = null;
            }
        }

        return $out;
    }

    /**
     * Géométrie (tracé d'origine, Lambert-93) des portions de tronc commun enregistrées par
     * junctionTrunk, et tracés précis de l'autre ligne repris.
     *
     * @return list<array<string, mixed>>
     */
    private function junctionGeometries(array $lines): array
    {
        if (! $this->junctions) {
            return [];
        }
        $rows = DB::select(<<<'SQL'
            WITH r AS (
                SELECT * FROM unnest(CAST(:n AS int[]), CAST(:k AS int[]), CAST(:a AS float8[]), CAST(:b AS float8[]), CAST(:eid AS int[]))
                    AS t(n, k, a, b, eid)
            ),
            g AS (SELECT r.n, r.eid, ST_LineSubstring(l.g, LEAST(1, r.a), LEAST(1, r.b)) AS g FROM r JOIN cf_lines l ON l.k = r.k)
            SELECT g.n, encode(ST_AsEWKB(g.g), 'hex') AS ewkb,
                   (SELECT jsonb_agg(DISTINCT p.id ORDER BY p.id) FROM cf_edges e, cf_pool p JOIN cf_sel s ON s.pid = p.pid
                     WHERE e.eid = g.eid AND p.cand AND p.family = e.family AND p.cls = e.cls
                       AND ST_DWithin(p.g, ST_CollectionExtract(ST_Intersection(e.g, ST_Buffer(g.g, :w)), 2), 1)) AS ids
            FROM g
            SQL, [
            'n' => $this->intArray(array_keys($this->junctions)),
            'k' => $this->intArray(array_column($this->junctions, 'k')),
            'a' => $this->floatArray(array_map(fn ($r) => $r['i0'] / $lines[$r['k']]['n'], $this->junctions)),
            'b' => $this->floatArray(array_map(fn ($r) => ($r['i1'] + 1) / $lines[$r['k']]['n'], $this->junctions)),
            'eid' => $this->intArray(array_column($this->junctions, 'eid')),
            'w' => self::JUNCTION_MAX_BUFFER_M,
        ]);
        $out = [];
        foreach ($rows as $r) {
            $rec = $this->junctions[(int) $r->n];
            unset($rec['k'], $rec['i0'], $rec['i1'], $rec['eid']);
            $out[] = $rec + ['ewkb' => $r->ewkb, 'trace_ids' => $r->ids ? json_decode($r->ids, true) : []];
        }

        return $out;
    }

    /**
     * Une portion « tracé d'origine gardé » entre deux portions précises, plus
     * courte que 2,5 × la somme des écarts aux deux bouts, ne laisserait que
     * deux raccords en zigzag : on reste sur la voie précise voisine si elle
     * couvre toute la portion à moins de jump_max.
     */
    private function absorbShortDivergences(array $runs, array $states, array $chunks, array $line): array
    {
        $count = count($runs);
        for ($r = 1; $r < $count - 1; $r++) {
            $run = $runs[$r];
            $prev = $runs[$r - 1];
            $next = $runs[$r + 1];
            if ($run['s'] !== 0 || $prev['s'] === 0 || $next['s'] === 0) {
                continue;
            }
            $cp = $chunks[$prev['i1']]['cands'][$prev['s']];
            $cn = $chunks[$next['i0']]['cands'][$next['s']];
            $j0 = hypot($cp['px'] - $chunks[$run['i0']]['sx'], $cp['py'] - $chunks[$run['i0']]['sy']);
            $j1 = hypot($cn['qx'] - $chunks[$next['i0']]['sx'], $cn['qy'] - $chunks[$next['i0']]['sy']);
            $span = ($run['i1'] - $run['i0'] + 1) * $line['clen'];
            if ($span >= 2.5 * ($j0 + $j1)) {
                continue;
            }
            $fill = [];
            for ($i = $run['i0']; $i <= $run['i1']; $i++) {
                $near = fn ($s) => isset($chunks[$i]['cands'][$s]) && $chunks[$i]['cands'][$s]['d'] <= $line['jump_max'];
                $fill[$i] = $near($prev['s']) ? $prev['s'] : ($near($next['s']) ? $next['s'] : null);
                if ($fill[$i] === null) {
                    continue 2;
                }
            }
            foreach ($fill as $i => $s) {
                $states[$i] = $s;
            }
        }

        return $states;
    }

    /**
     * Recousage au point de divergence : de part et d'autre d'une portion
     * « tracé d'origine gardé », la voie précise est abandonnée dès qu'elle
     * commence à s'écarter (écart > 8 m et croissant vers la divergence, sur
     * 150 m au plus, et dans tous les cas tant qu'elle est à plus de
     * jump_max), pour que le raccord soit court au lieu d'un long biais.
     */
    private function backToDivergencePoint(array $runs, array $states, array $chunks, float $jumpMax, array $locked = []): array
    {
        foreach ($runs as $r => $run) {
            if ($run['s'] !== 0) {
                continue;
            }
            foreach ([-1, 1] as $dir) {
                $nb = $runs[$r + $dir] ?? null;
                // Tronc commun d'une jonction (junctionTrunk) : le tracé d'origine en est écarté par
                // construction, la voie de l'autre ligne est gardée jusqu'au point de divergence.
                if (! $nb || $nb['s'] === 0 || isset($locked[$dir < 0 ? $nb['i1'] : $nb['i0']])) {
                    continue;
                }
                $i = $dir < 0 ? $nb['i1'] : $nb['i0'];
                $stop = $dir < 0 ? $nb['i0'] : $nb['i1'];
                $prevD = INF;
                for ($step = 0; $i !== $stop + $dir; $step++, $i += $dir) {
                    $d = $chunks[$i]['cands'][$nb['s']]['d'];
                    // Toujours reculer tant que l'écart dépasse jump_max (sinon raccord en long biais) ;
                    // au-delà, seulement sur 150 m tant que l'écart décroît en s'éloignant de la divergence.
                    if ($d <= $jumpMax && ($step >= 6 || $d <= 8 || $d > $prevD)) {
                        break;
                    }
                    $states[$i] = 0;
                    $prevD = $d;
                }
            }
        }

        return $states;
    }

    /**
     * Entre deux portions précises qui ne se touchent pas (voies différentes, ou
     * même voie reprise après une boucle), un raccord de plus de jump_max
     * serait un raccourci inventé : on garde là le tracé d'origine (deux
     * observations de part et d'autre).
     */
    private function breakLongJumps(array $runs, array $states, array $chunks, array $line, array $conn): array
    {
        for ($r = 1; $r < count($runs); $r++) {
            $a = $runs[$r - 1];
            $b = $runs[$r];
            if ($a['s'] === 0 || $b['s'] === 0) {
                continue;
            }
            $ca = $chunks[$a['i1']]['cands'][$a['s']];
            $cb = $chunks[$b['i0']]['cands'][$b['s']];
            if ($a['s'] !== $b['s']) {
                foreach ($conn[$a['s']][$b['s']] ?? [] as $t) {
                    if (hypot($t['x'] - $ca['px'], $t['y'] - $ca['py']) <= 2 * $line['clen'] + 10) {
                        continue 2;
                    }
                }
            }
            if (hypot($ca['px'] - $cb['qx'], $ca['py'] - $cb['qy']) <= $line['jump_max']) {
                continue;
            }
            for ($i = max($a['i0'], $a['i1'] - 1); $i <= min($b['i1'], $b['i0'] + 1); $i++) {
                $states[$i] = 0;
            }
        }

        return $states;
    }

    /**
     * Spécifications des morceaux : ['edge', eid, f0, f1] | ['coarse', k, a0, a1] | ['conn', x0, y0, x1, y1].
     */
    private function assemble(int $k, array $runs, array $chunks, array $line, array $edges, array $conn): array
    {
        $n = $line['n'];
        $clen = $line['clen'];
        $L = $line['len'];
        $count = count($runs);

        // Trous courts entre deux portions précises : raccord direct.
        foreach ($runs as $r => $run) {
            $len = ($run['i1'] - $run['i0'] + 1) * $clen;
            if ($run['s'] === 0 && $r > 0 && $r < $count - 1 && $len < self::SHORT_GAP_M) {
                $runs[$r]['gap'] = true;
            }
        }

        // Bornes de chaque portion précise (fractions sur l'arête), recousues aux jonctions.
        foreach ($runs as $r => $run) {
            if ($run['s'] === 0) {
                continue;
            }
            $runs[$r]['f0'] = $chunks[$run['i0']]['cands'][$run['s']]['fs'];
            $runs[$r]['f1'] = $chunks[$run['i1']]['cands'][$run['s']]['fe'];
        }
        for ($r = 1; $r < $count; $r++) {
            $a = $runs[$r - 1];
            $b = $runs[$r];
            if ($a['s'] === 0 || $b['s'] === 0 || $a['s'] === $b['s']) {
                continue;
            }
            $ca = $chunks[$a['i1']]['cands'][$a['s']];
            $best = null;
            foreach ($conn[$a['s']][$b['s']] ?? [] as $t) {
                $dist = hypot($t['x'] - $ca['px'], $t['y'] - $ca['py']);
                if ($dist <= 2 * $clen + 10 && ($best === null || $dist < $best[0])) {
                    $best = [$dist, $t];
                }
            }
            if ($best) {
                $runs[$r - 1]['f1'] = $best[1]['f_self'];
                $runs[$r]['f0'] = $best[1]['f_other'];
                $runs[$r]['joined'] = true;
            }
        }

        $specs = [];
        $pt = fn (array $c, string $end) => $end === 'start' ? [$c['qx'], $c['qy']] : [$c['px'], $c['py']];
        foreach ($runs as $r => $run) {
            if ($run['s'] !== 0) {
                $specs[] = ['edge', $run['s'], $run['f0'], $run['f1']];
                // Raccord avec la portion précise précédente (voies qui ne se touchent pas).
                if ($r > 0 && $runs[$r - 1]['s'] !== 0 && empty($run['joined'])) {
                    $prev = $runs[$r - 1];
                    $specs[] = ['conn_f', $prev['s'], $prev['f1'], $run['s'], $run['f0']];
                }

                continue;
            }
            $prevEdge = $r > 0 ? $runs[$r - 1] : null;
            $nextEdge = $r < $count - 1 ? $runs[$r + 1] : null;
            if (! empty($run['gap'])) {
                $specs[] = ['conn_f', $prevEdge['s'], $prevEdge['f1'], $nextEdge['s'], $nextEdge['f0']];

                continue;
            }
            // Tracé d'origine conservé ; raccords en biais (≤ 2 × l'écart) vers les voies précises voisines.
            $a0 = $run['i0'] * $clen;
            $a1 = ($run['i1'] + 1) * $clen;
            $span = $a1 - $a0;
            $t0 = $t1 = 0.0;
            if ($prevEdge) {
                $c = $chunks[$prevEdge['i1']]['cands'][$prevEdge['s']];
                $jump = hypot($c['px'] - $chunks[$run['i0']]['sx'], $c['py'] - $chunks[$run['i0']]['sy']);
                $t0 = min(2 * $jump, 0.4 * $span);
            }
            if ($nextEdge) {
                $c = $chunks[$nextEdge['i0']]['cands'][$nextEdge['s']];
                $jump = hypot($c['qx'] - ($chunks[$run['i1'] + 1]['sx'] ?? $c['qx']), $c['qy'] - ($chunks[$run['i1'] + 1]['sy'] ?? $c['qy']));
                $t1 = min(2 * $jump, 0.4 * $span);
            }
            $specs[] = ['coarse', $k, ($a0 + $t0) / $L, min(1.0, ($a1 - $t1) / $L)];
            if ($prevEdge) {
                $specs[] = ['conn_fc', $prevEdge['s'], $prevEdge['f1'], $k, ($a0 + $t0) / $L];
            }
            if ($nextEdge) {
                $specs[] = ['conn_fc', $nextEdge['s'], $nextEdge['f0'], $k, min(1.0, ($a1 - $t1) / $L)];
            }
        }

        return $specs;
    }

    /**
     * Construit les géométries et les regroupe par provenance.
     *
     * @return array<int, array{family: ?string, ewkb: string, len: float, trace_ids: array<int, int>}>
     */
    private function build(array $specs): array
    {
        if (! $specs) {
            return [];
        }
        $cols = ['kind' => [], 'r1' => [], 'a' => [], 'r2' => [], 'b' => []];
        foreach ($specs as $sp) {
            [$kind, $r1, $a] = $sp;
            $cols['kind'][] = $kind;
            $cols['r1'][] = $r1;
            $cols['a'][] = $a;
            $cols['r2'][] = $kind === 'edge' || $kind === 'coarse' ? 0 : $sp[3];
            $cols['b'][] = $kind === 'edge' || $kind === 'coarse' ? $sp[3] : $sp[4];
        }

        $rows = DB::select(<<<'SQL'
            WITH s AS (
                SELECT * FROM unnest(CAST(:kind AS text[]), CAST(:r1 AS int[]), CAST(:a AS float8[]), CAST(:r2 AS int[]), CAST(:b AS float8[]))
                    AS t(kind, r1, a, r2, b)
            ),
            g AS (
                SELECT CASE s.kind WHEN 'edge' THEN e1.family WHEN 'coarse' THEN NULL ELSE 'connector' END AS family,
                       CASE s.kind
                           WHEN 'edge' THEN CASE WHEN s.a <= s.b THEN ST_LineSubstring(e1.g, s.a, s.b)
                                                 ELSE ST_Reverse(ST_LineSubstring(e1.g, s.b, s.a)) END
                           WHEN 'coarse' THEN ST_LineSubstring(l1.g, LEAST(s.a, s.b), GREATEST(s.a, s.b))
                           WHEN 'conn_f' THEN ST_MakeLine(ST_LineInterpolatePoint(e1.g, s.a), ST_LineInterpolatePoint(e2.g, s.b))
                           WHEN 'conn_fc' THEN ST_MakeLine(ST_LineInterpolatePoint(e1.g, s.a), ST_LineInterpolatePoint(l2.g, s.b))
                       END AS geom
                FROM s
                LEFT JOIN cf_edges e1 ON s.kind <> 'coarse' AND e1.eid = s.r1
                LEFT JOIN cf_lines l1 ON s.kind = 'coarse' AND l1.k = s.r1
                LEFT JOIN cf_edges e2 ON s.kind = 'conn_f' AND e2.eid = s.r2
                LEFT JOIN cf_lines l2 ON s.kind = 'conn_fc' AND l2.k = s.r2
            ),
            grp AS (
                SELECT family, ST_LineMerge(ST_Collect(geom)) AS geom
                FROM g WHERE geom IS NOT NULL AND ST_Length(geom) > 0.05
                GROUP BY family
            ),
            grpb AS (SELECT * FROM grp)
            SELECT family, encode(ST_AsEWKB(geom), 'hex') AS ewkb, ST_Length(geom) AS len,
                   CASE WHEN family IN ('bdtopo', 'osm', 'contrib', 'ign-historique') THEN (
                       SELECT jsonb_agg(DISTINCT p.id ORDER BY p.id) FROM cf_pool p JOIN cf_sel USING (pid)
                       WHERE p.family = grpb.family AND ST_DWithin(p.g, grpb.geom, 1)
                         -- Trace réellement empruntée : au moins 2 de ses 3 quartiles sur le dessin.
                         AND (ST_DWithin(ST_LineInterpolatePoint(p.g, 0.25), grpb.geom, 1.5)::int
                              + ST_DWithin(ST_LineInterpolatePoint(p.g, 0.5), grpb.geom, 1.5)::int
                              + ST_DWithin(ST_LineInterpolatePoint(p.g, 0.75), grpb.geom, 1.5)::int) >= 2
                   ) END::text AS ids
            FROM grpb
            SQL, [
            'kind' => '{'.implode(',', $cols['kind']).'}',
            'r1' => $this->intArray($cols['r1']), 'a' => $this->floatArray($cols['a']),
            'r2' => $this->intArray($cols['r2']), 'b' => $this->floatArray($cols['b']),
        ]);

        return array_map(fn ($r) => [
            'family' => $r->family,
            'ewkb' => $r->ewkb,
            'len' => (float) $r->len,
            'trace_ids' => $r->ids ? json_decode($r->ids, true) : [],
        ], $rows);
    }

    private function intArray(array $v): string
    {
        return '{'.implode(',', array_map('intval', $v)).'}';
    }

    private function floatArray(array $v): string
    {
        return '{'.implode(',', array_map(fn ($x) => sprintf('%.12F', $x), $v)).'}';
    }
}
