<?php

namespace App\GraphQL\Builders;

use App\Models\LevelCrossing;
use App\Services\LevelCrossings\LevelCrossingQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Argument `filter` de la requête GraphQL `levelCrossings` : mêmes règles et mêmes filtres que
 * `GET /api/v1/level-crossings`, même ordre (code de ligne, PK, PK inconnus en dernier).
 */
final class LevelCrossingFilterBuilder
{
    public function __construct(private readonly LevelCrossingQuery $query) {}

    /**
     * @param  Builder<LevelCrossing>  $builder
     * @param  array<string, mixed>  $filter
     * @return Builder<LevelCrossing>
     */
    public function filter(Builder $builder, array $filter): Builder
    {
        return $this->query->apply($builder, $this->query->validate($filter))
            ->orderBy('code_ligne')->orderByRaw('pk_m IS NULL, pk_m')->orderBy('id');
    }
}
