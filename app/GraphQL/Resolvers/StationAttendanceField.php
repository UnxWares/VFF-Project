<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Loaders\StationAttendanceLoader;
use App\Models\Station;
use App\Services\Sncf\StationAttendance;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Fréquentation d'une gare en GraphQL (champ `Station.attendance`) : même bloc que `attendance` de
 * `GET /api/v1/stations/{station}`, chargé en une requête pour toutes les gares d'une liste.
 */
final class StationAttendanceField
{
    public function __construct(private readonly StationAttendance $attendance) {}

    /** @param  array<string, mixed>  $args */
    public function __invoke(Station $station, array $args, GraphQLContext $context, ResolveInfo $info): Deferred
    {
        $path = [...array_slice($info->path, 0, -1), 'attendance@'];

        return BatchLoaderRegistry::instance($path, fn (): StationAttendanceLoader => new StationAttendanceLoader($this->attendance))->load($station);
    }
}
