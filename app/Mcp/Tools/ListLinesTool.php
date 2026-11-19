<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\CursorPage;
use App\Mcp\Support\VffTool;
use App\Models\Era;
use App\Rules\PaginationCursor;
use App\Services\Api\LineDirectoryQuery;
use App\Services\Api\SortedCursor;
use App\Services\LineDirectory;
use App\Support\FrenchDepartments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_lines')]
#[Title('Annuaire des lignes')]
#[Description(<<<'TXT'
Annuaire filtrable des lignes, actuelles et disparues, avec les filtres de la page /lines (mêmes données que l'API REST GET /api/v1/lines). Pour trouver une ligne par son nom, search est plus direct ; cet outil répond aux listes (« lignes fermées du Finistère », « lignes à trafic fret seul en 1930 »).
Chaque ligne : `slug` (pour get_line), `code` SNCF Réseau, `name`, `network` (principal, secondaire, militaire, particulier, null), `historical_operator`, `regions`, `departements`, et à l'ère de référence : `state` (open = au moins une portion en service ; closed = tracé connu, plus rien en service ; unknown = aucun tracé à cette ère), `traffic` dominant (passenger, mixed, freight, unknown ; null si rien en service), `heritage` (portion associative).
L'ère de référence est l'ère courante (aujourd'hui) ou `era` (décennie 1830 à 2030) ; elle vaut pour `status`, `traffic` et l'état renvoyé (`era_year`).
Tri : `sort` name (alphabétique français, par défaut), code (lignes sans code en dernier), slug ; `order` asc ou desc. `facets: true` ajoute les compteurs de la recherche (total, open, closed, unknown, par réseau, par trafic, associatif, par département).
Territoire : `department` (code INSEE du département) ou `region` (code INSEE de la région) ; codes et noms : outil list_departments.
Pagination : `limit` lignes par appel (1 à 100, 25 par défaut), puis `next_cursor` dans `cursor` tant que `has_more` vaut true ; une page trop volumineuse est raccourcie (`truncated`) et `next_cursor` reprend à la première ligne non renvoyée.
Exemple : {"department": "29", "status": "closed"} ; {"traffic": "freight", "era": 1930, "facets": true}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListLinesTool extends VffTool
{
    protected function rules(): array
    {
        return LineDirectoryQuery::rules() + [
            'facets' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(SortedCursor::COLUMNS)],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->max(100)->description('Texte cherché dans le nom, le code ou la compagnie d\'origine (casse ignorée).'),
            'network' => $schema->string()->enum([...LineDirectory::NETWORKS, 'none'])->description('Réseau ; none = non renseigné.'),
            'status' => $schema->string()->enum(['open', 'closed'])->description('État à l\'ère de référence.'),
            'traffic' => $schema->string()->enum(LineDirectory::TRAFFICS)->description('Trafic d\'au moins une portion en service à l\'ère de référence.'),
            'heritage' => $schema->boolean()->description('true : seulement les lignes avec une portion associative (train touristique, vélorail).'),
            'department' => $schema->string()->enum(FrenchDepartments::codes())->description('Code INSEE du département en majuscules ("29", "2A") : lignes citant ce département ou y desservant une gare.'),
            'region' => $schema->string()->enum(FrenchDepartments::regionCodes())->description('Code INSEE de la région ("53" Bretagne, "84" Auvergne-Rhône-Alpes ; liste avec les noms : outil list_departments) : lignes citant l\'un de ses départements ou y desservant une gare.'),
            'era' => $schema->integer()->min(1830)->max(2030)->description('Année d\'une ère (décennie, ex. 1930) servant de référence. Par défaut : aujourd\'hui.'),
            'sort' => $schema->string()->enum(LineDirectoryQuery::SORTS)->default('name')->description('Clé de tri : name (par défaut), code ou slug.'),
            'order' => $schema->string()->enum(['asc', 'desc'])->default('asc')->description('Sens du tri.'),
            'facets' => $schema->boolean()->default(false)->description('true : ajouter les compteurs de la recherche (`facets`).'),
            'limit' => $schema->integer()->min(1)->max(100)->default(25)->description('Nombre de lignes par appel, 1 à 100 (25 par défaut).'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent (mêmes filtres).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $service = app(LineDirectoryQuery::class);
        $filters = LineDirectoryQuery::normalize(array_diff_key($input, array_flip(['limit', 'cursor', 'facets', 'lang'])));
        $page = $service->query($filters)
            ->cursorPaginate((int) ($input['limit'] ?? 25), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $lines = array_map(fn (array $l): array => $l + ['vff_url' => self::url('/lines/'.$l['slug'])], $service->present($page->items(), $filters));

        return $this->respondPage(
            ['filters' => $filters, 'era_year' => Era::whereKey($service->eraId($filters))->value('year')]
                + (($input['facets'] ?? false) ? ['facets' => $service->facets($filters)] : []),
            'lines', $page, $lines,
            ['wikidata'],
            $lang,
            self::url('/lines'),
        );
    }
}
