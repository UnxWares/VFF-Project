<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\DateComparison;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * DateComparison : troncatures par précision (millénaire, mois partageant le dernier chiffre, siècle),
 * dates avec heure, écarts lointains et mois d'une date complète contre un intervalle des ères.
 */
class DateComparisonMutationTest extends TestCase
{
    private static function gap(string $a, string $b): int
    {
        return (int) (new DateTimeImmutable($b.' UTC'))->diff(new DateTimeImmutable($a.' UTC'))->format('%r%a');
    }

    public function test_two_days_sharing_their_last_digit_still_differ(): void
    {
        $this->assertSame(['comparison' => 'differs', 'delta_days' => 31], DateComparison::compare('1955-04-15', 'day', '1955-03-15', 'day'));
    }

    public function test_the_first_digit_of_the_year_counts_at_every_precision(): void
    {
        $this->assertSame('differs', DateComparison::compare('2955-03-15', 'day', '1955-03-15', 'day')['comparison']);
        $this->assertSame('differs', DateComparison::compare('2955-03-01', 'year', '1955-03-01', 'year')['comparison']);
        $this->assertSame('differs', DateComparison::compare('2955-03-01', 'month', '1955-03-15', 'month')['comparison']);
    }

    public function test_a_century_compares_by_its_number_across_the_year_two_thousand(): void
    {
        $this->assertSame('more_precise', DateComparison::compare('2000-01-01', 'day', '1999-06-01', 'century')['comparison']);
        $this->assertSame('less_precise', DateComparison::compare('1999-06-01', 'century', '2000-01-01', 'day')['comparison']);
        $this->assertSame('differs', DateComparison::compare('2001-01-01', 'day', '1999-06-01', 'century')['comparison']);
    }

    public function test_a_time_of_day_after_the_date_is_ignored(): void
    {
        $this->assertSame(['comparison' => 'same', 'delta_days' => 0], DateComparison::compare('1955-03-15T23:59:59', 'day', '1955-03-15', 'day'));
        $this->assertSame(['comparison' => 'same', 'delta_days' => 0], DateComparison::compare('1955-03-15', 'day', '1955-03-15T00:00:01', 'day'));
    }

    public function test_the_gap_in_days_is_exact_far_from_the_epoch(): void
    {
        $this->assertSame(self::gap('2300-01-01', '2000-01-01'), DateComparison::compare('2300-01-01', 'day', '2000-01-01', 'day')['delta_days']);
        $this->assertSame(self::gap('1830-06-01', '2026-01-01'), DateComparison::compare('1830-06-01', 'day', '2026-01-01', 'day')['delta_days']);
    }

    public function test_a_full_date_at_month_precision_spans_its_whole_month_against_a_range(): void
    {
        $this->assertSame(['comparison' => 'more_precise', 'delta_days' => 0], DateComparison::compare('1955-03-15', 'month', '1960-12-31', 'range'));
        $this->assertSame(['comparison' => 'more_precise', 'delta_days' => 0], DateComparison::compare('1960-12-15', 'month', '1960-12-31', 'range'));
        $this->assertSame(['comparison' => 'close', 'delta_days' => self::gap('1961-01-01', '1960-12-31')], DateComparison::compare('1961-01-15', 'month', '1960-12-31', 'range'));
    }
}
