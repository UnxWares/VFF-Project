<?php

namespace App\GraphQL\Resolvers;

use App\Models\Station;

/**
 * `station(slug:)` : la gare de ce slug, ou la fiche gardée quand ce slug est celui d'une fiche fusionnée
 * (table station_redirects) ; son champ `slug` donne alors le slug actuel.
 */
final class StationBySlugResolver
{
    /** @param  array{slug: string}  $args */
    public function __invoke(mixed $root, array $args): ?Station
    {
        $slug = $args['slug'];

        return Station::query()->where('slug', $slug)->first()
            ?? (($current = Station::redirectSlugFor($slug)) !== null ? Station::query()->where('slug', $current)->first() : null);
    }
}
