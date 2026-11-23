<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AccidentStatisticsRequest;
use App\Services\Accidents\AccidentStatistics;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Accidents', "Les accidents et événements graves du rail français : date et précision, lieu, ligne et portion à l'époque, bilan humain et sa source, résumé, sources ouvertes avec leur licence.", weight: 7)]
class AccidentStatisticsController extends Controller
{
    /**
     * Les accidents en chiffres.
     *
     * Les agrégats de la page « Les accidents en chiffres » : accidents et victimes par décennie depuis le plus ancien
     * connu, ventilation par nature, part des accidents de passage à niveau, bilans les plus lourds. Mêmes accidents
     * que `GET /api/v1/accidents` (sans doublons ni heurts de personnes non confirmés) ; gardés en cache une heure.
     *
     * - `totals` : `accidents`, dont `to_review` (fiches dont un point reste à vérifier) ; `deaths` et `injuries` sont
     *   des sommes des bilans CONNUS, `deaths_known` et `injuries_known` donnent le nombre d'accidents au bilan connu
     *   (aucune valeur n'est estimée) ; `first_date` et `last_date` bornent la période couverte ;
     * - `decades` : une ligne par décennie (`decade`, `from_year`, `to_year`) avec les mêmes compteurs, `all_kinds`
     *   (accidents de toutes natures, quel que soit le filtre `kind`), `level_crossing` et `level_crossing_share`
     *   (part des passages à niveau parmi tous les accidents de la décennie, entre 0 et 1) ;
     * - `kinds` : une ligne par nature (toutes natures, quel que soit le filtre `kind`), de la plus fréquente à la moins fréquente ;
     * - `deadliest` : les quinze bilans les plus lourds, avec `slug` (fiche : `/accidents/{slug}`), noms par langue,
     *   date, nature, lieu, bilan et état de relecture.
     *
     * **Couverture inégale** : le nombre d'accidents suit autant les sources disponibles que la réalité (presse
     * ancienne, enquêtes officielles depuis 2004) ; il ne mesure pas la sécurité du réseau d'une époque à l'autre.
     * Par défaut les fiches « à vérifier » sont comptées ; `review=ok` ne garde que les fiches confirmées.
     */
    #[QueryParameter('from', 'Accidents survenus à partir de cette année, incluse (1800 à 2100).', type: 'int', example: 1900)]
    #[QueryParameter('to', 'Accidents survenus jusqu\'à cette année, incluse (1800 à 2100, au moins égale à `from`).', type: 'int', example: 1999)]
    #[QueryParameter('kind', 'Nature : `collision`, `derailment`, `level_crossing`, `fire`, `explosion`, `structure_failure`, `attack`, `war_action`, `person_struck`, `runaway`, `incident`, `other`. Filtre les décennies et les bilans les plus lourds ; `kinds` et la part des passages à niveau restent sur toutes les natures.', type: 'string', example: 'derailment')]
    #[QueryParameter('review', '`ok` : seulement les fiches dont la date et le lieu sont confirmés ; absent : toutes, fiches « à vérifier » comprises.', type: 'string', example: 'ok')]
    #[ApiResponse(200, 'Les chiffres des accidents (extrait).', examples: [[
        'data' => [
            'computed_at' => '2026-11-23T08:00:00+01:00',
            'filters' => ['from' => null, 'to' => null, 'kind' => null, 'review' => null],
            'totals' => ['accidents' => 925, 'to_review' => 787, 'deaths' => 4450, 'deaths_known' => 645, 'injuries' => 8500, 'injuries_known' => 600, 'first_date' => '1833-11-24', 'last_date' => '2026-09-11'],
            'decades' => [[
                'decade' => 1980, 'from_year' => 1980, 'to_year' => 1989,
                'accidents' => 21, 'to_review' => 19, 'deaths' => 181, 'deaths_known' => 14, 'injuries' => 649, 'injuries_known' => 14,
                'all_kinds' => 21, 'level_crossing' => 2, 'level_crossing_share' => 0.095,
            ]],
            'kinds' => [['kind' => 'collision', 'accidents' => 349, 'to_review' => 300, 'deaths' => 2344, 'deaths_known' => 250, 'injuries' => 4000, 'injuries_known' => 220]],
            'deadliest' => [[
                'slug' => '1917-12-12-saint-michel-de-maurienne',
                'name' => 'Accident ferroviaire de Saint-Michel-de-Maurienne',
                'names' => ['fr' => 'Accident ferroviaire de Saint-Michel-de-Maurienne'],
                'date' => '1917-12-12', 'kind' => 'derailment', 'commune' => 'Saint-Michel-de-Maurienne', 'department_code' => '73',
                'deaths' => 435, 'injuries' => null, 'review_status' => 'ok',
            ]],
        ],
        'links' => ['web' => 'https://vff-project.org/accidents/statistics', 'accidents' => 'https://vff-project.org/api/v1/accidents'],
    ]])]
    #[ApiResponse(422, 'Paramètre invalide (nature ou état inconnus, année hors bornes, `to` antérieure à `from`) : détail par paramètre dans `errors`.', examples: [AccidentExamples::MAP_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(AccidentStatisticsRequest $request, AccidentStatistics $statistics): JsonResponse
    {
        return response()->json([
            'data' => $statistics->get($request->filters()),
            'links' => ['web' => route('accidents.statistics'), 'accidents' => route('api.accidents.index')],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
