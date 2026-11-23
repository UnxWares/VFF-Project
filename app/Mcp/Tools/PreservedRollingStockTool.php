<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\VffTool;
use App\Models\Material;
use App\Services\Materials\PreservedRollingStock;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('preserved_rolling_stock')]
#[Title('Matériel roulant préservé : exemplaires, lieux, protections')]
#[Description(<<<'TXT'
Matériel roulant conservé que des sources citent : où le voir, dans quel état, avec quelle protection au titre des monuments historiques. Mêmes données que l'API REST GET /api/v1/materials/preserved et GET /api/v1/materials/{material}/preserved, et la page /materials/preserved (service PreservedRollingStock).
Avec `material` (slug d'une série, renvoyé par search ou materials) : `units`, les exemplaires préservés de la série. Sans `material` : `venues`, les lieux (musées, associations, lignes touristiques, sites) avec leurs pièces (`pieces`), du plus riche au moins riche ; chaque pièce donne `series` (séries VFF auxquelles son numéro se rattache, vide pour du matériel à voie étroite, des wagons ou des voitures) et `title` (désignation de la notice Palissy pour ces pièces).
Chaque exemplaire ou pièce : `number` (tel que la source l'écrit), `venue` (lieu : `slug`, `name`, `type` museum, association, tourist_railway ou site, `commune`, `tourist_railway` avec l'adresse de la ligne touristique sur le site quand elle y figure), `where` (lieu dit par la source quand VFF n'en a pas fait une fiche de lieu), `state` (running en état de marche, exposed exposé, restoration en restauration, stored garé ou en réserve ; null quand la source ne le dit pas, jamais deviné), `protection` (monument historique : `kind` classified, listed ou declassified, `date` de la notice Palissy, `year` quand seule l'année est connue, `ref` et `url` de la notice ; null sans protection connue) et `sources` (Wikipédia CC BY-SA 4.0, base Palissy Licence Ouverte, Wikidata CC0, chacune avec sa licence et le lien de la version lue).
Couverture : la liste est celle des sources, pas un inventaire. Une série absente n'a pas d'exemplaire préservé connu de VFF ; un exemplaire absent n'est pas démoli pour autant ; un exemplaire que la source dit ferraillé n'est pas repris. L'état est celui que rapporte la source à sa date de lecture. `figures` donne la couverture (exemplaires, séries, lieux, protégés, exemplaires au lieu connu, objets protégés sans série).
Filtres : `venue_type`, `venue` (slug d'un lieu, sans `material`), `state`, `protected` (true : seulement les pièces classées ou inscrites, pas les déclassées). Pagination : `limit` et `offset` sur les exemplaires ou les lieux, `total` donne le nombre qui correspond.
Exemples : {"material": "241-p"} ; {"venue_type": "museum", "limit": 5} ; {"venue": "cite-du-train"} ; {"state": "running", "protected": true}.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class PreservedRollingStockTool extends VffTool
{
    private const MAX_LIMIT = 50;

    /** Protections en vigueur (une pièce déclassée n'est plus protégée). */
    private const IN_FORCE = ['classified', 'listed'];

    protected function rules(): array
    {
        return [
            'material' => ['sometimes', 'string', 'min:1', 'max:255'],
            'venue_type' => ['sometimes', 'string', 'in:'.implode(',', PreservedRollingStock::VENUE_TYPES)],
            'venue' => ['sometimes', 'string', 'min:1', 'max:160', 'prohibits:material'],
            'state' => ['sometimes', 'string', 'in:'.implode(',', PreservedRollingStock::STATES)],
            'protected' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'material' => $schema->string()->min(1)->max(255)->description('Slug d\'une série (ex. "241-p", "bb-15000"), tel que renvoyé par search ou materials : ses exemplaires préservés. Sans lui : les lieux et leurs pièces.'),
            'venue_type' => $schema->string()->enum(PreservedRollingStock::VENUE_TYPES)->description('Type de lieu : museum, association, tourist_railway ou site (gare, remise, dépôt). Par défaut : tous.'),
            'venue' => $schema->string()->min(1)->max(160)->description('Slug d\'un lieu (ex. "cite-du-train"), tel que renvoyé dans `venue.slug` ou `venues[].slug`. Pas avec `material`.'),
            'state' => $schema->string()->enum(PreservedRollingStock::STATES)->description('État dit par la source : running, exposed, restoration ou stored. Par défaut : tous, y compris les états inconnus.'),
            'protected' => $schema->boolean()->description('true : seulement les pièces classées ou inscrites monuments historiques ; false : seulement les autres.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(20)->description('Nombre d\'exemplaires (avec `material`) ou de lieux renvoyés, 1 à 50 (20 par défaut).'),
            'offset' => $schema->integer()->min(0)->default(0)->description('Nombre d\'exemplaires ou de lieux à sauter (pagination).'),
        ];
    }

    protected function answer(array $input, string $lang): Response
    {
        $preserved = app(PreservedRollingStock::class);
        $keep = $this->pieceFilter($input);
        $offset = (int) ($input['offset'] ?? 0);
        $limit = (int) ($input['limit'] ?? 20);
        $filters = array_intersect_key($input, array_flip(['material', 'venue_type', 'venue', 'state', 'protected']));

        if (isset($input['material'])) {
            $material = Material::where('slug', $input['material'])->first(['id', 'slug', 'name']);
            if ($material === null) {
                return $this->fail('material_not_found', ['query' => $input['material']], ['suggestions' => $this->suggestions($input['material'], 'materials')]);
            }
            $type = $input['venue_type'] ?? null;
            $units = array_values(array_filter($preserved->forMaterial($material->slug),
                fn (array $u): bool => $keep($u) && ($type === null || ($u['venue']['type'] ?? null) === $type)));
            $page = array_map(fn (array $u): array => ['venue' => self::venue($u['venue'])] + $u, array_slice($units, $offset, $limit));

            return $this->respond([
                'material' => ['slug' => $material->slug, 'name' => $material->name],
                'filters' => $filters,
                'total' => count($units), 'offset' => $offset, 'has_more' => $offset + $limit < count($units),
                'coverage_note' => (string) trans('mcp.preserved.coverage_note', [], $lang),
                'units' => $page,
            ], self::providers($page), $lang, self::url('/materials/'.$material->slug), ['units']);
        }

        $venues = [];
        foreach ($preserved->venues() as $venue) {
            if ((isset($input['venue_type']) && $venue['type'] !== $input['venue_type'])
                || (isset($input['venue']) && $venue['slug'] !== $input['venue'])) {
                continue;
            }
            $pieces = array_values(array_filter($venue['pieces'], $keep));
            if ($pieces !== []) {
                $venues[] = self::venue($venue) + ['count' => count($pieces), 'pieces' => $pieces];
            }
        }
        $page = array_slice($venues, $offset, $limit);
        $figures = $preserved->figures();

        return $this->respond([
            'filters' => $filters,
            'total' => count($venues), 'offset' => $offset, 'has_more' => $offset + $limit < count($venues),
            'figures' => $figures,
            'coverage_note' => (string) trans('mcp.preserved.coverage_note', [], $lang),
            'venues' => $page,
        ], self::providers(array_merge([], ...array_column($page, 'pieces'))), $lang, self::url('/materials/preserved'), ['venues']);
    }

    /**
     * Filtre des exemplaires et des pièces : état dit par la source, protection en vigueur.
     *
     * @param  array<string, mixed>  $input
     * @return Closure(array<string, mixed>): bool
     */
    private function pieceFilter(array $input): Closure
    {
        $state = $input['state'] ?? null;
        $protected = isset($input['protected']) ? filter_var($input['protected'], FILTER_VALIDATE_BOOLEAN) : null;

        return fn (array $piece): bool => ($state === null || ($piece['state'] ?? null) === $state)
            && ($protected === null || in_array($piece['protection']['kind'] ?? null, self::IN_FORCE, true) === $protected);
    }

    /**
     * Lieu avec l'adresse complète de sa ligne touristique (le service donne un chemin du site).
     *
     * @param  array<string, mixed>|null  $venue
     * @return array<string, mixed>|null
     */
    private static function venue(?array $venue): ?array
    {
        if (isset($venue['tourist_railway']['url']) && str_starts_with((string) $venue['tourist_railway']['url'], '/')) {
            $venue['tourist_railway']['url'] = self::url((string) $venue['tourist_railway']['url']);
        }

        return $venue;
    }

    /**
     * Fournisseurs des sources citées (wikipedia, palissy, wikidata : mêmes clés que le registre des sources).
     *
     * @param  list<array<string, mixed>>  $pieces
     * @return list<string>
     */
    private static function providers(array $pieces): array
    {
        $keys = [];
        foreach ($pieces as $piece) {
            foreach ($piece['sources'] ?? [] as $source) {
                $keys[(string) $source['key']] = true;
            }
        }

        return array_keys($keys);
    }
}
