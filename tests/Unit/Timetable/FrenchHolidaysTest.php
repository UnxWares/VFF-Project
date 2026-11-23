<?php

namespace Tests\Unit\Timetable;

use App\Services\Timetable\FrenchHolidays;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class FrenchHolidaysTest extends TestCase
{
    public function test_easter_is_computed_for_known_years(): void
    {
        $this->assertSame('2026-04-05', FrenchHolidays::easter(2026)->format('Y-m-d'));
        $this->assertSame('2027-03-28', FrenchHolidays::easter(2027)->format('Y-m-d'));
        $this->assertSame('2024-03-31', FrenchHolidays::easter(2024)->format('Y-m-d'));
    }

    public function test_a_year_has_eleven_holidays_including_those_that_follow_easter(): void
    {
        $holidays = FrenchHolidays::forYear(2026);

        $this->assertCount(11, $holidays);
        $this->assertArrayHasKey(20260406, $holidays, 'lundi de Pâques');
        $this->assertArrayHasKey(20260514, $holidays, 'ascension');
        $this->assertArrayHasKey(20260525, $holidays, 'lundi de Pentecôte');
        $this->assertArrayHasKey(20261111, $holidays);
    }

    public function test_a_week_with_a_holiday_is_not_clean_even_when_the_holiday_is_on_another_day(): void
    {
        // 11 novembre 2026 : mercredi ; le mardi 10 est dans la même semaine.
        $this->assertFalse(FrenchHolidays::isCleanWeek(new DateTimeImmutable('2026-11-10')));
        $this->assertTrue(FrenchHolidays::isCleanWeek(new DateTimeImmutable('2026-11-17')));
        // La semaine du 1er janvier 2027 (vendredi) commence en 2026 : l'année suivante compte.
        $this->assertFalse(FrenchHolidays::isCleanWeek(new DateTimeImmutable('2026-12-29')));
    }

    public function test_clean_tuesdays_are_tuesdays_in_the_range_without_holiday_weeks(): void
    {
        $days = FrenchHolidays::cleanTuesdays(20261101, 20261231);

        $this->assertSame([20261103, 20261117, 20261124, 20261201, 20261208, 20261215], $days);
        foreach ($days as $day) {
            $this->assertSame('2', (new DateTimeImmutable((string) $day))->format('N'));
        }
    }

    public function test_the_range_bounds_are_included(): void
    {
        $this->assertSame([20261208], FrenchHolidays::cleanTuesdays(20261208, 20261208));
        $this->assertSame([], FrenchHolidays::cleanTuesdays(20261209, 20261214));
    }
}
