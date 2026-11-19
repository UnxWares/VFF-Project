<?php

namespace App\Services\StationPositioning;

/**
 * Nouvelle position calculée pour une gare d'une liste (station_sources), ou raison
 * pour laquelle elle reste où elle est ($x / $y null).
 */
final readonly class ListedPlacement
{
    public function __construct(
        public int $sourceId,
        public int $stationId,
        public int $lineId,
        public string $name,
        public int $pk,
        public float $oldLon,
        public float $oldLat,
        public ?int $oldPrecisionM,
        public ?float $x,
        public ?float $y,
        public ?int $precisionM,
        public string $note,
        public string $calibration,
        public int $movedM,
    ) {}

    public function moved(): bool
    {
        return $this->x !== null && $this->y !== null;
    }
}
