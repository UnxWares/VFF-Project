<?php

declare(strict_types=1);

namespace App\Services\Tracks;

/**
 * Plane computations for the detailed track layout on flat WGS 84 polylines ([lon0, lat0, lon1, lat1, ...]):
 * local equirectangular projection around the query point (error well under a metre over a few kilometres),
 * lengths, projection on a segment with a signed lateral offset (positive on the left of the segment
 * direction), densification. Pure functions (tests: tests/Unit/Tracks/TrackGeometryTest.php).
 */
final class TrackGeometry
{
    public const EARTH_M = 6371008.8;

    /** Metres per degree of latitude (and of longitude at the equator). */
    public const M_PER_DEG = self::EARTH_M * M_PI / 180.0;

    public static function metres(float $lon1, float $lat1, float $lon2, float $lat2): float
    {
        $dx = ($lon2 - $lon1) * cos(deg2rad(($lat1 + $lat2) / 2));
        $dy = $lat2 - $lat1;

        return self::M_PER_DEG * sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Length (m) of a flat polyline.
     *
     * @param  list<float>  $c
     */
    public static function length(array $c): float
    {
        $total = 0.0;
        for ($i = 2, $n = count($c); $i + 1 < $n; $i += 2) {
            $total += self::metres($c[$i - 2], $c[$i - 1], $c[$i], $c[$i + 1]);
        }

        return $total;
    }

    /**
     * Cumulative length (m) at each vertex of a flat polyline.
     *
     * @param  list<float>  $c
     * @return list<float>
     */
    public static function cumulative(array $c): array
    {
        $out = [0.0];
        $run = 0.0;
        for ($i = 2, $n = count($c); $i + 1 < $n; $i += 2) {
            $run += self::metres($c[$i - 2], $c[$i - 1], $c[$i], $c[$i + 1]);
            $out[] = $run;
        }

        return $out;
    }

    /**
     * Projection of point P on segment [A, B], in metres around P: [distance, fraction t along AB (0 to 1),
     * signed offset = distance with the sign of the side of P (positive on the left of A to B), fraction along
     * the infinite line AB (below 0 before A, above 1 beyond B)].
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function onSegment(float $px, float $py, float $ax, float $ay, float $bx, float $by): array
    {
        $k = cos(deg2rad($py));
        $x1 = ($ax - $px) * $k;
        $y1 = $ay - $py;
        $dx = ($bx - $ax) * $k;
        $dy = $by - $ay;
        $l2 = $dx * $dx + $dy * $dy;
        $raw = $l2 > 0.0 ? -($x1 * $dx + $y1 * $dy) / $l2 : 0.0;
        $t = max(0.0, min(1.0, $raw));
        $qx = $x1 + $t * $dx;
        $qy = $y1 + $t * $dy;
        $d = sqrt($qx * $qx + $qy * $qy) * self::M_PER_DEG;
        // Side of P: cross product of AB with AP, where AP = (-x1, -y1).
        $cross = -$dx * $y1 + $dy * $x1;

        return [$d, $t, $cross >= 0.0 ? $d : -$d, $raw];
    }

    /**
     * Unit direction (local metres, x east, y north) of segment [A, B]; [0, 0] for a degenerate segment.
     *
     * @return array{0: float, 1: float}
     */
    public static function direction(float $ax, float $ay, float $bx, float $by): array
    {
        $dx = ($bx - $ax) * cos(deg2rad(($ay + $by) / 2));
        $dy = $by - $ay;
        $n = sqrt($dx * $dx + $dy * $dy);

        return $n > 0.0 ? [$dx / $n, $dy / $n] : [0.0, 0.0];
    }

    /**
     * Densified polyline (no two consecutive points more than $step metres apart) and, for each original
     * vertex, its index in the densified polyline.
     *
     * @param  list<float>  $c
     * @return array{0: list<float>, 1: list<int>}
     */
    public static function densify(array $c, float $step): array
    {
        $out = [$c[0], $c[1]];
        $index = [0];
        for ($i = 2, $n = count($c); $i + 1 < $n; $i += 2) {
            $d = self::metres($c[$i - 2], $c[$i - 1], $c[$i], $c[$i + 1]);
            $pieces = (int) ceil($d / $step);
            for ($k = 1; $k < $pieces; $k++) {
                $f = $k / $pieces;
                $out[] = $c[$i - 2] + ($c[$i] - $c[$i - 2]) * $f;
                $out[] = $c[$i - 1] + ($c[$i + 1] - $c[$i - 1]) * $f;
            }
            $out[] = $c[$i];
            $out[] = $c[$i + 1];
            $index[] = intdiv(count($out), 2) - 1;
        }

        return [$out, $index];
    }

    /**
     * Point [lon, lat] reached $metres along the polyline from vertex $from, towards increasing ($step = 1)
     * or decreasing ($step = -1) vertex indices; the last vertex when the polyline is shorter.
     *
     * @param  list<float>  $c
     * @return array{0: float, 1: float}
     */
    public static function along(array $c, int $from, int $step, float $metres): array
    {
        $count = intdiv(count($c), 2);
        $run = 0.0;
        $i = $from;
        while ($i + $step >= 0 && $i + $step < $count) {
            $j = $i + $step;
            $d = self::metres($c[2 * $i], $c[2 * $i + 1], $c[2 * $j], $c[2 * $j + 1]);
            if ($run + $d >= $metres && $d > 0.0) {
                $f = ($metres - $run) / $d;

                return [$c[2 * $i] + ($c[2 * $j] - $c[2 * $i]) * $f, $c[2 * $i + 1] + ($c[2 * $j + 1] - $c[2 * $i + 1]) * $f];
            }
            $run += $d;
            $i = $j;
        }

        return [$c[2 * $i], $c[2 * $i + 1]];
    }
}
