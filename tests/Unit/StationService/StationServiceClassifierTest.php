<?php

namespace Tests\Unit\StationService;

use App\Enums\ServicePattern;
use App\Enums\StationServiceClass;
use App\Services\StationService\HeritageHints;
use App\Services\StationService\ServiceProfile;
use App\Services\StationService\StationEvidence;
use App\Services\StationService\StationServiceClassifier;
use App\Services\StationService\StationServiceVerdict;
use PHPUnit\Framework\TestCase;

/**
 * Classement d'une gare pour aujourd'hui (18/11/2026) : une règle de
 * StationServiceClassifier par test.
 */
class StationServiceClassifierTest extends TestCase
{
    private const TODAY = 20261118;

    private static function profile(ServicePattern $pattern, int $first = 20261118, int $last = 20270430): ServiceProfile
    {
        return new ServiceProfile($pattern, 100, 50, 20261118, 20270430, 20, 24, $first, $last, array_fill(1, 7, 7));
    }

    private static function classify(StationEvidence $evidence): StationServiceVerdict
    {
        return (new StationServiceClassifier)->classify($evidence, self::TODAY);
    }

    public function test_trains_almost_every_week_are_a_regular_service(): void
    {
        $verdict = self::classify(new StationEvidence(rail: self::profile(ServicePattern::Weekend), railSoon: self::profile(ServicePattern::Weekend)));

        $this->assertSame([StationServiceClass::Regular, ServicePattern::Weekend, null], [$verdict->class, $verdict->pattern, $verdict->note]);
    }

    public function test_regular_trains_in_the_next_four_weeks_are_regular_even_if_they_end_later(): void
    {
        $verdict = self::classify(new StationEvidence(rail: self::profile(ServicePattern::Ends, last: 20270103), railSoon: self::profile(ServicePattern::Daily)));

        $this->assertSame(StationServiceClass::Regular, $verdict->class);
        $this->assertSame('Dernier train prévu le 2027-01-03', $verdict->note);
    }

    public function test_irregular_trains_are_a_periodic_service(): void
    {
        $verdict = self::classify(new StationEvidence(rail: self::profile(ServicePattern::Intermittent), railSoon: self::profile(ServicePattern::Sporadic)));

        $this->assertSame([StationServiceClass::Periodic, ServicePattern::Intermittent], [$verdict->class, $verdict->pattern]);
    }

    public function test_trains_only_after_four_weeks_on_a_new_service_are_not_a_service_today(): void
    {
        $verdict = self::classify(new StationEvidence(rail: self::profile(ServicePattern::Starts, first: 20270327)));

        $this->assertSame(StationServiceClass::None, $verdict->class);
        $this->assertStringContainsString('à partir du 2027-03-27', (string) $verdict->note);
    }

    public function test_trains_only_after_four_weeks_on_a_line_served_earlier_this_year_are_periodic(): void
    {
        $verdict = self::classify(new StationEvidence(rail: self::profile(ServicePattern::Starts, first: 20270301),
            summer: self::profile(ServicePattern::Daily, 20260614, 20261130)));

        $this->assertSame([StationServiceClass::Periodic, ServicePattern::Starts], [$verdict->class, $verdict->pattern]);
        $this->assertStringContainsString('Pas de train avant le 2027-03-01', (string) $verdict->note);
    }

    public function test_tram_trains_alone_are_a_service_with_a_note(): void
    {
        $verdict = self::classify(new StationEvidence(tram: self::profile(ServicePattern::Daily)));

        $this->assertSame([StationServiceClass::Regular, 'Tram-train seulement'], [$verdict->class, $verdict->note]);
    }

    public function test_trains_in_summer_only_are_seasonal(): void
    {
        $verdict = self::classify(new StationEvidence(summer: self::profile(ServicePattern::Ends, 20260614, 20260913)));

        $this->assertSame([StationServiceClass::Periodic, ServicePattern::Seasonal], [$verdict->class, $verdict->pattern]);
    }

    public function test_trains_on_some_spring_days_then_every_summer_day_are_seasonal(): void
    {
        $verdict = self::classify(new StationEvidence(summer: self::profile(ServicePattern::Ends, 20260614, 20260928),
            spring: self::profile(ServicePattern::SomeDays, 20260416, 20260531)));

        $this->assertSame([StationServiceClass::Periodic, ServicePattern::Seasonal], [$verdict->class, $verdict->pattern]);
        $this->assertStringContainsString('du 2026-04-16 au 2026-09-28', (string) $verdict->note);
    }

    public function test_trains_every_spring_day_then_none_after_summer_are_not_seasonal(): void
    {
        $verdict = self::classify(new StationEvidence(summer: self::profile(ServicePattern::Ends, 20260614, 20260802),
            spring: self::profile(ServicePattern::Daily, 20260415, 20260531)));

        $this->assertSame(StationServiceClass::None, $verdict->class);
        $this->assertStringContainsString('du 2026-04-15 au 2026-08-02', (string) $verdict->note);
    }

    public function test_summer_trains_ending_after_mid_october_are_not_seasonal(): void
    {
        $evidence = new StationEvidence(summer: self::profile(ServicePattern::Ends, 20260614, 20261031));

        $this->assertFalse((new StationServiceClassifier)->isSeasonal($evidence, self::TODAY));
        $this->assertSame(StationServiceClass::None, self::classify($evidence)->class);
    }

    public function test_published_tourist_timetables_make_a_tourist_station(): void
    {
        $verdict = self::classify(new StationEvidence(touristTrains: self::profile(ServicePattern::SomeDays), bus: self::profile(ServicePattern::Daily)));

        $this->assertSame([StationServiceClass::Tourist, ServicePattern::SomeDays], [$verdict->class, $verdict->pattern]);
    }

    public function test_a_velorail_station_wins_over_a_tourist_line(): void
    {
        $hints = new HeritageHints(touristLine: true, touristStation: true, velorailStation: true);

        $this->assertSame(StationServiceClass::Velorail, self::classify(new StationEvidence(heritage: $hints))->class);
    }

    public function test_a_tourist_station_or_line_makes_a_tourist_station(): void
    {
        $this->assertSame(StationServiceClass::Tourist, self::classify(new StationEvidence(heritage: new HeritageHints(touristStation: true)))->class);
        $this->assertSame(StationServiceClass::Tourist,
            self::classify(new StationEvidence(heritage: new HeritageHints(touristLine: true, velorailLine: true)))->class);
    }

    public function test_a_velorail_on_the_line_makes_a_velorail_station(): void
    {
        $this->assertSame(StationServiceClass::Velorail, self::classify(new StationEvidence(heritage: new HeritageHints(velorailLine: true)))->class);
    }

    public function test_coaches_alone_are_bus_only(): void
    {
        $verdict = self::classify(new StationEvidence(bus: self::profile(ServicePattern::Daily)));

        $this->assertSame([StationServiceClass::BusOnly, ServicePattern::Daily], [$verdict->class, $verdict->pattern]);
    }

    public function test_trains_from_an_outdated_feed_only_are_unknown(): void
    {
        $verdict = self::classify(new StationEvidence(stale: self::profile(ServicePattern::Daily, 20260303, 20260309)));

        $this->assertSame(StationServiceClass::Unknown, $verdict->class);
        $this->assertStringContainsString('flux périmé', (string) $verdict->note);
    }

    public function test_a_region_without_up_to_date_feed_is_unknown(): void
    {
        $this->assertSame(StationServiceClass::Unknown, self::classify(new StationEvidence(covered: false))->class);
    }

    public function test_nothing_at_all_is_no_service(): void
    {
        $verdict = self::classify(new StationEvidence);

        $this->assertSame([StationServiceClass::None, null, null], [$verdict->class, $verdict->pattern, $verdict->note]);
    }
}
