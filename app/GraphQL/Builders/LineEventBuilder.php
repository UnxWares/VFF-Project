<?php

namespace App\GraphQL\Builders;

use App\Models\LineEvent;
use App\Services\Api\LineEventQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Argument `filter` de la requête GraphQL `lineEvents` : mêmes règles et même requête que
 * `GET /api/v1/events` (LineEventQuery).
 */
final class LineEventBuilder
{
    public function __construct(private readonly LineEventQuery $query) {}

    /**
     * @param  Builder<LineEvent>  $builder
     * @param  array<string, mixed>  $filter
     * @return Builder<LineEvent>
     */
    public function filter(Builder $builder, array $filter): Builder
    {
        return $this->query->apply($builder, $this->query->validate(self::withOrder($filter)));
    }

    /**
     * L'énumération `SortOrder` arrive en majuscules (`DESC`) dans l'argument : les règles attendent `asc` ou `desc`.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    private static function withOrder(array $filter): array
    {
        if (isset($filter['order']) && is_string($filter['order'])) {
            $filter['order'] = strtolower($filter['order']);
        }

        return $filter;
    }
}
