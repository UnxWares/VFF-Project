<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Un seul axe par ligne : retire du tracé de référence d'une ligne
 * (line_reference_parts) les portions qui LONGENT une autre portion de la
 * même ligne (parallèles, à moins de PARALLEL_M, sur au moins MIN_RUN_M) :
 *
 *   - BD TOPO : 4 voies saisies sur deux axes « 2 voies » (sorties de Paris,
 *     Lyon - Saint-Fons…), double voie saisie voie par voie, voies principales
 *     d'une gare (faisceau) ;
 *   - OSM : une voie par way (double voie, gare) ;
 *   - recouvrements entre sources après recalage, tracé archeo saisi deux fois
 *     (deux GPX du même tracé, aller-retour).
 *
 * Méthode (Lambert-93), après la fusion des sources et avant le croisement :
 *   1. Chaînes = sous-géométries de chaque morceau (les raccords à part),
 *      coupées aux rebroussements (deux voies parallèles enchaînées en U par
 *      ST_LineMerge, GPX aller-retour), classées par fiabilité du dessin (contribution, BD TOPO, OSM rattaché,
 *      voie précise trouvée par recalage, tracé grossier), puis par longueur
 *      de la VOIE CONTINUE dont elles font partie (chaînes bout à bout qui se
 *      prolongent sans coude, la plus droite à chaque aiguillage : une voie
 *      BD TOPO est coupée à chaque aiguillage), puis par longueur propre : le
 *      choix de l'axe gardé est déterministe et cohérent (la voie continue la
 *      plus longue d'une section à 4 voies garde toute la section, au lieu de
 *      sauter d'une voie à l'autre au gré de la longueur des morceaux, en
 *      escalier).
 *   2. Échantillons tous les STEP_M le long de chaque chaîne ; pour chacun, les
 *      autres chaînes à moins de PARALLEL_M (distance, angle, position du
 *      point le plus proche sur la chaîne, côté).
 *   3. Glouton dans l'ordre du 1 : un échantillon est « dans l'ombre » d'une
 *      chaîne déjà gardée s'il en est à moins de PARALLEL_M (12 m entre deux
 *      tracés grossiers), à moins de MAX_ANGLE, et se projette À L'INTÉRIEUR
 *      de celle-ci (au-delà de son extrémité, c'est un prolongement). Les
 *      séries d'au moins MIN_RUN_M (ou toute la chaîne) sont retirées ; une
 *      série qui passe d'un côté à l'autre (croisement) est gardée. Aux bouts
 *      d'une série, la coupe recule jusqu'au point de divergence (là où
 *      l'écart cesse de diminuer) : le raccord reste court.
 *   4. Tracés réellement distincts, gardés (et listés) : un ancien tracé de
 *      source historique (archeo, OHM) DATÉ (année de fermeture, événement
 *      daté rattaché au tracé, année dans son nom : Culoz - Modane, tracés de
 *      1866 et 1876) à plus de DISTINCT_M de la voie actuelle, ou une voie
 *      déposée / abandonnée à plus de LIFECYCLE_DISTINCT_M d'une voie en
 *      service. Au-delà de PARALLEL_M, rien n'est touché.
 *   5. Pas de trou aux aiguillages : chaque coupe, et chaque extrémité qui
 *      touchait une portion retirée, est raccordée (raccord droit ≤
 *      MAX_STITCH_M) à la géométrie gardée la plus proche, par un seul raccord
 *      (un trou vu depuis ses deux bords en donnait deux, superposés) ; un
 *      raccord dont un bout ne touche plus rien est retiré.
 *
 * Puis, après le croisement des sources, tidy() : bouts superposés à moins de
 * COINCIDENT_M retirés quelle que soit leur longueur, extrémités recollées au
 * bit près, raccords courts fusionnés (cf. tidy).
 *
 * Provenance : les morceaux gardés conservent source et ids ; les raccords
 * ajoutés prennent la source et les ids du morceau qu'ils prolongent ; les
 * portions retirées (et les tracés distincts gardés) sont enregistrées dans
 * line_reference_duplicates avec leurs ids et ceux de la portion conservée.
 * Déterministe et idempotent (la ligne est reconstruite à chaque passage).
 */
class SingleAxis
{
    public const PARALLEL_M = 30.0;

    /** Deux tracés grossiers (archeo / OHM / SNCF non recalés) : au-delà, ce sont deux voies distinctes. */
    public const PARALLEL_COARSE_M = 12.0;

    public const STEP_M = 10.0;

    private const MAX_ANGLE = 25.0;

    private const MIN_RUN_M = 100.0;

    /** Trou toléré dans une série (échantillons) : jonction entre deux chaînes gardées. */
    private const MAX_GAP_SAMPLES = 3;

    /** Reste plus court que ça, tout entier près de la géométrie gardée : retiré aussi. */
    private const MIN_REST_M = 60.0;

    /** Ancien tracé daté : écart minimal (m) pour être un tracé distinct. */
    private const DISTINCT_M = 3.0;

    /** Voie déposée / abandonnée le long d'une voie en service : écart minimal (m). */
    private const LIFECYCLE_DISTINCT_M = 8.0;

    /** Même chaîne repassant sur elle-même (GPX aller-retour) : écart max (m) et éloignement le long de la chaîne. */
    private const SELF_DUP_M = 5.0;

    private const SELF_SPAN_M = 150.0;

    /** Voie continue : chaînes bout à bout (à SNAP_M près) dont la déviation est d'au plus CONTINUE_DEG. */
    public const CONTINUE_DEG = 30.0;

    /** Croisement : écart minimal (m) de part et d'autre. */
    private const CROSS_M = 10.0;

    /** Recul de coupe : baisse minimale de l'écart (m) par échantillon. */
    private const TRIM_DROP_M = 0.3;

    private const MAX_STITCH_M = 40.0;

    /** Raccord : point visé à moins de ça (m) d'une extrémité de la portion visée → vers l'extrémité. */
    private const END_SNAP_M = 10.0;

    private const MAX_PASSES = 3;

    /** Nettoyage final (tidy) : deux portions à moins de ça l'une de l'autre sont la même voie (m). */
    public const COINCIDENT_M = 0.5;

    /** Bout superposé plus court : simple géométrie d'aiguillage (divergence), laissé au recollage (m). */
    private const MIN_TRIM_M = 3.0;

    /** Bout superposé qui ne déborde pas l'extrémité de l'autre portion (il s'en écarte ensuite) : retiré à partir de cette longueur (m). */
    private const MIN_COINCIDENT_M = 20.0;

    /** Recollage : extrémités à moins de ça d'une autre extrémité ou d'une portion confondues exactement (m). */
    public const SNAP_M = 0.5;

    /** Raccord plus court : fusionné avec la portion qu'il prolonge (m). */
    public const FOLD_M = 6.0;

    /**
     * Portion courte (< MIN_RUN_M) presque confondue : au moins NEAR_SHARE de sa longueur à moins de
     * NEAR_M d'autres portions (= DataInvariants::DUPLICATE_TOL_M : sinon deux tronçons en double dans
     * la même ère).
     */
    public const NEAR_M = 5.0;

    public const NEAR_SHARE = 0.9;

    /** Bout libre qui dépasse le croisement avec une autre portion de la ligne en la longeant (< NEAR_M) : longueur max coupée (m). */
    public const CROSS_STUB_MAX_M = 30.0;

    private const PRECISE = ['contrib', 'bdtopo', 'osm'];

    private const HISTORICAL = ['archeoferroviaire', 'ohm'];

    private const LIFECYCLE_LAYERS = ['abandoned', 'razed', 'disused', 'dismantled', 'historic', 'abandoned:railway', 'razed:railway',
        'disused:railway', 'demolished:railway', 'was:railway', 'removed:railway'];

    private bool $ready = false;

    private bool $tidyReady = false;

    /** Diagnostic : séries examinées et décision (rempli si $debug). */
    public bool $debug = false;

    public array $debugRuns = [];

    /**
     * Réduit le tracé de référence de la ligne à un seul axe.
     *
     * @return array<string, float> longueurs (m) retirées par cause, gardées comme tracé distinct, raccords ajoutés
     */
    public function collapse(int $lineId): array
    {
        $this->prepare();
        DB::delete('DELETE FROM line_reference_duplicates WHERE line_id = ?', [$lineId]);
        $stats = [];
        // Point fixe : une fois des portions retirées, les portions gardées d'un même morceau se
        // refusionnent en chaînes plus longues, qui peuvent en longer d'autres (ordre du glouton).
        for ($pass = 1; $pass <= self::MAX_PASSES; $pass++) {
            $chains = $this->loadChains($lineId);
            if (count($chains) < 2 && ! $this->hasSelfOverlapCandidate($chains)) {
                break;
            }
            [$removed, $records] = $this->greedy($chains, $this->candidates());
            foreach ($records as $r) {
                // Tracés distincts : comptés une seule fois (ils sont revus à chaque passe).
                if ($r['action'] !== 'removed' && $pass > 1) {
                    continue;
                }
                $key = $r['action'] === 'removed' ? 'removed:'.$r['reason'] : 'kept_distinct';
                $stats[$key] = ($stats[$key] ?? 0) + $r['b'] - $r['a'];
            }
            // Avant la réécriture : les ids des tracés se lisent sur les morceaux d'origine.
            $this->storeRecords($lineId, $pass > 1 ? array_filter($records, fn ($r) => $r['action'] === 'removed') : $records);
            if (! array_filter($removed)) {
                break;
            }
            $stats['connector_m'] = ($stats['connector_m'] ?? 0) + $this->rewrite($chains, $removed);
        }

        return $stats;
    }

    /**
     * Nettoyage final du tracé de référence d'une ligne, après le croisement des
     * sources (qui redécoupe les morceaux) : sans lui, vff:build-era-segments
     * produit des tronçons en double (même ligne, même ère) et des tronçons de
     * longueur nulle.
     *
     *   1. Superpositions : un bout de portion à moins de COINCIDENT_M d'une
     *      portion déjà gardée (ordre du glouton : fiabilité puis longueur ;
     *      raccords en dernier) est retiré, quelle que soit sa longueur (l'étape
     *      « un seul axe » ne retire que les séries d'au moins MIN_RUN_M) : bout
     *      qui déborde l'extrémité de l'autre portion (deux tracés qui se
     *      chevauchent bout à bout, ex. Somain - Orchies), ou bout superposé
     *      d'au moins MIN_COINCIDENT_M ; une portion tout entière superposée
     *      (raccord en double, aller et retour) disparaît. Une divergence
     *      d'aiguillage (écart qui croît dès l'extrémité) reste.
     *   1 bis. Portions courtes presque confondues (cf. dropNearDuplicates) :
     *      deux tracés de la même voie à 1-5 m, raccords superposés en V, petits
     *      côtés d'un rectangle de raccords.
     *   1 ter. Bouts croisés (cf. trimCrossedStubs) : le bout libre d'une portion
     *      qui dépasse de moins de CROSS_STUB_MAX_M le point où elle croise une
     *      autre portion de la ligne, en la longeant à moins de NEAR_M, est coupé
     *      au croisement (deux tracés de la même voie qui se croisent en biais).
     *   2. Recollage exact : les extrémités à moins de SNAP_M l'une de l'autre
     *      prennent les mêmes coordonnées, au bit près ; une extrémité posée sur
     *      une portion (raccord en T) devient un sommet de celle-ci. Sinon le
     *      ST_UnaryUnion du moteur décennal coupe la portion à 1e-9 m près et
     *      laisse des tronçons de longueur nulle.
     *   3. Doublons exacts (après recollage) et portions de moins de 1 cm retirés.
     *   4. Raccords de moins de FOLD_M fusionnés avec la portion qu'ils
     *      prolongent (extrémité partagée par elle seule) : plus de tronçon de
     *      quelques mètres isolé entre deux autres.
     *
     * Tous les morceaux de la ligne sont réécrits (même aller-retour Lambert-93
     * → WGS84 pour tous : les extrémités confondues le restent). Les bouts de
     * tracé source retirés sont listés dans line_reference_duplicates (cause
     * « superposition »). Idempotent.
     *
     * @return array<string, float|int> superposed_m (tracés), connector_dup_m, near_m (compris dans les deux premiers), crossed_m (bouts croisés, à part), snapped, folded
     */
    public function tidy(int $lineId): array
    {
        $this->prepareTidy();
        DB::delete('DELETE FROM tx_sub');
        DB::insert(<<<'SQL'
            INSERT INTO tx_sub (sid, part_id, sub, conn, rank, g)
            SELECT row_number() OVER (ORDER BY p.id, d.path[1]), p.id, d.path[1], p.matched_source IS NOT DISTINCT FROM 'connector',
                   CASE WHEN p.matched_source = 'connector' THEN 5
                        WHEN p.matched_source IS NULL AND p.source = 'contrib' THEN 0
                        WHEN p.matched_source IS NULL AND p.source = 'bdtopo' THEN 1
                        WHEN p.matched_source IS NULL AND p.source = 'osm' THEN 2
                        WHEN p.matched_source IN ('contrib', 'bdtopo', 'osm', 'ign-historique') THEN 3
                        ELSE 4 END,
                   d.geom
            FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
            WHERE p.line_id = ?
            SQL, [$lineId]);
        DB::statement('ANALYZE tx_sub');

        $stats = ['superposed_m' => 0.0, 'connector_dup_m' => 0.0, 'snapped' => 0, 'folded' => 0];
        $records = $this->trimCoincident($stats);
        $this->dropNearDuplicates($stats, $records);
        $this->trimCrossedStubs($stats, $records);
        $stats['snapped'] = $this->snapEnds();

        // Portions dégénérées, puis doublons exacts (après recollage) : la moins fiable / la plus courte part.
        DB::update('UPDATE tx_sub SET removed = TRUE WHERE NOT removed AND ST_Length(g) < 0.01');
        foreach (DB::select(<<<'SQL'
            UPDATE tx_sub a SET removed = TRUE
            WHERE NOT a.removed AND EXISTS (
                SELECT 1 FROM tx_sub b
                WHERE NOT b.removed AND b.sid <> a.sid AND ST_DWithin(a.g, b.g, 0.01)
                  AND (b.rank, -ST_Length(b.g), b.sid) < (a.rank, -ST_Length(a.g), a.sid)
                  AND ST_Length(ST_Intersection(a.g, ST_Buffer(b.g, 0.01))) >= 0.99 * ST_Length(a.g))
            RETURNING a.sid, a.conn, ST_Length(a.g) AS len
            SQL) as $r) {
            if ($r->conn) {
                $stats['connector_dup_m'] += $r->len;
            } else {
                $stats['superposed_m'] += $r->len;
                $records[] = ['sid' => (int) $r->sid, 'ksid' => null, 'ewkb' => null];
            }
        }
        $stats['folded'] = $this->foldShortConnectors();

        $this->storeTidyRecords($lineId, $records);

        // Réécriture de TOUS les morceaux de la ligne (cf. ci-dessus).
        DB::update(<<<'SQL'
            UPDATE line_reference_parts p SET geom = x.g, length_m = x.len, updated_at = NOW()
            FROM (
                SELECT part_id, ST_Multi(ST_Transform(ST_Collect(g ORDER BY sub), 4326)) AS g, ROUND(SUM(ST_Length(g)))::int AS len
                FROM tx_sub WHERE NOT removed GROUP BY part_id
            ) x
            WHERE p.id = x.part_id
            SQL);
        DB::delete('DELETE FROM line_reference_parts p WHERE p.line_id = ? AND NOT EXISTS (SELECT 1 FROM tx_sub t WHERE t.part_id = p.id AND NOT t.removed)', [$lineId]);

        return $stats;
    }

    private function prepareTidy(): void
    {
        if ($this->tidyReady) {
            return;
        }
        DB::statement('DROP TABLE IF EXISTS tx_sub');
        DB::statement('CREATE TEMP TABLE tx_sub (sid int PRIMARY KEY, part_id bigint, sub int, conn boolean, rank int,
            removed boolean NOT NULL DEFAULT FALSE, g geometry(LineString, 2154))');
        DB::statement('CREATE INDEX ON tx_sub USING gist (g)');
        $this->tidyReady = true;
    }

    /**
     * Glouton (fiabilité, longueur) : bouts de portion superposés (< COINCIDENT_M) à une
     * portion déjà gardée, retirés ; l'extrémité coupée est posée sur la portion gardée.
     *
     * @return list<array{sid: int, ksid: ?int, ewkb: ?string}> bouts de tracé retirés
     */
    private function trimCoincident(array &$stats): array
    {
        $tol = self::COINCIDENT_M;
        // Candidates : au moins MIN_TRIM_M (ou presque toute la portion) à moins de COINCIDENT_M d'une autre.
        $involved = array_flip(array_map(fn ($r) => (int) $r->sid, DB::select(<<<'SQL'
            SELECT DISTINCT a.sid FROM tx_sub a JOIN tx_sub b ON b.sid <> a.sid AND ST_DWithin(a.g, b.g, :tol)
            WHERE ST_Length(ST_Intersection(a.g, ST_Buffer(b.g, :tol2))) >= LEAST(:min, 0.95 * ST_Length(a.g))
            SQL, ['tol' => $tol, 'tol2' => $tol, 'min' => self::MIN_TRIM_M])));
        if (! $involved) {
            return [];
        }

        $records = [];
        $done = [];
        foreach (DB::select('SELECT sid, conn, ST_Length(g) AS len FROM tx_sub ORDER BY rank, ST_Length(g) DESC, sid') as $s) {
            $sid = (int) $s->sid;
            $len = (float) $s->len;
            if (! isset($involved[$sid]) || ! $done) {
                $done[] = $sid;

                continue;
            }
            $doneIds = $this->ints($done);
            $done[] = $sid;
            // Séries de la portion à moins de COINCIDENT_M de la géométrie déjà gardée, en abscisses (0-1).
            $runs = DB::select(<<<'SQL'
                WITH s AS (SELECT g FROM tx_sub WHERE sid = :sid),
                k AS (SELECT ST_Buffer(ST_Collect(t.g), :tol) AS b FROM tx_sub t, s
                      WHERE t.sid = ANY(CAST(:done AS int[])) AND NOT t.removed AND ST_DWithin(t.g, s.g, :tol2) HAVING COUNT(*) > 0),
                r AS (SELECT d.geom AS r FROM s, k, LATERAL ST_Dump(ST_CollectionExtract(ST_Intersection(s.g, k.b), 2)) d WHERE ST_Length(d.geom) > 0)
                SELECT LEAST(ST_LineLocatePoint(s.g, ST_StartPoint(r.r)), ST_LineLocatePoint(s.g, ST_EndPoint(r.r))) AS f0,
                       GREATEST(ST_LineLocatePoint(s.g, ST_StartPoint(r.r)), ST_LineLocatePoint(s.g, ST_EndPoint(r.r))) AS f1,
                       ST_Length(r.r) AS m
                FROM s, r ORDER BY 1
                SQL, ['sid' => $sid, 'tol' => $tol, 'tol2' => $tol, 'done' => $doneIds]);
            if (! $runs) {
                continue;
            }
            $covered = array_sum(array_map(fn ($r) => (float) $r->m, $runs));
            if ($covered >= 0.95 * $len && $len >= 4 * $tol) {
                $this->removeSub($sid, $s, $len, $stats, $records, $doneIds);

                continue;
            }
            // Bouts superposés aux extrémités : [0, a] et [b, 1] retirés.
            $a = 0.0;
            $b = 1.0;
            $first = $runs[0];
            $last = end($runs);
            if ((float) $first->f0 * $len <= 0.05 && $this->trimmable($sid, (float) $first->f1, (float) $first->m, $doneIds)) {
                $a = (float) $first->f1;
            }
            if ((float) $last->f1 * $len >= $len - 0.05 && $this->trimmable($sid, (float) $last->f0, (float) $last->m, $doneIds)) {
                $b = (float) $last->f0;
            }
            if ($a === 0.0 && $b === 1.0) {
                continue;
            }
            if (($b - $a) * $len < 1.0) {
                $this->removeSub($sid, $s, $len, $stats, $records, $doneIds);

                continue;
            }
            foreach ([[0.0, $a], [$b, 1.0]] as [$x, $y]) {
                if ($y > $x) {
                    $cut = DB::selectOne("SELECT encode(ST_AsEWKB(ST_LineSubstring(g, ?, ?)), 'hex') AS ewkb FROM tx_sub WHERE sid = ?", [$x, $y, $sid])->ewkb;
                    $stats[$s->conn ? 'connector_dup_m' : 'superposed_m'] += ($y - $x) * $len;
                    if (! $s->conn) {
                        $records[] = ['sid' => $sid, 'ksid' => null, 'ewkb' => $cut];
                    }
                }
            }
            // Portion raccourcie ; chaque extrémité coupée posée sur la géométrie gardée la plus proche.
            DB::update(<<<'SQL'
                UPDATE tx_sub t SET g = x.g FROM (
                    SELECT CASE WHEN :ce THEN ST_SetPoint(l1, ST_NPoints(l1) - 1, COALESCE((
                                SELECT ST_ClosestPoint(o.g, ST_EndPoint(l1)) FROM tx_sub o
                                WHERE o.sid = ANY(CAST(:done2 AS int[])) AND NOT o.removed AND ST_DWithin(o.g, ST_EndPoint(l1), :tol2)
                                ORDER BY ST_Distance(o.g, ST_EndPoint(l1)), o.rank LIMIT 1), ST_EndPoint(l1))) ELSE l1 END AS g
                    FROM (
                        SELECT CASE WHEN :cs THEN ST_SetPoint(l0, 0, COALESCE((
                                    SELECT ST_ClosestPoint(o.g, ST_StartPoint(l0)) FROM tx_sub o
                                    WHERE o.sid = ANY(CAST(:done AS int[])) AND NOT o.removed AND ST_DWithin(o.g, ST_StartPoint(l0), :tol)
                                    ORDER BY ST_Distance(o.g, ST_StartPoint(l0)), o.rank LIMIT 1), ST_StartPoint(l0))) ELSE l0 END AS l1
                        FROM (SELECT ST_LineSubstring(g, :a, :b) AS l0 FROM tx_sub WHERE sid = :sid) z
                    ) y
                ) x
                WHERE t.sid = :sid2
                SQL, ['cs' => $a > 0 ? 'true' : 'false', 'ce' => $b < 1 ? 'true' : 'false', 'a' => $a, 'b' => $b, 'sid' => $sid, 'sid2' => $sid,
                'done' => $doneIds, 'done2' => $doneIds, 'tol' => $tol + 0.1, 'tol2' => $tol + 0.1]);
        }

        return $records;
    }

    /**
     * Portions courtes presque confondues : une portion de moins de MIN_RUN_M dont au moins NEAR_SHARE
     * de la longueur est à moins de NEAR_M d'autres portions est retirée (raccords d'abord, puis les
     * moins fiables, puis les plus courtes : de deux portions courtes, la moins fiable part ; une
     * portion courte le long d'une longue part même plus fiable, la longue ne pouvant pas l'être). Une extrémité d'une autre portion qui
     * ne tenait qu'à elle est reposée sur la géométrie gardée la plus proche, à NEAR_M + SNAP_M près ;
     * sinon la portion reste (jamais de trou).
     */
    private function dropNearDuplicates(array &$stats, array &$records): void
    {
        $cands = DB::select(<<<'SQL'
            SELECT a.sid, a.conn, a.rank, ST_Length(a.g) AS len FROM tx_sub a
            WHERE NOT a.removed AND ST_Length(a.g) < :max
              AND EXISTS (SELECT 1 FROM tx_sub b WHERE b.sid <> a.sid AND NOT b.removed AND ST_DWithin(a.g, b.g, :near))
            ORDER BY a.rank DESC, ST_Length(a.g), a.sid DESC
            SQL, ['max' => self::MIN_RUN_M, 'near' => self::NEAR_M]);
        foreach ($cands as $c) {
            $sid = (int) $c->sid;
            $share = (float) DB::selectOne(<<<'SQL'
                WITH a AS (SELECT g FROM tx_sub WHERE sid = :sid AND NOT removed),
                o AS (SELECT ST_Union(ST_Buffer(b.g, :near)) AS u FROM tx_sub b, a
                      WHERE b.sid <> :sid2 AND NOT b.removed AND ST_DWithin(b.g, a.g, :near2))
                SELECT COALESCE(ST_Length(ST_Intersection(a.g, o.u)) / NULLIF(ST_Length(a.g), 0), 0) AS s FROM a, o
                SQL, ['sid' => $sid, 'sid2' => $sid, 'near' => self::NEAR_M, 'near2' => self::NEAR_M])?->s;
            if ($share < self::NEAR_SHARE) {
                continue;
            }
            // Extrémités d'autres portions qui ne tiennent qu'à elle, et point où les reposer.
            $ends = DB::select(<<<'SQL'
                WITH a AS (SELECT g FROM tx_sub WHERE sid = :sid)
                SELECT t.sid, e.k, encode(ST_AsEWKB(q.q), 'hex') AS q
                FROM a, tx_sub t
                CROSS JOIN LATERAL (VALUES (0, ST_StartPoint(t.g)), (1, ST_EndPoint(t.g))) e(k, p)
                LEFT JOIN LATERAL (
                    SELECT ST_ClosestPoint(o.g, e.p) AS q FROM tx_sub o
                    WHERE o.sid <> :sid2 AND o.sid <> t.sid AND NOT o.removed AND ST_DWithin(o.g, e.p, :reach)
                    ORDER BY ST_Distance(o.g, e.p), o.rank, o.sid LIMIT 1
                ) q ON TRUE
                WHERE t.sid <> :sid3 AND NOT t.removed AND ST_DWithin(a.g, e.p, :snap)
                  AND NOT EXISTS (SELECT 1 FROM tx_sub o WHERE o.sid <> :sid4 AND o.sid <> t.sid AND NOT o.removed AND ST_DWithin(o.g, e.p, :snap2))
                ORDER BY t.sid, e.k
                SQL, ['sid' => $sid, 'sid2' => $sid, 'sid3' => $sid, 'sid4' => $sid, 'reach' => self::NEAR_M + self::SNAP_M,
                'snap' => self::SNAP_M, 'snap2' => self::SNAP_M]);
            if (array_filter($ends, fn ($e) => $e->q === null)) {
                continue;
            }
            DB::update('UPDATE tx_sub SET removed = TRUE WHERE sid = ?', [$sid]);
            // Une à une : une extrémité déjà reposée peut en raccrocher une autre (lien de quelques mètres entre deux portions).
            foreach ($ends as $e) {
                DB::update(<<<'SQL'
                    UPDATE tx_sub t SET g = ST_SetPoint(t.g, CASE WHEN :k = 0 THEN 0 ELSE ST_NPoints(t.g) - 1 END, q.q)
                    FROM (SELECT CASE WHEN :k2 = 0 THEN ST_StartPoint(g) ELSE ST_EndPoint(g) END AS p FROM tx_sub WHERE sid = :sid) e
                    CROSS JOIN LATERAL (
                        SELECT ST_ClosestPoint(o.g, e.p) AS q FROM tx_sub o
                        WHERE o.sid <> :sid2 AND NOT o.removed AND ST_DWithin(o.g, e.p, :reach)
                        ORDER BY ST_Distance(o.g, e.p), o.rank, o.sid LIMIT 1
                    ) q
                    WHERE t.sid = :sid3
                      AND NOT EXISTS (SELECT 1 FROM tx_sub o WHERE o.sid <> :sid4 AND NOT o.removed AND ST_DWithin(o.g, e.p, :snap))
                    SQL, ['k' => (int) $e->k, 'k2' => (int) $e->k, 'sid' => (int) $e->sid, 'sid2' => (int) $e->sid, 'sid3' => (int) $e->sid, 'sid4' => (int) $e->sid,
                    'reach' => self::NEAR_M + self::SNAP_M, 'snap' => self::SNAP_M]);
            }
            $stats[$c->conn ? 'connector_dup_m' : 'superposed_m'] += (float) $c->len;
            $stats['near_m'] = ($stats['near_m'] ?? 0) + (float) $c->len;
            if (! $c->conn) {
                $records[] = ['sid' => $sid, 'ksid' => null, 'ewkb' => null];
            }
        }
    }

    /**
     * Bouts croisés : une portion (hors raccords) dont une extrémité libre dépasse de 1 m à CROSS_STUB_MAX_M le point où
     * elle croise une autre portion de la ligne, et dont tout ce bout reste à moins de NEAR_M de celle-ci, est coupée au
     * croisement. C'est le même rail dessiné par deux sources qui se croisent en biais (Sucrerie de Toury, 3110 : tracé
     * grossier et voie BD TOPO, bout de 14 m à 1-3 m ; sinon deux tronçons en double à chaque ère). La nouvelle
     * extrémité est sur l'autre portion : le recollage (snapEnds) en fait un raccord en T. Le bout reste quand une
     * autre portion de la ligne ou une autre ligne y finit (jonction), ou quand la portion ne garderait pas au moins
     * deux fois sa longueur.
     */
    private function trimCrossedStubs(array &$stats, array &$records): void
    {
        $cands = DB::select(<<<'SQL'
            WITH e AS (
                SELECT t.sid, e.k, e.p, t.g, ST_Length(t.g) AS len FROM tx_sub t, LATERAL (VALUES (0, ST_StartPoint(t.g)), (1, ST_EndPoint(t.g))) e(k, p)
                WHERE NOT t.removed AND NOT t.conn AND ST_Length(t.g) > 3 * :min
                  AND NOT EXISTS (SELECT 1 FROM tx_sub o WHERE NOT o.removed AND o.sid <> t.sid AND ST_DWithin(o.g, e.p, :snap))
            ),
            x AS (
                SELECT e.sid, e.k, e.len, o.sid AS osid, ST_LineLocatePoint(e.g, xp.geom) AS f, encode(ST_AsEWKB(xp.geom), 'hex') AS xp
                FROM e JOIN tx_sub o ON NOT o.removed AND o.sid <> e.sid AND ST_DWithin(o.g, e.p, :near) AND ST_Intersects(o.g, e.g),
                     LATERAL ST_Dump(ST_CollectionExtract(ST_Intersection(e.g, o.g), 1)) xp
            )
            SELECT DISTINCT ON (sid, k) sid, k, len, osid, f, xp, CASE WHEN k = 0 THEN f ELSE 1 - f END * len AS stub
            FROM x
            WHERE CASE WHEN k = 0 THEN f ELSE 1 - f END * len BETWEEN :min2 AND LEAST(:max, len / 3)
            ORDER BY sid, k, CASE WHEN k = 0 THEN f ELSE 1 - f END
            SQL, ['min' => 1.0, 'min2' => 1.0, 'max' => self::CROSS_STUB_MAX_M, 'snap' => self::SNAP_M, 'near' => self::NEAR_M]);
        $cut = [];
        foreach ($cands as $c) {
            $sid = (int) $c->sid;
            // Une coupe par portion et par passage : l'abscisse de l'autre bout ne vaut plus après la première.
            if (isset($cut[$sid])) {
                continue;
            }
            [$a, $b] = (int) $c->k === 0 ? [0.0, (float) $c->f] : [(float) $c->f, 1.0];
            $ok = DB::selectOne(<<<'SQL'
                WITH s AS (SELECT ST_LineSubstring(g, :a, :b) AS g FROM tx_sub WHERE sid = :sid AND NOT removed),
                o AS (SELECT g FROM tx_sub WHERE sid = :osid AND NOT removed)
                SELECT ST_Length(ST_Intersection(s.g, ST_Buffer(o.g, :near))) >= 0.99 * ST_Length(s.g)
                   AND NOT EXISTS (
                       SELECT 1 FROM tx_sub t, LATERAL (VALUES (ST_StartPoint(t.g)), (ST_EndPoint(t.g))) e(p)
                       WHERE NOT t.removed AND t.sid <> :sid2 AND ST_DWithin(s.g, e.p, :snap)
                         AND NOT ST_DWithin(e.p, ST_GeomFromEWKB(decode(:xp, 'hex')), :snap2))
                   AND NOT EXISTS (
                       SELECT 1 FROM line_reference_parts r, LATERAL ST_Dump(r.geom) d
                       WHERE r.line_id <> (SELECT p.line_id FROM line_reference_parts p JOIN tx_sub t ON t.part_id = p.id WHERE t.sid = :sid3)
                         AND r.geom && ST_Transform(ST_Expand(s.g, 2), 4326)
                         AND (ST_DWithin(ST_Transform(ST_StartPoint(d.geom), 2154), s.g, 1) OR ST_DWithin(ST_Transform(ST_EndPoint(d.geom), 2154), s.g, 1))) AS ok
                FROM s, o
                SQL, ['a' => $a, 'b' => $b, 'sid' => $sid, 'sid2' => $sid, 'sid3' => $sid, 'osid' => (int) $c->osid, 'near' => self::NEAR_M,
                'snap' => self::SNAP_M, 'snap2' => self::SNAP_M, 'xp' => $c->xp])?->ok;
            if ($ok !== true) {
                continue;
            }
            $records[] = ['sid' => $sid, 'ksid' => (int) $c->osid,
                'ewkb' => DB::selectOne("SELECT encode(ST_AsEWKB(ST_LineSubstring(g, ?, ?)), 'hex') AS ewkb FROM tx_sub WHERE sid = ?", [$a, $b, $sid])->ewkb];
            // Partie gardée, extrémité posée exactement au croisement.
            DB::update(<<<'SQL'
                UPDATE tx_sub SET g = ST_SetPoint(l.g, CASE WHEN :k = 0 THEN 0 ELSE ST_NPoints(l.g) - 1 END, ST_GeomFromEWKB(decode(:xp, 'hex')))
                FROM (SELECT ST_LineSubstring(g, :a, :b) AS g FROM tx_sub WHERE sid = :sid) l
                WHERE sid = :sid2
                SQL, ['k' => (int) $c->k, 'xp' => $c->xp, 'a' => (int) $c->k === 0 ? (float) $c->f : 0.0, 'b' => (int) $c->k === 0 ? 1.0 : (float) $c->f,
                'sid' => $sid, 'sid2' => $sid]);
            $cut[$sid] = true;
            // Compté à part (pas dans superposed_m) : vff:build-line-references affiche les deux postes côte à côte.
            $stats['crossed_m'] = ($stats['crossed_m'] ?? 0) + (float) $c->stub;
        }
    }

    /**
     * Bout superposé [extrémité, f] retirable : assez long, et soit il déborde l'extrémité de la
     * portion gardée la plus proche (chevauchement bout à bout), soit il est long (≥ MIN_COINCIDENT_M).
     * Sinon c'est une divergence d'aiguillage : l'écart croît dès l'extrémité.
     */
    private function trimmable(int $sid, float $f, float $m, string $doneIds): bool
    {
        if ($m < self::MIN_TRIM_M) {
            return false;
        }
        if ($m >= self::MIN_COINCIDENT_M) {
            return true;
        }
        $r = DB::selectOne(<<<'SQL'
            WITH p AS (SELECT ST_LineInterpolatePoint(g, :f) AS p FROM tx_sub WHERE sid = :sid)
            SELECT ST_LineLocatePoint(o.g, p.p) AS loc FROM tx_sub o, p
            WHERE o.sid = ANY(CAST(:done AS int[])) AND NOT o.removed AND ST_DWithin(o.g, p.p, :tol)
            ORDER BY ST_Distance(o.g, p.p), o.rank LIMIT 1
            SQL, ['f' => $f, 'sid' => $sid, 'done' => $doneIds, 'tol' => self::COINCIDENT_M + 1]);

        return $r !== null && ((float) $r->loc <= 1e-9 || (float) $r->loc >= 1 - 1e-9);
    }

    private function removeSub(int $sid, object $s, float $len, array &$stats, array &$records, string $doneIds): void
    {
        DB::update('UPDATE tx_sub SET removed = TRUE WHERE sid = ?', [$sid]);
        $stats[$s->conn ? 'connector_dup_m' : 'superposed_m'] += $len;
        if (! $s->conn) {
            $k = DB::selectOne(<<<'SQL'
                SELECT o.sid FROM tx_sub o, tx_sub s WHERE s.sid = ? AND o.sid = ANY(CAST(? AS int[])) AND NOT o.removed
                ORDER BY ST_Distance(o.g, ST_LineInterpolatePoint(s.g, 0.5)), o.rank LIMIT 1
                SQL, [$sid, $doneIds]);
            $records[] = ['sid' => $sid, 'ksid' => $k ? (int) $k->sid : null, 'ewkb' => null];
        }
    }

    /**
     * Recollage exact : extrémités regroupées à SNAP_M près (coordonnées de celle de la portion
     * la plus fiable) ; groupe posé sur une portion sans extrémité dans le groupe (raccord en T) :
     * point le plus proche de celle-ci, inséré comme sommet.
     *
     * @return int extrémités déplacées
     */
    private function snapEnds(): int
    {
        $rows = DB::select(<<<'SQL'
            WITH e AS (
                SELECT t.sid, e.k, e.p, t.rank, ST_Length(t.g) AS len
                FROM tx_sub t, LATERAL (VALUES (0, ST_StartPoint(t.g)), (1, ST_EndPoint(t.g))) e(k, p)
                WHERE NOT t.removed
            ),
            c AS (SELECT e.*, ST_ClusterDBSCAN(e.p, :snap, 1) OVER () AS cl FROM e),
            rep AS (SELECT DISTINCT ON (cl) cl, p FROM c ORDER BY cl, rank, len DESC, sid, k),
            tee AS (
                SELECT r.cl, h.sid AS hsid, ST_ClosestPoint(h.g, r.p) AS q
                FROM rep r CROSS JOIN LATERAL (
                    SELECT t.sid, t.g FROM tx_sub t
                    WHERE NOT t.removed AND ST_DWithin(t.g, r.p, :snap2)
                      AND NOT EXISTS (SELECT 1 FROM c WHERE c.cl = r.cl AND c.sid = t.sid)
                    ORDER BY t.rank, ST_Distance(t.g, r.p), t.sid LIMIT 1
                ) h
            ),
            fin AS (SELECT rep.cl, COALESCE(tee.q, rep.p) AS q, tee.hsid FROM rep LEFT JOIN tee USING (cl))
            SELECT c.sid, c.k, encode(ST_AsEWKB(fin.q), 'hex') AS q, fin.hsid, NOT ST_Equals(c.p, fin.q) AS moved
            FROM c JOIN fin USING (cl)
            WHERE fin.hsid IS NOT NULL OR NOT ST_Equals(c.p, fin.q)
            SQL, ['snap' => self::SNAP_M, 'snap2' => self::SNAP_M]);
        $moved = 0;
        $tees = [];
        foreach ($rows as $r) {
            if ($r->moved) {
                $moved++;
                DB::update(
                    "UPDATE tx_sub SET g = ST_SetPoint(g, CASE WHEN ? = 0 THEN 0 ELSE ST_NPoints(g) - 1 END, ST_GeomFromEWKB(decode(?, 'hex'))) WHERE sid = ?",
                    [(int) $r->k, $r->q, (int) $r->sid]);
            }
            if ($r->hsid !== null) {
                $tees[$r->hsid.':'.$r->q] = [(int) $r->hsid, $r->q];
            }
        }
        // Sommet inséré sur la portion (ou sommet confondu à 1 µm près remplacé).
        foreach ($tees as [$hsid, $q]) {
            DB::update(<<<'SQL'
                WITH h AS (SELECT g FROM tx_sub WHERE sid = :sid),
                q AS (SELECT ST_GeomFromEWKB(decode(:q, 'hex')) AS q),
                s AS (SELECT d.path[1] AS k FROM h, q, LATERAL ST_DumpSegments(h.g) d ORDER BY ST_Distance(d.geom, q.q), d.path[1] LIMIT 1)
                UPDATE tx_sub SET g = (
                    SELECT CASE WHEN ST_Distance(ST_PointN(h.g, s.k), q.q) < 1e-6 THEN ST_SetPoint(h.g, s.k - 1, q.q)
                                WHEN ST_Distance(ST_PointN(h.g, s.k + 1), q.q) < 1e-6 THEN ST_SetPoint(h.g, s.k, q.q)
                                ELSE ST_AddPoint(h.g, q.q, s.k) END
                    FROM h, q, s)
                WHERE sid = :sid2
                SQL, ['sid' => $hsid, 'sid2' => $hsid, 'q' => $q]);
        }

        return $moved;
    }

    /**
     * Raccords de moins de FOLD_M dont une extrémité n'est partagée que par l'extrémité d'une
     * portion de tracé : fusionnés avec elle (même géométrie, un tronçon de moins).
     *
     * @return int raccords fusionnés
     */
    private function foldShortConnectors(): int
    {
        $folded = 0;
        foreach (DB::select('SELECT sid FROM tx_sub WHERE conn AND NOT removed AND ST_Length(g) <= ? ORDER BY ST_Length(g), sid', [self::FOLD_M]) as $c) {
            $h = DB::selectOne(<<<'SQL'
                WITH c AS (SELECT g FROM tx_sub WHERE sid = :sid),
                e AS (SELECT e.p FROM c, LATERAL (VALUES (ST_StartPoint(c.g)), (ST_EndPoint(c.g))) e(p))
                SELECT t.sid FROM e
                JOIN tx_sub t ON NOT t.removed AND NOT t.conn AND t.sid <> :sid2
                     AND (ST_Equals(ST_StartPoint(t.g), e.p) OR ST_Equals(ST_EndPoint(t.g), e.p))
                     AND NOT ST_Equals(ST_StartPoint(t.g), ST_EndPoint(t.g))
                WHERE (SELECT COUNT(*) FROM tx_sub o WHERE NOT o.removed AND o.sid <> :sid3 AND ST_DWithin(o.g, e.p, 1e-6)) = 1
                ORDER BY t.rank, ST_Length(t.g) DESC, t.sid LIMIT 1
                SQL, ['sid' => (int) $c->sid, 'sid2' => (int) $c->sid, 'sid3' => (int) $c->sid]);
            if (! $h) {
                continue;
            }
            $n = DB::update(<<<'SQL'
                UPDATE tx_sub t SET g = m.g
                FROM (SELECT ST_LineMerge(ST_Collect(a.g, b.g)) AS g FROM tx_sub a, tx_sub b WHERE a.sid = :h AND b.sid = :c) m
                WHERE t.sid = :h2 AND ST_GeometryType(m.g) = 'ST_LineString'
                SQL, ['h' => (int) $h->sid, 'c' => (int) $c->sid, 'h2' => (int) $h->sid]);
            if ($n) {
                DB::update('UPDATE tx_sub SET removed = TRUE WHERE sid = ?', [(int) $c->sid]);
                $folded++;
            }
        }

        return $folded;
    }

    /** Bouts de tracé retirés par le nettoyage (line_reference_duplicates, cause « superposition »). */
    private function storeTidyRecords(int $lineId, array $records): void
    {
        foreach ($records as $r) {
            DB::insert(<<<'SQL'
                INSERT INTO line_reference_duplicates (line_id, action, reason, source, matched_source, trace_ids, kept_source, kept_matched_source,
                    kept_trace_ids, length_m, gap_median_m, geom, created_at, updated_at)
                SELECT :line, 'removed', 'superposition', p.source, p.matched_source,
                       CASE WHEN p.matched_source IN ('contrib', 'bdtopo', 'osm', 'ign-historique') THEN p.matched_trace_ids ELSE p.source_trace_ids END,
                       kp.source, kp.matched_source,
                       CASE WHEN kp.matched_source IN ('contrib', 'bdtopo', 'osm', 'ign-historique') THEN kp.matched_trace_ids ELSE kp.source_trace_ids END,
                       ROUND(ST_Length(x.g))::int, ROUND(COALESCE(k.d, 0)::numeric, 1), ST_Transform(x.g, 4326), NOW(), NOW()
                FROM tx_sub s
                JOIN line_reference_parts p ON p.id = s.part_id
                CROSS JOIN LATERAL (SELECT COALESCE(ST_GeomFromEWKB(decode(CAST(:ewkb AS text), 'hex')), s.g) AS g) x
                LEFT JOIN LATERAL (
                    SELECT o.part_id, ST_Distance(o.g, ST_LineInterpolatePoint(x.g, 0.5)) AS d
                    FROM tx_sub o WHERE o.sid <> s.sid AND NOT o.removed AND (o.sid = :ksid OR CAST(:ksid2 AS int) IS NULL)
                    ORDER BY 2, o.rank LIMIT 1
                ) k ON TRUE
                LEFT JOIN line_reference_parts kp ON kp.id = k.part_id
                WHERE s.sid = :sid AND ST_GeometryType(x.g) = 'ST_LineString' AND ST_Length(x.g) > 0
                SQL, ['line' => $lineId, 'ewkb' => $r['ewkb'], 'ksid' => $r['ksid'], 'ksid2' => $r['ksid'], 'sid' => $r['sid']]);
        }
    }

    private function prepare(): void
    {
        if ($this->ready) {
            return;
        }
        DB::statement('DROP TABLE IF EXISTS sx_chain');
        DB::statement('CREATE TEMP TABLE sx_chain (cid int PRIMARY KEY, part_id bigint, sub int, source text, matched text, rank int, len float8, n int, g geometry(LineString, 2154))');
        DB::statement('DROP TABLE IF EXISTS sx_piece');
        DB::statement('CREATE TEMP TABLE sx_piece (cid int, off float8, plen float8, clen float8, g geometry(LineString, 2154))');
        DB::statement('CREATE INDEX ON sx_piece USING gist (g)');
        DB::statement('DROP TABLE IF EXISTS sx_samp');
        DB::statement('CREATE TEMP TABLE sx_samp (cid int, j int, pos float8, pt geometry(Point, 2154), az float8)');
        DB::statement('DROP TABLE IF EXISTS sx_kept');
        DB::statement('CREATE TEMP TABLE sx_kept (kid int PRIMARY KEY, cid int, part_id bigint, a float8, b float8, g geometry(LineString, 2154))');
        DB::statement('CREATE INDEX ON sx_kept USING gist (g)');
        DB::statement('CREATE INDEX ON sx_chain USING gist (g)');
        DB::statement('DROP TABLE IF EXISTS sx_conn');
        DB::statement('CREATE TEMP TABLE sx_conn (part_id bigint, sub int, keep boolean, g geometry(LineString, 2154))');
        // Tracés (source_traces) d'un morceau situés sous une géométrie (Lambert-93) : dessin si recalé, sinon tracés source.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION pg_temp.sx_ids(part bigint, matched text, g geometry, dist float8)
            RETURNS jsonb LANGUAGE sql STABLE AS $$
                SELECT COALESCE(jsonb_agg(st.id ORDER BY st.id), '[]'::jsonb)
                FROM line_reference_parts p
                JOIN source_traces st ON st.id IN (
                    SELECT jsonb_array_elements_text(CASE WHEN matched IN ('contrib', 'bdtopo', 'osm', 'ign-historique') THEN p.matched_trace_ids ELSE p.source_trace_ids END)::bigint)
                WHERE p.id = part AND st.geom && ST_Transform(ST_Expand(g, dist + 1), 4326)
                  AND ST_DWithin(ST_Transform(st.geom, 2154), g, dist)
                  -- Tracé qui court réellement sous la portion (pas seulement croisé).
                  AND ST_Length(ST_Intersection(ST_Transform(st.geom, 2154), ST_Buffer(g, dist))) >= LEAST(20, 0.5 * ST_Length(g))
            $$
            SQL);
        $this->ready = true;
    }

    /**
     * Chaînes de la ligne (hors raccords), rang de fiabilité, signature de
     * datation (tracés historiques) et état de la voie sous le dessin.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadChains(int $lineId): array
    {
        foreach (['sx_chain', 'sx_piece', 'sx_samp', 'sx_kept', 'sx_conn'] as $t) {
            DB::delete("DELETE FROM {$t}");
        }
        // Chaînes coupées aux rebroussements (demi-tour sur ±5 m) : ST_LineMerge enchaîne deux voies
        // parallèles qui se rejoignent à un aiguillage en une seule ligne en U ; une voie ferrée, elle,
        // ne fait jamais demi-tour sur place (un tracé GPX aller-retour non plus n'en est pas un).
        DB::insert(<<<'SQL'
            INSERT INTO sx_chain (cid, part_id, sub, source, matched, rank, len, n, g)
            WITH d AS (
                SELECT p.id AS part_id, d.path[1] AS sub, p.source, p.matched_source, d.geom AS g, ST_Length(d.geom) AS len
                FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
                WHERE p.line_id = :line AND p.matched_source IS DISTINCT FROM 'connector' AND ST_Length(d.geom) > 1
            ),
            v0 AS (
                SELECT d.part_id, d.sub, d.g, d.len, dp.path[1] AS i, dp.geom AS pt,
                       LAG(dp.geom) OVER (PARTITION BY d.part_id, d.sub ORDER BY dp.path[1]) AS prev
                FROM d, LATERAL ST_DumpPoints(d.g) dp
            ),
            v AS (
                SELECT *, SUM(COALESCE(ST_Distance(pt, prev), 0)) OVER (PARTITION BY part_id, sub ORDER BY i) AS cum FROM v0
            ),
            cusp AS (
                SELECT part_id, sub, array_agg(cum ORDER BY cum) AS cs FROM v
                WHERE cum > 5 AND cum < len - 5
                  AND degrees(acos(LEAST(1, GREATEST(-1, cos(
                          ST_Azimuth(pt, ST_LineInterpolatePoint(g, (cum + 5) / len))
                        - ST_Azimuth(ST_LineInterpolatePoint(g, (cum - 5) / len), pt)))))) > 120
                GROUP BY part_id, sub
            ),
            b AS (
                SELECT d.*, ARRAY[0::float8] || COALESCE(c.cs, '{}') || ARRAY[d.len] AS bounds
                FROM d LEFT JOIN cusp c USING (part_id, sub)
            ),
            pieces AS (
                SELECT b.part_id, b.sub, k, b.source, b.matched_source,
                       ST_LineSubstring(b.g, b.bounds[k] / b.len, LEAST(1, b.bounds[k + 1] / b.len)) AS g
                FROM b, LATERAL generate_series(1, array_length(b.bounds, 1) - 1) k
                WHERE b.bounds[k + 1] - b.bounds[k] > 1
            )
            SELECT row_number() OVER (ORDER BY part_id, sub, k), part_id, sub, source, matched_source,
                   CASE WHEN matched_source IS NULL AND source = 'contrib' THEN 0
                        WHEN matched_source IS NULL AND source = 'bdtopo' THEN 1
                        WHEN matched_source IS NULL AND source = 'osm' THEN 2
                        WHEN matched_source IN ('contrib', 'bdtopo', 'osm', 'ign-historique') THEN 3
                        ELSE 4 END,
                   ST_Length(g), GREATEST(1, ROUND(ST_Length(g) / :step))::int, g
            FROM pieces
            SQL, ['line' => $lineId, 'step' => self::STEP_M]);
        DB::insert(<<<'SQL'
            INSERT INTO sx_conn (part_id, sub, keep, g)
            SELECT p.id, d.path[1], TRUE, d.geom
            FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
            WHERE p.line_id = ? AND p.matched_source = 'connector'
            SQL, [$lineId]);

        $chains = [];
        $rows = DB::select(<<<'SQL'
            SELECT c.cid, c.part_id, c.source, c.matched, c.rank, c.len, c.n,
                   -- Tracé historique (archeo / OHM) : signature de datation du tracé source le plus proche
                   -- (année de fermeture, événements de fermeture rattachés, années citées dans le nom).
                   CASE WHEN c.source IN ('archeoferroviaire', 'ohm') THEN (
                       SELECT COALESCE(array_to_string(ARRAY(
                                  SELECT DISTINCT y FROM (
                                      SELECT st.closed_year::text AS y WHERE st.closed_year IS NOT NULL
                                      UNION SELECT (regexp_matches(COALESCE(st.name, ''), '(1[89][0-9][0-9]|20[0-9][0-9])', 'g'))[1]
                                      UNION SELECT EXTRACT(YEAR FROM e.event_date)::int::text FROM line_events e
                                            WHERE e.source_trace_id = st.id AND e.type IN ('closure', 'dismantling', 'declassification')
                                  ) y ORDER BY y), ','), '')
                       FROM source_traces st, LATERAL ST_LineInterpolatePoint(c.g, 0.5) m
                       WHERE st.id IN (SELECT jsonb_array_elements_text(p.source_trace_ids)::bigint)
                         AND st.geom && ST_Expand(ST_Transform(m, 4326), 0.002)
                       ORDER BY ST_Transform(st.geom, 2154) <-> m LIMIT 1)
                   END AS sig,
                   -- Voie sous le dessin (précis) déposée / abandonnée, à mi-longueur et aux quarts.
                   CASE WHEN COALESCE(c.matched, c.source) IN ('bdtopo', 'osm') THEN (
                       SELECT COUNT(*) FILTER (WHERE life) >= 2 FROM (
                           SELECT (SELECT bool_and(st.layer = ANY(CAST(:life AS text[]))
                                                   OR (st.source = 'bdtopo' AND st.properties->>'etat_de_l_objet' = 'Non exploité'))
                                   FROM source_traces st
                                   WHERE st.source = COALESCE(c.matched, c.source) AND st.geom && ST_Expand(ST_Transform(q.pt, 4326), 0.00003)
                                     AND ST_DWithin(ST_Transform(st.geom, 2154), q.pt, 1.5)) AS life
                           FROM (SELECT ST_LineInterpolatePoint(c.g, f) AS pt FROM unnest(ARRAY[0.25, 0.5, 0.75]) f) q
                       ) z)
                   ELSE FALSE END AS life
            FROM sx_chain c JOIN line_reference_parts p ON p.id = c.part_id
            SQL, ['life' => '{'.implode(',', array_map(fn ($l) => '"'.$l.'"', self::LIFECYCLE_LAYERS)).'}']);
        $group = $this->continuousTracks();
        foreach ($rows as $r) {
            $chains[(int) $r->cid] = [
                'cid' => (int) $r->cid, 'part_id' => (int) $r->part_id, 'source' => $r->source, 'matched' => $r->matched,
                'rank' => (int) $r->rank, 'len' => (float) $r->len, 'n' => (int) $r->n, 'glen' => $group[(int) $r->cid] ?? (float) $r->len,
                'hist' => in_array($r->source, self::HISTORICAL, true),
                'coarse' => (int) $r->rank === 4,
                'sig' => (string) ($r->sig ?? ''),
                'life' => (bool) $r->life,
                'fam' => (int) $r->rank === 4 ? 'grossier' : ($r->matched ?? $r->source),
            ];
        }

        return $chains;
    }

    /**
     * Longueur de la voie continue de chaque chaîne : la chaîne, prolongée à chaque bout par la chaîne
     * qui la continue le plus droit (extrémités à SNAP_M près, déviation d'au plus CONTINUE_DEG ; à un
     * aiguillage, la voie directe plutôt que la branche déviée), et ainsi de suite. Une branche courte
     * qui rejoint la voie (Y) ne passe donc pas devant la voie qu'elle rejoint.
     *
     * @return array<int, float> cid => longueur cumulée de sa voie continue (m)
     */
    private function continuousTracks(): array
    {
        $rows = DB::select(<<<'SQL'
            WITH e AS (
                SELECT c.cid, e.k, e.p, c.len,
                       ST_Azimuth(ST_LineInterpolatePoint(c.g, CASE WHEN e.k = 0 THEN LEAST(0.5, 10 / c.len) ELSE GREATEST(0.5, 1 - 10 / c.len) END), e.p) AS az
                FROM sx_chain c, LATERAL (VALUES (0, ST_StartPoint(c.g)), (1, ST_EndPoint(c.g))) e(k, p)
            )
            SELECT a.cid AS a, a.k AS ak, b.cid AS b, b.k AS bk,
                   degrees(abs(atan2(sin(a.az - b.az - pi()), cos(a.az - b.az - pi())))) AS dev
            FROM e a JOIN e b ON b.cid <> a.cid AND ST_DWithin(a.p, b.p, :snap)
            ORDER BY a.cid, a.k, dev, b.cid, b.k
            SQL, ['snap' => self::SNAP_M]);
        $next = [];
        foreach ($rows as $r) {
            if (! isset($next[$r->a][$r->ak]) && (float) $r->dev <= self::CONTINUE_DEG) {
                $next[$r->a][$r->ak] = [(int) $r->b, 1 - (int) $r->bk];
            }
        }
        $len = [];
        foreach (DB::select('SELECT cid, len FROM sx_chain') as $c) {
            $len[(int) $c->cid] = (float) $c->len;
        }
        // Prolongement depuis le bout $k de la chaîne $cid (mémorisé ; une boucle s'arrête où elle repasse).
        $memo = [];
        $ext = function (int $cid, int $k, array $seen) use (&$ext, &$memo, $next, $len): float {
            if (isset($memo[$cid][$k])) {
                return $memo[$cid][$k];
            }
            if (! isset($next[$cid][$k]) || isset($seen[$next[$cid][$k][0]])) {
                return 0.0;
            }
            [$d, $dk] = $next[$cid][$k];
            $seen[$d] = true;

            return $memo[$cid][$k] = $len[$d] + $ext($d, $dk, $seen);
        };
        $out = [];
        foreach ($len as $cid => $l) {
            $out[$cid] = $l + $ext($cid, 0, [$cid => true]) + $ext($cid, 1, [$cid => true]);
        }

        return $out;
    }

    private function hasSelfOverlapCandidate(array $chains): bool
    {
        // Une seule chaîne : seul un tracé qui repasse sur lui-même (GPX aller-retour) peut être dédoublé.
        return $chains && reset($chains)['len'] > 2 * self::SELF_SPAN_M;
    }

    /**
     * Voisins de chaque échantillon : [cid][j] => liste de [ocid, d, angle, loc, side].
     *
     * @return array<int, array<int, array<int, array{0: int, 1: float, 2: float, 3: float, 4: int}>>>
     */
    private function candidates(): array
    {
        DB::insert(<<<'SQL'
            INSERT INTO sx_piece (cid, off, plen, clen, g)
            SELECT c.cid, c.len * i / q.n, c.len / q.n, c.len, ST_LineSubstring(c.g, i::float8 / q.n, (i + 1)::float8 / q.n)
            FROM sx_chain c, LATERAL (SELECT CEIL(c.len / 200)::int AS n) q, LATERAL generate_series(0, q.n - 1) i
            SQL);
        DB::statement('ANALYZE sx_piece');
        DB::insert(<<<'SQL'
            INSERT INTO sx_samp (cid, j, pos, pt, az)
            SELECT c.cid, j, (j + 0.5) / c.n * c.len, ST_LineInterpolatePoint(c.g, (j + 0.5) / c.n),
                   ST_Azimuth(ST_LineInterpolatePoint(c.g, GREATEST(0, (j + 0.5) / c.n - 6 / c.len)),
                              ST_LineInterpolatePoint(c.g, LEAST(1, (j + 0.5) / c.n + 6 / c.len)))
            FROM sx_chain c, LATERAL generate_series(0, c.n - 1) j
            SQL);

        $rows = DB::select(<<<'SQL'
            SELECT s.cid, s.j, x.ocid, x.d, x.ang, x.loc, x.side
            FROM sx_samp s
            CROSS JOIN LATERAL (
                SELECT DISTINCT ON (p.cid) p.cid AS ocid, ST_Distance(p.g, s.pt) AS d, p.off + f.f * p.plen AS loc,
                       degrees(asin(LEAST(1, abs(sin(a.az - s.az))))) AS ang,
                       -- Côté de la voisine par rapport au sens de la chaîne échantillonnée.
                       CASE WHEN ST_Distance(p.g, s.pt) < 0.05 THEN 0
                            ELSE sign(sin(ST_Azimuth(s.pt, ST_LineInterpolatePoint(p.g, f.f)) - s.az))::int END AS side
                FROM sx_piece p
                CROSS JOIN LATERAL (SELECT ST_LineLocatePoint(p.g, s.pt) AS f) f
                CROSS JOIN LATERAL (SELECT ST_Azimuth(ST_LineInterpolatePoint(p.g, GREATEST(0, f.f - 6 / p.plen)),
                                                      ST_LineInterpolatePoint(p.g, LEAST(1, f.f + 6 / p.plen))) AS az) a
                WHERE ST_DWithin(p.g, s.pt, :d)
                  AND (p.cid <> s.cid OR abs(p.off + f.f * p.plen - s.pos) > :self)
                ORDER BY p.cid, ST_Distance(p.g, s.pt)
            ) x
            SQL, ['d' => self::PARALLEL_M, 'self' => self::SELF_SPAN_M]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->cid][(int) $r->j][] = [(int) $r->ocid, (float) $r->d, (float) ($r->ang ?? 0), (float) $r->loc, (int) $r->side];
        }

        return $out;
    }

    /**
     * Glouton : chaînes par fiabilité puis longueur ; portions dans l'ombre
     * d'une chaîne déjà gardée retirées.
     *
     * @return array{0: array<int, array<int, array{0: float, 1: float}>>, 1: array<int, array<string, mixed>>}
     *                                                                                                          intervalles retirés (m) par chaîne, enregistrements (retirés / distincts gardés)
     */
    private function greedy(array $chains, array $cands): array
    {
        $order = array_values($chains);
        usort($order, fn ($a, $b) => [$a['rank'], -round($a['glen'], 3), -$a['len'], $a['cid']] <=> [$b['rank'], -round($b['glen'], 3), -$b['len'], $b['cid']]);

        $kept = [];      // cid => intervalles retirés (chaîne déjà traitée, au moins en partie gardée)
        $removed = [];
        $records = [];
        foreach ($order as $c) {
            $cid = $c['cid'];
            $n = $c['n'];
            $step = $c['len'] / $n;
            $shadow = array_fill(0, $n, null);   // [d, side, ocid]
            $distinct = array_fill(0, $n, null); // [d, ocid] : tracé distinct gardé
            $near = array_fill(0, $n, false);
            for ($j = 0; $j < $n; $j++) {
                $pos = ($j + 0.5) * $step;
                foreach ($cands[$cid][$j] ?? [] as [$ocid, $d, $ang, $loc, $side]) {
                    if ($ocid === $cid) {
                        // La même chaîne, plus tôt : tracé qui repasse sur lui-même.
                        if ($loc >= $pos - self::SELF_SPAN_M || $d > self::SELF_DUP_M || $this->inIntervals($removed[$cid] ?? [], $loc)) {
                            continue;
                        }
                    } elseif (! isset($kept[$ocid]) || $this->inIntervals($kept[$ocid], $loc)) {
                        continue;
                    }
                    $o = $chains[$ocid];
                    $limit = $c['coarse'] && $o['coarse'] ? self::PARALLEL_COARSE_M : self::PARALLEL_M;
                    if ($d > $limit) {
                        continue;
                    }
                    $near[$j] = true;
                    // Projeté au-delà de l'extrémité de la voisine : prolongement, pas un doublon latéral.
                    if ($ang > self::MAX_ANGLE || $loc <= 1 || $loc >= $o['len'] - 1) {
                        continue;
                    }
                    if ($ocid !== $cid && $this->distinct($c, $o, $d)) {
                        if ($distinct[$j] === null || $d < $distinct[$j][0]) {
                            $distinct[$j] = [$d, $ocid];
                        }

                        continue;
                    }
                    if ($shadow[$j] === null || $d < $shadow[$j][0]) {
                        $shadow[$j] = [$d, $side, $ocid];
                    }
                }
                // Un échantillon à la fois doublon d'une chaîne et distinct d'une autre : doublon.
                if ($shadow[$j] !== null) {
                    $distinct[$j] = null;
                }
            }

            $cut = [];
            foreach ($this->runs($shadow, $distinct) as [$j0, $j1]) {
                $whole = $j0 === 0 && $j1 === $n - 1;
                // Recul jusqu'au point de divergence (l'écart cesse de diminuer vers l'intérieur).
                while ($j1 < $n - 1 && $j1 > $j0 && $shadow[$j1] && $shadow[$j1 - 1] && $shadow[$j1 - 1][0] < $shadow[$j1][0] - self::TRIM_DROP_M) {
                    $j1--;
                }
                while ($j0 > 0 && $j0 < $j1 && $shadow[$j0] && $shadow[$j0 + 1] && $shadow[$j0 + 1][0] < $shadow[$j0][0] - self::TRIM_DROP_M) {
                    $j0++;
                }
                $this->debug && $this->debugRuns[] = ['cid' => $cid, 'j0' => $j0, 'j1' => $j1, 'n' => $n, 'len' => round(($j1 - $j0 + 1) * $step),
                    'whole' => $whole, 'crossing' => $this->crossing($shadow, $j0, $j1), 'd' => array_map(fn ($x) => $x ? round($x[0], 1).($x[1] < 0 ? 'L' : 'R') : '-', array_slice($shadow, $j0, $j1 - $j0 + 1))];
                if (! $whole && ($j1 - $j0 + 1) * $step < self::MIN_RUN_M) {
                    continue;
                }
                if ($this->crossing($shadow, $j0, $j1)) {
                    continue;
                }
                $a = $j0 === 0 ? 0.0 : $j0 * $step;
                $b = $j1 === $n - 1 ? $c['len'] : ($j1 + 1) * $step;
                $cut[] = [$a, $b];
                $records[] = $this->record($c, $chains, $shadow, $j0, $j1, $a, $b, 'removed');
            }
            // Restes courts, tout entiers près de la géométrie gardée : retirés aussi.
            if ($cut) {
                foreach ($this->complement($cut, $c['len']) as [$a, $b]) {
                    if ($b - $a >= self::MIN_REST_M) {
                        continue;
                    }
                    $ja = (int) floor($a / $step);
                    $jb = min($n - 1, (int) ceil($b / $step) - 1);
                    $all = true;
                    for ($j = $ja; $j <= $jb; $j++) {
                        $all = $all && $near[$j];
                    }
                    if ($all) {
                        $cut[] = [$a, $b];
                    }
                }
                $cut = $this->mergeIntervals($cut);
            }
            // Tracés distincts gardés (enregistrés pour contrôle), séries d'au moins MIN_RUN_M.
            foreach ($this->runs(array_map(fn ($x) => $x ? [$x[0], 0, $x[1]] : null, $distinct), array_fill(0, $n, null)) as [$j0, $j1]) {
                if (($j1 - $j0 + 1) * $step >= self::MIN_RUN_M) {
                    $records[] = $this->record($c, $chains, array_map(fn ($x) => $x ? [$x[0], 0, $x[1]] : null, $distinct), $j0, $j1,
                        $j0 * $step, min($c['len'], ($j1 + 1) * $step), 'kept_distinct');
                }
            }
            $removed[$cid] = $cut;
            if ($this->complement($cut, $c['len'])) {
                $kept[$cid] = $cut;
            }
        }

        return [$removed, $records];
    }

    /** Séries d'échantillons dans l'ombre, trous de MAX_GAP_SAMPLES au plus (sans tracé distinct dedans). */
    private function runs(array $shadow, array $distinct): array
    {
        $n = count($shadow);
        $runs = [];
        $j = 0;
        while ($j < $n) {
            if ($shadow[$j] === null) {
                $j++;

                continue;
            }
            $j0 = $j;
            $j1 = $j;
            $k = $j + 1;
            while ($k < $n) {
                if ($shadow[$k] !== null) {
                    $j1 = $k++;

                    continue;
                }
                $g = $k;
                while ($g < $n && $shadow[$g] === null && $distinct[$g] === null && $g - $j1 <= self::MAX_GAP_SAMPLES) {
                    $g++;
                }
                if ($g < $n && $shadow[$g] !== null) {
                    $k = $g;

                    continue;
                }
                break;
            }
            $runs[] = [$j0, $j1];
            $j = $j1 + 1;
        }

        return $runs;
    }

    /**
     * Tracé qui passe d'un côté à l'autre d'une MÊME voisine (croisement en biais), pas un
     * doublon. (Un axe central entre deux axes gardés a une voisine de chaque côté : doublon.)
     */
    private function crossing(array $shadow, int $j0, int $j1): bool
    {
        $first = [];
        $last = [];
        for ($j = $j0; $j <= $j1; $j++) {
            if ($shadow[$j] && $shadow[$j][0] >= self::CROSS_M) {
                $first[$shadow[$j][2]] ??= $shadow[$j][1];
                $last[$shadow[$j][2]] = $shadow[$j][1];
            }
        }
        foreach ($first as $ocid => $side) {
            if ($side * $last[$ocid] < 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tracé réellement distinct de la voisine $o (plutôt qu'une seconde version du même axe) :
     * ancien tracé historique daté le long de la voie actuelle, deux anciens tracés datés
     * différemment, ou voie déposée / abandonnée le long d'une voie en service.
     */
    private function distinct(array $c, array $o, float $d): bool
    {
        if ($d < self::DISTINCT_M) {
            return false;
        }
        if ($c['hist'] !== $o['hist']) {
            $h = $c['hist'] ? $c : $o;
            if ($h['sig'] !== '') {
                return true;
            }

            return $c['life'] !== $o['life'] && $d >= self::LIFECYCLE_DISTINCT_M;
        }
        if ($c['hist'] && ($c['sig'] !== '' || $o['sig'] !== '') && $c['sig'] !== $o['sig']) {
            return true;
        }

        return $c['life'] !== $o['life'] && $d >= self::LIFECYCLE_DISTINCT_M;
    }

    private function record(array $c, array $chains, array $shadow, int $j0, int $j1, float $a, float $b, string $action): array
    {
        $ds = [];
        $by = [];
        for ($j = $j0; $j <= $j1; $j++) {
            if ($shadow[$j]) {
                $ds[] = $shadow[$j][0];
                $by[$shadow[$j][2]] = ($by[$shadow[$j][2]] ?? 0) + 1;
            }
        }
        sort($ds);
        arsort($by);
        $o = $chains[array_key_first($by)];
        if ($action === 'kept_distinct') {
            $reason = $c['hist'] || $o['hist'] ? 'ancien tracé daté' : 'voie déposée le long de la voie';
        } elseif ($o['cid'] === $c['cid']) {
            $reason = 'tracé repassant sur lui-même';
        } elseif ($c['fam'] === $o['fam'] && in_array($c['fam'], ['bdtopo', 'osm'], true) && $c['rank'] === $o['rank']) {
            $reason = $c['fam'].'/'.$o['fam'];
        } elseif ($c['coarse'] && $o['coarse']) {
            $reason = 'grossier/grossier';
        } else {
            $reason = 'entre sources';
        }

        return ['cid' => $c['cid'], 'ocid' => $o['cid'], 'a' => $a, 'b' => $b, 'action' => $action, 'reason' => $reason,
            'gap' => $ds ? $ds[intdiv(count($ds), 2)] : 0.0];
    }

    private function inIntervals(array $intervals, float $x): bool
    {
        foreach ($intervals as [$a, $b]) {
            if ($x >= $a && $x <= $b) {
                return true;
            }
        }

        return false;
    }

    private function mergeIntervals(array $iv): array
    {
        usort($iv, fn ($x, $y) => $x[0] <=> $y[0]);
        $out = [];
        foreach ($iv as [$a, $b]) {
            $last = count($out) - 1;
            if ($last >= 0 && $a <= $out[$last][1] + 0.01) {
                $out[$last][1] = max($out[$last][1], $b);
            } else {
                $out[] = [$a, $b];
            }
        }

        return $out;
    }

    /** Intervalles gardés (complément des intervalles retirés), d'au moins 1 m. */
    private function complement(array $cut, float $len): array
    {
        $out = [];
        $x = 0.0;
        foreach ($this->mergeIntervals($cut) as [$a, $b]) {
            if ($a - $x >= 1) {
                $out[] = [$x, $a];
            }
            $x = max($x, $b);
        }
        if ($len - $x >= 1) {
            $out[] = [$x, $len];
        }

        return $out;
    }

    /**
     * Réécrit les morceaux de la ligne (portions gardées), retire les raccords
     * pendants et raccorde les coupes et extrémités orphelines.
     *
     * @return float longueur des raccords ajoutés (m)
     */
    private function rewrite(array $chains, array $removed): float
    {
        $kc = ['cid' => [], 'a' => [], 'b' => []];
        $cuts = ['cid' => [], 'x' => []];   // points de coupe intérieurs
        foreach ($chains as $cid => $c) {
            $cut = $removed[$cid] ?? [];
            foreach ($this->complement($cut, $c['len']) as [$a, $b]) {
                $kc['cid'][] = $cid;
                $kc['a'][] = $a;
                $kc['b'][] = $b;
            }
            foreach ($this->mergeIntervals($cut) as [$a, $b]) {
                foreach ([$a, $b] as $x) {
                    if ($x > 0.5 && $x < $c['len'] - 0.5) {
                        $cuts['cid'][] = $cid;
                        $cuts['x'][] = $x;
                    }
                }
            }
        }
        DB::insert(<<<'SQL'
            INSERT INTO sx_kept (kid, cid, part_id, a, b, g)
            SELECT row_number() OVER (), c.cid, c.part_id, r.a, r.b,
                   ST_LineSubstring(c.g, LEAST(1, r.a / c.len), LEAST(1, r.b / c.len))
            FROM unnest(CAST(:cid AS int[]), CAST(:a AS float8[]), CAST(:b AS float8[])) AS r(cid, a, b)
            JOIN sx_chain c ON c.cid = r.cid
            SQL, ['cid' => $this->ints($kc['cid']), 'a' => $this->floats($kc['a']), 'b' => $this->floats($kc['b'])]);
        DB::statement('ANALYZE sx_kept');

        // Raccords existants : gardés si leurs deux bouts touchent encore la géométrie gardée (ou un autre raccord).
        DB::update(<<<'SQL'
            UPDATE sx_conn c SET keep = (
                SELECT bool_and(EXISTS (SELECT 1 FROM sx_kept k WHERE ST_DWithin(k.g, e.p, 1))
                             OR EXISTS (SELECT 1 FROM sx_conn o WHERE (o.part_id, o.sub) <> (c.part_id, c.sub) AND ST_DWithin(o.g, e.p, 0.5)))
                FROM (VALUES (ST_StartPoint(c.g)), (ST_EndPoint(c.g))) e(p))
            SQL);

        // Points à raccorder : coupes, et extrémités de chaîne gardées qui touchaient une portion retirée
        // (ou un raccord retiré) et ne touchent plus rien.
        $stitch = DB::select(<<<'SQL'
            WITH cutp AS (
                SELECT k.kid, ST_LineInterpolatePoint(c.g, LEAST(1, r.x / c.len)) AS p
                FROM unnest(CAST(:cid AS int[]), CAST(:x AS float8[])) AS r(cid, x)
                JOIN sx_chain c ON c.cid = r.cid
                JOIN sx_kept k ON k.cid = r.cid AND (abs(k.a - r.x) < 0.01 OR abs(k.b - r.x) < 0.01)
            ),
            endp AS (
                SELECT k.kid, e.p FROM sx_kept k
                JOIN sx_chain c ON c.cid = k.cid
                CROSS JOIN LATERAL (VALUES (ST_StartPoint(k.g), k.a < 0.01), (ST_EndPoint(k.g), k.b > c.len - 0.01)) e(p, chain_end)
                WHERE e.chain_end
                  AND (EXISTS (SELECT 1 FROM sx_chain o WHERE o.cid <> k.cid AND ST_DWithin(o.g, e.p, 0.5))
                       OR EXISTS (SELECT 1 FROM sx_conn o WHERE NOT o.keep AND ST_DWithin(o.g, e.p, 0.5)))
                  AND NOT EXISTS (SELECT 1 FROM sx_kept o WHERE o.kid <> k.kid AND ST_DWithin(o.g, e.p, 0.5))
                  AND NOT EXISTS (SELECT 1 FROM sx_conn o WHERE o.keep AND ST_DWithin(o.g, e.p, 0.5))
            ),
            pts AS (SELECT * FROM cutp UNION ALL SELECT * FROM endp),
            st AS (
                SELECT k.part_id, LEAST(k.kid, n.kid) AS k1, GREATEST(k.kid, n.kid) AS k2,
                       ST_MakeLine(pts.p, q.q) AS g, ST_Distance(pts.p, q.q) AS d
                FROM pts JOIN sx_kept k ON k.kid = pts.kid
                CROSS JOIN LATERAL (
                    SELECT o.kid, o.g, ST_Distance(o.g, pts.p) AS d FROM sx_kept o
                    WHERE o.kid <> pts.kid AND ST_DWithin(o.g, pts.p, :max)
                    ORDER BY o.g <-> pts.p LIMIT 1
                ) n
                -- Point le plus proche à moins de END_SNAP_M d'une extrémité de la portion visée : raccord
                -- vers cette extrémité (sinon il laisse un bout de quelques mètres, tronçon isolé), sauf
                -- si ce raccord-là longerait sa propre portion (cf. ci-dessous) : point le plus proche.
                CROSS JOIN LATERAL (SELECT ST_ClosestPoint(n.g, pts.p) AS c) c
                CROSS JOIN LATERAL (
                    SELECT COALESCE((SELECT v.e FROM (VALUES (ST_StartPoint(n.g)), (ST_EndPoint(n.g))) v(e)
                                     WHERE ST_Distance(v.e, c.c) <= :endsnap AND ST_Distance(v.e, pts.p) <= :max2
                                       AND ST_Distance(ST_LineInterpolatePoint(ST_MakeLine(pts.p, v.e), 0.5), k.g) >= 0.25 * ST_Distance(pts.p, v.e)
                                     ORDER BY ST_Distance(v.e, c.c) LIMIT 1), c.c) AS q
                ) q
                WHERE n.d > 0.5
                  -- Raccord qui repart le long de sa propre portion (vers la portion suivante) : faux raccord.
                  AND ST_Distance(ST_LineInterpolatePoint(ST_MakeLine(pts.p, q.q), 0.5), k.g) >= 0.25 * ST_Distance(pts.p, q.q)
            ),
            o AS (SELECT st.*, row_number() OVER (ORDER BY st.d, st.part_id, ST_AsEWKB(st.g)) AS rn FROM st)
            SELECT o.part_id, encode(ST_AsEWKB(o.g), 'hex') AS ewkb, o.d
            FROM o
            -- Un trou recousu depuis ses deux bords donnerait deux raccords (superposés, en X ou en
            -- échelle entre deux voies parallèles) : un seul, le plus court, entre deux mêmes portions.
            WHERE NOT EXISTS (
                SELECT 1 FROM o t WHERE t.rn < o.rn
                  AND ((ST_DWithin(t.g, ST_StartPoint(o.g), 0.5) AND ST_DWithin(t.g, ST_EndPoint(o.g), 0.5))
                       OR (t.k1 = o.k1 AND t.k2 = o.k2 AND ST_HausdorffDistance(t.g, o.g) <= GREATEST(10, t.d))))
            SQL, ['cid' => $this->ints($cuts['cid']), 'x' => $this->floats($cuts['x']), 'max' => self::MAX_STITCH_M,
            'max2' => self::MAX_STITCH_M, 'endsnap' => self::END_SNAP_M]);

        // Morceaux réécrits : portions gardées ; morceau entièrement retiré supprimé.
        $touched = array_values(array_unique(array_map(fn ($cid) => $chains[$cid]['part_id'],
            array_keys(array_filter($removed)))));
        DB::update(<<<'SQL'
            UPDATE line_reference_parts p SET geom = x.g, length_m = x.len, updated_at = NOW()
            FROM (
                SELECT k.part_id, ST_Multi(ST_Transform(ST_LineMerge(ST_Collect(k.g ORDER BY k.cid, k.a)), 4326)) AS g,
                       ROUND(SUM(ST_Length(k.g)))::int AS len
                FROM sx_kept k WHERE k.part_id = ANY(CAST(:parts AS bigint[])) GROUP BY k.part_id
            ) x
            WHERE p.id = x.part_id
            SQL, ['parts' => $this->ints($touched)]);
        DB::delete('DELETE FROM line_reference_parts p WHERE p.id = ANY(CAST(? AS bigint[])) AND NOT EXISTS (SELECT 1 FROM sx_kept k WHERE k.part_id = p.id)',
            [$this->ints($touched)]);

        // Raccords pendants retirés.
        DB::update(<<<'SQL'
            UPDATE line_reference_parts p SET geom = x.g, length_m = x.len, updated_at = NOW()
            FROM (
                SELECT c.part_id, ST_Multi(ST_Transform(ST_Collect(c.g), 4326)) AS g, ROUND(SUM(ST_Length(c.g)))::int AS len
                FROM sx_conn c WHERE c.keep AND c.part_id IN (SELECT part_id FROM sx_conn WHERE NOT keep)
                GROUP BY c.part_id
            ) x
            WHERE p.id = x.part_id
            SQL);
        DB::delete('DELETE FROM line_reference_parts p WHERE p.id IN (SELECT part_id FROM sx_conn GROUP BY part_id HAVING NOT bool_or(keep))');

        // Nouveaux raccords : source et ids du morceau qu'ils prolongent.
        $len = 0.0;
        $byPart = [];
        foreach ($stitch as $s) {
            $byPart[$s->part_id][] = $s->ewkb;
            $len += (float) $s->d;
        }
        foreach ($byPart as $partId => $geoms) {
            DB::insert(<<<'SQL'
                INSERT INTO line_reference_parts (line_id, source, source_trace_ids, matched_source, matched_trace_ids, length_m, geom, created_at, updated_at)
                SELECT p.line_id, p.source, p.source_trace_ids, 'connector', NULL, ROUND(ST_Length(g.g))::int, ST_Multi(ST_Transform(g.g, 4326)), NOW(), NOW()
                FROM line_reference_parts p,
                     LATERAL (SELECT ST_Collect(ARRAY(SELECT ST_GeomFromEWKB(decode(x, 'hex')) FROM unnest(CAST(:geoms AS text[])) x)) AS g) g
                WHERE p.id = :part
                SQL, ['geoms' => '{'.implode(',', $geoms).'}', 'part' => $partId]);
        }

        return $len;
    }

    /** Enregistre les portions retirées et les tracés distincts gardés (line_reference_duplicates). */
    private function storeRecords(int $lineId, array $records): void
    {
        if (! $records) {
            return;
        }
        $cols = ['cid' => [], 'ocid' => [], 'a' => [], 'b' => [], 'action' => [], 'reason' => [], 'gap' => []];
        foreach ($records as $r) {
            foreach ($cols as $k => $_) {
                $cols[$k][] = $r[$k];
            }
        }
        DB::insert(<<<'SQL'
            INSERT INTO line_reference_duplicates (line_id, action, reason, source, matched_source, trace_ids, kept_source, kept_matched_source,
                kept_trace_ids, length_m, gap_median_m, geom, created_at, updated_at)
            SELECT :line, r.action, r.reason, c.source, c.matched,
                   pg_temp.sx_ids(c.part_id, c.matched, x.g, 2), o.source, o.matched,
                   pg_temp.sx_ids(o.part_id, o.matched, ST_CollectionExtract(ST_Intersection(o.g, ST_Buffer(x.g, :maxd)), 2), 2),
                   ROUND(r.b - r.a)::int, ROUND(r.gap::numeric, 1), ST_Transform(x.g, 4326), NOW(), NOW()
            FROM unnest(CAST(:cid AS int[]), CAST(:ocid AS int[]), CAST(:a AS float8[]), CAST(:b AS float8[]),
                        CAST(:action AS text[]), CAST(:reason AS text[]), CAST(:gap AS float8[])) AS r(cid, ocid, a, b, action, reason, gap)
            JOIN sx_chain c ON c.cid = r.cid
            JOIN sx_chain o ON o.cid = r.ocid
            CROSS JOIN LATERAL (SELECT ST_LineSubstring(c.g, LEAST(1, r.a / c.len), LEAST(1, r.b / c.len)) AS g) x
            WHERE ST_GeometryType(x.g) = 'ST_LineString'
            SQL, [
            'line' => $lineId, 'maxd' => self::PARALLEL_M,
            'cid' => $this->ints($cols['cid']), 'ocid' => $this->ints($cols['ocid']),
            'a' => $this->floats($cols['a']), 'b' => $this->floats($cols['b']),
            'action' => '{'.implode(',', $cols['action']).'}',
            'reason' => '{'.implode(',', array_map(fn ($s) => '"'.$s.'"', $cols['reason'])).'}',
            'gap' => $this->floats($cols['gap']),
        ]);
    }

    private function ints(array $v): string
    {
        return '{'.implode(',', array_map('intval', $v)).'}';
    }

    private function floats(array $v): string
    {
        return '{'.implode(',', array_map(fn ($x) => sprintf('%.6F', $x), $v)).'}';
    }
}
