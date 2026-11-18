<?php

namespace App\Services\StationService;

/**
 * Gare de VFF candidate au rapprochement : position, noms normalisés
 * (App\Support\StationNames) et codes UIC à 7 chiffres de toutes ses sources.
 */
final readonly class StationCandidate
{
    /**
     * @param  list<string>  $names
     * @param  list<string>  $uics
     */
    public function __construct(
        public int $id,
        public float $lat,
        public float $lon,
        public array $names,
        public array $uics,
    ) {}
}
