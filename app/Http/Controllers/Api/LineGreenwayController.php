<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Services\Greenways\GreenwayTiles;
use App\Services\Greenways\LineGreenways;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineGreenwayController extends Controller
{
    /**
     * Voies vertes d'une ligne.
     *
     * Voies vertes et chemins cyclables d'OpenStreetMap qui reprennent l'emprise d'une ligne disparue
     * ou fermée. Le rattachement est **mesuré** (`vff:link-greenways`), pas déclaré : chaque way OSM est
     * découpé en morceaux de 25 m, et un morceau compte comme « sur l'emprise » quand son milieu est à
     * 8 m au plus de l'axe de la ligne **et** qu'aucune voie n'est encore en service ou en place à
     * moins de 30 m. Une voie verte qui longe seulement une ligne en service, ou une voie laissée en
     * place, n'est donc jamais rattachée. Un way est retenu si au moins 50 m de sa longueur (et au moins
     * la moitié) suivent l'ancienne emprise ; une ligne n'a de voie verte qu'à partir de 300 m communs.
     * Liste vide pour une ligne sans voie verte rattachée.
     *
     * `greenways` regroupe les ways par voie verte nommée : le nom est celui de l'itinéraire cyclable OSM
     * (relation `route=bicycle` ou `route=mtb`) quand le way en est membre, sinon celui du way ; les ways
     * sans nom forment un groupe au `name` nul. Pour chaque voie verte :
     * - `shared_length_m` : longueur en mètres qui suit l'ancienne emprise de la ligne, tous ses ways
     *   ensemble (et non la longueur de la voie verte entière, qui peut dépasser la ligne) ;
     * - `opened_year` : année d'ouverture si OSM la donne (`start_date` du way ou de l'itinéraire, 1970 ou
     *   après), sinon nulle : elle n'est jamais devinée ;
     * - `osm_url` : lien vers l'itinéraire OSM (relation) s'il existe, sinon vers le way le plus long ;
     * - `ways` : le détail, un élément par way OSM, avec son identifiant (`osm_way_id`), son type de
     *   chemin (`highway` : `cycleway`, `path`, `footway`, `track`, `bridleway`, `pedestrian`), sa
     *   longueur entière (`way_length_m`), sa longueur sur l'emprise (`shared_length_m`) et la part de
     *   celle-ci (`shared_percent`, 50 à 100).
     *
     * `total_shared_length_m` additionne les longueurs sur l'emprise. `osm_date` est la date (AAAA-MM-JJ)
     * de la lecture d'OpenStreetMap la plus récente.
     *
     * **Licence** : données © contributeurs OpenStreetMap, ODbL 1.0. Reproduisez la mention du champ
     * `attribution` et publiez sous la même licence toute base dérivée que vous diffusez. `provenance`
     * donne le fournisseur et la licence, comme les autres réponses de l'API. Les mêmes tracés sont dessinés
     * sur la carte par `GET /greenways/tiles/{z}/{x}/{y}.mvt`.
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-436000')]
    #[ApiResponse(200, "Voies vertes rattachées à la ligne (liste vide s'il n'y en a aucune).", examples: [[
        'line' => ['slug' => 'rfn-436000', 'code' => '436000', 'name' => 'Ligne de La Chapelle-Anthenaise à Flers'],
        'total_shared_length_m' => 67658,
        'osm_date' => '2026-11-18',
        'greenways' => [[
            'name' => 'Voie verte de Mayenne à Javron',
            'shared_length_m' => 15161,
            'way_count' => 2,
            'opened_year' => null,
            'osm_relation_id' => 253186,
            'osm_url' => 'https://www.openstreetmap.org/relation/253186',
            'ways' => [[
                'osm_way_id' => 840864544, 'osm_url' => 'https://www.openstreetmap.org/way/840864544',
                'name' => 'Voie Verte de Mayenne à La Chapelle-Anthenaise', 'highway' => 'path',
                'shared_length_m' => 11200, 'way_length_m' => 11206, 'shared_percent' => 100, 'opened_year' => null,
            ], [
                'osm_way_id' => 155309742, 'osm_url' => 'https://www.openstreetmap.org/way/155309742',
                'name' => 'Voie Verte de Mayenne à La Chapelle-Anthenaise', 'highway' => 'path',
                'shared_length_m' => 3961, 'way_length_m' => 3962, 'shared_percent' => 100, 'opened_year' => null,
            ]],
        ]],
        'attribution' => LineGreenways::ATTRIBUTION,
        'provenance' => ['provider' => 'osm', 'name' => 'OpenStreetMap', 'license' => 'ODbL 1.0 (Open Database License)', 'license_status' => 'verified'],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue.', examples: [ErrorExamples::LINE_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(Line $line, LineGreenways $greenways): JsonResponse
    {
        $groups = $greenways->groups($line);

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'total_shared_length_m' => $greenways->totalLength($groups),
            'osm_date' => $groups === [] ? null : $greenways->osmDate($line),
            'greenways' => $groups,
            'attribution' => LineGreenways::ATTRIBUTION,
            'provenance' => $greenways->provenance(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Tuiles des voies vertes (MVT).
     *
     * Tuile vectorielle Mapbox (protobuf), couche `vff-greenways` : la partie de chaque way OSM qui suit
     * l'ancienne emprise d'une ligne (`GET /lines/{line}/greenways`). Propriétés : `way_id` (identifiant du
     * way OSM), `name` (nom de l'itinéraire OSM ou du way, absent s'ils n'en ont pas), `highway`,
     * `shared_length_m` (longueur du way sur l'emprise de cette ligne), `opened_year` (si OSM la donne),
     * `line_slug` et `line_name`. Un way qui reprend l'emprise de deux lignes superposées figure une fois
     * par ligne. Tuiles vides sous le zoom 4 ; avant le zoom 11 les tracés sont simplifiés à la résolution
     * de la tuile. Ces voies vertes ne dépendent pas de l'ère : c'est l'état d'OpenStreetMap à la dernière
     * lecture. Données © contributeurs OpenStreetMap, ODbL 1.0 ; tuiles en cache une journée.
     */
    #[PathParameter('z', 'Niveau de zoom (0 à 22 ; vide sous 4).', example: 9)]
    #[PathParameter('x', 'Colonne de la tuile dans la grille XYZ.', example: 258)]
    #[PathParameter('y', 'Rangée de la tuile dans la grille XYZ.', example: 176)]
    #[ApiResponse(200, 'Tuile MVT (protobuf binaire), éventuellement vide.')]
    #[ApiResponse(404, 'Tuile hors de la grille XYZ.')]
    public function tile(int $z, int $x, int $y, GreenwayTiles $tiles): Response
    {
        abort_unless($z <= 22 && $x < (1 << $z) && $y < (1 << $z), 404);

        return response($tiles->tile($z, $x, $y), 200, [
            'Content-Type' => 'application/x-protobuf',
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
