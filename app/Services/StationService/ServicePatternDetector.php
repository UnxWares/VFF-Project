<?php

namespace App\Services\StationService;

use App\Enums\ServicePattern;

/**
 * Motif de desserte d'une gare sur une fenêtre de dates, à partir du nombre
 * d'arrêts par jour (GtfsFeedReader). Fonctions pures, une règle par seuil :
 *
 *   - trois jours desservis au plus : ponctuelle (Sporadic) ;
 *   - au moins 75 % des semaines desservies : régulière, puis
 *       quotidienne si au moins 80 % des jours le sont,
 *       en semaine si 95 % des jours desservis sont du lundi au vendredi,
 *       le week-end si 90 % sont un samedi ou un dimanche,
 *       sinon certains jours ;
 *   - sinon, selon la place des jours desservis dans la fenêtre (marge de 21 jours) :
 *       au milieu seulement : saisonnière ; à partir d'une date : Starts ;
 *       jusqu'à une date : Ends ; ailleurs : certaines semaines (Intermittent).
 */
final class ServicePatternDetector
{
    public const REGULAR_WEEK_SHARE = 0.75;

    public const DAILY_DAY_SHARE = 0.8;

    public const WEEKDAY_SHARE = 0.95;

    public const WEEKEND_SHARE = 0.9;

    public const SPORADIC_MAX_DAYS = 3;

    public const EDGE_DAYS = 21;

    /** @param  array<int, int>  $days  AAAAMMJJ => arrêts */
    public function profile(array $days, int $from, int $to): ?ServiceProfile
    {
        $served = array_filter($days, fn (int $n, int $day): bool => $n > 0 && $day >= $from && $day <= $to, ARRAY_FILTER_USE_BOTH);
        if ($served === []) {
            return null;
        }
        ksort($served);
        $first = array_key_first($served);
        $last = array_key_last($served);
        $weekdays = array_fill(1, 7, 0);
        $servedWeeks = [];
        foreach (array_keys($served) as $day) {
            $date = Ymd::toDate($day);
            $weekdays[(int) $date->format('N')]++;
            $servedWeeks[$date->format('oW')] = true;
        }
        $weeks = $this->weeks($from, $to);
        $pattern = $this->pattern(count($served), Ymd::span($from, $to), count($servedWeeks), $weeks, $weekdays,
            $first > Ymd::addDays($from, self::EDGE_DAYS), $last < Ymd::addDays($to, -self::EDGE_DAYS));

        return new ServiceProfile($pattern, array_sum($served), count($served), $from, $to, count($servedWeeks), $weeks,
            $first, $last, $weekdays);
    }

    /** @param  array<int, int>  $weekdays */
    public function pattern(int $servedDays, int $windowDays, int $servedWeeks, int $weeks, array $weekdays, bool $startsLate, bool $endsEarly): ServicePattern
    {
        if ($servedDays <= self::SPORADIC_MAX_DAYS) {
            return ServicePattern::Sporadic;
        }
        if ($servedWeeks >= self::REGULAR_WEEK_SHARE * $weeks) {
            $weekend = $weekdays[6] + $weekdays[7];

            return match (true) {
                $servedDays >= self::DAILY_DAY_SHARE * $windowDays => ServicePattern::Daily,
                $servedDays - $weekend >= self::WEEKDAY_SHARE * $servedDays => ServicePattern::Weekdays,
                $weekend >= self::WEEKEND_SHARE * $servedDays => ServicePattern::Weekend,
                default => ServicePattern::SomeDays,
            };
        }

        return match (true) {
            $startsLate && $endsEarly => ServicePattern::Seasonal,
            $startsLate => ServicePattern::Starts,
            $endsEarly => ServicePattern::Ends,
            default => ServicePattern::Intermittent,
        };
    }

    /** Semaines ISO touchées par la fenêtre. */
    private function weeks(int $from, int $to): int
    {
        $weeks = [];
        $last = Ymd::toDate($to);
        for ($day = Ymd::toDate($from); $day <= $last; $day = $day->modify('+1 day')) {
            $weeks[$day->format('oW')] = true;
        }

        return count($weeks);
    }
}
