<?php

namespace App\GraphQL\Builders;

use App\Models\Accident;
use App\Services\Api\AccidentQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Argument `filter` de la requête GraphQL `accidents` : mêmes règles et même requête que
 * `GET /api/v1/accidents` (AccidentQuery).
 */
final class AccidentFilterBuilder
{
    public function __construct(private readonly AccidentQuery $query) {}

    /**
     * @param  Builder<Accident>  $builder
     * @param  array<string, mixed>  $filter
     * @return Builder<Accident>
     */
    public function filter(Builder $builder, array $filter): Builder
    {
        return $this->query->apply($builder, $this->query->validate($filter));
    }
}
