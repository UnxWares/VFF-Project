<?php

declare(strict_types=1);

namespace App\Services\Tracks\Osm;

use App\Services\Tracks\TrackChainage;

/**
 * A VFF line in service in the current era, as the OpenStreetMap track import sees it: its code, its reference
 * trace (flat polylines), the chainage calibrated on its sourced PKs and the stations attached to it.
 */
final readonly class TrackLine
{
    /**
     * @param  list<list<float>>  $parts
     * @param  list<array{id: int, lon: float, lat: float}>  $stations
     */
    public function __construct(
        public int $id,
        public ?string $code,
        public array $parts,
        public TrackChainage $chainage,
        public array $stations,
    ) {}
}
