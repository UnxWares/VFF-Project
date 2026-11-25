<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NetworkGraph\RouteException;
use App\Services\Routebook\Routebook;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Itinéraires', "Le chemin le plus court entre deux gares, sur le réseau d'une époque.", weight: 3)]
class RoutebookController extends Controller
{
    /** Exemple réel abrégé (Rennes - Saint-Malo, sans schéma des voies détaillé). */
    public const EXAMPLE = [
        'from' => ['slug' => 'rennes', 'name' => 'Rennes'],
        'to' => ['slug' => 'st-malo', 'name' => 'St-Malo'],
        'era_year' => 2030,
        'distance_m' => 80493,
        'distance_km' => 80.5,
        'objects' => 0,
        'legs' => [[
            'index' => 1,
            'line' => ['code' => '441000', 'slug' => 'rfn-441000', 'name' => 'Ligne de Rennes à St-Malo-St-Servan'],
            'from' => ['kind' => 'junction', 'slug' => null, 'name' => null],
            'to' => ['kind' => 'station', 'slug' => 'st-malo', 'name' => 'St-Malo'],
            'length_m' => 78276, 'route_from_km' => 2.217, 'route_to_km' => 80.493,
            'pk_from_m' => 375703, 'pk_to_m' => 454097, 'pk_precision' => 'derived', 'direction' => 'up', 'has_detail' => false,
        ]],
        'line_changes' => [[
            'route_km' => 2.217, 'from_line' => 'rfn-420000', 'to_line' => 'rfn-441000',
            'at' => ['kind' => 'junction', 'slug' => null, 'name' => null],
        ]],
        'sources' => [],
        'operational_points' => [],
        'tracks' => [],
        'switches' => [],
        'signals' => [],
        'crossings' => [],
        'buffer_stops' => [],
        'milestones' => [],
        'strip' => ['speeds' => [], 'gradients' => []],
        'coverage' => ['total_m' => 80493, 'rinf_m' => 0, 'osm_m' => 0, 'vff_m' => 0, 'missing' => [[
            'leg' => 1, 'line_code' => '441000', 'line_slug' => 'rfn-441000',
            'route_from_km' => 2.217, 'route_to_km' => 80.493, 'what' => 'all', 'pk_from_m' => 375703, 'pk_to_m' => 454097,
        ]]],
        'graph' => ['built_at' => '2026-11-24 08:00:00'],
    ];

    public function __construct(private readonly Routebook $routebook) {}

    /**
     * Livret de parcours entre deux gares.
     *
     * Le parcours d'aujourd'hui (réseau de l'ère courante, 2030) entre deux gares, ligne par ligne, à la manière
     * d'un livret de parcours : le plus court chemin du graphe de VFF (comme `GET /api/v1/route`, mode voyageurs),
     * puis le schéma des voies de chaque portion de ligne traversée (`GET /api/v1/lines/{line}/track-layout`),
     * mis bout à bout dans le sens de marche. Départ et arrivée au centre du point d'exploitation (PK de la gare
     * sur sa ligne).
     *
     * - `legs[]` : lignes traversées dans l'ordre. `from` et `to` : une gare (`kind` = `station`) ou un changement
     *   de ligne en pleine voie (`junction`) ; `pk_from_m`, `pk_to_m` : PK de la ligne aux deux bouts (nuls s'ils
     *   sont inconnus), `direction` : `up` (PK croissants), `down` (décroissants) ou nul ; `pk_precision` :
     *   `exact` (PK sourcé d'une gare), `derived` (PK projeté ou lu sur la forme SNCF de la ligne),
     *   `approximate` (interpolé entre deux gares) ; `route_from_km`, `route_to_km` : kilomètres parcourus depuis
     *   le départ ; `has_detail` : la portion a des voies ou des aiguillages détaillés.
     * - `line_changes[]` : changements de ligne, au kilomètre parcouru, à une gare ou en pleine voie.
     * - `operational_points`, `tracks`, `switches`, `signals`, `crossings`, `buffer_stops`, `milestones`, `strip` :
     *   mêmes objets que `track-layout`, avec la provenance de chacun (`source`, `precision`), plus `leg`,
     *   `line_code`, `line_slug` et `route_km` (ou `route_from_km` et `route_to_km` pour une étendue) ;
     *   identifiants préfixés du rang de la portion, références comprises (`from_track`, `to_track`, `track`,
     *   `tracks` d'une traversée, `op_id` d'une voie). Un signal porte `travel` (`same` : il vaut pour le sens de marche, `opposite`),
     *   un aiguillage `travel_facing` (pris en pointe). Déclivités `permille` dans le sens de marche (positive
     *   en rampe), `line_permille` dans le sens des PK. Listes triées par kilomètre parcouru.
     * - `coverage` : longueur totale, mètres de voies décrits par le RINF, OpenStreetMap et VFF, et `missing[]` :
     *   portions sans données, en kilomètres parcourus (`what` = une famille absente, `tracks`, `switches`…, ou
     *   `all` quand aucune source ne décrit la portion).
     *   Une portion sans donnée n'est pas une portion sans voie : la source ne la décrit pas.
     * - `objects` : nombre d'objets du livret (points d'exploitation, voies, aiguillages, signaux, traversées).
     *
     * Erreurs (champ `error`, avec `message`) : celles de `GET /api/v1/route` : `graph_not_built` (409),
     * `station_not_found` (404), `ambiguous_station` (422, avec `candidates`), `same_station` (422),
     * `station_not_in_network` (404), `no_path` (404).
     *
     * @response array{
     *     from: array{slug: string, name: string},
     *     to: array{slug: string, name: string},
     *     era_year: int,
     *     distance_m: int,
     *     distance_km: float,
     *     objects: int,
     *     legs: array<array{index: int, line: array{code: ?string, slug: string, name: string},
     *         from: array{kind: string, slug: ?string, name: ?string}, to: array{kind: string, slug: ?string, name: ?string},
     *         length_m: int, route_from_km: float, route_to_km: float, pk_from_m: ?int, pk_to_m: ?int,
     *         pk_precision: ?string, direction: ?string, has_detail: bool}>,
     *     line_changes: array<array{route_km: float, from_line: ?string, to_line: ?string, at: array{kind: string, slug: ?string, name: ?string}}>,
     *     sources: array<array{key: string, label: string, licence: ?string, url: ?string}>,
     *     operational_points: array<array<string, mixed>>,
     *     tracks: array<array<string, mixed>>,
     *     switches: array<array<string, mixed>>,
     *     signals: array<array<string, mixed>>,
     *     crossings: array<array<string, mixed>>,
     *     buffer_stops: array<array<string, mixed>>,
     *     milestones: array<array<string, mixed>>,
     *     strip: array{speeds: array<array<string, mixed>>, gradients: array<array<string, mixed>>},
     *     coverage: array{total_m: int, rinf_m: int, osm_m: int, vff_m: int, missing: array<array{leg: int, line_code: ?string, line_slug: string, route_from_km: float, route_to_km: float, what: string}>},
     *     graph: array{built_at: string}
     * }
     */
    #[QueryParameter('from', 'Gare de départ : slug (ex. `rennes`), identifiant, nom exact ou nom de ville, comme pour `GET /api/v1/route`.', required: true, type: 'string', example: 'rennes')]
    #[QueryParameter('to', "Gare d'arrivée, mêmes formes que `from`.", required: true, type: 'string', example: 'st-malo')]
    #[ApiResponse(200, 'Livret du parcours, y compris quand les portions traversées n\'ont pas de schéma détaillé (listes vides, `coverage.missing`).', examples: [RoutebookController::EXAMPLE])]
    #[ApiResponse(404, 'Gare ou chemin introuvable (`error` = `station_not_found`, `station_not_in_network` ou `no_path`).')]
    #[ApiResponse(409, 'Graphe du réseau actuel pas encore calculé (`error` = `graph_not_built`).')]
    #[ApiResponse(422, 'Paramètres invalides, gare ambiguë (`ambiguous_station`, avec `candidates`) ou même gare (`same_station`).')]
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|string|max:200',
            'to' => 'required|string|max:200',
        ]);

        try {
            $book = $this->routebook->between($request->string('from')->value(), $request->string('to')->value());
        } catch (RouteException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage(), ...$e->details], $e->status);
        }

        return response()->json($book)->header('Cache-Control', 'public, max-age=300');
    }
}
