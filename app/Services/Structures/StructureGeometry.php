<?php

declare(strict_types=1);

namespace App\Services\Structures;

/**
 * Calculs plans sur des polylignes en longitude, latitude (WGS 84), à l'échelle d'un ouvrage ou d'une
 * ligne : projection équirectangulaire locale (erreur bien sous le mètre sur quelques kilomètres).
 * Fonctions pures (tests : tests/Unit/Structures/StructureGeometryTest.php).
 */
final class StructureGeometry
{
    private const EARTH_M = 6371008.8;

    /** Distance (m) entre deux points [lon, lat]. */
    public static function metres(array $a, array $b): float
    {
        $k = cos(deg2rad(($a[1] + $b[1]) / 2));
        $dx = deg2rad($b[0] - $a[0]) * $k;
        $dy = deg2rad($b[1] - $a[1]);

        return self::EARTH_M * sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Longueur (m) d'une polyligne.
     *
     * @param  list<array{0: float, 1: float}>  $coords
     */
    public static function length(array $coords): float
    {
        $total = 0.0;
        for ($i = 1, $n = count($coords); $i < $n; $i++) {
            $total += self::metres($coords[$i - 1], $coords[$i]);
        }

        return $total;
    }

    /**
     * Point situé à mi-longueur de la polyligne.
     *
     * @param  list<array{0: float, 1: float}>  $coords
     * @return array{0: float, 1: float}
     */
    public static function midpoint(array $coords): array
    {
        $half = self::length($coords) / 2;
        $run = 0.0;
        for ($i = 1, $n = count($coords); $i < $n; $i++) {
            $step = self::metres($coords[$i - 1], $coords[$i]);
            if ($run + $step >= $half && $step > 0) {
                $f = ($half - $run) / $step;

                return [
                    $coords[$i - 1][0] + ($coords[$i][0] - $coords[$i - 1][0]) * $f,
                    $coords[$i - 1][1] + ($coords[$i][1] - $coords[$i - 1][1]) * $f,
                ];
            }
            $run += $step;
        }

        return $coords[0];
    }

    /**
     * Projection d'un point sur une polyligne : indice du segment, fraction le long de ce segment (0 à 1)
     * et distance (m) entre le point et sa projection. Null pour une polyligne de moins de deux sommets.
     *
     * @param  array{0: float, 1: float}  $p
     * @param  list<array{0: float, 1: float}>  $coords
     * @return array{segment: int, fraction: float, distance_m: float}|null
     */
    public static function project(array $p, array $coords): ?array
    {
        $n = count($coords);
        if ($n < 2) {
            return null;
        }
        $k = cos(deg2rad($p[1]));
        $best = null;
        for ($i = 1; $i < $n; $i++) {
            $ax = ($coords[$i - 1][0] - $p[0]) * $k;
            $ay = $coords[$i - 1][1] - $p[1];
            $bx = ($coords[$i][0] - $p[0]) * $k;
            $by = $coords[$i][1] - $p[1];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $len2 = $dx * $dx + $dy * $dy;
            $t = $len2 > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $len2)) : 0.0;
            $cx = $ax + $t * $dx;
            $cy = $ay + $t * $dy;
            $d2 = $cx * $cx + $cy * $cy;
            if ($best === null || $d2 < $best[2]) {
                $best = [$i - 1, $t, $d2];
            }
        }

        return [
            'segment' => $best[0],
            'fraction' => $best[1],
            'distance_m' => self::EARTH_M * deg2rad(sqrt($best[2])),
        ];
    }

    /**
     * Distance (m) entre un point et une polyligne (ou le point seul s'il n'y a qu'un sommet).
     *
     * @param  array{0: float, 1: float}  $p
     * @param  list<array{0: float, 1: float}>  $coords
     */
    public static function distanceToLine(array $p, array $coords): float
    {
        if (count($coords) === 1) {
            return self::metres($p, $coords[0]);
        }

        return self::project($p, $coords)['distance_m'] ?? INF;
    }

    /**
     * Polyligne en WKT (SRID 4326 à ajouter par l'appelant).
     *
     * @param  list<array{0: float, 1: float}>  $coords
     */
    public static function wkt(array $coords): string
    {
        return 'LINESTRING('.implode(',', array_map(
            fn (array $c): string => sprintf('%.7F %.7F', $c[0], $c[1]),
            $coords,
        )).')';
    }
}
