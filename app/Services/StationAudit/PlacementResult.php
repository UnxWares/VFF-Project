<?php

namespace App\Services\StationAudit;

/** Résultat de PlacementGrader::grade(). */
final readonly class PlacementResult
{
    public function __construct(
        public PlacementGrade $grade,
        /** Nombre de familles de sources (position retenue comprise). */
        public int $families,
        /** Écart à la source indépendante la plus proche (m). */
        public ?int $nearestAgreeingM,
        /** Écart à la source précise indépendante la plus éloignée (m). */
        public ?int $farthestPreciseM,
        /** Au moins deux autres familles d'accord entre elles (à moins de 50 m) et loin (plus de 100 m) de la position retenue. */
        public bool $outvoted = false,
    ) {}
}
