<?php

namespace App\Console\Commands;

use App\Services\JunctionRepair;
use App\Support\JunctionCurve;
use App\Support\Lgv;
use App\Support\TrackPriority;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Voies partagées entre lignes DIFFÉRENTES : remplit `line_segment_covers`.
 *
 * Sur un tronc commun, plusieurs lignes VFF ont chacune une portion
 * (line_segments) sur la même voie physique : ligne et raccordement, deux
 * lignes RFN qui partagent une section, ligne archeo « secondaire » ou
 * « particulier » posée sur une ligne SNCF, ligne « lo-* » et ligne RFN…
 * Selon l'ordre des entités dans la tuile, la même voie apparaissait de
 * couleurs ou de statuts différents (tirets rouges d'une ligne fermée sur
 * une voie en service, fret sur mixte…).
 *
 * Règle : à chaque ère, la carte montre l'état le PLUS ACTIF de la voie
 * (App\Support\TrackPriority). Pour chaque portion, on calcule la partie
 * située à moins de TOLERANCE_M (10 m) d'une portion PLUS PRIORITAIRE d'une
 * autre ligne sur au moins MIN_SHARED_M (100 m) : elle est masquée sur la
 * carte (tuiles : entité `covered` = true), le reste reste dessiné. Les
 * croisements et les jonctions courtes ne sont pas concernés ; une voie
 * parallèle distincte (faisceau, à plus de 10 m) reste dessinée.
 *
 * Continuité (continuity) : un tracé peu précis qui longe la voie d'une autre
 * ligne n'est masqué que là où il en passe à moins de 10 m ; ailleurs (écarts
 * de 10 à 25 m du tracé grossier) il restait dessiné par morceaux : bouts
 * décalés, tirets, escaliers, et bout de ligne qui s'arrête à 10 m de la voie
 * qu'il rejoint (Vire, 413000 le long de 405000). Deux règles, à chaque ère :
 *   1. Morceau dessiné d'une ligne coincé entre deux parties masquées de
 *      cette même ligne (ou entre une partie masquée et le bout de la ligne),
 *      qui reste à moins de CORRIDOR_M de la ligne plus active sur toute sa
 *      longueur (au plus FRAGMENT_MAX_M, TAIL_MAX_M pour un bout de ligne) :
 *      masqué lui aussi (la ligne est sur la même voie, dessinée de façon
 *      imprécise). Un morceau touché par une autre partie dessinée de la ligne
 *      (embranchement, suite de la ligne) n'est jamais masqué.
 *   2. Bout dessiné qui s'arrête au début d'une partie masquée, à plus de
 *      JOIN_MIN_M de la ligne qui la masque : l'approche reste dessinée jusqu'au
 *      premier contact (JOIN_SEARCH_M au plus ; à défaut, si le bout est à
 *      plus de JOIN_MAX_M, jusqu'au premier point à JOIN_MAX_M) ; sans contact, raccord de
 *      dessin jusqu'à la voie de la ligne qui masque, vers le point situé JOIN_AHEAD_M plus loin (angle doux, pas de
 *      marche d'escalier), dans visible_geom de la partie masquée. Pas de
 *      bout qui flotte à côté de la voie qu'il rejoint.
 *      La voie qui masque se mesure sur son DESSIN : les parties dessinées des
 *      portions qui masquent la suite, ou de celles qui les masquent à leur tour
 *      (chaîne, autres lignes que celle du bout), pas leurs parties masquées.
 *      Suite masquée sous une chaîne qu'aucune ligne ne dessine à moins de
 *      CORRIDOR_M : elle reste dessinée en entier (sinon la voie disparaît de la
 *      carte : La Voulte, 800000 sous le raccordement 800316, lui-même masqué
 *      sous le tracé grossier de 913000 à 36 m).
 *   3. Contact à 0 m (contact) : sur la géométrie dessinée de TOUTES les lignes
 *      de l'ère, le bout d'une branche qui rejoint une autre ligne (suite
 *      masquée sous elle, ou arrivée à moins de JOIN_MAX_M, cap vers elle) finit
 *      exactement SUR son dessin : raccord en biseau jusqu'au point le plus
 *      proche (ST_ClosestPoint), ou bout qui dépasse coupé au contact. Les
 *      autres bouts (heurtoir, terminus le long d'une autre ligne) restent.
 *   4. Lissage (smooth) : bout de jonction (posé sur le dessin d'une autre ligne) qui y arrive en coude, en
 *      crochet ou en biseau (cassure > JunctionCurve::COUDE_DEG sur les 120 derniers m, ou arrivée >
 *      JunctionCurve::ARRIVAL_DEG) : remplacé par une courbe tangente à la branche EN AMONT du coude (son cap, pas la
 *      projection orthogonale) et à la ligne rejointe (angle d'aiguillage 6,3°, au plus 15°), point de jonction glissé
 *      le long de celle-ci dans le sens de la branche, dernier sommet exactement sur son dessin (0 m). Branche
 *      précise (BD TOPO, OSM, SNCF) : seulement si son coude dépasse 35°. Ni croisement d'une autre voie, ni
 *      courbe de plus de 250 m, ni départ au-delà d'une autre jonction de la branche ; sinon dessin laissé tel quel.
 *
 * line_segments n'est PAS modifié (moteur décennal inchangé, fiche ligne
 * complète). Une entrée devient caduque dès que la portion ou une portion
 * recouvrante est régénérée ou change d'état : les tuiles l'ignorent alors
 * (portion dessinée entière, ordre de dessin `draw_rank`) jusqu'au prochain
 * passage de cette commande.
 *
 *   php artisan vff:build-shared-track                 # toutes les ères
 *   php artisan vff:build-shared-track --era=1980
 *   php artisan vff:build-shared-track --shard=2/6     # ères dont le rang % 6 = 1
 */
class BuildSharedTrack extends Command
{
    protected $signature = 'vff:build-shared-track
        {--era= : limite à une ère (année)}
        {--shard= : traitement parallèle « i/n » (ex. 1/6) : ne prend que les ères de rang % n = i-1}';

    /** Morceau dessiné à moins de ça (m) de la ligne plus active sur toute sa longueur : même voie, tracé imprécis. */
    public const CORRIDOR_M = 25.0;

    /** Longueur max (m) d'un morceau coincé entre deux parties masquées de la ligne. */
    public const FRAGMENT_MAX_M = 400.0;

    /** Longueur max (m) d'un bout de ligne au-delà d'une partie masquée. */
    public const TAIL_MAX_M = 150.0;

    /** Raccord de dessin : bout à plus de JOIN_MIN_M et au plus JOIN_MAX_M de la ligne qui masque ; visée JOIN_AHEAD_M plus loin. */
    public const JOIN_MIN_M = 1.5;

    public const JOIN_MAX_M = 15.0;

    public const JOIN_AHEAD_M = 30.0;

    /** Règle 3 (contact) : écart (m) en deçà duquel le bout est sur la ligne qu'il rejoint. */
    public const CONTACT_EPS_M = 0.01;

    /** Règle 3 : prolongement (m) du bout qui doit passer à moins de JOIN_MIN_M de la ligne pour une « arrivée ». */
    public const CONTACT_RAY_M = 60.0;

    /**
     * Règle 3 : écart (m) toléré entre ce prolongement et la ligne, un peu plus large que JOIN_MIN_M (le contrôle
     * jonctions-detecteur/check_join_0m.sql compte avec JOIN_MIN_M sur une grille de 5 cm : aucun bout qu'il juge
     * « arrivée » n'échappe ainsi à la règle).
     */
    public const CONTACT_RAY_TOL_M = 2.0;

    /** Règle 3 : derniers mètres du dessin où un contact avec la ligne rejointe signale un bout qui dépasse. */
    public const CONTACT_TAIL_M = 30.0;

    /** Règle 3 : angle (°) au-delà duquel le raccord visé en biseau est remplacé par le plus court. */
    public const CONTACT_TURN_DEG = 45.0;

    /** Règle 4 (lissage) : bout de jonction = à au plus ça (m) du dessin d'une autre ligne (après la règle 3 : 0 m). */
    public const JUNCTION_ON_M = 0.05;

    /** Règle 4 : approche lue sur SMOOTH_SPAN_M m, ligne rejointe sur ±SMOOTH_WINDOW_M m autour du bout. */
    public const SMOOTH_SPAN_M = 260.0;

    public const SMOOTH_WINDOW_M = 400.0;

    /** Règle 4 : distance (m) minimale de la courbe au bout d'une autre ligne (au-delà de JOIN_MIN_M : pas d'« arrivée »). */
    public const SMOOTH_CLEAR_M = 2.0;

    /** Règle 4 : rayon plancher (m) de la courbe (cible : JunctionRepair::minRadius du réseau de la branche). */
    public const SMOOTH_FLOOR_M = 60.0;

    /** Contact (<= JOIN_MIN_M) cherché sur les JOIN_SEARCH_M premiers mètres de la partie masquée : approche laissée dessinée. */
    public const JOIN_SEARCH_M = 100.0;

    protected $description = 'Voies partagées entre lignes : masque, par ère, la portion d\'une ligne recouverte par une ligne plus active (line_segment_covers).';

    public function handle(): int
    {
        $eras = DB::table('eras')->orderBy('year')->get(['id', 'year']);
        if ($this->option('era') !== null) {
            $eras = $eras->where('year', (int) $this->option('era'))->values();
            if ($eras->isEmpty()) {
                $this->error('Ère inconnue : '.$this->option('era'));

                return self::INVALID;
            }
        }
        if ($shard = $this->option('shard')) {
            [$i, $n] = array_map('intval', explode('/', $shard));
            if ($n < 1 || $i < 1 || $i > $n) {
                $this->error("Shard invalide : {$shard} (attendu i/n, 1 ≤ i ≤ n)");

                return self::INVALID;
            }
            $eras = $eras->filter(fn ($e, $k) => $k % $n === $i - 1)->values();
        }

        DB::statement('SET work_mem = \'64MB\'');
        $total = ['rows' => 0, 'hidden' => 0, 'km' => 0.0];
        foreach ($eras as $era) {
            $started = microtime(true);
            $stats = DB::transaction(fn () => $this->buildEra((int) $era->id));
            $total['rows'] += $stats->rows;
            $total['hidden'] += $stats->hidden;
            $total['km'] += $stats->km;
            $this->line(sprintf('  %d : %d portions recouvertes (%d entièrement), %.1f km masqués ; continuité : %d morceaux masqués (%.1f km), %d approches dessinées jusqu\'au contact (dont %d suites masquées que rien ne dessine), %d raccords de dessin ; contact à 0 m : %d raccords, %d bouts coupés ; lissage : %d arrivées lissées, %d laissées (%.0f s)',
                $era->year, $stats->rows, $stats->hidden, $stats->km, $stats->fragments, $stats->fragments_km, $stats->approaches, $stats->orphans, $stats->joins, $stats->contacts, $stats->trims, $stats->smoothed, $stats->unsmoothed, microtime(true) - $started));
        }
        $this->info(sprintf('Voies partagées : %d portions recouvertes, %d entièrement, %.0f km masqués sur %d ère(s).',
            $total['rows'], $total['hidden'], $total['km'], $eras->count()));

        return self::SUCCESS;
    }

    private function buildEra(int $eraId): object
    {
        $rank = TrackPriority::rankSql(Lgv::sql('l', 'ls'));
        $tie = TrackPriority::tieSql('l');
        $state = TrackPriority::stateSql('ls');
        $tol = TrackPriority::TOLERANCE_M;

        // Portions de l'ère en Lambert-93 (mètres), avec leur bande de tolérance.
        // Extrémités plates : la bande d'une portion ne déborde pas au-delà de
        // son bout (sinon on masquerait 10 m de trop à chaque jonction).
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_shared_seg');
        DB::statement(<<<SQL
            CREATE TEMP TABLE tp_shared_seg AS
            SELECT ls.id, ls.line_id,
                   {$rank} AS rank, {$tie} AS tie, {$state} AS state,
                   ST_Transform(ls.geom, 2154) AS g,
                   ST_Buffer(ST_Transform(ls.geom, 2154), {$tol}, 'endcap=flat join=round') AS buf
            FROM line_segments ls
            JOIN lines l ON l.id = ls.line_id
            WHERE ls.era_id = {$eraId} AND ST_NPoints(ls.geom) >= 2
        SQL);
        DB::statement('CREATE INDEX ON tp_shared_seg USING gist (buf)');
        DB::statement('CREATE INDEX ON tp_shared_seg (id)');
        DB::statement('ANALYZE tp_shared_seg');

        DB::delete('DELETE FROM line_segment_covers WHERE era_id = ?', [$eraId]);

        DB::insert(<<<'SQL'
            INSERT INTO line_segment_covers
                (segment_id, era_id, covering_ids, covering_line_ids, covered_m, visible_m, signature, visible_geom, covered_geom, created_at, updated_at)
            WITH cand AS (
                -- Portions d'AUTRES lignes, strictement plus prioritaires, dont la bande touche la portion.
                SELECT a.id, a.g, array_agg(b.id) AS bids, ST_Union(b.buf) AS u
                FROM tp_shared_seg a
                JOIN tp_shared_seg b
                  ON b.buf && a.g AND b.line_id <> a.line_id
                 AND (b.rank, b.tie, -b.line_id) > (a.rank, a.tie, -a.line_id)
                 AND ST_Intersects(a.g, b.buf)
                GROUP BY a.id, a.g
            ),
            kept AS (
                -- Parties communes d'au moins MIN_SHARED_M (les croisements font ~20 m).
                SELECT c.id, ST_Union(d.geom) AS kept
                FROM cand c
                CROSS JOIN LATERAL ST_Dump(ST_LineMerge(ST_CollectionExtract(ST_Intersection(c.g, c.u), 2))) d
                WHERE ST_Length(d.geom) >= :min_shared
                GROUP BY c.id
            ),
            vis AS (
                -- Reste dessiné, sans les miettes de moins de MIN_REMAINDER_M.
                SELECT k.id, k.kept, c.g, c.bids,
                       (SELECT ST_Union(v.geom)
                          FROM ST_Dump(ST_LineMerge(ST_CollectionExtract(ST_Difference(c.g, ST_Buffer(k.kept, 0.05)), 2))) v
                         WHERE ST_Length(v.geom) >= :min_rem) AS visible
                FROM kept k JOIN cand c ON c.id = k.id
            ),
            fin AS (
                SELECT v.id, v.visible,
                       CASE WHEN v.visible IS NULL THEN v.g
                            ELSE ST_CollectionExtract(ST_Difference(v.g, ST_Buffer(v.visible, 0.05)), 2) END AS covered,
                       -- Recouvrantes effectives (pas les simples croisements) : servent à la signature.
                       (SELECT array_agg(b.id ORDER BY b.id) FROM tp_shared_seg b
                         WHERE b.id = ANY (v.bids) AND ST_Length(ST_Intersection(v.kept, b.buf)) >= 1) AS cov_ids
                FROM vis v
            )
            SELECT f.id, :era, f.cov_ids,
                   (SELECT jsonb_agg(DISTINCT b.line_id) FROM tp_shared_seg b WHERE b.id = ANY (f.cov_ids)),
                   ROUND(ST_Length(f.covered)), COALESCE(ROUND(ST_Length(f.visible)), 0),
                   (SELECT md5(string_agg(t.state, ',' ORDER BY t.id)) FROM tp_shared_seg t WHERE t.id = ANY (f.cov_ids || f.id)),
                   ST_Multi(ST_Transform(f.visible, 4326)), ST_Multi(ST_Transform(f.covered, 4326)), now(), now()
            FROM fin f
            WHERE f.cov_ids IS NOT NULL AND NOT ST_IsEmpty(f.covered)
        SQL, [
            'era' => $eraId,
            'min_shared' => TrackPriority::MIN_SHARED_M,
            'min_rem' => TrackPriority::MIN_REMAINDER_M,
        ]);

        $cont = $this->continuity($eraId);

        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_shared_seg');

        $stats = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) FILTER (WHERE covered_m > 0) AS rows, COUNT(*) FILTER (WHERE visible_geom IS NULL) AS hidden,
                   COALESCE(SUM(covered_m), 0) / 1000.0 AS km
            FROM line_segment_covers WHERE era_id = ?
        SQL, [$eraId]);

        return (object) ((array) $stats + $cont);
    }

    /**
     * Continuité du dessin (cf. docblock) : morceaux dessinés coincés entre des parties masquées
     * (règle 1, deux passes), puis raccords de dessin des bouts qui s'arrêtent à côté de la voie (règle 2).
     *
     * @return array{fragments: int, fragments_km: float, approaches: int, orphans: int, joins: int, contacts: int, trims: int, smoothed: int, unsmoothed: int}
     */
    private function continuity(int $eraId): array
    {
        $out = ['fragments' => 0, 'fragments_km' => 0.0, 'approaches' => 0, 'orphans' => 0, 'joins' => 0];
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_cv');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE tp_cv AS
            SELECT c.segment_id AS sid, s.line_id, c.covering_ids,
                   ST_Transform(c.visible_geom, 2154) AS vis, ST_Transform(c.covered_geom, 2154) AS cov, FALSE AS changed, FALSE AS added
            FROM line_segment_covers c JOIN tp_shared_seg s ON s.id = c.segment_id
            WHERE c.era_id = ?
            SQL, [$eraId]);
        DB::statement('CREATE INDEX ON tp_cv (sid)');
        for ($pass = 0; $pass < 2; $pass++) {
            $this->pieces();
            $frag = DB::select(<<<'SQL'
                WITH e AS (
                    SELECT d.pid, d.line_id, x.p FROM tp_dp d, LATERAL (VALUES (ST_StartPoint(d.g)), (ST_EndPoint(d.g))) x(p)
                ),
                st AS (
                    SELECT e.pid,
                           CASE WHEN EXISTS (SELECT 1 FROM tp_dp o WHERE o.line_id = e.line_id AND o.pid <> e.pid AND ST_DWithin(o.g, e.p, 0.5)) THEN 2
                                WHEN EXISTS (SELECT 1 FROM tp_cp c WHERE c.line_id = e.line_id AND ST_DWithin(c.g, e.p, 0.5)) THEN 1
                                ELSE 0 END AS s
                    FROM e
                ),
                f AS (SELECT pid, MAX(s) AS mx, COUNT(*) FILTER (WHERE s = 1) AS nm FROM st GROUP BY pid)
                SELECT d.pid, d.sid, d.line_id, u.ids
                FROM f JOIN tp_dp d ON d.pid = f.pid
                JOIN tp_shared_seg a ON a.id = d.sid
                CROSS JOIN LATERAL (
                    SELECT array_agg(b.id ORDER BY b.id) AS ids, ST_Collect(b.g) AS ug FROM tp_shared_seg b
                    WHERE b.line_id <> a.line_id AND (b.rank, b.tie, -b.line_id) > (a.rank, a.tie, -a.line_id)
                      AND b.g && ST_Expand(d.g, :corr) AND ST_DWithin(b.g, d.g, :corr2)
                ) u
                WHERE f.mx < 2 AND f.nm >= 1
                  -- Une autre partie dessinée de la ligne s'y raccorde (embranchement en T) : gardé.
                  AND NOT EXISTS (SELECT 1 FROM tp_dp o WHERE o.line_id = d.line_id AND o.pid <> d.pid AND ST_DWithin(o.g, d.g, 0.5))
                  AND ST_Length(d.g) <= CASE WHEN f.nm = 2 THEN CAST(:frag AS float8) ELSE CAST(:tail AS float8) END
                  AND u.ids IS NOT NULL
                  AND (SELECT MAX(ST_Distance(u.ug, x.geom)) FROM ST_DumpPoints(ST_Segmentize(d.g, 5)) x) <= :corr3
                ORDER BY d.pid
                SQL, ['corr' => self::CORRIDOR_M, 'corr2' => self::CORRIDOR_M, 'corr3' => self::CORRIDOR_M,
                'frag' => self::FRAGMENT_MAX_M, 'tail' => self::TAIL_MAX_M]);
            if (! $frag) {
                break;
            }
            foreach ($frag as $f) {
                $piece = DB::selectOne('SELECT g, ST_Length(g) AS m FROM tp_dp WHERE pid = ?', [$f->pid]);
                $out['fragments']++;
                $out['fragments_km'] += $piece->m / 1000;
                $exists = DB::selectOne('SELECT 1 AS x FROM tp_cv WHERE sid = ?', [$f->sid]);
                if ($exists) {
                    DB::update(<<<'SQL'
                        UPDATE tp_cv SET
                            vis = ST_CollectionExtract(ST_Difference(vis, ST_Buffer(CAST(:g AS geometry), 0.05)), 2),
                            cov = ST_LineMerge(ST_Union(cov, CAST(:g2 AS geometry))),
                            covering_ids = ARRAY(SELECT DISTINCT unnest(covering_ids || CAST(:ids AS bigint[])) ORDER BY 1),
                            changed = TRUE
                        WHERE sid = :sid
                        SQL, ['g' => $piece->g, 'g2' => $piece->g, 'ids' => $f->ids, 'sid' => $f->sid]);
                } else {
                    DB::insert(<<<'SQL'
                        INSERT INTO tp_cv (sid, line_id, covering_ids, vis, cov, changed, added)
                        VALUES (:sid, :line, CAST(:ids AS bigint[]), NULL, CAST(:g AS geometry), TRUE, TRUE)
                        SQL, ['sid' => $f->sid, 'line' => $f->line_id, 'ids' => $f->ids, 'g' => $piece->g]);
                }
            }
            DB::update('UPDATE tp_cv SET vis = NULL WHERE vis IS NOT NULL AND (ST_IsEmpty(vis) OR ST_Length(vis) < 0.01)');
        }

        // Règle 2 : bouts dessinés qui s'arrêtent à côté de la voie qui masque la suite de la ligne.
        $this->pieces();
        $ends = DB::select(<<<'SQL'
            WITH e AS (
                SELECT d.pid, d.line_id, x.p FROM tp_dp d, LATERAL (VALUES (ST_StartPoint(d.g)), (ST_EndPoint(d.g))) x(p)
            ),
            m AS (
                SELECT e.pid, e.line_id, e.p, c.sid AS csid, c.g AS cg
                FROM e JOIN LATERAL (
                    SELECT c.sid, c.g FROM tp_cp c WHERE c.line_id = e.line_id AND ST_DWithin(c.g, e.p, 0.5)
                    ORDER BY ST_Length(c.g) DESC, c.sid LIMIT 1
                ) c ON TRUE
                WHERE NOT EXISTS (SELECT 1 FROM tp_dp o WHERE o.line_id = e.line_id AND o.pid <> e.pid AND ST_DWithin(o.g, e.p, 0.5))
            ),
            u AS (
                -- C orientée depuis le bout dessiné ; ug : DESSIN de la voie qui masque C (parties dessinées des portions
                -- qui la masquent, ou de celles qui les masquent à leur tour, hors de la ligne du bout).
                SELECT m.pid, m.p, m.csid,
                       CASE WHEN ST_LineLocatePoint(m.cg, m.p) < 0.5 THEN m.cg ELSE ST_Reverse(m.cg) END AS cg,
                       (WITH RECURSIVE k(id, n) AS (
                            SELECT x.id, 1 FROM tp_cv v CROSS JOIN LATERAL unnest(v.covering_ids) x(id) WHERE v.sid = m.csid
                            UNION
                            SELECT x.id, k.n + 1 FROM k JOIN tp_cv w ON w.sid = k.id CROSS JOIN LATERAL unnest(w.covering_ids) x(id) WHERE k.n < 4
                        )
                        SELECT ST_Collect(CASE WHEN w.sid IS NULL THEN b.g ELSE w.vis END)
                        FROM tp_shared_seg b LEFT JOIN tp_cv w ON w.sid = b.id
                        WHERE b.id IN (SELECT k.id FROM k) AND b.line_id <> m.line_id) AS ug
                FROM m
            )
            SELECT u.pid, u.csid, ST_Distance(u.p, u.ug) AS d, ST_Length(u.cg) AS clen,
                   -- Aucune ligne ne dessine C à moins de CORRIDOR_M : la suite reste dessinée en entier.
                   (u.ug IS NULL OR NOT ST_DWithin(u.cg, u.ug, :corr)) AS orphan,
                   -- Premier point de C (pas de 2 m, dans les JOIN_SEARCH_M premiers mètres) au contact de la voie qui masque.
                   (SELECT MIN(k * 2.0) FROM generate_series(0, CAST(LEAST(:search, ST_Length(u.cg)) / 2 AS int)) k
                     WHERE ST_Distance(ST_LineInterpolatePoint(u.cg, LEAST(1, k * 2.0 / ST_Length(u.cg))), u.ug) <= :min) AS contact,
                   -- Sinon, premier point de C à au plus JOIN_MAX_M de cette voie (la règle 3 raccorde de là).
                   (SELECT MIN(k * 2.0) FROM generate_series(0, CAST(LEAST(:search2, ST_Length(u.cg)) / 2 AS int)) k
                     WHERE ST_Distance(ST_LineInterpolatePoint(u.cg, LEAST(1, k * 2.0 / ST_Length(u.cg))), u.ug) <= :max) AS near,
                   ST_MakeLine(u.p, ST_ClosestPoint(u.ug, ST_LineInterpolatePoint(u.cg, LEAST(1, :ahead / ST_Length(u.cg))))) AS conn,
                   u.cg
            FROM u WHERE ST_Length(u.cg) > 0
            ORDER BY u.csid, u.pid
            SQL, ['search' => self::JOIN_SEARCH_M, 'min' => self::JOIN_MIN_M, 'ahead' => self::JOIN_AHEAD_M,
            'search2' => self::JOIN_SEARCH_M, 'max' => self::JOIN_MAX_M, 'corr' => self::CORRIDOR_M]);
        $orphans = [];
        foreach ($ends as $j) {
            if (! $j->orphan && (float) $j->d <= self::JOIN_MIN_M) {
                continue;
            }
            // Partie masquée orpheline entre deux bouts dessinés de la ligne : rendue une seule fois.
            if ($j->orphan && isset($orphans[$j->csid.':'.round((float) $j->clen, 1)])) {
                continue;
            }
            if ($j->orphan) {
                $orphans[$j->csid.':'.round((float) $j->clen, 1)] = true;
            }
            // Bout à plus de JOIN_MAX_M de la voie qui masque la suite : approche dessinée jusqu'à JOIN_MAX_M d'elle.
            $far = (float) $j->d > self::JOIN_MAX_M && $j->near !== null && (float) $j->near > 0;
            if ($j->orphan || ($j->contact !== null && (float) $j->contact > 0) || $far) {
                // Suite que rien ne dessine : dessinée jusqu'au bout de la partie masquée.
                $j->contact = $j->orphan ? $j->clen : (($j->contact !== null && (float) $j->contact > 0) ? $j->contact : $j->near);
                $out['orphans'] += $j->orphan ? 1 : 0;
                // La ligne rejoint la voie un peu plus loin : son approche reste dessinée jusqu'au contact.
                DB::update(<<<'SQL'
                    UPDATE tp_cv SET
                        vis = ST_CollectionExtract(ST_Collect(ARRAY(SELECT x.geom FROM ST_Dump(COALESCE(vis, ST_GeomFromText('MULTILINESTRING EMPTY', 2154))) x
                                  UNION ALL SELECT ST_LineSubstring(CAST(:cg AS geometry), 0, LEAST(1, :a / ST_Length(CAST(:cg2 AS geometry)))))), 2),
                        cov = ST_CollectionExtract(ST_Difference(cov, ST_Buffer(ST_LineSubstring(CAST(:cg3 AS geometry), 0, LEAST(1, :a2 / ST_Length(CAST(:cg4 AS geometry)))), 0.05)), 2),
                        changed = TRUE
                    WHERE sid = :sid
                    SQL, ['cg' => $j->cg, 'cg2' => $j->cg, 'cg3' => $j->cg, 'cg4' => $j->cg, 'a' => (float) $j->contact, 'a2' => (float) $j->contact, 'sid' => $j->csid]);
                $out['approaches']++;
            } elseif ((float) $j->d <= self::JOIN_MAX_M) {
                DB::update(<<<'SQL'
                    UPDATE tp_cv SET vis = ST_CollectionExtract(ST_Collect(ARRAY(SELECT x.geom FROM ST_Dump(COALESCE(vis, ST_GeomFromText('MULTILINESTRING EMPTY', 2154))) x
                                                               UNION ALL SELECT CAST(:g AS geometry))), 2),
                                     changed = TRUE
                    WHERE sid = :sid
                    SQL, ['g' => $j->conn, 'sid' => $j->csid]);
                $out['joins']++;
            }
        }

        $out += $this->contact();

        // Écriture des entrées modifiées / ajoutées.
        DB::update(<<<'SQL'
            UPDATE line_segment_covers c SET
                visible_geom = CASE WHEN v.vis IS NULL OR ST_IsEmpty(v.vis) THEN NULL ELSE ST_Multi(ST_Transform(v.vis, 4326)) END,
                covered_geom = ST_Multi(ST_Transform(v.cov, 4326)),
                covered_m = ROUND(ST_Length(v.cov)), visible_m = COALESCE(ROUND(ST_Length(v.vis)), 0),
                covering_ids = v.covering_ids,
                covering_line_ids = (SELECT jsonb_agg(DISTINCT b.line_id) FROM tp_shared_seg b WHERE b.id = ANY (v.covering_ids)),
                signature = (SELECT md5(string_agg(t.state, ',' ORDER BY t.id)) FROM tp_shared_seg t WHERE t.id = ANY (v.covering_ids || v.sid)),
                updated_at = now()
            FROM tp_cv v
            WHERE v.changed AND NOT v.added AND c.segment_id = v.sid AND c.era_id = :era
            SQL, ['era' => $eraId]);
        DB::insert(<<<'SQL'
            INSERT INTO line_segment_covers
                (segment_id, era_id, covering_ids, covering_line_ids, covered_m, visible_m, signature, visible_geom, covered_geom, created_at, updated_at)
            SELECT v.sid, :era, v.covering_ids,
                   (SELECT jsonb_agg(DISTINCT b.line_id) FROM tp_shared_seg b WHERE b.id = ANY (v.covering_ids)),
                   ROUND(ST_Length(v.cov)), COALESCE(ROUND(ST_Length(v.vis)), 0),
                   (SELECT md5(string_agg(t.state, ',' ORDER BY t.id)) FROM tp_shared_seg t WHERE t.id = ANY (v.covering_ids || v.sid)),
                   CASE WHEN v.vis IS NULL OR ST_IsEmpty(v.vis) THEN NULL ELSE ST_Multi(ST_Transform(v.vis, 4326)) END,
                   ST_Multi(ST_Transform(v.cov, 4326)), now(), now()
            FROM tp_cv v WHERE v.added
            SQL, ['era' => $eraId]);
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_cv');
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_dp');
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_cp');

        return $out;
    }

    /**
     * Règle 3 (cf. docblock) : contact exact, à 0 m, du bout dessiné d'une branche avec la ligne qu'elle
     * rejoint, sur la géométrie DESSINÉE de toutes les lignes de l'ère (après les règles 1 et 2).
     *
     * Bout libre d'une ligne (aucune autre partie dessinée de la ligne à moins de 0,5 m) à plus de
     * CONTACT_EPS_M du dessin d'une autre ligne (ou d'un autre morceau de la même ligne à au plus JOIN_MIN_M :
     * portions d'états différents masquées l'une par l'autre), qui est une jonction réelle :
     *   - « masquée » : la ligne continue sous une ligne plus active (sa partie masquée part du bout), ou le bout
     *     est posé sur la partie masquée d'une autre ligne (relais : cette voie est dessinée par la ligne qui la masque),
     *     dessin de l'autre ligne à au plus CORRIDOR_M (la ligne qui masque peut elle-même être masquée là par une troisième) ;
     *   - « arrivée » (au plus JOIN_MAX_M) : à au plus JOIN_MIN_M (aucune voie n'est à 1,5 m d'une autre sans la rejoindre),
     *     ou cap vers elle (le prolongement du bout sur CONTACT_RAY_M passe à moins de CONTACT_RAY_TOL_M de son dessin).
     * Les autres bouts (heurtoir, terminus le long d'une autre ligne, ligne qui s'en éloigne) restent tels quels.
     * Correction :
     *   - bout qui dépasse (le dessin touche déjà l'autre ligne dans ses CONTACT_TAIL_M derniers mètres, et le
     *     bout n'a pas de cap vers une ligne plus loin) : coupé au dernier point de contact ;
     *   - deux lignes qui se passent le relais (bout d'en face : jonction de la ligne rejointe vers celle-ci, à
     *     moins de JOIN_AHEAD_M) : reliées bout à bout par UN raccord (ligne d'id le plus petit ; même ligne :
     *     premier morceau), jamais deux
     *     raccords parallèles ;
     *   - sinon raccord droit jusqu'à un point SUR le dessin de l'autre ligne : suite masquée, point le plus
     *     proche (ST_ClosestPoint) de la partie masquée 6 x l'écart plus loin (au moins 2 m, au plus JOIN_AHEAD_M) :
     *     arrivée en biseau (moins de 10 degrés pour un écart de 1 m) ; arrivée à au plus JOIN_MIN_M : de même,
     *     dans le prolongement du bout ; arrivée avec cap : prolongement droit du bout jusqu'à la ligne s'il la
     *     coupe à moins de ces 6 x l'écart, sinon en biseau de même (pas de long doublon presque parallèle). Si l'angle avec le bout dépasse CONTACT_TURN_DEG à au plus JOIN_MIN_M :
     *     raccord au plus court.
     * Deux passes (une cible coupée dans la même passe est revue). Une portion sans entrée en reçoit une :
     * covered_geom vide, covering_ids = portion(s) rejointe(s) (le raccord devient caduc si elles changent).
     *
     * @return array{contacts: int, trims: int, smoothed: int, unsmoothed: int}
     */
    private function contact(): array
    {
        $out = ['contacts' => 0, 'trims' => 0];
        $this->pieces();
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_ad');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE tp_ad AS
            SELECT row_number() OVER (ORDER BY s.id, d.path) AS pid, s.id AS sid, s.line_id, d.geom AS g
            FROM tp_shared_seg s LEFT JOIN tp_cv v ON v.sid = s.id,
                 LATERAL ST_Dump(CASE WHEN v.sid IS NULL THEN s.g ELSE v.vis END) d
            WHERE d.geom IS NOT NULL AND ST_Length(d.geom) > 0.01
            SQL);
        DB::statement('CREATE INDEX ON tp_ad USING gist (g)');
        DB::statement('CREATE INDEX ON tp_ad (pid)');
        DB::statement('CREATE INDEX ON tp_ad (line_id)');
        DB::statement('ANALYZE tp_ad');

        /** @var array<int, array<int, int>> $targets sid => portions rejointes */
        $targets = [];
        // Deux passes, puis le lissage (règle 4), puis une passe pour les bouts voisins d'une courbe nouvelle.
        $smoothed = false;
        for ($pass = 0; $pass < 3; $pass++) {
            if ($pass === 2) {
                $out += $this->smooth($targets);
                $smoothed = true;
            }
            $this->singleLines();
            $rows = DB::select(<<<'SQL'
                WITH e AS (
                    SELECT a.pid, a.sid, a.line_id, a.g, x.k, x.p,
                           ST_Azimuth(ST_LineInterpolatePoint(a.g, CASE WHEN x.k = 0 THEN LEAST(1, 30 / ST_Length(a.g)) ELSE GREATEST(0, 1 - 30 / ST_Length(a.g)) END), x.p) AS az
                    FROM tp_ad a, LATERAL (VALUES (0, ST_StartPoint(a.g)), (1, ST_EndPoint(a.g))) x(k, p)
                    WHERE NOT ST_IsClosed(a.g)
                      AND NOT EXISTS (SELECT 1 FROM tp_ad o WHERE o.line_id = a.line_id AND o.pid <> a.pid AND ST_DWithin(o.g, x.p, 0.5))
                      -- Bout posé sur son propre morceau (boucle) : pas un bout libre.
                      AND (ST_Length(a.g) <= 4 OR NOT ST_DWithin(CASE WHEN x.k = 0 THEN ST_LineSubstring(a.g, 2 / ST_Length(a.g), 1)
                                                                      ELSE ST_LineSubstring(a.g, 0, 1 - 2 / ST_Length(a.g)) END, x.p, 0.5))
                ),
                n AS (
                    SELECT e.*, o.line_id AS oline, ST_Distance(o.g, e.p) AS d
                    FROM e CROSS JOIN LATERAL (
                        -- Autre ligne ; ou autre morceau de la MÊME ligne à au plus JOIN_MIN_M (deux portions
                        -- d'états différents qui se masquent l'une l'autre : trou de dessin dans la ligne).
                        SELECT o.line_id, o.g FROM tp_ad o
                        WHERE ST_DWithin(o.g, e.p, :corr)
                          AND (o.line_id <> e.line_id OR (o.pid <> e.pid AND ST_DWithin(o.g, e.p, :minsame)))
                        ORDER BY ST_Distance(o.g, e.p), o.pid LIMIT 1
                    ) o
                    WHERE ST_Distance(o.g, e.p) > :eps AND e.az IS NOT NULL
                ),
                c AS (
                    SELECT n.*,
                           -- Dessin de la ligne rejointe (la plus proche) autour du bout.
                           (SELECT ST_Collect(o.g ORDER BY o.pid) FROM tp_ad o WHERE o.line_id = n.oline AND o.pid <> n.pid AND ST_DWithin(o.g, n.p, :reach)) AS og,
                           (SELECT cp.g FROM tp_cp cp WHERE cp.line_id = n.line_id AND ST_DWithin(cp.g, n.p, 0.5)
                             ORDER BY ST_Length(cp.g) DESC, cp.sid LIMIT 1) AS cg,
                           -- Bout posé sur la partie masquée d'une AUTRE ligne (relais : la voie continue, dessinée par
                           -- la ligne qui la masque) : point de cette partie, a plus loin, dans le sens du bout.
                           (SELECT CASE WHEN ST_Equals(x.a1, n.p) THEN x.a2 WHEN ST_Equals(x.a2, n.p) THEN x.a1
                                        WHEN degrees(abs(atan2(sin(ST_Azimuth(n.p, x.a1) - n.az), cos(ST_Azimuth(n.p, x.a1) - n.az))))
                                          <= degrees(abs(atan2(sin(ST_Azimuth(n.p, x.a2) - n.az), cos(ST_Azimuth(n.p, x.a2) - n.az))))
                                        THEN x.a1 ELSE x.a2 END
                              FROM tp_cp cp,
                                   LATERAL (SELECT ST_LineLocatePoint(cp.g, n.p) AS f, ST_Length(cp.g) AS len) l,
                                   LATERAL (SELECT ST_LineInterpolatePoint(cp.g, LEAST(1, l.f + LEAST(CAST(:ahead3 AS float8), GREATEST(2, 6 * n.d)) / l.len)) AS a1,
                                                   ST_LineInterpolatePoint(cp.g, GREATEST(0, l.f - LEAST(CAST(:ahead4 AS float8), GREATEST(2, 6 * n.d)) / l.len)) AS a2) x
                             WHERE cp.line_id <> n.line_id AND ST_DWithin(cp.g, n.p, 0.5)
                               AND NOT (ST_Equals(x.a1, n.p) AND ST_Equals(x.a2, n.p))
                             ORDER BY ST_Length(cp.g) DESC, cp.sid LIMIT 1) AS ha,
                           ST_MakeLine(n.p, ST_Translate(n.p, :ray * sin(n.az), :ray * cos(n.az))) AS ry,
                           -- Derniers mètres (au plus la moitié du morceau : l'autre bout a les siens).
                           CASE WHEN n.k = 0 THEN ST_LineSubstring(n.g, 0, LEAST(0.5, :tail / ST_Length(n.g)))
                                ELSE ST_LineSubstring(n.g, GREATEST(0.5, 1 - :tail2 / ST_Length(n.g)), 1) END AS tl,
                           LEAST(CAST(:ahead AS float8), GREATEST(2, 6 * n.d)) AS a
                    FROM n
                ),
                c2 AS MATERIALIZED (
                    SELECT c.*, ST_DWithin(c.og, c.ry, :raytol) AS conv,
                           -- Premier point où le prolongement du bout coupe le dessin de la ligne rejointe.
                           (SELECT x.geom FROM ST_DumpPoints(ST_Intersection(c.ry, c.og)) x ORDER BY ST_Distance(x.geom, c.p) LIMIT 1) AS hit,
                           -- Jonction réelle. Suite masquée : au plus CORRIDOR_M (couloir de la règle 1) ; arrivée : au plus JOIN_MAX_M.
                           (c.cg IS NOT NULL OR (c.ha IS NOT NULL AND c.d <= :corr2) OR (c.d <= :max AND (c.d <= :min2 OR ST_DWithin(c.og, c.ry, :raytol2)))) AS jn
                    FROM c
                ),
                j AS (
                    SELECT c2.*,
                           -- Dernier contact du dessin avec une autre ligne dans les derniers mètres (fraction le long du morceau).
                           (SELECT CASE WHEN c2.k = 0 THEN MIN(ST_LineLocatePoint(c2.g, x.geom)) ELSE MAX(ST_LineLocatePoint(c2.g, x.geom)) END
                              FROM tp_ad o, LATERAL ST_DumpPoints(ST_Intersection(c2.tl, o.g)) x
                             WHERE o.line_id <> c2.line_id AND o.g && c2.tl AND ST_Intersects(o.g, c2.tl)) AS cut,
                           -- Bout d'en face : jonction de la ligne rejointe vers celle-ci, à moins de JOIN_AHEAD_M (deux lignes
                           -- qui se passent le relais) : relié bout à bout, pas deux raccords parallèles.
                           q.p AS q, q.pid AS qpid, q.k AS qk
                    FROM c2
                    LEFT JOIN LATERAL (
                        SELECT m.p, m.pid, m.k FROM c2 m
                        WHERE m.jn AND m.line_id = c2.oline AND m.oline = c2.line_id AND ST_DWithin(m.p, c2.p, :ahead2)
                          AND NOT (m.pid = c2.pid AND m.k = c2.k)
                        ORDER BY ST_Distance(m.p, c2.p), m.pid, m.k LIMIT 1
                    ) q ON TRUE
                    WHERE c2.jn
                ),
                r AS (
                    SELECT j.*,
                           CASE WHEN j.q IS NOT NULL THEN j.q
                                WHEN j.cg IS NOT NULL THEN ST_ClosestPoint(j.og, ST_LineInterpolatePoint(
                                    CASE WHEN ST_LineLocatePoint(j.cg, j.p) < 0.5 THEN j.cg ELSE ST_Reverse(j.cg) END, LEAST(1, j.a / ST_Length(j.cg))))
                                WHEN j.ha IS NOT NULL AND j.d <= :corr3 THEN ST_ClosestPoint(j.og, j.ha)
                                -- Cap vers la ligne qu'il coupe à moins de a : prolongement droit jusqu'à elle.
                                WHEN j.d > :min3 AND j.hit IS NOT NULL AND ST_Distance(j.p, j.hit) <= j.a THEN j.hit
                                -- Sinon (écart <= JOIN_MIN_M, ou ligne presque parallèle) : en biseau, visée a plus loin.
                                ELSE ST_ClosestPoint(j.og, ST_Translate(j.p, j.a * sin(j.az), j.a * cos(j.az))) END AS t
                    FROM j
                    -- Bouts face à face : un seul raccord, celui de la ligne d'id le plus petit (même ligne : du premier morceau).
                    WHERE NOT (j.q IS NOT NULL AND (j.line_id > j.oline OR (j.line_id = j.oline AND (j.pid, j.k) > (j.qpid, j.qk))))
                ),
                f AS (
                    SELECT r.*,
                       CASE WHEN r.q IS NULL AND r.cut IS NOT NULL AND (r.cg IS NOT NULL OR (r.ha IS NOT NULL AND r.d <= :corr4) OR r.d <= :min4 OR NOT r.conv) THEN NULL
                            WHEN r.q IS NULL AND r.d <= :min5
                                 AND degrees(abs(atan2(sin(ST_Azimuth(r.p, r.t) - r.az), cos(ST_Azimuth(r.p, r.t) - r.az)))) > :turn
                                THEN ST_MakeLine(r.p, ST_ClosestPoint(r.og, r.p))
                            ELSE ST_MakeLine(r.p, r.t) END AS cg2
                    FROM r
                )
                -- Portion rejointe : celle du dessin d'une autre ligne au nouveau bout (signature du raccord).
                SELECT f.pid, f.sid, f.k, f.cut, ST_AsEWKT(f.cg2) AS conn,
                       (SELECT o.sid FROM tp_ad o WHERE o.pid <> f.pid
                         ORDER BY o.g <-> COALESCE(ST_EndPoint(f.cg2), ST_LineInterpolatePoint(f.g, f.cut)), o.pid LIMIT 1) AS osid
                FROM f
                ORDER BY f.pid, f.k
                SQL, ['max' => self::JOIN_MAX_M, 'eps' => self::CONTACT_EPS_M, 'ray' => self::CONTACT_RAY_M,
                'tail' => self::CONTACT_TAIL_M, 'tail2' => self::CONTACT_TAIL_M, 'ahead' => self::JOIN_AHEAD_M,
                'min2' => self::JOIN_MIN_M, 'min3' => self::JOIN_MIN_M, 'min4' => self::JOIN_MIN_M, 'min5' => self::JOIN_MIN_M,
                'turn' => self::CONTACT_TURN_DEG, 'ahead2' => self::JOIN_AHEAD_M, 'raytol2' => self::CONTACT_RAY_TOL_M,
                'reach' => self::JOIN_MAX_M + self::JOIN_AHEAD_M + self::CONTACT_RAY_M, 'raytol' => self::CONTACT_RAY_TOL_M, 'corr' => self::CORRIDOR_M, 'minsame' => self::JOIN_MIN_M,
                'ahead3' => self::JOIN_AHEAD_M, 'ahead4' => self::JOIN_AHEAD_M, 'corr2' => self::CORRIDOR_M, 'corr3' => self::CORRIDOR_M, 'corr4' => self::CORRIDOR_M]);
            if (! $rows) {
                if ($smoothed) {
                    break;
                }
                $pass = 1;

                continue;
            }
            // Par morceau : coupe(s) aux bouts qui dépassent, raccord(s) aux autres.
            $byPiece = [];
            foreach ($rows as $r) {
                $byPiece[(int) $r->pid][] = $r;
            }
            foreach ($byPiece as $pid => $ends) {
                $f0 = 0.0;
                $f1 = 1.0;
                $conns = [];
                foreach ($ends as $r) {
                    if ($r->conn === null) {
                        if ((int) $r->k === 0) {
                            $f0 = (float) $r->cut;
                        } else {
                            $f1 = (float) $r->cut;
                        }
                    } else {
                        $conns[] = $r->conn;
                    }
                }
                if ($f1 - $f0 < 1e-9) {
                    // Les deux coupes se rejoignent : morceau laissé tel quel (cas dégénéré).
                    [$f0, $f1] = [0.0, 1.0];
                    $ends = array_filter($ends, fn ($r) => $r->conn !== null);
                }
                $out['trims'] += count(array_filter($ends, fn ($r) => $r->conn === null));
                $out['contacts'] += count($conns);
                DB::update(<<<'SQL'
                    UPDATE tp_ad SET g = ST_LineMerge(ST_CollectionExtract(ST_Collect(
                        ARRAY[ST_LineSubstring(g, CAST(:f0 AS float8), CAST(:f1 AS float8))]
                        || ARRAY(SELECT ST_GeomFromEWKT(x) FROM json_array_elements_text(CAST(:conns AS json)) x)), 2))
                    WHERE pid = :pid
                    SQL, ['f0' => $f0, 'f1' => $f1, 'conns' => json_encode($conns, JSON_THROW_ON_ERROR), 'pid' => $pid]);
                foreach ($ends as $r) {
                    $targets[(int) $r->sid][] = (int) $r->osid;
                }
            }
            DB::statement('ANALYZE tp_ad');
        }

        foreach ($targets as $sid => $osids) {
            $ids = '{'.implode(',', array_values(array_unique($osids))).'}';
            $exists = DB::selectOne('SELECT 1 AS x FROM tp_cv WHERE sid = ?', [$sid]);
            if ($exists) {
                DB::update(<<<'SQL'
                    UPDATE tp_cv SET
                        vis = (SELECT ST_CollectionExtract(ST_Collect(a.g ORDER BY a.pid), 2) FROM tp_ad a WHERE a.sid = :sid),
                        covering_ids = ARRAY(SELECT DISTINCT unnest(covering_ids || CAST(:ids AS bigint[])) ORDER BY 1),
                        changed = TRUE
                    WHERE sid = :sid2
                    SQL, ['sid' => $sid, 'ids' => $ids, 'sid2' => $sid]);
            } else {
                DB::insert(<<<'SQL'
                    INSERT INTO tp_cv (sid, line_id, covering_ids, vis, cov, changed, added)
                    SELECT s.id, s.line_id, ARRAY(SELECT DISTINCT unnest(CAST(:ids AS bigint[])) ORDER BY 1),
                           (SELECT ST_CollectionExtract(ST_Collect(a.g ORDER BY a.pid), 2) FROM tp_ad a WHERE a.sid = s.id),
                           ST_GeomFromText('MULTILINESTRING EMPTY', 2154), TRUE, TRUE
                    FROM tp_shared_seg s WHERE s.id = :sid
                    SQL, ['ids' => $ids, 'sid' => $sid]);
            }
        }
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_ad');

        return $out;
    }

    /**
     * Règle 4 (cf. docblock, App\Support\JunctionCurve) : bouts de jonction lissés sur le dessin (tp_ad), après la règle 3.
     * Chaînes dessinées par ligne ; bout libre posé (<= JUNCTION_ON_M) sur le dessin d'une autre ligne, hors relais (bout de
     * chaîne de l'autre ligne au même point). La partie remplacée (du départ D au bout) est retirée des morceaux de la
     * branche, la courbe est ajoutée au morceau qui porte D. Jusqu'à trois tours : une ligne (ou la ligne rejointe)
     * modifiée dans un tour n'est revue qu'au tour suivant, sur son nouveau dessin.
     *
     * @param  array<int, array<int, int>>  $targets  sid => portions rejointes (complété)
     * @return array{smoothed: int, unsmoothed: int}
     */
    private function smooth(array &$targets): array
    {
        $out = ['smoothed' => 0, 'unsmoothed' => 0];
        $done = [];
        $round = 0;
        do {
            $this->singleLines();
            DB::statement('DROP TABLE IF EXISTS pg_temp.tp_ch');
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE tp_ch AS
                SELECT row_number() OVER () AS cid, t.line_id, d.geom AS g
                FROM (SELECT line_id, ST_LineMerge(ST_Collect(g)) AS m FROM tp_ad GROUP BY line_id) t, LATERAL ST_Dump(t.m) d
                WHERE ST_Length(d.geom) > 0.05
                SQL);
            DB::statement('CREATE INDEX ON tp_ch USING gist (g)');
            DB::statement('CREATE INDEX ON tp_ch (line_id)');
            DB::statement('ANALYZE tp_ch');
            $ends = DB::select(<<<'SQL'
                WITH e AS (
                    SELECT c.cid, c.line_id, c.g, ST_Length(c.g) AS len, x.k, x.p
                    FROM tp_ch c, LATERAL (VALUES (0, ST_StartPoint(c.g)), (1, ST_EndPoint(c.g))) x(k, p)
                    WHERE NOT ST_IsClosed(c.g) AND ST_Length(c.g) >= 25
                      AND NOT EXISTS (SELECT 1 FROM tp_ch o WHERE o.line_id = c.line_id AND o.cid <> c.cid AND ST_DWithin(o.g, x.p, 0.5))
                      AND NOT ST_DWithin(CASE WHEN x.k = 0 THEN ST_LineSubstring(c.g, 2 / ST_Length(c.g), 1)
                                              ELSE ST_LineSubstring(c.g, 0, 1 - 2 / ST_Length(c.g)) END, x.p, 0.5)
                ),
                j AS (
                    SELECT e.*, o.cid AS ocid, o.line_id AS oline, o.g AS og
                    FROM e CROSS JOIN LATERAL (
                        SELECT o.cid, o.line_id, o.g FROM tp_ch o
                        WHERE o.line_id <> e.line_id AND ST_DWithin(o.g, e.p, :on)
                        ORDER BY ST_Distance(o.g, e.p), o.cid LIMIT 1
                    ) o
                    -- Relais : la ligne rejointe a elle aussi un bout de chaîne ici (bout à bout, pas une jonction en T).
                    WHERE NOT EXISTS (SELECT 1 FROM tp_ch q WHERE q.line_id = o.line_id
                                        AND (ST_DWithin(ST_StartPoint(q.g), e.p, 1) OR ST_DWithin(ST_EndPoint(q.g), e.p, 1)))
                )
                SELECT j.cid, j.line_id, j.k, j.ocid, j.oline, j.len,
                       ST_AsText(CASE WHEN j.k = 1 THEN ST_Reverse(ST_LineSubstring(j.g, GREATEST(0, 1 - :span / j.len), 1))
                                      ELSE ST_LineSubstring(j.g, 0, LEAST(1, :span2 / j.len)) END) AS a,
                       ST_AsText(ST_LineSubstring(j.og, GREATEST(0, l.f - :win / l.olen), LEAST(1, l.f + :win2 / l.olen))) AS w,
                       -- Branche précise : morceaux de ses 120 derniers m tous BD TOPO, OSM, SNCF ou contribution.
                       COALESCE((SELECT bool_and(ls.source IN ('bdtopo', 'osm', 'sncf', 'contrib'))
                                   FROM tp_ad a JOIN line_segments ls ON ls.id = a.sid
                                  WHERE a.line_id = j.line_id AND ST_DWithin(a.g, j.p, 120)
                                    AND ST_Length(ST_Intersection(a.g, ST_Buffer(j.g, 0.05))) > 0.5), false) AS precise,
                       (SELECT ls.gauge_mm FROM tp_ad a JOIN line_segments ls ON ls.id = a.sid
                         WHERE a.line_id = j.line_id ORDER BY a.g <-> j.p LIMIT 1) AS gauge,
                       (SELECT network FROM lines WHERE id = j.line_id) AS network
                FROM j, LATERAL (SELECT ST_LineLocatePoint(j.og, j.p) AS f, ST_Length(j.og) AS olen) l
                ORDER BY j.line_id, j.cid, j.k
                SQL, ['on' => self::JUNCTION_ON_M, 'span' => self::SMOOTH_SPAN_M, 'span2' => self::SMOOTH_SPAN_M,
                'win' => self::SMOOTH_WINDOW_M, 'win2' => self::SMOOTH_WINDOW_M]);
            $touched = [];
            $deferred = 0;
            foreach ($ends as $e) {
                $key = $e->line_id.':'.$e->cid.':'.$e->k;
                if (isset($done[$key])) {
                    continue;
                }
                $a = self::coords($e->a);
                $w = self::coords($e->w);
                $rTarget = JunctionRepair::minRadius($e->network, $e->gauge !== null ? (int) $e->gauge : null);
                $rFloor = min($rTarget, self::SMOOTH_FLOOR_M);
                if (count($a) < 2 || count($w) < 2 || JunctionCurve::plan($a, $w, $rTarget, $rFloor, null, (bool) $e->precise) === null) {
                    continue;
                }
                if (isset($touched[$e->line_id]) || isset($touched[$e->oline])) {
                    $deferred++;

                    continue;
                }
                $done[$key] = true;
                $ok = $this->smoothEnd($e, $a, $w, $rTarget, $rFloor, $targets);
                if ($ok) {
                    $touched[$e->line_id] = true;
                    $out['smoothed']++;
                } else {
                    $out['unsmoothed']++;
                }
            }
            DB::statement('ANALYZE tp_ad');
        } while ($deferred > 0 && ++$round < 3);
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_ch');

        return $out;
    }

    /**
     * Lisse un bout (cf. smooth) : départ avant toute autre jonction de la branche, courbe qui ne croise aucune
     * autre voie, puis remplacement dans tp_ad. Faux si aucune courbe ne convient (dessin laissé tel quel).
     *
     * @param  list<array{0: float, 1: float}>  $a
     * @param  list<array{0: float, 1: float}>  $w
     * @param  array<int, array<int, int>>  $targets
     */
    private function smoothEnd(object $e, array $a, array $w, float $rTarget, float $rFloor, array &$targets): bool
    {
        // Première autre voie (autre ligne, ou embranchement de la même) au contact de l'approche, hors la ligne rejointe au bout.
        $block = DB::selectOne(<<<'SQL'
            WITH a AS (SELECT ST_GeomFromText(:a, 2154) AS g), p AS (SELECT ST_StartPoint(g) AS p FROM a)
            SELECT MIN(LEAST(ST_LineLocatePoint(a.g, ST_StartPoint(x.geom)), ST_LineLocatePoint(a.g, ST_EndPoint(x.geom))) * ST_Length(a.g)) AS s
            FROM a, p, tp_ad o, LATERAL ST_Dump(ST_Intersection(a.g, ST_Buffer(o.g, 0.5))) x
            WHERE o.g && ST_Expand(a.g, 0.5) AND ST_DWithin(o.g, a.g, 0.5)
              AND NOT (o.line_id = :line AND ST_Length(ST_Intersection(o.g, ST_Buffer(a.g, 0.05))) > 1)
              AND x.geom IS NOT NULL AND NOT ST_IsEmpty(x.geom)
              -- Contact au bout : seul celui de la ligne rejointe est attendu ; une troisième ligne qui finit au même point
              -- (bout posé sur la partie remplacée) bloque tout lissage (sinon elle resterait à côté de la courbe).
              AND NOT (o.line_id = :oline AND ST_DWithin(x.geom, p.p, 0.01))
            SQL, ['a' => $e->a, 'line' => $e->line_id, 'oline' => $e->oline]);
        $sBlock = $block?->s !== null ? (float) $block->s : null;
        $plan = JunctionCurve::plan($a, $w, $rTarget, $rFloor, $sBlock, (bool) $e->precise);
        if ($plan === null) {
            return false;
        }
        $curve = 'LINESTRING('.implode(',', array_map(fn ($p) => sprintf('%.4f %.4f', $p[0], $p[1]), $plan['points'])).')';
        // Partie remplacée Rm (du bout au départ D, sur la chaîne) ; la courbe ne doit croiser aucune voie (sauf à ses bouts).
        $geo = <<<'SQL'
            WITH c AS (SELECT g, ST_Length(g) AS len FROM tp_ch WHERE cid = :cid),
                 r AS (SELECT CASE WHEN :k = 1 THEN ST_LineSubstring(c.g, GREATEST(0, 1 - CAST(:sd AS float8) / c.len), 1)
                                   ELSE ST_LineSubstring(c.g, 0, LEAST(1, CAST(:sd2 AS float8) / c.len)) END AS g,
                              ST_LineInterpolatePoint(c.g, CASE WHEN :k2 = 1 THEN GREATEST(0, 1 - CAST(:sd3 AS float8) / c.len)
                                                               ELSE LEAST(1, CAST(:sd4 AS float8) / c.len) END) AS d FROM c),
                 cv AS (SELECT ST_GeomFromText(:curve, 2154) AS g)
            SQL;
        $bind = ['cid' => $e->cid, 'k' => $e->k, 'k2' => $e->k, 'sd' => $plan['sD'], 'sd2' => $plan['sD'], 'sd3' => $plan['sD'], 'sd4' => $plan['sD'], 'curve' => $curve];
        $bad = DB::selectOne($geo.<<<'SQL'
            SELECT EXISTS (
                SELECT 1 FROM tp_ad o, r, cv
                WHERE o.g && cv.g
                  AND (ST_Intersects(CASE WHEN o.line_id = :line THEN ST_Difference(o.g, ST_Buffer(r.g, 0.05)) ELSE o.g END,
                                     ST_LineSubstring(cv.g, LEAST(0.4, 1.0 / ST_Length(cv.g)), 1 - LEAST(0.4, 0.3 / ST_Length(cv.g))))
                       -- Bout d'une autre ligne frôlé (< SMOOTH_CLEAR_M) : il deviendrait une jonction qui n'existe pas.
                       OR (o.line_id NOT IN (:line2, :oline) AND ST_DWithin(ST_Collect(ST_StartPoint(o.g), ST_EndPoint(o.g)),
                                     ST_LineSubstring(cv.g, LEAST(0.4, 1.0 / ST_Length(cv.g)), 1), :clear)))
            ) AS bad
            SQL, $bind + ['line' => $e->line_id, 'line2' => $e->line_id, 'oline' => $e->oline, 'clear' => self::SMOOTH_CLEAR_M]);
        if ($bad === null || $bad->bad) {
            return false;
        }
        // Morceau qui porte D : coupé en D, la courbe y est ajoutée (premier sommet = point du morceau, dernier = point
        // du dessin de la ligne rejointe : 0 m) ; les autres morceaux de la branche dans Rm sont retirés.
        $dp = DB::selectOne($geo.<<<'SQL'
            SELECT a.pid, a.sid, ST_LineLocatePoint(a.g, r.d) AS f,
                   ST_Length(ST_Difference(ST_LineSubstring(a.g, 0, ST_LineLocatePoint(a.g, r.d)), ST_Buffer(r.g, 0.02)))
                     >= ST_Length(ST_Difference(ST_LineSubstring(a.g, ST_LineLocatePoint(a.g, r.d), 1), ST_Buffer(r.g, 0.02))) AS head
            FROM tp_ad a, r
            WHERE a.line_id = :line AND ST_DWithin(a.g, r.d, 0.02) AND GeometryType(a.g) = 'LINESTRING'
              AND ST_Length(ST_Difference(a.g, ST_Buffer(r.g, 0.02))) > 0.05
            ORDER BY ST_Length(ST_Difference(a.g, ST_Buffer(r.g, 0.02))) DESC, a.pid LIMIT 1
            SQL, $bind + ['line' => $e->line_id]);
        if ($dp === null) {
            return false;
        }
        $osid = DB::selectOne(<<<'SQL'
            SELECT o.sid, ST_AsText(ST_ClosestPoint(o.g, ST_EndPoint(ST_GeomFromText(:curve, 2154)))) AS j
            FROM tp_ad o WHERE o.line_id = :oline ORDER BY o.g <-> ST_EndPoint(ST_GeomFromText(:curve2, 2154)), o.pid LIMIT 1
            SQL, ['curve' => $curve, 'curve2' => $curve, 'oline' => $e->oline]);
        if ($osid === null) {
            return false;
        }
        $removed = DB::select($geo.<<<'SQL'
            DELETE FROM tp_ad a USING r
            WHERE a.line_id = :line AND a.pid <> :pid AND ST_Covers(ST_Buffer(r.g, 0.05), a.g)
            RETURNING a.sid
            SQL, $bind + ['line' => $e->line_id, 'pid' => $dp->pid]);
        // Reste de la branche qui déborde dans Rm (morceau de biais) : coupé hors de Rm.
        $trimmed = DB::select($geo.<<<'SQL'
            UPDATE tp_ad a SET g = ST_LineMerge(ST_CollectionExtract(ST_Difference(a.g, ST_Buffer(r.g, 0.05, 'endcap=flat')), 2))
            FROM r
            WHERE a.line_id = :line AND a.pid <> :pid AND a.g && ST_Expand(r.g, 0.05)
              AND ST_Length(ST_Intersection(a.g, ST_Buffer(r.g, 0.05))) > 0.5
            RETURNING a.sid
            SQL, $bind + ['line' => $e->line_id, 'pid' => $dp->pid]);
        DB::delete('DELETE FROM tp_ad WHERE line_id = ? AND (g IS NULL OR ST_IsEmpty(g) OR ST_Length(g) <= 0.01)', [$e->line_id]);
        DB::update(<<<'SQL'
            UPDATE tp_ad a SET g = ST_LineMerge(ST_Collect(k.g, ST_SetPoint(ST_SetPoint(ST_GeomFromText(:curve, 2154), 0,
                                       CASE WHEN :head2 THEN ST_EndPoint(k.g) ELSE ST_StartPoint(k.g) END), -1, ST_GeomFromText(:j, 2154))))
            FROM (SELECT CASE WHEN :head THEN ST_LineSubstring(b.g, 0, CAST(:f AS float8)) ELSE ST_LineSubstring(b.g, CAST(:f2 AS float8), 1) END AS g
                  FROM tp_ad b WHERE b.pid = :pid2) k
            WHERE a.pid = :pid
            SQL, ['head' => (bool) $dp->head, 'head2' => (bool) $dp->head, 'f' => (float) $dp->f, 'f2' => (float) $dp->f,
            'curve' => $curve, 'j' => $osid->j, 'pid' => $dp->pid, 'pid2' => $dp->pid]);
        foreach (array_merge([$dp], $removed, $trimmed) as $r) {
            $targets[(int) $r->sid][] = (int) $osid->sid;
        }

        return true;
    }

    /** Morceaux de tp_ad en LINESTRING simples (une fusion de raccord ou une coupe peut laisser une MULTILINESTRING). */
    private function singleLines(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO tp_ad (pid, sid, line_id, g)
            SELECT (SELECT MAX(pid) FROM tp_ad) + row_number() OVER (ORDER BY a.pid, d.path), a.sid, a.line_id, d.geom
            FROM tp_ad a, LATERAL ST_Dump(a.g) d
            WHERE GeometryType(a.g) <> 'LINESTRING' AND GeometryType(d.geom) = 'LINESTRING' AND ST_Length(d.geom) > 0.01
            SQL);
        DB::delete("DELETE FROM tp_ad WHERE g IS NULL OR GeometryType(g) <> 'LINESTRING' OR ST_Length(g) <= 0.01");
    }

    /**
     * Coordonnées d'une LINESTRING en WKT.
     *
     * @return list<array{0: float, 1: float}>
     */
    private static function coords(?string $wkt): array
    {
        if ($wkt === null || ! preg_match('/^LINESTRING\((.*)\)$/', $wkt, $m)) {
            return [];
        }

        return array_map(function (string $p): array {
            $xy = explode(' ', trim($p));

            return [(float) $xy[0], (float) $xy[1]];
        }, explode(',', $m[1]));
    }

    /** Parties dessinées (tp_dp) et masquées (tp_cp) des lignes qui ont au moins une partie masquée à l'ère. */
    private function pieces(): void
    {
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_dp');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE tp_dp AS
            SELECT row_number() OVER (ORDER BY s.id, d.path) AS pid, s.id AS sid, s.line_id, d.geom AS g
            FROM tp_shared_seg s LEFT JOIN tp_cv v ON v.sid = s.id,
                 LATERAL ST_Dump(CASE WHEN v.sid IS NULL THEN s.g ELSE v.vis END) d
            WHERE s.line_id IN (SELECT line_id FROM tp_cv) AND d.geom IS NOT NULL AND ST_Length(d.geom) > 0.01
            SQL);
        DB::statement('CREATE INDEX ON tp_dp USING gist (g)');
        DB::statement('ANALYZE tp_dp');
        DB::statement('DROP TABLE IF EXISTS pg_temp.tp_cp');
        DB::statement('CREATE TEMP TABLE tp_cp AS SELECT v.sid, v.line_id, d.geom AS g FROM tp_cv v, LATERAL ST_Dump(v.cov) d WHERE ST_Length(d.geom) > 0.01');
        DB::statement('CREATE INDEX ON tp_cp USING gist (g)');
        DB::statement('ANALYZE tp_cp');
    }
}
