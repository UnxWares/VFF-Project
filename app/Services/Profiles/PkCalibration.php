<?php

namespace App\Services\Profiles;

/**
 * PK estimé de chaque point du profil, calé sur les PK officiels des gares placées sur le parcours.
 *
 * Les gares de calage sont les « ancres » [distance le long du parcours, PK officiel]. Seule la plus
 * longue suite d'ancres dont le PK croît avec la distance est gardée : une gare mal placée, ou un
 * PK d'une autre origine (ligne prolongée, changement de numérotation), ne fait pas reculer le PK.
 * Entre deux ancres, le PK est interpolé linéairement (trous compris, où la distance est mesurée en
 * ligne droite) ; avant la première et après la dernière, il avance d'un mètre par mètre de
 * parcours. Sans ancre, aucun PK n'est estimé.
 *
 * Fonctions pures (tests : tests/Unit/Profiles/PkCalibrationTest.php).
 */
final class PkCalibration
{
    /**
     * Ancres gardées, triées par distance : plus longue suite strictement croissante en distance et
     * en PK (à égalité de longueur, la première trouvée).
     *
     * @param  list<array{0: float, 1: int}>  $anchors  [distance_m, pk_m]
     * @return list<array{0: float, 1: int}>
     */
    public static function monotone(array $anchors): array
    {
        usort($anchors, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $n = count($anchors);
        if ($n <= 1) {
            return $anchors;
        }
        // Programmation dynamique en O(n²) : quelques dizaines de gares par ligne au plus.
        $best = array_fill(0, $n, 1);
        $prev = array_fill(0, $n, -1);
        for ($i = 1; $i < $n; $i++) {
            for ($j = 0; $j < $i; $j++) {
                if ($anchors[$j][0] < $anchors[$i][0] && $anchors[$j][1] < $anchors[$i][1] && $best[$i] < $best[$j] + 1) {
                    $best[$i] = $best[$j] + 1;
                    $prev[$i] = $j;
                }
            }
        }
        $end = (int) array_search(max($best), $best, true);
        $kept = [];
        for ($i = $end; $i >= 0; $i = $prev[$i]) {
            $kept[] = $anchors[$i];
        }

        return array_reverse($kept);
    }

    /**
     * PK estimé (mètres, entier) à chaque distance ; null partout sans ancre.
     *
     * @param  list<array{0: float, 1: int}>  $anchors  ancres déjà filtrées par monotone()
     * @param  list<float|int>  $distances
     * @return list<int|null>
     */
    public static function estimate(array $anchors, array $distances): array
    {
        $n = count($anchors);
        if ($n === 0) {
            return array_fill(0, count($distances), null);
        }
        $out = [];
        foreach ($distances as $d) {
            if ($d <= $anchors[0][0]) {
                $pk = $anchors[0][1] - ($anchors[0][0] - $d);
            } elseif ($d >= $anchors[$n - 1][0]) {
                $pk = $anchors[$n - 1][1] + ($d - $anchors[$n - 1][0]);
            } else {
                $k = 1;
                while ($anchors[$k][0] < $d) {
                    $k++;
                }
                [$d0, $p0] = $anchors[$k - 1];
                [$d1, $p1] = $anchors[$k];
                $pk = $p0 + ($p1 - $p0) * ($d - $d0) / ($d1 - $d0);
            }
            $out[] = (int) round($pk);
        }

        return $out;
    }
}
