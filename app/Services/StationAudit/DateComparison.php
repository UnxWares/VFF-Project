<?php

namespace App\Services\StationAudit;

/**
 * Comparaison d'une date candidate à la date de VFF, à la précision la plus grossière des deux
 * (fonction pure, testée).
 *
 *   same         même date à la même précision ;
 *   more_precise d'accord, la candidate est plus fine (jour contre année : elle préciserait VFF) ;
 *   less_precise d'accord, la candidate est plus grossière ;
 *   differs      désaccord (delta_days = candidate moins VFF, en jours) ;
 *   new          VFF n'a pas de date.
 *
 * Une date VFF de précision « range » (intervalle des ères) compte comme une décennie.
 */
final class DateComparison
{
    /** Rang des précisions, de la plus grossière à la plus fine. */
    private const LEVEL = ['century' => 0, 'decade' => 1, 'range' => 1, 'year' => 2, 'month' => 3, 'day' => 4];

    /** @return array{comparison: string, delta_days: ?int} */
    public static function compare(string $date, string $precision, ?string $vffDate, ?string $vffPrecision): array
    {
        if ($vffDate === null || $vffDate === '') {
            return ['comparison' => 'new', 'delta_days' => null];
        }
        $a = self::LEVEL[$precision] ?? 2;
        $b = self::LEVEL[$vffPrecision ?? 'day'] ?? 2;
        $level = min($a, $b);
        $delta = (int) round((strtotime($date) - strtotime($vffDate)) / 86400);
        if (self::truncate($date, $level) !== self::truncate($vffDate, $level)) {
            return ['comparison' => 'differs', 'delta_days' => $delta];
        }

        return ['comparison' => match ($a <=> $b) {
            1 => 'more_precise',
            -1 => 'less_precise',
            default => 'same',
        }, 'delta_days' => $delta];
    }

    private static function truncate(string $date, int $level): string
    {
        return match ($level) {
            0 => substr($date, 0, 2),
            1 => substr($date, 0, 3),
            2 => substr($date, 0, 4),
            3 => substr($date, 0, 7),
            default => substr($date, 0, 10),
        };
    }
}
