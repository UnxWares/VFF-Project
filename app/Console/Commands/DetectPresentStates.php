<?php

namespace App\Console\Commands;

use App\Services\SectionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remplit `present_states` : l'état ACTUEL observé de l'emprise des voies
 * (voie en place, déposée, voie verte, route), et écrit les reconversions
 * DATÉES dans `line_events` (type conversion).
 *
 * Pourquoi : les libellés SNCF (« Fermée », « Déclassée vendue »,
 * « Neutralisée ») sont administratifs. Une ligne « Fermée » peut être une voie
 * verte depuis vingt ans (La Chapelle-Anthenaise - Flers), et une voie verte peut
 * avoir été posée JUSTE À CÔTÉ d'une voie laissée en place. Seules des preuves de
 * terrain tranchent ; le moteur décennal (EraTimeline::stateAt) en déduit
 * l'affichage : une voie disparue n'apparaît « déposée » que sur la carte de la
 * décennie qui suit sa dépose, puis plus du tout.
 *
 * Preuves, de la plus forte à la plus faible (calculs en Lambert-93) :
 *   1. RAILS cartographiés → track_in_place : BD TOPO « En service » ou
 *      « Non exploité », OSM railway=rail|narrow_gauge|light_rail|disused|
 *      preserved (ou disused:railway sans autre usage). Voies en service :
 *      seulement près d'une emprise fermée ou disparue, ou voies principales
 *      rattachées à aucune ligne VFF (trains touristiques, voies forestières :
 *      une ligne au tracé archeo recalé sur ces rails n'a pas disparu). Le moteur fait primer
 *      track_in_place sur tout le reste (≤ 15 m). Un tronçon BD TOPO
 *      « Non exploité » est écarté quand OSM le CONTREDIT explicitement :
 *      way railway=abandoned|razed portant un highway=* (≤ 8 m sur ≥ 70 % : le
 *      contributeur affirme que la plateforme porte un chemin / une route), ou
 *      voie verte OSM sur l'axe même (≤ 5 m sur ≥ 70 %), ou tronçon SNCF
 *      « Fermée et déposée » (≤ 15 m sur ≥ 70 %), sans rails OSM à 8 m
 *      (BD TOPO en retard sur une dépose récente, ex. 436000 à Mayenne).
 *   2. OSM, plateforme sans rails → way railway=abandoned|razed|dismantled ou
 *      <cycle>:railway : highway=cycleway / chemin cyclable / nom « voie verte »
 *      → greenway ; highway routier → road ; sinon dismantled (reuse « path »
 *      pour un simple chemin).
 *   3. OSM, voie verte sans tag ferroviaire → greenway si elle suit l'axe d'une
 *      ancienne voie (tracés de référence, OSM abandonné, SNCF non exploité,
 *      archeoferroviaire, OHM) à ≤ 8 m sur ≥ 70 % de sa longueur (ou un tronçon
 *      continu ≥ 300 m), SANS rails à ≤ 15 m. Une voie verte À CÔTÉ d'une voie
 *      laissée en place (rails non exploités à 8-40 m sur ≥ 50 % du way, ou
 *      rails à ≤ 40 m sur ≥ 50 % de sa partie sur l'axe) ne produit RIEN : les
 *      rails produisent track_in_place (propriété voie_verte_a_cote).
 *   4. lignes-oubliees, rubrique « État actuel » par section (fiches en cache) :
 *      « déposée (voie verte) », « piste cyclable » → greenway ; « déposée » →
 *      dismantled ; « déposée (tunnel routier) » → road ; « inexploitée »,
 *      vélorail, service… → track_in_place. Les notes (« Une voie verte a été
 *      ouverte en 2006 entre Audincourt et Beaucourt ») donnent des sous-sections
 *      greenway, parfois datées. Portions résolues par App\Services\SectionResolver
 *      (PK calés, sinon lieux) dans une transaction annulée : rien n'est laissé
 *      dans line_events.
 *   5. SNCF : « Fermée et déposée » → dismantled ; « Fermée avec maintien en
 *      place de la voie », « Fermée non déposée », « Neutralisée » → track_in_place.
 * Les preuves déclaratives (4, 5) « voie en place » sont amputées de toute
 * portion contredite par une preuve de disparition (≤ 15 m), et les
 * « déposée » génériques des portions où la reconversion est connue (voie verte,
 * route), pour que le moteur affiche la bonne reconversion.
 *
 * since_year (le moteur s'en sert comme date de DÉPOSE) : start_date /
 * opening_date du way (voie verte ≥ 1970, way ferroviaire ≥ 1980), d'une relation
 * vélo nommée « voie verte » (hors projets), ou année d'une note lignes-oubliees.
 * Il n'est gardé que si la portion n'a pas de fermeture connue plus de
 * MAX_GAP_YEARS ans avant (sinon la dépose est bien antérieure à la voie verte :
 * l'année reste dans properties.voie_verte_depuis).
 * Événements : `conversion` (reuse greenway) quand l'année est connue ET qu'une
 * fermeture localisée la précède de ≤ MAX_GAP_YEARS ans, sur la portion du tracé
 * de référence longée par la voie verte (section_resolution 'trace' pour OSM,
 * méthode du résolveur pour lignes-oubliees). Pas d'événement si la ligne a déjà
 * une dépose / reconversion d'une autre source sur cette portion (ou non
 * localisée) : la date de dépose connue prime, et une reconversion postérieure
 * ferait réapparaître la voie sur la carte. Idem si la portion est encore
 * ouverte / fermée après l'année annoncée (date incohérente).
 *
 * Rejouable : supprime puis réécrit ses seules lignes (properties.detector,
 * empreintes d'événements « present:… »), dans la zone traitée.
 *
 *   php artisan vff:import-osm-greenways               (avant, pour les voies vertes)
 *   php artisan vff:detect-present-states
 *   php artisan vff:detect-present-states --line=436000 --line=476000 --dry-run
 *   php artisan vff:build-era-segments                  (après)
 */
class DetectPresentStates extends Command
{
    protected $signature = 'vff:detect-present-states
        {--line=* : limite à la zone (3 km) autour de ces lignes (code ou id), pour tester}
        {--no-events : n\'écrit pas les événements datés}
        {--no-lo : ignore les fiches lignes-oubliees}
        {--dry-run : calcule et affiche, sans rien écrire}
        {--report : n\'affiche que le bilan (km par état sur les tracés de référence, comme le voit le moteur)}';

    protected $description = 'Détecte l\'état actuel des emprises (voie en place, déposée, voie verte, route) et les reconversions datées.';

    public const DETECTOR = 'vff:detect-present-states';

    /** Voie verte sur l'axe de l'ancienne voie : distance, part du way, ou tronçon continu. */
    private const AXIS_TOL_M = 8;

    private const AXIS_SHARE = 0.7;

    private const AXIS_RUN_M = 300;

    /** Pas d'analyse des voies vertes (m). */
    private const CHUNK_M = 25;

    /** Rails à moins de ça d'un morceau de voie verte : ce n'est pas la plateforme libérée. */
    private const RAILS_CLEAR_M = 15;

    /** Voie verte À CÔTÉ d'une voie laissée en place : écart. */
    private const ALONGSIDE_MIN_M = 8;

    private const ALONGSIDE_MAX_M = 40;

    /** Écart max (ans) entre la fermeture connue et l'ouverture de la voie verte pour dater la dépose. */
    private const MAX_GAP_YEARS = 15;

    /** Tolérance d'effacement d'une preuve contredite (= PRESENT_TOL_M du moteur). */
    private const COVER_M = 15;

    private const ROAD_HIGHWAYS = '^(motorway|trunk|primary|secondary|tertiary|unclassified|residential|living_street|service|road|busway)(_link){0,1}$';

    /** Nom de voie verte (sans « ? » : PDO le prendrait pour un paramètre). */
    private const GREEN_NAME = '(voie|coul[ée]e|chemin) vert|v[ée]lo[ -]{0,1}route|piste cyclable|v[ée]lo[ -]{0,1}voie';

    private const SNCF_IN_PLACE = ['Fermée avec maintien en place de la voie', 'Fermée non déposée (Plus utilisable)', 'Neutralisée',
        'Neutralisée et conservée pour les besoins de la Défense'];

    private const SNCF_DISMANTLED = ['Fermée et déposée (Plus utilisable)'];

    private bool $zoned = false;

    public function handle(SectionResolver $resolver): int
    {
        ini_set('memory_limit', '2G');
        $t0 = microtime(true);
        $this->zone();
        $this->info('Zone : '.($this->zoned ? 'autour de '.implode(', ', (array) $this->option('line')) : 'France entière'));
        if ($this->option('report')) {
            $this->report();

            return self::SUCCESS;
        }

        $this->step('OSM : ways ferroviaires (rails / cycle de vie)', fn () => $this->osmWays());
        $this->step('SNCF : statuts', fn () => $this->sncf());
        $this->step('Rails cartographiés (BD TOPO, OSM)', fn () => $this->rails());
        $this->step('Axes des anciennes voies', fn () => $this->axis());
        $this->step('Voies vertes OSM sur l\'axe', fn () => $this->greenways());
        $this->step('lignes-oubliees : état actuel', fn () => $this->option('no-lo') ? $this->emptyLo() : $this->lignesOubliees($resolver));
        $this->step('Arbitrage', fn () => $this->assemble());

        $this->summary();
        if ($this->option('dry-run')) {
            $this->warn('--dry-run : rien n\'a été écrit.');

            return self::SUCCESS;
        }
        $this->step('Écriture present_states', fn () => $this->write());
        if (! $this->option('no-events')) {
            $this->step('Événements datés (conversion)', fn () => $this->events());
        }
        $this->report();
        $this->info(sprintf('Terminé en %d s.', microtime(true) - $t0));

        return self::SUCCESS;
    }

    /**
     * Bilan VÉRIFIÉ, tel que le moteur décennal le verra : tracés de référence
     * découpés en tronçons de 100 m, observation retenue à ≤ 15 m (voie en place
     * d'abord, puis la plus ancienne datée), comme vff:build-era-segments.
     */
    private function report(): void
    {
        $lines = $this->zoned ? '(SELECT line_id FROM line_reference_geometries WHERE ST_Intersects(geom, (SELECT g4326 FROM dp_zone)))' : null;
        DB::statement('DROP TABLE IF EXISTS dp_ps');
        DB::statement('CREATE TEMP TABLE dp_ps AS SELECT state, since_year, source, ST_Transform(geom, 2154) AS g FROM present_states WHERE '.$this->inZone('geom', 4326));
        DB::statement('CREATE INDEX ON dp_ps USING GIST (g)');
        DB::statement('DROP TABLE IF EXISTS dp_eval');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_eval AS
            WITH parts AS (
                SELECT p.line_id, p.source, d.geom AS g FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d
                WHERE {$this->inZone('p.geom', 4326)}
            ),
            chunks AS (
                SELECT line_id, source, ST_LineSubstring(g, i::float8 / n, (i + 1)::float8 / n) AS c
                FROM parts, LATERAL (SELECT GREATEST(1, CEIL(ST_Length(g) / 100))::int AS n) k, generate_series(0, n - 1) AS i
            )
            SELECT line_id, source, ST_Length(c) AS len,
                   (SELECT ps.state FROM dp_ps ps WHERE ST_DWithin(ps.g, ST_LineInterpolatePoint(c, 0.5), 15)
                    ORDER BY (ps.state = 'track_in_place') DESC, ps.since_year NULLS LAST LIMIT 1) AS state
            FROM chunks
            SQL);
        if ($lines) {
            DB::statement("DELETE FROM dp_eval WHERE line_id NOT IN {$lines}");
        }
        $rows = DB::select(<<<'SQL'
            SELECT COALESCE(state, '(aucune observation)') AS state,
                   ROUND((SUM(len) FILTER (WHERE source <> 'archeoferroviaire') / 1000.0)::numeric) AS km_precis,
                   ROUND((SUM(len) FILTER (WHERE source = 'archeoferroviaire') / 1000.0)::numeric) AS km_archeo,
                   ROUND((SUM(len) / 1000.0)::numeric) AS km, COUNT(DISTINCT line_id) AS lignes
            FROM dp_eval GROUP BY 1 ORDER BY 4 DESC
            SQL);
        $this->info('Bilan sur les tracés de référence (tronçons de 100 m, observation à ≤ 15 m) :');
        $this->table(['État retenu', 'km (tracé IGN/OSM/SNCF)', 'km (tracé archeo)', 'km', 'lignes'],
            array_map(fn ($r) => [$r->state, $r->km_precis, $r->km_archeo, $r->km, $r->lignes], $rows));
        $rows = DB::select(<<<'SQL'
            SELECT source, state, COUNT(*) AS n, ROUND((SUM(ST_Length(g)) / 1000.0)::numeric) AS km, COUNT(since_year) AS dated
            FROM dp_ps GROUP BY 1, 2 ORDER BY 1, 2
            SQL);
        $this->table(['Source', 'État', 'Observations', 'km observés', 'datées'],
            array_map(fn ($r) => [$r->source, $r->state, $r->n, $r->km, $r->dated], $rows));
    }

    private function step(string $label, callable $fn): void
    {
        $t = microtime(true);
        $this->line("→ {$label}…");
        $msg = $fn();
        $this->line(sprintf('  %s(%.1f s)', $msg ? $msg.' ' : '', microtime(true) - $t));
    }

    // ------------------------------------------------------------------
    // Zone de travail
    // ------------------------------------------------------------------

    private function zone(): void
    {
        DB::statement('DROP TABLE IF EXISTS dp_zone');
        DB::statement('CREATE TEMP TABLE dp_zone (g geometry, g4326 geometry)');
        $refs = array_filter((array) $this->option('line'));
        if (! $refs) {
            return;
        }
        $ids = DB::table('lines')->whereIn('code', $refs)
            ->orWhereIn('id', array_map('intval', array_filter($refs, fn ($r) => ctype_digit($r) && strlen($r) < 6)))->pluck('id')->all();
        DB::insert(
            'INSERT INTO dp_zone SELECT g, ST_Transform(g, 4326) FROM (
                SELECT ST_Buffer(ST_Union(ST_Transform(geom, 2154)), 3000) g FROM line_reference_geometries WHERE line_id = ANY(CAST(? AS bigint[]))) z
             WHERE g IS NOT NULL',
            ['{'.implode(',', $ids ?: [0]).'}'],
        );
        $this->zoned = DB::selectOne('SELECT COUNT(*) n FROM dp_zone')->n > 0;
        if (! $this->zoned) {
            $this->warn('Aucun tracé de référence pour ces lignes : France entière.');
        }
    }

    /** Filtre de zone sur une colonne géométrique (4326 si $srid = 4326). */
    private function inZone(string $col, int $srid = 2154): string
    {
        if (! $this->zoned) {
            return 'TRUE';
        }

        return $srid === 4326
            ? "({$col} && (SELECT g4326 FROM dp_zone) AND ST_Intersects({$col}, (SELECT g4326 FROM dp_zone)))"
            : "ST_Intersects({$col}, (SELECT g FROM dp_zone))";
    }

    // ------------------------------------------------------------------
    // 1-2. OSM ferroviaire
    // ------------------------------------------------------------------

    private function osmWays(): string
    {
        DB::statement('DROP TABLE IF EXISTS dp_osm');
        // present_osm_ways (import complet du jour) d'abord, puis source_traces (vff:import-osm-railways).
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_osm AS
            SELECT DISTINCT ON (id) id, tags, routes, g, observed FROM (
                SELECT id, tags, routes, ST_Transform(geom, 2154) AS g, seen_at::date AS observed, 0 AS pri
                FROM present_osm_ways WHERE kind = 'railway' AND {$this->inZone('geom', 4326)}
                UNION ALL
                SELECT source_ref::bigint, properties, NULL::jsonb, ST_Transform(ST_LineMerge(geom), 2154), seen_at::date, 1
                FROM source_traces WHERE source = 'osm' AND source_ref ~ '^[0-9]+$' AND {$this->inZone('geom', 4326)}
            ) x ORDER BY id, pri
            SQL);
        DB::statement('ALTER TABLE dp_osm ADD COLUMN cls text, ADD COLUMN state text, ADD COLUMN reuse text, ADD COLUMN since_year int');
        DB::statement(<<<'SQL'
            UPDATE dp_osm SET cls = CASE
                WHEN tags->>'railway' IN ('rail', 'narrow_gauge', 'light_rail', 'funicular', 'disused', 'preserved') THEN 'rails'
                WHEN tags->>'railway' IN ('abandoned', 'razed', 'dismantled') THEN 'gone'
                WHEN tags->>'railway' IN ('construction', 'tram', 'subway', 'miniature', 'monorail') THEN 'skip'
                WHEN COALESCE(tags->>'abandoned:railway', tags->>'razed:railway', tags->>'removed:railway',
                              tags->>'dismantled:railway', tags->>'demolished:railway', tags->>'was:railway') IS NOT NULL THEN 'gone'
                -- disused:railway sans railway=* : rails en place, sauf si le way est devenu un chemin (ambigu → ignoré).
                WHEN COALESCE(tags->>'disused:railway', tags->>'preserved:railway') IS NOT NULL
                    THEN CASE WHEN tags->>'highway' IS NULL THEN 'rails' ELSE 'skip' END
                WHEN tags->>'historic' = 'railway' THEN 'gone'
                ELSE 'skip' END
            SQL);
        $green = self::GREEN_NAME;
        $road = self::ROAD_HIGHWAYS;
        DB::statement(<<<SQL
            UPDATE dp_osm SET
                state = CASE
                    -- chantier / projet de voie verte : rails déposés, reconversion pas encore faite
                    WHEN tags->>'highway' IN ('construction', 'proposed') OR COALESCE(tags->>'name', '') ~* 'projet|futur' THEN 'dismantled'
                    WHEN tags->>'highway' ~ '{$road}' AND NOT (COALESCE(tags->>'name', '') ~* '{$green}') THEN 'road'
                    WHEN tags->>'highway' = 'cycleway'
                      OR tags->>'bicycle' IN ('designated', 'official')
                      OR (tags->>'highway' IN ('path', 'track', 'footway', 'bridleway', 'pedestrian') AND tags->>'bicycle' IN ('yes', 'permissive'))
                      OR COALESCE(tags->>'name', '') ~* '{$green}'
                      OR (routes IS NOT NULL AND tags->>'highway' IS NOT NULL) THEN 'greenway'
                    ELSE 'dismantled' END,
                reuse = CASE WHEN tags->>'highway' IN ('path', 'track', 'footway', 'bridleway', 'pedestrian', 'steps') THEN 'path' END
            WHERE cls = 'gone'
            SQL);
        DB::statement(<<<SQL
            UPDATE dp_osm SET since_year = y FROM (
                -- start_date d'un way ferroviaire = souvent l'ouverture de la LIGNE : seulement après 1980.
                SELECT id, COALESCE(
                    CASE WHEN (substring(COALESCE(tags->>'start_date', tags->>'opening_date') FROM '(19[89][0-9]|20[0-9][0-9])'))::int <= EXTRACT(YEAR FROM NOW())
                         THEN (substring(COALESCE(tags->>'start_date', tags->>'opening_date') FROM '(19[89][0-9]|20[0-9][0-9])'))::int END,
                    (SELECT MIN((substring(r->>'start_date' FROM '(19[7-9][0-9]|20[0-9][0-9])'))::int)
                     FROM jsonb_array_elements(COALESCE(routes, '[]'::jsonb)) r WHERE r->>'name' ~* '{$green}' AND r->>'name' !~* 'projet|futur')) y
                FROM dp_osm WHERE state = 'greenway') s
            WHERE s.id = dp_osm.id AND s.y IS NOT NULL
            SQL);
        DB::statement('CREATE INDEX ON dp_osm USING GIST (g)');
        DB::statement('ANALYZE dp_osm');

        $r = DB::selectOne("SELECT COUNT(*) FILTER (WHERE cls = 'rails') rails, COUNT(*) FILTER (WHERE cls = 'gone') gone FROM dp_osm");

        return "{$r->rails} ways avec rails, {$r->gone} sans rails (cycle de vie)";
    }

    // ------------------------------------------------------------------
    // Rails cartographiés
    // ------------------------------------------------------------------

    private function rails(): string
    {
        DB::statement('DROP TABLE IF EXISTS dp_rails');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_rails AS
            SELECT 'bdtopo'::text AS src, source_ref AS ref, properties->>'etat_de_l_objet' AS detail, layer AS kind,
                   LEFT(properties->>'date_modification', 10)::date AS observed, ST_Transform(ST_LineMerge(geom), 2154) AS g,
                   FALSE AS contradicted, NULL::text AS why
            FROM source_traces
            WHERE source = 'bdtopo' AND properties->>'etat_de_l_objet' IN ('En service', 'Non exploité') AND {$this->inZone('geom', 4326)}
            UNION ALL
            SELECT 'osm', 'way/'||id, COALESCE(tags->>'railway', 'disused:railway'), tags->>'usage', observed, g, FALSE, NULL
            FROM dp_osm WHERE cls = 'rails'
            SQL);
        DB::statement('CREATE INDEX ON dp_rails USING GIST (g)');
        DB::statement('ANALYZE dp_rails');

        // Voies vertes OSM (hors ways ferroviaires) de la zone : servent aussi à contredire la BD TOPO.
        DB::statement('DROP TABLE IF EXISTS dp_green');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_green AS
            SELECT w.id, w.name, w.tags, w.routes, w.start_year, w.seen_at::date AS observed, ST_Transform(w.geom, 2154) AS g
            FROM present_osm_ways w
            WHERE w.kind = 'greenway' AND {$this->inZone('w.geom', 4326)}
            SQL);
        DB::statement('DELETE FROM dp_green WHERE id IN (SELECT id FROM dp_osm)');
        DB::statement('CREATE INDEX ON dp_green USING GIST (g)');
        DB::statement('ANALYZE dp_green');

        // BD TOPO « Non exploité » contredite par OSM (plateforme devenue chemin / route / voie verte).
        DB::statement(<<<'SQL'
            UPDATE dp_rails r SET contradicted = TRUE, why = c.why
            FROM (
                SELECT r.ref, CASE WHEN hw.len >= 0.7 * ST_Length(r.g) THEN 'osm:'||hw.ids
                                   WHEN gw.len >= 0.7 * ST_Length(r.g) THEN 'voie verte osm:'||gw.ids
                                   ELSE 'sncf « Fermée et déposée » :'||sd.refs END AS why
                FROM dp_rails r
                LEFT JOIN LATERAL (
                    SELECT ST_Length(ST_Intersection(r.g, ST_Union(ST_Buffer(o.g, 8)))) AS len, string_agg('way/'||o.id, ',') AS ids
                    FROM dp_osm o WHERE o.cls = 'gone' AND o.tags->>'highway' IS NOT NULL AND ST_DWithin(o.g, r.g, 8)
                ) hw ON TRUE
                LEFT JOIN LATERAL (
                    SELECT ST_Length(ST_Intersection(r.g, ST_Union(ST_Buffer(w.g, 5)))) AS len, string_agg('way/'||w.id, ',') AS ids
                    FROM dp_green w WHERE ST_DWithin(w.g, r.g, 5)
                ) gw ON TRUE
                LEFT JOIN LATERAL (
                    SELECT ST_Length(ST_Intersection(r.g, ST_Union(ST_Buffer(s.g, 15)))) AS len, string_agg(s.ref, ',') AS refs
                    FROM dp_sncf s WHERE s.state = 'dismantled' AND ST_DWithin(s.g, r.g, 15)
                ) sd ON TRUE
                WHERE r.src = 'bdtopo' AND r.detail = 'Non exploité'
                  AND (hw.len >= 0.7 * ST_Length(r.g)
                       OR ((gw.len >= 0.7 * ST_Length(r.g) OR sd.len >= 0.7 * ST_Length(r.g))
                           AND NOT EXISTS (SELECT 1 FROM dp_rails o WHERE o.src = 'osm' AND ST_DWithin(o.g, r.g, 8)
                                           AND ST_Length(ST_Intersection(o.g, ST_Buffer(r.g, 8))) > 50)))
            ) c
            WHERE r.ref = c.ref AND r.src = 'bdtopo'
            SQL);

        $r = DB::selectOne("SELECT COUNT(*) FILTER (WHERE src = 'bdtopo') b, COUNT(*) FILTER (WHERE src = 'osm') o,
            COUNT(*) FILTER (WHERE contradicted) c, ROUND((SUM(ST_Length(g)) FILTER (WHERE contradicted) / 1000.0)::numeric, 1) ckm FROM dp_rails");

        return "{$r->b} tronçons BD TOPO + {$r->o} ways OSM ; {$r->c} « Non exploité » contredits par OSM ({$r->ckm} km)";
    }

    // ------------------------------------------------------------------
    // Axes des anciennes voies
    // ------------------------------------------------------------------

    private function axis(): string
    {
        DB::statement('DROP TABLE IF EXISTS dp_axis');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_axis AS
            SELECT src, ST_Subdivide(g, 32) AS g FROM (
                SELECT 'osm'::text AS src, g FROM dp_osm WHERE cls = 'gone'
                UNION ALL
                SELECT 'reference', ST_Transform(geom, 2154) FROM line_reference_parts WHERE {$this->inZone('geom', 4326)}
                UNION ALL
                SELECT source, ST_Transform(geom, 2154) FROM source_traces
                WHERE source IN ('sncf', 'archeoferroviaire', 'ohm') AND {$this->inZone('geom', 4326)}
                  AND (source <> 'sncf' OR properties->>'statut' <> 'Exploitée')
                  AND (source <> 'archeoferroviaire' OR layer <> 'exploitee')
                  AND (source <> 'ohm' OR layer NOT IN ('subway', 'tram', 'proposed'))
                UNION ALL
                SELECT 'bdtopo', g FROM dp_rails WHERE src = 'bdtopo' AND detail = 'Non exploité'
            ) a
            SQL);
        DB::statement('CREATE INDEX ON dp_axis USING GIST (g)');
        DB::statement('ANALYZE dp_axis');

        return DB::selectOne('SELECT COUNT(*) n FROM dp_axis')->n.' morceaux d\'axe';
    }

    // ------------------------------------------------------------------
    // 3. Voies vertes sur l'axe, voies vertes À CÔTÉ de rails en place
    // ------------------------------------------------------------------

    private function greenways(): string
    {
        $chunk = self::CHUNK_M;
        $tol = self::AXIS_TOL_M;
        $near = self::ALONGSIDE_MAX_M;

        // Morceaux de CHUNK_M des voies vertes proches d'un axe ou de rails.
        DB::statement('DROP TABLE IF EXISTS dp_gwc');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_gwc AS
            WITH cand AS (
                SELECT id, g FROM dp_green w
                WHERE EXISTS (SELECT 1 FROM dp_axis a WHERE ST_DWithin(a.g, w.g, {$tol}))
                   OR EXISTS (SELECT 1 FROM dp_rails r WHERE NOT r.contradicted AND ST_DWithin(r.g, w.g, {$near}))
            ),
            pieces AS (
                SELECT id, i, ST_LineSubstring(g, i::float8 / n, (i + 1)::float8 / n) AS c
                FROM cand, LATERAL (SELECT GREATEST(1, CEIL(ST_Length(g) / {$chunk}))::int AS n) k, generate_series(0, n - 1) AS i
            )
            SELECT id, i, c, ST_Length(c) AS len, m,
                   EXISTS (SELECT 1 FROM dp_axis a WHERE ST_DWithin(a.g, m, {$tol})) AS on_axis,
                   (SELECT MIN(ST_Distance(r.g, m)) FROM dp_rails r WHERE NOT r.contradicted AND ST_DWithin(r.g, m, {$near})) AS rail_d,
                   -- rails NON exploités (voie laissée en place) : BD TOPO « Non exploité », OSM disused / preserved
                   (SELECT MIN(ST_Distance(r.g, m)) FROM dp_rails r
                    WHERE NOT r.contradicted AND (r.detail = 'Non exploité' OR r.detail IN ('disused', 'preserved', 'disused:railway'))
                      AND ST_DWithin(r.g, m, {$near})) AS idle_d
            FROM pieces, LATERAL (SELECT ST_LineInterpolatePoint(pieces.c, 0.5) AS m) p
            SQL);

        // Par way : part sur l'axe, voie verte à côté de rails, tronçons continus sur l'axe libérés.
        $share = self::AXIS_SHARE;
        $run = self::AXIS_RUN_M;
        $clear = self::RAILS_CLEAR_M;
        $amin = self::ALONGSIDE_MIN_M;
        DB::statement('DROP TABLE IF EXISTS dp_gwa');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_gwa AS
            SELECT id, SUM(len) AS total,
                   COALESCE(SUM(len) FILTER (WHERE on_axis), 0) AS on_axis_m,
                   COALESCE(SUM(len) FILTER (WHERE on_axis AND rail_d IS NOT NULL), 0) AS axis_near_rails_m,
                   COALESCE(SUM(len) FILTER (WHERE idle_d BETWEEN {$amin} AND {$near}), 0) AS alongside_idle_m
            FROM dp_gwc GROUP BY id
            SQL);
        DB::statement('ALTER TABLE dp_gwa ADD COLUMN alongside boolean');
        DB::statement(<<<'SQL'
            UPDATE dp_gwa SET alongside = (alongside_idle_m >= 0.5 * total AND alongside_idle_m >= 200)
                                       OR (on_axis_m > 0 AND axis_near_rails_m >= 0.5 * on_axis_m)
            SQL);

        DB::statement('DROP TABLE IF EXISTS dp_gw');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_gw AS
            WITH ok AS (
                SELECT c.*, i - ROW_NUMBER() OVER (PARTITION BY c.id ORDER BY i) AS grp
                FROM dp_gwc c
                WHERE c.on_axis AND (c.rail_d IS NULL OR c.rail_d > {$clear})
            ),
            runs AS (
                SELECT ok.*, SUM(len) OVER (PARTITION BY id, grp) AS run_m FROM ok
            )
            SELECT r.id, ST_LineMerge(ST_Collect(r.c ORDER BY r.i)) AS g, SUM(r.len) AS len, a.total, a.on_axis_m
            FROM runs r JOIN dp_gwa a USING (id)
            WHERE NOT a.alongside AND (a.on_axis_m >= {$share} * a.total OR r.run_m >= {$run})
            GROUP BY r.id, a.total, a.on_axis_m
            HAVING SUM(r.len) >= 100
            SQL);
        DB::statement('ALTER TABLE dp_gw ADD COLUMN since_year int');
        $green = self::GREEN_NAME;
        DB::statement(<<<SQL
            UPDATE dp_gw SET since_year = COALESCE(
                CASE WHEN w.start_year >= 1970 THEN w.start_year END,
                (SELECT MIN((substring(r->>'start_date' FROM '(19[7-9][0-9]|20[0-9][0-9])'))::int)
                 FROM jsonb_array_elements(COALESCE(w.routes, '[]'::jsonb)) r WHERE r->>'name' ~* '{$green}' AND r->>'name' !~* 'projet|futur'))
            FROM dp_green w WHERE w.id = dp_gw.id
            SQL);
        DB::statement('CREATE INDEX ON dp_gw USING GIST (g)');

        // Voies vertes À CÔTÉ d'une voie laissée en place : diagnostic + annotation des rails.
        DB::statement('DROP TABLE IF EXISTS dp_alongside');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_alongside AS
            SELECT a.id AS way_id, w.name, a.total, a.alongside_idle_m,
                   (SELECT array_agg(DISTINCT r.src||':'||r.ref) FROM dp_rails r
                    WHERE NOT r.contradicted AND (r.detail = 'Non exploité' OR r.detail IN ('disused', 'preserved', 'disused:railway'))
                      AND ST_DWithin(r.g, w.g, {$near})) AS rails
            FROM dp_gwa a JOIN dp_green w USING (id)
            WHERE a.alongside_idle_m >= 0.5 * a.total AND a.alongside_idle_m >= 200
            SQL);

        $r = DB::selectOne('SELECT COUNT(*) n, ROUND((SUM(len) / 1000.0)::numeric, 1) km, COUNT(since_year) dated FROM dp_gw');
        $s = DB::selectOne('SELECT COUNT(*) n, ROUND((SUM(alongside_idle_m) / 1000.0)::numeric, 1) km FROM dp_alongside');

        return "{$r->n} voies vertes sur l'axe ({$r->km} km, {$r->dated} datées) ; {$s->n} à côté d'une voie laissée en place ({$s->km} km)";
    }

    // ------------------------------------------------------------------
    // 4. lignes-oubliees « État actuel »
    // ------------------------------------------------------------------

    private function emptyLo(): string
    {
        DB::statement('DROP TABLE IF EXISTS dp_lo');
        DB::statement('CREATE TEMP TABLE dp_lo (ref text, page text, url text, line_id bigint, section text, value text, state text,
            since_year int, quote text, sub_from text, sub_to text, resolution text, note text, g geometry)');

        return 'ignoré';
    }

    private function lignesOubliees(SectionResolver $resolver): string
    {
        $this->emptyLo();
        $items = $this->loItems();
        if (! $items) {
            return 'aucune fiche en cache';
        }

        // Résolution des portions par le résolveur existant, dans une transaction ANNULÉE :
        // événements temporaires → section_geom → copie → rollback.
        $resolved = [];
        DB::beginTransaction();
        try {
            $ids = [];
            foreach ($items as $k => $it) {
                $ids[$k] = DB::table('line_events')->insertGetId([
                    'line_id' => $it['line_id'], 'type' => 'other', 'event_date' => '2000-01-01', 'date_precision' => 'year',
                    'description' => 'tmp present-state', 'source' => 'present-tmp', 'confidence' => 'low',
                    'section_from' => $it['from'], 'section_to' => $it['to'],
                    'section_pk_start_m' => $it['pk_start'], 'section_pk_end_m' => $it['pk_end'],
                    'section_resolution' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach (array_unique(array_column($items, 'line_id')) as $lineId) {
                try {
                    $resolver->resolveLine((int) $lineId, 'present-tmp', 'pending');
                } catch (\Throwable $e) {
                    $this->warn("  ligne {$lineId} : ".mb_substr($e->getMessage(), 0, 200));
                }
            }
            $rows = DB::table('line_events')->whereIn('id', array_values($ids))
                ->get(['id', 'section_resolution', 'section_resolution_note', DB::raw("encode(ST_AsEWKB(ST_Transform(section_geom, 2154)), 'hex') AS ewkb")])
                ->keyBy('id');
            foreach ($ids as $k => $id) {
                $resolved[$k] = $rows[$id] ?? null;
            }
        } finally {
            DB::rollBack();
        }

        $n = ['ok' => 0, 'ko' => 0];
        foreach ($items as $k => $it) {
            $r = $resolved[$k];
            $okRes = $r && $r->ewkb && in_array($r->section_resolution, ['pk', 'places', 'whole_line', 'trace'], true);
            $n[$okRes ? 'ok' : 'ko']++;
            DB::insert(
                'INSERT INTO dp_lo (ref, page, url, line_id, section, value, state, since_year, quote, sub_from, sub_to, resolution, note, g)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromEWKB(decode(?, \'hex\')))',
                [$it['ref'], $it['page'], $it['url'], $it['line_id'], $it['section'], $it['value'], $it['state'], $it['since_year'],
                    $it['quote'], $it['sub'] ? $it['from'] : null, $it['sub'] ? $it['to'] : null,
                    $r->section_resolution ?? 'unresolved', mb_substr((string) ($r->section_resolution_note ?? ''), 0, 500),
                    $okRes ? $r->ewkb : null],
            );
        }
        if ($this->zoned) {
            DB::statement('DELETE FROM dp_lo WHERE g IS NOT NULL AND NOT ST_Intersects(g, (SELECT g FROM dp_zone))');
        }
        DB::statement('CREATE INDEX ON dp_lo USING GIST (g)');

        return count($items)." portions ({$n['ok']} localisées, {$n['ko']} non localisées)";
    }

    /**
     * Rubrique « État actuel de la ligne » des fiches en cache, + sous-sections
     * « voie verte entre X et Y » (valeur entre parenthèses ou note de bas de fiche).
     *
     * @return list<array<string, mixed>>
     */
    private function loItems(): array
    {
        $dir = storage_path('app/sources/lignes-oubliees/fiches');
        $files = glob($dir.'/*.html') ?: [];
        if (! $files) {
            return [];
        }
        // Lignes : celles que vff:import-lignes-oubliees a rattachées à chaque fiche.
        $lineByUrl = DB::table('line_events')->where('source', ImportLignesOubliees::SOURCE)
            ->where('source_url', 'like', '%index.php?page=%')
            ->select('source_url', DB::raw('MIN(line_id) AS line_id'))->groupBy('source_url')->pluck('line_id', 'source_url')->all();

        $inZone = $this->zoned
            ? array_flip(DB::table('line_reference_geometries')->whereRaw('ST_Intersects(geom, (SELECT g4326 FROM dp_zone))')->pluck('line_id')->all())
            : null;

        $lo = app(ImportLignesOubliees::class);
        $call = fn (string $m, ...$a) => (new \ReflectionMethod($lo, $m))->invoke($lo, ...$a);

        $items = [];
        foreach ($files as $file) {
            $page = basename($file, '.html');
            $url = 'https://www.lignes-oubliees.com/index.php?page='.$page;
            $lineId = $lineByUrl[$url] ?? null;
            if (! $lineId || ($inZone !== null && ! isset($inZone[$lineId]))) {
                continue;
            }
            $html = file_get_contents($file);
            $dom = new \DOMDocument;
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            $xp = new \DOMXPath($dom);
            $stations = $call('parseStations', $xp);
            $raw = [];
            foreach ($call('dlPairs', $xp, 'historique') as [$label, $dd]) {
                $spanNode = $xp->query('./span', $dd)->item(0);
                $span = $spanNode ? $call('text', $spanNode->textContent) : '';
                $full = $call('text', $dd->textContent);
                $rest = $span !== '' && str_starts_with($full, $span) ? trim(substr($full, strlen($span))) : $full;
                $raw[] = ['label' => $call('text', $label), 'span' => $span, 'rest' => ltrim($rest, " :\t")];
            }
            // PK appris sur la fiche (même logique que l'import des événements).
            $learned = [];
            $ranges = [];
            foreach ($raw as $r) {
                $sec = $call('parseSection', $r['span']);
                foreach ($sec['bounds'] as $b) {
                    if ($b['pk'] !== null) {
                        $k = $call('norm', $b['name']);
                        $learned[$k] = isset($learned[$k]) && $learned[$k] !== $b['pk'] ? false : $b['pk'];
                    }
                }
                if ($sec['range'] !== null && $sec['from'] !== null && $sec['from'] !== $sec['to']) {
                    $k = $call('norm', $sec['from']).'|'.$call('norm', $sec['to']);
                    $ranges[$k] = isset($ranges[$k]) && $ranges[$k] !== $sec['range'] ? false : $sec['range'];
                }
            }
            $learned = array_filter($learned, fn ($v) => $v !== false);
            $learned['@ranges'] = array_filter($ranges, fn ($v) => $v !== false);

            // Notes de bas de rubrique historique (<ol> qui suit le <dl>).
            $notes = [];
            foreach ($xp->query('//dl[contains(@class, "historique")]/following-sibling::*[1][self::ol]/li') as $li) {
                $notes[] = $call('text', $li->textContent);
            }

            $n = 0;
            foreach ($raw as $r) {
                if (! str_starts_with($call('norm', $r['label']), 'etat actuel')) {
                    continue;
                }
                $n++;
                $value = $r['rest'];
                $state = $this->loState($call('norm', $value));
                if ($state === null) {
                    continue;
                }
                $sec = $call('parseSection', $r['span']);
                [$pkA, $pkB] = $call('sectionPk', $sec, $stations, $learned);
                $refNotes = [];
                if (preg_match_all('/\((\d)\)/', $value, $mm)) {
                    foreach ($mm[1] as $i) {
                        if (isset($notes[(int) $i - 1])) {
                            $refNotes[] = $notes[(int) $i - 1];
                        }
                    }
                }
                $section = rtrim($r['span'], ' :');
                $quote = 'État actuel de la ligne : '.($section !== '' ? $section.' : ' : '').$value;
                $items[] = [
                    'ref' => "{$page}:{$n}", 'page' => $page, 'url' => $url, 'line_id' => $lineId, 'section' => $section,
                    'value' => $value, 'state' => $state, 'since_year' => null, 'sub' => false,
                    'quote' => mb_substr($quote.($refNotes ? ' / '.implode(' / ', $refNotes) : ''), 0, 1000),
                    'from' => $sec['whole'] ? null : $sec['from'], 'to' => $sec['whole'] ? null : $sec['to'],
                    'pk_start' => $pkA, 'pk_end' => $pkB,
                ];
                if (! in_array($state, ['dismantled', 'greenway'], true)) {
                    continue;
                }
                // Sous-sections « voie verte entre X et Y » (parenthèse de la valeur, puis notes).
                $k = 0;
                foreach (array_merge([$value], $refNotes) as $text) {
                    foreach ($this->greenSubsections($text) as [$from, $to, $year, $sentence]) {
                        $k++;
                        $items[] = [
                            'ref' => "{$page}:{$n}:vv{$k}", 'page' => $page, 'url' => $url, 'line_id' => $lineId, 'section' => "{$from} - {$to}",
                            'value' => $value, 'state' => 'greenway', 'since_year' => $year, 'sub' => true,
                            'quote' => mb_substr($quote.' / '.$sentence, 0, 1000),
                            'from' => $from, 'to' => $to, 'pk_start' => null, 'pk_end' => null,
                        ];
                    }
                }
            }
        }

        return $items;
    }

    /** Valeur normalisée (minuscules sans accents) → état. */
    private function loState(string $v): ?string
    {
        return match (true) {
            str_starts_with($v, 'en travaux') => null,
            (bool) preg_match('/^(piste cyclable|voie verte)/', $v),
            (bool) preg_match('/^deposee,? \(?(majoritairement )?(reconvertie en )?(voie verte|piste cyclable)(\)| \(sauf|$)/', $v) => 'greenway',
            str_starts_with($v, 'deposee (tunnel routier)') => 'road',
            str_starts_with($v, 'deposee') => 'dismantled',
            (bool) preg_match('/^(inexploitee|velo-?rail|service|services|trafic|train tour|exploitee|voie de service)/', $v) => 'track_in_place',
            default => null,
        };
    }

    /**
     * « Une voie verte a été ouverte en 2006 entre Audincourt et Beaucourt. »,
     * « (voies vertes sur Chateaubriant - Rougé et Messac - Ploërmel) ».
     * Les projets / travaux en cours sont ignorés.
     *
     * @return list<array{0: string, 1: string, 2: ?int, 3: string}>
     */
    private function greenSubsections(string $text): array
    {
        $out = [];
        foreach (preg_split('/(?<=\.)\s+(?=[A-ZÉÈ])/u', $text) as $sentence) {
            if (! preg_match('/voies? vertes?|piste cyclable|chemin vert|coul[ée]e verte/iu', $sentence)
                || preg_match('/projet|en cours|travaux|devrait|pr[ée]vu|envisag/iu', $sentence)) {
                continue;
            }
            $year = preg_match('/\b(?:en|d[èe]s|depuis)\s+((?:19|20)\d\d)\b/u', $sentence, $y) ? (int) $y[1] : null;
            $year = $year !== null && $year <= (int) date('Y') ? $year : null;
            $clean = fn (string $s) => trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $s), ' .,;');
            if (preg_match_all("/entre\s+(.+?)\s+(?:et|jusqu'(?:à|au)|jusqu’(?:à|au))\s+(.+?)(?=\s+d'une part|\s+d'autre part|\s+et entre|\s+en\s+(?:19|20)\d\d|\s+d[èe]s\s|\s+depuis\s|[.,;)]|$)/u", $sentence, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    $out[] = [$clean($m[1]), $clean($m[2]), $year, $sentence];
                }
            } elseif (preg_match('/\bsur\s+(.+?)\)?$/u', $sentence, $m)) {
                foreach (preg_split('/\s+et\s+/u', $m[1]) as $pair) {
                    $p = preg_split('/\s+[-–]\s+/u', $pair);
                    if (count($p) === 2) {
                        $out[] = [$clean($p[0]), $clean($p[1]), $year, $sentence];
                    }
                }
            }
        }

        // « le tunnel du Tracol », « Médréac » : on garde les noms plausibles de lieux.
        return array_values(array_filter($out, fn ($o) => $o[0] !== '' && $o[1] !== '' && mb_strlen($o[0]) < 60 && mb_strlen($o[1]) < 60
            && ! preg_match('/^(le|la|les|l\') /iu', $o[1])));
    }

    // ------------------------------------------------------------------
    // 5. SNCF
    // ------------------------------------------------------------------

    private function sncf(): string
    {
        $inPlace = "'".implode("', '", array_map(fn ($s) => str_replace("'", "''", $s), self::SNCF_IN_PLACE))."'";
        $gone = "'".implode("', '", array_map(fn ($s) => str_replace("'", "''", $s), self::SNCF_DISMANTLED))."'";
        DB::statement('DROP TABLE IF EXISTS dp_sncf');
        DB::statement(<<<SQL
            CREATE TEMP TABLE dp_sncf AS
            SELECT source_ref AS ref, properties->>'statut' AS statut, properties->>'code_ligne' AS code,
                   (properties->>'pk_debut_m')::int AS pkd, (properties->>'pk_fin_m')::int AS pkf, seen_at::date AS observed,
                   CASE WHEN properties->>'statut' IN ({$gone}) THEN 'dismantled' ELSE 'track_in_place' END AS state,
                   ST_Transform(ST_LineMerge(geom), 2154) AS g
            FROM source_traces
            WHERE source = 'sncf' AND properties->>'statut' IN ({$inPlace}, {$gone}) AND {$this->inZone('geom', 4326)}
            SQL);
        DB::statement('CREATE INDEX ON dp_sncf USING GIST (g)');

        return DB::selectOne('SELECT COUNT(*) n FROM dp_sncf')->n.' tronçons';
    }

    // ------------------------------------------------------------------
    // Arbitrage → dp_out
    // ------------------------------------------------------------------

    private function assemble(): string
    {
        $c = self::COVER_M;
        $near = self::ALONGSIDE_MAX_M;
        DB::statement('DROP TABLE IF EXISTS dp_out');
        DB::statement('CREATE TEMP TABLE dp_out (source text, source_ref text, state text, observed_at date, since_year int, properties jsonb, g geometry)');

        // Preuves de disparition « fortes » (OSM) : servent à amputer les preuves déclaratives.
        DB::statement('DROP TABLE IF EXISTS dp_gone');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE dp_gone AS
            SELECT 'osm'::text AS src, state, ST_Subdivide(g, 32) AS g FROM dp_osm WHERE cls = 'gone'
            UNION ALL SELECT 'osm', 'greenway', ST_Subdivide(g, 32) FROM dp_gw
            UNION ALL SELECT 'lignes-oubliees', state, ST_Subdivide(g, 32) FROM dp_lo WHERE state <> 'track_in_place' AND g IS NOT NULL
            UNION ALL SELECT 'sncf', state, ST_Subdivide(g, 32) FROM dp_sncf WHERE state = 'dismantled'
            SQL);
        DB::statement('CREATE INDEX ON dp_gone USING GIST (g)');

        // Rails : voies non exploitées partout ; voies en service près d'une emprise fermée /
        // disparue, ou voies principales en service rattachées à AUCUNE ligne VFF (train
        // touristique, voie forestière ou industrielle encore exploitée…) : une ligne dont le
        // tracé de référence vient d'archeoferroviaire (réputée disparue sans observation) mais
        // recalé sur ces rails existe encore (ex. Abreschviller - Grand-Soldat, absente de la
        // carte de 2030). Les voies en service rattachées à une ligne restent inutiles au moteur.
        DB::statement(<<<SQL
            INSERT INTO dp_out
            SELECT r.src, r.ref, 'track_in_place', r.observed, NULL,
                   jsonb_strip_nulls(jsonb_build_object('detail', r.detail, 'kind', r.kind,
                        'voie_verte_a_cote', (SELECT jsonb_agg(DISTINCT 'way/'||s.way_id) FROM dp_alongside s
                                              WHERE (r.src||':'||r.ref) = ANY(s.rails)))),
                   r.g
            FROM dp_rails r
            WHERE NOT r.contradicted
              AND (r.detail IN ('Non exploité', 'disused', 'preserved', 'disused:railway')
                   OR EXISTS (SELECT 1 FROM dp_gone g WHERE ST_DWithin(g.g, r.g, {$near}))
                   OR EXISTS (SELECT 1 FROM dp_sncf s WHERE ST_DWithin(s.g, r.g, {$near}))
                   OR EXISTS (SELECT 1 FROM dp_lo l WHERE ST_DWithin(l.g, r.g, {$near}))
                   OR EXISTS (
                       SELECT 1 FROM source_traces t
                       WHERE t.source = r.src AND t.source_ref = CASE WHEN r.src = 'osm' THEN substr(r.ref, 5) ELSE r.ref END
                         AND t.line_id IS NULL
                         AND CASE WHEN t.source = 'bdtopo' THEN t.layer IN ('Voie ferrée principale', 'LGV')
                                  ELSE t.layer IN ('rail', 'narrow_gauge', 'light_rail', 'preserved') AND NOT jsonb_exists(t.properties, 'service') END))
            SQL);

        // OSM sans rails (cycle de vie) ; un simple « déposée » s'efface devant une voie verte connue.
        DB::statement(<<<'SQL'
            INSERT INTO dp_out
            SELECT 'osm', 'way/'||o.id, o.state, o.observed, o.since_year,
                   jsonb_strip_nulls(jsonb_build_object('railway', COALESCE(o.tags->>'railway', 'lifecycle'), 'highway', o.tags->>'highway',
                        'name', o.tags->>'name', 'reuse', o.reuse)),
                   CASE WHEN o.state = 'dismantled'
                        THEN COALESCE(ST_Difference(o.g, (SELECT ST_Union(ST_Buffer(w.g, 10)) FROM dp_gw w WHERE ST_DWithin(w.g, o.g, 10))), o.g)
                        ELSE o.g END
            FROM dp_osm o WHERE o.cls = 'gone'
            SQL);

        DB::statement(<<<'SQL'
            INSERT INTO dp_out
            SELECT 'osm', 'way/'||w.id, 'greenway', g.observed, w.since_year,
                   jsonb_strip_nulls(jsonb_build_object('highway', g.tags->>'highway', 'name', g.name, 'routes', g.routes,
                        'part_on_axis', ROUND((w.on_axis_m / NULLIF(w.total, 0))::numeric, 2))),
                   w.g
            FROM dp_gw w JOIN dp_green g USING (id)
            SQL);

        // lignes-oubliees : « voie en place » amputée des preuves contraires ; « déposée » amputée des reconversions connues.
        DB::statement(<<<SQL
            INSERT INTO dp_out
            SELECT 'lignes-oubliees', l.ref, l.state, NULL, l.since_year,
                   jsonb_strip_nulls(jsonb_build_object('line_id', l.line_id, 'section', l.section, 'value', l.value, 'quote', l.quote,
                        'url', l.url, 'resolution', l.resolution, 'resolution_note', NULLIF(l.note, ''))),
                   CASE
                     WHEN l.state = 'track_in_place' THEN COALESCE(ST_Difference(l.g,
                         (SELECT ST_Union(ST_Buffer(g.g, {$c})) FROM dp_gone g WHERE ST_DWithin(g.g, l.g, {$c}))), l.g)
                     WHEN l.state = 'dismantled' THEN COALESCE(ST_Difference(l.g,
                         (SELECT ST_Union(ST_Buffer(g.g, {$c})) FROM dp_gone g WHERE g.state IN ('greenway', 'road') AND ST_DWithin(g.g, l.g, {$c}))), l.g)
                     ELSE l.g END
            FROM dp_lo l WHERE l.g IS NOT NULL
            SQL);

        // SNCF : idem.
        DB::statement(<<<SQL
            INSERT INTO dp_out
            SELECT 'sncf', s.ref, s.state, s.observed, NULL,
                   jsonb_build_object('statut', s.statut, 'code_ligne', s.code, 'pk_debut_m', s.pkd, 'pk_fin_m', s.pkf),
                   CASE
                     WHEN s.state = 'track_in_place' THEN COALESCE(ST_Difference(s.g,
                         (SELECT ST_Union(ST_Buffer(g.g, {$c})) FROM dp_gone g WHERE ST_DWithin(g.g, s.g, {$c}))), s.g)
                     ELSE COALESCE(ST_Difference(s.g,
                         (SELECT ST_Union(ST_Buffer(g.g, {$c})) FROM dp_gone g WHERE g.state IN ('greenway', 'road') AND g.src <> 'sncf'
                          AND ST_DWithin(g.g, s.g, {$c}))), s.g) END
            FROM dp_sncf s
            SQL);

        // Géométries propres : lignes seulement, morceaux ≥ 30 m.
        DB::statement(<<<'SQL'
            UPDATE dp_out SET g = (SELECT ST_Collect(d.geom) FROM ST_Dump(ST_LineMerge(ST_CollectionExtract(g, 2))) d WHERE ST_Length(d.geom) >= 30)
            SQL);
        DB::statement('DELETE FROM dp_out WHERE g IS NULL OR ST_IsEmpty(g) OR ST_Length(g) < 30');

        // since_year = date de DÉPOSE pour le moteur. Une voie verte ouverte bien après la
        // fermeture connue de la portion (> MAX_GAP_YEARS) ne date pas la dépose : la voie aurait
        // sinon l'air « en place » jusqu'à l'ouverture de la voie verte. L'année reste en propriété.
        DB::statement('DROP TABLE IF EXISTS dp_closed');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE dp_closed AS
            SELECT o.source, o.source_ref, MAX(EXTRACT(YEAR FROM e.event_date))::int AS closed_year
            FROM dp_out o JOIN line_events e
              ON e.type IN ('closure', 'closure_freight', 'closure_passenger', 'declassification')
             AND e.section_geom IS NOT NULL AND e.section_geom && ST_Transform(ST_Expand(o.g, 50), 4326)
             AND ST_DWithin(ST_Transform(e.section_geom, 2154), o.g, 30)
             AND EXTRACT(YEAR FROM e.event_date) <= o.since_year + 1
            WHERE o.since_year IS NOT NULL
            GROUP BY 1, 2
            SQL);
        $gap = self::MAX_GAP_YEARS;
        DB::statement(<<<SQL
            UPDATE dp_out o SET properties = COALESCE(o.properties, '{}'::jsonb)
                    || jsonb_build_object('voie_verte_depuis', o.since_year, 'fermeture_connue', c.closed_year),
                since_year = NULL
            FROM dp_closed c
            WHERE c.source = o.source AND c.source_ref = o.source_ref AND o.since_year - c.closed_year > {$gap}
            SQL);
        DB::statement("UPDATE dp_out SET properties = COALESCE(properties, '{}'::jsonb) || jsonb_build_object('detector', '".self::DETECTOR."')");

        return DB::selectOne('SELECT COUNT(*) n FROM dp_out')->n.' observations';
    }

    private function summary(): void
    {
        $rows = DB::select('SELECT source, state, COUNT(*) n, ROUND((SUM(ST_Length(g)) / 1000.0)::numeric, 1) km FROM dp_out GROUP BY 1, 2 ORDER BY 1, 2');
        $this->table(['Source', 'État', 'Observations', 'km'], array_map(fn ($r) => [$r->source, $r->state, $r->n, $r->km], $rows));
    }

    // ------------------------------------------------------------------
    // Écriture
    // ------------------------------------------------------------------

    private function write(): string
    {
        return DB::transaction(function () {
            $zone = $this->zoned ? 'AND ST_Intersects(geom, (SELECT g4326 FROM dp_zone))' : '';
            $del = DB::delete("DELETE FROM present_states WHERE properties->>'detector' = ? {$zone}", [self::DETECTOR]);
            $ins = DB::affectingStatement(<<<'SQL'
                INSERT INTO present_states (source, source_ref, state, observed_at, since_year, properties, geom, created_at, updated_at)
                SELECT source, LEFT(source_ref, 128), state, observed_at, since_year, properties, ST_Multi(ST_Transform(g, 4326)), NOW(), NOW()
                FROM dp_out
                ON CONFLICT (source, source_ref) DO UPDATE SET state = EXCLUDED.state, observed_at = EXCLUDED.observed_at,
                    since_year = EXCLUDED.since_year, properties = EXCLUDED.properties, geom = EXCLUDED.geom, updated_at = NOW()
                SQL);

            return "{$del} anciennes observations supprimées, {$ins} écrites";
        });
    }

    // ------------------------------------------------------------------
    // Événements datés : reconversion en voie verte
    // ------------------------------------------------------------------

    private function events(): string
    {
        // Candidats : (ligne, année, portion du tracé de référence longée par la voie verte).
        DB::statement('DROP TABLE IF EXISTS dp_ev');
        DB::statement(<<<'SQL'
            CREATE TEMP TABLE dp_ev (line_id bigint, year int, source text, source_url text, quote text, label text,
                                     section_from text, section_to text, resolution text, note text, key text, g geometry)
            SQL);

        // OSM : voies vertes datées, regroupées par ligne + année.
        DB::statement(<<<'SQL'
            WITH gw AS (
                SELECT o.source_ref, o.since_year AS year, o.g, o.properties->>'name' AS label
                FROM dp_out o WHERE o.source = 'osm' AND o.state = 'greenway' AND o.since_year IS NOT NULL
            ),
            hit AS (
                SELECT p.line_id, gw.year, gw.label, gw.source_ref,
                       ST_CollectionExtract(ST_Intersection(ST_Transform(p.geom, 2154), ST_Buffer(gw.g, 15)), 2) AS sg
                FROM gw JOIN line_reference_parts p ON p.geom && ST_Transform(ST_Expand(gw.g, 20), 4326)
                     AND ST_DWithin(ST_Transform(p.geom, 2154), gw.g, 15)
            )
            INSERT INTO dp_ev
            SELECT line_id, year, 'osm', 'https://www.openstreetmap.org/'||MIN(source_ref),
                   LEFT('OpenStreetMap : '||string_agg(DISTINCT source_ref, ', ')||' (start_date '||year||')', 1000),
                   COALESCE(string_agg(DISTINCT label, ', '), 'voie verte OSM'),
                   NULL, NULL, 'trace', LEFT('Portion longée par la voie verte OSM '||string_agg(DISTINCT source_ref, ', '), 500),
                   'osm|'||line_id||'|'||year, ST_LineMerge(ST_Union(sg))
            FROM hit WHERE NOT ST_IsEmpty(sg)
            GROUP BY line_id, year
            HAVING SUM(ST_Length(sg)) >= 200
            SQL);

        // lignes-oubliees : sous-sections « voie verte … en AAAA ».
        DB::statement(<<<'SQL'
            INSERT INTO dp_ev
            SELECT line_id, since_year, 'lignes-oubliees', url||'#etat-actuel', quote, section, sub_from, sub_to, resolution, note,
                   'lo|'||ref||'|'||since_year, g
            FROM dp_lo WHERE since_year IS NOT NULL AND g IS NOT NULL AND state = 'greenway'
            SQL);

        // Une reconversion n'est datée que si la portion a une fermeture connue au plus
        // MAX_GAP_YEARS ans avant (sinon la dépose est bien plus ancienne que la voie verte).
        $gap = self::MAX_GAP_YEARS;
        $skipped = DB::delete(<<<SQL
            DELETE FROM dp_ev v WHERE NOT EXISTS (
                SELECT 1 FROM line_events e
                WHERE e.line_id = v.line_id AND e.type IN ('closure', 'closure_freight', 'closure_passenger', 'declassification')
                  AND EXTRACT(YEAR FROM e.event_date) BETWEEN v.year - {$gap} AND v.year + 1
                  AND e.section_geom IS NOT NULL AND ST_DWithin(ST_Transform(e.section_geom, 2154), v.g, 30))
            SQL);
        // Dépose / reconversion déjà connue (autre source) sur la portion, ou non localisée : on
        // s'abstient (la date de dépose prime). Idem si la portion est encore ouverte ou fermée APRÈS
        // l'année annoncée (date incohérente : ouverture de la ligne, projet…).
        $skipped += DB::delete(<<<'SQL'
            DELETE FROM dp_ev v WHERE EXISTS (
                SELECT 1 FROM line_events e
                WHERE e.line_id = v.line_id
                  AND (e.fingerprint IS NULL OR e.fingerprint NOT LIKE 'present:%')
                  AND (e.type IN ('dismantling', 'conversion')
                       OR ((e.type LIKE 'closure%' OR e.type LIKE 'opening%' OR e.type LIKE 'reopening%')
                           AND e.event_date > make_date(v.year + 1, 12, 31)))
                  AND (e.section_geom IS NULL OR ST_DWithin(ST_Transform(e.section_geom, 2154), v.g, 30)))
            SQL);

        return DB::transaction(function () use ($skipped) {
            $rows = DB::select("SELECT *, encode(ST_AsEWKB(ST_Multi(ST_Transform(g, 4326))), 'hex') AS ewkb, ROUND(ST_Length(g))::int AS len FROM dp_ev
                                WHERE g IS NOT NULL AND NOT ST_IsEmpty(g) AND ST_Length(g) >= 200");
            $fps = [];
            foreach ($rows as $r) {
                $fp = 'present:'.substr(hash('sha256', self::DETECTOR.'|'.$r->key), 0, 56);
                $fps[] = $fp;
                $what = $r->source === 'osm'
                    ? "Reconversion en voie verte ({$r->label}), ouverte en {$r->year} d'après OpenStreetMap"
                    : "Reconversion en voie verte ({$r->label}) en {$r->year} d'après lignes-oubliees";
                $data = [
                    'line_id' => $r->line_id, 'type' => 'conversion', 'reuse' => 'greenway',
                    'event_date' => sprintf('%04d-01-01', $r->year), 'date_precision' => 'year',
                    'description' => mb_substr($what, 0, 1000), 'source' => $r->source, 'source_url' => mb_substr($r->source_url, 0, 500),
                    'source_quote' => $r->quote, 'confidence' => 'medium',
                    'section_from' => $r->section_from, 'section_to' => $r->section_to,
                    'section_resolution' => $r->resolution, 'section_resolution_note' => mb_substr((string) $r->note, 0, 500),
                    'section_length_m' => $r->len, 'updated_at' => now(),
                ];
                $exists = DB::table('line_events')->where('fingerprint', $fp)->exists();
                if ($exists) {
                    DB::table('line_events')->where('fingerprint', $fp)->update($data);
                } else {
                    DB::table('line_events')->insert($data + ['fingerprint' => $fp, 'created_at' => now()]);
                }
                DB::update("UPDATE line_events SET section_geom = ST_GeomFromEWKB(decode(?, 'hex')) WHERE fingerprint = ?", [$r->ewkb, $fp]);
            }
            // Événements de ce détecteur qui ne sont plus produits (dans la zone traitée).
            $q = DB::table('line_events')->where('fingerprint', 'like', 'present:%')->whereNotIn('fingerprint', $fps ?: ['-']);
            if ($this->zoned) {
                $q->whereRaw('ST_Intersects(section_geom, (SELECT g4326 FROM dp_zone))');
            }
            $stale = $q->delete();

            return count($rows)." événements conversion écrits, {$skipped} écartés (fermeture trop ancienne ou inconnue, dépose déjà connue), {$stale} obsolètes supprimés";
        });
    }
}
