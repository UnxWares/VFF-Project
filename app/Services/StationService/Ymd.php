<?php

namespace App\Services\StationService;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Dates GTFS sous forme d'entiers AAAAMMJJ (20261118) : comparables directement,
 * compacts dans les tableaux de milliers de jours par gare.
 */
final class Ymd
{
    public static function parse(string $value): int
    {
        $digits = str_replace('-', '', trim($value));
        if (preg_match('/^\d{8}$/', $digits) !== 1) {
            throw new InvalidArgumentException("Date invalide : {$value}");
        }

        return (int) $digits;
    }

    public static function toDate(int $ymd): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Ymd', (string) $ymd);
        if ($date === false) {
            throw new InvalidArgumentException("Date invalide : {$ymd}");
        }

        return $date;
    }

    public static function fromDate(DateTimeImmutable $date): int
    {
        return (int) $date->format('Ymd');
    }

    public static function iso(int $ymd): string
    {
        return self::toDate($ymd)->format('Y-m-d');
    }

    public static function addDays(int $ymd, int $days): int
    {
        return self::fromDate(self::toDate($ymd)->modify(sprintf('%+d days', $days)));
    }

    /** Nombre de jours de $from à $to inclus. */
    public static function span(int $from, int $to): int
    {
        return $to < $from ? 0 : (int) self::toDate($from)->diff(self::toDate($to))->days + 1;
    }
}
