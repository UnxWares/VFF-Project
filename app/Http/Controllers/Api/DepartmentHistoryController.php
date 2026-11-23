<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Departments\DepartmentFigures;
use App\Support\FrenchDepartments;
use App\Support\RedistributableDates;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Territoires', "Les départements et les régions : valeurs acceptées par les filtres `department` et `region` des autres points d'accès, avec leur nom et ce que VFF recense dans chacun.", weight: 3)]
class DepartmentHistoryController extends Controller
{
    /**
     * Le rail d'un département, décennie par décennie.
     *
     * La fiche « Le rail dans… » du site pour un département, par son code (`16` pour la Charente, `2A`, `971`) :
     * `decades` (une entrée par carte décennale, de 1830 à `current_year` : `open_km` (en service), `closed_km` (fermée, voie en place), `service_km` (voie de service), `dismantled_km` (déposée), `razed_km` (emprise effacée),
     * `removed_km` et `added_km` par rapport à la carte précédente, `stations` par statut, `accidents`), `milestones`
     * (première ouverture datée, sommet du réseau, plus fortes vagues de retrait du service, réouvertures), `lines`
     * (lignes dont 1 km de voie au moins sont dans le département à l'une des ères : `state` `open`, `closed` ou `service`, et `track` `in_place`, `dismantled`, `razed` ou `none` pour une ligne fermée,
     * `km` dans le département et `km_year` de la carte qui les mesure, `opened`, `closed` et `electrified` quand un
     * événement daté passe par le département), `communes` (communes qui ont du rail, avec la page de chacune dans
     * `slug`) et `accidents` (total, morts, les plus lourds).
     *
     * **Mesures** : longueurs en kilomètres de voie, voies parallèles comprises, donc un peu au-dessus des longueurs
     * d'axe publiées par l'exploitant ; un tronçon est réparti entre départements par points échantillonnés tous les
     * 500 m d'après les contours de communes (IGN, simplifiés à 100 m), soit 250 m près par limite. `closed_share` :
     * part de la longueur ouverte en 1930, ligne par ligne, qui n'est plus ouverte aujourd'hui. Chiffres calculés par
     * `vff:compute-department-figures` et mis en cache : `computed_at` en donne la date. Les noms d'accident sont
     * dans la langue de la requête. Aucune géométrie n'est servie.
     *
     * Code inconnu ou département sans rail : 404. Chiffres pas encore calculés : 503.
     */
    #[PathParameter('department', 'Code du département (deux chiffres, `2A`, `2B` ou trois chiffres outre-mer).', example: '23')]
    #[ApiResponse(200, 'La Creuse, réponse raccourcie.', examples: [[
        'data' => [
            'department' => ['code' => '23', 'slug' => '23-creuse', 'name' => 'Creuse', 'region' => 'Nouvelle-Aquitaine'],
            'computed_at' => '2026-11-23T03:00:00+01:00', 'reference_year' => 1930, 'current_year' => 2030, 'closed_share' => 0.7,
            'decades' => [['year' => 1930, 'current' => false, 'open_km' => 700, 'closed_km' => 10, 'service_km' => 0, 'dismantled_km' => 15, 'razed_km' => 5, 'removed_km' => 5, 'added_km' => 0,
                'stations' => ['active' => 60, 'tourist' => 0, 'no_commercial' => 2, 'declassified' => 5], 'accidents' => ['count' => 1, 'deaths' => 0]]],
            'milestones' => ['first_opening' => ['date' => '1856-01-01', 'precision' => 'year', 'line' => ['slug' => 'rfn-570000', 'name' => 'Ligne de Vierzon à Saint-Germain-des-Fossés']], 'peak' => ['year' => 1930, 'km' => 700], 'closure_waves' => [], 'reopenings' => []],
            'lines' => [['slug' => 'rfn-570000', 'name' => 'Ligne de Vierzon à Saint-Germain-des-Fossés', 'code' => '570000', 'is_lgv' => false, 'state' => 'open', 'track' => null, 'km' => 12.4, 'km_year' => 2030, 'first_year' => 1860, 'last_year' => 2030, 'opened' => null, 'closed' => null, 'electrified' => null]],
            'communes' => [['insee' => '23096', 'slug' => '23096-gueret', 'name' => 'Guéret', 'population' => 13000, 'stations' => 1, 'stations_open' => 1]],
            'accidents' => ['count' => 1, 'deaths' => 0, 'limit' => 10, 'list' => []],
            'links' => ['page' => 'https://vff-project.org/departments/23-creuse'],
        ],
    ]])]
    #[ApiResponse(404, 'Code inconnu, ou département sans rail.', examples: [['message' => 'Unknown department.']])]
    #[ApiResponse(503, 'Chiffres pas encore calculés.', examples: [['message' => 'Department figures not computed yet.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(string $department, DepartmentFigures $figures): JsonResponse
    {
        $code = strtoupper($department);
        abort_unless(isset(FrenchDepartments::ALL[$code]), 404, 'Unknown department.');
        if ($figures->index() === null) {
            abort(503, 'Department figures not computed yet.');
        }
        $sheet = $figures->sheet($code, app()->getLocale());
        abort_if($sheet === null, 404, 'Unknown department.');
        unset($sheet['map'], $sheet['summary']);
        // Dates sans source redistribuable : remplacées par celles d'une source servie, ou nulles.
        $sheet = app(RedistributableDates::class)->departmentSheet($sheet);
        $sheet['links'] = ['page' => route('departments.show', $sheet['department']['slug'])];

        return response()->json(['data' => $sheet])->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * Tous les départements d'un coup d'œil.
     *
     * Une entrée par département qui a du rail, dans l'ordre du code officiel : `km_reference` (longueur ouverte
     * en 1930) et `km_today`, `km_peak` et `peak_year`, `closed_share` (part de la longueur ouverte en 1930 qui ne
     * l'est plus aujourd'hui, de 0 à 1), gares ouvertes en 1930 et aujourd'hui, nombre de lignes et de lignes en
     * service, accidents. Mesures et limites : voir `/departments/{department}/history`.
     */
    #[ApiResponse(200, 'Deux départements, liste raccourcie.', examples: [[
        'data' => [['code' => '16', 'slug' => '16-charente', 'name' => 'Charente', 'region' => 'Nouvelle-Aquitaine', 'km_reference' => 800, 'km_today' => 300, 'km_peak' => 820, 'peak_year' => 1920, 'closed_share' => 0.6, 'stations_reference' => 70, 'stations_today' => 20, 'lines' => 30, 'lines_open' => 8, 'accidents' => 12, 'links' => ['page' => 'https://vff-project.org/departments/16-charente', 'history' => 'https://vff-project.org/api/v1/departments/16/history']]],
        'meta' => ['computed_at' => '2026-11-23T03:00:00+01:00', 'reference_year' => 1930, 'current_year' => 2030],
    ]])]
    #[ApiResponse(503, 'Chiffres pas encore calculés.', examples: [['message' => 'Department figures not computed yet.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(DepartmentFigures $figures): JsonResponse
    {
        $index = $figures->index();
        if ($index === null) {
            abort(503, 'Department figures not computed yet.');
        }
        $rows = array_map(fn (array $d): array => $d + ['links' => [
            'page' => route('departments.show', $d['slug']),
            'history' => route('api.departments.history', $d['code']),
        ]], $index['departments']);

        return response()->json(['data' => $rows, 'meta' => [
            'computed_at' => $index['computed_at'], 'reference_year' => $index['reference_year'], 'current_year' => $index['current_year'],
        ]])->header('Cache-Control', 'public, max-age=600');
    }
}
