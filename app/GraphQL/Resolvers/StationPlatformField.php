<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Loaders\StationPlatformLoader;
use App\Models\Station;
use App\Services\Sncf\StationPlatforms;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Quais d'une gare en GraphQL (champ `Station.platforms`) : même bloc que `platforms` de
 * `GET /api/v1/stations/{station}`, chargé en une requête pour toutes les gares d'une liste.
 */
final class StationPlatformField
{
    public function __construct(private readonly StationPlatforms $platforms) {}

    /** @param  array<string, mixed>  $args */
    public function __invoke(Station $station, array $args, GraphQLContext $context, ResolveInfo $info): Deferred
    {
        $path = [...array_slice($info->path, 0, -1), 'platforms@'];

        return BatchLoaderRegistry::instance($path, fn (): StationPlatformLoader => new StationPlatformLoader($this->platforms))->load($station);
    }
}
