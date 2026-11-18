<?php

namespace App\Services\StationService;

use App\Enums\StopMatchMethod;

/**
 * Gare(s) de VFF rapprochée(s) d'un arrêt GTFS.
 */
final readonly class StopMatch
{
    /**
     * @param  list<int>  $stationIds  plusieurs gares quand elles partagent le code UIC de l'arrêt
     * @param  list<string>  $flags  uic_far, uic_multiple, ambiguous, homonym
     */
    public function __construct(
        public array $stationIds,
        public StopMatchMethod $method,
        public ?int $distanceM,
        public array $flags = [],
    ) {}
}
