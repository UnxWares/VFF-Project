<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Loaders\StationTimetableLoader;
use App\Models\Station;
use App\Services\Timetable\Timetables;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Desserte d'une gare un jour type en GraphQL (champ `Station.timetable`) : même bloc que `timetable` de
 * `GET /api/v1/stations/{station}`, chargé en une requête pour toutes les gares d'une liste.
 */
final class StationTimetableField
{
    public function __construct(private readonly Timetables $timetables) {}

    /** @param  array<string, mixed>  $args */
    public function __invoke(Station $station, array $args, GraphQLContext $context, ResolveInfo $info): Deferred
    {
        $path = [...array_slice($info->path, 0, -1), 'timetable@'];

        return BatchLoaderRegistry::instance($path, fn (): StationTimetableLoader => new StationTimetableLoader($this->timetables))->load($station);
    }
}
