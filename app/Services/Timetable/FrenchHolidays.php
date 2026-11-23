<?php

namespace App\Services\Timetable;

use DateTimeImmutable;

/**
 * Jours fériés de la France métropolitaine et choix des mardis « types » : un mardi dont la semaine
 * (du lundi au dimanche) ne compte aucun jour férié.
 */
final class FrenchHolidays
{
    /**
     * Jours fériés d'une année (AAAAMMJJ => nom) : fixes, puis ceux qui dépendent de Pâques.
     *
     * @return array<int, string>
     */
    public static function forYear(int $year): array
    {
        $easter = self::easter($year);
        $out = [
            $year * 10000 + 101 => 'jour de l\'an',
            $year * 10000 + 501 => 'fête du travail',
            $year * 10000 + 508 => 'victoire 1945',
            $year * 10000 + 714 => 'fête nationale',
            $year * 10000 + 815 => 'assomption',
            $year * 10000 + 1101 => 'toussaint',
            $year * 10000 + 1111 => 'armistice 1918',
            $year * 10000 + 1225 => 'noël',
        ];
        foreach (['+1 day' => 'lundi de Pâques', '+39 days' => 'ascension', '+50 days' => 'lundi de Pentecôte'] as $shift => $name) {
            $out[(int) $easter->modify($shift)->format('Ymd')] = $name;
        }

        return $out;
    }

    /** Dimanche de Pâques (calendrier grégorien, algorithme de Meeus, Jones et Butcher). */
    public static function easter(int $year): DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $g = intdiv(8 * $b + 13, 25);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 19 * $l, 433);
        $month = intdiv($h + $l - 7 * $m + 90, 25);
        $day = ($h + $l - 7 * $m + 33 * $month + 19) % 32;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /** La semaine (lundi au dimanche) qui contient ce jour n'a aucun jour férié. */
    public static function isCleanWeek(DateTimeImmutable $day): bool
    {
        $monday = $day->modify('monday this week');
        $holidays = self::forYear((int) $monday->format('Y')) + self::forYear((int) $monday->modify('+6 days')->format('Y'));
        for ($i = 0; $i < 7; $i++) {
            if (isset($holidays[(int) $monday->modify("+{$i} days")->format('Ymd')])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mardis de semaines sans jour férié entre deux dates (AAAAMMJJ, bornes comprises), du plus proche au plus lointain.
     *
     * @return list<int>
     */
    public static function cleanTuesdays(int $from, int $to): array
    {
        $out = [];
        $day = (new DateTimeImmutable((string) $from))->modify('tuesday this week');
        $day = (int) $day->format('Ymd') < $from ? $day->modify('+1 week') : $day;
        for (; (int) $day->format('Ymd') <= $to; $day = $day->modify('+1 week')) {
            if (self::isCleanWeek($day)) {
                $out[] = (int) $day->format('Ymd');
            }
        }

        return $out;
    }
}
