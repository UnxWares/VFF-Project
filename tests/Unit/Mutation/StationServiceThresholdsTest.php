<?php

namespace Tests\Unit\Mutation;

use App\Enums\ServicePattern;
use App\Enums\StationServiceClass;
use App\Services\StationService\ServicePatternDetector;
use App\Services\StationService\ServiceProfile;
use App\Services\StationService\StationEvidence;
use App\Services\StationService\StationServiceClassifier;
use PHPUnit\Framework\TestCase;

/**
 * Seuils exacts de la desserte des gares (ServicePatternDetector, StationServiceClassifier) : une
 * valeur égale au seuil tombe du côté annoncé par la règle. Complète tests/Unit/StationService,
 * écrit pour les tests de mutation (tests/Mutation/Critical).
 */
class StationServiceThresholdsTest extends TestCase
{
    private const NO_WEEKDAYS = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0];

    private function pattern(int $servedDays, int $windowDays, int $servedWeeks, int $weeks, array $weekdays = self::NO_WEEKDAYS, bool $startsLate = false, bool $endsEarly = false): ServicePattern
    {
        return (new ServicePatternDetector)->pattern($servedDays, $windowDays, $servedWeeks, $weeks, $weekdays, $startsLate, $endsEarly);
    }

    public function test_three_quarters_of_the_weeks_make_a_regular_service(): void
    {
        $this->assertTrue($this->pattern(10, 100, 3, 4)->isRegular());
        $this->assertFalse($this->pattern(10, 100, 2, 4)->isRegular());
    }

    public function test_four_days_out_of_five_make_a_daily_service(): void
    {
        $this->assertSame(ServicePattern::Daily, $this->pattern(80, 100, 15, 15));
        $this->assertSame(ServicePattern::SomeDays, $this->pattern(79, 100, 15, 15, [1 => 20, 2 => 20, 3 => 20, 4 => 0, 5 => 0, 6 => 19, 7 => 0]));
    }

    public function test_ninety_five_percent_of_weekdays_make_a_weekday_service(): void
    {
        $this->assertSame(ServicePattern::Weekdays, $this->pattern(20, 100, 4, 4, [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 3, 6 => 1, 7 => 0]));
        $this->assertSame(ServicePattern::SomeDays, $this->pattern(20, 100, 4, 4, [1 => 4, 2 => 4, 3 => 4, 4 => 3, 5 => 3, 6 => 1, 7 => 1]));
    }

    public function test_ninety_percent_of_weekends_make_a_weekend_service(): void
    {
        $this->assertSame(ServicePattern::Weekend, $this->pattern(10, 100, 4, 4, [1 => 1, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 5, 7 => 4]));
        $this->assertSame(ServicePattern::SomeDays, $this->pattern(10, 100, 4, 4, [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 0, 6 => 4, 7 => 4]));
    }

    public function test_the_profile_of_unsorted_days(): void
    {
        // Lundi 2 novembre au dimanche 29 novembre 2026 ; trains donnés dans le désordre.
        $profile = (new ServicePatternDetector)->profile([20261120 => 2, 20261104 => 1, 20261112 => 3, 20261103 => 1, 20261201 => 5], 20261102, 20261129);

        $this->assertInstanceOf(ServiceProfile::class, $profile);
        $this->assertSame([20261103, 20261120], [$profile->first, $profile->last]);
        $this->assertSame([1 => 0, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 0, 7 => 0], $profile->weekdays);
        $this->assertSame([7, 4, 3, 4], [$profile->calls, $profile->servedDays, $profile->servedWeeks, $profile->weeks]);
    }

    public function test_the_last_day_of_the_window_counts_its_week(): void
    {
        // Lundi 2 au lundi 9 novembre 2026 : deux semaines ISO.
        $profile = (new ServicePatternDetector)->profile([20261102 => 1], 20261102, 20261109);

        $this->assertSame(2, $profile?->weeks);
    }

    public function test_the_edges_of_the_window_are_twenty_one_days(): void
    {
        $detector = new ServicePatternDetector;
        // Fenêtre du 1er janvier au 31 décembre 2026 ; trains quatre jours d'une semaine de juin.
        $days = fn (int $first): array => [$first => 1, $first + 1 => 1, $first + 2 => 1, $first + 3 => 1];

        // Premier train le 22 janvier, 21 jours après le début : ce n'est pas un départ tardif.
        $this->assertSame(ServicePattern::Ends, $detector->profile($days(20260122), 20260101, 20261231)?->pattern);
        // Premier train le 23 janvier : départ tardif.
        $this->assertSame(ServicePattern::Seasonal, $detector->profile($days(20260123), 20260101, 20261231)?->pattern);
        // Dernier train le 10 décembre, 21 jours avant la fin : ce n'est pas une fin avancée.
        $this->assertSame(ServicePattern::Starts, $detector->profile($days(20261207), 20260101, 20261231)?->pattern);
        // Dernier train le 9 décembre : fin avancée.
        $this->assertSame(ServicePattern::Seasonal, $detector->profile($days(20261206), 20260101, 20261231)?->pattern);
    }

    // ─── Classement ─────────────────────────────────────────────────────────

    private const TODAY = 20261118;

    private static function profile(ServicePattern $pattern, int $first = 20261118, int $last = 20270430): ServiceProfile
    {
        return new ServiceProfile($pattern, 100, 50, 20261118, 20270430, 20, 24, $first, $last, array_fill(1, 7, 7));
    }

    public function test_trains_come_before_tram_trains(): void
    {
        $verdict = (new StationServiceClassifier)->classify(new StationEvidence(
            rail: self::profile(ServicePattern::Daily),
            tram: self::profile(ServicePattern::Intermittent),
        ), self::TODAY);

        $this->assertSame([StationServiceClass::Regular, ServicePattern::Daily, null], [$verdict->class, $verdict->pattern, $verdict->note]);
    }

    public function test_regular_trains_without_a_four_week_profile_stay_regular(): void
    {
        $verdict = (new StationServiceClassifier)->classify(new StationEvidence(rail: self::profile(ServicePattern::Daily)), self::TODAY);

        $this->assertSame([StationServiceClass::Regular, ServicePattern::Daily, null], [$verdict->class, $verdict->pattern, $verdict->note]);
    }

    public function test_notes_of_trains_that_start_later(): void
    {
        $classifier = new StationServiceClassifier;

        $new = $classifier->classify(new StationEvidence(rail: self::profile(ServicePattern::Starts, first: 20270327)), self::TODAY);
        $this->assertSame('Trains prévus à partir du 2027-03-27 (ouverture ou réouverture à venir)', $new->note);

        $back = $classifier->classify(new StationEvidence(
            rail: self::profile(ServicePattern::Starts, first: 20270327),
            summer: self::profile(ServicePattern::Daily, 20260701, 20260831),
        ), self::TODAY);
        $this->assertSame([StationServiceClass::Periodic, ServicePattern::Starts], [$back->class, $back->pattern]);
        $this->assertSame('Pas de train avant le 2027-03-27 (desservie plus tôt dans l\'année)', $back->note);
    }

    public function test_a_summer_ending_today_is_not_over(): void
    {
        $classifier = new StationServiceClassifier;

        $this->assertFalse($classifier->isSeasonal(new StationEvidence(summer: self::profile(ServicePattern::Daily, 20260701, 20261015)), 20261015));
        $this->assertTrue($classifier->isSeasonal(new StationEvidence(summer: self::profile(ServicePattern::Daily, 20260701, 20261015)), 20261016));
        $this->assertFalse($classifier->isSeasonal(new StationEvidence(summer: self::profile(ServicePattern::Daily, 20260701, 20261016)), 20261118));
    }
}
