<?php

namespace App\Services\StationAudit;

/**
 * Comparaison d'une date candidate à la date de VFF, à la précision la plus grossière des deux
 * (fonction pure, testée).
 *
 *   same         même date à la même précision ;
 *   more_precise d'accord, la candidate est plus fine (jour contre année : elle préciserait VFF) ;
 *   less_precise d'accord, la candidate est plus grossière ;
 *   close        date VFF « range » seulement : la candidate tombe à moins d'un an de l'intervalle ;
 *   differs      désaccord (delta_days = candidate moins VFF, en jours) ;
 *   new          VFF n'a pas de date.
 *
 * Une date VFF de précision « range » est un intervalle des ères (fermeture entre deux ères) dont VFF
 * ne garde que la borne haute : la fin de l'ère (1960-12-31 pour « entre 1951 et 1960 ») ou le jour du
 * calcul. La borne basse est le lendemain de l'ère précédente ($vffFrom, sinon déduite des ères
 * décennales : 1951-01-01 pour 1960-12-31, 2021-01-01 pour 2026-11-21). La candidate, prise sur toute
 * l'étendue de sa précision (1955 = toute l'année 1955), est d'accord quand elle touche l'intervalle,
 * proche quand elle en reste à moins d'un an, sinon différente ; delta_days est alors l'écart à la borne
 * la plus proche (0 dans l'intervalle). Un intervalle des ères a le rang d'une décennie.
 */
final class DateComparison
{
    /** Rang des précisions, de la plus grossière à la plus fine. */
    private const LEVEL = ['century' => 0, 'decade' => 1, 'range' => 1, 'year' => 2, 'month' => 3, 'day' => 4];

    /** Écart maximal (jours) entre une candidate et un intervalle des ères pour qu'elle soit « proche ». */
    public const RANGE_CLOSE_DAYS = 366;

    /** @return array{comparison: string, delta_days: ?int} */
    public static function compare(string $date, string $precision, ?string $vffDate, ?string $vffPrecision, ?string $vffFrom = null): array
    {
        if ($vffDate === null || $vffDate === '') {
            return ['comparison' => 'new', 'delta_days' => null];
        }
        $a = self::LEVEL[$precision] ?? 2;
        $b = self::LEVEL[$vffPrecision ?? 'day'] ?? 2;
        if ($vffPrecision === 'range') {
            return self::inRange($date, $precision, $vffFrom ?? self::eraStart($vffDate), $vffDate, $a <=> $b);
        }
        $level = min($a, $b);
        $delta = self::days($date) - self::days($vffDate);
        if (self::truncate($date, $level) !== self::truncate($vffDate, $level)) {
            return ['comparison' => 'differs', 'delta_days' => $delta];
        }

        return ['comparison' => self::agreement($a <=> $b), 'delta_days' => $delta];
    }

    /** @return array{comparison: string, delta_days: int} */
    private static function inRange(string $date, string $precision, string $from, string $to, int $finer): array
    {
        [$start, $end] = self::span($date, $precision);
        $lo = self::days($from);
        $hi = self::days($to);
        $delta = match (true) {
            $end < $lo => $end - $lo,
            $start > $hi => $start - $hi,
            default => 0,
        };

        return ['comparison' => match (true) {
            $delta === 0 => self::agreement($finer),
            abs($delta) < self::RANGE_CLOSE_DAYS => 'close',
            default => 'differs',
        }, 'delta_days' => $delta];
    }

    private static function agreement(int $finer): string
    {
        return match ($finer) {
            1 => 'more_precise',
            -1 => 'less_precise',
            default => 'same',
        };
    }

    /** Premier jour de l'intervalle des ères décennales qui se termine à $to (1960-12-31 : 1951-01-01). */
    private static function eraStart(string $to): string
    {
        return sprintf('%04d-01-01', intdiv((int) substr($to, 0, 4) - 1, 10) * 10 + 1);
    }

    /**
     * Étendue d'une date à sa précision, en jours depuis l'époque Unix : [premier jour, dernier jour].
     *
     * @return array{0: int, 1: int}
     */
    private static function span(string $date, string $precision): array
    {
        $y = (int) substr($date, 0, 4);
        [$first, $last] = match ($precision) {
            // Siècle rangé à sa première année (1801 pour le XIXe), comme WikidataStationDates::time.
            'century' => [sprintf('%04d-01-01', intdiv($y - 1, 100) * 100 + 1), sprintf('%04d-12-31', intdiv($y - 1, 100) * 100 + 100)],
            'decade' => [sprintf('%04d-01-01', intdiv($y, 10) * 10), sprintf('%04d-12-31', intdiv($y, 10) * 10 + 9)],
            'year' => [sprintf('%04d-01-01', $y), sprintf('%04d-12-31', $y)],
            'month' => [substr($date, 0, 7).'-01', date('Y-m-t', strtotime(substr($date, 0, 7).'-01'))],
            default => [substr($date, 0, 10), substr($date, 0, 10)],
        };

        return [self::days($first), self::days($last)];
    }

    /** Jours depuis l'époque Unix, sans fuseau ni changement d'heure. */
    private static function days(string $date): int
    {
        return intdiv((int) strtotime(substr($date, 0, 10).' UTC'), 86400);
    }

    private static function truncate(string $date, int $level): string
    {
        return match ($level) {
            // Numéro du siècle (1801 à 1900 : XIXe), comme WikidataStationDates::time.
            0 => (string) intdiv((int) substr($date, 0, 4) - 1, 100),
            1 => substr($date, 0, 3),
            2 => substr($date, 0, 4),
            3 => substr($date, 0, 7),
            default => substr($date, 0, 10),
        };
    }
}
