<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattache les tracés précis du réseau actuel (BD TOPO, OSM) aux lignes RFN
 * (`lines.code`) en les confrontant aux formes SNCF (`sncf_line_geometries`).
 *
 * Pourquoi par points et pas par simple tampon : un tronçon est échantillonné
 * tous les 50 m et chaque point « vote » pour le code SNCF dont la forme est
 * la plus proche. Un tampon seul rattacherait à une ligne toutes les voies
 * qui la longent (faisceaux, raccordements, ligne voisine) ; le vote au plus
 * proche départage deux lignes parallèles et mesure l'ambiguïté aux nœuds.
 *
 * Calibrage (2026-10-15, 270 000 points SNCF « Exploitée » tous les 100 m
 * confrontés à la BD TOPO) : écart médian 3 m, p90 11 m, p95 14 m, p99 32 m ;
 * 99,2 % sous 50 m. Les formes SNCF sont donc justes sauf sur une traîne de
 * ~1 % où la généralisation atteint 50-165 m. D'où deux niveaux :
 *   - strict : ≥ 80 % des points à ≤ 50 m (--buffer) et plus proches de ce
 *     code que de tout autre, écart médian ≤ 25 m (--max-median), au plus
 *     34 % de points ambigus (autre code à moins de max(5 m, d/2) de plus) ;
 *   - souple (forme SNCF décalée, ex. LGV Est phase 2 à 50-170 m) : ≥ 80 %
 *     des points à ≤ 150 m (--loose-buffer) et sans ambiguïté, le tracé ne
 *     double pas une voie active déjà rattachée (≥ 30 % des points à ≤ 60 m),
 *     ET continuité : il touche (≤ 2 m) un tracé déjà rattaché au même code ;
 *     propagé de proche en proche. Score réduit (× 0,6).
 *   L'écart médian > 25 m n'est toléré au niveau strict qu'en zone isolée
 *   (aucun autre code à ≤ 150 m d'aucun point).
 *
 * Contrôles de cohérence (sinon line_id reste NULL, motif compté) :
 *   - nature : une voie de service (BD TOPO « Voie de service », OSM
 *     `service=*`) est refusée si elle double la ligne - ≥ 30 % de ses points
 *     à ≤ 60 m d'une voie active rattachée au même code, ou longée par une
 *     autre voie de service plus proche de la forme SNCF ; seule, elle EST la
 *     ligne (la BD TOPO classe ainsi des lignes fret entières). Pas de refus
 *     si statut « Transférée en voie de service » ou ref OSM = code.
 *     BD TOPO « En projet » → jamais ;
 *   - ref OSM : si le way porte un code RFN (ref=420 000…), ce code doit être
 *     celui retenu ; il départage aussi les tronçons communs (ambiguïté levée) ;
 *   - statut : un way OSM disparu (abandoned/razed/removed/…) sur une forme
 *     « Exploitée » est un ancien tracé → refusé ; un tronçon « Non exploité »
 *     (BD TOPO) ou disused (OSM) sur une forme « Exploitée » est refusé s'il
 *     longe (≤ 25 m sur la moitié de sa longueur) une voie active rattachée au
 *     même code - sinon c'est la voie elle-même, d'état divergent.
 *
 * Lignes EXPLOITÉES HORS RFN (CFC en Corse, Chemins de fer de Provence,
 * ligne de Sceaux, P'tit train de la Haute-Somme… : lignes créées par
 * vff:link-traces depuis le calque archeo « lignes exploitées », sans aucune
 * forme SNCF) : leur tracé archeo « lignes exploitées » tient lieu de forme
 * (pseudo-code « L<id de ligne> », statut « Exploitée »), avec les mêmes
 * seuils et contrôles. Sans cela, aucune voie BD TOPO / OSM ne leur était
 * rattachée et vff:build-line-references (qui écarte ce calque peu précis)
 * ne gardait que quelques morceaux OHM : 29 km sur 157 pour Bastia - Ajaccio,
 * rien pour Ponte-Leccia - Calvi. Mesure 2026-10-19 en Corse : points BD
 * TOPO à 0,5 m (médiane), 1,4 m (p90) du tracé archeo. match_method =
 * 'exploited_overlap'.
 *
 * Lignes exploitées absentes d'archeo comme des formes SNCF (partie RATP du
 * RER A et du RER B, tunnel sous la Manche, antenne CFL de Volmerange, trains
 * touristiques sur voie propre) : leur FORME DÉCLARÉE (source_traces source =
 * 'declared', union des ways OSM / tronçons BD TOPO désignés dans
 * database/data/lignes_hors_rfn.php par vff:seed-off-network-lines) tient le
 * même rôle, même pseudo-code « L<id> », même match_method.
 *
 * Résultat : source_traces.line_id, match_method = 'sncf_overlap' (ou
 * 'exploited_overlap'), match_score ∈ ]0, 1] = part des points votants ×
 * (1 − part ambiguë) × facteur d'écart (1 sous 10 m, 0,5 à 35 m et au-delà).
 * Ne touche qu'aux sources bdtopo et osm. Idempotent (recalcul complet).
 * Avec --line, seuls les tracés situés autour de ces lignes sont recalculés,
 * et seuls les rattachements à ces lignes sont réécrits (ceux des autres
 * lignes ne bougent pas) : résultat identique à un recalcul complet pour ces
 * lignes.
 *
 *   php artisan vff:match-current-network
 *   php artisan vff:match-current-network --source=bdtopo --dry-run
 *   php artisan vff:match-current-network --line=4457 --line=4458
 */
class MatchCurrentNetwork extends Command
{
    protected $signature = 'vff:match-current-network
        {--source=* : bdtopo, osm (défaut : les deux)}
        {--buffer=50 : distance max (m) point ↔ forme SNCF, niveau strict}
        {--loose-buffer=150 : distance max (m) du niveau souple (forme SNCF décalée)}
        {--max-median=25 : écart médian max (m) hors zone isolée}
        {--min-share=0.8 : part minimale des points votant pour le code}
        {--max-ambiguous=0.34 : part maximale de points ambigus}
        {--step=50 : pas d\'échantillonnage (m)}
        {--explain=* : codes de ligne dont on détaille les tracés candidats non rattachés}
        {--line=* : id ou code d\'une ligne (répétable) : ne recalcule que les rattachements de ces lignes}
        {--dry-run : calcule et affiche sans écrire}';

    protected $description = 'Rattache les tronçons BD TOPO / ways OSM aux lignes RFN (formes SNCF) et aux lignes exploitées hors RFN (tracé archeo « lignes exploitées »).';

    private const SOURCES = ['bdtopo', 'osm'];

    /** Préfixe du pseudo-code d'une ligne exploitée hors RFN (forme = tracé archeo « lignes exploitées »). */
    public const EXPLOITED_PREFIX = 'L';

    /** Lignes visées par --line (ids), null = toutes. */
    private ?array $lineIds = null;

    public function handle(): int
    {
        $sources = $this->option('source') ?: self::SOURCES;
        if ($bad = array_diff($sources, self::SOURCES)) {
            $this->error('Sources gérées : '.implode(', ', self::SOURCES).' (reçu : '.implode(', ', $bad).')');

            return self::FAILURE;
        }
        $p = [
            'buf' => (float) $this->option('buffer'),
            'loose' => (float) $this->option('loose-buffer'),
            'maxmed' => (float) $this->option('max-median'),
            'share' => (float) $this->option('min-share'),
            'amb' => (float) $this->option('max-ambiguous'),
            'step' => max(5.0, (float) $this->option('step')),
        ];

        if ($opts = (array) $this->option('line')) {
            $ids = array_map(fn ($o) => ctype_digit((string) $o) && strlen((string) $o) < 6 ? (int) $o : 0, $opts);
            $this->lineIds = DB::table('lines')->whereIn('id', $ids)->orWhereIn('code', $opts)->pluck('id')->map(fn ($v) => (int) $v)->all();
            if (! $this->lineIds) {
                $this->error('Aucune ligne trouvée pour --line='.implode(',', $opts));

                return self::FAILURE;
            }
        }

        $this->prepareSncf();
        foreach ($sources as $source) {
            $t0 = microtime(true);
            $this->line("→ {$source} : échantillonnage et plus proches formes SNCF…");
            $this->prepareTraces($source, $p);
            $this->decide($p);
            $this->report($source);
            foreach ($this->option('explain') as $code) {
                $this->explain((string) $code);
            }
            if (! $this->option('dry-run')) {
                $this->write($source);
            }
            $this->line(sprintf('  (%.0f s)', microtime(true) - $t0));
        }
        if ($this->option('dry-run')) {
            $this->warn('--dry-run : aucune écriture.');
        }

        return self::SUCCESS;
    }

    /**
     * Formes en Lambert 93, découpées (ST_Subdivide) pour que l'index KNN soit
     * efficace : formes SNCF, puis tracé archeo « lignes exploitées » des lignes
     * exploitées hors RFN (aucune forme SNCF pour leur code), pseudo-code « L<id> ».
     */
    private function prepareSncf(): void
    {
        DB::statement('DROP TABLE IF EXISTS m_sncf');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE m_sncf AS
            SELECT s.code_ligne AS code, s.libelle AS statut, ST_Subdivide(ST_Transform(s.geom, 2154), 16) AS g
            FROM sncf_line_geometries s
            SQL);
        // + formes DÉCLARÉES (vff:seed-off-network-lines : RER de la RATP, tunnel sous la Manche,
        //   trains touristiques sur voie propre…), absentes d'archeo comme des formes SNCF.
        DB::insert(<<<'SQL'
            INSERT INTO m_sncf (code, statut, g)
            SELECT ? || t.line_id, 'Exploitée', ST_Subdivide(ST_Transform(t.geom, 2154), 16)
            FROM source_traces t JOIN lines l ON l.id = t.line_id
            WHERE ((t.source = 'archeoferroviaire' AND t.layer = 'exploitee') OR t.source = ?)
              AND NOT EXISTS (SELECT 1 FROM sncf_line_geometries s WHERE s.code_ligne = l.code)
            SQL, [self::EXPLOITED_PREFIX, SeedOffNetworkLines::FORM_SOURCE]);
        DB::statement('CREATE INDEX ON m_sncf USING gist (g)');
        DB::statement('ANALYZE m_sncf');
        $exploited = DB::selectOne("SELECT COUNT(DISTINCT code) AS n FROM m_sncf WHERE code LIKE ? || '%'", [self::EXPLOITED_PREFIX])->n;
        $this->line("  {$exploited} lignes exploitées hors RFN : tracé archeo « lignes exploitées » en guise de forme SNCF.");
    }

    /**
     * Avec --line : emprises (WGS 84) des formes de chaque ligne visée (formes
     * SNCF de son code, tracés rattachés hors BD TOPO / OSM, dont le tracé archeo
     * « lignes exploitées »), une boîte par ligne élargie de 3 km, dans la table
     * temporaire m_env. Retourne false sans --line (toute la France).
     */
    private function prepareEnvelope(): bool
    {
        DB::statement('DROP TABLE IF EXISTS m_env');
        if ($this->lineIds === null) {
            return false;
        }
        $ids = '{'.implode(',', $this->lineIds).'}';
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE m_env AS
            WITH f AS (
                SELECT l.id AS line_id, ST_Transform(s.geom, 2154) AS g FROM sncf_line_geometries s JOIN lines l ON l.code = s.code_ligne
                WHERE l.id = ANY (CAST(:ids AS bigint[]))
                UNION ALL
                SELECT t.line_id, ST_Transform(t.geom, 2154) FROM source_traces t
                WHERE t.line_id = ANY (CAST(:ids2 AS bigint[])) AND t.source NOT IN ('bdtopo', 'osm')
            )
            SELECT line_id, ST_Transform(ST_SetSRID(ST_Expand(ST_Extent(g)::geometry, 3000), 2154), 4326) AS g FROM f GROUP BY line_id
            SQL, ['ids' => $ids, 'ids2' => $ids]);

        return true;
    }

    private function prepareTraces(string $source, array $p): void
    {
        foreach (['m_tr', 'm_pts', 'm_knn', 'm_pt1', 'm_cand', 'm_dec'] as $t) {
            DB::statement("DROP TABLE IF EXISTS {$t}");
        }

        // Classe de nature, commune aux deux sources :
        //   active   = voie principale en service ou en construction (ou préservée)
        //   inactive = voie en place non exploitée (BD TOPO Non exploité, OSM disused)
        //   vanished = voie disparue (OSM abandoned, razed, removed…)
        //   service  = voie de service (faisceau, garage, embranchement)
        //   projet   = BD TOPO En projet
        $cls = $source === 'bdtopo'
            ? <<<'SQL'
                CASE WHEN t.properties->>'etat_de_l_objet' = 'En projet' THEN 'projet'
                     WHEN t.layer = 'Voie de service' THEN 'service'
                     WHEN t.properties->>'etat_de_l_objet' = 'Non exploité' THEN 'inactive'
                     ELSE 'active' END
                SQL
            : <<<'SQL'
                CASE WHEN jsonb_exists(t.properties, 'service') THEN 'service'
                     WHEN t.layer IN ('disused', 'disused:railway') THEN 'inactive'
                     WHEN t.layer IN ('rail', 'narrow_gauge', 'light_rail', 'funicular', 'construction', 'preserved',
                                      'construction:railway', 'preserved:railway') THEN 'active'
                     ELSE 'vanished' END
                SQL;
        $refs = $source === 'osm'
            ? "ARRAY(SELECT r FROM unnest(regexp_split_to_array(regexp_replace(COALESCE(t.properties->>'ref', ''), '\\s', '', 'g'), '[;,/]')) r WHERE r ~ '^[0-9]{6}$' AND r IN (SELECT code_ligne FROM sncf_line_geometries))"
            : "'{}'::text[]";

        // Métropole uniquement (le RFN) ; Lambert 93 pour des distances en mètres.
        // Avec --line : seulement les tracés autour des lignes visées.
        $env = $this->prepareEnvelope()
            ? 'AND EXISTS (SELECT 1 FROM m_env e WHERE t.geom && e.g)'
            : '';
        DB::statement(<<<SQL
            CREATE TEMP TABLE m_tr AS
            SELECT t.id, {$cls} AS cls, {$refs} AS refs, ST_Transform(t.geom, 2154) AS g
            FROM source_traces t
            WHERE t.source = ? AND t.geom && ST_MakeEnvelope(-5.6, 41.2, 10.0, 51.2, 4326) {$env}
            SQL, [$source]);
        DB::statement('CREATE INDEX ON m_tr (id)');
        DB::statement('CREATE INDEX ON m_tr USING gist (g)');

        // Points aux abscisses (k − ½)/n : jamais pile sur un nœud d'extrémité,
        // où toutes les lignes d'une bifurcation se rejoignent.
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE m_pts AS
            SELECT row_number() OVER () AS pid, t.id AS tid, ST_LineInterpolatePoint(d.geom, (k - 0.5) / c.n) AS p
            FROM m_tr t
            CROSS JOIN LATERAL ST_Dump(t.g) d
            CROSS JOIN LATERAL (SELECT GREATEST(1, LEAST(400, CEIL(ST_Length(d.geom) / ?)))::int AS n) c
            CROSS JOIN LATERAL generate_series(1, c.n) k
            WHERE ST_Length(d.geom) > 0
            SQL, [$p['step']]);
        DB::statement('CREATE INDEX ON m_pts (tid)');

        // Pour chaque point : distance à chaque code SNCF proche (≤ loose), le plus proche d'abord.
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE m_knn AS
            SELECT pt.pid, pt.tid, k.code, k.statut, k.d
            FROM m_pts pt
            CROSS JOIN LATERAL (
                SELECT DISTINCT ON (x.code) x.code, x.statut, x.d FROM (
                    SELECT s.code, s.statut, pt.p <-> s.g AS d
                    FROM m_sncf s WHERE ST_DWithin(pt.p, s.g, ?)
                    ORDER BY pt.p <-> s.g LIMIT 8
                ) x ORDER BY x.code, x.d
            ) k
            SQL, [$p['loose']]);
        DB::statement('CREATE INDEX ON m_knn (pid)');

        DB::statement(<<<'SQL'
            CREATE TEMP TABLE m_pt1 AS
            SELECT pid, tid,
                   (array_agg(code ORDER BY d))[1] AS c1, MIN(d) AS d1,
                   (array_agg(d ORDER BY d))[2] AS d2, COUNT(*) AS ncodes
            FROM m_knn GROUP BY pid, tid
            SQL);
        DB::statement('CREATE INDEX ON m_pt1 (pid)');
    }

    private function decide(array $p): void
    {
        // Agrégat par (tracé, code candidat).
        // Seuils injectés en littéraux (floats castés) : PDO pgsql n'accepte pas un même
        // paramètre nommé plusieurs fois en préparation native.
        [$buf, $share, $amb, $maxmed] = array_map(fn ($v) => sprintf('%F', $v), [$p['buf'], $p['share'], $p['amb'], $p['maxmed']]);
        $prefix = self::EXPLOITED_PREFIX;

        DB::statement(<<<SQL
            CREATE TEMP TABLE m_cand AS
            SELECT k.tid, k.code,
                   COUNT(*) FILTER (WHERE k.d <= {$buf}) AS near,
                   COUNT(*) FILTER (WHERE p1.c1 = k.code AND k.d <= {$buf}) AS votes,
                   COUNT(*) FILTER (WHERE p1.c1 = k.code AND k.d <= {$buf} AND p1.d2 <= {$buf}
                                    AND p1.d2 - p1.d1 < GREATEST(5, 0.5 * p1.d1)) AS amb,
                   COUNT(*) FILTER (WHERE p1.c1 = k.code AND (p1.d2 IS NULL OR p1.d2 - p1.d1 >= GREATEST(5, 0.5 * p1.d1))) AS votes_loose,
                   percentile_cont(0.5) WITHIN GROUP (ORDER BY k.d) FILTER (WHERE k.d <= {$buf}) AS med,
                   mode() WITHIN GROUP (ORDER BY k.statut) AS statut
            FROM m_knn k JOIN m_pt1 p1 USING (pid)
            GROUP BY k.tid, k.code
            SQL);
        DB::statement('CREATE INDEX ON m_cand (tid)');

        // Choix du code : ref OSM s'il est géométriquement plausible, sinon le plus voté.
        DB::statement(<<<SQL
            CREATE TEMP TABLE m_dec AS
            WITH n AS (SELECT tid, COUNT(*) AS n FROM m_pts GROUP BY tid),
            iso AS (SELECT tid, MAX(ncodes) = 1 AS isolated FROM m_pt1 GROUP BY tid),
            pick AS (
                SELECT DISTINCT ON (c.tid) c.*, (c.code = ANY (t.refs)) AS ref_ok
                FROM m_cand c JOIN m_tr t ON t.id = c.tid JOIN n ON n.tid = c.tid
                ORDER BY c.tid, (c.code = ANY (t.refs) AND c.near >= {$share} * n.n) DESC,
                         c.votes DESC, c.votes_loose DESC, c.med NULLS LAST
            )
            SELECT t.id AS tid, t.cls, cardinality(t.refs) > 0 AS has_ref, COALESCE(n.n, 0) AS n,
                   pick.code, l.id AS line_id, pick.statut, pick.ref_ok, COALESCE(iso.isolated, false) AS isolated,
                   pick.med,
                   CASE WHEN pick.ref_ok THEN pick.near ELSE pick.votes END::float / NULLIF(n.n, 0) AS share,
                   pick.votes_loose::float / NULLIF(n.n, 0) AS share_loose,
                   CASE WHEN pick.ref_ok THEN 0 ELSE pick.amb::float / NULLIF(pick.votes, 0) END AS amb,
                   NULL::text AS reason, false AS accepted, NULL::float AS score
            FROM m_tr t
            LEFT JOIN n ON n.tid = t.id
            LEFT JOIN pick ON pick.tid = t.id
            LEFT JOIN iso ON iso.tid = t.id
            -- Pseudo-code « L<id> » d'une ligne exploitée hors RFN : la ligne elle-même.
            LEFT JOIN LATERAL (
                SELECT CASE WHEN pick.code LIKE '{$prefix}%' THEN CAST(substr(pick.code, 2) AS bigint)
                            ELSE (SELECT id FROM lines WHERE code = pick.code ORDER BY id LIMIT 1) END AS id
            ) l ON true
            SQL);
        DB::statement('CREATE INDEX ON m_dec (tid)');

        // Décision, dans l'ordre des motifs (le premier qui s'applique).
        DB::update(<<<SQL
            UPDATE m_dec SET reason = CASE
                WHEN code IS NULL THEN 'hors_rfn'
                WHEN cls = 'projet' THEN 'projet'
                WHEN line_id IS NULL THEN 'code_absent'
                WHEN has_ref AND NOT ref_ok THEN 'ref_conflit'
                WHEN share >= {$share} AND COALESCE(amb, 0) <= {$amb} AND (med <= {$maxmed} OR isolated) THEN 'strict'
                WHEN share_loose >= {$share} THEN 'souple_candidat'
                WHEN share >= {$share} AND amb > {$amb} THEN 'ambigu'
                WHEN share >= {$share} THEN 'ecart'
                WHEN share > 0 OR share_loose > 0 THEN 'partiel'
                ELSE 'hors_rfn' END
            SQL);
        DB::update("UPDATE m_dec SET accepted = true WHERE reason = 'strict'");
        $this->coherenceChecks();

        // Souple : refusé s'il double une voie active déjà rattachée au même code
        // (≥ 30 % de ses points à ≤ 60 m) - c'est alors une voie parallèle (embranchement,
        // ligne privée), pas la ligne dont la forme SNCF serait décalée.
        DB::update(<<<'SQL'
            UPDATE m_dec d SET reason = 'double'
            WHERE d.reason = 'souple_candidat'
              AND (SELECT COUNT(*) FROM m_pts pt WHERE pt.tid = d.tid AND EXISTS (
                      SELECT 1 FROM m_dec a JOIN m_tr ta ON ta.id = a.tid
                      WHERE a.accepted AND a.cls = 'active' AND a.code = d.code AND ST_DWithin(ta.g, pt.p, 60)
                  )) >= 0.3 * d.n
            SQL);

        // Continuité : un candidat « souple » doit toucher un tracé déjà rattaché au même code.
        for ($i = 0; $i < 30; $i++) {
            $n = DB::update(<<<'SQL'
                UPDATE m_dec d SET accepted = true, reason = 'souple'
                FROM m_tr td
                WHERE d.reason = 'souple_candidat' AND td.id = d.tid
                  AND EXISTS (
                      SELECT 1 FROM m_dec a JOIN m_tr ta ON ta.id = a.tid
                      WHERE a.accepted AND a.code = d.code AND ST_DWithin(ta.g, ST_Boundary(td.g), 2)
                  )
                SQL);
            if (! $n) {
                break;
            }
        }
        DB::update("UPDATE m_dec SET reason = 'partiel' WHERE reason = 'souple_candidat'");

        $this->coherenceChecks();

        DB::update(<<<'SQL'
            UPDATE m_dec SET score = ROUND((
                share * (1 - COALESCE(amb, 0)) * GREATEST(0.5, LEAST(1, 1 - (COALESCE(med, 0) - 10) / 50))
                + CASE WHEN ref_ok THEN 0.1 ELSE 0 END)::numeric, 3)
            WHERE accepted AND reason = 'strict'
            SQL);
        DB::update(<<<'SQL'
            UPDATE m_dec SET score = ROUND((0.6 * share_loose)::numeric, 3) WHERE accepted AND reason = 'souple'
            SQL);
        DB::update('UPDATE m_dec SET score = LEAST(1, score) WHERE accepted');
    }

    /**
     * Contrôles nature / statut appliqués aux tracés acceptés (n'ôtent que des
     * rattachements ; rejoués après la propagation souple).
     */
    private function coherenceChecks(): void
    {
        // Statut : voie disparue sur une ligne exploitée = ancien tracé.
        DB::update(<<<'SQL'
            UPDATE m_dec SET accepted = false, reason = 'etat'
            WHERE accepted AND cls = 'vanished' AND statut = 'Exploitée'
            SQL);
        // Voie non exploitée qui longe une voie active du même code = voie parallèle délaissée.
        DB::update(<<<'SQL'
            UPDATE m_dec d SET accepted = false, reason = 'parallele'
            WHERE d.accepted AND d.cls = 'inactive' AND d.statut = 'Exploitée'
              AND (SELECT COUNT(*) FROM m_pts pt WHERE pt.tid = d.tid AND EXISTS (
                      SELECT 1 FROM m_dec a JOIN m_tr ta ON ta.id = a.tid
                      WHERE a.accepted AND a.cls = 'active' AND a.code = d.code AND ST_DWithin(ta.g, pt.p, 25)
                  )) >= 0.5 * d.n
            SQL);

        // Voie de service : la BD TOPO classe aussi en « Voie de service » des lignes
        // fret entières (ex. 086000 Conflans-Jarny-Metz). Elle n'est donc refusée
        // que si elle double la ligne : ≥ 30 % de ses points à ≤ 60 m d'une voie
        // active rattachée au même code (garage, faisceau, embranchement)…
        DB::update(<<<'SQL'
            UPDATE m_dec d SET accepted = false, reason = 'voie_service'
            WHERE d.accepted AND d.cls = 'service' AND NOT d.ref_ok AND d.statut <> 'Transférée en voie de service'
              AND (SELECT COUNT(*) FROM m_pts pt WHERE pt.tid = d.tid AND EXISTS (
                      SELECT 1 FROM m_dec a JOIN m_tr ta ON ta.id = a.tid
                      WHERE a.accepted AND a.cls = 'active' AND a.code = d.code AND ST_DWithin(ta.g, pt.p, 60)
                  )) >= 0.3 * d.n
            SQL);
        // … ou si une autre voie de service plus proche de la forme SNCF la longe
        // (faisceau d'une gare de ligne fret : on ne garde que la voie la plus juste).
        DB::update(<<<'SQL'
            UPDATE m_dec d SET accepted = false, reason = 'voie_service'
            WHERE d.accepted AND d.cls = 'service' AND NOT d.ref_ok AND d.statut <> 'Transférée en voie de service'
              AND (SELECT COUNT(*) FROM m_pts pt WHERE pt.tid = d.tid AND EXISTS (
                      SELECT 1 FROM m_dec a JOIN m_tr ta ON ta.id = a.tid
                      WHERE a.accepted AND a.cls = 'service' AND a.code = d.code AND a.tid <> d.tid
                        AND (a.med < d.med OR (a.med = d.med AND a.tid < d.tid))
                        AND ST_DWithin(ta.g, pt.p, 25)
                  )) >= 0.5 * d.n
            SQL);
    }

    private function report(string $source): void
    {
        if ($this->lineIds !== null) {
            $rows = DB::select(<<<'SQL'
                SELECT d.line_id, l.name, d.reason, d.cls, COUNT(*) AS n, ROUND(CAST(SUM(ST_Length(t.g)) / 1000.0 AS numeric), 1) AS km
                FROM m_dec d JOIN m_tr t ON t.id = d.tid JOIN lines l ON l.id = d.line_id
                WHERE d.accepted AND d.line_id = ANY (CAST(? AS bigint[]))
                GROUP BY 1, 2, 3, 4 ORDER BY 1, 3, 4
                SQL, ['{'.implode(',', $this->lineIds).'}']);
            $this->table(['Ligne', 'Nom', 'Motif', 'Classe', 'Tracés', 'km'],
                array_map(fn ($r) => [$r->line_id, mb_strimwidth($r->name, 0, 45, '…'), $r->reason, $r->cls, $r->n, $r->km], $rows));
        }

        $rows = DB::select(<<<'SQL'
            SELECT d.accepted, d.reason, d.cls, COUNT(*) AS n, ROUND(SUM(ST_Length(t.g)) / 1000.0) AS km
            FROM m_dec d JOIN m_tr t ON t.id = d.tid
            GROUP BY 1, 2, 3 ORDER BY 1 DESC, 2, 3
            SQL);
        $this->table(['Rattaché', 'Motif', 'Classe', 'Tracés', 'km'],
            array_map(fn ($r) => [$r->accepted ? 'oui' : 'non', $r->reason, $r->cls, $r->n, $r->km], $rows));

        $s = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) FILTER (WHERE accepted) AS ok, COUNT(*) AS n,
                   ROUND(SUM(ST_Length(t.g)) FILTER (WHERE accepted) / 1000.0) AS km_ok,
                   ROUND(AVG(score) FILTER (WHERE accepted)::numeric, 3) AS score
            FROM m_dec d JOIN m_tr t ON t.id = d.tid
            SQL);
        $this->info("  {$source} : {$s->ok}/{$s->n} tracés rattachés ({$s->km_ok} km), score moyen {$s->score}.");

        // États divergents (rattachés malgré tout : la géométrie est la bonne, l'attribut diffère).
        $e = DB::selectOne(<<<'SQL'
            SELECT ROUND(SUM(ST_Length(t.g)) FILTER (WHERE d.cls = 'inactive' AND d.statut = 'Exploitée') / 1000.0) AS inactive_on_open,
                   ROUND(SUM(ST_Length(t.g)) FILTER (WHERE d.cls = 'active' AND d.statut NOT IN
                       ('Exploitée', 'Neutralisée', 'S9A3 - Ligne en travaux', 'Transférée en voie de service',
                        'Neutralisée et conservée pour les besoins de la Défense')) / 1000.0) AS active_on_closed
            FROM m_dec d JOIN m_tr t ON t.id = d.tid WHERE d.accepted
            SQL);
        $this->line("  États divergents rattachés : {$e->inactive_on_open} km non exploités sur forme « Exploitée », {$e->active_on_closed} km actifs sur forme fermée/déclassée.");

        if ($source === 'osm') {
            // Validation du vote géométrique par les ref OSM : code le plus voté (sans
            // tenir compte du ref) vs ref, pour les ways actifs qui portent un ref RFN.
            $v = DB::selectOne(<<<'SQL'
                WITH best AS (
                    SELECT DISTINCT ON (c.tid) c.tid, c.code, c.votes
                    FROM m_cand c ORDER BY c.tid, c.votes DESC, c.med NULLS LAST
                )
                SELECT COUNT(*) AS n, COUNT(*) FILTER (WHERE b.code = ANY (t.refs)) AS agree
                FROM m_tr t JOIN best b ON b.tid = t.id JOIN m_dec d ON d.tid = t.id
                WHERE cardinality(t.refs) > 0 AND t.cls = 'active' AND b.votes::float / NULLIF(d.n, 0) >= 0.8
                SQL);
            if ($v->n) {
                $this->line(sprintf('  Contrôle ref OSM : le vote géométrique désigne le code du ref pour %d/%d ways (%.1f %%).',
                    $v->agree, $v->n, 100 * $v->agree / $v->n));
            }
        }
    }

    /** Pourquoi les tracés proches d'une ligne ne lui sont pas (tous) rattachés. */
    private function explain(string $code): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT d.accepted, d.reason, d.cls, CASE WHEN d.code = c.code THEN 'ce code' ELSE d.code END AS pick,
                   COUNT(*) AS n, ROUND((SUM(ST_Length(t.g)) / 1000.0)::numeric, 1) AS km
            FROM m_cand c JOIN m_dec d ON d.tid = c.tid JOIN m_tr t ON t.id = c.tid
            WHERE c.code = ? AND c.near > 0
            GROUP BY 1, 2, 3, 4 ORDER BY 1 DESC, 6 DESC
            SQL, [$code]);
        $this->line("  Tracés à ≤ buffer de la forme {$code} :");
        $this->table(['Rattaché', 'Motif', 'Classe', 'Code retenu', 'Tracés', 'km'],
            array_map(fn ($r) => [$r->accepted ? 'oui' : 'non', $r->reason, $r->cls, $r->pick, $r->n, $r->km], $rows));
    }

    private function write(string $source): void
    {
        // --line : seuls les tracés rattachés aux lignes visées (avant ou après) sont réécrits,
        // chacun avec sa décision du recalcul (même résultat qu'un recalcul complet pour eux).
        $ids = $this->lineIds === null ? null : '{'.implode(',', $this->lineIds).'}';
        DB::transaction(function () use ($source, $ids): void {
            DB::statement('DROP TABLE IF EXISTS m_touched');
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE m_touched AS
                SELECT t.id FROM source_traces t
                WHERE t.source = ? AND (t.line_id IS NOT NULL OR t.match_method IS NOT NULL)
                  AND (CAST(? AS text) IS NULL OR t.line_id = ANY (CAST(? AS bigint[])))
                UNION
                SELECT d.tid FROM m_dec d
                WHERE d.accepted AND (CAST(? AS text) IS NULL OR d.line_id = ANY (CAST(? AS bigint[])))
                SQL, [$source, $ids, $ids, $ids, $ids]);
            DB::update(<<<'SQL'
                UPDATE source_traces t SET line_id = NULL, match_method = NULL, match_score = NULL, updated_at = NOW()
                FROM m_touched x WHERE x.id = t.id
                SQL);
            DB::update(<<<'SQL'
                UPDATE source_traces t
                SET line_id = d.line_id, match_score = d.score, updated_at = NOW(),
                    match_method = CASE WHEN d.code LIKE ? || '%' THEN 'exploited_overlap' ELSE 'sncf_overlap' END
                FROM m_dec d JOIN m_touched x ON x.id = d.tid
                WHERE d.tid = t.id AND d.accepted
                SQL, [self::EXPLOITED_PREFIX]);
        });
    }
}
