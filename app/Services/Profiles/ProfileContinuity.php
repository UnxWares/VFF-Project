<?php

namespace App\Services\Profiles;

/**
 * Continuité du tracé de référence sous un profil en long : les trous du tracé (`line_profiles.gaps`)
 * moins les trous conventionnels que ProfileChain::append pose devant chaque embranchement mis bout à
 * bout (leur fin est le début de l'embranchement). Ce qui reste sont de vrais manques du tracé : la
 * courbe s'y interrompt, aucune altitude n'y est connue, et les chiffres du profil (altitudes
 * extrêmes, dénivelés, déclivité) ne portent que sur la partie connue.
 *
 * La longueur d'un trou est celle de la corde qui le franchit (distance à vol d'oiseau entre ses deux
 * bords, ProfileChain::addGap) : la voie disparue était au moins aussi longue.
 */
final class ProfileContinuity
{
    /**
     * Trous du tracé hors trous conventionnels des embranchements, dans l'ordre du parcours ; avec
     * `$minM`, seulement ceux d'au moins cette longueur.
     *
     * @param  list<array{from_m: int, to_m: int}>  $gaps
     * @param  list<array{from_m: int, to_m: int}>  $branches
     * @return list<array{from_m: int, to_m: int}>
     */
    public static function holes(array $gaps, array $branches, int $minM = 0): array
    {
        $starts = array_flip(array_map(fn (array $b): int => (int) $b['from_m'], $branches));
        $holes = array_values(array_filter(
            array_map(fn (array $g): array => ['from_m' => (int) $g['from_m'], 'to_m' => (int) $g['to_m']], $gaps),
            fn (array $g): bool => $g['to_m'] > $g['from_m'] && $minM <= $g['to_m'] - $g['from_m'] && ! isset($starts[$g['to_m']]),
        ));
        usort($holes, fn (array $a, array $b): int => $a['from_m'] <=> $b['from_m']);

        return $holes;
    }

    /**
     * Bilan servi avec le profil (`trace_holes`) : nombre de trous, longueur cumulée (à vol
     * d'oiseau), part du parcours qu'ils représentent (trous rapportés au parcours connu plus les
     * trous, de 0 à 1, au millième) et les trous eux-mêmes (`$minM` : voir holes()). `$coveredM` : longueur du parcours hors
     * trous (`line_profiles.covered_m`), nulle pour un profil ancien : la part est alors nulle.
     *
     * @param  list<array{from_m: int, to_m: int}>  $gaps
     * @param  list<array{from_m: int, to_m: int}>  $branches
     * @return array{count: int, length_m: int, share: float|null, ranges: list<array{from_m: int, to_m: int}>}
     */
    public static function summary(array $gaps, array $branches, ?int $coveredM, int $minM = 0): array
    {
        $holes = self::holes($gaps, $branches, $minM);
        $length = array_sum(array_map(fn (array $h): int => $h['to_m'] - $h['from_m'], $holes));
        $share = $coveredM === null || $coveredM + $length <= 0 ? null : round($length / ($coveredM + $length), 3);

        return ['count' => count($holes), 'length_m' => $length, 'share' => $share, 'ranges' => $holes];
    }
}
