<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Services\CatalogSearch;
use App\Services\SearchResultsPage;
use App\Support\FrenchDepartments;
use App\Support\Search\SearchQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page de résultats de la recherche (/search?q=…), atteinte par Entrée dans la recherche de l'en-tête et de
 * l'accueil, ou par « Voir tous les résultats » : lignes, gares, accidents et matériel roulant (SearchResultsPage).
 *
 * Paramètres (une valeur inconnue est ignorée, comme dans les annuaires) : `q`, `type` (line, station, accident,
 * material ; tout sinon), `state` (open, closed) et `dept` (code de département) pour les lignes et les gares,
 * `page`. Les pages de résultats ne sont pas indexées (`X-Robots-Tag: noindex`) : leur adresse se partage, mais
 * leur contenu est déjà celui des fiches.
 */
class SearchPageController extends Controller
{
    use ReadsListFilters;

    public function index(Request $request, SearchResultsPage $search): Response
    {
        $q = mb_substr(CatalogSearch::normalizeQuery($request->query('q')), 0, SearchQuery::MAX_LENGTH);
        $type = $this->filterString($request, 'type');
        $type = in_array($type, SearchResultsPage::TYPES, true) ? $type : '';
        $filterable = in_array($type, SearchResultsPage::FILTERABLE, true);
        $state = $this->filterString($request, 'state');
        $state = $filterable && in_array($state, SearchResultsPage::STATES, true) ? $state : '';
        $dept = strtoupper($this->filterString($request, 'dept', trim: true));
        $dept = $filterable && array_key_exists($dept, FrenchDepartments::ALL) ? $dept : '';
        $page = max(1, $request->integer('page', 1));

        $searched = mb_strlen($q) >= CatalogSearch::MIN_LENGTH;
        $results = $searched ? $search->results($q, $type, $state, $dept, $page) : null;

        $response = Inertia::render('Search/Index', [
            'query' => $q,
            'searched' => $searched,
            'filters' => [
                'type' => $type,
                'state' => $state,
                'dept' => $dept,
            ],
            'groups' => $results['groups'] ?? [],
            'counts' => $results['counts'] ?? array_fill_keys(SearchResultsPage::TYPES, 0),
            'total' => $results['total'] ?? 0,
            'approximate' => $results['approximate'] ?? false,
            'suggestion' => $results['suggestion'] ?? null,
            'departments' => $results['departments'] ?? [],
        ])->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
