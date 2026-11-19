<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompareRouteRequest;
use App\Services\NetworkGraph\RouteException;
use App\Services\PathfindingService;
use App\Services\RouteComparison;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Itinéraires', "Le chemin le plus court entre deux gares, sur le réseau d'une époque.", weight: 3)]
class RouteController extends Controller
{
    /**
     * Exemple réel de réponse de `compare` (Angoulême - Limoges-Bénédictins, 1930 et 2026), abrégé :
     * gares, lignes et tracés raccourcis, champs de l'itinéraire réduits à l'essentiel.
     */
    public const COMPARE_EXAMPLE = [
        'from' => ['slug' => 'angouleme', 'name' => 'Angoulême'],
        'to' => ['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins'],
        'earlier' => [
            'requested_year' => 1930, 'era_year' => 1930, 'era_is_current' => false,
            'route' => [
                'distance_m' => 118722, 'distance_km' => 118.72, 'duration_h' => 1.19, 'hops' => 18,
                'stations' => [['slug' => 'angouleme', 'name' => 'Angoulême', 'lat' => 45.653643, 'lng' => 0.164145, 'status' => 'active']],
                'lines' => [[
                    'line_slug' => 'rfn-610000', 'line_name' => 'Ligne de Limoges-Bénédictins à Angoulême', 'line_code' => '610000',
                    'length_m' => 117471, 'length_km' => 117.5, 'from_slug' => 'angouleme', 'from_name' => 'Angoulême', 'to_slug' => null, 'to_name' => null,
                ]],
                'warnings' => ['gap_joins'],
            ],
            'unavailable' => null,
        ],
        'later' => [
            'requested_year' => 2026, 'era_year' => 2030, 'era_is_current' => true,
            'route' => [
                'distance_m' => 241065, 'distance_km' => 241.07, 'duration_h' => 2.41, 'hops' => 19,
                'stations' => [['slug' => 'angouleme', 'name' => 'Angoulême', 'lat' => 45.653643, 'lng' => 0.164145, 'status' => 'active']],
                'lines' => [[
                    'line_slug' => 'rfn-570000', 'line_name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean', 'line_code' => '570000',
                    'length_m' => 22203, 'length_km' => 22.2, 'from_slug' => 'angouleme', 'from_name' => 'Angoulême', 'to_slug' => null, 'to_name' => null,
                ]],
                'warnings' => [],
            ],
            'unavailable' => null,
        ],
        'differences' => [
            'outcome' => 'both',
            'distance_change_m' => 122343, 'distance_change_km' => 122.3, 'distance_change_percent' => 103.0,
            'same_path' => false,
            'shared_lines' => [],
            'lines_only_earlier' => [[
                'line_slug' => 'rfn-610000', 'line_name' => 'Ligne de Limoges-Bénédictins à Angoulême', 'line_code' => '610000',
                'length_m' => 117471, 'length_km' => 117.5, 'other_era' => 'partly_open',
            ]],
            'lines_only_later' => [[
                'line_slug' => 'rfn-566000', 'line_name' => 'Ligne SEA-Atlantique (LGV)', 'line_code' => '566000',
                'length_m' => 6238, 'length_km' => 6.2, 'other_era' => 'none',
            ]],
            'shared_stations' => 2,
        ],
    ];

    public function __construct(protected PathfindingService $pathfinding) {}

    /**
     * Itinéraire historique entre deux gares.
     *
     * Le plus court chemin ferroviaire entre deux gares sur le réseau **en
     * service à l'époque demandée** : par exemple Rennes - Brest en 1930. Le
     * calcul se fait sur le graphe gare à gare de l'ère (gares ouvertes et
     * voies exploitées au 31/12 de l'année de l'ère).
     *
     * Réponse :
     * - `requested_year` : année demandée ; `era_year` : ère utilisée (la
     *   décennie qui la précède ou la contient ; 2030 = réseau actuel) ;
     * - `distance_m` / `distance_km` : longueur du trajet ; `duration_h` :
     *   durée **indicative** en heures, à 100 km/h de moyenne (pas un horaire) ;
     * - `hops` : nombre de sections gare à gare ; `stations` : gares traversées,
     *   dans l'ordre, avec leur statut à l'ère ; `lines` : lignes empruntées,
     *   dans l'ordre, avec leur longueur parcourue ; `edges` : détail de
     *   chaque section (`skipped_count` : gares fermées sautées entre deux gares) ;
     * - `non_passenger_m` : mètres parcourus sur des voies sans service
     *   voyageurs ; `heritage_m` : mètres sur des voies d'associations ;
     *   `gauge_changes` : changements d'écartement en route (en mm) ;
     * - `gap_joins` : raccords entre portions de tracé non jointives ;
     *   `warnings` : avertissements à signaler à l'utilisateur ;
     * - `geometry` : tracé du trajet en GeoJSON (WGS 84) ;
     * - `graph` : métadonnées du graphe utilisé (date de calcul…).
     *
     * Erreurs (champ `error`, avec `message`) : `era_not_found` (404),
     * `graph_not_built` (409, graphe de l'ère pas encore calculé),
     * `station_not_found` (404), `ambiguous_station` (422, liste
     * `candidates` à proposer), `same_station` (422),
     * `station_not_in_network` (404, gare hors du réseau exploité à cette
     * ère), `no_path` (404, aucun chemin à cette époque).
     *
     * @response array{
     *     from: array{slug: string, name: string},
     *     to: array{slug: string, name: string},
     *     requested_year: ?int,
     *     era_year: int,
     *     distance_m: int,
     *     distance_km: float,
     *     duration_h: float,
     *     hops: int,
     *     stations: array<array{slug: string, name: string, lat: float, lng: float, status: ?string}>,
     *     lines: array<array{line_slug: ?string, line_name: ?string, line_code: ?string, length_m: int, length_km: float, from_slug: ?string, from_name: ?string, to_slug: ?string, to_name: ?string}>,
     *     edges: array<array{from_slug: string, to_slug: string, line_id: int, line_ids: array<int>, length_m: int, skipped_count: int, non_passenger_m: int, heritage_m: int}>,
     *     gauge_changes: array<array{station_slug: string, station_name: string, from_mm: int, to_mm: int}>,
     *     non_passenger_m: int,
     *     heritage_m: int,
     *     closed_stations_passed: int,
     *     gap_joins: int,
     *     warnings: array<string>,
     *     geometry: array{type: string, coordinates: array},
     *     graph: array{built_at: string, stations_source: string, components: int, station_nodes: int}
     * }
     */
    #[QueryParameter('from', "Gare de départ : slug (ex. `rennes`), identifiant, nom exact ou nom de ville (« Paris » = toutes les gares parisiennes ouvertes à l'époque).", required: true, type: 'string', example: 'rennes')]
    #[QueryParameter('to', "Gare d'arrivée, mêmes formes que `from`.", required: true, type: 'string', example: 'brest')]
    #[QueryParameter('era', "Année quelconque de 1800 à 2100 : on retient l'ère de cette année, sinon la décennie précédente (1937 donne l'ère 1930, réseau au 31/12/1930). À partir de l'année en cours : réseau actuel. Par défaut : réseau actuel.", type: 'int', example: 1930)]
    #[QueryParameter('year', 'Synonyme de `era` (ignoré si `era` est fourni).', type: 'int', example: 1930)]
    #[QueryParameter('heritage', 'Autoriser les voies exploitées par une association (train touristique) : `1` ou `0`.', type: 'bool', default: false, example: false)]
    #[QueryParameter('traffic', 'Voies sans service voyageurs (fret seul, réseaux militaires ou particuliers) : `passenger` les pénalise (comptées 4 fois leur longueur), `all` les traite comme les autres.', type: 'string', default: 'passenger', example: 'passenger')]
    #[ApiResponse(200, 'Itinéraire trouvé.', examples: [[
        'from' => ['slug' => 'rennes', 'name' => 'Rennes'],
        'to' => ['slug' => 'brest', 'name' => 'Brest'],
        'requested_year' => 1930, 'era_year' => 1930, 'era_is_current' => false,
        'distance_m' => 248936, 'distance_km' => 248.94, 'duration_h' => 2.49, 'hops' => 31,
        'stations' => [
            ['slug' => 'rennes', 'name' => 'Rennes', 'lat' => 48.103193, 'lng' => -1.672381, 'status' => 'active'],
            ['slug' => 'lhermitage-mordelles', 'name' => "L'Hermitage-Mordelles", 'lat' => 48.123186, 'lng' => -1.819765, 'status' => 'active'],
        ],
        'lines' => [[
            'line_slug' => 'rfn-420000', 'line_name' => 'Ligne de Paris-Montparnasse à Brest', 'line_code' => '420000',
            'length_m' => 248398, 'length_km' => 248.4, 'from_slug' => 'rennes', 'from_name' => 'Rennes', 'to_slug' => 'brest', 'to_name' => 'Brest',
        ]],
        'edges' => [[
            'from_slug' => 'rennes', 'to_slug' => 'lhermitage-mordelles', 'line_id' => 402, 'line_ids' => [402],
            'length_m' => 11884, 'skipped_count' => 0, 'non_passenger_m' => 0, 'heritage_m' => 0,
        ]],
        'gauge_changes' => [], 'non_passenger_m' => 0, 'heritage_m' => 0, 'closed_stations_passed' => 0,
        'gap_joins' => 3, 'warnings' => ['gap_joins'],
        'options' => ['heritage' => false, 'traffic' => 'passenger'],
        'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[-4.47978, 48.38782], [-4.47854, 48.38787]]]],
        'graph' => ['built_at' => '2026-10-20 13:20:49', 'stations_source' => 'station_eras', 'components' => 21, 'station_nodes' => 8298],
    ]])]
    #[ApiResponse(404, 'Ère, gare ou chemin introuvable (`error` = `era_not_found`, `station_not_found`, `station_not_in_network` ou `no_path`).')]
    #[ApiResponse(409, "Graphe de l'ère pas encore calculé (`error` = `graph_not_built`).")]
    public function route(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|string|max:200',
            'to' => 'required|string|max:200',
            'era' => 'nullable|integer|min:1800|max:2100',
            'year' => 'nullable|integer|min:1800|max:2100',
            'heritage' => 'nullable|boolean',
            'traffic' => 'nullable|in:passenger,all',
        ]);

        $year = $request->filled('era') ? $request->integer('era')
            : ($request->filled('year') ? $request->integer('year') : null);

        try {
            $result = $this->pathfinding->route(
                $request->string('from')->value(),
                $request->string('to')->value(),
                $year,
                ['heritage' => $request->boolean('heritage'), 'traffic' => $request->input('traffic', 'passenger')],
            );
        } catch (RouteException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage(), ...$e->details], $e->status);
        }

        return response()->json($result)->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Comparer un trajet entre deux époques.
     *
     * Le même trajet (gare A → gare B) calculé sur le réseau de deux époques, par exemple
     * Angoulême - Limoges en 1930 et aujourd'hui : chaque époque reçoit l'itinéraire de
     * `GET /api/v1/route` (mêmes champs), ou la raison qui le rend impossible, puis un résumé
     * des différences.
     *
     * Les deux époques sont rangées dans l'ordre chronologique, quel que soit l'ordre des
     * paramètres : `earlier` (la plus ancienne) et `later`. Pour chacune :
     * - `requested_year` : année demandée (nulle : réseau actuel) ; `era_year` : ère retenue ;
     *   `era_is_current` : vrai pour le réseau d'aujourd'hui ;
     * - `route` : l'itinéraire complet (voir `GET /api/v1/route`), ou `null` ;
     * - `unavailable` : `null`, ou la raison de l'absence de trajet : `error` = `no_path`
     *   (gares non reliées par le réseau en service) ou `station_not_in_network` (gare fermée
     *   ou hors du réseau exploité, avec `side`, `station` et `status`), avec `message`.
     *
     * `differences` :
     * - `outcome` : `both` (trajet possible aux deux époques), `lost` (devenu impossible),
     *   `gained` (devenu possible), `neither` (impossible aux deux) ;
     * - `distance_change_m`, `distance_change_km`, `distance_change_percent` : écart de
     *   longueur, seconde époque moins première (nul si un trajet manque) ;
     * - `same_path` : vrai si les deux trajets passent par les mêmes gares ;
     * - `shared_lines` : lignes empruntées aux deux époques ; `lines_only_earlier`,
     *   `lines_only_later` : lignes d'un seul des deux trajets, longueurs parcourues
     *   additionnées, avec `other_era` = ce qu'était la ligne à l'autre époque : `open` (en
     *   service sur au moins 90 % de son tracé, mais hors du plus court chemin), `partly_open`
     *   (en service sur une partie seulement), `closed` (fermée, voie en place) ou `none`
     *   (déposée, pas encore ouverte ou absente de la carte) ;
     * - `shared_stations` : nombre de gares communes aux deux trajets.
     *
     * Erreurs (champ `error`, avec `message`) : celles de `GET /api/v1/route` qui valent pour
     * les deux époques (`era_not_found` 404, `graph_not_built` 409, `station_not_found` 404,
     * `ambiguous_station` 422 avec `candidates`, `same_station` 422), et `same_era` (422) quand
     * les deux années désignent la même ère (1931 et 1935 donnent toutes deux 1930).
     *
     * @response array{
     *     from: array{slug: string, name: string}|null,
     *     to: array{slug: string, name: string}|null,
     *     earlier: array{requested_year: ?int, era_year: int, era_is_current: bool, route: ?array<string, mixed>, unavailable: ?array{error: string, message: string}},
     *     later: array{requested_year: ?int, era_year: int, era_is_current: bool, route: ?array<string, mixed>, unavailable: ?array{error: string, message: string}},
     *     differences: array{
     *         outcome: 'both'|'lost'|'gained'|'neither',
     *         distance_change_m: ?int,
     *         distance_change_km: ?float,
     *         distance_change_percent: ?float,
     *         same_path: bool,
     *         shared_lines: array<array{line_slug: ?string, line_name: ?string, line_code: ?string, length_m: int, length_km: float}>,
     *         lines_only_earlier: array<array{line_slug: ?string, line_name: ?string, line_code: ?string, length_m: int, length_km: float, other_era: 'open'|'partly_open'|'closed'|'none'|null}>,
     *         lines_only_later: array<array{line_slug: ?string, line_name: ?string, line_code: ?string, length_m: int, length_km: float, other_era: 'open'|'partly_open'|'closed'|'none'|null}>,
     *         shared_stations: int
     *     }
     * }
     */
    #[QueryParameter('from', 'Gare de départ : slug (ex. `angouleme`), identifiant, nom exact ou nom de ville, comme pour `GET /api/v1/route`.', required: true, type: 'string', example: 'angouleme')]
    #[QueryParameter('to', "Gare d'arrivée, mêmes formes que `from`.", required: true, type: 'string', example: 'limoges-benedictins')]
    #[QueryParameter('era', "Première année, de 1800 à 2100 : on retient l'ère de cette année, sinon la décennie précédente (1937 donne l'ère 1930). À partir de l'année en cours, et par défaut : réseau actuel.", type: 'int', example: 1930)]
    #[QueryParameter('compare', 'Seconde année, mêmes règles que `era` (2026 ou plus : réseau actuel). Elle doit désigner une autre ère que `era`.', required: true, type: 'int', example: 2026)]
    #[QueryParameter('heritage', 'Autoriser les voies exploitées par une association (train touristique), aux deux époques : `1` ou `0`.', type: 'bool', default: false, example: false)]
    #[QueryParameter('traffic', 'Voies sans service voyageurs : `passenger` les pénalise (comptées 4 fois leur longueur), `all` les traite comme les autres.', type: 'string', default: 'passenger', example: 'passenger')]
    #[ApiResponse(200, 'Comparaison calculée, y compris quand le trajet est impossible à une époque (`route` nul, `unavailable` renseigné).', examples: [RouteController::COMPARE_EXAMPLE])]
    #[ApiResponse(404, 'Ère ou gare introuvable (`error` = `era_not_found` ou `station_not_found`).')]
    #[ApiResponse(409, "Graphe d'une des deux ères pas encore calculé (`error` = `graph_not_built`).")]
    #[ApiResponse(422, 'Paramètres invalides, gare ambiguë (`ambiguous_station`, avec `candidates`), même gare (`same_station`) ou même ère (`same_era`).')]
    public function compare(CompareRouteRequest $request, RouteComparison $comparison): JsonResponse
    {
        try {
            $result = $comparison->compare(
                $request->string('from')->value(),
                $request->string('to')->value(),
                $request->eraYear(),
                $request->compareYear(),
                $request->routeOptions(),
            );
        } catch (RouteException $e) {
            return response()->json(['error' => $e->errorCode, 'message' => $e->getMessage(), ...$e->details], $e->status);
        }

        return response()->json($result)->header('Cache-Control', 'public, max-age=300');
    }
}
