<?php

namespace Tests\Unit\Timetable;

use App\Services\StationService\FeedDefinition;
use App\Services\StationService\GtfsArchive;
use App\Services\Timetable\TimetableProfile;
use App\Services\Timetable\TypicalDay;
use App\Services\Timetable\TypicalDayReader;
use PHPUnit\Framework\TestCase;

/**
 * Lecture d'un jour type (tests/Fixtures/Timetable/mini : du 16/11/2026 au 31/01/2027) : une règle par test.
 */
class TypicalDayReaderTest extends TestCase
{
    private const DIR = __DIR__.'/../../Fixtures/Timetable/mini';

    private static function definition(): FeedDefinition
    {
        return FeedDefinition::fromArray(['key' => 'sncf', 'name' => 'Test', 'provider' => 'sncf', 'licence' => 'ODbL', 'page' => 'https://example.org',
            'url' => 'https://example.org/gtfs.zip', 'modes' => ['rail'], 'uic_fields' => ['parent_station', 'stop_id'], 'uic_pattern' => '/OCE(?:[^-]*-)?([0-9]{8})$/']);
    }

    private static function read(int $date): TypicalDay
    {
        return (new TypicalDayReader)->read(new GtfsArchive(self::DIR), TimetableProfile::sncf(), self::definition(), $date);
    }

    public function test_a_tuesday_keeps_the_weekday_trains_with_their_type(): void
    {
        $day = self::read(20261208);

        $this->assertSame(['ter', 'tgv'], array_column($day->runs, 'type'));
        $this->assertSame('2026-11-16', $day->version);
        $this->assertSame(['2026-11-16', '2027-01-31'], $day->horizon);
    }

    public function test_buses_and_coaches_in_a_train_line_are_not_trains(): void
    {
        $day = self::read(20261208);

        $this->assertCount(2, $day->runs);
        $this->assertSame(1, $day->skippedRuns);
    }

    public function test_a_stop_without_boarding_or_alighting_is_dropped_from_the_run(): void
    {
        $tgv = self::read(20261208)->runs[1];

        $this->assertCount(2, $tgv['stops'], 'B : pickup et drop-off interdits, absent de la course');
    }

    public function test_a_date_removed_by_calendar_dates_has_no_train(): void
    {
        $this->assertSame([], self::read(20261215)->runs);
    }

    public function test_a_saturday_keeps_only_its_own_service_and_times_past_midnight(): void
    {
        $runs = self::read(20261212)->runs;

        $this->assertCount(1, $runs);
        $this->assertSame(25 * 3600 + 600, $runs[0]['stops'][1][1]);
    }

    public function test_zones_carry_the_uic_code_and_position(): void
    {
        $zones = self::read(20261208)->zones;

        $this->assertSame('87100010', $zones[0]->uic);
        $this->assertSame('AAA', $zones[0]->name);
        $this->assertSame([45.0, 1.0], [$zones[0]->lat, $zones[0]->lon]);
    }

    public function test_the_typical_date_is_a_tuesday_of_a_week_without_holiday_inside_the_bounds(): void
    {
        [$date, $compared] = (new TypicalDayReader)->chooseDate(new GtfsArchive(self::DIR), TimetableProfile::sncf(), 20261101);

        $this->assertSame('2', date('N', (int) strtotime((string) $date)));
        $this->assertGreaterThanOrEqual(20261117, $date);
        $this->assertNotSame(20261215, $date, 'jour sans train : jamais choisi');
        $this->assertGreaterThan(1, $compared);
    }

    public function test_bounds_restrict_the_choice(): void
    {
        [$date] = (new TypicalDayReader)->chooseDate(new GtfsArchive(self::DIR), TimetableProfile::sncf(), 20261101, [20261208, 20261209]);

        $this->assertSame(20261208, $date);
    }
}
