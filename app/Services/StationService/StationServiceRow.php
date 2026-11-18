<?php

namespace App\Services\StationService;

/**
 * Classement d'une gare et ses preuves, tel que rangé dans station_services.
 */
final readonly class StationServiceRow
{
    /**
     * @param  list<array<string, mixed>>  $evidence  un élément par arrêt GTFS rapproché
     * @param  array<string, mixed>  $indicators  indices sans horaires (VFF, OSM, fréquentation)
     */
    public function __construct(
        public int $stationId,
        public StationServiceVerdict $verdict,
        public ?ServiceProfile $profile,
        public array $evidence,
        public array $indicators,
    ) {}
}
