<?php

namespace App\Services\Greenways;

use Illuminate\Support\Facades\DB;

/**
 * Rattache les voies vertes OpenStreetMap (table `present_osm_ways`, remplie par
 * `vff:import-osm-greenways`) aux lignes disparues ou fermées de VFF dont elles reprennent
 * l'emprise. Résultat dans `line_greenways`, une ligne par couple (ligne, way OSM).
 *
 * Le rattachement est MESURÉ. Chaque way est découpé en morceaux de PIECE_M mètres ; un morceau
 * est « sur l'emprise » d'une ligne quand son milieu est à AXIS_TOLERANCE_M mètres au plus de
 * l'axe de référence de la ligne (`line_reference_geometries`) ET qu'aucune voie n'est encore en
 * place à moins de CLEARANCE_M mètres : ni un tronçon en service à l'ère courante, ni une voie
 * laissée en place (`present_states`, état `track_in_place` : rails BD TOPO, OSM, SNCF). Une voie
 * verte qui longe une ligne en service, ou une voie encore posée, ne mesure donc rien : ses
 * morceaux sont écartés. Les longueurs sont celles des morceaux retenus.
 *
 * Un way est rattaché à une ligne quand, sur elle :
 *   - sa longueur commune atteint MIN_SHARED_M mètres (écarte les croisements : un chemin qui coupe
 *     l'emprise en biais n'a que 16 m à moins de 8 m de l'axe), et
 *   - cette longueur est au moins MIN_SHARE_PERCENT % de celle du way (le way est surtout sur
 *     l'ancienne emprise, pas seulement de passage).
 * Une ligne n'a de voie verte que si la somme de ces longueurs atteint MIN_LINE_TOTAL_M mètres.
 * Seuils choisis sur la distribution mesurée le 19/11/2026 (France entière, 23 222 ways à moins de
 * 8 m d'une ligne) : les ways sont presque tous soit à plus de 90 % sur l'emprise, soit à moins de
 * 40 % (parallèles ou en croisement), les seuils coupent dans le creux entre les deux.
 *
 * Seules les lignes ferroviaires au sens de VFF reçoivent une voie verte : un tramway, un métro, un
 * funiculaire, un téléphérique ou un plan incliné ne compte pas (NOT_RAILWAY_NAME, lu sur le nom de
 * la fiche). Une piste cyclable le long d'une rue tombe sinon à moins de 8 m de l'axe d'un ancien
 * tramway. Exception : un tram-train du réseau ferré national (code SNCF Réseau à 6 chiffres), qui
 * est une ligne ferroviaire.
 *
 * Candidats : les voies vertes de `present_osm_ways` (`kind = greenway` : highway=cycleway ou chemin
 * ouvert aux vélos), plus les ways ferroviaires au cycle de vie `abandoned|razed|dismantled` qui
 * portent déjà une voie cyclable (railway=abandoned + highway=cycleway) : ils ne sont pas rangés
 * dans `kind = greenway` par l'import, mais ils en sont.
 *
 * Nom affiché (`display_name`) : celui que donne GreenwayNames::displayName, calculé ici une fois pour
 * toutes et lu tel quel par la fiche, l'API, GraphQL, le serveur MCP et les tuiles de la carte.
 *
 * Rejouable : les couples recalculés sont mis à jour, ceux qui ne passent plus les seuils sont
 * supprimés de `line_greenways` (table propre à cette fonctionnalité). Une transaction : un lecteur
 * ne voit jamais un état à moitié écrit. Données © contributeurs OpenStreetMap, ODbL.
 */
final class GreenwayLinker
{
    /** Écart maximal (m) entre le milieu d'un morceau de way et l'axe de référence de la ligne. */
    public const AXIS_TOLERANCE_M = 8;

    /** Distance (m) en dessous de laquelle une voie en service ou en place invalide un morceau. */
    public const CLEARANCE_M = 30;

    /** Longueur (m) des morceaux de way mesurés. */
    public const PIECE_M = 25;

    /** Longueur commune minimale (m) d'un way avec une ligne. */
    public const MIN_SHARED_M = 50;

    /** Part minimale (%) du way qui doit suivre l'ancienne emprise. */
    public const MIN_SHARE_PERCENT = 50;

    /** Longueur commune minimale (m) d'une ligne, tous ses ways ensemble. */
    public const MIN_LINE_TOTAL_M = 300;

    /** Année la plus ancienne retenue comme ouverture d'une voie verte (avant : date de la ligne ferroviaire). */
    public const MIN_OPENED_YEAR = 1970;

    /** Nom (début, sans la mention « Ligne de ») d'une fiche qui n'est pas une ligne ferroviaire. */
    public const NOT_RAILWAY_NAME = '^(ancienne |ancien )?(ligne de )?(tramways?|tram-train|metro|métro|funiculaires?|téléphérique|telepherique|plan incliné|plan incline)( |$)';

    /** Un tram-train est ferroviaire quand sa ligne a un code SNCF Réseau à 6 chiffres (réseau ferré national). */
    public const RFN_CODE = '^[0-9]{6}$';

    /** Voie cyclable ou chemin ouvert aux vélos : way du cycle de vie ferroviaire déjà reconverti. */
    private const REUSED_RAILWAY = <<<'SQL'
        (w.kind = 'railway' AND w.tags->>'railway' IN ('abandoned', 'razed', 'dismantled')
         AND (w.tags->>'highway' = 'cycleway'
              OR (w.tags->>'highway' IN ('path', 'track', 'footway', 'bridleway', 'pedestrian')
                  AND w.tags->>'bicycle' IN ('designated', 'official'))))
        SQL;

    /**
     * @param  list<int>|null  $lineIds  limite le calcul à ces lignes (essais) ; null : toutes
     * @return array{links: int, lines: int, shared_km: float, removed: int}
     */
    public function link(?array $lineIds = null, bool $dryRun = false): array
    {
        DB::beginTransaction();
        try {
            $this->measure($lineIds);
            $result = $this->write($lineIds);
            $this->dropTemporaryTables();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
        $dryRun ? DB::rollBack() : DB::commit();

        return $result;
    }

    /** Tables temporaires : morceaux de way sur l'emprise d'une ligne, puis couples retenus. */
    private function measure(?array $lineIds): void
    {
        $tolerance = self::AXIS_TOLERANCE_M;
        $clearance = self::CLEARANCE_M;
        $reused = self::REUSED_RAILWAY;
        $zone = $lineIds === null ? '' : 'AND r.line_id = ANY(CAST(:lines AS bigint[]))';
        $bindings = $lineIds === null ? [] : ['lines' => '{'.implode(',', array_map('intval', $lineIds)).'}'];

        $this->dropTemporaryTables();

        // Axes de référence des lignes, en Lambert-93 (mètres), découpés pour que l'index spatial serve.
        DB::statement(<<<SQL
            CREATE TEMP TABLE gw_refs AS
            SELECT r.line_id, ST_Subdivide(ST_Transform(r.geom, 2154), 64) AS g
            FROM line_reference_geometries r JOIN lines l ON l.id = r.line_id
            WHERE NOT (l.name ~* :notRailway AND NOT (l.name ~* 'tram-train' AND l.code ~ :rfnCode)) {$zone}
            SQL, $bindings + ['notRailway' => self::NOT_RAILWAY_NAME, 'rfnCode' => self::RFN_CODE]);
        DB::statement('CREATE INDEX ON gw_refs USING GIST (g)');

        // Voies encore là : tronçons en service à l'ère courante, voies laissées en place.
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE gw_tracks AS
            SELECT ST_Subdivide(g, 64) AS g FROM (
                SELECT ST_Transform(s.geom, 2154) AS g FROM line_segments s JOIN eras e ON e.id = s.era_id AND e.is_current
                WHERE s.status IN ('in_service', 'single_track')
                UNION ALL
                SELECT ST_Transform(geom, 2154) FROM present_states WHERE state = 'track_in_place'
            ) t
            SQL);
        DB::statement('CREATE INDEX ON gw_tracks USING GIST (g)');

        // Ways candidats, avec l'itinéraire cyclable nommé dont ils sont membres et l'année d'ouverture.
        DB::statement(<<<SQL
            CREATE TEMP TABLE gw_cand AS
            SELECT w.id, w.name, w.highway, w.length_m, w.seen_at, ST_Transform(w.geom, 2154) AS g,
                   rel.id AS relation_id, rel.name AS relation_name,
                   CASE WHEN w.kind = 'greenway' AND w.start_year >= :minYear THEN w.start_year ELSE rel.year END AS opened_year
            FROM present_osm_ways w
            LEFT JOIN LATERAL (
                SELECT (r->>'id')::bigint AS id, r->>'name' AS name,
                       NULLIF(substring(r->>'start_date' FROM '(19[7-9][0-9]|20[0-9][0-9])'), '')::int AS year
                FROM jsonb_array_elements(COALESCE(w.routes, '[]'::jsonb)) r
                WHERE r->>'name' IS NOT NULL ORDER BY (r->>'id')::bigint LIMIT 1
            ) rel ON TRUE
            WHERE (w.kind = 'greenway' OR {$reused}) AND ST_Length(w.geom::geography) > 0
              AND EXISTS (SELECT 1 FROM gw_refs r WHERE ST_DWithin(r.g, ST_Transform(w.geom, 2154), {$tolerance}))
            SQL, ['minYear' => self::MIN_OPENED_YEAR]);
        DB::statement('CREATE INDEX ON gw_cand USING GIST (g)');

        // Morceaux de PIECE_M mètres : leur milieu est comparé à l'axe, leur tracé garde l'emprise commune.
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE gw_pieces AS
            SELECT id, i, c, ST_LineInterpolatePoint(c, 0.5) AS m, ST_Length(c) AS len
            FROM (
                SELECT cand.id, i, ST_LineSubstring(cand.g, i::float8 / n, (i + 1)::float8 / n) AS c
                FROM gw_cand cand, LATERAL (SELECT GREATEST(1, CEIL(ST_Length(cand.g) / :piece))::int AS n) k, generate_series(0, n - 1) AS i
            ) x
            SQL, ['piece' => self::PIECE_M]);
        DB::statement('CREATE INDEX ON gw_pieces USING GIST (m)');

        // Morceaux sur l'axe d'une ligne, sans voie en service ni en place à proximité.
        DB::statement(<<<SQL
            CREATE TEMP TABLE gw_pairs AS
            SELECT x.id, x.line_id, SUM(x.len) AS shared_m, ST_LineMerge(ST_Collect(x.c ORDER BY x.i)) AS g
            FROM (
                SELECT DISTINCT p.id, p.i, p.c, p.len, r.line_id
                FROM gw_pieces p JOIN gw_refs r ON ST_DWithin(r.g, p.m, {$tolerance})
            ) x
            WHERE NOT EXISTS (SELECT 1 FROM gw_tracks t WHERE ST_DWithin(t.g, ST_LineInterpolatePoint(x.c, 0.5), {$clearance}))
            GROUP BY x.id, x.line_id
            SQL);

        // Seuils : par way, puis par ligne.
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE gw_new AS
            SELECT p.line_id, c.id AS osm_way_id, c.relation_id AS osm_relation_id, c.name, c.relation_name, c.highway,
                   ROUND(p.shared_m)::int AS shared_length_m,
                   GREATEST(c.length_m, ROUND(p.shared_m))::int AS way_length_m,
                   LEAST(100, ROUND(100 * p.shared_m / GREATEST(c.length_m, 1)))::int AS shared_percent,
                   c.opened_year, c.seen_at,
                   ST_Multi(ST_Transform(p.g, 4326)) AS geom
            FROM gw_pairs p JOIN gw_cand c ON c.id = p.id
            WHERE p.shared_m >= :minShared AND 100 * p.shared_m >= :minShare * GREATEST(c.length_m, 1)
              AND p.line_id IN (
                  SELECT q.line_id FROM gw_pairs q JOIN gw_cand d ON d.id = q.id
                  WHERE q.shared_m >= :minShared2 AND 100 * q.shared_m >= :minShare2 * GREATEST(d.length_m, 1)
                  GROUP BY q.line_id HAVING SUM(q.shared_m) >= :minLine)
            SQL, [
            'minShared' => self::MIN_SHARED_M, 'minShared2' => self::MIN_SHARED_M,
            'minShare' => self::MIN_SHARE_PERCENT, 'minShare2' => self::MIN_SHARE_PERCENT,
            'minLine' => self::MIN_LINE_TOTAL_M,
        ]);
    }

    /**
     * Calcule `display_name`, le nom affiché de chaque couple (GreenwayNames::displayName), à partir du nom
     * de l'itinéraire OSM et du nom du way. La règle est écrite en PHP : elle est appliquée une fois par
     * couple de noms distincts, puis écrite en une requête. Seules les lignes dont le nom change sont touchées.
     *
     * @return int nombre de couples (ligne, way) dont le nom affiché a changé
     */
    public function fillDisplayNames(): int
    {
        $names = DB::table('line_greenways')->distinct()->get(['name', 'relation_name'])
            ->map(fn (object $row): array => [
                'n' => $row->name,
                'r' => $row->relation_name,
                'd' => GreenwayNames::displayName($row->relation_name, $row->name),
            ])->all();
        if ($names === []) {
            return 0;
        }

        return DB::affectingStatement(<<<'SQL'
            UPDATE line_greenways g SET display_name = v.d
            FROM jsonb_to_recordset(CAST(:names AS jsonb)) AS v(n text, r text, d text)
            WHERE g.name IS NOT DISTINCT FROM v.n AND g.relation_name IS NOT DISTINCT FROM v.r
              AND g.display_name IS DISTINCT FROM v.d
            SQL, ['names' => json_encode($names, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    private function dropTemporaryTables(): void
    {
        foreach (['gw_cand', 'gw_refs', 'gw_tracks', 'gw_pieces', 'gw_pairs', 'gw_new'] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }
    }

    /** @return array{links: int, lines: int, shared_km: float, removed: int} */
    private function write(?array $lineIds): array
    {
        DB::statement(<<<'SQL'
            INSERT INTO line_greenways (line_id, osm_way_id, osm_relation_id, name, relation_name, highway, shared_length_m,
                                        way_length_m, shared_percent, opened_year, source, osm_seen_at, created_at, updated_at, geom)
            SELECT line_id, osm_way_id, osm_relation_id, name, relation_name, highway, shared_length_m,
                   way_length_m, shared_percent, opened_year, 'osm', seen_at, NOW(), NOW(), geom
            FROM gw_new
            ON CONFLICT (line_id, osm_way_id) DO UPDATE SET
                osm_relation_id = EXCLUDED.osm_relation_id, name = EXCLUDED.name, relation_name = EXCLUDED.relation_name,
                highway = EXCLUDED.highway, shared_length_m = EXCLUDED.shared_length_m, way_length_m = EXCLUDED.way_length_m,
                shared_percent = EXCLUDED.shared_percent, opened_year = EXCLUDED.opened_year, osm_seen_at = EXCLUDED.osm_seen_at,
                geom = EXCLUDED.geom, updated_at = NOW()
            SQL);

        $this->fillDisplayNames();

        // Couples qui ne passent plus les seuils (way modifié, voie verte supprimée d'OSM, ligne remise en service).
        $zone = $lineIds === null ? '' : 'AND g.line_id = ANY(CAST(:lines AS bigint[]))';
        $removed = DB::affectingStatement(<<<SQL
            DELETE FROM line_greenways g
            WHERE NOT EXISTS (SELECT 1 FROM gw_new n WHERE n.line_id = g.line_id AND n.osm_way_id = g.osm_way_id) {$zone}
            SQL, $lineIds === null ? [] : ['lines' => '{'.implode(',', array_map('intval', $lineIds)).'}']);

        $totals = DB::selectOne('SELECT COUNT(*) AS links, COUNT(DISTINCT line_id) AS lines, COALESCE(SUM(shared_length_m), 0) AS metres FROM gw_new');

        return [
            'links' => (int) $totals->links,
            'lines' => (int) $totals->lines,
            'shared_km' => round($totals->metres / 1000, 1),
            'removed' => $removed,
        ];
    }
}
