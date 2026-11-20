<?php

namespace App\Services\Profiles;

/**
 * Calculs sur une série d'altitudes le long d'un parcours (points [distance_m, altitude_m|null]
 * triés par distance) : correction des ouvrages d'art, altitude interpolée, extrêmes, dénivelés
 * cumulés et déclivité la plus forte sur une fenêtre glissante.
 *
 * Fonctions pures, sans base ni réseau (tests : tests/Unit/Profiles/ProfileStatsTest.php).
 */
final class ProfileStats
{
    /** Seuil d'hystérésis des dénivelés cumulés : le bruit du modèle de terrain n'est pas compté. */
    public const CLIMB_THRESHOLD_M = 3.0;

    /** Fenêtre de la déclivité maximale (« rampe sur 1 km », pente moyenne par moindres carrés). */
    public const GRADE_WINDOW_M = 1000.0;

    /** Écart à la médiane locale au-delà duquel un point est une aberration. */
    public const SPIKE_THRESHOLD_M = 20.0;

    /**
     * Déclivité au-delà de laquelle un aller-retour est un artefact (120 ‰ : au-dessus de toute
     * ligne à adhérence française, 90 ‰ au plus sur Saint-Gervais - Vallorcine ; les lignes à
     * crémaillère ne sont pas traitées).
     */
    public const MAX_PLAUSIBLE_GRADE = 0.12;

    /** Demi-largeur (en points) de la fenêtre de la médiane locale. */
    private const SPIKE_HALF_WINDOW = 5;

    /**
     * Morceaux continus de la série : bornes [premier, dernier] (indices inclus), coupées là où
     * une lacune du parcours sépare deux points voisins. Sans lacune, un seul morceau.
     *
     * @param  list<array{0: int|float, 1: float|null}>  $points
     * @param  list<array{0: float, 1: float}>  $gaps
     * @return list<array{0: int, 1: int}>
     */
    public static function pieces(array $points, array $gaps = []): array
    {
        $n = count($points);
        if ($n === 0) {
            return [];
        }
        $pieces = [];
        $start = 0;
        for ($i = 1; $i < $n; $i++) {
            if (self::crossesGap($gaps, $points[$i - 1][0], $points[$i][0])) {
                $pieces[] = [$start, $i - 1];
                $start = $i;
            }
        }
        $pieces[] = [$start, $n - 1];

        return $pieces;
    }

    /**
     * Efface (altitude null) les points qui s'écartent de plus de SPIKE_THRESHOLD_M de la médiane
     * des points voisins (fenêtre de 2 × 5 + 1 points) : une voie ne saute pas de plusieurs
     * dizaines de mètres d'un point à l'autre. Cas vus : tronçon d'une autre voie croisée en tunnel
     * à un autre niveau, tablier ou fond de tranchée. Sur une pente régulière, la médiane d'une
     * fenêtre symétrique vaut le point central : les vraies déclivités ne sont pas touchées.
     * La fenêtre reste dans le morceau du point : de l'autre côté d'une lacune, la voie peut être
     * bien plus haute ou plus basse sans que rien soit faux.
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     * @param  list<array{0: float, 1: float}>  $gaps  lacunes [début, fin] du parcours
     * @return list<array{0: float, 1: float|null}>
     */
    public static function removeSpikes(array $points, array $gaps = []): array
    {
        $zs = array_column($points, 1);
        foreach (self::pieces($points, $gaps) as [$first, $last]) {
            for ($i = $first; $i <= $last; $i++) {
                if ($points[$i][1] === null) {
                    continue;
                }
                // Fenêtre symétrique, rétrécie près des bouts du morceau : sur une pente, une fenêtre
                // d'un seul côté aurait une médiane décalée et effacerait à tort le premier ou le
                // dernier point.
                $half = min(self::SPIKE_HALF_WINDOW, $i - $first, $last - $i);
                $window = array_values(array_filter(
                    array_slice($zs, $i - $half, 2 * $half + 1),
                    fn (?float $z): bool => $z !== null,
                ));
                if (count($window) < 3) {
                    continue;
                }
                sort($window);
                $median = $window[intdiv(count($window), 2)];
                if (abs($points[$i][1] - $median) > self::SPIKE_THRESHOLD_M) {
                    $points[$i][1] = null;
                }
            }
        }

        return self::removePeaks($points, $gaps);
    }

    /**
     * Efface les pics isolés entre deux trous (point à l'air libre entre deux tunnels, où la
     * médiane manque de voisins) : un point qu'on atteint et qu'on quitte avec une déclivité de
     * plus de MAX_PLAUSIBLE_GRADE, en sens contraires, par rapport aux points valides qui
     * l'encadrent dans le même morceau. Plusieurs passages, un pic pouvant en cacher un autre.
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     * @param  list<array{0: float, 1: float}>  $gaps
     * @return list<array{0: float, 1: float|null}>
     */
    private static function removePeaks(array $points, array $gaps): array
    {
        $pieces = self::pieces($points, $gaps);
        for ($pass = 0; $pass < 3; $pass++) {
            $peaks = [];
            foreach ($pieces as [$first, $last]) {
                array_push($peaks, ...self::peaksIn($points, $first, $last));
            }
            if ($peaks === []) {
                break;
            }
            foreach ($peaks as $i) {
                $points[$i][1] = null;
            }
        }

        return $points;
    }

    /**
     * Un passage de removePeaks sur les points [$first, $last] : indices des pics à effacer (un
     * pic sur deux au plus quand ils se suivent, le suivant est jugé au passage d'après).
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     * @return list<int>
     */
    private static function peaksIn(array $points, int $first, int $last): array
    {
        $valid = array_keys(array_filter(
            array_slice($points, $first, $last - $first + 1, true),
            fn (array $p): bool => $p[1] !== null,
        ));
        $peaks = [];
        for ($k = 1, $n = count($valid); $k < $n - 1; $k++) {
            [$a, $b, $c] = [$points[$valid[$k - 1]], $points[$valid[$k]], $points[$valid[$k + 1]]];
            $in = ($b[1] - $a[1]) / max(1.0, $b[0] - $a[0]);
            $out = ($c[1] - $b[1]) / max(1.0, $c[0] - $b[0]);
            if (abs($in) > self::MAX_PLAUSIBLE_GRADE && abs($out) > self::MAX_PLAUSIBLE_GRADE && $in * $out < 0) {
                $peaks[] = $valid[$k];
                $k++;
            }
        }

        return $peaks;
    }

    /**
     * Remplace l'altitude des points pris dans un ouvrage (tunnel, pont : le modèle de terrain y
     * donne le sommet de la montagne ou le fond de la vallée) et des trous de courte longueur par
     * une interpolation linéaire entre les deux points valides qui les encadrent. Une suite sans
     * voisin d'un côté prend l'altitude du seul voisin si elle est courte ; une suite plus longue
     * que `$maxHoleM` reste vide, sauf un ouvrage encadré des deux côtés. Les voisins sont pris
     * dans le même morceau : rien n'est interpolé par-dessus une lacune du parcours.
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     * @param  list<bool>  $inStructure  un drapeau par point
     * @param  list<array{0: float, 1: float}>  $gaps  lacunes [début, fin] du parcours
     * @return list<array{0: float, 1: float|null}>
     */
    public static function fillStructures(array $points, array $inStructure, float $maxHoleM = 1000.0, array $gaps = []): array
    {
        foreach (self::pieces($points, $gaps) as [$first, $last]) {
            $i = $first;
            while ($i <= $last) {
                if (! ($inStructure[$i] ?? false) && $points[$i][1] !== null) {
                    $i++;

                    continue;
                }
                // Suite de points à remplacer : [$i, $j[.
                $j = $i;
                $structure = false;
                while ($j <= $last && (($inStructure[$j] ?? false) || $points[$j][1] === null)) {
                    $structure = $structure || ($inStructure[$j] ?? false);
                    $j++;
                }
                $before = $i > $first ? $points[$i - 1] : null;
                $after = $j <= $last ? $points[$j] : null;
                $span = ($after[0] ?? $points[$j - 1][0]) - ($before[0] ?? $points[$i][0]);
                // Ouvrage encadré des deux côtés : interpolation, quelle que soit sa longueur. Ouvrage
                // ouvert d'un côté (tunnel qui passe la frontière) ou trou : seulement s'il est court.
                if (($structure && $before !== null && $after !== null) || $span <= $maxHoleM) {
                    for ($k = $i; $k < $j; $k++) {
                        $points[$k][1] = self::between($before, $after, $points[$k][0]);
                    }
                }
                $i = $j;
            }
        }

        return $points;
    }

    /**
     * @param  array{0: float, 1: float|null}|null  $a
     * @param  array{0: float, 1: float|null}|null  $b
     */
    private static function between(?array $a, ?array $b, float $d): ?float
    {
        if ($a === null || $b === null) {
            return $a[1] ?? $b[1] ?? null;
        }
        $span = $b[0] - $a[0];

        return $span > 0 ? $a[1] + ($b[1] - $a[1]) * ($d - $a[0]) / $span : $a[1];
    }

    /**
     * Altitude à une distance donnée, interpolée entre les deux points voisins ; null hors du
     * parcours ou si l'un des voisins n'a pas d'altitude.
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     */
    public static function altitudeAt(array $points, float $d): ?float
    {
        $n = count($points);
        if ($n === 0 || $d < $points[0][0] || $d > $points[$n - 1][0]) {
            return null;
        }
        $lo = 0;
        $hi = $n - 1;
        while ($hi - $lo > 1) {
            $mid = intdiv($lo + $hi, 2);
            if ($points[$mid][0] <= $d) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        if ($points[$lo][1] === null || $points[$hi][1] === null) {
            return $d === $points[$lo][0] ? $points[$lo][1] : ($d === $points[$hi][0] ? $points[$hi][1] : null);
        }

        return self::between($points[$lo], $points[$hi], $d);
    }

    /**
     * Altitudes extrêmes, dénivelés cumulés (hystérésis de CLIMB_THRESHOLD_M, remise à zéro après
     * chaque lacune) et déclivité la plus forte sur GRADE_WINDOW_M (ou sur toute la longueur pour
     * un parcours plus court), en ‰ : un mètre de dénivelé sur un kilomètre fait 1 ‰.
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     * @param  list<array{0: float, 1: float}>  $gaps  lacunes [début, fin] : aucune fenêtre ne les traverse
     * @param  list<bool>  $interpolated  points à l'altitude interpolée (un drapeau par point) : une
     *                                    fenêtre dont plus du quart des points le sont n'entre pas dans la déclivité maximale
     *                                    (dans un tunnel, la pente réelle est inconnue, et un tracé simplifié d'une boucle
     *                                    hélicoïdale la ferait paraître bien plus forte)
     * @return array{min_altitude_m: float, max_altitude_m: float, ascent_m: float, descent_m: float,
     *     max_grade_permil: float, max_grade_from_m: int, max_grade_to_m: int, max_grade_direction: string}|null
     */
    public static function summarize(array $points, array $gaps = [], array $interpolated = []): ?array
    {
        $valid = array_values(array_filter($points, fn (array $p): bool => $p[1] !== null));
        if (count($valid) < 2) {
            return null;
        }
        $zs = array_column($valid, 1);

        [$ascent, $descent] = self::climbs($points, $gaps);
        $grade = self::maxGrade($points, $gaps, $interpolated);

        return [
            'min_altitude_m' => round(min($zs), 1),
            'max_altitude_m' => round(max($zs), 1),
            'ascent_m' => round($ascent),
            'descent_m' => round($descent),
            'max_grade_permil' => round(abs($grade['permil']), 1),
            'max_grade_from_m' => (int) round($grade['from']),
            'max_grade_to_m' => (int) round($grade['to']),
            'max_grade_direction' => $grade['permil'] >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * @param  list<array{0: float, 1: float|null}>  $points
     * @param  list<array{0: float, 1: float}>  $gaps
     * @return array{0: float, 1: float}
     */
    private static function climbs(array $points, array $gaps): array
    {
        $ascent = 0.0;
        $descent = 0.0;
        $ref = null;
        $prevD = null;
        foreach ($points as [$d, $z]) {
            if ($z === null || ($prevD !== null && self::crossesGap($gaps, $prevD, $d))) {
                $ref = $z;
                $prevD = $d;

                continue;
            }
            $prevD = $d;
            if ($ref === null) {
                $ref = $z;

                continue;
            }
            if ($z - $ref >= self::CLIMB_THRESHOLD_M) {
                $ascent += $z - $ref;
                $ref = $z;
            } elseif ($ref - $z >= self::CLIMB_THRESHOLD_M) {
                $descent += $ref - $z;
                $ref = $z;
            }
        }

        return [$ascent, $descent];
    }

    /**
     * Fenêtre glissante partant de chaque point : pente de la droite des moindres carrés des
     * points de la fenêtre (et non l'écart entre ses deux bouts, trop sensible au bruit de
     * quelques mètres des altitudes). Signe positif pour une rampe dans le sens du parcours,
     * négatif pour une pente. Une fenêtre qui traverse une lacune ou un point sans altitude, qui
     * compte moins de trois points ou qui finit avant la fin du parcours, est ignorée.
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     * @param  list<array{0: float, 1: float}>  $gaps
     * @param  list<bool>  $interpolated
     * @return array{permil: float, from: float, to: float}
     */
    private static function maxGrade(array $points, array $gaps, array $interpolated): array
    {
        $n = count($points);
        $first = $points[0][0];
        $window = min(self::GRADE_WINDOW_M, $points[$n - 1][0] - $first);
        $best = ['permil' => 0.0, 'from' => $first, 'to' => $first + $window];
        if ($window <= 0) {
            return $best;
        }
        for ($i = 0; $i < $n; $i++) {
            $end = $points[$i][0] + $window;
            $slice = [];
            $guessed = 0;
            for ($j = $i; $j < $n && $points[$j][0] <= $end + 0.01; $j++) {
                $slice[] = $points[$j];
                $guessed += ($interpolated[$j] ?? false) ? 1 : 0;
            }
            if ($guessed * 4 > count($slice)) {
                continue;
            }
            // Fenêtre complète : son dernier point à moins d'un quart de fenêtre de la fin (pas de
            // 150 m : le dernier point tombe à 900 m).
            if ($points[$j - 1][0] - $points[$i][0] < 0.75 * $window || self::crossesGap($gaps, $points[$i][0], $end)) {
                continue;
            }
            $permil = self::slope($slice);
            if ($permil !== null && abs($permil) > abs($best['permil'])) {
                $best = ['permil' => $permil, 'from' => $points[$i][0], 'to' => $points[$j - 1][0]];
            }
        }

        return $best;
    }

    /**
     * Pente (‰) de la droite des moindres carrés ; null s'il manque une altitude ou s'il y a
     * moins de trois points.
     *
     * @param  list<array{0: float, 1: float|null}>  $points
     */
    public static function slope(array $points): ?float
    {
        $n = count($points);
        if ($n < 3) {
            return null;
        }
        $sd = 0.0;
        $sz = 0.0;
        foreach ($points as [$d, $z]) {
            if ($z === null) {
                return null;
            }
            $sd += $d;
            $sz += $z;
        }
        $md = $sd / $n;
        $mz = $sz / $n;
        $num = 0.0;
        $den = 0.0;
        foreach ($points as [$d, $z]) {
            $num += ($d - $md) * ($z - $mz);
            $den += ($d - $md) ** 2;
        }

        return $den > 0 ? $num / $den * 1000 : null;
    }

    /** @param list<array{0: float, 1: float}> $gaps */
    private static function crossesGap(array $gaps, float $a, float $b): bool
    {
        foreach ($gaps as [$from, $to]) {
            if ($from < $b - 0.5 && $to > $a + 0.5) {
                return true;
            }
        }

        return false;
    }
}
