<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Era;
use App\Models\Line;
use App\Services\LinePk;
use App\Services\Lines\LinePkPosition;
use App\Support\Search\PkQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LinePkController extends Controller
{
    /**
     * Point kilométrique d'un lieu sur une ligne.
     *
     * Convertit des coordonnées (latitude, longitude WGS 84) en **point
     * kilométrique (PK)** sur le tracé de la ligne à l'ère choisie : le point
     * est projeté sur le tracé le plus proche (`snap`), puis la distance est
     * mesurée le long du tracé depuis son origine (PostGIS `ST_LineLocatePoint`).
     *
     * - `pk_m` : PK en mètres ; `pk_label` : notation ferroviaire `km+mmm`
     *   (ex. `249+204` = 249,204 km) ;
     * - `total_m` : longueur totale du tracé à cette ère, en mètres ;
     * - `snap` : point du tracé retenu (`lat`, `lng`) et
     *   `snap.distance_from_input_m`, distance en mètres entre le point fourni
     *   et le tracé. Au-delà de quelques dizaines de mètres, le point n'est
     *   probablement pas sur cette ligne.
     *
     * **Tracé retenu** (`geometry_withheld` vrai) : quand le point projeté longe
     * un tronçon dont la source n'autorise pas la redistribution (voir « Droits
     * des tracés »), `snap` vaut `null` et `geometry_withheld_reason` donne la
     * raison (`licence_not_open`, `unknown_source`). Ni le point projeté ni la
     * distance au tracé ne sont donnés, car une grille de requêtes suffirait à
     * reconstituer le tracé, même avec une distance arrondie. Le lieu est ramené
     * au centre de sa maille de 0,001 degré (environ 110 m sur 75 m en France)
     * et le PK est arrondi à 100 m : il est juste à 150 m près environ.
     *
     * Ce PK est une distance mesurée le long du tracé VFF, de bout en bout de
     * ses portions : il peut différer du PK officiel de SNCF Réseau (origine
     * différente, sauts de numérotation, tracé discontinu). Pour les PK
     * officiels des gares, voir l'endpoint des gares de la ligne (`stops`).
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     pk_m: int,
     *     pk_label: string,
     *     total_m: int,
     *     geometry_withheld: bool,
     *     geometry_withheld_reason: ?string,
     *     snap: array{lat: float, lng: float, distance_from_input_m: float}|null
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-420000')]
    #[QueryParameter('lat', 'Latitude WGS 84 du point, en degrés décimaux (-90 à 90).', required: true, type: 'float', example: 48.10)]
    #[QueryParameter('lng', 'Longitude WGS 84 du point, en degrés décimaux (-180 à 180).', required: true, type: 'float', example: -1.67)]
    #[QueryParameter('era', "Année de l'ère (décennie de 1830 à 2030). Par défaut : l'ère courante, 2030, c'est-à-dire le réseau d'aujourd'hui.", type: 'int', example: 1930)]
    #[ApiResponse(200, 'Point kilométrique du lieu sur la ligne.', examples: [[
        'line' => ['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest'],
        'pk_m' => 249204,
        'pk_label' => '249+204',
        'total_m' => 626972,
        'geometry_withheld' => false,
        'geometry_withheld_reason' => null,
        'snap' => ['lat' => 48.103576, 'lng' => -1.67024, 'distance_from_input_m' => 395.12],
    ], [
        'line' => ['slug' => 'rfn-134000', 'code' => '134000', 'name' => 'Ligne d\'Altkirch à Ferrette'],
        'pk_m' => 1800,
        'pk_label' => '1+800',
        'total_m' => 24116,
        'geometry_withheld' => true,
        'geometry_withheld_reason' => 'licence_not_open',
        'snap' => null,
    ]])]
    #[ApiResponse(404, 'Ligne ou ère inconnue, ou ligne sans tracé à cette ère (`error` = `no_geometry`).')]
    public function fromPoint(Line $line, Request $request, LinePk $pk): JsonResponse
    {
        $request->validate([
            // Bornes WGS 84 : un point impossible n'a pas de PK.
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'era' => 'nullable|integer',
        ]);

        $era = $request->filled('era')
            ? Era::where('year', $request->integer('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();

        $result = $pk->locate($line, $era->id, (float) $request->input('lat'), (float) $request->input('lng'));

        if ($result === null) {
            return response()->json(['error' => 'no_geometry'], 404);
        }

        return response()->json(['line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name]] + $result);
    }

    /**
     * Position d'un point kilométrique sur une ligne.
     *
     * L'inverse de `GET /lines/{line}/pk` : à partir d'un PK, donne les deux gares entre lesquelles il tombe
     * (`between`, ou `at_station` quand le PK est celui d'une gare), la commune (`commune`, celle de la gare la
     * plus proche en PK), les bornes de la ligne (`bounds`, PK de sa première et de sa dernière gare) et un point
     * à placer sur la carte (`lat`, `lng`). Les PK sont ceux des gares de la ligne (SNCF Réseau pour la plupart),
     * à l'ère courante, sinon à la dernière ère où la ligne a des gares. Même lecture que la recherche
     * (`GET /search` renvoie ces positions dans `pk_positions` pour « 570000 PK 120 »).
     *
     * `pk` : en kilomètres, `120`, `120,5` ou `120+500` (kilomètres et mètres) ; ou `pk_m` en mètres. Le point
     * (`position_method`) est lu sur le tracé du profil en long (`profile`) ; sans profil, ou quand le tracé sous
     * ce point n'est pas redistribuable (`position_withheld`, voir « Droits des tracés »), il est placé en ligne
     * droite entre les deux gares (`stations`, approximatif) ; `station` : celui de la gare elle-même.
     *
     * @response array{
     *     line: array{slug: string, code: ?string, name: string},
     *     pk_m: int,
     *     pk_label: string,
     *     in_range: bool,
     *     bounds: array{from_m: int, to_m: int, from_label: string, to_label: string},
     *     at_station: ?array{slug: string, name: string, city: ?string, pk_m: int, pk_label: string},
     *     between: ?array{from: array{slug: string, name: string, city: ?string, pk_m: int, pk_label: string}, to: array{slug: string, name: string, city: ?string, pk_m: int, pk_label: string}},
     *     commune: ?string,
     *     lat: ?float,
     *     lng: ?float,
     *     position_method: 'profile'|'stations'|'station'|null,
     *     position_withheld: ?string
     * }
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne.', example: 'rfn-570000')]
    #[QueryParameter('pk', 'PK en kilomètres : `120`, `120,5` ou `120+500`.', type: 'string', example: '120+500')]
    #[QueryParameter('pk_m', 'PK en mètres (au lieu de `pk`).', type: 'int', example: 120500)]
    #[ApiResponse(200, 'Position du PK (Paris-Austerlitz - Bordeaux-St-Jean, PK 120+500).', examples: [[
        'line' => ['slug' => 'rfn-570000', 'code' => '570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean'],
        'pk_m' => 120500,
        'pk_label' => '120+500',
        'in_range' => true,
        'bounds' => ['from_m' => 0, 'to_m' => 583844, 'from_label' => '0+000', 'to_label' => '583+844'],
        'at_station' => null,
        'between' => [
            'from' => ['slug' => 'exemple-a', 'name' => 'Gare A', 'city' => 'Ville A', 'pk_m' => 118000, 'pk_label' => '118+000'],
            'to' => ['slug' => 'exemple-b', 'name' => 'Gare B', 'city' => 'Ville B', 'pk_m' => 123000, 'pk_label' => '123+000'],
        ],
        'commune' => 'Ville A',
        'lat' => 48.0,
        'lng' => 2.0,
        'position_method' => 'profile',
        'position_withheld' => null,
    ]])]
    #[ApiResponse(404, 'Ligne inconnue ou sans gare (`no_stops`), ou PK hors de la ligne (`pk_out_of_range`, avec `bounds`).')]
    #[ApiResponse(422, 'Ni `pk` ni `pk_m` valide.')]
    public function atPk(Line $line, Request $request, LinePkPosition $position): JsonResponse
    {
        $request->validate([
            'pk' => 'nullable|string|max:20|required_without:pk_m',
            'pk_m' => 'nullable|integer|between:0,5000000|required_without:pk',
        ]);
        $pk = $request->filled('pk_m') ? $request->integer('pk_m') : PkQuery::parseValue((string) $request->input('pk'));
        if ($pk === null) {
            return response()->json(['message' => 'The pk field must be kilometres: 120, 120,5 or 120+500.', 'errors' => ['pk' => ['Invalid pk.']]], 422);
        }
        $at = $position->at($line, $pk);
        if ($at === null) {
            return response()->json(['error' => 'no_stops'], 404);
        }
        if (! $at['in_range']) {
            return response()->json(['error' => 'pk_out_of_range', 'message' => "PK {$at['pk_label']} hors de la ligne ({$at['bounds']['from_label']} à {$at['bounds']['to_label']})."] + $at, 404);
        }

        return response()->json($at)->header('Cache-Control', 'public, max-age=300');
    }
}
