<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MaterialTimelineRequest;
use App\Services\Materials\MaterialTimeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Matériel roulant', "Les séries de matériel roulant qui ont circulé en France (locomotives, automotrices, autorails, rames TGV, voitures, wagons, tramways), leurs caractéristiques sourcées et les lignes qu'elles ont parcourues.", weight: 6)]
class MaterialTimelineController extends Controller
{
    /** Exemple : deux séries de la base (lecture du 23/11/2026), une datée des deux côtés, une dont le retrait est déclaré sans date. */
    private const EXAMPLE = [
        'data' => [
            [
                'slug' => 'bb-9200', 'name' => 'BB 9200', 'type' => 'locomotive_electrique', 'group' => 'electrique',
                'builders' => ['Jeumont-Schneider', 'Compagnie électro-mécanique'],
                'in_service_from' => 1957, 'in_service_to' => 2015, 'service_status' => null, 'units_built' => 92,
            ],
            [
                'slug' => 'tgv-sud-est', 'name' => 'TGV Sud-Est', 'type' => 'rame_tgv', 'group' => 'tgv',
                'builders' => ['Alstom', 'Francorail'],
                'in_service_from' => 1981, 'in_service_to' => null, 'service_status' => 'withdrawn', 'units_built' => null,
            ],
        ],
        'meta' => ['total' => 885, 'dated' => 416, 'undated' => 469, 'first_year' => 1852, 'current_year' => 2026],
    ];

    /** 422 : traction inconnue. */
    private const INVALID = [
        'message' => 'The selected group is invalid.',
        'errors' => ['group' => ['The selected group is invalid.']],
    ];

    /**
     * Frise du matériel roulant.
     *
     * Toutes les séries de matériel roulant à plat, avec de quoi les placer sur une frise : `in_service_from` et
     * `in_service_to` (années de mise en service et de retrait), `service_status` (`in_service` ou `withdrawn`
     * quand une source le déclare), `units_built` (exemplaires construits) et `group`, la traction qui les
     * regroupe : `vapeur`, `electrique`, `diesel`, `autorail`, `automoteur` (automotrices et bimodes), `tgv`,
     * `tramway`, `voitures_wagons`, `service`. La liste n'est pas paginée (quelques centaines de séries) et
     * porte les mêmes valeurs que `GET /materials` ; `type` y est la catégorie fine.
     *
     * **Dates** : une année absente veut dire « non renseignée par la source », jamais une estimation. Une série
     * sans `in_service_from` est comptée dans `meta.undated` et n'a pas de place sur la frise. Un `in_service_to`
     * nul ne prouve pas que la série circule encore : seul `service_status` = `in_service` le dit.
     *
     * **En service une année donnée** (`in_service_at`) : ne garde que les séries dont la mise en service est
     * antérieure ou égale à l'année et que rien ne dit retirées avant ; chaque entrée porte alors
     * `in_service_state` : `yes` (les dates connues encadrent l'année, ou série déclarée en service) ou `maybe`
     * (mise en service passée mais fin non renseignée : aucune source ne dit si elle circulait encore).
     *
     * `meta` : `total`, `dated`, `undated`, `first_year` (plus ancienne mise en service connue) et `current_year`.
     * Réponse gardée en cache 10 minutes.
     */
    #[QueryParameter('group', 'Traction : `vapeur`, `electrique`, `diesel`, `autorail`, `automoteur`, `tgv`, `tramway`, `voitures_wagons`, `service`.', type: 'string', example: 'vapeur')]
    #[QueryParameter('builder', 'Constructeur, recherche partielle sans accents ni capitales (ex. `alstom`).', type: 'string', example: 'alstom')]
    #[QueryParameter('in_service_at', 'Année (1800 à 2100) : séries en service cette année-là (`in_service_state` dit si c\'est sûr ou possible).', type: 'int', example: 1960)]
    #[ApiResponse(200, 'Les séries de la frise (ici deux séries, sur 885).', examples: [self::EXAMPLE])]
    #[ApiResponse(422, 'Paramètre invalide (traction inconnue, année hors bornes) : détail par paramètre dans `errors`.', examples: [self::INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(MaterialTimelineRequest $request, MaterialTimeline $timeline): JsonResponse
    {
        return response()->json($timeline->filtered($request->filters()))->header('Cache-Control', 'public, max-age=600');
    }
}
