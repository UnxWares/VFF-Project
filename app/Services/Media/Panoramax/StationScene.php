<?php

namespace App\Services\Media\Panoramax;

/**
 * Abords d'une gare en mètres, dans un repère local centré sur la gare (x vers l'est, y vers le nord) : bâtiments
 * de la gare (« cibles », bâtiment voyageurs et abris), autres bâtiments (obstacles), quais et zone de la gare.
 * Sert à dire ce qu'une photo de rue voit vraiment : des rayons lancés dans le champ de l'appareil, arrêtés par le
 * premier bâtiment touché.
 */
final readonly class StationScene
{
    /** Pas angulaire des rayons (degrés). */
    public const RAY_STEP = 1.0;

    /**
     * @param  list<list<array{0: float, 1: float}>>  $targets  contours des bâtiments de la gare
     * @param  list<list<array{0: float, 1: float}>>  $obstacles  contours des autres bâtiments
     * @param  list<array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}>  $platforms  quais (segments)
     * @param  ?list<array{0: float, 1: float}>  $zone  emprise de la gare (BD TOPO), null si inconnue
     */
    public function __construct(
        public float $lat,
        public float $lng,
        public array $targets = [],
        public array $obstacles = [],
        public array $platforms = [],
        public ?array $zone = null,
    ) {}

    /**
     * Repère local (mètres) d'un point WGS 84 : projection équirectangulaire autour de la gare, juste à moins de
     * 0,1 % sur quelques centaines de mètres.
     *
     * @return array{0: float, 1: float}
     */
    public function project(float $lat, float $lng): array
    {
        return [
            deg2rad($lng - $this->lng) * 6371008.8 * cos(deg2rad($this->lat)),
            deg2rad($lat - $this->lat) * 6371008.8,
        ];
    }

    /** Vrai si aucun bâtiment de la gare n'est connu (les quais seuls ne suffisent pas). */
    public function isEmpty(): bool
    {
        return $this->targets === [];
    }

    /** Vrai si le point (mètres) est dans l'emprise de la gare. */
    public function inZone(float $x, float $y): bool
    {
        return $this->zone !== null && self::contains($this->zone, $x, $y);
    }

    /**
     * Ce que voit une photo prise en ($x, $y) (mètres), tournée vers $azimuth (degrés, 0 = nord), sur un demi-champ
     * de $halfFov degrés : largeur (degrés) des bâtiments de la gare vus en premier entre $minM et $maxM, largeur des
     * quais vus avant tout bâtiment à $platformMaxM au plus, largeur des bâtiments de la gare cachés par un autre
     * bâtiment, distance du bâtiment de la gare vu le plus proche.
     *
     * @return array{target_deg: float, platform_deg: float, hidden_deg: float, nearest_m: ?float}
     */
    public function view(float $x, float $y, float $azimuth, float $halfFov, float $minM, float $maxM, float $platformMaxM): array
    {
        $targetDeg = $platformDeg = $hiddenDeg = 0.0;
        $nearest = null;
        $reach = max($maxM, $platformMaxM);
        $targets = self::near($this->targets, $x, $y, $reach);
        $obstacles = self::near($this->obstacles, $x, $y, $reach);
        $rays = max(1, (int) floor(2 * $halfFov / self::RAY_STEP));
        for ($i = 0; $i <= $rays; $i++) {
            $a = deg2rad($azimuth - $halfFov + $i * self::RAY_STEP);
            $dx = sin($a);
            $dy = cos($a);
            $tHit = self::firstHit($targets, $x, $y, $dx, $dy);
            $oHit = self::firstHit($obstacles, $x, $y, $dx, $dy);
            $building = min($tHit ?? INF, $oHit ?? INF);
            if ($tHit !== null && $tHit <= $building) {
                if ($tHit >= $minM && $tHit <= $maxM) {
                    $targetDeg += self::RAY_STEP;
                    $nearest = min($nearest ?? INF, $tHit);
                }
            } elseif ($tHit !== null && $tHit <= $maxM) {
                $hiddenDeg += self::RAY_STEP;
            }
            foreach ($this->platforms as [$p, $q]) {
                $d = self::raySegment($x, $y, $dx, $dy, $p[0], $p[1], $q[0], $q[1]);
                if ($d !== null && $d <= $platformMaxM && $d < $building) {
                    $platformDeg += self::RAY_STEP;
                    break;
                }
            }
        }

        return ['target_deg' => $targetDeg, 'platform_deg' => $platformDeg, 'hidden_deg' => $hiddenDeg, 'nearest_m' => $nearest];
    }

    /** Distance (mètres) du point au bord du bâtiment de la gare le plus proche, null s'il n'y en a pas. */
    public function targetDistance(float $x, float $y): ?float
    {
        $best = null;
        foreach ($this->targets as $poly) {
            if (self::contains($poly, $x, $y)) {
                return 0.0;
            }
            $n = count($poly);
            for ($i = 0; $i < $n; $i++) {
                $best = min($best ?? INF, self::pointSegment($x, $y, $poly[$i], $poly[($i + 1) % $n]));
            }
        }

        return $best;
    }

    /**
     * Polygones dont la boîte est à moins de $reach mètres du point.
     *
     * @param  list<list<array{0: float, 1: float}>>  $polys
     * @return list<list<array{0: float, 1: float}>>
     */
    private static function near(array $polys, float $x, float $y, float $reach): array
    {
        return array_values(array_filter($polys, function (array $poly) use ($x, $y, $reach): bool {
            $xs = array_column($poly, 0);
            $ys = array_column($poly, 1);

            return min($xs) - $reach <= $x && max($xs) + $reach >= $x && min($ys) - $reach <= $y && max($ys) + $reach >= $y;
        }));
    }

    /**
     * Distance du premier bord touché par le rayon, null si aucun.
     *
     * @param  list<list<array{0: float, 1: float}>>  $polys
     */
    private static function firstHit(array $polys, float $x, float $y, float $dx, float $dy): ?float
    {
        $best = null;
        foreach ($polys as $poly) {
            $n = count($poly);
            for ($i = 0; $i < $n; $i++) {
                $p = $poly[$i];
                $q = $poly[($i + 1) % $n];
                $d = self::raySegment($x, $y, $dx, $dy, $p[0], $p[1], $q[0], $q[1]);
                if ($d !== null && ($best === null || $d < $best)) {
                    $best = $d;
                }
            }
        }

        return $best;
    }

    /** Distance le long du rayon (origine, direction unitaire) jusqu'au segment, null s'il ne le coupe pas. */
    public static function raySegment(float $ox, float $oy, float $dx, float $dy, float $ax, float $ay, float $bx, float $by): ?float
    {
        $ex = $bx - $ax;
        $ey = $by - $ay;
        $den = $dx * $ey - $dy * $ex;
        if (abs($den) < 1e-12) {
            return null;
        }
        $wx = $ax - $ox;
        $wy = $ay - $oy;
        $t = ($wx * $ey - $wy * $ex) / $den;
        $u = ($wx * $dy - $wy * $dx) / $den;

        return $t >= 0 && $u >= 0 && $u <= 1 ? $t : null;
    }

    /**
     * Point dans un polygone (règle pair-impair).
     *
     * @param  list<array{0: float, 1: float}>  $poly
     */
    public static function contains(array $poly, float $x, float $y): bool
    {
        $in = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $poly[$i];
            [$xj, $yj] = $poly[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $in = ! $in;
            }
        }

        return $in;
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    public static function pointSegment(float $x, float $y, array $a, array $b): float
    {
        $ex = $b[0] - $a[0];
        $ey = $b[1] - $a[1];
        $len = $ex * $ex + $ey * $ey;
        $t = $len > 0 ? max(0.0, min(1.0, (($x - $a[0]) * $ex + ($y - $a[1]) * $ey) / $len)) : 0.0;

        return hypot($a[0] + $t * $ex - $x, $a[1] + $t * $ey - $y);
    }

    /**
     * Aire (m²) d'un polygone.
     *
     * @param  list<array{0: float, 1: float}>  $poly
     */
    public static function area(array $poly): float
    {
        $s = 0.0;
        $n = count($poly);
        for ($i = 0; $i < $n; $i++) {
            $s += $poly[$i][0] * $poly[($i + 1) % $n][1] - $poly[($i + 1) % $n][0] * $poly[$i][1];
        }

        return abs($s) / 2;
    }
}
