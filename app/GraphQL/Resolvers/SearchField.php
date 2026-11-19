<?php

namespace App\GraphQL\Resolvers;

use App\Services\CatalogSearch;

/**
 * Requête GraphQL `search(query, limit)` : même recherche par nom que `GET /api/v1/search` et l'outil MCP
 * `search` (CatalogSearch), lignes, gares et matériels.
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

        return $this->search->search($args['query'], $limit, $limit, $limit);
    }
}
