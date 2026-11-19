<?php

namespace App\GraphQL\Builders;

use App\Models\Material;
use App\Services\Api\MaterialQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Argument `filter` de la requête GraphQL `materials` : mêmes règles et même requête que
 * `GET /api/v1/materials` (MaterialQuery).
 */
final class MaterialFilterBuilder
{
    public function __construct(private readonly MaterialQuery $query) {}

    /**
     * @param  Builder<Material>  $builder
     * @param  array<string, mixed>  $filter
     * @return Builder<Material>
     */
    public function filter(Builder $builder, array $filter): Builder
    {
        return $this->query->apply($builder, $this->query->validate($filter));
    }
}
