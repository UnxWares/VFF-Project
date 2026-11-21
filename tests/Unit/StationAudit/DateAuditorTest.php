<?php

namespace Tests\Unit\StationAudit;

use App\Services\StationAudit\DateAuditor;
use App\Services\StationAudit\DateGrade;
use PHPUnit\Framework\TestCase;

class DateAuditorTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function facts(array $overrides = []): array
    {
        return array_merge([
            'status' => 'declassified',
            'opened_at' => '1866-05-01', 'opened_precision' => 'day', 'opened_source' => 'wikidata',
            'closed_at' => '1938-01-01', 'closed_precision' => 'year', 'closed_source' => 'wikipedia',
            'events' => [],
            'lines' => ['count' => 1, 'first_year' => 1866, 'last_year' => 1938, 'open' => 0],
            'freight_flag' => null,
            'candidates' => [],
            'track_offset_m' => 4,
            'today_year' => 2026,
        ], $overrides);
    }

    public function test_grades_follow_the_source_of_each_date(): void
    {
        $r = DateAuditor::audit(self::facts(['opened_source' => 'inferred:line_event']));
        $this->assertSame(DateGrade::Estimated, $r->opening);
        $this->assertSame(DateGrade::Sourced, $r->closure);
        $this->assertSame(DateGrade::Estimated, DateAuditor::audit(self::facts(['closed_precision' => 'range', 'closed_source' => 'vff:eras']))->closure);
        $this->assertSame(DateGrade::Unknown, DateAuditor::audit(self::facts(['opened_at' => null, 'opened_source' => null]))->opening);
    }

    public function test_an_open_station_has_no_closure_to_find(): void
    {
        $r = DateAuditor::audit(self::facts(['status' => 'active', 'closed_at' => null, 'closed_source' => null, 'freight_flag' => false]));
        $this->assertSame(DateGrade::NotApplicable, $r->closure);
        $this->assertSame('open', $r->services['passenger']['state']);
        $this->assertSame('closed', $r->services['freight']['state']);
        $this->assertSame(DateGrade::Unknown->value, $r->services['freight']['grade']);
    }

    public function test_freight_only_station_keeps_freight_and_dates_the_passenger_end(): void
    {
        $r = DateAuditor::audit(self::facts([
            'status' => 'no_commercial', 'closed_at' => null, 'closed_source' => null,
            'events' => [
                ['type' => 'closure_passenger', 'date' => '1969-09-28', 'precision' => 'day', 'source' => 'inferred:line_event', 'inferred' => true],
                ['type' => 'closure_passenger', 'date' => '1970-01-01', 'precision' => 'year', 'source' => 'wikipedia', 'inferred' => false],
            ],
        ]));
        $this->assertSame('open', $r->services['freight']['state']);
        $this->assertSame('closed', $r->services['passenger']['state']);
        // L'événement sourcé passe avant l'événement déduit.
        $this->assertSame('1970-01-01', $r->services['passenger']['closed_on']);
        $this->assertSame('sourced', $r->services['passenger']['grade']);
        $this->assertSame(DateGrade::NotApplicable, $r->closure);
    }

    public function test_cross_checks(): void
    {
        $r = DateAuditor::audit(self::facts([
            'opened_at' => '1850-01-01', 'closed_at' => '1840-01-01',
            'events' => [
                ['type' => 'closure_freight', 'date' => '1845-01-01', 'precision' => 'year', 'source' => 'wikipedia', 'inferred' => false],
                ['type' => 'closure_passenger', 'date' => '1900-01-01', 'precision' => 'year', 'source' => 'wikipedia', 'inferred' => false],
            ],
            'candidates' => [['kind' => 'opening', 'comparison' => 'differs', 'delta_days' => 4000, 'date' => '1861-01-01', 'precision' => 'year']],
            'track_offset_m' => 80,
        ]));
        $codes = array_column($r->checks, 'code');
        $this->assertContains('closure_before_opening', $codes);
        $this->assertContains('opening_before_lines', $codes);
        $this->assertContains('freight_closed_before_opening', $codes);
        $this->assertContains('passenger_after_closure', $codes);
        $this->assertContains('wikidata_differs', $codes);
        $this->assertContains('far_from_track', $codes);
    }

    public function test_an_open_station_on_lines_that_all_ended_is_flagged(): void
    {
        $r = DateAuditor::audit(self::facts(['status' => 'active', 'closed_at' => null, 'closed_source' => null,
            'lines' => ['count' => 2, 'first_year' => 1866, 'last_year' => 1990, 'open' => 0]]));
        $this->assertSame(['open_after_lines'], array_column($r->checks, 'code'));
        $this->assertSame([], DateAuditor::audit(self::facts())->checks);
    }

    public function test_checks_against_the_map_eras(): void
    {
        // Messei : fermée aux voyageurs en 1973 (Wikipédia), « fret seul » sur la carte en 1970.
        $r = DateAuditor::audit(self::facts([
            'opened_at' => '1840-01-01', 'opened_source' => 'inferred:line_event', 'closed_at' => '1988-01-01',
            'events' => [['type' => 'closure_passenger', 'date' => '1973-01-01', 'precision' => 'year', 'source' => 'wikipedia', 'inferred' => false]],
            'eras' => [['year' => 1870, 'status' => 'active'], ['year' => 1960, 'status' => 'active'], ['year' => 1970, 'status' => 'no_commercial'], ['year' => 1990, 'status' => 'declassified']],
        ]));
        $codes = array_column($r->checks, 'code');
        $this->assertContains('opening_before_map', $codes);
        $this->assertContains('era_passenger_mismatch', $codes);
        $this->assertNotContains('freight_only_undated', $codes);
        // Sans aucune fin des voyageurs datée, l'ère « fret seul » est signalée.
        $undated = DateAuditor::audit(self::facts(['events' => [], 'eras' => [['year' => 1970, 'status' => 'no_commercial']]]));
        $this->assertContains('freight_only_undated', array_column($undated->checks, 'code'));

        // Ouverte en 1866, première ère 1870 : rien. Une réouverture après la fin des voyageurs : rien non plus.
        $ok = DateAuditor::audit(self::facts([
            'events' => [
                ['type' => 'closure_passenger', 'date' => '1938-01-01', 'precision' => 'year', 'source' => 'wikipedia', 'inferred' => false],
                ['type' => 'reopening', 'date' => '1990-01-01', 'precision' => 'year', 'source' => 'wikipedia', 'inferred' => false],
            ],
            'eras' => [['year' => 1870, 'status' => 'active'], ['year' => 2000, 'status' => 'active']],
        ]));
        $this->assertSame([], $ok->checks);
    }

    public function test_a_closed_station_without_date_is_flagged(): void
    {
        $r = DateAuditor::audit(self::facts(['closed_at' => null, 'closed_source' => null, 'closed_precision' => null]));
        $this->assertSame(['closed_without_date'], array_column($r->checks, 'code'));
        // Intervalle qui s'arrête au jour du calcul : fermeture non datée.
        $range = DateAuditor::audit(self::facts(['closed_at' => '2026-11-21', 'closed_precision' => 'range', 'closed_source' => 'vff:eras']));
        $this->assertSame(DateGrade::Unknown, $range->closure);
        $this->assertSame(['closed_without_date'], array_column($range->checks, 'code'));
    }
}
