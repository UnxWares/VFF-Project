<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Continuité et jonctions du tracé de référence d'une ligne, SUR LES VOIES
 * RÉELLES (étape de vff:build-line-references, après « un seul axe » et avant
 * le croisement des sources). Aucune géométrie n'est inventée (sauf la courbe
 * lissée de la règle 7 b, marquée « raccord-lisse ») : tout ajout suit
 * des voies précises du pool de recalage (BD TOPO, OSM, contributions ;
 * rattachées à la ligne, à une autre ligne ou à aucune), aiguillage par
 * aiguillage.
 *
 *   1. Sauts entre voies parallèles (raccords « en escalier ») : un raccord
 *      droit de moins de JOG_MAX_M entre deux portions précises, qui fait un
 *      angle de plus de KINK_DEG avec l'une d'elles, n'est pas une voie (une
 *      voie ne tourne jamais à angle droit) : il est retiré provisoirement, et
 *      les extrémités qu'il reliait sont reliées autrement (2 et 3) ; sinon il
 *      est remis (jamais de trou créé).
 *   2. Trous : deux extrémités libres de la ligne, face à face, à moins de
 *      HOLE_MAX_M (ponts, passages à niveau : morceaux BD TOPO courts non
 *      rattachés, résidu OSM trop court jeté) sont reliées par le chemin le
 *      plus court sur les voies précises, s'il existe sans détour.
 *   3. Changement de voie : extrémités d'un saut (bout à bout) reliées par le
 *      chemin réel (aiguillage, communication) depuis l'une jusqu'à la portion
 *      d'en face ; le bout de celle-ci qui longe le chemin est retiré.
 *   4. Restes : une portion précise courte qui n'était tenue que par des sauts
 *      retirés et qui longe le reste de la ligne (autre voie du faisceau) est
 *      retirée.
 *   5. Embranchement qui s'arrête avant la jonction : un bout libre tourné
 *      vers une AUTRE ligne, à moins de GAP_MAX_M, est prolongé par les voies
 *      réelles jusqu'à la voie de cette ligne (aiguillage, déviation <=
 *      TURN_DEG à chaque nœud), écartements compatibles.
 *   6. Dépassement : un tracé grossier (archeo, OHM, SNCF non recalé) qui
 *      croise la voie précise d'une autre ligne et s'arrête moins de
 *      OVERSHOOT_MAX_M plus loin est coupé au croisement.
 *   7. Arrivée d'un tracé imprécis sur une portion précise (jonction, tronc
 *      commun, début de la voie propre) : la fin du tracé imprécis (grossier,
 *      raccords droits ; au plus LEG_MAX_M) qui fait un coude de plus de
 *      KINK_DEG (marche d'escalier, raccord à angle vif, tracé qui longe la
 *      voie puis la rejoint d'un coup) est refaite :
 *        a. par la voie précise réelle si la fin imprécise relie deux portions
 *           précises de la ligne (aiguillage, voie de faisceau : chemin sur
 *           les voies du pool, sans détour) ;
 *        b. sinon par une courbe lissée (Hermite cubique) tangente au tracé
 *           imprécis au point D et à la portion précise au point de jonction J,
 *           de rayon au moins égal au rayon minimal plausible du réseau
 *           (minRadius) et à moins de LEG_DEV_M du tracé remplacé ; D et J
 *           sont pris au plus près de la jonction. La courbe est ESTIMÉE :
 *           matched_source = SMOOTHED (« raccord-lisse »), jamais présentée
 *           comme précise ; au-delà de J la ligne suit la portion précise
 *           (tronc commun masqué ensuite par vff:build-shared-track).
 *      Même courbe estimée, faute de voie précise (étapes 2 et 5 sans chemin) :
 *        c. bout d'un tracé imprécis qui converge vers la voie précise d'une
 *           AUTRE ligne et s'arrête à moins de END_GAP_M avant elle (cap vers
 *           elle, écartements et réseaux compatibles) : raccordé à cette voie ;
 *        d. trou de moins de HOLE_SMOOTH_MAX_M entre deux bouts de la ligne
 *           face à face (voie déposée, aucune voie précise) : comblé.
 *
 * Graphe des voies : morceaux du pool (TraceConflation::preparePool, table
 * temporaire cf_pool) dans une fenêtre, nœuds aux intersections (ST_Node, une
 * famille de sources à la fois : BD TOPO et OSM ne partagent pas de sommets) ;
 * chemin le plus court (Dijkstra sur (morceau, sens)) avec une déviation d'au
 * plus TURN_DEG à chaque nœud : on ne rebrousse jamais et on ne prend pas une
 * voie qui croise.
 *
 * Provenance : un ajout sur des voies rattachées à la ligne garde leur source
 * et leurs ids ; sinon il prend la source et les ids de la portion qu'il
 * prolonge, avec matched_source = famille de la voie et matched_trace_ids.
 * Déterministe (ordres explicites) et idempotent.
 */
class JunctionRepair
{
    /** Raccord coudé : angle (°) au-delà duquel un raccord n'est pas la continuation d'une portion. */
    public const KINK_DEG = 35.0;

    /** Raccords examinés (m). */
    public const JOG_MAX_M = 60.0;

    /** Trou entre deux extrémités d'une même ligne (m). */
    public const HOLE_MAX_M = 300.0;

    /** Bout libre tourné vers une autre ligne (m). */
    public const GAP_MAX_M = 150.0;

    /** Extrémité « tournée vers » sa cible : écart de cap max (°). */
    public const FACE_DEG = 45.0;

    /**
     * Accroche d'une extrémité sur une voie du pool (m). Une extrémité plus loin de toute voie (tracé
     * grossier décalé) n'est pas reprise : le petit raccord vers la voie ferait un coude.
     */
    public const SNAP_M = 3.0;

    /** Déviation max à un nœud du graphe des voies (°) : aiguillage, pas un croisement ni un rebroussement. */
    public const TURN_DEG = 30.0;

    /** Cap de départ / d'arrivée : écart max avec la voie (°). */
    public const START_DEG = 35.0;

    /** Voie précise confondue avec une portion de la ligne (m). */
    public const MERGE_M = 2.0;

    /** Chemin admis : au plus DETOUR × distance + DETOUR_ADD_M. */
    public const DETOUR = 1.5;

    public const DETOUR_ADD_M = 30.0;

    /** Changement de voie : chemin max (m). */
    public const REROUTE_MAX_M = 400.0;

    /** Reste / bout qui longe la ligne : écart max (m) et part de longueur. */
    public const PARALLEL_M = 30.0;

    public const REMNANT_SHARE = 0.8;

    /** Ajout qui longe une autre portion de la ligne : part de sa longueur et longueur au-delà desquelles il est refusé. */
    public const DOUBLE_SHARE = 0.3;

    public const DOUBLE_MIN_M = 30.0;

    public const REMNANT_MAX_M = 300.0;

    /** Boucle (portion tenue aux deux bouts par des sauts retirés) : longueur max (m), part longeant la ligne, à moins de BUBBLE_M (m). */
    public const BUBBLE_MAX_M = 1000.0;

    public const BUBBLE_SHARE = 0.9;

    public const BUBBLE_M = 60.0;

    /** Prolongement vers une autre ligne : chemin max (m) et facteur de détour. */
    public const EXTEND_MAX_M = 200.0;

    public const EXTEND_DETOUR = 1.3;

    /** Dépassement d'un tracé grossier au-delà d'un croisement (m), angle min du croisement (°), reste min (m). */
    public const OVERSHOOT_MAX_M = 60.0;

    public const OVERSHOOT_CROSS_DEG = 20.0;

    public const OVERSHOOT_REST_M = 50.0;

    /** Arrivée d'un tracé imprécis : longueur max refaite (m), coude déclencheur (°), écart max de la courbe au tracé remplacé (m). */
    public const LEG_MAX_M = 400.0;

    public const LEG_KINK_DEG = 35.0;

    public const LEG_DEV_M = 40.0;

    /** Pont imprécis entre deux portions précises refait par la voie réelle : longueur max (m). */
    public const BRIDGE_MAX_M = 300.0;

    /** Bout imprécis qui s'arrête avant la voie d'une autre ligne (m) ; trou comblé par une courbe (m). */
    public const END_GAP_M = 40.0;

    public const HOLE_SMOOTH_MAX_M = 100.0;

    /** Géométrie lissée estimée (matched_source). */
    public const SMOOTHED = 'raccord-lisse';

    /** Points D (en arrière de la jonction) et J (sur la portion précise) essayés (m). */
    private const LEG_D_STEPS = [20, 30, 45, 60, 80, 100, 130, 160, 200, 250, 300, 400];

    private const LEG_J_STEPS = [0, 10, 25, 50, 80, 120, 160, 200, 250, 300];

    private const MAX_EXPANSIONS = 20000;

    /** Diagnostic : décisions (rempli si $debug). */
    public bool $debug = false;

    public array $log = [];

    private int $lineId = 0;

    /** Extrémités libérées par le retrait des sauts (« sid:k ») : les portions qu'elles laissent détachées sont des restes possibles. */
    private array $freed = [];

    private ?int $gauge = null;

    /**
     * Avant « un seul axe » : trous de la ligne comblés par la voie précise qui les
     * traverse TOUT DROIT (sans changer de voie à un aiguillage). Une voie BD TOPO
     * coupée à chaque aiguillage par des morceaux courts non rattachés (ponts,
     * passages à niveau, têtes de gare) redevient continue : l'étape « un seul
     * axe » garde alors cette voie d'un bout à l'autre au lieu de sauter d'une
     * voie à l'autre (raccords en escalier).
     *
     * @return array<string, float|int> hole_filled, hole_filled_m
     */
    public function fillStraightHoles(int $lineId): array
    {
        if (! $this->start($lineId)) {
            return [];
        }
        $this->freed = [];
        $stats = ['hole_filled' => 0, 'hole_filled_m' => 0.0];
        $this->fillHoles($stats, true);
        if (array_filter($stats)) {
            $this->write();
        }

        return $stats;
    }

    /**
     * Après « un seul axe » : répare le tracé de référence de la ligne (line_reference_parts réécrits).
     *
     * @return array<string, float|int> jog_removed, jog_restored, hole_filled (+ _m), rerouted (+ _m), stub_trimmed_m,
     *                                  remnant_removed_m, extended (+ _m), overshoot_trimmed_m, leg_rerouted (+ _m), leg_smoothed (+ _m),
     *                                  end_joined (+ _m), hole_smoothed (+ _m)
     */
    public function repair(int $lineId): array
    {
        if (! $this->start($lineId)) {
            return [];
        }

        $stats = ['jog_removed' => 0, 'jog_restored' => 0, 'hole_filled' => 0, 'hole_filled_m' => 0.0, 'rerouted' => 0, 'rerouted_m' => 0.0,
            'stub_trimmed_m' => 0.0, 'remnant_removed_m' => 0.0, 'extended' => 0, 'extended_m' => 0.0, 'overshoot_trimmed_m' => 0.0,
            'leg_rerouted' => 0, 'leg_rerouted_m' => 0.0, 'leg_smoothed' => 0, 'leg_smoothed_m' => 0.0,
            'end_joined' => 0, 'end_joined_m' => 0.0, 'hole_smoothed' => 0, 'hole_smoothed_m' => 0.0];

        $jogs = $this->removeJogs();
        $stats['jog_removed'] = count($jogs);
        $this->freed = [];
        foreach ($jogs as $j) {
            foreach ($j['ends'] as $e) {
                if ($e['end']) {
                    $this->freed[$e['sid'].':'.$e['k']] = true;
                }
            }
        }

        $this->fillHoles($stats, false);
        $this->reroute($jogs, $stats);
        $this->removeRemnants($stats);
        $stats['jog_restored'] = $this->restoreJogs($jogs);
        $stats['jog_removed'] -= $stats['jog_restored'];
        $this->extendToOtherLines($stats);
        $this->trimOvershoots($stats);
        $this->smoothLegs($stats);
        $this->smoothEnds($stats);

        if (array_filter($stats)) {
            $this->write();
        }

        return $stats;
    }

    // ─── Chargement / écriture ──────────────────────────────────────────

    /** Pool de recalage présent : ligne chargée (jr_sub). */
    private function start(int $lineId): bool
    {
        if (DB::selectOne("SELECT to_regclass('pg_temp.cf_pool') IS NOT NULL AS ok")->ok !== true) {
            return false;
        }
        $this->lineId = $lineId;
        $this->log = [];
        $g = DB::selectOne('SELECT gauge_mm_default AS g FROM lines WHERE id = ?', [$lineId])?->g;
        $this->gauge = $g !== null ? (int) $g : null;
        $this->load();

        return true;
    }

    private function load(): void
    {
        DB::statement('DROP TABLE IF EXISTS jr_sub');
        DB::statement('CREATE TEMP TABLE jr_sub (sid serial PRIMARY KEY, part_id bigint, sub int, conn boolean, rank int,
            removed boolean NOT NULL DEFAULT FALSE, changed boolean NOT NULL DEFAULT FALSE, added text, g geometry(LineString, 2154))');
        DB::statement('CREATE INDEX ON jr_sub USING gist (g)');
        DB::insert(<<<'SQL'
            INSERT INTO jr_sub (part_id, sub, conn, rank, g)
            SELECT p.id, d.path[1], p.matched_source IS NOT DISTINCT FROM 'connector',
                   CASE WHEN p.matched_source = 'connector' THEN 5
                        WHEN p.matched_source IS NULL AND p.source = 'contrib' THEN 0
                        WHEN p.matched_source IS NULL AND p.source = 'bdtopo' THEN 1
                        WHEN p.matched_source IS NULL AND p.source = 'osm' THEN 2
                        WHEN p.matched_source IN ('contrib', 'bdtopo', 'osm', 'ign-historique') THEN 3
                        ELSE 4 END,
                   d.geom
            FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
            WHERE p.line_id = ? AND ST_Length(d.geom) > 0.01
            ORDER BY p.id, d.path[1]
            SQL, [$this->lineId]);
        DB::statement('ANALYZE jr_sub');
    }

    /**
     * Morceaux réécrits (portions retirées ou raccourcies), morceaux vidés supprimés,
     * ajouts insérés (un morceau par ajout).
     */
    private function write(): void
    {
        DB::update(<<<'SQL'
            UPDATE line_reference_parts p SET geom = x.g, length_m = x.len, updated_at = NOW()
            FROM (
                SELECT part_id, ST_Multi(ST_Transform(ST_Collect(g ORDER BY sub, sid), 4326)) AS g, ROUND(SUM(ST_Length(g)))::int AS len
                FROM jr_sub WHERE NOT removed AND part_id IS NOT NULL AND part_id IN (SELECT part_id FROM jr_sub WHERE (removed OR changed) AND part_id IS NOT NULL)
                GROUP BY part_id
            ) x
            WHERE p.id = x.part_id
            SQL);
        DB::delete(<<<'SQL'
            DELETE FROM line_reference_parts p WHERE p.line_id = ?
              AND p.id IN (SELECT part_id FROM jr_sub WHERE part_id IS NOT NULL)
              AND NOT EXISTS (SELECT 1 FROM jr_sub t WHERE t.part_id = p.id AND NOT t.removed)
            SQL, [$this->lineId]);
        foreach (DB::select('SELECT sid, added, encode(ST_AsEWKB(g), \'hex\') AS ewkb FROM jr_sub WHERE added IS NOT NULL AND NOT removed ORDER BY sid') as $a) {
            $m = json_decode($a->added, true);
            DB::insert(<<<'SQL'
                INSERT INTO line_reference_parts (line_id, source, source_trace_ids, matched_source, matched_trace_ids, length_m, geom, created_at, updated_at)
                SELECT ?, ?, CAST(? AS jsonb), ?, CAST(? AS jsonb), ROUND(ST_Length(g))::int, ST_Multi(ST_Transform(g, 4326)), NOW(), NOW()
                FROM (SELECT ST_GeomFromEWKB(decode(?, 'hex')) AS g) t
                WHERE ST_Length(g) > 0
                SQL, [$this->lineId, $m['source'], json_encode($m['source_ids']), $m['matched'], $m['matched'] !== null ? json_encode($m['matched_ids']) : null, $a->ewkb]);
        }
    }

    // ─── 1. Sauts (raccords coudés entre portions précises) ────────────

    /**
     * Retire (provisoirement) les raccords coudés entre deux portions précises.
     *
     * @return list<array{sid: int, ends: list<array{sid: int, k: int, end: bool}>}> raccords retirés ; ends = portion attachée à chaque bout
     *                                                                               (k = 0 début / 1 fin de la portion ; end = attaché à une extrémité)
     */
    private function removeJogs(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT c.sid, e.k AS ck, a.sid AS asid, a.rank AS arank, a.ang, a.at_end, a.ak
            FROM jr_sub c
            CROSS JOIN LATERAL (VALUES (0, ST_StartPoint(c.g)), (1, ST_EndPoint(c.g))) e(k, p)
            LEFT JOIN LATERAL (
                SELECT o.sid, o.rank,
                       degrees(asin(LEAST(1, abs(sin(ST_Azimuth(ST_StartPoint(c.g), ST_EndPoint(c.g))
                           - ST_Azimuth(ST_LineInterpolatePoint(o.g, GREATEST(0, f.f - 8 / ST_Length(o.g))), ST_LineInterpolatePoint(o.g, LEAST(1, f.f + 8 / ST_Length(o.g))))))))) AS ang,
                       ST_DWithin(ST_StartPoint(o.g), e.p, 0.5) OR ST_DWithin(ST_EndPoint(o.g), e.p, 0.5) AS at_end,
                       CASE WHEN ST_DWithin(ST_StartPoint(o.g), e.p, 0.5) THEN 0 ELSE 1 END AS ak
                FROM jr_sub o, LATERAL (SELECT ST_LineLocatePoint(o.g, e.p) AS f) f
                WHERE NOT o.conn AND NOT o.removed AND ST_DWithin(o.g, e.p, 0.5) AND ST_Length(o.g) > 1
                ORDER BY ST_Distance(o.g, e.p), o.rank, o.sid LIMIT 1
            ) a ON TRUE
            WHERE c.conn AND NOT c.removed AND ST_Length(c.g) BETWEEN 1 AND :max
            ORDER BY c.sid, e.k
            SQL, ['max' => self::JOG_MAX_M]);
        $by = [];
        foreach ($rows as $r) {
            $by[(int) $r->sid][] = $r;
        }
        $jogs = [];
        foreach ($by as $sid => $ends) {
            if (count($ends) !== 2 || $ends[0]->asid === null || $ends[1]->asid === null || (int) $ends[0]->asid === (int) $ends[1]->asid) {
                continue;
            }
            if ((int) $ends[0]->arank > 3 || (int) $ends[1]->arank > 3) {
                continue;
            }
            if (max((float) $ends[0]->ang, (float) $ends[1]->ang) <= self::KINK_DEG) {
                continue;
            }
            $jogs[] = ['sid' => $sid, 'ends' => array_map(fn ($e) => ['sid' => (int) $e->asid, 'k' => (int) $e->ak, 'end' => (bool) $e->at_end], $ends)];
        }
        if ($jogs) {
            DB::update('UPDATE jr_sub SET removed = TRUE WHERE sid = ANY(CAST(? AS int[]))', [$this->ints(array_column($jogs, 'sid'))]);
        }
        $this->debug && $this->log[] = ['jogs' => array_column($jogs, 'sid')];

        return $jogs;
    }

    /**
     * Remet les raccords retirés dont un bout reste une extrémité libre d'une portion gardée.
     *
     * @return int raccords remis
     */
    private function restoreJogs(array $jogs): int
    {
        $n = 0;
        foreach ($jogs as $j) {
            $free = false;
            foreach ($j['ends'] as $e) {
                if ($e['end'] && $this->isFreeEnd($e['sid'], $e['k'])) {
                    $free = true;
                }
            }
            if ($free) {
                DB::update('UPDATE jr_sub SET removed = FALSE WHERE sid = ?', [$j['sid']]);
                $n++;
            }
        }

        return $n;
    }

    // ─── 2. Trous ──────────────────────────────────────────────────────

    /** @param bool $straight tout droit seulement (aucun changement de voie) */
    private function fillHoles(array &$stats, bool $straight): void
    {
        $ends = $this->freeEnds();
        $pairs = [];
        foreach ($ends as $i => $a) {
            foreach ($ends as $j => $b) {
                if ($j <= $i || $a['sid'] === $b['sid']) {
                    continue;
                }
                $d = hypot($b['x'] - $a['x'], $b['y'] - $a['y']);
                if ($d <= 0.5 || $d > self::HOLE_MAX_M) {
                    continue;
                }
                $ab = atan2($b['x'] - $a['x'], $b['y'] - $a['y']);
                if ($this->angDiff($ab, $a['az']) > deg2rad(self::FACE_DEG) || $this->angDiff($ab + M_PI, $b['az']) > deg2rad(self::FACE_DEG)) {
                    continue;
                }
                $pairs[] = [$d, $i, $j];
            }
        }
        usort($pairs, fn ($x, $y) => [$x[0], $x[1], $x[2]] <=> [$y[0], $y[1], $y[2]]);
        $used = [];
        foreach ($pairs as [$d, $i, $j]) {
            if (isset($used[$i]) || isset($used[$j])) {
                continue;
            }
            $a = $ends[$i];
            $b = $ends[$j];
            $path = $this->route($a, ['type' => 'point', 'x' => $b['x'], 'y' => $b['y'], 'az' => $b['az'] + M_PI, 'snap' => self::SNAP_M],
                self::DETOUR * $d + self::DETOUR_ADD_M, $straight);
            $this->debug && $this->log[] = ['hole' => [$a['sid'], $b['sid']], 'd' => round($d, 1), 'xy' => [round($a['x']), round($a['y'])], 'path' => $path ? round($path['len'], 1) : null];
            // Après « un seul axe », un ajout ne double jamais une autre portion de la ligne.
            if (! $path || (! $straight && $this->doubles($path, [$a['sid'], $b['sid']]))) {
                continue;
            }
            $this->addPath($path, $a['sid']);
            $used[$i] = $used[$j] = true;
            $stats['hole_filled']++;
            $stats['hole_filled_m'] += $path['len'];
        }
    }

    // ─── 3. Changement de voie ─────────────────────────────────────────

    private function reroute(array $jogs, array &$stats): void
    {
        foreach ($jogs as $j) {
            [$e0, $e1] = $j['ends'];
            // Bout à bout seulement (un raccord en T laisse la portion d'en face entière : pas de bout à retirer).
            if (! $e0['end'] || ! $e1['end'] || ! $this->isFreeEnd($e0['sid'], $e0['k']) || ! $this->isFreeEnd($e1['sid'], $e1['k'])) {
                continue;
            }
            $best = null;
            foreach ([[$e0, $e1], [$e1, $e0]] as [$from, $to]) {
                $start = $this->endInfo($from['sid'], $from['k']);
                $path = $this->route($start, ['type' => 'subs', 'sids' => [$to['sid']]], self::REROUTE_MAX_M);
                if (! $path || $path['tsid'] !== $to['sid']) {
                    $this->debug && $this->log[] = ['reroute' => [$from['sid'], $to['sid']], 'xy' => [round($start['x']), round($start['y'])], 'path' => null];

                    continue;
                }
                $trim = $this->doubles($path, [$from['sid'], $to['sid']]) ? null : $this->stubToTrim($to['sid'], $to['k'], $path);
                $this->debug && $this->log[] = ['reroute' => [$from['sid'], $to['sid']], 'path' => round($path['len'], 1), 'trim' => $trim];
                if ($trim === null) {
                    continue;
                }
                if ($best === null || $path['len'] < $best[0]['len']) {
                    $best = [$path, $from, $to, $trim];
                }
            }
            if ($best === null) {
                continue;
            }
            [$path, $from, $to, $trim] = $best;
            $this->addPath($path, $from['sid']);
            $this->cutSub($to['sid'], $trim[0], $trim[1]);
            $stats['rerouted']++;
            $stats['rerouted_m'] += $path['len'];
            $stats['stub_trimmed_m'] += $trim[2];
        }
    }

    /**
     * Bout de la portion $sid, depuis son extrémité $k jusqu'au point où le chemin s'y raccroche,
     * à retirer : il doit longer le chemin (à moins de PARALLEL_M) sur toute sa longueur.
     *
     * @return array{0: float, 1: float, 2: float}|null intervalle gardé (fractions) et longueur retirée (m), null si refusé
     */
    private function stubToTrim(int $sid, int $k, array $path): ?array
    {
        $r = DB::selectOne(<<<'SQL'
            WITH t AS (SELECT g, ST_Length(g) AS len FROM jr_sub WHERE sid = :sid),
            p AS (SELECT ST_GeomFromText(:wkt, 2154) AS p),
            m AS (SELECT ST_LineLocatePoint(t.g, ST_EndPoint(p.p)) AS f FROM t, p)
            SELECT m.f, t.len,
                   ST_Covers(ST_Buffer(p.p, :par), ST_LineSubstring(t.g, LEAST(m.f, CASE WHEN CAST(:k AS int) = 0 THEN 0 ELSE 1 END), GREATEST(m.f, CASE WHEN CAST(:k2 AS int) = 0 THEN 0 ELSE 1 END))) AS along
            FROM t, p, m
            SQL, ['sid' => $sid, 'wkt' => $this->wkt($path['coords']), 'par' => self::PARALLEL_M, 'k' => $k, 'k2' => $k]);
        $f = (float) $r->f;
        $len = (float) $r->len;
        $cut = $k === 0 ? $f * $len : (1 - $f) * $len;
        if (! $r->along || $cut > self::REROUTE_MAX_M || ($k === 0 ? 1 - $f : $f) * $len < 1) {
            return null;
        }

        return $k === 0 ? [$f, 1.0, $cut] : [0.0, $f, $cut];
    }

    // ─── 4. Restes ─────────────────────────────────────────────────────

    /**
     * Portions précises détachées par le retrait des sauts (les deux bouts libres, l'un au moins libéré),
     * hors tracés distincts gardés par « un seul axe » (ancien tracé daté, voie déposée).
     *
     * @return list<int>
     */
    private function looseSubs(): array
    {
        $sids = array_values(array_unique(array_map(fn ($k) => (int) explode(':', $k)[0], array_keys($this->freed))));
        sort($sids);
        $out = [];
        foreach ($sids as $sid) {
            if (! $this->isFreeEnd($sid, 0) || ! $this->isFreeEnd($sid, 1)) {
                continue;
            }
            $distinct = DB::selectOne(<<<'SQL'
                SELECT 1 AS x FROM jr_sub t, line_reference_duplicates d
                WHERE t.sid = :sid AND d.line_id = :line AND d.action = 'kept_distinct' AND ST_DWithin(ST_Transform(d.geom, 2154), t.g, 1)
                  AND ST_Length(ST_Intersection(t.g, ST_Buffer(ST_Transform(d.geom, 2154), 1))) >= 0.5 * ST_Length(t.g)
                LIMIT 1
                SQL, ['sid' => $sid, 'line' => $this->lineId]);
            if (! $distinct) {
                $out[] = $sid;
            }
        }

        return $out;
    }

    private function removeRemnants(array &$stats): void
    {
        foreach ($this->looseSubs() as $sid) {
            // Boucle : portion tenue aux DEUX bouts par des sauts retirés (Épône-est : 520 m sur la voie voisine entre deux raccords
            // perpendiculaires), qui double la ligne de bout en bout (à moins de BUBBLE_M) : retirée jusqu'à BUBBLE_MAX_M.
            // (au-delà de REMNANT_MAX_M seulement : plus courte, c'est la règle des restes, et une branche qui s'écarte reste).
            $bubble = isset($this->freed[$sid.':0'], $this->freed[$sid.':1'])
                && (float) DB::selectOne('SELECT ST_Length(g) AS m FROM jr_sub WHERE sid = ?', [$sid])->m > self::REMNANT_MAX_M;
            $par = $bubble ? self::BUBBLE_M : self::PARALLEL_M;
            $r = DB::selectOne(<<<'SQL'
                WITH t AS (SELECT g FROM jr_sub WHERE sid = :sid AND NOT removed AND NOT conn AND rank <= 3),
                o AS (SELECT ST_Union(ST_Buffer(o.g, :par)) AS b FROM jr_sub o, t WHERE o.sid <> :sid2 AND NOT o.removed AND NOT o.conn AND ST_DWithin(o.g, t.g, :par2))
                SELECT ST_Length(t.g) AS len, COALESCE(ST_Length(ST_Intersection(t.g, o.b)), 0) AS along FROM t, o
                SQL, ['sid' => $sid, 'sid2' => $sid, 'par' => $par, 'par2' => $par]);
            $max = $bubble ? self::BUBBLE_MAX_M : self::REMNANT_MAX_M;
            $share = $bubble ? self::BUBBLE_SHARE : self::REMNANT_SHARE;
            if (! $r || (float) $r->len > $max || (float) $r->along < $share * (float) $r->len) {
                continue;
            }
            DB::update('UPDATE jr_sub SET removed = TRUE WHERE sid = ?', [$sid]);
            $stats['remnant_removed_m'] += (float) $r->len;
            $this->debug && $this->log[] = ['remnant' => $sid, 'len' => round((float) $r->len, 1)];
        }
    }

    // ─── 5. Prolongement jusqu'à la voie d'une autre ligne ─────────────

    private function extendToOtherLines(array &$stats): void
    {
        foreach ($this->freeEnds() as $e) {
            if ($e['conn']) {
                continue;
            }
            // Voie d'une autre ligne devant l'extrémité (cap <= FACE_DEG), à moins de GAP_MAX_M.
            $o = DB::selectOne(<<<'SQL'
                SELECT ST_Distance(p.g, e.p) AS d
                FROM cf_pool p, (SELECT ST_SetSRID(ST_MakePoint(:x, :y), 2154) AS p) e
                WHERE p.cand AND p.family IN ('contrib', 'bdtopo', 'osm') AND p.line_id IS NOT NULL AND p.line_id <> :line
                  AND ST_DWithin(p.g, e.p, :max) AND ST_Distance(p.g, e.p) > :snap
                  AND degrees(abs(atan2(sin(ST_Azimuth(e.p, ST_ClosestPoint(p.g, e.p)) - :az), cos(ST_Azimuth(e.p, ST_ClosestPoint(p.g, e.p)) - :az2)))) <= :face
                  AND NOT EXISTS (SELECT 1 FROM lines l WHERE l.id = p.line_id AND CAST(:gauge AS int) IS NOT NULL AND l.gauge_mm_default IS NOT NULL AND l.gauge_mm_default <> CAST(:gauge2 AS int))
                ORDER BY ST_Distance(p.g, e.p) LIMIT 1
                SQL, ['x' => $e['x'], 'y' => $e['y'], 'line' => $this->lineId, 'max' => self::GAP_MAX_M, 'snap' => self::SNAP_M, 'az' => $e['az'], 'az2' => $e['az'],
                'face' => self::FACE_DEG, 'gauge' => $this->gauge, 'gauge2' => $this->gauge]);
            if (! $o) {
                continue;
            }
            $max = min(self::EXTEND_MAX_M, self::EXTEND_DETOUR * (float) $o->d + 20);
            $path = $this->route($e, ['type' => 'other'], $max);
            $this->debug && $this->log[] = ['extend' => $e['sid'], 'd' => round((float) $o->d, 1), 'path' => $path ? round($path['len'], 1) : null];
            if (! $path || $this->doubles($path, [$e['sid']])) {
                continue;
            }
            $this->addPath($path, $e['sid']);
            $stats['extended']++;
            $stats['extended_m'] += $path['len'];
        }
    }

    // ─── 6. Dépassements des tracés grossiers ──────────────────────────

    private function trimOvershoots(array &$stats): void
    {
        foreach ($this->freeEnds() as $e) {
            if ($e['rank'] !== 4) {
                continue;
            }
            // Dernier croisement (depuis l'extrémité) avec la voie précise d'une autre ligne, dans les OVERSHOOT_MAX_M derniers mètres.
            $r = DB::selectOne(<<<'SQL'
                WITH t AS (SELECT g, ST_Length(g) AS len FROM jr_sub WHERE sid = :sid),
                tail AS (SELECT CASE WHEN CAST(:k AS int) = 0 THEN ST_LineSubstring(g, 0, LEAST(1, :max / len)) ELSE ST_LineSubstring(g, GREATEST(0, 1 - :max2 / len), 1) END AS g FROM t),
                x AS (
                    SELECT (ST_Dump(ST_Intersection(tail.g, p.g))).geom AS q, p.g AS pg
                    FROM cf_pool p, tail
                    WHERE p.cand AND p.family IN ('contrib', 'bdtopo', 'osm') AND p.line_id IS NOT NULL AND p.line_id <> :line AND ST_Intersects(p.g, tail.g)
                )
                SELECT ST_LineLocatePoint(t.g, x.q) AS f, t.len,
                       degrees(asin(LEAST(1, abs(sin(
                           ST_Azimuth(ST_LineInterpolatePoint(t.g, GREATEST(0, ST_LineLocatePoint(t.g, x.q) - 8 / t.len)), ST_LineInterpolatePoint(t.g, LEAST(1, ST_LineLocatePoint(t.g, x.q) + 8 / t.len)))
                         - ST_Azimuth(ST_LineInterpolatePoint(x.pg, GREATEST(0, ST_LineLocatePoint(x.pg, x.q) - 8 / ST_Length(x.pg))), ST_LineInterpolatePoint(x.pg, LEAST(1, ST_LineLocatePoint(x.pg, x.q) + 8 / ST_Length(x.pg))))))))) AS ang
                FROM t, x WHERE ST_GeometryType(x.q) = 'ST_Point'
                ORDER BY CASE WHEN CAST(:k2 AS int) = 0 THEN ST_LineLocatePoint(t.g, x.q) ELSE -ST_LineLocatePoint(t.g, x.q) END DESC LIMIT 1
                SQL, ['sid' => $e['sid'], 'k' => $e['k'], 'k2' => $e['k'], 'max' => self::OVERSHOOT_MAX_M, 'max2' => self::OVERSHOOT_MAX_M, 'line' => $this->lineId]);
            if (! $r || (float) $r->ang < self::OVERSHOOT_CROSS_DEG) {
                continue;
            }
            $f = (float) $r->f;
            $len = (float) $r->len;
            $over = $e['k'] === 0 ? $f * $len : (1 - $f) * $len;
            $rest = $len - $over;
            if ($over < 1 || $rest < self::OVERSHOOT_REST_M) {
                continue;
            }
            // Rien d'autre de la ligne ne s'accroche au bout coupé (hors croisement).
            $other = DB::selectOne(<<<'SQL'
                WITH t AS (SELECT g FROM jr_sub WHERE sid = :sid),
                tail AS (SELECT ST_LineSubstring(g, :a, :b) AS g FROM t)
                SELECT 1 AS x FROM jr_sub o, tail WHERE o.sid <> :sid2 AND NOT o.removed AND ST_DWithin(o.g, tail.g, 0.5) LIMIT 1
                SQL, ['sid' => $e['sid'], 'sid2' => $e['sid'], 'a' => $e['k'] === 0 ? 0.0 : $f, 'b' => $e['k'] === 0 ? $f : 1.0]);
            if ($other) {
                continue;
            }
            $this->cutSub($e['sid'], $e['k'] === 0 ? $f : 0.0, $e['k'] === 0 ? 1.0 : $f);
            $stats['overshoot_trimmed_m'] += $over;
            $this->debug && $this->log[] = ['overshoot' => $e['sid'], 'm' => round($over, 1)];
        }
    }

    // ─── 7. Arrivée d'un tracé imprécis sur une portion précise ─────────

    /**
     * Rayon minimal plausible (m) d'un raccord de la ligne : aiguillage tg 0,11 (rayon 190 m) sur une ligne
     * principale ; 150 m pour une voie normale secondaire ou particulière ; 60 m pour la voie métrique (et un réseau
     * secondaire d'écartement inconnu) ; 25 m pour la voie de 60 cm (Decauville militaire, inconnue comprise).
     */
    public static function minRadius(?string $network, ?int $gauge): float
    {
        return match (true) {
            $gauge !== null && $gauge <= 600, $gauge === null && $network === 'militaire' => 25.0,
            $gauge !== null && $gauge < 1435, $gauge === null && $network === 'secondaire' => 60.0,
            $network === 'principal' => 190.0,
            default => 150.0,
        };
    }

    private function smoothLegs(array &$stats): void
    {
        $net = DB::selectOne('SELECT network FROM lines WHERE id = ?', [$this->lineId])?->network;
        $rmin = self::minRadius($net, $this->gauge);
        $subs = [];
        foreach (DB::select('SELECT sid, rank, ST_AsText(g) AS wkt FROM jr_sub WHERE NOT removed AND ST_Length(g) > 0.5 ORDER BY sid') as $r) {
            $c = $this->parseWkt($r->wkt);
            if (count($c) >= 2) {
                $subs[(int) $r->sid] = ['rank' => (int) $r->rank, 'c' => $c, 'len' => $this->plen($c)];
            }
        }
        // Extrémités voisines (à 0,5 m près), grille de 1 m.
        $grid = [];
        foreach ($subs as $sid => $t) {
            foreach ([0, 1] as $k) {
                $p = $k === 0 ? $t['c'][0] : end($t['c']);
                $grid[(int) floor($p[0]).':'.(int) floor($p[1])][] = [$sid, $k, $p];
            }
        }
        $adj = function (int $sid, int $k) use (&$subs, $grid): array {
            $p = $k === 0 ? $subs[$sid]['c'][0] : end($subs[$sid]['c']);
            $out = [];
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    foreach ($grid[((int) floor($p[0]) + $dx).':'.((int) floor($p[1]) + $dy)] ?? [] as [$s2, $k2, $q]) {
                        if ($s2 !== $sid && isset($subs[$s2]) && hypot($q[0] - $p[0], $q[1] - $p[1]) <= 0.5) {
                            $out[] = [$s2, $k2];
                        }
                    }
                }
            }

            return $out;
        };

        $done = [];
        foreach (array_keys($subs) as $qsid) {
            foreach ([0, 1] as $qk) {
                if ($subs[$qsid]['rank'] > 3 || isset($done[$qsid])) {
                    continue;
                }
                $nb = $adj($qsid, $qk);
                $this->debug && $nb && $this->log[] = ['leg_nb' => [$qsid, $qk], 'nb' => array_map(fn ($x) => [$x[0], $subs[$x[0]]['rank']], $nb)];
                if (count($nb) !== 1 || $subs[$nb[0][0]]['rank'] < 4 || isset($done[$nb[0][0]])) {
                    continue;
                }
                // Chaîne imprécise depuis la jonction X (bout $qk de Q), vers l'arrière.
                $chain = [$nb[0]];
                $len = $subs[$nb[0][0]]['len'];
                [$cur, $ck] = $nb[0];
                $p1 = null;
                while ($len < self::LEG_MAX_M) {
                    $nx = array_values(array_filter($adj($cur, 1 - $ck), fn ($x) => $x[0] !== $qsid));
                    if (count($nx) !== 1 || in_array($nx[0][0], array_column($chain, 0), true)) {
                        break;
                    }
                    if ($subs[$nx[0][0]]['rank'] <= 3) {
                        $p1 = $nx[0];
                        break;
                    }
                    $chain[] = $nx[0];
                    $len += $subs[$nx[0][0]]['len'];
                    [$cur, $ck] = $nx[0];
                }
                // S : tracé imprécis orienté vers X (du plus loin au plus près) ; QP : Q orientée depuis X.
                $s = [];
                foreach (array_reverse($chain) as [$sid, $k]) {
                    $c = $k === 1 ? $subs[$sid]['c'] : array_reverse($subs[$sid]['c']);
                    foreach ($c as $pt) {
                        if (! $s || hypot($pt[0] - end($s)[0], $pt[1] - end($s)[1]) > 1e-6) {
                            $s[] = $pt;
                        }
                    }
                }
                $qp = $qk === 0 ? $subs[$qsid]['c'] : array_reverse($subs[$qsid]['c']);
                $slen = $this->plen($s);
                $qlen = $subs[$qsid]['len'];
                $chainSids = array_column($chain, 0);
                // a. Pont imprécis entre deux portions précises : voie réelle (dans un sens ou dans l'autre), si la chaîne est coudée.
                $whole = array_merge($s, array_slice($qp, 1));
                if ($p1 !== null && $len <= self::BRIDGE_MAX_M && $this->maxKink($whole) > self::LEG_KINK_DEG
                    && $this->bridge($p1, $qsid, $qk, $chainSids, $len, $stats)) {
                    $done[$qsid] = $done[$p1[0]] = true;
                    foreach ($chainSids as $c) {
                        $done[$c] = true;
                    }

                    continue;
                }
                // Coude sur la fin imprécise près de la jonction (100 derniers m) et l'entrée de la portion précise (30 m).
                $tail = $this->subLine($s, max(0.0, $slen - 100), $slen);
                $probe = array_merge($tail, array_slice($this->subLine($qp, 0, min($qlen, 30)), 1));
                $this->debug && $this->log[] = ['leg_kink' => $qsid, 'kink' => round($this->maxKink($probe)), 'len' => round($len), 'p1' => $p1];
                if ($this->maxKink($probe) <= self::LEG_KINK_DEG) {
                    continue;
                }
                // b. Courbe lissée D -> J.
                $best = $this->fitLeg($s, $slen, $qp, $qlen, $p1 !== null, $rmin);
                $this->debug && $this->log[] = ['leg' => $qsid, 'chain' => $chainSids, 'len' => round($len, 1), 'fit' => $best ? [$best['ld'], $best['lj'], round($best['r'])] : null];
                if ($best === null) {
                    continue;
                }
                $curve = ['coords' => $best['coords'], 'len' => $this->plen($best['coords'])];
                if ($this->doubles($curve, array_merge([$qsid], $chainSids))) {
                    continue;
                }
                // Coupe de la chaîne à D (à $best['ld'] m de X) et de Q à J.
                $acc = 0.0;
                foreach ($chain as [$sid, $k]) {
                    $l = $subs[$sid]['len'];
                    if ($acc + $l <= $best['ld'] + 1e-6) {
                        DB::update('UPDATE jr_sub SET removed = TRUE WHERE sid = ?', [$sid]);
                    } elseif ($acc < $best['ld']) {
                        $f = ($best['ld'] - $acc) / $l;
                        // Bout $k vers X : on garde la partie éloignée de X.
                        $k === 1 ? $this->cutSub($sid, 0.0, 1 - $f) : $this->cutSub($sid, $f, 1.0);
                    }
                    $acc += $l;
                }
                if ($best['lj'] > 0) {
                    $f = $best['lj'] / $qlen;
                    $qk === 0 ? $this->cutSub($qsid, $f, 1.0) : $this->cutSub($qsid, 0.0, 1 - $f);
                }
                $from = DB::selectOne('SELECT 1 AS x FROM jr_sub WHERE sid = ? AND NOT removed', [$chain[count($chain) - 1][0]]) ? $chain[count($chain) - 1][0] : $chain[0][0];
                $this->insertSmoothed($best['coords'], $from);
                $stats['leg_smoothed']++;
                $stats['leg_smoothed_m'] += $curve['len'];
                $done[$qsid] = true;
                foreach ($chainSids as $c) {
                    $done[$c] = true;
                }
            }
        }
    }

    /** Règle 7 c et d : bouts imprécis arrêtés avant la voie d'une autre ligne, trous sans voie précise. */
    private function smoothEnds(array &$stats): void
    {
        $net = DB::selectOne('SELECT network FROM lines WHERE id = ?', [$this->lineId])?->network;
        $rmin = self::minRadius($net, $this->gauge);
        $narrow = in_array($net, TraceConflation::JUNCTION_NARROW_NETWORKS, true);
        $used = [];

        // d. Trous face à face (les trous couverts par une voie précise ont été comblés à l'étape 2).
        $ends = $this->freeEnds();
        $pairs = [];
        foreach ($ends as $i => $a) {
            foreach ($ends as $j => $b) {
                if ($j <= $i || $a['sid'] === $b['sid'] || $a['conn'] || $b['conn']) {
                    continue;
                }
                $d = hypot($b['x'] - $a['x'], $b['y'] - $a['y']);
                $ab = atan2($b['x'] - $a['x'], $b['y'] - $a['y']);
                if ($d <= 0.5 || $d > self::HOLE_SMOOTH_MAX_M
                    || $this->angDiff($ab, $a['az']) > deg2rad(self::FACE_DEG) || $this->angDiff($ab + M_PI, $b['az']) > deg2rad(self::FACE_DEG)) {
                    continue;
                }
                $pairs[] = [$d, $i, $j];
            }
        }
        sort($pairs);
        foreach ($pairs as [$d, $i, $j]) {
            if (isset($used[$i]) || isset($used[$j])) {
                continue;
            }
            $a = $ends[$i];
            $b = $ends[$j];
            $pts = $this->hermite([$a['x'], $a['y']], [sin($a['az']), cos($a['az'])], [$b['x'], $b['y']], [-sin($b['az']), -cos($b['az'])]);
            if ($this->minCurveRadius($pts) < $rmin || max(array_map(fn ($p) => $this->distToLine([[$a['x'], $a['y']], [$b['x'], $b['y']]], $p), $pts)) > self::LEG_DEV_M) {
                $this->debug && $this->log[] = ['hole_smooth' => [$a['sid'], $b['sid']], 'd' => round($d, 1), 'fit' => null];

                continue;
            }
            $curve = ['coords' => $pts, 'len' => $this->plen($pts)];
            if ($this->doubles($curve, [$a['sid'], $b['sid']])) {
                continue;
            }
            $this->insertSmoothed($pts, $a['sid']);
            $used[$i] = $used[$j] = true;
            $stats['hole_smoothed']++;
            $stats['hole_smoothed_m'] += $curve['len'];
        }

        // c. Bout imprécis qui converge vers la voie d'une autre ligne et s'arrête avant elle (voie devant lui : à moins de 80° du cap ;
        // la convergence est vérifiée plus bas).
        foreach ($this->freeEnds() as $e) {
            if ($e['rank'] !== 4 || $e['conn']) {
                continue;
            }
            $t = DB::selectOne(<<<'SQL'
                WITH e AS (SELECT ST_SetSRID(ST_MakePoint(:x, :y), 2154) AS p),
                near AS (
                    SELECT p.line_id, ST_Distance(p.g, e.p) AS d
                    FROM cf_pool p, e
                    WHERE p.cand AND p.family IN ('contrib', 'bdtopo', 'osm') AND p.cls <> 'service' AND p.line_id IS NOT NULL AND p.line_id <> :line
                      AND ST_DWithin(p.g, e.p, :gap) AND ST_Distance(p.g, e.p) > :snap
                      AND degrees(abs(atan2(sin(ST_Azimuth(e.p, ST_ClosestPoint(p.g, e.p)) - :az), cos(ST_Azimuth(e.p, ST_ClosestPoint(p.g, e.p)) - :az2)))) <= :face
                    ORDER BY 2, p.pid LIMIT 1
                )
                SELECT near.line_id, near.d, l.gauge_mm_default AS gauge, l.network,
                       (SELECT ST_AsText(x.geom) FROM e, LATERAL ST_Dump(ST_LineMerge(ST_Union(ARRAY(
                            SELECT q.g FROM cf_pool q WHERE q.cand AND q.line_id = near.line_id AND q.family IN ('contrib', 'bdtopo', 'osm')
                              AND ST_DWithin(q.g, (SELECT p FROM e), 500))))) x ORDER BY ST_Distance(x.geom, e.p) LIMIT 1) AS wkt
                FROM near JOIN lines l ON l.id = near.line_id
                SQL, ['x' => $e['x'], 'y' => $e['y'], 'line' => $this->lineId, 'gap' => self::END_GAP_M, 'snap' => self::SNAP_M,
                'az' => $e['az'], 'az2' => $e['az'], 'face' => 80.0]);
            if (! $t || $t->wkt === null) {
                continue;
            }
            // Partage de voie physiquement impossible (garde-fou de TraceConflation::junctionTrunk).
            if (($this->gauge !== null && $t->gauge !== null && (int) $t->gauge !== $this->gauge)
                || (($this->gauge === null || $t->gauge === null) && $narrow && $t->network === 'principal')) {
                continue;
            }
            $sub = DB::selectOne('SELECT ST_AsText(g) AS wkt FROM jr_sub WHERE sid = ?', [$e['sid']]);
            $s = $this->parseWkt($sub->wkt);
            if ($e['k'] === 0) {
                $s = array_reverse($s);
            }
            $slen = $this->plen($s);
            // Converge : le tracé est plus loin de la voie 60 m avant son bout.
            $tc = $this->parseWkt($t->wkt);
            $back = $this->pointAlong($s, max(0.0, $slen - 60));
            if ($this->distToLine($tc, $back) < (float) $t->d + 5) {
                continue;
            }
            // Voie orientée dans le sens du tracé, depuis la projection du bout.
            [$pos, $dir] = $this->projectOn($tc, [$e['x'], $e['y']], [sin($e['az']), cos($e['az'])]);
            $qp = $dir > 0 ? $this->subLine($tc, $pos, $this->plen($tc)) : array_reverse($this->subLine($tc, 0, $pos));
            $qlen = $this->plen($qp);
            if ($qlen < 10) {
                continue;
            }
            $best = $this->fitLeg($s, $slen, $qp, $qlen, false, $rmin, array_merge($s, [[$e['x'], $e['y']]], $qp));
            $this->debug && $this->log[] = ['end_join' => $e['sid'], 'd' => round((float) $t->d, 1), 'fit' => $best ? [$best['ld'], $best['lj']] : null];
            if ($best === null) {
                continue;
            }
            $curve = ['coords' => $best['coords'], 'len' => $this->plen($best['coords'])];
            if ($this->doubles($curve, [$e['sid']])) {
                continue;
            }
            $f = $best['ld'] / $slen;
            $e['k'] === 1 ? $this->cutSub($e['sid'], 0.0, 1 - $f) : $this->cutSub($e['sid'], $f, 1.0);
            $this->insertSmoothed($best['coords'], $e['sid']);
            $stats['end_joined']++;
            $stats['end_joined_m'] += $curve['len'];
        }
    }

    /** Ajoute une courbe estimée (« raccord-lisse ») ; provenance : morceau prolongé. */
    private function insertSmoothed(array $coords, int $fromSid): void
    {
        // Bouts de la courbe posés exactement sur les extrémités voisines (coupes : fusion au bit près).
        foreach ([0, count($coords) - 1] as $i) {
            $e = DB::selectOne(<<<'SQL'
                SELECT ST_X(v.e) AS x, ST_Y(v.e) AS y FROM jr_sub t, LATERAL (VALUES (ST_StartPoint(t.g)), (ST_EndPoint(t.g))) v(e)
                WHERE NOT t.removed AND ST_DWithin(v.e, ST_SetSRID(ST_MakePoint(:x, :y), 2154), 0.5)
                ORDER BY ST_Distance(v.e, ST_SetSRID(ST_MakePoint(:x2, :y2), 2154)) LIMIT 1
                SQL, ['x' => $coords[$i][0], 'y' => $coords[$i][1], 'x2' => $coords[$i][0], 'y2' => $coords[$i][1]]);
            if ($e) {
                $coords[$i] = [(float) $e->x, (float) $e->y];
            }
        }
        $from = DB::selectOne('SELECT p.source, p.source_trace_ids FROM jr_sub t JOIN line_reference_parts p ON p.id = t.part_id WHERE t.sid = ?', [$fromSid])
            ?? DB::selectOne('SELECT added FROM jr_sub WHERE sid = ?', [$fromSid]);
        if ($from && isset($from->added)) {
            $m = json_decode($from->added, true);
            $from = (object) ['source' => $m['source'], 'source_trace_ids' => json_encode($m['source_ids'])];
        }
        $meta = ['source' => $from->source ?? 'archeoferroviaire', 'source_ids' => $from ? json_decode($from->source_trace_ids, true) : [],
            'matched' => self::SMOOTHED, 'matched_ids' => []];
        DB::insert('INSERT INTO jr_sub (part_id, sub, conn, rank, added, g) VALUES (NULL, 0, FALSE, 4, ?, ST_GeomFromText(?, 2154))',
            [json_encode($meta), $this->wkt($coords)]);
    }

    /**
     * Abscisse de la projection de $p sur la polyligne et sens (1 / -1) de la polyligne le plus proche du cap $u.
     *
     * @return array{0: float, 1: int}
     */
    private function projectOn(array $c, array $p, array $u): array
    {
        $best = [0.0, INF, 1];
        $acc = 0.0;
        for ($i = 1; $i < count($c); $i++) {
            [$ax, $ay] = $c[$i - 1];
            [$bx, $by] = $c[$i];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $l2 = $dx * $dx + $dy * $dy;
            $l = sqrt($l2);
            $t = $l2 > 0 ? max(0.0, min(1.0, (($p[0] - $ax) * $dx + ($p[1] - $ay) * $dy) / $l2)) : 0.0;
            $d = hypot($ax + $t * $dx - $p[0], $ay + $t * $dy - $p[1]);
            if ($d < $best[1]) {
                $best = [$acc + $t * $l, $d, ($dx * $u[0] + $dy * $u[1]) >= 0 ? 1 : -1];
            }
            $acc += $l;
        }

        return [$best[0], $best[2]];
    }

    /** Hermite cubique de $d (tangente unitaire $u0) à $j (tangente $u1), tangentes mises à l'échelle de la corde. */
    private function hermite(array $d, array $u0, array $j, array $u1): array
    {
        $c = hypot($j[0] - $d[0], $j[1] - $d[1]);
        $n = max(10, (int) ceil($c / 4));
        $pts = [];
        for ($i = 0; $i <= $n; $i++) {
            $t = $i / $n;
            $h00 = 2 * $t ** 3 - 3 * $t ** 2 + 1;
            $h10 = $t ** 3 - 2 * $t ** 2 + $t;
            $h01 = -2 * $t ** 3 + 3 * $t ** 2;
            $h11 = $t ** 3 - $t ** 2;
            $pts[] = [$h00 * $d[0] + $h10 * $u0[0] * $c + $h01 * $j[0] + $h11 * $u1[0] * $c, $h00 * $d[1] + $h10 * $u0[1] * $c + $h01 * $j[1] + $h11 * $u1[1] * $c];
        }
        $pts[0] = $d;
        $pts[$n] = $j;

        return $pts;
    }

    private function minCurveRadius(array $pts): float
    {
        $r = INF;
        for ($i = 1; $i < count($pts) - 1; $i++) {
            $r = min($r, $this->circumradius($pts[$i - 1], $pts[$i], $pts[$i + 1]));
        }

        return $r;
    }

    /**
     * Pont imprécis (chaîne $chainSids) entre la portion précise $p1 et la portion $qsid (bout $qk) refait par
     * le chemin réel sur les voies précises, essayé depuis chaque bout ; le bout de la portion d'arrivée qui longe
     * le chemin est retiré.
     */
    private function bridge(array $p1, int $qsid, int $qk, array $chainSids, float $len, array &$stats): bool
    {
        $best = null;
        foreach ([[[$p1[0], $p1[1]], [$qsid, $qk]], [[$qsid, $qk], [$p1[0], $p1[1]]]] as [[$fs, $fk], [$ts, $tk]]) {
            $start = $this->endInfo($fs, $fk);
            $path = $this->route($start, ['type' => 'subs', 'sids' => [$ts]], min(self::REROUTE_MAX_M, self::DETOUR * $len + self::DETOUR_ADD_M));
            if (! $path || $path['tsid'] !== $ts || $this->doubles($path, array_merge([$fs, $ts], $chainSids))) {
                continue;
            }
            $x = $this->endInfo($ts, $tk);
            $end = end($path['coords']);
            $trim = hypot($end[0] - $x['x'], $end[1] - $x['y']) <= 2 ? [0.0, 1.0, 0.0] : $this->stubToTrim($ts, $tk, $path);
            if ($trim !== null && ($best === null || $path['len'] < $best[0]['len'])) {
                $best = [$path, $fs, $ts, $trim];
            }
        }
        if ($best === null) {
            return false;
        }
        [$path, $fs, $ts, $trim] = $best;
        DB::update('UPDATE jr_sub SET removed = TRUE WHERE sid = ANY(CAST(? AS int[]))', [$this->ints($chainSids)]);
        $this->addPath($path, $fs);
        if ($trim[2] > 0) {
            $this->cutSub($ts, $trim[0], $trim[1]);
        }
        $stats['leg_rerouted']++;
        $stats['leg_rerouted_m'] += $path['len'];
        $this->debug && $this->log[] = ['leg_route' => [$fs, $ts], 'len' => round($path['len'], 1)];

        return true;
    }

    /**
     * Courbe D -> J la plus courte (D à ld m de X sur S, J à lj m de X sur QP) dont le rayon minimal est au moins
     * $rmin et qui reste à moins de LEG_DEV_M du tracé remplacé.
     *
     * @param  list<array{0: float, 1: float}>  $s
     * @param  list<array{0: float, 1: float}>  $qp
     * @return array{coords: list<array{0: float, 1: float}>, ld: float, lj: float, r: float}|null
     */
    private function fitLeg(array $s, float $slen, array $qp, float $qlen, bool $toEnd, float $rmin, ?array $ref = null): ?array
    {
        $ds = array_values(array_filter(self::LEG_D_STEPS, fn ($d) => $d < $slen - 1e-6));
        if ($toEnd || ! $ds) {
            $ds[] = $slen;
        }
        $js = array_values(array_filter(self::LEG_J_STEPS, fn ($j) => $j <= $qlen - 5));
        $cands = [];
        foreach ($ds as $ld) {
            foreach ($js as $lj) {
                $cands[] = [$ld + $lj, $ld, $lj];
            }
        }
        sort($cands);
        $ref ??= array_merge($s, array_slice($qp, 1));
        foreach ($cands as [, $ld, $lj]) {
            $d = $this->pointAlong($s, $slen - $ld);
            $a = $this->pointAlong($s, max(0.0, $slen - $ld - 15));
            if ($slen - $ld < 1) {
                $a = $this->pointAlong($s, min($slen, 15));
                $u0 = [$a[0] - $d[0], $a[1] - $d[1]];
            } else {
                $u0 = [$d[0] - $a[0], $d[1] - $a[1]];
            }
            $j = $this->pointAlong($qp, $lj);
            $b = $this->pointAlong($qp, min($qlen, $lj + 15));
            $u1 = [$b[0] - $j[0], $b[1] - $j[1]];
            $n0 = hypot($u0[0], $u0[1]);
            $n1 = hypot($u1[0], $u1[1]);
            $c = hypot($j[0] - $d[0], $j[1] - $d[1]);
            if ($n0 < 1e-6 || $n1 < 1e-6 || $c < 2) {
                continue;
            }
            $pts = $this->hermite($d, [$u0[0] / $n0, $u0[1] / $n0], $j, [$u1[0] / $n1, $u1[1] / $n1]);
            $r = $this->minCurveRadius($pts);
            if ($r < $rmin) {
                continue;
            }
            $dev = 0.0;
            foreach ($pts as $pt) {
                $dev = max($dev, $this->distToLine($ref, $pt));
            }
            if ($dev > self::LEG_DEV_M) {
                continue;
            }

            return ['coords' => $pts, 'ld' => (float) $ld, 'lj' => (float) $lj, 'r' => $r];
        }

        return null;
    }

    /** Coude max (°) sur ±10 m aux sommets d'une polyligne. */
    private function maxKink(array $c): float
    {
        $len = $this->plen($c);
        $max = 0.0;
        $acc = 0.0;
        for ($i = 1; $i < count($c) - 1; $i++) {
            $acc += hypot($c[$i][0] - $c[$i - 1][0], $c[$i][1] - $c[$i - 1][1]);
            if ($acc < 10 || $len - $acc < 10) {
                continue;
            }
            $a = $this->pointAlong($c, $acc - 10);
            $b = $this->pointAlong($c, $acc + 10);
            $a1 = atan2($c[$i][0] - $a[0], $c[$i][1] - $a[1]);
            $a2 = atan2($b[0] - $c[$i][0], $b[1] - $c[$i][1]);
            $max = max($max, rad2deg($this->angDiff($a1, $a2)));
        }

        return $max;
    }

    private function plen(array $c): float
    {
        $l = 0.0;
        for ($i = 1; $i < count($c); $i++) {
            $l += hypot($c[$i][0] - $c[$i - 1][0], $c[$i][1] - $c[$i - 1][1]);
        }

        return $l;
    }

    /** Point à l'abscisse $s (m) d'une polyligne (bornée). */
    private function pointAlong(array $c, float $s): array
    {
        $acc = 0.0;
        for ($i = 1; $i < count($c); $i++) {
            $l = hypot($c[$i][0] - $c[$i - 1][0], $c[$i][1] - $c[$i - 1][1]);
            if ($acc + $l >= $s && $l > 0) {
                $t = max(0.0, min(1.0, ($s - $acc) / $l));

                return [$c[$i - 1][0] + $t * ($c[$i][0] - $c[$i - 1][0]), $c[$i - 1][1] + $t * ($c[$i][1] - $c[$i - 1][1])];
            }
            $acc += $l;
        }

        return $s <= 0 ? $c[0] : end($c);
    }

    /** Sous-polyligne entre les abscisses $a <= $b (m). */
    private function subLine(array $c, float $a, float $b): array
    {
        $out = [$this->pointAlong($c, $a)];
        $acc = 0.0;
        for ($i = 1; $i < count($c) - 1; $i++) {
            $acc += hypot($c[$i][0] - $c[$i - 1][0], $c[$i][1] - $c[$i - 1][1]);
            if ($acc > $a && $acc < $b) {
                $out[] = $c[$i];
            }
        }
        $out[] = $this->pointAlong($c, $b);

        return $out;
    }

    private function circumradius(array $a, array $b, array $c): float
    {
        $ab = hypot($b[0] - $a[0], $b[1] - $a[1]);
        $bc = hypot($c[0] - $b[0], $c[1] - $b[1]);
        $ca = hypot($a[0] - $c[0], $a[1] - $c[1]);
        $cross = abs(($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]));

        return $cross < 1e-9 ? INF : $ab * $bc * $ca / (2 * $cross);
    }

    private function distToLine(array $c, array $p): float
    {
        $best = INF;
        for ($i = 1; $i < count($c); $i++) {
            [$ax, $ay] = $c[$i - 1];
            [$bx, $by] = $c[$i];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $l2 = $dx * $dx + $dy * $dy;
            $t = $l2 > 0 ? max(0.0, min(1.0, (($p[0] - $ax) * $dx + ($p[1] - $ay) * $dy) / $l2)) : 0.0;
            $best = min($best, hypot($ax + $t * $dx - $p[0], $ay + $t * $dy - $p[1]));
        }

        return $best;
    }

    // ─── Extrémités ────────────────────────────────────────────────────

    /**
     * Extrémités libres des portions gardées (ne touchant aucune autre portion à 0,5 m près).
     *
     * @return list<array{sid: int, k: int, x: float, y: float, az: float, rank: int, conn: bool}> az = cap sortant (radians, depuis le nord)
     */
    private function freeEnds(): array
    {
        return array_map(fn ($r) => $this->endRow($r), DB::select(<<<'SQL'
            SELECT t.sid, e.k, ST_X(e.p) AS x, ST_Y(e.p) AS y, t.rank, t.conn,
                   ST_Azimuth(ST_LineInterpolatePoint(t.g, CASE WHEN e.k = 0 THEN LEAST(0.5, 15 / ST_Length(t.g)) ELSE GREATEST(0.5, 1 - 15 / ST_Length(t.g)) END), e.p) AS az
            FROM jr_sub t, LATERAL (VALUES (0, ST_StartPoint(t.g)), (1, ST_EndPoint(t.g))) e(k, p)
            WHERE NOT t.removed AND ST_Length(t.g) > 1 AND NOT ST_DWithin(ST_StartPoint(t.g), ST_EndPoint(t.g), 0.5)
              AND NOT EXISTS (SELECT 1 FROM jr_sub o WHERE NOT o.removed AND o.sid <> t.sid AND ST_DWithin(o.g, e.p, 0.5))
            ORDER BY t.sid, e.k
            SQL));
    }

    private function endInfo(int $sid, int $k): array
    {
        return $this->endRow(DB::selectOne(<<<'SQL'
            SELECT t.sid, e.k, ST_X(e.p) AS x, ST_Y(e.p) AS y, t.rank, t.conn,
                   ST_Azimuth(ST_LineInterpolatePoint(t.g, CASE WHEN e.k = 0 THEN LEAST(0.5, 15 / ST_Length(t.g)) ELSE GREATEST(0.5, 1 - 15 / ST_Length(t.g)) END), e.p) AS az
            FROM jr_sub t, LATERAL (VALUES (CAST(:k AS int), CASE WHEN CAST(:k2 AS int) = 0 THEN ST_StartPoint(t.g) ELSE ST_EndPoint(t.g) END)) e(k, p)
            WHERE t.sid = :sid
            SQL, ['sid' => $sid, 'k' => $k, 'k2' => $k]));
    }

    private function endRow(object $r): array
    {
        return ['sid' => (int) $r->sid, 'k' => (int) $r->k, 'x' => (float) $r->x, 'y' => (float) $r->y, 'az' => (float) $r->az,
            'rank' => (int) $r->rank, 'conn' => (bool) $r->conn];
    }

    private function isFreeEnd(int $sid, int $k): bool
    {
        return (bool) DB::selectOne(<<<'SQL'
            SELECT NOT t.removed AND NOT EXISTS (
                SELECT 1 FROM jr_sub o WHERE NOT o.removed AND o.sid <> t.sid
                  AND ST_DWithin(o.g, CASE WHEN CAST(:k AS int) = 0 THEN ST_StartPoint(t.g) ELSE ST_EndPoint(t.g) END, 0.5)) AS free
            FROM jr_sub t WHERE t.sid = :sid
            SQL, ['sid' => $sid, 'k' => $k])?->free;
    }

    // ─── Modifications ─────────────────────────────────────────────────

    /**
     * Le chemin longe-t-il une autre portion de la ligne (hors $exclude et hors restes détachés
     * par le retrait des sauts, retirés ensuite) à moins de PARALLEL_M sur plus de DOUBLE_SHARE
     * de sa longueur (et plus de DOUBLE_MIN_M) ? Ce serait un doublon.
     */
    private function doubles(array $path, array $exclude): bool
    {
        $m = (float) DB::selectOne(<<<'SQL'
            WITH p AS (SELECT ST_GeomFromText(:wkt, 2154) AS p)
            SELECT COALESCE(ST_Length(ST_Intersection(p.p, ST_Union(ST_Buffer(o.g, :par)))), 0) AS m
            FROM p, jr_sub o
            WHERE NOT o.removed AND NOT o.conn AND o.sid <> ALL(CAST(:ex AS int[])) AND ST_DWithin(o.g, p.p, :par2)
            GROUP BY p.p
            SQL, ['wkt' => $this->wkt($path['coords']), 'par' => self::PARALLEL_M, 'par2' => self::PARALLEL_M,
            'ex' => $this->ints(array_merge($exclude, $this->freed ? $this->looseSubs() : []))])?->m;

        return $m > self::DOUBLE_MIN_M && $m > self::DOUBLE_SHARE * $path['len'];
    }

    /** Ajoute un chemin (portion nouvelle), provenance : voies de la ligne, sinon portion prolongée + voie précise. */
    private function addPath(array $path, int $fromSid): void
    {
        $from = DB::selectOne('SELECT p.source, p.source_trace_ids FROM jr_sub t JOIN line_reference_parts p ON p.id = t.part_id WHERE t.sid = ?', [$fromSid]);
        // Voies de la ligne ou rattachées à aucune ligne : géométrie de la famille (rang de la source) ; voie d'une autre ligne : recalage.
        $own = array_diff($path['lines'], [$this->lineId]) === [];
        $meta = $own
            ? ['source' => $path['family'], 'source_ids' => $path['ids'], 'matched' => null, 'matched_ids' => null]
            : ['source' => $from->source ?? $path['family'], 'source_ids' => $from ? json_decode($from->source_trace_ids, true) : [], 'matched' => $path['family'], 'matched_ids' => $path['ids']];
        $rank = $own ? ['contrib' => 0, 'bdtopo' => 1, 'osm' => 2][$path['family']] : 3;
        DB::insert('INSERT INTO jr_sub (part_id, sub, conn, rank, added, g) VALUES (NULL, 0, FALSE, ?, ?, ST_GeomFromText(?, 2154))',
            [$rank, json_encode($meta), $this->wkt($path['coords'])]);
    }

    /** Garde la portion $sid entre les fractions $a et $b. */
    private function cutSub(int $sid, float $a, float $b): void
    {
        DB::update('UPDATE jr_sub SET g = ST_LineSubstring(g, ?, ?), changed = TRUE WHERE sid = ?', [$a, $b, $sid]);
    }

    // ─── Graphe des voies réelles et chemin le plus court ──────────────

    /**
     * Chemin sur les voies précises depuis l'extrémité $start (cap sortant az) jusqu'à :
     *   point : un point (cap d'arrivée az) à moins de snap m d'une voie ;
     *   subs  : une portion de la ligne (sids), voie confondue avec elle (MERGE_M) ;
     *   other : la voie d'une autre ligne (écartement compatible).
     *
     * @return array{coords: list<array{0: float, 1: float}>, len: float, family: string, ids: list<int>, lines: list<int>, tsid: ?int}|null
     */
    private function route(array $start, array $target, float $maxLen, bool $straight = false): ?array
    {
        $snapS = self::SNAP_M;
        if ($target['type'] === 'point') {
            $cx = ($start['x'] + $target['x']) / 2;
            $cy = ($start['y'] + $target['y']) / 2;
            $radius = $maxLen / 2 + $snapS + $target['snap'];
        } else {
            $cx = $start['x'];
            $cy = $start['y'];
            $radius = $maxLen + $snapS;
        }
        $edges = $this->graph($cx, $cy, $radius, $target['type'] === 'subs' ? $target['sids'] : []);
        if (! $edges) {
            return null;
        }

        $best = null;
        foreach (array_keys(array_count_values(array_column($edges, 'family'))) as $family) {
            $fe = array_values(array_filter($edges, fn ($e) => $e['family'] === $family));
            $p = $this->dijkstra($fe, $start, $snapS, $target, $maxLen, $straight);
            if ($p && ($best === null || $p['len'] < $best['len'] - 1e-6)) {
                $best = $p + ['family' => $family];
            }
        }
        if (! $best) {
            return null;
        }
        $coords = $best['coords'];
        // Bouts exacts : départ sur l'extrémité, arrivée sur le point visé (ou sur la portion visée).
        if (hypot($coords[0][0] - $start['x'], $coords[0][1] - $start['y']) > 1e-6) {
            array_unshift($coords, [$start['x'], $start['y']]);
        }
        $tsid = null;
        if ($target['type'] === 'point') {
            $last = end($coords);
            if (hypot($last[0] - $target['x'], $last[1] - $target['y']) > 1e-6) {
                $coords[] = [$target['x'], $target['y']];
            }
        } elseif ($target['type'] === 'subs') {
            $tsid = $best['tsid'];
            $last = end($coords);
            $q = DB::selectOne('SELECT ST_X(q) AS x, ST_Y(q) AS y FROM (SELECT ST_ClosestPoint(g, ST_SetSRID(ST_MakePoint(?, ?), 2154)) AS q FROM jr_sub WHERE sid = ?) z',
                [$last[0], $last[1], $tsid]);
            if (hypot($last[0] - $q->x, $last[1] - $q->y) > 1e-6) {
                $coords[] = [(float) $q->x, (float) $q->y];
            }
        }
        $len = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $len += hypot($coords[$i][0] - $coords[$i - 1][0], $coords[$i][1] - $coords[$i - 1][1]);
        }
        if ($len < 0.5) {
            return null;
        }
        $ids = array_values(array_unique($best['ids']));
        sort($ids);
        $lines = array_values(array_unique($best['lines']));
        sort($lines);

        return ['coords' => $coords, 'len' => $len, 'family' => $best['family'], 'ids' => $ids, 'lines' => $lines, 'tsid' => $tsid];
    }

    /**
     * Morceaux de voie précise dans la fenêtre, coupés à leurs intersections (une famille à la fois).
     *
     * @param  list<int>  $targetSids  portions visées : fractions du morceau confondues avec elles
     * @return list<array<string, mixed>>
     */
    private function graph(float $cx, float $cy, float $radius, array $targetSids): array
    {
        $rows = DB::select(<<<'SQL'
            WITH w AS (SELECT ST_Buffer(ST_SetSRID(ST_MakePoint(:cx, :cy), 2154), :r, 16) AS w),
            e AS (
                SELECT p.id, p.family, p.line_id, d.geom AS g
                FROM cf_pool p, w, LATERAL ST_Dump(ST_CollectionExtract(ST_Intersection(p.g, w.w), 2)) d
                WHERE p.cand AND p.family IN ('contrib', 'bdtopo', 'osm') AND p.g && w.w AND ST_Intersects(p.g, w.w)
                  AND (p.line_id IS NULL OR p.line_id = :line OR NOT EXISTS (
                      SELECT 1 FROM lines l WHERE l.id = p.line_id AND CAST(:gauge AS int) IS NOT NULL
                        AND l.gauge_mm_default IS NOT NULL AND l.gauge_mm_default <> CAST(:gauge2 AS int)))
                  AND ST_Length(d.geom) > 0.05
            ),
            n AS (SELECT family, (ST_Dump(ST_Node(ST_Collect(g ORDER BY id)))).geom AS g FROM e GROUP BY family),
            n2 AS (SELECT row_number() OVER (ORDER BY family, ST_XMin(g), ST_YMin(g), ST_XMax(g), ST_YMax(g), ST_Length(g)) AS i, family, g FROM n WHERE ST_Length(g) > 0.05)
            SELECT n2.i, n2.family, ST_AsText(n2.g) AS wkt,
                   (SELECT jsonb_agg(DISTINCT jsonb_build_array(e.id, e.line_id)) FROM e
                    WHERE e.family = n2.family AND ST_DWithin(e.g, ST_LineInterpolatePoint(n2.g, 0.5), 0.01)) AS orig,
                   t.sid AS tsid, t.fa, t.fb
            FROM n2
            LEFT JOIN LATERAL (
                SELECT o.sid, MIN(LEAST(ST_LineLocatePoint(n2.g, ST_StartPoint(x.geom)), ST_LineLocatePoint(n2.g, ST_EndPoint(x.geom)))) AS fa,
                       MAX(GREATEST(ST_LineLocatePoint(n2.g, ST_StartPoint(x.geom)), ST_LineLocatePoint(n2.g, ST_EndPoint(x.geom)))) AS fb
                FROM jr_sub o, LATERAL ST_Dump(ST_CollectionExtract(ST_Intersection(n2.g, ST_Buffer(o.g, :merge)), 2)) x
                WHERE o.sid = ANY(CAST(:tsids AS int[])) AND NOT o.removed AND ST_DWithin(o.g, n2.g, :merge2)
                GROUP BY o.sid
                -- Voie confondue avec la portion sur au moins la moitié du morceau (pas la fin d'une communication qui s'en approche).
                HAVING SUM(ST_Length(x.geom)) >= 0.5 * ST_Length(n2.g)
                ORDER BY o.sid LIMIT 1
            ) t ON TRUE
            ORDER BY n2.i
            SQL, ['cx' => $cx, 'cy' => $cy, 'r' => $radius, 'line' => $this->lineId, 'gauge' => $this->gauge, 'gauge2' => $this->gauge,
            'merge' => self::MERGE_M, 'merge2' => self::MERGE_M, 'tsids' => $this->ints($targetSids)]);

        $edges = [];
        foreach ($rows as $r) {
            $coords = $this->parseWkt($r->wkt);
            if (count($coords) < 2) {
                continue;
            }
            $cum = [0.0];
            for ($i = 1; $i < count($coords); $i++) {
                $cum[] = $cum[$i - 1] + hypot($coords[$i][0] - $coords[$i - 1][0], $coords[$i][1] - $coords[$i - 1][1]);
            }
            $len = end($cum);
            $orig = json_decode($r->orig ?? '[]', true) ?: [];
            $lineIds = array_values(array_unique(array_filter(array_map(fn ($o) => $o[1], $orig), fn ($l) => $l !== null)));
            $edges[] = [
                'family' => $r->family, 'coords' => $coords, 'cum' => $cum, 'len' => $len,
                'k0' => $this->key($coords[0]), 'k1' => $this->key(end($coords)),
                'ids' => array_map(fn ($o) => (int) $o[0], $orig), 'lines' => array_map('intval', $lineIds),
                'own' => in_array($this->lineId, $lineIds, true),
                'other' => $lineIds && ! in_array($this->lineId, $lineIds, true),
                'tsid' => $r->tsid !== null ? (int) $r->tsid : null, 'fa' => (float) ($r->fa ?? 0) * $len, 'fb' => (float) ($r->fb ?? 0) * $len,
            ];
        }

        return $edges;
    }

    /**
     * Dijkstra sur les états (morceau, sens) ; déviation <= TURN_DEG à chaque nœud.
     *
     * @return array{coords: list<array{0: float, 1: float}>, len: float, ids: list<int>, lines: list<int>, tsid: ?int}|null
     */
    private function dijkstra(array $edges, array $start, float $snapS, array $target, float $maxLen, bool $straight): ?array
    {
        $adj = [];
        foreach ($edges as $i => $e) {
            $adj[$e['k0']][] = [$i, 1];
            $adj[$e['k1']][] = [$i, -1];
        }
        $turn = deg2rad(self::TURN_DEG);
        $startTol = deg2rad(self::START_DEG);

        // Fin de chemin sur le morceau $i parcouru dans le sens $dir, de l'abscisse $from : abscisse d'arrêt ou null.
        $stopOn = function (int $i, int $dir, float $from) use ($edges, $target, $startTol): ?float {
            $e = $edges[$i];
            if ($target['type'] === 'point') {
                [$s, $d, $az] = $this->project($e, $target['x'], $target['y']);
                if ($d > $target['snap'] || ($dir > 0 ? $s < $from - 1e-9 : $s > $from + 1e-9)) {
                    return null;
                }

                return $this->angDiff($dir > 0 ? $az : $az + M_PI, $target['az']) <= $startTol ? $s : null;
            }
            if ($target['type'] === 'subs') {
                if ($e['tsid'] === null) {
                    return null;
                }
                $s = $dir > 0 ? $e['fa'] : $e['fb'];

                return ($dir > 0 ? $s >= $from - 1e-9 : $s <= $from + 1e-9) ? $s : null;
            }

            return $e['other'] ? $from : null;
        };

        // États de départ : morceaux à moins de snap m de l'extrémité, dans le sens du cap sortant.
        $pq = new \SplPriorityQueue;
        $pq->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
        $dist = [];
        $prev = [];
        $seq = 0;
        foreach ($edges as $i => $e) {
            [$s, $d, $az] = $this->project($e, $start['x'], $start['y']);
            if ($d > $snapS) {
                continue;
            }
            foreach ([1, -1] as $dir) {
                if ($this->angDiff($dir > 0 ? $az : $az + M_PI, $start['az']) > $startTol) {
                    continue;
                }
                // Départ déjà sur la cible (voie d'une autre ligne, portion visée) : pas un prolongement.
                if ($target['type'] !== 'point' && ($e['other'] && $target['type'] === 'other' || $e['tsid'] !== null && $target['type'] === 'subs')) {
                    continue;
                }
                $stop = $stopOn($i, $dir, $s);
                if ($stop !== null) {
                    $c = abs($stop - $s);
                    if ($c <= $maxLen) {
                        $state = "$i:$dir:start";
                        $dist[$state] = $c;
                        $prev[$state] = ['from' => $s, 'to' => $stop];
                        $pq->insert([$state, $i, $dir, $s, $stop, true], [-$c, -$seq++]);
                    }

                    continue;
                }
                $c = $dir > 0 ? $e['len'] - $s : $s;
                if ($c > $maxLen) {
                    continue;
                }
                $state = "$i:$dir";
                if (! isset($dist[$state]) || $c < $dist[$state]) {
                    $dist[$state] = $c;
                    $prev[$state] = ['from' => $s, 'to' => $dir > 0 ? $e['len'] : 0.0];
                    $pq->insert([$state, $i, $dir, $s, $dir > 0 ? $e['len'] : 0.0, false], [-$c, -$seq++]);
                }
            }
        }

        $done = [];
        $n = 0;
        while (! $pq->isEmpty() && $n++ < self::MAX_EXPANSIONS) {
            [$state, $i, $dir, $from, $to, $final] = $pq->extract();
            if (isset($done[$state])) {
                continue;
            }
            $done[$state] = true;
            if ($final) {
                return $this->buildPath($edges, $prev, $state, $dist[$state]);
            }
            $e = $edges[$i];
            $node = $dir > 0 ? $e['k1'] : $e['k0'];
            $inAz = $dir > 0 ? $this->azEnd($e) : $this->azStart($e) + M_PI;
            $next = [];
            foreach ($adj[$node] ?? [] as [$j, $jdir]) {
                if ($j === $i) {
                    continue;
                }
                $f = $edges[$j];
                $dev = $this->angDiff($inAz, $jdir > 0 ? $this->azStart($f) : $this->azEnd($f) + M_PI);
                if ($dev <= $turn) {
                    $next[] = [$j, $jdir, $dev];
                }
            }
            // Tout droit : à un aiguillage, seulement la branche la moins déviée (± 1°).
            if ($straight && $next) {
                $min = min(array_column($next, 2));
                $next = array_filter($next, fn ($x) => $x[2] <= $min + deg2rad(1));
            }
            foreach ($next as [$j, $jdir]) {
                $f = $edges[$j];
                $from2 = $jdir > 0 ? 0.0 : $f['len'];
                $stop = $stopOn($j, $jdir, $from2);
                $base = $dist[$state];
                if ($stop !== null) {
                    $c = $base + abs($stop - $from2);
                    $s2 = "$j:$jdir:end";
                    if ($c <= $maxLen && (! isset($dist[$s2]) || $c < $dist[$s2])) {
                        $dist[$s2] = $c;
                        $prev[$s2] = ['state' => $state, 'from' => $from2, 'to' => $stop, 'edge' => $j, 'dir' => $jdir];
                        $pq->insert([$s2, $j, $jdir, $from2, $stop, true], [-$c, -$seq++]);
                    }

                    continue;
                }
                $c = $base + $f['len'];
                $s2 = "$j:$jdir";
                if ($c <= $maxLen && (! isset($dist[$s2]) || $c < $dist[$s2])) {
                    $dist[$s2] = $c;
                    $prev[$s2] = ['state' => $state, 'from' => $from2, 'to' => $jdir > 0 ? $f['len'] : 0.0, 'edge' => $j, 'dir' => $jdir];
                    $pq->insert([$s2, $j, $jdir, $from2, $jdir > 0 ? $f['len'] : 0.0, false], [-$c, -$seq++]);
                }
            }
        }

        return null;
    }

    private function buildPath(array $edges, array $prev, string $state, float $len): array
    {
        $legs = [];
        $cur = $state;
        while ($cur !== null) {
            [$i, $dir] = array_map('intval', array_slice(explode(':', $cur), 0, 2));
            $p = $prev[$cur];
            $legs[] = [$i, $dir, $p['from'], $p['to']];
            $cur = $p['state'] ?? null;
        }
        $coords = [];
        $ids = [];
        $lines = [];
        foreach (array_reverse($legs) as [$i, $dir, $from, $to]) {
            $e = $edges[$i];
            $piece = $this->substring($e, min($from, $to), max($from, $to));
            if ($dir < 0) {
                $piece = array_reverse($piece);
            }
            foreach ($piece as $pt) {
                $last = end($coords);
                if ($last === false || hypot($pt[0] - $last[0], $pt[1] - $last[1]) > 1e-9) {
                    $coords[] = $pt;
                }
            }
            // Provenance : morceaux réellement parcourus (pas l'entrée de longueur nulle sur la voie visée).
            if (abs($to - $from) >= 0.01) {
                $ids = array_merge($ids, $e['ids']);
                $lines = array_merge($lines, $e['lines']);
            }
        }

        return ['coords' => $coords, 'len' => $len, 'ids' => $ids, 'lines' => $lines, 'tsid' => $edges[$legs[0][0]]['tsid']];
    }

    // ─── Géométrie (Lambert-93, en PHP) ────────────────────────────────

    /**
     * Projection d'un point sur un morceau : abscisse, distance, cap local (sens du morceau).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private function project(array $e, float $x, float $y): array
    {
        $best = [0.0, INF, 0.0];
        $c = $e['coords'];
        for ($i = 1; $i < count($c); $i++) {
            [$ax, $ay] = $c[$i - 1];
            [$bx, $by] = $c[$i];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $l2 = $dx * $dx + $dy * $dy;
            $t = $l2 > 0 ? max(0.0, min(1.0, (($x - $ax) * $dx + ($y - $ay) * $dy) / $l2)) : 0.0;
            $d = hypot($ax + $t * $dx - $x, $ay + $t * $dy - $y);
            if ($d < $best[1] - 1e-9) {
                $best = [$e['cum'][$i - 1] + $t * sqrt($l2), $d, 0.0];
            }
        }
        $best[2] = $this->azAt($e, $best[0]);

        return $best;
    }

    /** Cap local (radians, depuis le nord, sens du morceau) autour de l'abscisse $s (± 8 m). */
    private function azAt(array $e, float $s): float
    {
        $a = $this->pointAt($e, max(0.0, $s - 8));
        $b = $this->pointAt($e, min($e['len'], $s + 8));

        return atan2($b[0] - $a[0], $b[1] - $a[1]);
    }

    private function azStart(array $e): float
    {
        $b = $this->pointAt($e, min($e['len'], 10));

        return atan2($b[0] - $e['coords'][0][0], $b[1] - $e['coords'][0][1]);
    }

    private function azEnd(array $e): float
    {
        $a = $this->pointAt($e, max(0.0, $e['len'] - 10));
        $z = end($e['coords']);

        return atan2($z[0] - $a[0], $z[1] - $a[1]);
    }

    private function pointAt(array $e, float $s): array
    {
        $c = $e['coords'];
        $cum = $e['cum'];
        for ($i = 1; $i < count($c); $i++) {
            if ($cum[$i] >= $s || $i === count($c) - 1) {
                $l = $cum[$i] - $cum[$i - 1];
                $t = $l > 0 ? max(0.0, min(1.0, ($s - $cum[$i - 1]) / $l)) : 0.0;

                return [$c[$i - 1][0] + $t * ($c[$i][0] - $c[$i - 1][0]), $c[$i - 1][1] + $t * ($c[$i][1] - $c[$i - 1][1])];
            }
        }

        return $c[0];
    }

    /** Sous-polyligne entre les abscisses $a <= $b (sens du morceau). */
    private function substring(array $e, float $a, float $b): array
    {
        $out = [$this->pointAt($e, $a)];
        for ($i = 1; $i < count($e['coords']) - 1; $i++) {
            if ($e['cum'][$i] > $a && $e['cum'][$i] < $b) {
                $out[] = $e['coords'][$i];
            }
        }
        $out[] = $this->pointAt($e, $b);

        return $out;
    }

    /** Écart absolu entre deux caps (radians), dans [0, π]. */
    private function angDiff(float $a, float $b): float
    {
        return abs(atan2(sin($a - $b), cos($a - $b)));
    }

    private function key(array $p): string
    {
        return sprintf('%.3f,%.3f', $p[0], $p[1]);
    }

    private function parseWkt(string $wkt): array
    {
        if (! preg_match('/^LINESTRING\s*\((.*)\)$/', $wkt, $m)) {
            return [];
        }

        return array_map(fn ($p) => array_map('floatval', preg_split('/\s+/', trim($p))), explode(',', $m[1]));
    }

    private function wkt(array $coords): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => sprintf('%.17G %.17G', $p[0], $p[1]), $coords)).')';
    }

    private function ints(array $v): string
    {
        return '{'.implode(',', array_map('intval', $v)).'}';
    }
}
