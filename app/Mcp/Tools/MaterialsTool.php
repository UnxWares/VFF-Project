<?php

namespace App\Mcp\Tools;

use App\Enums\MaterialType;
use App\Http\Resources\MaterialResource;
use App\Mcp\Support\CursorPage;
use App\Mcp\Support\VffTool;
use App\Rules\PaginationCursor;
use App\Services\Api\MaterialQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('materials')]
#[Title('Matériel roulant')]
#[Description(<<<'TXT'
Séries de matériel roulant ayant circulé en France (locomotives à vapeur, diesel, électriques, automotrices, autorails, rames TGV, voitures, wagons, tramways, engins de service), filtrables et triables. Mêmes données et mêmes filtres que l'API REST GET /api/v1/materials ; fiche détaillée avec les lignes parcourues : get_material.
Chaque série : `slug` (pour get_material), `name`, `type` et `type_label`, `designations` (autres noms), `builder`/`builders`, `operators` (exploitants avec années), `in_service_from`/`in_service_to` (années ; in_service_to nul ne prouve pas que la série circule encore), `units_built`, `max_speed_kmh` (km/h), `power_kw` (kW), `electrification`, `gauge_mm` (mm), `wheel_arrangement`, `image` (avec son attribution Commons, à afficher avec l'image), liens Wikidata et Wikipédia, `lines_count`, `provenance`. Un champ nul veut dire que la source ne le renseigne pas : VFF n'estime aucune valeur. Source : Wikidata (CC0), images Wikimedia Commons (licence par fichier).
Filtres : `q` (nom, autres noms, constructeur, numéros d'engins ; sans accents), `type`, `builder`, `operator` (identifiant Wikidata exact ou nom partiel), `line` (slug : séries attestées sur la ligne), `in_service_at` (année : en service cette année-là), `from_year`/`to_year` (mise en service), `gauge_mm`, `service_status` (`in_service` ou `withdrawn`, seulement quand une source le dit), `scope` (`france` ou `attested` : séries étrangères attestées sur une ligne française), `has_image`, `has_lines`. Tri `sort` : name, in_service_from, max_speed_kmh, units_built, lines_count, préfixe « - » pour décroissant ; inconnues en dernier.
Pagination par curseur, comme les autres outils de liste : `limit` séries par appel (1 à 50, 20 par défaut), puis `next_cursor` dans `cursor` tant que `has_more` vaut true ; une page trop volumineuse est raccourcie (`truncated`) et `next_cursor` reprend à la première série non renvoyée.
Exemple : {"type": "locomotive_electrique", "sort": "-max_speed_kmh", "limit": 5} ; {"line": "rfn-610000"} ; {"in_service_at": 1960, "q": "picasso"}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class MaterialsTool extends VffTool
{
    protected function rules(): array
    {
        return MaterialQuery::rules() + [
            'cursor' => ['sometimes', 'string', 'max:512', new PaginationCursor(MaterialQuery::CURSOR_COLUMNS)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->max(100)->description('Texte cherché dans le nom, les autres noms, les constructeurs et les numéros d\'engins, sans accents ni capitales (ex. "picasso", "15025").'),
            'type' => $schema->string()->enum(array_map(fn (MaterialType $t) => $t->value, MaterialType::cases()))->description('Catégorie de matériel.'),
            'builder' => $schema->string()->max(100)->description('Constructeur, recherche partielle sans accents (ex. "alstom").'),
            'operator' => $schema->string()->max(100)->description('Exploitant : identifiant Wikidata exact ("Q13646" = SNCF) ou nom partiel.'),
            'line' => $schema->string()->max(255)->description('Slug d\'une ligne : séries attestées sur cette ligne par une source.'),
            'in_service_at' => $schema->integer()->min(1800)->max(2100)->description('Année : séries mises en service cette année-là ou avant, et pas retirées avant.'),
            'from_year' => $schema->integer()->min(1800)->max(2100)->description('Mise en service à partir de cette année, incluse.'),
            'to_year' => $schema->integer()->min(1800)->max(2100)->description('Mise en service jusqu\'à cette année, incluse (au moins from_year).'),
            'gauge_mm' => $schema->integer()->min(500)->max(2000)->description('Écartement en mm : 1435 voie normale, 1000 voie métrique.'),
            'service_status' => $schema->string()->enum(MaterialQuery::SERVICE_STATUSES)->description('État d\'utilisation déclaré par une source : in_service (en service) ou withdrawn (retirée). Une série sans état n\'est dans aucun des deux cas.'),
            'scope' => $schema->string()->enum(MaterialQuery::SCOPES)->description('france : série rattachée à la France dans Wikidata ; attested : série non rattachée (ICE, locomotive belge…) dont la circulation sur une ligne française est attestée.'),
            'has_image' => $schema->boolean()->description('true : séries illustrées seulement ; false : sans image.'),
            'has_lines' => $schema->boolean()->description('true : séries reliées à au moins une ligne ; false : sans ligne connue.'),
            'sort' => $schema->string()->enum(array_keys(MaterialQuery::SORTS))->default('name')->description('Tri ; préfixe « - » pour l\'ordre décroissant.'),
            'cursor' => $schema->string()->max(512)->description('Curseur opaque `next_cursor` d\'un appel précédent (mêmes filtres et même tri).'),
            'limit' => $schema->integer()->min(1)->max(50)->default(20)->description('Nombre de séries par page, 1 à 50 (20 par défaut).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $filters = array_diff_key($input, array_flip(['cursor', 'limit', 'lang']));
        $page = app(MaterialQuery::class)->cursorQuery($filters)
            ->cursorPaginate((int) ($input['limit'] ?? 20), ['*'], 'cursor', CursorPage::cursor($input['cursor'] ?? null));
        $materials = MaterialResource::collection($page->items())->resolve();

        return $this->respondPage(['filters' => $filters], 'materials', $page, $materials, ['wikidata', 'commons'], $lang, self::url('/materials'));
    }
}
