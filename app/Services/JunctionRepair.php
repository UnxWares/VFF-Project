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
 *   1 bis. Saut remis entre deux voies presque parallèles (caps à moins de
 *      JOG_PARALLEL_DEG) : la marche d'escalier devient un changement de voie
 *      en S, courbe lissée tangente aux deux voies (rayon plausible du réseau,
 *      reculs d'au plus JOG_TRIM_MAX_M de chaque côté, sans passer une autre
 *      jonction), marquée « raccord-lisse » (estimée). Une portion qui ne dépasse
 *      le saut que d'un bout sans issue de moins de JOG_STUB_MAX_M est coupée
 *      comme si elle s'y arrêtait (sinon ce bout double la courbe), même
 *      quand la courbe est refusée et que le saut reste un raccord droit.
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
 *           sont pris au plus près de la jonction, et aucune autre ligne ne
 *           finit sur la partie remplacée. La courbe est ESTIMÉE :
 *           matched_source = SMOOTHED (« raccord-lisse »), jamais présentée
 *           comme précise ; au-delà de J la ligne suit la portion précise
 *           (tronc commun masqué ensuite par vff:build-shared-track).
 *      Même courbe estimée, faute de voie précise (étapes 2 et 5 sans chemin) :
 *        c. bout d'un tracé imprécis qui converge vers la voie précise d'une
 *           AUTRE ligne et s'arrête à moins de END_GAP_M avant elle (cap vers
 *           elle, écartements et réseaux compatibles) : raccordé à cette voie ;
 *        d. trou de moins de HOLE_SMOOTH_MAX_M entre deux bouts de la ligne
 *           face à face (voie déposée, aucune voie précise) : comblé ; trou
 *           de moins de HOLE_MAX_M sur une voie précise que l'étape 2 n'a pas
 *           pu suivre : comblé si la courbe reste partout à moins de
 *           HOLE_TRACK_M de cette voie (rayon d'au moins la moitié du minimum).
 *   8. Allers-retours : le tracé dessiné ne revient jamais sur ses pas. Un
 *      éperon sans issue d'au plus SPUR_MAX_M qui repart en arrière (virage de
 *      plus de HAIRPIN_DEG au milieu d'une portion, ou départ à moins de
 *      SPUR_DEG d'une autre portion de la ligne au même nœud) et longe la ligne
 *      à moins de PARALLEL_M sur au moins SPUR_SHARE de sa longueur (autre voie
 *      du faisceau, voie de gare) est retiré, sauf si une autre portion de la
 *      ligne ou une autre ligne s'y raccorde. Un vrai rebroussement, dont les
 *      deux branches s'écartent, reste.
 *   9. Longs trous (de HOLE_MAX_M à LONG_GAP_MAX_M) d'une ligne disparue : depuis
 *      un bout libre, chemin sur les voies NON rattachées d'une voie abandonnée
 *      (OSM abandoned, razed, disused, dismantled ; BD TOPO « Sans objet ») vers
 *      l'autre bout ; un chemin qui s'arrête à moins de LONG_GAP_LEFT_M de lui
 *      est accepté et le reste du trou reste visible (rien n'est inventé). Refusé
 *      en cas de doute : détour, voie d'une autre ligne, voie en service à moins
 *      de LONG_GAP_LIVE_M sur la moitié du chemin, gares de la ligne dans
 *      le désordre ou manquées, longueur incompatible avec l'écart de PK, arrivée
 *      de travers sur le bout d'en face. Deux bouts d'un même morceau du tracé
 *      (branches d'un Y) ne bordent pas un trou.
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

    /** Trou sur une voie précise comblé par une courbe : écart max (m) de la courbe à cette voie. */
    public const HOLE_TRACK_M = 5.0;

    /** Saut entre deux voies presque parallèles (écart de cap max, °) et recul max de chaque côté pour la courbe en S (m). */
    public const JOG_PARALLEL_DEG = 30.0;

    public const JOG_TRIM_MAX_M = 150.0;

    /**
     * Saut posé à moins de JOG_STUB_MAX_M d'un bout sans issue d'une portion (elle ne dépasse le saut que de ce bout) :
     * la portion est traitée comme si elle s'arrêtait sur le saut (le bout serait doublé par la courbe en S).
     */
    public const JOG_STUB_MAX_M = 50.0;

    /** Éperon (aller-retour) : longueur max (m), virage d'un aller-retour (°), écart de cap au nœud (°), part qui longe la ligne. */
    public const SPUR_MAX_M = 600.0;

    public const HAIRPIN_DEG = 150.0;

    public const SPUR_DEG = 30.0;

    public const SPUR_SHARE = 0.8;

    /** Long trou (règle 9) : écart max entre les deux bouts (m) ; c'est aussi la longueur max du chemin. */
    public const LONG_GAP_MAX_M = 20000.0;

    /** Reste du trou laissé visible au bout d'un chemin incomplet : au plus LONG_GAP_LEFT_M et LONG_GAP_LEFT_SHARE de l'écart. */
    public const LONG_GAP_LEFT_M = 1000.0;

    public const LONG_GAP_LEFT_SHARE = 0.35;

    /**
     * Reste plus court (m) : le chemin est raccordé au bout d'en face. Le raccord (easeInto) est une corde droite
     * étiquetée comme la voie suivie : il ne s'en écarte pas de plus de la tolérance du projet (5 m). Avec 30 m, une
     * corde de 240 m passait à 30 m de toute voie réelle (revue du 21/11/2026).
     */
    public const LONG_GAP_CLOSE_M = 5.0;

    /**
     * Départ : la voie abandonnée passe à moins de LONG_GAP_SNAP_M (m) du bout, dans son prolongement (START_DEG). Même
     * tolérance que LONG_GAP_CLOSE_M pour la corde de départ.
     */
    public const LONG_GAP_SNAP_M = 5.0;

    /** Chemin admis : longueur + reste <= LONG_GAP_DETOUR × écart + LONG_GAP_DETOUR_ADD_M. */
    public const LONG_GAP_DETOUR = 1.6;

    public const LONG_GAP_DETOUR_ADD_M = 500.0;

    /** Voie d'une autre ligne : chemin refusé si plus de LONG_GAP_OTHER_SHARE de sa longueur est à moins de LONG_GAP_OTHER_M (m) de son tracé. */
    public const LONG_GAP_OTHER_M = 15.0;

    public const LONG_GAP_OTHER_SHARE = 0.3;

    /** Gare de la ligne desservie par le chemin (ou par le reste du trou) : à moins de LONG_GAP_STATION_M (m). */
    public const LONG_GAP_STATION_M = 500.0;

    /** PK : gare de PK sûr à moins de LONG_GAP_PK_NEAR_M (m) d'un bout ; tolérance LONG_GAP_PK_TOL × écart de PK, au moins LONG_GAP_PK_MIN_TOL_M. */
    public const LONG_GAP_PK_NEAR_M = 3000.0;

    public const LONG_GAP_PK_TOL = 0.2;

    public const LONG_GAP_PK_MIN_TOL_M = 1000.0;

    /**
     * Voie en service le long du chemin : refusé si plus de LONG_GAP_LIVE_SHARE de sa partie intérieure (hors
     * LONG_GAP_LIVE_ENDS_M à chaque bout) passe à moins de LONG_GAP_LIVE_M (m) d'une voie précise en service, rattachée
     * ou non (revue du 21/11/2026 : trou comblé par un ancien tracé à 200 m d'une voie BD TOPO en service qui le
     * traversait tout droit ; la ligne passait sans doute sur celle-ci).
     */
    public const LONG_GAP_LIVE_M = 250.0;

    public const LONG_GAP_LIVE_SHARE = 0.5;

    public const LONG_GAP_LIVE_ENDS_M = 300.0;

    /** Morceaux d'une voie abandonnée séparés par un manque court (m), dans le prolongement l'un de l'autre : enchaînés. */
    public const LONG_GAP_JUMP_M = 30.0;

    /** Voies d'une ligne disparue (couches OSM du cycle de vie ; BD TOPO : nature « Sans objet »). */
    public const DEAD_OSM_LAYERS = ['abandoned', 'razed', 'disused', 'dismantled', 'abandoned:railway', 'razed:railway', 'disused:railway',
        'dismantled:railway', 'demolished:railway', 'removed:railway', 'was:railway'];

    /** Sources de PK sûres (les PK projetés sur le dessin, faux dans un trou, sont ignorés). */
    public const RELIABLE_PK_SOURCES = ['sncf', 'lignes-oubliees', 'wikipedia_schema', 'correction'];

    /** Géométrie lissée estimée (matched_source). */
    public const SMOOTHED = 'raccord-lisse';

    /** Points D (en arrière de la jonction) et J (sur la portion précise) essayés (m). */
    private const LEG_D_STEPS = [20, 30, 45, 60, 80, 100, 130, 160, 200, 250, 300, 400];

    private const LEG_J_STEPS = [0, 10, 25, 50, 80, 120, 160, 200, 250, 300];

    private const MAX_EXPANSIONS = 20000;

    /** Chemin d'un long trou : états explorés au plus (fenêtre de 20 km de voies abandonnées). */
    private const LONG_GAP_MAX_EXPANSIONS = 80000;

    /** Paires de bouts examinées au plus par ligne (règle 9). */
    private const LONG_GAP_MAX_PAIRS = 40;

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
     * @return array<string, float|int> jog_removed, jog_restored, jog_smoothed (+ _m), hole_filled (+ _m), rerouted (+ _m), stub_trimmed_m,
     *                                  remnant_removed_m, extended (+ _m), overshoot_trimmed_m, leg_rerouted (+ _m), leg_smoothed (+ _m),
     *                                  end_joined (+ _m), hole_smoothed (+ _m), spur_removed (+ _m), long_gap_filled (+ _m), long_gap_partial,
     *                                  long_gap_left_m, long_gap_refused
     */
    public function repair(int $lineId): array
    {
        if (! $this->start($lineId)) {
            return [];
        }

        $stats = ['jog_removed' => 0, 'jog_restored' => 0, 'jog_smoothed' => 0, 'jog_smoothed_m' => 0.0, 'hole_filled' => 0, 'hole_filled_m' => 0.0,
            'rerouted' => 0, 'rerouted_m' => 0.0,
            'stub_trimmed_m' => 0.0, 'remnant_removed_m' => 0.0, 'extended' => 0, 'extended_m' => 0.0, 'overshoot_trimmed_m' => 0.0,
            'leg_rerouted' => 0, 'leg_rerouted_m' => 0.0, 'leg_smoothed' => 0, 'leg_smoothed_m' => 0.0,
            'end_joined' => 0, 'end_joined_m' => 0.0, 'hole_smoothed' => 0, 'hole_smoothed_m' => 0.0, 'spur_removed' => 0, 'spur_removed_m' => 0.0,
            'long_gap_filled' => 0, 'long_gap_filled_m' => 0.0, 'long_gap_partial' => 0, 'long_gap_left_m' => 0.0, 'long_gap_refused' => 0];

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
        $this->smoothJogs($jogs, $stats);
        $this->extendToOtherLines($stats);
        $this->trimOvershoots($stats);
        $this->smoothLegs($stats);
        $this->smoothEnds($stats);
        $this->removeSpurs($stats);
        $this->fillLongGaps($stats);

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

    /**
     * Règle 1 bis : saut remis (aucune voie réelle ne le remplace) entre deux portions presque parallèles : remplacé par
     * un changement de voie en S, du point D de la portion A (en arrière du saut) au point J de la portion B (au-delà),
     * tangent aux deux. Une portion qui s'arrête sur le saut est coupée à D (ou à J) ; une portion qui le dépasse (saut
     * posé en son milieu) reste entière, la courbe s'y raccorde. Le recul de chaque
     * côté est la moitié de la longueur qu'il faut pour décaler la ligne de l'écart y entre les deux voies au rayon
     * minimal du réseau (S de Hermite : rayon ≈ L² / 6y), sans dépasser JOG_TRIM_MAX_M ni la jonction d'une autre portion.
     * Un barreau entre deux voies qui continuent toutes les deux (aucune ne s'arrête sur le saut) reste tel quel.
     * Une portion qui ne dépasse le saut que d'un bout sans issue court (jogEnd) est coupée comme si elle s'y arrêtait :
     * sinon ce bout et la voie entre le saut et J doubleraient la courbe (tronçons en double, mesuré le 21/11 : 3283, 3786).
     * Une portion qui continue vraiment au-delà du saut reste entière : la courbe s'y raccorde en Y, et ses derniers
     * mètres longent cette voie (fusion de deux voies, comme un aiguillage).
     */
    private function smoothJogs(array $jogs, array &$stats): void
    {
        $net = DB::selectOne('SELECT network FROM lines WHERE id = ?', [$this->lineId])?->network;
        $rmin = self::minRadius($net, $this->gauge);
        foreach ($jogs as $j) {
            [$e0, $e1] = $j['ends'];
            $c = $this->subCoords($j['sid']);
            $a = $this->subCoords($e0['sid']);
            $b = $this->subCoords($e1['sid']);
            if (! $c || ! $a || ! $b) {
                continue;
            }
            $p = $c[0];
            $q = end($c);
            $la = $this->plen($a);
            $lb = $this->plen($b);
            if ($this->distToLine($a, $p) > 0.5 || $this->distToLine($b, $q) > 0.5 || $la < 20 || $lb < 20) {
                continue;
            }
            [$sA0] = $this->projectOn($a, $p, [0.0, 1.0]);
            [$sB0] = $this->projectOn($b, $q, [0.0, 1.0]);
            $endA = $this->jogEnd($e0, $a, $sA0, [$j['sid'], $e1['sid']]);
            $endB = $this->jogEnd($e1, $b, $sB0, [$j['sid'], $e0['sid']]);
            // Barreau entre deux voies qui continuent toutes les deux : pas un changement de voie de la ligne.
            if ($endA === null && $endB === null) {
                continue;
            }
            // Sens de marche f : celui d'une portion qui s'arrête sur le saut (vers lui pour A, depuis lui pour B).
            $f = $endA !== null
                ? ($endA['k'] === 1 ? $this->unit($this->pointAlong($a, $sA0 - 15), $this->pointAlong($a, $sA0)) : $this->unit($this->pointAlong($a, $sA0 + 15), $this->pointAlong($a, $sA0)))
                : ($endB['k'] === 0 ? $this->unit($this->pointAlong($b, $sB0), $this->pointAlong($b, $sB0 + 15)) : $this->unit($this->pointAlong($b, $sB0), $this->pointAlong($b, $sB0 - 15)));
            [$sA, $dA] = $this->projectOn($a, $p, $f);
            [$sB, $dB] = $this->projectOn($b, $q, $f);
            // Le bout coupé doit être en aval de A et en amont de B dans le sens de marche (sinon la coupe emporterait le reste).
            if (($endA !== null && ($endA['k'] === 1) !== ($dA > 0)) || ($endB !== null && ($endB['k'] === 0) !== ($dB > 0))) {
                $this->debug && $this->log[] = ['jog_smooth' => $j['sid'], 'skip' => 'sens'];

                continue;
            }
            // Courbe refusée plus bas (recul, autre ligne, rayon, doublon) : le saut reste un raccord droit, mais le bout
            // sans issue qui dépasse son pied est coupé quand même (sinon il longe l'autre voie et le nettoyage final en
            // fait un V en double : Strasbourg, ligne 523, 20 m).
            $trimStubs = function () use ($endA, $endB, $e0, $e1, $sA0, $sB0, $la, $lb, &$stats): void {
                foreach ([[$endA, $e0['sid'], $sA0, $la], [$endB, $e1['sid'], $sB0, $lb]] as [$end, $sid, $s0, $l]) {
                    if ($end !== null && $end['stub'] > 0) {
                        $end['k'] === 1 ? $this->cutSub($sid, 0.0, $s0 / $l) : $this->cutSub($sid, $s0 / $l, 1.0);
                        $stats['stub_trimmed_m'] += $end['stub'];
                    }
                }
            };
            $ga = $this->unit($this->pointAlong($a, $sA - 10 * $dA), $this->pointAlong($a, $sA));
            $gb = $this->unit($this->pointAlong($b, $sB), $this->pointAlong($b, $sB + 10 * $dB));
            if ($this->angDiff(atan2($ga[0], $ga[1]), atan2($gb[0], $gb[1])) > deg2rad(self::JOG_PARALLEL_DEG)) {
                $this->debug && $this->log[] = ['jog_smooth' => $j['sid'], 'skip' => 'cap'];

                continue;
            }
            $y = abs($f[0] * ($q[1] - $p[1]) - $f[1] * ($q[0] - $p[0]));
            $t = min(sqrt(6 * max($y, 0.5) * $rmin) / 2, self::JOG_TRIM_MAX_M,
                ($dA > 0 ? $sA : $la - $sA) - 5, ($dB > 0 ? $lb - $sB : $sB) - 5);
            // On ne recule pas au-delà d'une autre jonction sur la partie coupée (bout sans issue compris).
            if ($endA !== null) {
                $t = min($t, $this->nextJunction($e0['sid'], $dA > 0 ? $a : array_reverse($a), [$j['sid'], $e1['sid']]) - $endA['stub'] - 5);
            }
            if ($endB !== null) {
                $t = min($t, $this->nextJunction($e1['sid'], $dB > 0 ? array_reverse($b) : $b, [$j['sid'], $e0['sid']]) - $endB['stub'] - 5);
            }
            if ($t < 10) {
                $this->debug && $this->log[] = ['jog_smooth' => $j['sid'], 'skip' => 'recul', 't' => round($t)];
                $trimStubs();

                continue;
            }
            $sD = $sA - $dA * $t;
            $sJ = $sB + $dB * $t;
            // Partie coupée, recul compris (et pas seulement le bout qui dépasse le saut) : aucune autre ligne ne doit y
            // finir, sa jonction resterait en l'air. Le saut reste alors un raccord droit.
            $cutA = $endA === null ? null : ($dA > 0 ? $this->subLine($a, $sD + 1, $la) : $this->subLine($a, 0.0, $sD - 1));
            $cutB = $endB === null ? null : ($dB > 0 ? $this->subLine($b, 0.0, $sJ - 1) : $this->subLine($b, $sJ + 1, $lb));
            // Le raccord lui-même est retiré aussi : une autre ligne qui y finit resterait détachée (214 sur 438, 3,9 m
            // après le rejeu du 21/11/2026).
            if (($cutA !== null && $this->holdsOtherLine($cutA)) || ($cutB !== null && $this->holdsOtherLine($cutB)) || $this->holdsOtherLine($c)) {
                $this->debug && $this->log[] = ['jog_smooth' => $j['sid'], 'skip' => 'autre ligne sur la partie coupée'];
                $trimStubs();

                continue;
            }
            $curve = $this->hermite(
                $this->pointAlong($a, $sD), $this->unit($this->pointAlong($a, $sD - 5 * $dA), $this->pointAlong($a, $sD + 5 * $dA)),
                $this->pointAlong($b, $sJ), $this->unit($this->pointAlong($b, $sJ - 5 * $dB), $this->pointAlong($b, $sJ + 5 * $dB)));
            $segA = $this->subLine($a, min($sA, $sD), max($sA, $sD));
            $segB = $this->subLine($b, min($sB, $sJ), max($sB, $sJ));
            $dev = max(array_map(fn ($pt) => min($this->distToLine($segA, $pt), $this->distToLine($c, $pt), $this->distToLine($segB, $pt)), $curve));
            $this->debug && $this->log[] = ['jog_smooth' => $j['sid'], 'y' => round($y, 1), 't' => round($t), 'r' => round($this->minCurveRadius($curve)), 'dev' => round($dev, 1),
                'stub' => [$endA['stub'] ?? null, $endB['stub'] ?? null]];
            if ($this->minCurveRadius($curve) < 0.5 * $rmin || $dev > self::LEG_DEV_M) {
                $this->debug && $this->log[] = ['jog_smooth' => $j['sid'], 'skip' => 'rayon ou écart'];
                $trimStubs();

                continue;
            }
            $path = ['coords' => $curve, 'len' => $this->plen($curve)];
            if ($this->doubles($path, [$j['sid'], $e0['sid'], $e1['sid']])) {
                $this->debug && $this->log[] = ['jog_smooth' => $j['sid'], 'skip' => 'double la ligne'];
                $trimStubs();

                continue;
            }
            if ($endA !== null) {
                $dA > 0 ? $this->cutSub($e0['sid'], 0.0, $sD / $la) : $this->cutSub($e0['sid'], $sD / $la, 1.0);
            }
            if ($endB !== null) {
                $dB > 0 ? $this->cutSub($e1['sid'], $sJ / $lb, 1.0) : $this->cutSub($e1['sid'], 0.0, $sJ / $lb);
            }
            DB::update('UPDATE jr_sub SET removed = TRUE WHERE sid = ?', [$j['sid']]);
            $this->insertSmoothed($curve, $e0['sid']);
            $stats['jog_smoothed']++;
            $stats['jog_smoothed_m'] += $path['len'];
        }
    }

    /**
     * Distance (m) depuis le bout de $c (polyligne de la portion $sid, orientée vers ce bout) jusqu'au premier point où
     * une autre portion de la ligne (hors $exclude) la touche : on ne recule pas au-delà d'une jonction.
     */
    private function nextJunction(int $sid, array $c, array $exclude): float
    {
        $len = $this->plen($c);
        $best = INF;
        foreach (DB::select(<<<'SQL'
            SELECT ST_X(x.p) AS x, ST_Y(x.p) AS y
            FROM jr_sub t, jr_sub o, LATERAL (VALUES (ST_StartPoint(o.g)), (ST_EndPoint(o.g))) x(p)
            WHERE t.sid = :sid AND NOT o.removed AND o.sid <> :sid2 AND o.sid <> ALL(CAST(:ex AS int[])) AND ST_DWithin(t.g, x.p, 0.5)
            SQL, ['sid' => $sid, 'sid2' => $sid, 'ex' => $this->ints($exclude)]) as $r) {
            [$s] = $this->projectOn($c, [(float) $r->x, (float) $r->y], [0.0, 1.0]);
            $best = min($best, $len - $s);
        }

        return $best;
    }

    /**
     * Côté d'un saut (règle 1 bis) : extrémité k de la portion $e['sid'] (polyligne $c, pied du saut à l'abscisse $s0)
     * qui s'arrête sur le saut, et longueur du bout qui le dépasse (0 si l'extrémité est posée dessus). Une portion
     * qui passe le saut compte comme s'y arrêtant quand son bras le plus court est un bout sans issue d'au plus
     * JOG_STUB_MAX_M : extrémité libre, aucune autre portion de la ligne (hors $exclude) ni extrémité d'une autre
     * ligne posée dessus. Sinon null : la portion continue vraiment au-delà du saut.
     *
     * @param  array{sid: int, k: int, end: bool}  $e
     * @param  list<array{0: float, 1: float}>  $c
     * @param  list<int>  $exclude
     * @return array{k: int, stub: float}|null
     */
    private function jogEnd(array $e, array $c, float $s0, array $exclude): ?array
    {
        $len = $this->plen($c);
        if ($e['end']) {
            return ['k' => $s0 > $len / 2 ? 1 : 0, 'stub' => 0.0];
        }
        $k = $s0 <= $len - $s0 ? 0 : 1;
        $stub = $k === 0 ? $s0 : $len - $s0;
        if ($stub > self::JOG_STUB_MAX_M || ! $this->isFreeEnd($e['sid'], $k)
            || $this->nextJunction($e['sid'], $k === 1 ? $c : array_reverse($c), $exclude) < $stub + 1) {
            return null;
        }
        $arm = $k === 0 ? $this->subLine($c, 0.0, $s0) : $this->subLine($c, $s0, $len);

        return $this->holdsOtherLine($arm) ? null : ['k' => $k, 'stub' => $stub];
    }

    /** Une autre ligne finit-elle sur la polyligne (à 1 m près) ? La retirer laisserait sa jonction en l'air. */
    private function holdsOtherLine(array $c): bool
    {
        return (bool) DB::selectOne(<<<'SQL'
            WITH s AS (SELECT ST_GeomFromText(:wkt, 2154) AS g)
            SELECT 1 AS x FROM line_reference_parts o, LATERAL ST_Dump(o.geom) d, s
            WHERE o.line_id <> :line AND o.geom && ST_Transform(ST_Expand(s.g, 2), 4326)
              AND (ST_DWithin(ST_Transform(ST_StartPoint(d.geom), 2154), s.g, 1) OR ST_DWithin(ST_Transform(ST_EndPoint(d.geom), 2154), s.g, 1))
            LIMIT 1
            SQL, ['wkt' => $this->wkt($c), 'line' => $this->lineId]);
    }

    // ─── 8. Allers-retours ─────────────────────────────────────────────

    /**
     * Règle 8 (cf. docblock) : éperons qui font faire un aller-retour au tracé. Deux formes :
     *   a. virage de plus de HAIRPIN_DEG au milieu d'une portion : la branche la plus courte, sans issue, est retirée ;
     *   b. bout sans issue qui part d'un nœud (autre bout de la portion, ou raccord d'une autre portion en son milieu) à
     *      moins de SPUR_DEG d'une autre portion de la ligne : retiré jusqu'au nœud.
     * Dans les deux cas l'éperon doit longer le reste de la ligne (PARALLEL_M, SPUR_SHARE de sa longueur), et aucune
     * autre ligne ne doit finir dessus (sa jonction resterait en l'air). La branche de la forme a ne doit pas non plus
     * porter le raccord d'une autre portion de la ligne, qui resterait détachée (en b, l'éperon s'arrête au premier
     * raccord par construction).
     */
    private function removeSpurs(array &$stats): void
    {
        // a. Aller-retour à l'intérieur d'une portion.
        foreach (DB::select('SELECT sid, ST_AsText(g) AS wkt FROM jr_sub WHERE NOT removed AND ST_Length(g) > 30 ORDER BY sid') as $r) {
            $sid = (int) $r->sid;
            $c = $this->parseWkt($r->wkt);
            $s = $this->hairpin($c);
            if ($s === null) {
                continue;
            }
            $len = $this->plen($c);
            // Branches depuis le sommet du virage : [polyligne, longueur, bout libre au loin, bout de la portion].
            $legs = [
                [array_reverse($this->subLine($c, 0, $s)), $s, $this->isFreeEnd($sid, 0), 0],
                [$this->subLine($c, $s, $len), $len - $s, $this->isFreeEnd($sid, 1), 1],
            ];
            usort($legs, fn ($x, $y) => $x[1] <=> $y[1]);
            [$leg, $legLen, $free, $k] = $legs[0];
            if (! $free || $legLen > self::SPUR_MAX_M || $this->alongShare($leg, $sid, $legs[1][0]) < self::SPUR_SHARE
                || ($legLen > 2 && $this->holdsOtherLine($this->subLine($leg, 2.0, $legLen)))) {
                continue;
            }
            // Une autre portion de la ligne se raccorde sur la branche (hors sommet du virage) : la retirer la laisserait
            // détachée (même protection que pour les autres lignes) : la branche reste.
            if ($this->nextJunction($sid, $k === 1 ? $c : array_reverse($c), []) < $legLen - 1) {
                $this->debug && $this->log[] = ['spur_hairpin' => $sid, 'skip' => 'portion de la ligne sur la branche'];

                continue;
            }
            $k === 0 ? $this->cutSub($sid, $s / $len, 1.0) : $this->cutSub($sid, 0.0, $s / $len);
            $stats['spur_removed']++;
            $stats['spur_removed_m'] += $legLen;
            $this->debug && $this->log[] = ['spur_hairpin' => $sid, 'm' => round($legLen)];
        }

        // b. Bout sans issue qui repart d'un nœud (autre bout de la portion, ou point où une autre portion s'y raccorde)
        //    à moins de SPUR_DEG d'une autre portion de la ligne, et la longe.
        foreach ($this->freeEnds() as $e) {
            $full = $this->subCoords($e['sid']);
            if (! $full) {
                continue;
            }
            // Polyligne orientée vers le bout libre ; nœud le plus proche de ce bout.
            $towardFree = $e['k'] === 1 ? $full : array_reverse($full);
            $len = $this->plen($towardFree);
            $j = $this->nextJunction($e['sid'], $towardFree, []);
            if ($j < 1 || $j > self::SPUR_MAX_M) {
                continue;
            }
            $spur = $this->subLine($towardFree, $len - $j, $len);
            $n = $spur[0];
            $u = $this->unit($n, $this->pointAlong($spur, min(15.0, $j)));
            $parallel = false;
            foreach (DB::select(<<<'SQL'
                SELECT o.sid, ST_AsText(o.g) AS wkt FROM jr_sub o
                WHERE NOT o.removed AND o.sid <> :sid AND ST_DWithin(o.g, ST_SetSRID(ST_MakePoint(:x, :y), 2154), 0.5)
                SQL, ['sid' => $e['sid'], 'x' => $n[0], 'y' => $n[1]]) as $o) {
                $oc = $this->parseWkt($o->wkt);
                if (count($oc) < 2) {
                    continue;
                }
                [$pos] = $this->projectOn($oc, $n, [0.0, 1.0]);
                $olen = $this->plen($oc);
                // L'autre portion part du nœud dans un sens ou dans l'autre (nœud en son milieu : les deux).
                foreach ([min($olen, $pos + 15), max(0.0, $pos - 15)] as $to) {
                    $v = $this->unit($n, $this->pointAlong($oc, $to));
                    if (abs($to - $pos) > 1 && $this->angDiff(atan2($u[0], $u[1]), atan2($v[0], $v[1])) <= deg2rad(self::SPUR_DEG)) {
                        $parallel = true;
                    }
                }
            }
            if (! $parallel || $this->alongShare($spur, $e['sid']) < self::SPUR_SHARE || ($j > 2 && $this->holdsOtherLine($this->subLine($spur, 2.0, $j)))) {
                continue;
            }
            if ($j >= $len - 0.01) {
                DB::update('UPDATE jr_sub SET removed = TRUE WHERE sid = ?', [$e['sid']]);
            } else {
                // Bout libre au début de la portion : on garde la fin, et inversement.
                $e['k'] === 0 ? $this->cutSub($e['sid'], $j / $len, 1.0) : $this->cutSub($e['sid'], 0.0, 1 - $j / $len);
            }
            $stats['spur_removed']++;
            $stats['spur_removed_m'] += $j;
            $this->debug && $this->log[] = ['spur_node' => $e['sid'], 'm' => round($j)];
        }
    }

    /**
     * Abscisse (m) du sommet d'un aller-retour dans la polyligne : point où le tracé tourne de plus de HAIRPIN_DEG
     * entre les cordes des 10 m qui précèdent et des 10 m qui suivent (le plus fort), ou null.
     */
    private function hairpin(array $c): ?float
    {
        $len = $this->plen($c);
        $best = null;
        $max = deg2rad(self::HAIRPIN_DEG);
        $acc = 0.0;
        for ($i = 1; $i < count($c) - 1; $i++) {
            $acc += hypot($c[$i][0] - $c[$i - 1][0], $c[$i][1] - $c[$i - 1][1]);
            if ($acc < 10 || $len - $acc < 10) {
                continue;
            }
            $a = $this->pointAlong($c, $acc - 10);
            $b = $this->pointAlong($c, $acc + 10);
            $turn = $this->angDiff(atan2($c[$i][0] - $a[0], $c[$i][1] - $a[1]), atan2($b[0] - $c[$i][0], $b[1] - $c[$i][1]));
            if ($turn > $max) {
                $max = $turn;
                $best = $acc;
            }
        }

        return $best;
    }

    /**
     * Part de la polyligne $c (points tous les 5 m, hors 10 premiers mètres) à moins de PARALLEL_M des autres portions
     * de la ligne (hors $sid) ou de la polyligne $also.
     */
    private function alongShare(array $c, int $sid, array $also = []): float
    {
        $len = $this->plen($c);
        if ($len <= 10) {
            return 0.0;
        }
        $pts = [];
        for ($s = 10.0; $s <= $len; $s += 5.0) {
            $pts[] = $this->pointAlong($c, $s);
        }
        $near = array_fill(0, count($pts), false);
        if (count($also) >= 2) {
            foreach ($pts as $i => $p) {
                $near[$i] = $this->distToLine($also, $p) <= self::PARALLEL_M;
            }
        }
        $rows = DB::select(<<<'SQL'
            SELECT x.path[1] AS i
            FROM ST_DumpPoints(ST_GeomFromText(:mp, 2154)) x
            WHERE EXISTS (SELECT 1 FROM jr_sub o WHERE NOT o.removed AND o.sid <> :sid AND ST_DWithin(o.g, x.geom, :par))
            SQL, ['mp' => 'MULTIPOINT('.implode(',', array_map(fn ($p) => sprintf('(%.17G %.17G)', $p[0], $p[1]), $pts)).')',
            'sid' => $sid, 'par' => self::PARALLEL_M]);
        foreach ($rows as $r) {
            $near[(int) $r->i - 1] = true;
        }

        return count(array_filter($near)) / count($pts);
    }

    /** La polyligne (un point tous les 5 m) reste-t-elle partout à moins de HOLE_TRACK_M d'une voie précise du pool ? */
    private function onPreciseTrack(array $c): bool
    {
        $len = $this->plen($c);
        $pts = [];
        for ($s = 0.0; $s <= $len; $s += 5.0) {
            $pts[] = $this->pointAlong($c, $s);
        }
        $pts[] = end($c);

        return (int) DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS n
            FROM ST_DumpPoints(ST_GeomFromText(:mp, 2154)) x
            WHERE NOT EXISTS (SELECT 1 FROM cf_pool p WHERE p.cand AND p.family IN ('contrib', 'bdtopo', 'osm') AND ST_DWithin(p.g, x.geom, :tol))
            SQL, ['mp' => 'MULTIPOINT('.implode(',', array_map(fn ($p) => sprintf('(%.17G %.17G)', $p[0], $p[1]), $pts)).')',
            'tol' => self::HOLE_TRACK_M])->n === 0;
    }

    /** Polyligne d'une portion gardée (null si retirée ou vide). */
    private function subCoords(int $sid): ?array
    {
        $r = DB::selectOne('SELECT ST_AsText(g) AS wkt FROM jr_sub WHERE sid = ? AND NOT removed', [$sid]);
        $c = $r ? $this->parseWkt($r->wkt) : [];

        return count($c) >= 2 ? $c : null;
    }

    /** Vecteur unitaire de $from vers $to ([dx, dy]). */
    private function unit(array $from, array $to): array
    {
        $l = hypot($to[0] - $from[0], $to[1] - $from[1]);

        return $l > 0 ? [($to[0] - $from[0]) / $l, ($to[1] - $from[1]) / $l] : [0.0, 1.0];
    }

    // ─── 9. Longs trous sur les voies abandonnées ─────────────────────

    /**
     * Règle 9 (cf. docblock) : paires de bouts libres de la ligne (hors raccords) à plus de HOLE_MAX_M et au plus
     * LONG_GAP_MAX_M, de la plus proche à la plus lointaine, chaque bout servant une fois. Le chemin est cherché depuis
     * chaque bout d'où part une voie abandonnée ; le meilleur (plus petit reste) est gardé s'il passe tous les contrôles.
     */
    private function fillLongGaps(array &$stats): void
    {
        $ends = array_values(array_filter($this->freeEnds(), fn ($e) => ! $e['conn']));
        $pairs = [];
        foreach ($ends as $i => $a) {
            foreach ($ends as $j => $b) {
                $d = hypot($b['x'] - $a['x'], $b['y'] - $a['y']);
                if ($j > $i && $a['sid'] !== $b['sid'] && $d > self::HOLE_MAX_M && $d <= self::LONG_GAP_MAX_M) {
                    $pairs[] = [$d, $i, $j];
                }
            }
        }
        sort($pairs);
        $leaves = [];
        $stations = null;
        $used = [];
        $tried = 0;
        // Morceaux connexes du tracé (portions qui se touchent à 0,5 m près) : deux bouts d'un même morceau ne bordent
        // pas un trou (les deux branches d'un Y), les relier inventerait une boucle.
        $comp = [];
        if ($pairs !== []) {
            foreach (DB::select('SELECT sid, ST_ClusterDBSCAN(g, 0.5, 1) OVER () AS c FROM jr_sub WHERE NOT removed') as $r) {
                $comp[(int) $r->sid] = (int) $r->c;
            }
        }
        foreach ($pairs as [$d, $i, $j]) {
            if (isset($used[$i]) || isset($used[$j])) {
                continue;
            }
            $ci = $comp[$ends[$i]['sid']] ?? -1 - $i;
            $cj = $comp[$ends[$j]['sid']] ?? -1 - $j;
            if ($ci === $cj) {
                continue;
            }
            $leaves[$i] ??= $this->deadTrackLeaves($ends[$i]);
            $leaves[$j] ??= $this->deadTrackLeaves($ends[$j]);
            if (! $leaves[$i] && ! $leaves[$j]) {
                continue;
            }
            if ($tried++ >= self::LONG_GAP_MAX_PAIRS) {
                break;
            }
            $stations ??= $this->lineStations();
            $best = null;
            $why = [];
            foreach ([[$i, $j], [$j, $i]] as [$from, $to]) {
                if (! $leaves[$from]) {
                    continue;
                }
                $path = $this->longGapRoute($ends[$from], $ends[$to], $d);
                $reason = $path === null ? 'aucun chemin' : $this->longGapRefusal($path, $ends[$from], $ends[$to], $d, $stations);
                if ($reason !== null) {
                    $why[] = $reason;
                    $this->debug && $path && $this->log[] = ['long_gap_try' => $ends[$from]['sid'], 'why' => $reason, 'len' => round($path['len']),
                        'left' => round($path['left']), 'wkt' => $this->wkt($path['coords'])];
                } elseif ($best === null || $path['left'] < $best['left']) {
                    $best = $path + ['from' => $ends[$from]['sid']];
                }
            }
            $this->debug && $this->log[] = ['long_gap' => [$ends[$i]['sid'], $ends[$j]['sid']], 'd' => round($d),
                'ends' => [[round($ends[$i]['x']), round($ends[$i]['y'])], [round($ends[$j]['x']), round($ends[$j]['y'])]],
                'len' => $best ? round($best['len']) : null, 'left' => $best ? round($best['left']) : null, 'why' => $why,
                'wkt' => $best ? $this->wkt($best['coords']) : null];
            if ($best === null) {
                $stats['long_gap_refused']++;

                continue;
            }
            $this->insertLongGap($best);
            $used[$i] = $used[$j] = true;
            // Desserte des gares à recalculer : le chemin ajouté en dessert peut-être de nouvelles.
            $stations = null;
            // Trou fermé : les deux morceaux n'en font plus qu'un.
            if ($best['left'] <= 0) {
                $comp = array_map(fn (int $c): int => $c === $cj ? $ci : $c, $comp);
            }
            $stats['long_gap_filled']++;
            $stats['long_gap_filled_m'] += $best['len'];
            if ($best['left'] > 0) {
                $stats['long_gap_partial']++;
                $stats['long_gap_left_m'] += $best['left'];
            }
        }
    }

    /** Filtre SQL des voies d'une ligne disparue (cf_pool p, source_traces st). */
    private function deadFilter(): string
    {
        return "((p.family = 'osm' AND st.layer IN ('".implode("', '", self::DEAD_OSM_LAYERS)."')) OR (p.family = 'bdtopo' AND st.layer = 'Sans objet'))";
    }

    /** Une voie abandonnée non rattachée passe-t-elle au bout $e et le prolonge-t-elle (encore là 30 m plus loin) ? */
    private function deadTrackLeaves(array $e): bool
    {
        $dead = $this->deadFilter();

        return (bool) DB::selectOne(<<<SQL
            WITH e AS (SELECT ST_SetSRID(ST_MakePoint(:x, :y), 2154) AS p, ST_SetSRID(ST_MakePoint(:qx, :qy), 2154) AS q)
            SELECT 1 AS x FROM e
            WHERE EXISTS (SELECT 1 FROM cf_pool p JOIN source_traces st ON st.id = p.id WHERE p.cand AND p.line_id IS NULL AND {$dead} AND ST_DWithin(p.g, e.p, :snap))
              AND EXISTS (SELECT 1 FROM cf_pool p JOIN source_traces st ON st.id = p.id WHERE p.cand AND p.line_id IS NULL AND {$dead} AND ST_DWithin(p.g, e.q, :snap2))
            SQL, ['x' => $e['x'], 'y' => $e['y'], 'qx' => $e['x'] + 30 * sin($e['az']), 'qy' => $e['y'] + 30 * cos($e['az']),
            'snap' => self::LONG_GAP_SNAP_M, 'snap2' => 2 * self::LONG_GAP_SNAP_M]);
    }

    /**
     * Gares de la ligne : position, PK sûr (sources RELIABLE_PK_SOURCES, sinon null) et desserte par le tracé actuel
     * (à moins de 300 m d'une portion gardée).
     *
     * @return list<array{x: float, y: float, pk: ?float, drawn: bool}>
     */
    private function lineStations(): array
    {
        return array_map(fn ($r) => ['x' => (float) $r->x, 'y' => (float) $r->y, 'pk' => $r->pk !== null ? (float) $r->pk : null, 'drawn' => (bool) $r->drawn],
            DB::select(<<<'SQL'
                SELECT ST_X(q.g) AS x, ST_Y(q.g) AS y, CASE WHEN sl.pk_source = ANY(CAST(:src AS text[])) THEN sl.pk_m END AS pk,
                       EXISTS (SELECT 1 FROM jr_sub t WHERE NOT t.removed AND ST_DWithin(t.g, q.g, 300)) AS drawn
                FROM station_lines sl JOIN stations s ON s.id = sl.station_id, LATERAL (SELECT ST_Transform(s.geom, 2154) AS g) q
                WHERE sl.line_id = :line
                ORDER BY sl.id
                SQL, ['src' => '{'.implode(',', self::RELIABLE_PK_SOURCES).'}', 'line' => $this->lineId]));
    }

    /**
     * Chemin le plus court (Dijkstra, déviation <= TURN_DEG à chaque nœud) depuis le bout $e sur les voies abandonnées
     * non rattachées d'un couloir autour du trou, jusqu'au point le plus proche du bout $f ; null sans voie au départ.
     *
     * @return array{coords: list<array{0: float, 1: float}>, len: float, left: float, family: string, ids: list<int>, endAz: float}|null
     */
    private function longGapRoute(array $e, array $f, float $d): ?array
    {
        $edges = $this->deadGraph(sprintf('LINESTRING(%.3F %.3F,%.3F %.3F)', $e['x'], $e['y'], $f['x'], $f['y']), max(1000.0, 0.35 * $d));
        $edges = array_merge($edges, $this->jumpEdges($edges));
        $adj = [];
        foreach ($edges as $i => $ed) {
            $adj[$ed['k0']][] = [$i, 1];
            $adj[$ed['k1']][] = [$i, -1];
        }
        $maxLen = min(self::LONG_GAP_MAX_M, self::LONG_GAP_DETOUR * $d + self::LONG_GAP_DETOUR_ADD_M);
        $turn = deg2rad(self::TURN_DEG);
        $pq = new \SplPriorityQueue;
        $pq->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
        $dist = [];
        $prev = [];
        $seq = 0;
        foreach ($edges as $i => $ed) {
            [$s, $dd, $az] = $this->project($ed, $e['x'], $e['y']);
            foreach ([1, -1] as $dir) {
                $c = $dir > 0 ? $ed['len'] - $s : $s;
                if ($dd > self::LONG_GAP_SNAP_M || $c < 1 || $this->angDiff($dir > 0 ? $az : $az + M_PI, $e['az']) > deg2rad(self::START_DEG)) {
                    continue;
                }
                $state = "$i:$dir";
                if (! isset($dist[$state]) || $c < $dist[$state]) {
                    $dist[$state] = $c;
                    $prev[$state] = ['from' => $s, 'to' => $dir > 0 ? $ed['len'] : 0.0];
                    $pq->insert($state, [-$c, -$seq++]);
                }
            }
        }
        // Meilleur arrêt : [reste jusqu'à $f, longueur, état, abscisse d'arrêt].
        $best = null;
        $done = [];
        $n = 0;
        while (! $pq->isEmpty() && $n++ < self::LONG_GAP_MAX_EXPANSIONS) {
            $state = $pq->extract();
            if (isset($done[$state])) {
                continue;
            }
            $done[$state] = true;
            [$i, $dir] = array_map('intval', explode(':', $state));
            $ed = $edges[$i];
            ['from' => $from, 'to' => $to] = $prev[$state];
            [$sF] = $this->project($ed, $f['x'], $f['y']);
            $stop = max(min($from, $to), min(max($from, $to), $sF));
            $pt = $this->pointAt($ed, $stop);
            $left = hypot($pt[0] - $f['x'], $pt[1] - $f['y']);
            $cost = $dist[$state] - abs($to - $stop);
            if ($best === null || $left < $best[0] - 1e-6 || (abs($left - $best[0]) <= 1e-6 && $cost < $best[1])) {
                $best = [$left, $cost, $state, $stop];
            }
            if ($left <= self::LONG_GAP_CLOSE_M) {
                break;
            }
            $inAz = $dir > 0 ? $this->azEnd($ed) : $this->azStart($ed) + M_PI;
            foreach ($adj[$dir > 0 ? $ed['k1'] : $ed['k0']] ?? [] as [$j, $jdir]) {
                $g = $edges[$j];
                $c = $dist[$state] + $g['len'];
                $s2 = "$j:$jdir";
                if ($j === $i || $c > $maxLen || (isset($dist[$s2]) && $c >= $dist[$s2])
                    || $this->angDiff($inAz, $jdir > 0 ? $this->azStart($g) : $this->azEnd($g) + M_PI) > $turn) {
                    continue;
                }
                $dist[$s2] = $c;
                $prev[$s2] = ['state' => $state, 'from' => $jdir > 0 ? 0.0 : $g['len'], 'to' => $jdir > 0 ? $g['len'] : 0.0];
                $pq->insert($s2, [-$c, -$seq++]);
            }
        }
        if ($best === null) {
            return null;
        }
        [$left, $cost, $state, $stop] = $best;
        $prev[$state.':end'] = ['from' => $prev[$state]['from'], 'to' => $stop] + (isset($prev[$state]['state']) ? ['state' => $prev[$state]['state']] : []);
        $path = $this->buildPath($edges, $prev, $state.':end', $cost);
        // Famille du dessin : celle qui porte la plus grande longueur (les manques enjambés ne comptent pas).
        $byFamily = [];
        for ($cur = $state.':end'; $cur !== null; $cur = $prev[$cur]['state'] ?? null) {
            $leg = $edges[(int) explode(':', $cur)[0]];
            $byFamily[$leg['family']] = ($byFamily[$leg['family']] ?? 0) + abs($prev[$cur]['to'] - $prev[$cur]['from']);
        }
        unset($byFamily['jump']);
        arsort($byFamily);
        $coords = $this->easeInto($path['coords'], [$e['x'], $e['y']]);
        if ($left <= self::LONG_GAP_CLOSE_M) {
            $coords = array_reverse($this->easeInto(array_reverse($coords), [$f['x'], $f['y']]));
            $left = 0.0;
        }
        if (count($coords) < 2 || ! $byFamily) {
            return null;
        }
        $last = end($coords);
        $before = $this->pointAlong($coords, max(0.0, $this->plen($coords) - 15));
        $ids = array_values(array_unique($path['ids']));
        sort($ids);

        return ['coords' => $coords, 'len' => $this->plen($coords), 'left' => $left, 'family' => (string) array_key_first($byFamily), 'ids' => $ids,
            'endAz' => atan2($last[0] - $before[0], $last[1] - $before[1])];
    }

    /**
     * Raccord du point $p au début du chemin $c (posé à quelques mètres de lui) : en biais doux, vers un point du chemin
     * pris assez loin (8 fois l'écart, au moins 30 m, au plus un tiers du chemin) pour qu'il n'y ait pas de coude.
     *
     * @param  list<array{0: float, 1: float}>  $c
     * @return list<array{0: float, 1: float}>
     */
    private function easeInto(array $c, array $p): array
    {
        $off = hypot($c[0][0] - $p[0], $c[0][1] - $p[1]);
        if ($off <= 1e-6) {
            return $c;
        }
        $len = $this->plen($c);
        $s = $off > 2 ? min($len / 3, max(30.0, 8 * $off)) : 0.0;

        return array_merge([$p], $s > 0 ? $this->subLine($c, $s, $len) : $c);
    }

    /**
     * Morceaux de voie abandonnée non rattachée dans un couloir de demi-largeur $w autour de $segWkt, coupés à leurs
     * intersections (une famille à la fois), au format de graph().
     *
     * @return list<array<string, mixed>>
     */
    private function deadGraph(string $segWkt, float $w): array
    {
        $dead = $this->deadFilter();
        $rows = DB::select(<<<SQL
            WITH w AS (SELECT ST_Buffer(ST_GeomFromText(:seg, 2154), :w, 8) AS w),
            e AS (
                SELECT p.id, p.family, d.geom AS g
                FROM cf_pool p JOIN source_traces st ON st.id = p.id, w, LATERAL ST_Dump(ST_CollectionExtract(ST_Intersection(p.g, w.w), 2)) d
                WHERE p.cand AND p.line_id IS NULL AND p.g && w.w AND ST_Intersects(p.g, w.w) AND {$dead} AND ST_Length(d.geom) > 0.05
            ),
            n AS (SELECT family, (ST_Dump(ST_Node(ST_Collect(g ORDER BY id)))).geom AS g FROM e GROUP BY family),
            n2 AS (SELECT row_number() OVER (ORDER BY family, ST_XMin(g), ST_YMin(g), ST_XMax(g), ST_YMax(g), ST_Length(g)) AS i, family, g FROM n WHERE ST_Length(g) > 0.05)
            SELECT n2.family, ST_AsText(n2.g) AS wkt,
                   (SELECT jsonb_agg(DISTINCT e.id) FROM e WHERE e.family = n2.family AND ST_DWithin(e.g, ST_LineInterpolatePoint(n2.g, 0.5), 0.01)) AS orig
            FROM n2 ORDER BY n2.i
            SQL, ['seg' => $segWkt, 'w' => $w]);
        $edges = [];
        foreach ($rows as $r) {
            $edge = $this->edge($this->parseWkt($r->wkt), $r->family, array_map('intval', json_decode($r->orig ?? '[]', true) ?: []));
            if ($edge !== null) {
                $edges[] = $edge;
            }
        }

        return $edges;
    }

    /**
     * Morceau du graphe des voies (format de graph() : polyligne, abscisses cumulées, clés des nœuds).
     *
     * @param  list<array{0: float, 1: float}>  $coords
     * @param  list<int>  $ids
     * @return array<string, mixed>|null
     */
    private function edge(array $coords, string $family, array $ids): ?array
    {
        if (count($coords) < 2) {
            return null;
        }
        $cum = [0.0];
        for ($i = 1; $i < count($coords); $i++) {
            $cum[] = $cum[$i - 1] + hypot($coords[$i][0] - $coords[$i - 1][0], $coords[$i][1] - $coords[$i - 1][1]);
        }

        return ['family' => $family, 'coords' => $coords, 'cum' => $cum, 'len' => end($cum), 'k0' => $this->key($coords[0]), 'k1' => $this->key(end($coords)),
            'ids' => $ids, 'lines' => [], 'own' => false, 'other' => false, 'tsid' => null, 'fa' => 0.0, 'fb' => 0.0];
    }

    /**
     * Manques courts d'une voie abandonnée (morceau rasé non dessiné, passage d'une famille de sources à l'autre) :
     * deux bouts pendants à moins de LONG_GAP_JUMP_M, dans le prolongement l'un de l'autre (TURN_DEG), reliés tout droit.
     *
     * @param  list<array<string, mixed>>  $edges
     * @return list<array<string, mixed>>
     */
    private function jumpEdges(array $edges): array
    {
        $deg = [];
        foreach ($edges as $ed) {
            $deg[$ed['k0']] = ($deg[$ed['k0']] ?? 0) + 1;
            $deg[$ed['k1']] = ($deg[$ed['k1']] ?? 0) + 1;
        }
        // Bouts pendants : point et cap sortant (au-delà du bout).
        $dangling = [];
        $grid = [];
        foreach ($edges as $ed) {
            foreach ([[$ed['k0'], $ed['coords'][0], $this->azStart($ed) + M_PI], [$ed['k1'], end($ed['coords']), $this->azEnd($ed)]] as [$k, $p, $az]) {
                if ($deg[$k] === 1) {
                    $n = count($dangling);
                    $dangling[] = [$p, $az];
                    $grid[(int) floor($p[0] / self::LONG_GAP_JUMP_M).':'.(int) floor($p[1] / self::LONG_GAP_JUMP_M)][] = $n;
                }
            }
        }
        $turn = deg2rad(self::TURN_DEG);
        $out = [];
        foreach ($dangling as $a => [$p, $az]) {
            $gx = (int) floor($p[0] / self::LONG_GAP_JUMP_M);
            $gy = (int) floor($p[1] / self::LONG_GAP_JUMP_M);
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    foreach ($grid[($gx + $dx).':'.($gy + $dy)] ?? [] as $b) {
                        [$q, $bz] = $dangling[$b];
                        $l = hypot($q[0] - $p[0], $q[1] - $p[1]);
                        if ($b <= $a || $l < 0.05 || $l > self::LONG_GAP_JUMP_M) {
                            continue;
                        }
                        $v = atan2($q[0] - $p[0], $q[1] - $p[1]);
                        // Le manque prolonge le morceau de $p et entre dans celui de $q (cap sortant de $q opposé au manque).
                        if ($this->angDiff($v, $az) <= $turn && $this->angDiff($v, $bz + M_PI) <= $turn && ($edge = $this->edge([$p, $q], 'jump', [])) !== null) {
                            $out[] = $edge;
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Contrôles d'un chemin de long trou (règle 9) ; raison du refus, ou null s'il est accepté.
     *
     * @param  array{coords: list<array{0: float, 1: float}>, len: float, left: float, family: string, ids: list<int>, endAz: float}  $p
     * @param  list<array{x: float, y: float, pk: ?float, drawn: bool}>  $stations
     */
    private function longGapRefusal(array $p, array $e, array $f, float $d, array $stations): ?string
    {
        if ($p['left'] > min(self::LONG_GAP_LEFT_M, self::LONG_GAP_LEFT_SHARE * $d)) {
            return sprintf('reste de %d m', round($p['left']));
        }
        if (self::LONG_GAP_DETOUR * $d + self::LONG_GAP_DETOUR_ADD_M < $p['len'] + $p['left']) {
            return 'détour';
        }
        $end = end($p['coords']);
        if ($p['left'] > 0) {
            // Le reste du trou prolonge le chemin et arrive de face sur le bout d'en face.
            $g = atan2($f['x'] - $end[0], $f['y'] - $end[1]);
            if ($this->angDiff($g, $f['az'] + M_PI) > deg2rad(60) || $this->angDiff($g, $p['endAz']) > deg2rad(60)) {
                return 'reste de travers';
            }
        } elseif ($this->angDiff($p['endAz'], $f['az'] + M_PI) > deg2rad(self::START_DEG)) {
            // Chemin raccordé : il arrive sur le bout d'en face dans son prolongement (START_DEG, comme la règle 2) ;
            // aucune règle ne lisse ensuite un coude à cet endroit.
            return 'arrivée de travers';
        }
        // Hors de ses 50 premiers et derniers mètres (raccords aux bouts), le chemin ne longe aucune portion de la ligne,
        // pas même celles des deux bouts (un chemin qui revient le long de la portion d'en face la doublerait).
        $inner = $this->subLine($p['coords'], 50.0, max(50.0, $p['len'] - 50));
        if ($p['len'] > 150 && $this->doubles(['coords' => $inner, 'len' => $this->plen($inner)], [])) {
            return 'double la ligne';
        }
        if ($this->liveTrackShare($p['coords']) > self::LONG_GAP_LIVE_SHARE) {
            return 'longe une voie en service';
        }
        $onOther = $this->otherLineShare($p['coords']) > self::LONG_GAP_OTHER_SHARE;

        // Gares : abscisse le long du chemin prolongé du reste du trou ; gares à LONG_GAP_STATION_M près.
        $full = $p['left'] > 0 ? array_merge($p['coords'], [[$f['x'], $f['y']]]) : $p['coords'];
        $pkE = $this->pkNear($e, $stations);
        $pkF = $this->pkNear($f, $stations);
        $sign = $pkE !== null && $pkF !== null && abs($pkF[0] - $pkE[0]) > 1 ? ($pkF[0] > $pkE[0] ? 1 : -1) : 0;
        if ($sign !== 0) {
            $expected = abs($pkF[0] - $pkE[0]);
            $tol = max(self::LONG_GAP_PK_TOL * $expected, self::LONG_GAP_PK_MIN_TOL_M) + $pkE[1] + $pkF[1];
            if (abs($p['len'] + $p['left'] - $expected) > $tol) {
                return sprintf('écart de PK (%d m pour %d m)', round($p['len'] + $p['left']), round($expected));
            }
        }
        $near = [];
        $newlyServed = 0;
        foreach ($stations as $s) {
            $pt = [$s['x'], $s['y']];
            $dist = $this->distToLine($full, $pt);
            [$t, $off] = $this->alongSegment([$e['x'], $e['y']], [$f['x'], $f['y']], $pt);
            if ($dist <= self::LONG_GAP_STATION_M) {
                // Gare du trou que le tracé actuel ne dessert pas et que le chemin dessert : la ligne passait bien là.
                $newlyServed += ! $s['drawn'] && $t > 0.05 && $t < 0.95 && $this->distToLine($p['coords'], $pt) <= self::LONG_GAP_STATION_M ? 1 : 0;
                if ($s['pk'] === null) {
                    continue;
                }
                // Gare de PK sûr desservie par le chemin : son PK doit tomber entre ceux des deux bouts.
                if ($sign !== 0 && ($s['pk'] < min($pkE[0], $pkF[0]) - $pkE[1] - $pkF[1] - self::LONG_GAP_PK_MIN_TOL_M
                    || $s['pk'] > max($pkE[0], $pkF[0]) + $pkE[1] + $pkF[1] + self::LONG_GAP_PK_MIN_TOL_M)) {
                    return 'gare hors de l\'écart de PK';
                }
                [$abs] = $this->projectOn($full, $pt, [0.0, 1.0]);
                $near[] = [$abs, $s['pk']];

                continue;
            }
            // Gare de la ligne que le tracé actuel ne dessert pas, dans le trou (entre les deux bouts) : le chemin doit y passer.
            if (! $s['drawn'] && $t > 0.05 && $t < 0.95 && $off <= min(2000.0, 0.35 * $d)) {
                return 'gare manquée';
            }
            // Gare de PK sûr strictement entre les PK des deux bouts : le chemin doit y passer.
            $margin = $sign !== 0 ? $pkE[1] + $pkF[1] + 500 : 0.0;
            if ($sign !== 0 && $s['pk'] !== null && $s['pk'] > min($pkE[0], $pkF[0]) + $margin && $s['pk'] < max($pkE[0], $pkF[0]) - $margin) {
                return 'gare manquée (PK)';
            }
        }
        // Ordre des gares desservies : leurs PK vont dans un seul sens le long du chemin (celui des bouts s'il est connu).
        usort($near, fn ($a, $b) => $a[0] <=> $b[0]);
        $up = $down = 0;
        for ($k = 1; $k < count($near); $k++) {
            $step = $near[$k][1] - $near[$k - 1][1];
            $up += $step > 300 ? 1 : 0;
            $down += $step < -300 ? 1 : 0;
        }
        if (($up > 0 && $down > 0) || ($sign > 0 && $down > 0) || ($sign < 0 && $up > 0)) {
            return 'gares dans le désordre';
        }
        // Voie déjà dessinée pour une autre ligne : ses rails, sauf si le chemin dessert une gare de la ligne restée dans
        // le trou (section commune : 371000 et 3225 entre Gravigny et Amfreville).
        if ($onOther && $newlyServed === 0) {
            return 'voie d\'une autre ligne';
        }

        return null;
    }

    /**
     * PK au bout $e : celui de la gare de PK sûr la plus proche, à LONG_GAP_PK_NEAR_M près ; [PK, distance] ou null.
     *
     * @param  list<array{x: float, y: float, pk: ?float, drawn: bool}>  $stations
     * @return array{0: float, 1: float}|null
     */
    private function pkNear(array $e, array $stations): ?array
    {
        $best = null;
        foreach ($stations as $s) {
            $dist = hypot($s['x'] - $e['x'], $s['y'] - $e['y']);
            if ($s['pk'] !== null && $dist <= self::LONG_GAP_PK_NEAR_M && ($best === null || $dist < $best[1])) {
                $best = [$s['pk'], $dist];
            }
        }

        return $best;
    }

    /**
     * Position de $p par rapport au segment $a → $b : fraction de sa projection (non bornée) et distance au segment.
     *
     * @return array{0: float, 1: float}
     */
    private function alongSegment(array $a, array $b, array $p): array
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $l2 = $dx * $dx + $dy * $dy;
        $t = $l2 > 0 ? (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $l2 : 0.0;

        return [$t, $this->distToLine([$a, $b], $p)];
    }

    /**
     * Part de la polyligne (un point tous les 25 m, hors LONG_GAP_LIVE_ENDS_M à chaque bout, où la ligne rejoint souvent
     * une voie en service) à moins de LONG_GAP_LIVE_M d'une voie précise du pool qui n'est pas une voie abandonnée.
     */
    private function liveTrackShare(array $c): float
    {
        $len = $this->plen($c);
        $pts = [];
        for ($s = self::LONG_GAP_LIVE_ENDS_M; $s <= $len - self::LONG_GAP_LIVE_ENDS_M; $s += 25.0) {
            $pts[] = $this->pointAlong($c, $s);
        }
        if ($pts === []) {
            return 0.0;
        }
        $dead = $this->deadFilter();
        $n = (int) DB::selectOne(<<<SQL
            SELECT COUNT(*) AS n FROM ST_DumpPoints(ST_GeomFromText(:mp, 2154)) x
            WHERE EXISTS (SELECT 1 FROM cf_pool p JOIN source_traces st ON st.id = p.id
                          WHERE p.cand AND p.family IN ('contrib', 'bdtopo', 'osm') AND ST_DWithin(p.g, x.geom, :live) AND NOT {$dead})
            SQL, ['mp' => 'MULTIPOINT('.implode(',', array_map(fn ($p) => sprintf('(%.17G %.17G)', $p[0], $p[1]), $pts)).')',
            'live' => self::LONG_GAP_LIVE_M])->n;

        return $n / count($pts);
    }

    /** Part de la polyligne à moins de LONG_GAP_OTHER_M du tracé de référence d'une autre ligne. */
    private function otherLineShare(array $c): float
    {
        return (float) DB::selectOne(<<<'SQL'
            WITH p AS (SELECT ST_GeomFromText(:wkt, 2154) AS p)
            SELECT COALESCE(ST_Length(ST_Intersection(p.p, ST_Union(ST_Buffer(ST_Transform(d.geom, 2154), :m)))) / NULLIF(ST_Length(p.p), 0), 0) AS s
            FROM p, line_reference_parts o, LATERAL ST_Dump(o.geom) d
            WHERE o.line_id <> :line AND o.geom && ST_Transform(ST_Expand(p.p, :m2), 4326) AND ST_DWithin(ST_Transform(d.geom, 2154), p.p, :m3)
            GROUP BY p.p
            SQL, ['wkt' => $this->wkt($c), 'm' => self::LONG_GAP_OTHER_M, 'm2' => self::LONG_GAP_OTHER_M, 'm3' => self::LONG_GAP_OTHER_M, 'line' => $this->lineId])?->s;
    }

    /**
     * Ajoute le chemin d'un long trou : dessin de la voie abandonnée (matched_source = sa famille, ids de ses tracés),
     * définition de la portion qu'il prolonge (source et ids de celle-ci).
     */
    private function insertLongGap(array $p): void
    {
        $from = DB::selectOne('SELECT p.source, p.source_trace_ids FROM jr_sub t JOIN line_reference_parts p ON p.id = t.part_id WHERE t.sid = ?', [$p['from']])
            ?? DB::selectOne('SELECT added FROM jr_sub WHERE sid = ?', [$p['from']]);
        if ($from && isset($from->added)) {
            $m = json_decode($from->added, true);
            $from = (object) ['source' => $m['source'], 'source_trace_ids' => json_encode($m['source_ids'])];
        }
        $meta = ['source' => $from->source ?? $p['family'], 'source_ids' => $from ? json_decode($from->source_trace_ids, true) : [],
            'matched' => $p['family'], 'matched_ids' => $p['ids']];
        DB::insert('INSERT INTO jr_sub (part_id, sub, conn, rank, added, g) VALUES (NULL, 0, FALSE, 3, ?, ST_GeomFromText(?, 2154))',
            [json_encode($meta), $this->wkt($p['coords'])]);
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
                // Partie remplacée (D -> X -> J) : aucune autre ligne ne doit y finir, sa jonction resterait en l'air (438 au
                // bout de 214, 3,9 m après le rejeu du 21/11/2026). L'arrivée reste alors telle quelle.
                if (($best['ld'] > 0 && $this->holdsOtherLine($this->subLine($s, max(0.0, $slen - $best['ld']), $slen)))
                    || ($best['lj'] > 0 && $this->holdsOtherLine($this->subLine($qp, 0.0, min($qlen, $best['lj']))))) {
                    $this->debug && $this->log[] = ['leg' => $qsid, 'skip' => 'autre ligne sur la partie remplacée'];

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

    /** Règle 7 c et d : bouts imprécis arrêtés avant la voie d'une autre ligne, trous que la voie précise ne permet pas de combler. */
    private function smoothEnds(array &$stats): void
    {
        $net = DB::selectOne('SELECT network FROM lines WHERE id = ?', [$this->lineId])?->network;
        $rmin = self::minRadius($net, $this->gauge);
        $narrow = in_array($net, TraceConflation::JUNCTION_NARROW_NETWORKS, true);
        $used = [];

        // d. Trous face à face : sans voie précise (jusqu'à HOLE_SMOOTH_MAX_M), ou sur une voie précise que l'étape 2 n'a pas
        // pu suivre (changement de voie sans aiguillage, bout à plus de SNAP_M de la voie) : jusqu'à HOLE_MAX_M, la courbe
        // doit alors rester à moins de HOLE_TRACK_M d'une voie précise sur toute sa longueur.
        $ends = $this->freeEnds();
        $pairs = [];
        foreach ($ends as $i => $a) {
            foreach ($ends as $j => $b) {
                if ($j <= $i || $a['sid'] === $b['sid'] || $a['conn'] || $b['conn']) {
                    continue;
                }
                $d = hypot($b['x'] - $a['x'], $b['y'] - $a['y']);
                $ab = atan2($b['x'] - $a['x'], $b['y'] - $a['y']);
                if ($d <= 0.5 || $d > self::HOLE_MAX_M
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
            $radius = $this->minCurveRadius($pts);
            $free = $d <= self::HOLE_SMOOTH_MAX_M && $radius >= $rmin
                && max(array_map(fn ($p) => $this->distToLine([[$a['x'], $a['y']], [$b['x'], $b['y']]], $p), $pts)) <= self::LEG_DEV_M;
            if (! $free && ($radius < 0.5 * $rmin || ! $this->onPreciseTrack($pts))) {
                $this->debug && $this->log[] = ['hole_smooth' => [$a['sid'], $b['sid']], 'd' => round($d, 1), 'r' => round($radius), 'fit' => null];

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
