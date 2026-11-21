<?php

namespace App\GraphQL\Resolvers;

use App\Services\Api\MediaQuery;

/**
 * Requête GraphQL `mediaFacets` : les compteurs de `GET /api/v1/media?facets=true` (`meta.facets`),
 * pour les mêmes filtres que `media`.
 */
final readonly class MediaFacetsResolver
{
    public function __construct(private MediaQuery $query) {}

    /**
     * @param  array{filter?: array<string, mixed>}  $args
     * @return array{total: int, types: list<array{code: string, count: int}>, licenses: list<array{license: ?string, count: int}>, authors: list<array{author: string, count: int}>}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $filter = $args['filter'] ?? [];
        if (isset($filter['order']) && is_string($filter['order'])) {
            $filter['order'] = strtolower($filter['order']);
        }
        $facets = $this->query->facets($this->query->validate($filter));

        return [
            'total' => $facets['total'],
            'types' => array_map(fn (string $code, int $count): array => ['code' => $code, 'count' => $count], array_keys($facets['types']), $facets['types']),
            'licenses' => $facets['licenses'],
            'authors' => $facets['authors'],
        ];
    }
}
