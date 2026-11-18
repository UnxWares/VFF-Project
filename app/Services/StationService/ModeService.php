<?php

namespace App\Services\StationService;

/**
 * Arrêts d'un mode (train, tramway, car) à une gare, sur la fenêtre lue d'un flux.
 */
final readonly class ModeService
{
    /**
     * @param  array<int, int>  $days  AAAAMMJJ => nombre d'arrêts ce jour-là
     * @param  list<string>  $routes  lignes GTFS (nom court ou long)
     */
    public function __construct(
        public int $calls,
        public array $days,
        public array $routes,
    ) {}
}
