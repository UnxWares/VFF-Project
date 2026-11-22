<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\CursorPage;
use App\Mcp\Support\VffTool;
use App\Rules\PaginationCursor;
use App\Services\Api\SortedCursor;
use App\Services\Api\StationDirectoryQuery;
use App\Services\StationDirectory;
use App\Support\FrenchDepartments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_stations')]
#[Title('Annuaire des gares')]
#[Description(<<<'TXT'
Annuaire filtrable des gares, haltes et points d'arrêt, actuels et disparus, avec les filtres de la page /stations (mêmes données que l'API REST GET /api/v1/stations). Points techniques et fiches retirées du réseau ferré exclus. Pour une gare précise, search puis get_station.
Chaque gare : `slug` (pour get_station), `name`, `type` (gare, halte, point_arret, passage_niveau, embranchement), `status`, `city`, `department_code`, `uic_code`, `wikidata_id`, `opened_at`/`closed_at` (ISO AAAA-MM-JJ, à lire avec `opened_precision`/`closed_precision` : day, month, year, ou range pour une ouverture ou une fermeture connue seulement par un intervalle : la date n'en est que la borne haute, jamais un jour sûr), `opened_after` (ouverte après cette année, pour une ouverture de précision range, nul sinon), `closed_after` (fermée après cette année, date exacte inconnue), `lat`/`lng` (WGS 84), `position_flagged` (position à vérifier), `sources_count`, `lines` (10 au plus, `lines_more` = les autres).
Statut sans `era` : active (voyageurs), tourist (train touristique), no_commercial (plus de voyageurs), declassified (fermée), gone (disparue selon ses sources), planned (projetée, jamais ouverte). Avec `era` (décennie 1830 à 2030) : statut à cette ère, et seules les gares existant à cette ère.
Tri : `sort` name (par défaut), city, opened, closed (valeurs manquantes en dernier) ; `order` asc ou desc. `facets: true` ajoute le nombre de gares par statut (`facets`) et par département (`department_facets`).
Territoire : `department` (code INSEE du département) ou `region` (code INSEE de la région) ; codes et noms : outil list_departments.
Pagination : `limit` gares par appel (1 à 100, 25 par défaut), puis `next_cursor` dans `cursor` tant que `has_more` vaut true ; une page trop volumineuse est raccourcie (`truncated`) et `next_cursor` reprend à la première gare non renvoyée.
Exemple : {"department": "29", "era": 1930, "sort": "opened"} ; {"q": "Morlaix"} ; {"status": "declassified", "department": "23", "facets": true}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListStationsTool extends VffTool
{
    protected function rules(): array
    {
        return StationDirectoryQuery::rules() + [
            'facets' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(SortedCursor::COLUMNS)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->max(100)->description('Texte cherché dans le nom, la commune, les autres noms, ou début du code UIC.'),
            'status' => $schema->string()->enum(StationDirectory::STATUSES)->description('Statut (avec `era` : active, tourist, no_commercial ou declassified).'),
            'department' => $schema->string()->enum(FrenchDepartments::codes())->description('Code INSEE du département de la gare, en majuscules ("29", "2A").'),
            'region' => $schema->string()->enum(FrenchDepartments::regionCodes())->description('Code INSEE de la région ("53" Bretagne, "84" Auvergne-Rhône-Alpes ; liste avec les noms : outil list_departments) : gares de ses départements.'),
            'era' => $schema->integer()->min(1830)->max(2030)->description('Année d\'une ère (décennie, ex. 1930) : statut à cette ère, gares absentes exclues.'),
            'flagged' => $schema->boolean()->description('true : seulement les gares dont la position est à vérifier.'),
            'sort' => $schema->string()->enum(StationDirectoryQuery::SORTS)->default('name')->description('Clé de tri : name (par défaut), city, opened ou closed.'),
            'order' => $schema->string()->enum(['asc', 'desc'])->default('asc')->description('Sens du tri.'),
            'facets' => $schema->boolean()->default(false)->description('true : ajouter le nombre de gares par statut (`facets`).'),
            'limit' => $schema->integer()->min(1)->max(100)->default(25)->description('Nombre de gares par appel, 1 à 100 (25 par défaut).'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent (mêmes filtres).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $service = app(StationDirectoryQuery::class);
        $filters = StationDirectoryQuery::normalize(array_diff_key($input, array_flip(['limit', 'cursor', 'facets', 'lang'])));
        $page = $service->query($filters)
            ->cursorPaginate((int) ($input['limit'] ?? 25), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $stations = array_map(fn (array $s): array => $s + ['vff_url' => self::url('/stations/'.$s['slug'])], $service->present($page->items()));

        return $this->respondPage(
            ['filters' => $filters, 'era_year' => $service->era($filters)?->year]
                + (($input['facets'] ?? false) ? ['facets' => $service->facets($filters), 'department_facets' => (object) $service->departmentFacets($filters)] : []),
            'stations', $page, $stations,
            ['wikidata', 'ohm'],
            $lang,
            self::url('/stations'),
        );
    }
}
