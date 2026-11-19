<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListStationsRequest;
use App\Http\Resources\StationSheetResource;
use App\Models\Station;
use App\Services\Api\SortedCursor;
use App\Services\Api\StationDirectoryQuery;
use App\Services\StationSheet;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

#[Group('Gares', "L'annuaire des gares, haltes et points d'arrêt, actuels et disparus, et la fiche complète de chaque gare : dates, présence par décennie, lignes desservies, sources.", weight: 2)]
class StationController extends Controller
{
    /**
     * Annuaire des gares.
     *
     * Les gares, haltes et points d'arrêt de l'encyclopédie, actuels et
     * disparus, avec les filtres de la page [/stations](/stations), triés et
     * paginés par curseur. Les points techniques (postes, garages, points
     * d'une LGV) et les fiches retirées du réseau ferré par une correction
     * sourcée (tramway, petit train d'un parc) n'y figurent pas.
     *
     * **Statut** (`status`) : sans `era`, le statut affiché aujourd'hui :
     * `active` (desservie par des trains de voyageurs), `tourist` (train
     * touristique), `no_commercial` (plus de voyageurs : fret ou service),
     * `declassified` (fermée), `gone` (disparue selon ses sources),
     * `planned` (projetée, jamais ouverte). Avec `era`, le statut **à cette
     * ère** (`active`, `tourist`, `no_commercial`, `declassified`), et seules
     * les gares qui existaient à cette ère sont listées.
     *
     * **Dates** : `opened_at` et `closed_at` au format `AAAA-MM-JJ`, à lire
     * avec `opened_precision` / `closed_precision` (`day`, `month`, `year`) ;
     * `closed_after` : gare fermée après cette année, date exacte inconnue.
     * **Nature** (`type`) : `gare`, `halte`, `point_arret`, `passage_niveau`,
     * `embranchement`. `lines` : lignes rattachées à la gare (10 au plus,
     * codes SNCF Réseau d'abord ; `lines_more` compte les autres).
     * `position_flagged` : position à vérifier (sources éloignées les unes
     * des autres) ; `sources_count` : nombre de sources rattachées.
     *
     * **Tri** : `sort=name` (par défaut, alphabétique français), `city`
     * (commune), `opened` (date d'ouverture), `closed` (date de fermeture) ;
     * les gares sans valeur pour la clé de tri viennent toujours en dernier ;
     * `order=desc` inverse l'ordre. **Pagination par curseur** : `per_page`
     * (1 à 100) et `cursor`. **Compteurs** : `facets=true` ajoute
     * `meta.facets`, le nombre de gares par statut pour la recherche en
     * cours (sans le filtre `status`).
     *
     * Données : SNCF Open Data (ODbL), OpenStreetMap (ODbL), Wikidata (CC0),
     * OpenHistoricalMap (CC0), relevés des lignes disparues (voir `/sources`).
     */
    #[QueryParameter('q', 'Recherche littérale dans le nom, la commune ou les autres noms, ou début du code UIC, casse ignorée, 100 caractères au plus.', type: 'string', example: 'Morlaix')]
    #[QueryParameter('status', 'Statut : `active`, `tourist`, `no_commercial`, `declassified`, `gone` ou `planned` (avec `era` : les quatre premiers). Une autre valeur donne HTTP 422.', type: 'string', example: 'declassified')]
    #[QueryParameter('department', 'Code INSEE du département de la gare (`01` à `95`, `2A`, `2B`, `971` à `976`), minuscules acceptées.', type: 'string', example: '29')]
    #[QueryParameter('era', "Année d'une ère (décennie de 1830 à 2030) : statut à cette ère, gares absentes de l'ère exclues. Une année sans ère donne HTTP 422.", type: 'int', example: 1930)]
    #[QueryParameter('flagged', 'Seulement les gares dont la position est à vérifier : `true` ou `false`.', type: 'boolean', example: false)]
    #[QueryParameter('sort', 'Clé de tri : `name` (par défaut), `city`, `opened` ou `closed`.', type: 'string', default: 'name', example: 'opened')]
    #[QueryParameter('order', 'Sens du tri : `asc` (par défaut) ou `desc`.', type: 'string', default: 'asc', example: 'asc')]
    #[QueryParameter('facets', 'Ajouter le nombre de gares par statut dans `meta.facets` : `true` ou `false` (par défaut).', type: 'boolean', default: false, example: true)]
    #[QueryParameter('per_page', 'Nombre de gares par page, de 1 à 100.', type: 'int', default: 25, example: 25)]
    #[QueryParameter('cursor', 'Curseur de la page voulue, tel que renvoyé par `meta.next_cursor` : une chaîne opaque, à ne pas fabriquer.', type: 'string', example: 'eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiQnJlc3QiLCJpZCI6MTYzODIsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0')]
    #[ApiResponse(200, 'Une page de l\'annuaire (ici `department=29&era=1930&sort=opened&order=desc&per_page=1&facets=true`).', examples: [[
        'data' => [[
            'slug' => 'camaret-sur-mer', 'name' => 'Camaret-sur-Mer', 'type' => 'gare', 'status' => 'active', 'city' => 'Camaret-sur-Mer',
            'department_code' => '29', 'uic_code' => null, 'wikidata_id' => null,
            'opened_at' => '1923-08-12', 'opened_precision' => 'day', 'closed_at' => '1967-04-10', 'closed_precision' => 'day', 'closed_after' => null,
            'lat' => 48.2732755, 'lng' => -4.5948215, 'position_flagged' => false, 'sources_count' => 1,
            'lines' => [['slug' => 'archeo-ligne-de-chateaulin-a-camaret', 'code' => null, 'name' => 'Ligne de Châteaulin à Camaret']], 'lines_more' => 0,
            'links' => ['web' => 'https://vff-project.org/stations/camaret-sur-mer', 'self' => 'https://vff-project.org/api/v1/stations/camaret-sur-mer'],
        ]],
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => 'https://vff-project.org/api/v1/stations?department=29&era=1930&sort=opened&order=desc&per_page=1&facets=true&cursor=eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiMTkyMy0wOC0xMiIsImlkIjo0MjAxMywiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ'],
        'meta' => ['path' => 'https://vff-project.org/api/v1/stations', 'per_page' => 1, 'next_cursor' => 'eyJzb3J0X251bGwiOjAsInNvcnRfa2V5IjoiMTkyMy0wOC0xMiIsImlkIjo0MjAxMywiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ', 'prev_cursor' => null, 'era_year' => 1930, 'facets' => ['active' => 65]],
    ]])]
    #[ApiResponse(422, 'Filtre, tri ou curseur invalide.', examples: [ErrorExamples::STATIONS_INVALID])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function index(ListStationsRequest $request, StationDirectoryQuery $query): JsonResponse
    {
        $filters = $request->filters();
        $page = $query->query($filters)->cursorPaginate($request->perPage())->withQueryString();
        $data = array_map(fn (array $s): array => $s + [
            'links' => ['web' => url('/stations/'.$s['slug']), 'self' => route('api.stations.show', $s['slug'])],
        ], $query->present($page->items()));

        return response()->json(SortedCursor::envelope($page, $data, array_filter([
            'era_year' => $query->era($filters)?->year,
            'facets' => $request->wantsFacets() ? $query->facets($filters) : null,
        ], fn ($v) => $v !== null)))->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Fiche complète d'une gare.
     *
     * Tout ce que montre la page [/stations/{slug}](/stations/brest) :
     *
     * - identité : `name`, `alt_names`, `type`, `status` (statut affiché, voir
     *   l'annuaire ; `technical` point technique, `excluded` fiche retirée du
     *   réseau ferré), `source_status` (statut selon les sources),
     *   `lifecycle`, `city`, `department_code`, `uic_code`, `wikidata_id`,
     *   `description` (en français) ;
     * - dates : `opened_at`, `closed_at` (`AAAA-MM-JJ`) et leur précision
     *   (`day`, `month`, `year`), `closed_after` (fermée après cette année) ;
     * - position : `lat`, `lng` (WGS 84) ; `position.source` (source retenue),
     *   `position.spread_m` (écart en mètres entre les sources),
     *   `position.flagged` (à vérifier) ; `attribute_sources` : source de
     *   chaque attribut (`name`, `opened_at`, `geom`…) et détail du recalage ;
     * - `eras` : statut à chaque décennie (`null` : la gare n'existe pas
     *   encore ou plus ; `uncertain` : ouverte selon ses sources, non
     *   confirmée par les voies de l'ère), avec une note en français ;
     * - `events` : chronologie (`type`, `date`, `precision`, `source`,
     *   `source_url`, `inferred` : date déduite, `note`, `provenance`) ; la
     *   liste paginée et filtrable est sur `/stations/{station}/events` ;
     * - `lines` : lignes desservies, avec `pk_m` (point kilométrique en
     *   mètres) et sa source, `method` (comment la gare est rattachée),
     *   `first_year`/`last_year` (années de desserte), `never_served`
     *   (`line_closed`, `station_closed` ou `null`), `active_current`
     *   (desservie par une portion en service aujourd'hui), `era_years`
     *   (décennies où la gare est un arrêt de la ligne) ;
     * - `sources` : chaque source rattachée, avec son nom, ses dates, son
     *   écart à la position retenue (`distance_m`), la desserte déclarée
     *   (`passenger`, `freight`), son lien `url` et sa `provenance`
     *   (fournisseur, licence) ;
     * - `neighbors` : gares à moins de 10 km (12 au plus), de la plus proche
     *   à la plus lointaine.
     */
    #[PathParameter('station', 'Identifiant (slug) de la gare, tel que renvoyé par l\'annuaire ou la recherche.', example: 'brest')]
    #[ApiResponse(200, 'Fiche de la gare de Brest (listes raccourcies).', examples: [StationSheetExample::BODY])]
    #[ApiResponse(404, 'Gare inconnue.', examples: [ErrorExamples::STATION_NOT_FOUND])]
    #[ApiResponse(429, ErrorExamples::TOO_MANY_REQUESTS, examples: [ErrorExamples::TOO_MANY_REQUESTS_BODY])]
    public function show(Station $station, StationSheet $sheet): JsonResponse
    {
        return StationSheetResource::make($sheet->page($station))->response()->header('Cache-Control', 'public, max-age=300');
    }
}
