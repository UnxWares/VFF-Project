<?php

namespace App\GraphQL\Resolvers;

use App\Services\PathfindingService;

class RouteResolver
{
    public function __construct(protected PathfindingService $pathfinding) {}

    /**
     * @param  array{from: string, to: string, era?: ?int}  $args
     */
    public function __invoke(mixed $_, array $args): ?array
    {
        return $this->pathfinding->shortestPathBetweenStations(
            $args['from'],
            $args['to'],
            $args['era'] ?? null,
        );
    }
}
