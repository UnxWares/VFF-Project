<?php

namespace App\Services\StationService;

use App\Enums\TransitMode;

/**
 * Point d'arrêt d'un flux GTFS (zone d'arrêt, ou arrêt sans zone parente) et sa desserte.
 */
final readonly class GtfsStop
{
    /** @param  array<string, ModeService>  $services  valeur de TransitMode => desserte */
    public function __construct(
        public string $key,
        public string $name,
        public ?float $lat,
        public ?float $lon,
        public ?string $uic,
        public array $services,
    ) {}

    public function service(TransitMode $mode): ?ModeService
    {
        return $this->services[$mode->value] ?? null;
    }
}
