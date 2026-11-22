<?php

namespace App\Services\Profiles;

/**
 * Altitude de la VOIE estimée d'après le modèle de terrain (RGE ALTI), là où la BD TOPO ne donne
 * pas l'altitude de la voie (option --estimate-track de vff:compute-line-profiles, éteinte par
 * défaut). Méthode étalonnée le 22/11/2026 (rapport `aprem-2111/voie-depuis-terrain.md`) :
 *
 * 1. coupe en travers du tracé, de -HALF_M à +HALF_M tous les 1 m (crossSection) : un modèle de
 *    terrain contient les remblais et les tranchées, la plateforme y est une bande plate ;
 * 2. plateforme (platform) : la bande de MIN_WIDTH_M à MAX_WIDTH_M dont l'altitude varie de moins
 *    de FLAT_TOLERANCE_M, la plus proche du tracé (à 2 m près, la plus large) ; sans bande plate,
 *    l'altitude du terrain au point du tracé ;
 * 3. lissage robuste le long de la ligne (smooth) : lissage de Whittaker (pénalité sur la dérivée
 *    seconde), poids de Tukey recalculés ROBUST_ITERATIONS fois pour écarter les plateformes mal
 *    reconnues, puis pente bornée à MAX_GRADE ;
 * 4. ponts et tunnels de la BD TOPO : interpolation entre les têtes, comme pour la voie mesurée
 *    (ProfileStats::fillStructures, déjà appliqué par LineProfileBuilder).
 *
 * Erreur mesurée contre le MNT LiDAR HD de l'IGN (pas de 50 cm) pris exactement sous la voie, sur
 * 2 642 points de voies en service (15 zones, plaine, remblais, tranchées, montagne), au pas de
 * 100 m du profil : ACCURACY. Elle dépend surtout de la position du tracé : à 5 m de la voie au
 * plus, 93 % des points sont à moins de 1 m ; à 10 m, 70 % ; à 20 m, 32 % (une bande plate voisine,
 * champ ou chemin, est alors prise pour la plateforme).
 */
final class TrackFromTerrain
{
    /** Valeur de `line_profile_points.source` des points estimés. */
    public const SOURCE = 'rge_track';

    /** Demi-largeur de la coupe en travers, en mètres (31 points tous les 1 m). */
    public const HALF_M = 15;

    public const MIN_WIDTH_M = 4;

    public const MAX_WIDTH_M = 14;

    /** Étendue d'altitude au-delà de laquelle une bande n'est plus plate, en mètres. */
    public const FLAT_TOLERANCE_M = 0.30;

    /** Force du lissage pour des points espacés de 20 m ; mise à l'échelle du pas (puissance 4). */
    public const LAMBDA_AT_20_M = 2000.0;

    public const ROBUST_ITERATIONS = 6;

    /** Pente bornée du profil lissé : au-delà de 120 ‰, aucune ligne à adhérence (ProfileStats). */
    public const MAX_GRADE = 0.12;

    /** Date de l'étalonnage servie par l'API. */
    public const CALIBRATED_ON = '2026-11-22';

    /**
     * Erreur mesurée (référence : MNT LiDAR HD sous la voie, hors ouvrages et leurs abords, pas de
     * 100 m), selon l'écart entre le tracé et la voie : écarts d'altitude (médiane, 90e et 99e
     * centiles, parts sous 1 m et 2 m) et écart de la pente moyenne sur 1 km (90e centile, part à
     * moins de 1 ‰).
     *
     * @var list<array{trace_offset_m: int, points: int, median_m: float, p90_m: float, p99_m: float,
     *     under_1m_share: float, under_2m_share: float, grade_1km_p90_permil: float, grade_1km_within_1_permil_share: float}>
     */
    public const ACCURACY = [
        ['trace_offset_m' => 5, 'points' => 549, 'median_m' => 0.22, 'p90_m' => 0.68, 'p99_m' => 2.62,
            'under_1m_share' => 0.931, 'under_2m_share' => 0.971, 'grade_1km_p90_permil' => 0.88, 'grade_1km_within_1_permil_share' => 0.907],
        ['trace_offset_m' => 10, 'points' => 549, 'median_m' => 0.49, 'p90_m' => 2.22, 'p99_m' => 4.38,
            'under_1m_share' => 0.703, 'under_2m_share' => 0.874, 'grade_1km_p90_permil' => 1.86, 'grade_1km_within_1_permil_share' => 0.659],
        ['trace_offset_m' => 20, 'points' => 549, 'median_m' => 2.01, 'p90_m' => 7.97, 'p99_m' => 16.91,
            'under_1m_share' => 0.321, 'under_2m_share' => 0.499, 'grade_1km_p90_permil' => 10.16, 'grade_1km_within_1_permil_share' => 0.258],
    ];

    /**
     * Ponts encadrés de voie au sol, interpolés entre les culées : écart avec l'altitude de la voie
     * de la BD TOPO sur le tablier (28 points au pas de 100 m).
     *
     * @var array{points: int, median_m: float, p90_m: float}
     */
    public const BRIDGE_ACCURACY = ['points' => 28, 'median_m' => 0.84, 'p90_m' => 2.40];

    /**
     * Méthode et erreur mesurée, telles que les sert l'API du profil (`track_estimate`).
     *
     * @return array{method: string, calibrated_on: string, reference: string, step_m: int,
     *     accuracy: list<array{trace_offset_m: int, points: int, median_m: float, p90_m: float, p99_m: float,
     *         under_1m_share: float, under_2m_share: float, grade_1km_p90_permil: float, grade_1km_within_1_permil_share: float}>,
     *     bridges: array{points: int, median_m: float, p90_m: float}}
     */
    public static function method(): array
    {
        return [
            'method' => 'platform_cross_section',
            'calibrated_on' => self::CALIBRATED_ON,
            'reference' => 'lidar_hd_mnt',
            'step_m' => 100,
            'accuracy' => self::ACCURACY,
            'bridges' => self::BRIDGE_ACCURACY,
        ];
    }

    /**
     * Points de la coupe en travers au point `$at` du parcours, perpendiculaire à la direction
     * donnée par `$before` et `$after` (points du parcours de part et d'autre), de -HALF_M à
     * +HALF_M tous les 1 m, de la droite vers la gauche dans le sens du parcours (ordre du script
     * d'étalonnage). Plan local (écart sous 1 cm à 15 m).
     *
     * @param  array{0: float, 1: float}  $before
     * @param  array{0: float, 1: float}  $at
     * @param  array{0: float, 1: float}  $after
     * @return list<array{0: float, 1: float}> [lon, lat], 2 × HALF_M + 1 points
     */
    public static function crossSection(array $before, array $at, array $after): array
    {
        $kx = cos(deg2rad($at[1])) * 111_320.0;
        $ky = 110_574.0;
        $dx = ($after[0] - $before[0]) * $kx;
        $dy = ($after[1] - $before[1]) * $ky;
        $norm = hypot($dx, $dy);
        // Parcours dégénéré (deux points confondus) : coupe est-ouest, sans effet sur la plateforme d'une voie nord-sud.
        [$nx, $ny] = $norm > 0 ? [-$dy / $norm, $dx / $norm] : [1.0, 0.0];
        $points = [];
        for ($s = -self::HALF_M; $s <= self::HALF_M; $s++) {
            $points[] = [$at[0] + $nx * $s / $kx, $at[1] + $ny * $s / $ky];
        }

        return $points;
    }

    /**
     * Altitude de la plateforme sur une coupe (altitudes tous les 1 m, tracé au milieu) : médiane
     * de la bande plate retenue, ou altitude du terrain au point du tracé sans bande plate. Null si
     * le point du tracé n'a pas d'altitude (hors couverture).
     *
     * @param  list<float|null>  $cross
     */
    public static function platform(array $cross): ?float
    {
        $n = count($cross);
        $at = intdiv($n, 2);
        if ($n === 0 || $cross[$at] === null) {
            return null;
        }
        $best = null;
        $i = 0;
        while ($i + self::MIN_WIDTH_M <= $n - 1) {
            $band = array_slice($cross, $i, self::MIN_WIDTH_M + 1);
            if (in_array(null, $band, true) || max($band) - min($band) > self::FLAT_TOLERANCE_M) {
                $i++;

                continue;
            }
            $j = $i + self::MIN_WIDTH_M;
            $lo = min($band);
            $hi = max($band);
            while ($j + 1 <= $n - 1 && $j - $i < self::MAX_WIDTH_M && $cross[$j + 1] !== null
                && max($hi, $cross[$j + 1]) - min($lo, $cross[$j + 1]) <= self::FLAT_TOLERANCE_M) {
                $j++;
                $lo = min($lo, $cross[$j]);
                $hi = max($hi, $cross[$j]);
            }
            $distance = $i <= $at && $at <= $j ? 0 : min(abs($at - $i), abs($at - $j));
            $width = $j - $i;
            if ($best === null || $distance < $best['distance'] - 2
                || (abs($distance - $best['distance']) <= 2 && $width > $best['width'])) {
                /** @var list<float> $flat */
                $flat = array_slice($cross, $i, $width + 1);
                $best = ['distance' => $distance, 'width' => $width, 'z' => self::median($flat)];
            }
            $i = $j + 1;
        }

        return $best === null ? $cross[$at] : $best['z'];
    }

    /**
     * Lissage robuste d'une suite d'altitudes régulièrement espacées de `$step` mètres le long de
     * la ligne (un morceau sans trou) : les points sans altitude restent sans altitude et ne pèsent
     * pas. Moins de 5 altitudes : suite rendue telle quelle.
     *
     * @param  list<float|null>  $z
     * @return list<float|null>
     */
    public static function smooth(array $z, float $step): array
    {
        $n = count($z);
        $known = array_keys(array_filter($z, fn (?float $v): bool => $v !== null));
        if (count($known) < 5 || $step <= 0) {
            return $z;
        }
        // Valeurs de départ des points sans altitude : interpolation, pour un système bien posé.
        $filled = self::interpolateNulls($z, $known);
        $lambda = self::LAMBDA_AT_20_M * (20.0 / $step) ** 4;
        $weights = array_map(fn (?float $v): float => $v === null ? 0.0 : 1.0, $z);
        $smoothed = $filled;
        for ($k = 0; $k < self::ROBUST_ITERATIONS; $k++) {
            $smoothed = self::whittaker($filled, $weights, $lambda);
            $residuals = [];
            foreach ($known as $i) {
                $residuals[] = abs($filled[$i] - $smoothed[$i]);
            }
            $scale = 4.685 * (self::median($residuals) * 1.4826 + 0.05);
            $next = [];
            for ($i = 0; $i < $n; $i++) {
                $u = $z[$i] === null ? 1.0 : ($filled[$i] - $smoothed[$i]) / $scale;
                $next[] = abs($u) < 1 ? (1 - $u * $u) ** 2 : 0.0;
            }
            if (array_sum($next) < 3) {
                break;
            }
            $weights = $next;
        }

        // Pente bornée : les pas plus raides que MAX_GRADE sont rabotés, le niveau moyen est gardé.
        $bounded = [0.0];
        for ($i = 1; $i < $n; $i++) {
            $grade = max(-self::MAX_GRADE, min(self::MAX_GRADE, ($smoothed[$i] - $smoothed[$i - 1]) / $step));
            $bounded[] = $bounded[$i - 1] + $grade * $step;
        }
        $shift = (array_sum($smoothed) - array_sum($bounded)) / $n;

        return array_map(fn (?float $v, float $b): ?float => $v === null ? null : $b + $shift, $z, $bounded);
    }

    /**
     * Lissage de Whittaker : minimise Σ w (x - z)² + λ Σ (Δ² x)², système pentadiagonal symétrique
     * résolu par Cholesky en bande (O(n)).
     *
     * @param  list<float>  $z
     * @param  list<float>  $w
     * @return list<float>
     */
    private static function whittaker(array $z, array $w, float $lambda): array
    {
        $n = count($z);
        // Diagonales de A = W + λ DᵀD : a0[i] = A[i][i], a1[i] = A[i][i+1], a2[i] = A[i][i+2].
        $a0 = array_map(fn (float $v): float => $v + 1e-9, $w);
        $a1 = array_fill(0, $n, 0.0);
        $a2 = array_fill(0, $n, 0.0);
        $c = [1.0, -2.0, 1.0];
        for ($k = 0; $k + 2 < $n; $k++) {
            for ($p = 0; $p < 3; $p++) {
                $a0[$k + $p] += $lambda * $c[$p] * $c[$p];
                if ($p < 2) {
                    $a1[$k + $p] += $lambda * $c[$p] * $c[$p + 1];
                }
            }
            $a2[$k] += $lambda * $c[0] * $c[2];
        }
        // Cholesky en bande : L[i][i], L[i][i-1], L[i][i-2].
        $l0 = $l1 = $l2 = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $l2[$i] = $i >= 2 ? $a2[$i - 2] / $l0[$i - 2] : 0.0;
            $l1[$i] = $i >= 1 ? ($a1[$i - 1] - ($i >= 2 ? $l2[$i] * $l1[$i - 1] : 0.0)) / $l0[$i - 1] : 0.0;
            $l0[$i] = sqrt(max(1e-12, $a0[$i] - $l1[$i] ** 2 - $l2[$i] ** 2));
        }
        $y = [];
        for ($i = 0; $i < $n; $i++) {
            $y[$i] = ($w[$i] * $z[$i] - ($i >= 1 ? $l1[$i] * $y[$i - 1] : 0.0) - ($i >= 2 ? $l2[$i] * $y[$i - 2] : 0.0)) / $l0[$i];
        }
        $x = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $x[$i] = ($y[$i] - ($i + 1 < $n ? $l1[$i + 1] * $x[$i + 1] : 0.0) - ($i + 2 < $n ? $l2[$i + 2] * $x[$i + 2] : 0.0)) / $l0[$i];
        }

        return $x;
    }

    /**
     * @param  list<float|null>  $z
     * @param  list<int>  $known  indices des altitudes connues (au moins une)
     * @return list<float>
     */
    private static function interpolateNulls(array $z, array $known): array
    {
        $out = [];
        $k = 0;
        foreach ($z as $i => $v) {
            if ($v !== null) {
                $out[] = $v;

                continue;
            }
            while ($k + 1 < count($known) && $known[$k + 1] < $i) {
                $k++;
            }
            $left = $known[$k] < $i ? $known[$k] : null;
            $right = null;
            foreach ($known as $j) {
                if ($j > $i) {
                    $right = $j;
                    break;
                }
            }
            $out[] = match (true) {
                $left !== null && $right !== null => $z[$left] + ($z[$right] - $z[$left]) * ($i - $left) / ($right - $left),
                $left !== null => (float) $z[$left],
                default => (float) $z[(int) $right],
            };
        }

        return $out;
    }

    /** @param list<float> $values */
    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
