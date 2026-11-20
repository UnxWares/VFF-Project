<?php

namespace App\Services\StationPositioning;

/**
 * Position d'un PK sur l'axe : abscisse (m), écart en PK au point d'appui le plus proche
 * (0 si le PK est encadré par deux gares d'appui), précision estimée (m) et méthode.
 */
final readonly class PkPlacement
{
    public function __construct(
        public float $measure,
        public int $gapM,
        public bool $bracketed,
        public int $precisionM,
        public string $how,
    ) {}
}
