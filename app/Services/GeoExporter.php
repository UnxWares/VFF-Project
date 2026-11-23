<?php

namespace App\Services;

use App\Enums\TileFields;
use App\Models\Era;
use App\Services\TraceExport\LineTraceExport;
use App\Support\Lgv;
use App\Support\MapTiles;
use App\Support\SpeedClass;
use App\Support\TrackPriority;
use Illuminate\Support\Facades\DB;

/**
 * Service d'export des tracés VFF vers GeoJSON et MVT (Mapbox Vector Tiles).
 * Préparation de la Phase 2 : la carte MapLibre consommera ces sorties.
 *
 * Tous les calculs sont délégués à PostGIS (ST_AsGeoJSON, ST_AsMVT) qui est
 * 10-100× plus rapide qu'une sérialisation PHP/JSON côté Laravel.
 */
class GeoExporter
{
    public function __construct(private readonly LineTraceExport $traceRights) {}

    /**
     * Tronçons d'une ère en features GeoJSON, une à une via un curseur DB
     * (mémoire constante), pour éviter l'OOM 128M sur les ~77k segments.
     *
     * Règle des droits (LineTraceExport) : un tronçon dont la source n'autorise pas la
     * redistribution garde son entité et ses propriétés, avec `geometry` null,
     * `geometry_withheld` vrai et `geometry_withheld_reason` (`licence_not_open`,
     * `unknown_source`) ; le résumé par source est donné par LineTraceExport::withheldForEra.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamLinesGeoJson(int $eraYear): \Generator
    {
        $era = Era::where('year', $eraYear)->firstOrFail();

        $lgv = Lgv::sql('l', 'ls');
        $open = $this->traceRights->sqlRedistributable('ls.source');
        $cursor = DB::cursor(<<<SQL
            SELECT
                ls.id, ls.line_id, l.slug AS line_slug, l.name AS line_name, l.code AS line_code,
                {$lgv} AS is_lgv,
                l.network,
                ls.status, ls.traffic_type, ls.max_speed_kmh, ls.electrified,
                ls.voltage_v, ls.gauge_mm, ls.is_heritage, ls.heritage_kind, ls.operator,
                ls.source, ls.source_trace_id,
                CASE WHEN {$open} THEN ST_AsGeoJSON(ls.geom, 6) END AS geometry
            FROM line_segments ls
            INNER JOIN lines l ON l.id = ls.line_id
            WHERE ls.era_id = :era_id
        SQL, ['era_id' => $era->id]);

        foreach ($cursor as $row) {
            $withheld = $this->traceRights->withheldReason($row->source);
            yield [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => $withheld === null && $row->geometry !== null ? json_decode($row->geometry, true) : null,
                'properties' => [
                    'line_id' => $row->line_id,
                    'line_slug' => $row->line_slug,
                    'line_name' => $row->line_name,
                    'line_code' => $row->line_code,
                    'network' => $row->network,
                    'status' => $row->status,
                    'traffic_type' => $row->traffic_type,
                    'is_lgv' => (bool) $row->is_lgv,
                    'is_heritage' => (bool) $row->is_heritage,
                    'heritage_kind' => $row->heritage_kind,
                    'max_speed_kmh' => $row->max_speed_kmh,
                    'electrified' => (bool) $row->electrified,
                    'voltage_v' => $row->voltage_v,
                    'gauge_mm' => $row->gauge_mm,
                    'operator' => $row->operator,
                    'source' => $row->source,
                    'source_trace_id' => $row->source_trace_id,
                    'geometry_withheld' => $withheld !== null,
                    'geometry_withheld_reason' => $withheld,
                ],
            ];
        }
    }

    /**
     * Génère une tuile vectorielle MVT (Mapbox Vector Tiles) à la volée via
     * `ST_AsMVT` PostGIS pour une ère donnée et les coordonnées Z/X/Y de la tuile.
     *
     * Format binaire compact (10-100× plus léger que GeoJSON), idéal pour
     * MapLibre GL JS qui le lit nativement.
     *
     * `$fields` : toutes les propriétés documentées (défaut), ou seulement celles que lit la carte du
     * site (TileFields::Map, voir mapColumnsSql).
     */
    public function linesAsMvt(int $eraYear, int $z, int $x, int $y, TileFields $fields = TileFields::All): string
    {
        $era = Era::where('year', $eraYear)->firstOrFail();

        // PERF : on intersecte d'abord en 4326 (l'index GIST natif des segments)
        // puis on transforme en 3857 uniquement pour les rares géométries qui
        // matchent. Sans ça, le ST_Transform à l'intérieur du ST_Intersects
        // défausse l'index GIST et scan toute la table (lent avec 77k+ segments).
        //
        // Buffer 256 px pour éviter l'effet escalier aux frontières de tuiles.
        $lgv = Lgv::sql('l', 'ls');
        // Voies partagées entre lignes (troncs communs) : ordre de dessin par
        // priorité (draw_rank → line-sort-key) et portions recouvertes par une
        // ligne plus active découpées (vff:build-shared-track) : la partie
        // recouverte sort avec covered = true et n'est pas dessinée par la
        // carte. Recouvrement caduc (signature ≠ état courant) : ignoré.
        $drawRank = TrackPriority::drawRankSql($lgv, 'l', 'ls');
        $parts = TrackPriority::partsJoinSql('ls');
        // Tracés simplifiés à la tolérance d'affichage du zoom à partir du zoom 10 (MapTiles), puis découpés dans la tuile.
        $geom = MapTiles::lineGeomSql('part.g', $z);
        $extent = MapTiles::extent($z);
        if ($fields === TileFields::Map) {
            $columns = $this->mapColumnsSql($lgv, $drawRank);
            // Tuile allégée : les tracés aux propriétés identiques (en général les portions voisines d'une même
            // ligne dans le même état) forment une seule entité MultiLineString. Mêmes traits à l'écran, mais une
            // seule fois les étiquettes, et moins d'entités à préparer pour MapLibre.
            $group = implode(', ', TileFields::MAP_PROPERTIES);
            $select = <<<SQL
                SELECT ST_AsMVT(merged.*, 'vff-lines', {$extent}) AS tile FROM (
                    SELECT {$group}, ST_Collect(part_geom.geom) AS geom
                    FROM mvt_layer CROSS JOIN LATERAL ST_Dump(mvt_layer.geom) AS part_geom
                    WHERE mvt_layer.geom IS NOT NULL
                    GROUP BY {$group}
                ) merged
            SQL;
        } else {
            $columns = $this->allColumnsSql($lgv, $drawRank);
            $select = <<<SQL
                SELECT ST_AsMVT(mvt_layer.*, 'vff-lines', {$extent}) AS tile FROM mvt_layer
                WHERE geom IS NOT NULL
            SQL;
        }

        // MATERIALIZED : sans lui, PostgreSQL recopie ST_AsMVTGeom dans le filtre `geom IS NOT NULL` et
        // découpe chaque tracé deux fois (10 % du temps de la tuile du zoom 4).
        return MapTiles::query(<<<SQL
            WITH bounds AS (
                SELECT
                    ST_TileEnvelope(:z, :x, :y) AS env_3857,
                    ST_Transform(ST_TileEnvelope(:z, :x, :y), 4326) AS env_4326
            ),
            mvt_layer AS MATERIALIZED (
                SELECT
                    {$columns}
                    {$geom} AS geom
                FROM line_segments ls
                INNER JOIN lines l ON l.id = ls.line_id
                CROSS JOIN bounds
                {$parts}
                WHERE ls.era_id = :era_id
                  AND ls.geom && bounds.env_4326
            )
            {$select}
        SQL, [
            'era_id' => $era->id,
            'z' => $z,
            'x' => $x,
            'y' => $y,
        ]);
    }

    /** Colonnes de la couche `vff-lines` : toutes les propriétés documentées (réponse par défaut). */
    private function allColumnsSql(string $lgv, string $drawRank): string
    {
        return <<<SQL
                    ls.id,
                    ls.line_id,
                    l.slug AS line_slug,
                    l.name AS line_name,
                    l.code AS line_code,
                    -- LGV : OU de tous les signaux (lines.is_lgv, catlig SNCF, nom,
                    -- vitesse ≥ 250), indépendant du false par défaut (cf. App\Support\Lgv).
                    {$lgv} AS is_lgv,
                    l.is_threatened,
                    -- Campagne soutenue par VFF : threatened (menacée) | reopening (réouverture)
                    COALESCE(l.advocacy->>'kind', CASE WHEN l.is_threatened THEN 'threatened' END) AS advocacy_kind,
                    -- Catégorie de réseau (principal | secondaire | militaire | particulier)
                    l.network,
                    -- Statut et trafic de la PORTION à cette décennie : pilotent la
                    -- couleur (trafic) et le trait (statut) côté MapLibre.
                    ls.status,
                    COALESCE(ls.traffic_type, 'unknown') AS traffic_type,
                    ls.max_speed_kmh,
                    ls.electrified,
                    ls.voltage_v,
                    ls.gauge_mm,
                    -- Électrification / écartement de la portion À CETTE ÈRE (vff:enrich-era-segments) :
                    -- provenance (unknown = électrifiée aujourd'hui, date inconnue), année, classe d'écartement.
                    ls.electrification_source,
                    ls.electrified_year,
                    ls.gauge_class,
                    ls.is_heritage,
                    -- Exploitation associative : tourist_train | velorail | museum (trait vert plein / pointillé)
                    ls.heritage_kind,
                    ls.operator,
                    -- Provenance du tracé (sncf, osm, archeoferroviaire, ohm…)
                    ls.source,
                    ls.source_trace_id,
                    ls.dismantled_at::text AS dismantled_at,
                    -- Agrégats au niveau LIGNE (cohérents quel que soit le segment cliqué)
                    COALESCE(l.max_speed_kmh, ls.max_speed_kmh) AS line_max_speed,
                    COALESCE(l.electrified, ls.electrified) AS line_electrified,
                    COALESCE(l.voltage_v, ls.voltage_v) AS line_voltage_v,
                    -- Voie partagée : ordre de dessin (plus actif au-dessus) et
                    -- partie recouverte par une ligne plus active (non dessinée).
                    {$drawRank} AS draw_rank,
                    part.covered,
        SQL;
    }

    /**
     * Colonnes de la couche `vff-lines` avec `fields=map` (TileFields::MAP_PROPERTIES) : ce que lisent les couches,
     * les filtres de la légende, l'ordre de dessin et le clic de `RailMap.svelte` (et `lib/railStyle.js`), rien de plus.
     *
     * ST_AsMVT n'écrit pas une valeur NULL : un booléen faux, un rang de dessin nul, un écartement inconnu ou une
     * tension sans électrification deviennent NULL, donc absents de l'entité. La carte les lit comme avant (ses
     * expressions comparent à `true`, `coalesce` à 0 ou à `unknown`, et la tension ne sert que si la portion est
     * électrifiée). `line_electrified` garde son `false` : le clic ne retombe sur `electrified` que si la valeur
     * de la ligne manque.
     */
    private function mapColumnsSql(string $lgv, string $drawRank): string
    {
        return <<<SQL
                    l.slug AS line_slug,
                    l.name AS line_name,
                    l.code AS line_code,
                    NULLIF({$lgv}, FALSE) AS is_lgv,
                    NULLIF(l.is_threatened, FALSE) AS is_threatened,
                    CASE WHEN l.advocacy->>'kind' = 'reopening' THEN 'reopening' END AS advocacy_kind,
                    l.network,
                    ls.status,
                    COALESCE(ls.traffic_type, 'unknown') AS traffic_type,
                    NULLIF(ls.electrified, FALSE) AS electrified,
                    CASE WHEN ls.electrified THEN ls.voltage_v END AS voltage_v,
                    CASE WHEN ls.electrification_source = 'unknown' THEN 'unknown' END AS electrification_source,
                    NULLIF(ls.gauge_class, 'unknown') AS gauge_class,
                    NULLIF(ls.is_heritage, FALSE) AS is_heritage,
                    CASE WHEN ls.is_heritage THEN ls.heritage_kind END AS heritage_kind,
                    COALESCE(l.max_speed_kmh, ls.max_speed_kmh) AS line_max_speed,
                    COALESCE(l.electrified, ls.electrified) AS line_electrified,
                    COALESCE(l.voltage_v, ls.voltage_v) AS line_voltage_v,
                    {$this->speedClassSql()} AS speed_class,
                    NULLIF({$drawRank}, 0) AS draw_rank,
                    NULLIF(part.covered, FALSE) AS covered,
        SQL;
    }

    /**
     * Classe de vitesse maximale d'une portion en service (coloration « Vitesse maximale » de la carte, voir
     * App\Support\SpeedClass) : 1 jusqu'à 100 km/h, 2 de 101 à 160, 3 de 161 à 220, 4 au-delà ; NULL (absente
     * de l'entité) pour une portion sans vitesse connue ou hors service. Un entier sur un octet, pas la vitesse
     * elle-même : les portions voisines de la même classe restent réunies dans la tuile allégée.
     */
    private function speedClassSql(): string
    {
        return SpeedClass::sql('ls.max_speed_kmh', "ls.status IN ('in_service', 'single_track', 'service_track')");
    }

    /**
     * Retourne les gares actives à une ère donnée en GeoJSON.
     *
     * @return array{type: 'FeatureCollection', features: array<int, array<string, mixed>>}
     */
    public function stationsAsGeoJson(int $eraYear): array
    {
        // Présence et statut par décennie = `station_eras`, matérialisé par
        // vff:build-station-eras depuis la chronologie de chaque gare
        // (ouverture ≤ année < fermeture) plafonnée par l'état des lignes de
        // l'ère. Statuts : active (voyageurs + fret), tourist, no_commercial
        // (fret seul), declassified (fermée mais existante), uncertain (ouverte
        // selon ses sources mais non confirmée par les voies de l'ère :
        // station_eras.uncertain, cf. StationLineCoverage ; jamais montrée
        // ouverte, statut des sources dans `sources_status`).
        $era = Era::where('year', $eraYear)->firstOrFail();

        if (! DB::table('station_eras')->where('era_id', $era->id)->exists()) {
            // Ère pas encore calculée : seule l'ère courante montre les gares
            // non fermées, d'après leur statut actuel.
            if (! ($era->is_current ?? false)) {
                return ['type' => 'FeatureCollection', 'features' => []];
            }
            $join = "JOIN (SELECT id AS station_id, status, FALSE AS uncertain, NULL::text AS note FROM stations WHERE status <> 'declassified') se ON se.station_id = s.id";
        } else {
            $join = 'JOIN station_eras se ON se.station_id = s.id AND se.era_id = '.(int) $era->id;
        }

        // Gare fermée : montrée seulement si une voie est encore en place à moins de
        // ISOLATION_RADIUS_M à cette ère (ligne exploitée, ou fermée non déposée). Ligne
        // déposée ou disparue : plus de gare sur la carte (la fiche de la ligne les garde).
        $radius = StationLineCoverage::ISOLATION_RADIUS_M;
        $eraId = (int) $era->id;
        $rows = DB::select(<<<SQL
            SELECT
                s.id, s.slug, s.name, s.type, se.status, se.uncertain, se.note,
                s.city, s.department_code, s.uic_code,
                EXTRACT(YEAR FROM s.opened_at)::int AS opened_year, s.opened_precision,
                s.provenance->>'opened_after' AS opened_after,
                EXTRACT(YEAR FROM s.closed_at)::int AS closed_year, s.closed_at, s.closed_precision,
                s.provenance->>'closed_after' AS closed_after,
                ST_AsGeoJSON(s.geom, 6)::json AS geometry
            FROM stations s
            {$join}
            WHERE se.status <> 'declassified'
               OR EXISTS (
                   SELECT 1 FROM line_segments ls
                   WHERE ls.era_id = {$eraId} AND ls.status NOT IN ('dismantled', 'razed')
                     AND ls.geom && ST_Expand(s.geom, 0.005)
                     AND ST_DWithin(ls.geom::geography, s.geom::geography, {$radius}))
        SQL);

        $service = ['active' => 'passenger', 'tourist' => 'tourist', 'no_commercial' => 'freight', 'declassified' => 'closed', 'uncertain' => 'uncertain'];
        $shown = fn ($row) => $row->uncertain && $row->status !== 'declassified' ? 'uncertain' : $row->status;
        $features = array_map(
            fn ($row) => [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => is_string($row->geometry) ? json_decode($row->geometry, true) : $row->geometry,
                'properties' => [
                    'slug' => $row->slug,
                    'name' => $row->name,
                    'type' => $row->type,
                    'status' => $shown($row),
                    // Statut selon les sources de la gare (diffère de status si incertaine).
                    'sources_status' => $row->status,
                    // passenger (voyageurs, donc aussi fret) | tourist | freight (fret seul) | closed (fermée) | uncertain
                    'service' => $service[$shown($row)] ?? $shown($row),
                    'city' => $row->city,
                    'department_code' => $row->department_code,
                    'uic' => $row->uic_code,
                    'opened_year' => $row->opened_year,
                    // Ouverture encadrée (précision range : correction sourcée, règle G3) : entre opened_after + 1
                    // et opened_year, la borne haute ; opened_after nul pour toute autre précision.
                    'opened_precision' => $row->opened_year !== null ? ($row->opened_precision ?? 'day') : null,
                    'opened_after' => $row->opened_precision === StationTimeline::RANGE && is_numeric($row->opened_after) ? (int) $row->opened_after : null,
                    // Date de fermeture retenue, la même que la fiche gare et la fiche ligne
                    // (StationTimeline::resolveClosure) ; précision range : fermée après closed_after.
                    'closed_year' => $row->closed_year,
                    'closed_at' => $row->closed_at,
                    'closed_precision' => $row->closed_precision,
                    'closed_after' => is_numeric($row->closed_after) ? (int) $row->closed_after : null,
                    'note' => $row->note,
                ],
            ],
            $rows,
        );

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
