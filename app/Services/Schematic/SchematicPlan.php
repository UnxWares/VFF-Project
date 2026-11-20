<?php

namespace App\Services\Schematic;

/**
 * Plan schématique calculé par SchematicLayout, en mètres Web Mercator (EPSG:3857).
 *
 * `pieces` : morceaux d'axes, chacun d'une seule ligne et d'une seule catégorie, en segments
 * horizontaux, verticaux ou à 45°.
 *
 * `stations` : gares du plan. `kind` vaut `stop` (gare intermédiaire, dessinée en trait en travers de
 * l'axe, `angle` = direction de l'axe en degrés, 0 = est), `hub` (gare d'un nœud où se rejoignent au
 * moins trois axes) ou `end` (terminus) ; `main` est vrai pour la gare qui nomme son nœud (les autres
 * gares fondues dans le même nœud sont au même point) ; `degree` = nombre d'axes du nœud (0 pour une
 * gare intermédiaire).
 */
final readonly class SchematicPlan
{
    /**
     * @param  list<array{line: ?int, category: string, coords: list<array{0: float, 1: float}>}>  $pieces
     * @param  list<array{station: int, x: float, y: float, angle: int, kind: string, main: bool, degree: int}>  $stations
     * @param  array<string, int|float>  $stats
     */
    public function __construct(
        public float $cellM,
        public array $pieces,
        public array $stations,
        public array $stats,
    ) {}
}
