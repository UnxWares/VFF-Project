<?php

namespace App\Services\StationService;

/**
 * Indices sans horaires d'un usage touristique : tronçon touristique ou vélorail de
 * l'ère courante sur une ligne de la gare (VFF), gare touristique ou de vélorail (OSM).
 */
final readonly class HeritageHints
{
    public function __construct(
        public bool $touristLine = false,
        public bool $touristStation = false,
        public bool $velorailLine = false,
        public bool $velorailStation = false,
    ) {}
}
