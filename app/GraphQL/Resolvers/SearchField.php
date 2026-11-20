<?php

namespace App\GraphQL\Resolvers;

use App\Services\CatalogSearch;

/**
 * Requête GraphQL `search(query, limit)` : même recherche par nom que `GET /api/v1/search` et l'outil MCP
 * `search` (CatalogSearch), lignes, gares, matériels et accidents.
 */
final readonly class SearchField
{
    public function __construct(private CatalogSearch $search) {}

    /**
     * @param  array{query: string, limit?: int|null}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $_, array $args): array
    {
        $limit = $args['limit'] ?? 8;

        return $this->search->search($args['query'], $limit, $limit, $limit, $limit);
    }

    /**
     * Nom d'un accident trouvé, dans la langue demandée (français à défaut).
     *
     * @param  array{names: array<string, string>, name: string}  $accident
     * @param  array{lang?: string}  $args
     */
    public function accidentName(array $accident, array $args): string
    {
        return $accident['names'][$args['lang'] ?? 'fr'] ?? $accident['name'];
    }
}
