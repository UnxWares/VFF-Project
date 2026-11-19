<?php

namespace App\Services\StationPositioning;

/**
 * Axe d'une ligne en coordonnées projetées (Lambert-93, mètres) : abscisse curviligne
 * d'un point (projection orthogonale), point à une abscisse.
 *
 * chain() recoud les morceaux du tracé de référence (ST_LineMerge laisse des trous et
 * des embranchements) bout à bout, du plus long aux plus proches, tant que le trou
 * à combler fait au plus $maxGap mètres ; un morceau qui se raccroche au milieu de
 * l'axe (embranchement, voie de service) n'en fait pas partie.
 */
final readonly class Polyline
{
    /** @var list<float> abscisse cumulée de chaque sommet */
    private array $cumulative;

    /** @param  list<array{0: float, 1: float}>  $points */
    public function __construct(public array $points)
    {
        $cum = [0.0];
        for ($i = 1; $i < count($points); $i++) {
            $cum[] = $cum[$i - 1] + hypot($points[$i][0] - $points[$i - 1][0], $points[$i][1] - $points[$i - 1][1]);
        }
        $this->cumulative = $cum;
    }

    public function length(): float
    {
        return $this->cumulative[count($this->cumulative) - 1];
    }

    /**
     * Abscisse (m) du point de l'axe le plus proche et distance (m) à l'axe.
     *
     * @return array{0: float, 1: float}
     */
    public function project(float $x, float $y): array
    {
        $best = [0.0, INF];
        for ($i = 0; $i < count($this->points) - 1; $i++) {
            [$ax, $ay] = $this->points[$i];
            [$bx, $by] = $this->points[$i + 1];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $len2 = $dx * $dx + $dy * $dy;
            $t = $len2 > 0 ? max(0.0, min(1.0, (($x - $ax) * $dx + ($y - $ay) * $dy) / $len2)) : 0.0;
            $d = hypot($ax + $t * $dx - $x, $ay + $t * $dy - $y);
            if ($d < $best[1]) {
                $best = [$this->cumulative[$i] + $t * sqrt($len2), $d];
            }
        }

        return $best;
    }

    /**
     * Point de l'axe à une abscisse (bornée à l'axe).
     *
     * @return array{0: float, 1: float}
     */
    public function pointAt(float $measure): array
    {
        $n = count($this->points);
        if ($n === 1 || $measure <= 0) {
            return $this->points[0];
        }
        if ($measure >= $this->length()) {
            return $this->points[$n - 1];
        }
        $lo = 0;
        $hi = $n - 1;
        while ($hi - $lo > 1) {
            $mid = intdiv($lo + $hi, 2);
            $this->cumulative[$mid] <= $measure ? $lo = $mid : $hi = $mid;
        }
        $seg = $this->cumulative[$hi] - $this->cumulative[$lo];
        $t = $seg > 0 ? ($measure - $this->cumulative[$lo]) / $seg : 0.0;

        return [
            $this->points[$lo][0] + $t * ($this->points[$hi][0] - $this->points[$lo][0]),
            $this->points[$lo][1] + $t * ($this->points[$hi][1] - $this->points[$lo][1]),
        ];
    }

    /**
     * Morceaux recousus en un axe (cf. classe). Null si aucun morceau.
     *
     * @param  list<list<array{0: float, 1: float}>>  $parts
     */
    public static function chain(array $parts, float $maxGap = 5000.0): ?self
    {
        $parts = array_values(array_filter($parts, fn ($p) => count($p) >= 2));
        if ($parts === []) {
            return null;
        }
        $lengths = array_map(fn ($p) => (new self($p))->length(), $parts);
        arsort($lengths);
        $order = array_keys($lengths);
        $axis = $parts[array_shift($order)];
        $rest = array_map(fn ($k) => $parts[$k], $order);
        while ($rest !== []) {
            $best = null;
            foreach ($rest as $k => $p) {
                foreach ([false, true] as $reverse) {
                    $q = $reverse ? array_reverse($p) : $p;
                    // Bout de l'axe → début du morceau (ajout en queue), fin du morceau → début de l'axe (en tête).
                    foreach (['tail' => [end($axis), $q[0]], 'head' => [$axis[0], $q[count($q) - 1]]] as $end => [$e, $s]) {
                        $d = hypot($e[0] - $s[0], $e[1] - $s[1]);
                        if ($best === null || $d < $best['d']) {
                            $best = ['d' => $d, 'k' => $k, 'end' => $end, 'q' => $q];
                        }
                    }
                }
            }
            if ($best['d'] > $maxGap) {
                break;
            }
            // Embranchement : le morceau est plus proche du milieu de l'axe que de son bout.
            $joint = $best['end'] === 'tail' ? $best['q'][0] : $best['q'][count($best['q']) - 1];
            $far = $best['end'] === 'tail' ? $best['q'][count($best['q']) - 1] : $best['q'][0];
            [, $farToAxis] = (new self($axis))->project($far[0], $far[1]);
            unset($rest[$best['k']]);
            if ($farToAxis < hypot($far[0] - $joint[0], $far[1] - $joint[1]) / 2) {
                continue; // morceau replié le long de l'axe (doublon, voie parallèle)
            }
            $axis = $best['end'] === 'tail' ? array_merge($axis, $best['q']) : array_merge($best['q'], $axis);
        }

        return new self($axis);
    }
}
