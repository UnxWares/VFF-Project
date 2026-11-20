<?php

namespace App\Services\StationService;

/**
 * Desserte lue dans un flux : horizon publié, fenêtre retenue, arrêts desservis.
 */
final readonly class FeedSnapshot
{
    /** @param  list<GtfsStop>  $stops */
    public function __construct(
        public FeedDefinition $feed,
        public int $horizonStart,
        public int $horizonEnd,
        public int $windowStart,
        public int $windowEnd,
        public array $stops,
    ) {}

    /** Flux dont l'horizon est fini à la date donnée. */
    public function isStaleOn(int $today): bool
    {
        return $this->horizonEnd < $today;
    }
}
