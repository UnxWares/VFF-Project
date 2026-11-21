<?php

namespace App\Services\Profiles;

/**
 * Index spatial des tronçons de voie de la BD TOPO d'une ou plusieurs tuiles : chaque segment
 * (deux sommets consécutifs) est rangé dans les cases d'une grille de CELL_DEG degré que couvre
 * son emprise. Pour un point du parcours, seuls les segments des cases voisines sont mesurés, au
 * lieu de tous les tronçons de la tuile (plusieurs milliers à Paris).
 *
 * Distances en projection plane locale (équirectangulaire), comme ProfilePath::locate.
 */
final class TrackIndex
{
    /** Côté d'une case de la grille, en degrés (environ 220 m sur 150 m en France). */
    public const CELL_DEG = 0.002;

    private const EARTH_RADIUS_M = 6_371_008.8;

    /** @var array<string, list<array{0: int, 1: int}>> case => [tronçon, segment] */
    private array $cells = [];

    /** @var list<array{kind: string, coords: list<array{0: float, 1: float}>, z: list<float|null>}> */
    private array $tracks = [];

    /**
     * @param  list<array{kind: string, coords: list<array{0: float, 1: float}>, z: list<float|null>}>  $tracks
     */
    public function __construct(array $tracks = [])
    {
        foreach ($tracks as $track) {
            $this->add($track);
        }
    }

    /** @param array{kind: string, coords: list<array{0: float, 1: float}>, z: list<float|null>} $track */
    public function add(array $track): void
    {
        if (count($track['coords']) < 2) {
            return;
        }
        $t = count($this->tracks);
        $this->tracks[] = $track;
        for ($s = 0, $n = count($track['coords']) - 1; $s < $n; $s++) {
            [$a, $b] = [$track['coords'][$s], $track['coords'][$s + 1]];
            for ($x = self::cell(min($a[0], $b[0])); $x <= self::cell(max($a[0], $b[0])); $x++) {
                for ($y = self::cell(min($a[1], $b[1])); $y <= self::cell(max($a[1], $b[1])); $y++) {
                    $this->cells[$x.':'.$y][] = [$t, $s];
                }
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->tracks === [];
    }

    /**
     * Tronçon le plus proche du point, à moins de `$toleranceM`, avec sa position (`ground`,
     * `tunnel`, `bridge`) et l'altitude interpolée entre les deux sommets qui encadrent le point
     * projeté (null si l'un d'eux n'en a pas), et l'écart au point en mètres. Aucun tronçon assez
     * proche : kind et z null, écart infini.
     *
     * @param  array{0: float, 1: float}  $point  [lon, lat]
     * @return array{kind: string|null, z: float|null, offset: float}
     */
    public function nearest(array $point, float $toleranceM): array
    {
        $kx = cos(deg2rad($point[1])) * self::EARTH_RADIUS_M * M_PI / 180;
        $ky = self::EARTH_RADIUS_M * M_PI / 180;
        // Marge en cases : la tolérance rapportée au plus petit côté d'une case.
        $reach = (int) ceil($toleranceM / (self::CELL_DEG * min($kx, $ky)));
        $cx = self::cell($point[0]);
        $cy = self::cell($point[1]);

        $best = ['kind' => null, 'z' => null, 'offset' => INF];
        $bestOffset = $toleranceM;
        $seen = [];
        for ($x = $cx - $reach; $x <= $cx + $reach; $x++) {
            for ($y = $cy - $reach; $y <= $cy + $reach; $y++) {
                foreach ($this->cells[$x.':'.$y] ?? [] as [$t, $s]) {
                    $key = $t.':'.$s;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $track = $this->tracks[$t];
                    $a = $track['coords'][$s];
                    $b = $track['coords'][$s + 1];
                    $ax = ($a[0] - $point[0]) * $kx;
                    $ay = ($a[1] - $point[1]) * $ky;
                    $dx = ($b[0] - $a[0]) * $kx;
                    $dy = ($b[1] - $a[1]) * $ky;
                    $len2 = $dx * $dx + $dy * $dy;
                    $u = $len2 > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $len2)) : 0.0;
                    $offset = hypot($ax + $u * $dx, $ay + $u * $dy);
                    if ($offset > $bestOffset) {
                        continue;
                    }
                    $bestOffset = $offset;
                    $za = $track['z'][$s] ?? null;
                    $zb = $track['z'][$s + 1] ?? null;
                    $best = [
                        'kind' => $track['kind'],
                        'z' => $za === null || $zb === null ? null : $za + $u * ($zb - $za),
                        'offset' => $offset,
                    ];
                }
            }
        }

        return $best;
    }

    private static function cell(float $deg): int
    {
        return (int) floor($deg / self::CELL_DEG);
    }
}
