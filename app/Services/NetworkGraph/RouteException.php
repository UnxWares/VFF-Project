<?php

namespace App\Services\NetworkGraph;

use RuntimeException;

/**
 * Itinéraire impossible, avec un code stable pour l'API et l'interface :
 * era_not_found, graph_not_built, station_not_found, ambiguous_station,
 * same_station, station_not_in_network, no_path.
 */
final class RouteException extends RuntimeException
{
    /** @param  array<string, mixed>  $details */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
