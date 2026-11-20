<?php

namespace App\Services\Schematic;

/**
 * Web Mercator sphérique (EPSG:3857), la projection des cartes MapLibre : un segment droit du plan
 * schématique reste droit à l'écran, et ses angles de 45° restent de 45°.
 */
final class Mercator
{
    /** Rayon de la sphère de Web Mercator (m). */
    public const RADIUS = 6378137.0;

    /** @return array{0: float, 1: float} (x, y) en mètres */
    public static function project(float $lng, float $lat): array
    {
        return [
            deg2rad($lng) * self::RADIUS,
            log(tan(M_PI / 4 + deg2rad($lat) / 2)) * self::RADIUS,
        ];
    }

    /** @return array{0: float, 1: float} (longitude, latitude) en degrés */
    public static function unproject(float $x, float $y): array
    {
        return [
            rad2deg($x / self::RADIUS),
            rad2deg(2 * atan(exp($y / self::RADIUS)) - M_PI / 2),
        ];
    }
}
