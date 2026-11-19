<?php

namespace App\GraphQL\Builders;

use App\Models\Media;
use App\Services\Api\MediaQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Médias en GraphQL : argument `filter` de la requête `media` (mêmes règles et même requête que
 * `GET /api/v1/media`, MediaQuery), et ordre des médias d'une ligne ou d'une gare.
 */
final class MediaBuilder
{
    public function __construct(private readonly MediaQuery $query) {}

    /**
     * @param  Builder<Media>  $builder
     * @param  array<string, mixed>  $filter
     * @return Builder<Media>
     */
    public function filter(Builder $builder, array $filter): Builder
    {
        return $this->query->apply($builder, $this->query->validate(self::withOrder($filter)))->with('mediable');
    }

    /**
     * Médias rattachés à une entité (`Line.media`, `Station.media`) : dans l'ordre de leurs identifiants.
     * (La position `lat` / `lng` est lue média par média : la sélectionner ici fausserait le comptage de la page.)
     *
     * @param  Builder<Media>|Relation<Media, *, *>  $builder
     * @return Builder<Media>|Relation<Media, *, *>
     */
    public function inOrder(Builder|Relation $builder): Builder|Relation
    {
        return $builder->orderBy('media.id');
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
