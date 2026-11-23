<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EraTileRequest;
use App\Models\Era;
use App\Services\GeoExporter;
use App\Services\MapDataCache;
use App\Services\TraceExport\LineTraceExport;
use App\Support\MapTiles;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints publics pour la consommation cartographique :
 *  - GET /api/v1/eras/{year}/lines.geojson      → FeatureCollection des tronçons
 *  - GET /api/v1/eras/{year}/stations.geojson   → FeatureCollection des gares
 *  - GET /api/v1/eras/{year}/tiles/{z}/{x}/{y}.mvt → tuile vectorielle MVT
 *
 * Les tuiles MVT sont produites par PostGIS (`ST_AsMVT`). Tuiles et gares passent par
 * MapDataCache : cache serveur jusqu'au prochain recalcul, ETag, gzip, Cache-Control.
 */
#[Group('Cartographie par ère', "Le réseau tel qu'il était à la fin d'une décennie : tracés et gares en GeoJSON, tuiles vectorielles MVT pour MapLibre.", weight: 1)]
class GeoController extends Controller
{
    public function __construct(protected GeoExporter $exporter, private readonly MapDataCache $cache) {}

    /**
     * Tracés du réseau à une ère (GeoJSON).
     *
     * Tous les tronçons de voie connus à l'ère demandée, en `FeatureCollection`
     * GeoJSON (coordonnées WGS 84, longitude puis latitude, 6 décimales). Un
     * tronçon par entité (`Feature`) ; une ligne en compte en général plusieurs.
     *
     * L'ère est un **instantané au 31/12** de son année (`1930` = réseau au
     * 31/12/1930) ; `2030` désigne le réseau **actuel**, recalculé au jour même.
     * Les ères disponibles vont de 1830 à 2030, de dix en dix.
     *
     * Les tronçons fermés, sans trafic (`abandoned`) ou dont la voie est
     * déposée (`dismantled`) **sont inclus** : filtrez sur `status` pour n'avoir
     * que le réseau exploité. La réponse est envoyée en flux (plusieurs dizaines
     * de Mo pour les ères récentes) : préférez les tuiles MVT pour un affichage.
     *
     * Propriétés de chaque tronçon :
     * - `status` : `in_service` (exploité), `single_track` (exploité, voie unique),
     *   `service_track` (voie de service ou portuaire, hors ligne du réseau ferré national),
     *   `construction` (en travaux), `abandoned` (fermé ou neutralisé, voie encore en place),
     *   `dismantled` (voie déposée, emprise visible), `razed` (emprise disparue) ;
     * - `traffic_type` : `passenger` (voyageurs seuls), `freight` (fret seul),
     *   `mixed` (voyageurs et fret ; une ligne ouverte aux voyageurs est réputée
     *   mixte, sauf les LGV), `unknown` (non renseigné) ;
     * - `network` : `principal` (grandes compagnies puis SNCF), `secondaire`
     *   (réseaux secondaires d'intérêt local, souvent à voie étroite, tramways),
     *   `militaire`, `particulier` (réseaux particuliers, industriels) ;
     * - `is_lgv` : ligne à grande vitesse ;
     * - `is_heritage` / `heritage_kind` : portion exploitée par une association
     *   (`tourist_train` train touristique, `velorail` vélorail) ;
     * - `electrified`, `voltage_v` (tension en volts, ex. 1500 ou 25000),
     *   `max_speed_kmh` (vitesse maximale en km/h), `gauge_mm` (écartement en
     *   millimètres : 1435 voie normale, 1000 voie métrique, 600 voie étroite) ;
     * - `source` : provenance du tracé (`sncf` SNCF Réseau Open Data, ODbL ;
     *   `bdtopo` IGN BD TOPO, Licence Ouverte 2.0 ; `osm` OpenStreetMap, ODbL ;
     *   `ohm` OpenHistoricalMap, CC0 ; d'autres fournisseurs hors ODbL, à usage
     *   non commercial et non redistribués : voir ci-dessous et `/sources`) ;
     * - `geometry_withheld` : `true` quand la géométrie du tronçon n'est pas
     *   redistribuée ; `geometry_withheld_reason` en donne la raison
     *   (`licence_not_open` : source dont la licence n'autorise pas la
     *   redistribution, `unknown_source` : source absente du registre des droits),
     *   `null` sinon.
     *
     * **Droits des tracés** : seule la géométrie d'un tronçon dont la source a une
     * licence ouverte vérifiée (registre `/sources`) est redistribuée. Pour une
     * source dont la licence n'autorise pas la redistribution, le tronçon reste
     * dans la collection avec toutes ses propriétés (état, trafic, longueur par
     * la fiche…), mais `geometry` vaut `null` (entité GeoJSON sans géométrie,
     * permise par la RFC 7946). Gardez ou écartez ces entités selon votre outil.
     * Le membre `withheld`, après `features`, résume ces tronçons par source :
     * `provider`, `name`, `license`, `reason`, `sections` (nombre) et `length_m`.
     * Les tuiles vectorielles servent à l'affichage de la carte : elles ne sont
     * pas un export de données.
     *
     * @param  int  $year  Année de l'ère (décennie de 1830 à 2030 ; 2030 = aujourd'hui).
     *
     * @response array{
     *     type: 'FeatureCollection',
     *     features: array<array{
     *         type: 'Feature',
     *         id: int,
     *         geometry: array{type: 'LineString', coordinates: array<array{0: float, 1: float}>},
     *         properties: array{
     *             line_id: int,
     *             line_slug: string,
     *             line_name: string,
     *             line_code: ?string,
     *             network: ?string,
     *             status: string,
     *             traffic_type: ?string,
     *             is_lgv: bool,
     *             is_heritage: bool,
     *             heritage_kind: ?string,
     *             max_speed_kmh: ?int,
     *             electrified: bool,
     *             voltage_v: ?int,
     *             gauge_mm: ?int,
     *             operator: ?string,
     *             source: ?string,
     *             source_trace_id: ?int,
     *             geometry_withheld: bool,
     *             geometry_withheld_reason: ?string
     *         }
     *     }>,
     *     withheld: array<array{provider: ?string, name: ?string, license: ?string, reason: string, sections: int, length_m: int}>
     * }
     */
    #[PathParameter('year', "Année de l'ère : une décennie de 1830 à 2030 (2030 = réseau actuel).", example: 1930)]
    #[ApiResponse(200, 'FeatureCollection GeoJSON des tronçons de l\'ère.', examples: [[
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'id' => 8596381,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[7.542458, 48.018663], [7.540932, 48.018664]]],
            'properties' => [
                'line_id' => 1, 'line_slug' => 'rfn-123000', 'line_name' => 'Ligne de Neuf-Brisach à Bantzenheim',
                'line_code' => '123000', 'network' => 'principal', 'status' => 'in_service', 'traffic_type' => 'mixed',
                'is_lgv' => false, 'is_heritage' => false, 'heritage_kind' => null, 'max_speed_kmh' => null,
                'electrified' => false, 'voltage_v' => null, 'gauge_mm' => 1435, 'operator' => null,
                'source' => 'bdtopo', 'source_trace_id' => null,
                'geometry_withheld' => false, 'geometry_withheld_reason' => null,
            ],
        ]],
        'withheld' => [[
            'provider' => 'archeoferroviaire', 'name' => 'archeoferroviaire.free.fr (J. Randé, F. Librini, K. Moreau ; tracés aussi de F. Hespert et Y. Zwartjes)',
            'license' => 'Tous droits réservés (mentions légales) ; usage par VFF non commercial, avec crédit et lien', 'reason' => 'licence_not_open', 'sections' => 1, 'length_m' => 2480,
        ]],
    ]])]
    #[ApiResponse(404, "Ère inconnue (année qui n'est pas une décennie entre 1830 et 2030).")]
    public function lines(int $year, LineTraceExport $traceRights): StreamedResponse
    {
        // Ère inconnue → 404 AVANT d'ouvrir le flux : une fois les en-têtes 200
        // envoyés, le firstOrFail() du générateur produirait un JSON tronqué.
        abort_unless(Era::where('year', $year)->exists(), 404);

        // Ère résolue AVANT d'ouvrir le flux : sinon une année inconnue levait
        // l'exception en plein streaming (200 + JSON tronqué + page d'erreur).
        $era = Era::where('year', $year)->firstOrFail();

        // Streaming via curseur DB → mémoire constante (évite l'OOM 128M sur ~77k segments).
        return response()->stream(function () use ($year, $era, $traceRights) {
            echo '{"type":"FeatureCollection","features":[';
            $first = true;
            foreach ($this->exporter->streamLinesGeoJson($year) as $feature) {
                echo ($first ? '' : ',').json_encode($feature);
                $first = false;
            }
            // Résumé des tronçons dont la géométrie n'est pas redistribuée (règle des droits).
            echo '],"withheld":'.json_encode($traceRights->withheldForEra($era)).'}';
        }, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    /**
     * Gares à une ère (GeoJSON).
     *
     * Gares, haltes et points d'arrêt présents à l'ère demandée, en
     * `FeatureCollection` GeoJSON de points (WGS 84, longitude puis latitude).
     * L'ère est un instantané au 31/12 de son année ; `2030` = aujourd'hui.
     *
     * Une gare est **fermée** à une ère si toutes ses lignes sont fermées à
     * cette ère, ou si elle n'est plus desservie. Une gare fermée n'est
     * renvoyée que si une voie existe encore à proximité (ligne exploitée, ou
     * fermée mais non déposée) ; sur une ligne déposée, elle disparaît de la carte.
     *
     * Propriétés :
     * - `type` : `gare`, `halte`, `point_arret`, `passage_niveau`, `embranchement` ;
     * - `status` : `active` (desservie par des trains de voyageurs), `tourist`
     *   (desservie par un train touristique), `no_commercial` (plus de voyageurs,
     *   fret seul), `declassified` (fermée), `uncertain` (ouverte selon ses sources,
     *   mais aucune voie exploitée ne le confirme à cette ère) ;
     * - `sources_status` : statut selon les sources de la gare (diffère de `status` si `uncertain`) ;
     * - `service` : résumé pour l'affichage, `passenger` (voyageurs, donc aussi fret),
     *   `tourist`, `freight` (fret seul), `closed` (fermée), `uncertain` ;
     * - `uic` : code UIC à 8 chiffres ; `department_code` : département (INSEE) ;
     * - `opened_year`, `closed_year`, `closed_at` (AAAA-MM-JJ) et `closed_precision`
     *   (`day`, `month`, `year`, ou `range` : fermée après l'année `closed_after`) ;
     *   n'affichez que la partie connue de la date (ex. « 1938 » si `year`).
     *
     * @param  int  $year  Année de l'ère (décennie de 1830 à 2030 ; 2030 = aujourd'hui).
     *
     * @response array{
     *     type: 'FeatureCollection',
     *     features: array<array{
     *         type: 'Feature',
     *         id: int,
     *         geometry: array{type: 'Point', coordinates: array{0: float, 1: float}},
     *         properties: array{
     *             slug: string,
     *             name: string,
     *             type: string,
     *             status: string,
     *             sources_status: string,
     *             service: string,
     *             city: ?string,
     *             department_code: ?string,
     *             uic: ?string,
     *             opened_year: ?int,
     *             closed_year: ?int,
     *             closed_at: ?string,
     *             closed_precision: ?string,
     *             closed_after: ?int,
     *             note: ?string
     *         }
     *     }>
     * }
     */
    #[PathParameter('year', "Année de l'ère : une décennie de 1830 à 2030 (2030 = réseau actuel).", example: 1930)]
    #[ApiResponse(200, "FeatureCollection GeoJSON des gares de l'ère.", examples: [[
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'id' => 14873,
            'geometry' => ['type' => 'Point', 'coordinates' => [2.479135, 48.894032]],
            'properties' => [
                'slug' => 'bondy', 'name' => 'Bondy', 'type' => 'gare', 'status' => 'active',
                'sources_status' => 'active', 'service' => 'passenger', 'city' => 'Bondy',
                'department_code' => '93', 'uic' => '87113407', 'opened_year' => 1849,
                'closed_year' => null, 'closed_at' => null, 'closed_precision' => null,
                'closed_after' => null, 'note' => null,
            ],
        ]],
    ]])]
    #[ApiResponse(404, "Ère inconnue (année qui n'est pas une décennie entre 1830 et 2030).")]
    public function stations(Request $request, int $year): Response
    {
        abort_unless(Era::where('year', $year)->exists(), 404);

        return $this->cache->json($request, "stations:{$year}", fn (): array => $this->exporter->stationsAsGeoJson($year));
    }

    /**
     * Tuile vectorielle d'une ère (MVT).
     *
     * Tuile vectorielle au format Mapbox Vector Tile (protobuf), produite à la
     * volée par PostGIS (`ST_AsMVT`) dans la grille XYZ « Web Mercator »
     * habituelle. À utiliser comme source `vector` de MapLibre GL ou d'OpenLayers :
     *
     * ```
     * tiles: ["https://vff-project.org/api/v1/eras/1930/tiles/{z}/{x}/{y}.mvt"]
     * ```
     *
     * **Couche** `vff-lines` (LineString) : mêmes propriétés que l'endpoint GeoJSON
     * des tracés (`status`, `traffic_type`, `electrified`, `is_lgv`…), plus,
     * pour les voies partagées entre plusieurs lignes, `draw_rank` (ordre de
     * dessin : la portion la plus active au-dessus) et `covered` (`true` :
     * portion recouverte par une ligne plus active à cette ère, que la carte VFF
     * ne dessine pas).
     *
     * **Zooms 0 à 14** : la carte VFF n'en demande pas au-delà, et MapLibre agrandit
     * lui-même la tuile 14 aux zooms suivants (déclarez `maxzoom: 14` dans la source).
     * Au-delà, HTTP 404.
     *
     * **Géométrie pour l'écran** : chaque tracé est découpé dans la grille de la
     * tuile (4 096 unités de côté jusqu'au zoom 13, 8 192 au zoom 14, plus une marge
     * d'un seizième), qui arrondit ses coordonnées : 153 m au zoom 6, 9,6 m au zoom 10,
     * 0,3 m au zoom 14 (mètres Web Mercator ; environ deux tiers sur le terrain en
     * France). La grille de chaque couche est écrite dans la tuile (champ `extent`),
     * comme le prévoit la spécification MVT. À partir du zoom 10, le tracé est d'abord
     * simplifié (Douglas-Peucker) à une unité de cette grille (9,6 m au zoom 10, 1,2 m
     * au zoom 13, 0,3 m Web Mercator au zoom 14, soit 0,2 m sur le terrain).
     *
     * **Cache** : la réponse porte un `ETag` (requête conditionnelle `If-None-Match`
     * → 304 tant que les données n'ont pas changé) et arrive compressée (gzip) si
     * le client l'accepte. `Cache-Control` vaut un jour quand l'adresse porte un
     * paramètre `v` (version des données, comme le fait la carte VFF), une minute
     * sinon.
     *
     * **Tuile allégée** (`fields=map`) : seulement les propriétés que lit la carte du site
     * (`line_slug`, `line_name`, `line_code`, `is_lgv`, `is_threatened`, `network`,
     * `status`, `traffic_type`, `electrified`, `voltage_v`, `electrification_source`, `gauge_class`,
     * `is_heritage`, `heritage_kind`, `line_max_speed`, `line_electrified`, `line_voltage_v`,
     * `draw_rank`, `covered`). Sans `id`, `line_id`, `max_speed_kmh`, `gauge_mm`, `electrified_year`,
     * `operator`, `source`, `source_trace_id` ni `dismantled_at`. Une propriété sans valeur utile est
     * absente de l'entité : booléen faux (sauf `line_electrified`), `draw_rank` nul, `gauge_class`
     * `unknown`, `voltage_v` d'une portion non électrifiée, `heritage_kind` hors patrimoine ;
     * `electrification_source` ne vaut que `unknown` (électrifiée aujourd'hui, date inconnue) ou manque.
     * Les tracés aux propriétés identiques (en général les
     * portions voisines d'une même ligne dans le même état) sont réunis en une entité `MultiLineString`.
     * Le dessin ne change pas ; la tuile du zoom 4 qui couvre l'est de la France passe de 291 à 120 Ko
     * compressés. Sans `fields` (ou avec `fields=all`), la tuile garde toutes ses propriétés, une entité
     * par tronçon.
     *
     * **Affichage, pas export** : les tuiles servent à afficher la carte du site et
     * la carte intégrable (`/embed/maps`). Elles dessinent tous les tracés, y compris
     * ceux d'une source dont la licence n'autorise pas la redistribution, mais ne
     * sont pas un export de données. Pour réutiliser des tracés, servez-vous du
     * GeoJSON de l'ère, de `/lines/{line}/geojson`, `/gpx` ou `/kml`, qui suivent la
     * règle des droits.
     *
     * @param  int  $year  Année de l'ère (décennie de 1830 à 2030 ; 2030 = aujourd'hui).
     * @param  int  $z  Niveau de zoom (0 à 14).
     * @param  int  $x  Colonne de la tuile (0 à 2^z - 1).
     * @param  int  $y  Rangée de la tuile (0 à 2^z - 1, origine en haut à gauche).
     */
    #[PathParameter('year', "Année de l'ère : une décennie de 1830 à 2030 (2030 = réseau actuel).", example: 2030)]
    #[PathParameter('z', 'Niveau de zoom, de 0 à 14 (au-delà, MapLibre agrandit la tuile 14).', example: 8)]
    #[PathParameter('x', 'Colonne de la tuile dans la grille XYZ (0 à 2^z - 1).', example: 129)]
    #[PathParameter('y', 'Rangée de la tuile dans la grille XYZ (0 à 2^z - 1, origine en haut à gauche).', example: 88)]
    #[QueryParameter('fields', 'Propriétés des tracés : `all` (défaut, toutes) ou `map` (seulement celles que lit la carte du site, booléens écrits seulement quand ils sont vrais, tracés identiques réunis ; 60 % d\'octets en moins en vue d\'ensemble).', type: 'string', example: 'map')]
    #[ApiResponse(200, 'Tuile Mapbox Vector Tile binaire (protobuf), éventuellement vide.', mediaType: 'application/x-protobuf', type: 'string', format: 'binary')]
    #[ApiResponse(404, 'Tuile hors de la grille XYZ (x ou y >= 2^z) ou zoom supérieur à 14.')]
    #[ApiResponse(422, 'Valeur de `fields` inconnue (ni `all` ni `map`).', examples: [[
        'message' => 'The selected fields is invalid.',
        'errors' => ['fields' => ['The selected fields is invalid.']],
    ]])]
    public function tile(EraTileRequest $request, int $year, int $z, int $x, int $y): Response
    {
        // Tuile hors de la grille XYZ (ou zoom > 14) : elle n'existe pas. Sans ce
        // contrôle, ST_TileEnvelope lève « Invalid tile x value » (HTTP 500).
        abort_unless(MapTiles::onGrid($z, $x, $y), 404);
        abort_unless(Era::where('year', $year)->exists(), 404);
        $fields = $request->fields();

        return $this->cache->tile(
            $request,
            "lines:{$year}:{$z}:{$x}:{$y}{$fields->cacheSuffix()}",
            $z,
            fn (): string => $this->exporter->linesAsMvt($year, $z, $x, $y, $fields),
        );
    }
}
