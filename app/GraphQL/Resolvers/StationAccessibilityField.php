<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Loaders\StationAccessibilityLoader;
use App\Models\Station;
use App\Services\Accessibility\StationAccessibility;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Accessibilité d'une gare en GraphQL (champ `Station.accessibility`) : même bloc que `accessibility` de
 * `GET /api/v1/stations/{station}`, chargé en une requête pour toutes les gares d'une liste.
 */
final class StationAccessibilityField
{
    public function __construct(private readonly StationAccessibility $accessibility) {}

    /** @param  array<string, mixed>  $args */
    public function __invoke(Station $station, array $args, GraphQLContext $context, ResolveInfo $info): Deferred
    {
        $path = [...array_slice($info->path, 0, -1), 'accessibility@'];

        return BatchLoaderRegistry::instance($path, fn (): StationAccessibilityLoader => new StationAccessibilityLoader($this->accessibility))->load($station);
    }
}
