<?php

namespace App\Services\Profiles;

/**
 * Parcours ordonné d'une ligne (suite de sommets WGS 84 [lon, lat]) le long duquel on mesure le
 * profil en long : distances cumulées, lacunes (sauts entre deux morceaux du tracé, sans altitude),
 * points d'échantillonnage et projection d'un lieu (gare) sur le parcours.
 *
 * Distances géodésiques (haversine, sphère de 6 371 km) : à l'échelle d'une ligne, l'écart avec
 * l'ellipsoïde reste sous 0,5 %, bien en deçà de la précision des tracés.
 */
final readonly class ProfilePath
{
    private const EARTH_RADIUS_M = 6_371_008.8;

    /** @var list<float> distance cumulée de chaque sommet depuis le début, en mètres */
    public array $cumulative;

    /**
     * @param  list<array{0: float, 1: float}>  $coords  sommets [lon, lat] dans l'ordre du parcours
     * @param  list<array{0: float, 1: float}>  $gaps  lacunes [début, fin] en mètres le long du parcours
     * @param  array<int, float>  $jumps  longueur imposée du segment qui ARRIVE au sommet d'indice
     *                                    donné (saut vers un embranchement : longueur conventionnelle, pas la distance à vol d'oiseau)
     */
    public function __construct(public array $coords, public array $gaps = [], public array $jumps = [])
    {
        $cum = [0.0];
        for ($i = 1, $n = count($coords); $i < $n; $i++) {
            $cum[] = $cum[$i - 1] + ($jumps[$i] ?? self::distance($coords[$i - 1], $coords[$i]));
        }
        $this->cumulative = $cum;
    }

    public function length(): float
    {
        return $this->cumulative[count($this->cumulative) - 1];
    }

    /** Le même parcours pris dans l'autre sens (lacunes comprises). */
    public function reversed(): self
    {
        $length = $this->length();
        $gaps = array_map(fn (array $g): array => [$length - $g[1], $length - $g[0]], array_reverse($this->gaps));
        $n = count($this->coords);
        $jumps = [];
        foreach ($this->jumps as $i => $m) {
            // Le segment (i-1, i) devient (n-1-i, n-i) : il arrive au sommet n-i.
            $jumps[$n - $i] = $m;
        }

        return new self(array_reverse($this->coords), $gaps, $jumps);
    }

    /**
     * Pas d'échantillonnage adapté à la longueur : 100 m jusqu'à 200 km, puis par paliers de 50 m
     * pour rester sous 2 000 points environ, 250 m au plus (rampe sur 1 km : quatre points au moins).
     */
    public static function stepFor(float $lengthM): int
    {
        $step = (int) (ceil($lengthM / 2000 / 50) * 50);

        return max(100, min(250, $step));
    }

    /**
     * Distances d'échantillonnage : tous les `$step` mètres, plus les deux bouts du parcours et les
     * deux bords de chaque lacune ; aucun point à l'intérieur d'une lacune.
     *
     * @return list<float>
     */
    public function sampleDistances(int $step): array
    {
        $length = $this->length();
        $marks = [0.0, $length];
        for ($d = $step; $d < $length; $d += $step) {
            $marks[] = (float) $d;
        }
        foreach ($this->gaps as [$from, $to]) {
            $marks[] = $from;
            $marks[] = $to;
        }
        $marks = array_filter($marks, fn (float $d): bool => ! $this->inGap($d));
        sort($marks);

        // Deux marques à moins d'un mètre (bord de lacune sur un multiple du pas) : une seule.
        $out = [];
        foreach ($marks as $d) {
            if ($out === [] || $d - $out[count($out) - 1] >= 1.0) {
                $out[] = $d;
            }
        }

        return $out;
    }

    /** Vrai si la distance tombe strictement à l'intérieur d'une lacune. */
    public function inGap(float $d): bool
    {
        foreach ($this->gaps as [$from, $to]) {
            if ($d > $from + 0.5 && $d < $to - 0.5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vrai si une lacune sépare les deux distances.
     */
    public function gapBetween(float $a, float $b): bool
    {
        [$lo, $hi] = $a <= $b ? [$a, $b] : [$b, $a];
        foreach ($this->gaps as [$from, $to]) {
            if ($from < $hi - 0.5 && $to > $lo + 0.5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Point [lon, lat] à la distance donnée (interpolation linéaire entre deux sommets).
     *
     * @return array{0: float, 1: float}
     */
    public function pointAt(float $d): array
    {
        $n = count($this->coords);
        if ($d <= 0 || $n === 1) {
            return $this->coords[0];
        }
        if ($d >= $this->length()) {
            return $this->coords[$n - 1];
        }
        // Recherche dichotomique du segment qui contient $d.
        $lo = 0;
        $hi = $n - 1;
        while ($hi - $lo > 1) {
            $mid = intdiv($lo + $hi, 2);
            if ($this->cumulative[$mid] <= $d) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        $span = $this->cumulative[$hi] - $this->cumulative[$lo];
        $t = $span > 0 ? ($d - $this->cumulative[$lo]) / $span : 0.0;

        return [
            $this->coords[$lo][0] + $t * ($this->coords[$hi][0] - $this->coords[$lo][0]),
            $this->coords[$lo][1] + $t * ($this->coords[$hi][1] - $this->coords[$lo][1]),
        ];
    }

    /**
     * Projection d'un lieu sur le parcours : distance le long du parcours du point le plus proche,
     * et écart entre le lieu et ce point (mètres). Projection plane locale par segment
     * (équirectangulaire), largement suffisante à l'échelle d'un segment de tracé.
     *
     * `segment` : indice du premier sommet du segment le plus proche ; `t` : position sur ce
     * segment (0 à 1), pour interpoler une valeur portée par les sommets (altitude).
     *
     * @param  array{0: float, 1: float}  $point  [lon, lat]
     * @return array{distance: float, offset: float, segment: int, t: float}
     */
    public function locate(array $point): array
    {
        $best = ['distance' => 0.0, 'offset' => INF, 'segment' => 0, 't' => 0.0];
        $kx = cos(deg2rad($point[1])) * self::EARTH_RADIUS_M * M_PI / 180;
        $ky = self::EARTH_RADIUS_M * M_PI / 180;
        for ($i = 1, $n = count($this->coords); $i < $n; $i++) {
            $ax = ($this->coords[$i - 1][0] - $point[0]) * $kx;
            $ay = ($this->coords[$i - 1][1] - $point[1]) * $ky;
            $bx = ($this->coords[$i][0] - $point[0]) * $kx;
            $by = ($this->coords[$i][1] - $point[1]) * $ky;
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $len2 = $dx * $dx + $dy * $dy;
            $t = $len2 > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $len2)) : 0.0;
            $px = $ax + $t * $dx;
            $py = $ay + $t * $dy;
            $offset = sqrt($px * $px + $py * $py);
            if ($offset < $best['offset']) {
                $segment = $this->cumulative[$i] - $this->cumulative[$i - 1];
                $best = ['distance' => $this->cumulative[$i - 1] + $t * $segment, 'offset' => $offset, 'segment' => $i - 1, 't' => $t];
            }
        }

        return $best;
    }

    /**
     * Distance géodésique entre deux points [lon, lat], en mètres (haversine).
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    public static function distance(array $a, array $b): float
    {
        $dLat = deg2rad($b[1] - $a[1]);
        $dLon = deg2rad($b[0] - $a[0]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[1])) * cos(deg2rad($b[1])) * sin($dLon / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($h)));
    }
}
