<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Companies\CompanyFigures;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Compagnies', 'Les compagnies de chemins de fer historiques (Nord, Est, Ouest, Paris-Orléans, PLM, Midi, État, Alsace-Lorraine, SNCF, réseaux secondaires) : dates, siège, lignes rattachées par un événement daté et réseau par décennie.', weight: 4)]
class CompanyController extends Controller
{
    /**
     * Les compagnies de chemins de fer historiques.
     *
     * Une entrée par compagnie : `slug`, `kind` (`grande-compagnie`, `etat`, `sncf`, `infrastructure` pour Réseau ferré de France et SNCF Réseau,
     * `secondaire`), nom officiel et appellation courante, `founded` et `ended` (date et précision `day` ou `year`, `ended` null tant que la compagnie existe),
     * `seat`, nombre de lignes rattachées (`lines`, dont `lines_counted` dont la fin du rattachement est connue), `km_peak` kilomètres de voie ouverte
     * à l'ère de plus grande extension (`peak_year`) et `km_today`. Les lignes ne sont rattachées à une compagnie que par un événement daté
     * de concession sourcé (infobox de l'article de la ligne) : jamais deviné. `meta.coverage` donne, par ère, la part des kilomètres ouverts
     * qui sont rattachés à au moins une compagnie. Chiffres calculés par `vff:compute-company-figures` et mis en cache.
     */
    #[ApiResponse(200, 'Deux compagnies, liste raccourcie.', examples: [[
        'data' => [['slug' => 'nord', 'kind' => 'grande-compagnie', 'name' => 'Compagnie des chemins de fer du Nord', 'short' => 'Nord', 'wikidata' => 'Q541879',
            'founded' => ['date' => '1845-09-20', 'precision' => 'day'], 'ended' => ['date' => '1937-12-31', 'precision' => 'day'], 'seat' => 'Paris',
            'summary' => 'Compagnie du nord de la France.', 'lines' => 69, 'lines_counted' => 69, 'km_peak' => 3046, 'peak_year' => 1920, 'km_today' => 0, 'lines_open_today' => 0,
            'links' => ['page' => 'https://vff-project.org/companies/nord', 'detail' => 'https://vff-project.org/api/v1/companies/nord']]],
        'meta' => ['computed_at' => '2026-11-23T05:00:00+01:00', 'current_year' => 2030, 'coverage' => [['year' => 1930, 'current' => false, 'lines_open' => 1392, 'lines_attached' => 545, 'km_open' => 60533, 'km_attached' => 34817, 'share' => 0.575]]],
    ]])]
    #[ApiResponse(503, 'Chiffres pas encore calculés.', examples: [['message' => 'Company figures not computed yet.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(CompanyFigures $figures): JsonResponse
    {
        $index = $figures->index(app()->getLocale());
        if ($index === null) {
            abort(503, 'Company figures not computed yet.');
        }
        $rows = array_map(fn (array $c): array => $c + ['links' => [
            'page' => route('companies.show', $c['slug']),
            'detail' => route('api.companies.show', $c['slug']),
        ]], $index['companies']);

        return response()->json(['data' => $rows, 'meta' => [
            'computed_at' => $index['computed_at'], 'current_year' => $index['current_year'], 'coverage' => $index['coverage'],
        ]])->header('Cache-Control', 'public, max-age=600');
    }

    /**
     * La fiche d'une compagnie de chemins de fer.
     *
     * Dates et événements de la compagnie (`events`, avec leurs `sources`), `predecessors` et `successors`, `decades` (une entrée par carte décennale
     * de 1830 à `current_year` : `lines` rattachées et en service, `km` de voie ouverte, voies parallèles comprises), `peak`, et `lines` : les lignes
     * rattachées, chacune avec `from_year` (l'année de l'événement de concession, `from_date` et `from_precision`), `to_year` et `to_basis` (`event` : un événement
     * de fin de concession daté ; `successor` : la concession suivante de la ligne à une autre compagnie d'exploitation ; `company_end` : la fin de la compagnie ;
     * `open` : sans fin ; `unknown` : fin inconnue, la ligne n'est alors pas comptée par décennie), `km` à l'ère de plus grande extension et `open_today`.
     * `map` donne le tracé simplifié du réseau à cette ère (`paths` en longitude et latitude). Les lignes sans événement de concession sourcé ne sont pas listées.
     *
     * **Mesures** : kilomètres de voie ouverte (« en service » ou « voie unique »), voies parallèles comprises, des lignes rattachées à l'ère ; un rattachement
     * vaut de l'année de l'événement jusqu'à l'année de fin exclue. Une ligne rattachée à deux compagnies une même ère est comptée dans chacune.
     * Chiffres calculés par `vff:compute-company-figures` et mis en cache. Textes dans la langue de la requête.
     *
     * Compagnie inconnue : 404. Chiffres pas encore calculés : 503.
     */
    #[PathParameter('company', 'Adresse de la compagnie (`nord`, `est`, `ouest`, `paris-orleans`, `plm`, `midi`, `etat`, `alsace-lorraine`, `sncf`, `rff`, `sncf-reseau`, `grand-central`, `economiques`, `cfd`).', example: 'nord')]
    #[ApiResponse(200, 'Le Nord, réponse raccourcie.', examples: [[
        'data' => [
            'company' => ['slug' => 'nord', 'kind' => 'grande-compagnie', 'name' => 'Compagnie des chemins de fer du Nord', 'short' => 'Nord', 'wikidata' => 'Q541879',
                'founded' => ['date' => '1845-09-20', 'precision' => 'day'], 'ended' => ['date' => '1937-12-31', 'precision' => 'day'], 'seat' => 'Paris', 'summary' => 'Compagnie du nord de la France.'],
            'computed_at' => '2026-11-23T05:00:00+01:00', 'current_year' => 2030,
            'events' => [['date' => '1845-09-20', 'precision' => 'day', 'text' => 'Création de la compagnie.']],
            'sources' => [['label' => 'Wikipédia', 'url' => 'https://fr.wikipedia.org/wiki/Compagnie_des_chemins_de_fer_du_Nord']],
            'predecessors' => [], 'successors' => [['slug' => 'sncf', 'short' => 'SNCF']],
            'decades' => [['year' => 1920, 'current' => false, 'lines' => 60, 'km' => 3046]],
            'peak' => ['year' => 1920, 'current' => false, 'lines' => 60, 'km' => 3046], 'first_year' => 1850,
            'lines' => [['slug' => 'rfn-001000', 'name' => 'Ligne de Paris-Nord à Lille', 'code' => '001 000', 'is_lgv' => false, 'from_year' => 1845, 'to_year' => 1937, 'to_basis' => 'event', 'from_date' => '1845-01-01', 'from_precision' => 'year', 'km' => 250.0, 'open_today' => false, 'event_id' => 1]],
            'map' => null,
            'links' => ['page' => 'https://vff-project.org/companies/nord'],
        ],
    ]])]
    #[ApiResponse(404, 'Compagnie inconnue.', examples: [['message' => 'Unknown company.']])]
    #[ApiResponse(503, 'Chiffres pas encore calculés.', examples: [['message' => 'Company figures not computed yet.']])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(string $company, CompanyFigures $figures): JsonResponse
    {
        abort_unless(preg_match('/^[a-z0-9-]{2,40}$/', $company) === 1, 404, 'Unknown company.');
        if ($figures->index() === null) {
            abort(503, 'Company figures not computed yet.');
        }
        $sheet = $figures->sheet($company, app()->getLocale());
        abort_if($sheet === null, 404, 'Unknown company.');
        unset($sheet['summary'], $sheet['map_info']);
        $sheet['map'] = $figures->map($company);
        $sheet['links'] = ['page' => route('companies.show', $sheet['company']['slug'])];

        return response()->json(['data' => $sheet])->header('Cache-Control', 'public, max-age=600');
    }
}
