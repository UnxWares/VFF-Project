<?php

namespace Tests\Unit;

use App\Services\StationTimeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règle du 21/11/2026 de StationTimeline::massClosureAsPassenger, cas de la gare fermée ou disparue :
 * une « fermeture » en masse (Wikidata, OSM, OHM) de la même année qu'une fin des voyageurs d'une source
 * mieux classée est cette fin des voyageurs quand une source mieux classée date le fret plus tard.
 */
class StationMassClosureRetypeTest extends TestCase
{
    /** @return array<string, mixed> */
    private function ev(string $type, string $date, string $source, array $more = []): array
    {
        return $more + ['type' => $type, 'date' => $date, 'source' => $source, 'precision' => 'year', 'inferred' => false];
    }

    /** @param  list<array<string, mixed>>  $events @return list<string> */
    private function types(array $events, bool $freightToday = false): array
    {
        return array_column(StationTimeline::massClosureAsPassenger($events, $freightToday), 'type');
    }

    public function test_livarot_wikidata_closure_becomes_the_passenger_end_when_freight_went_on(): void
    {
        $events = [
            $this->ev('closure', '1938-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1938-05-05', 'wikipedia'),
            $this->ev('closure_freight', '1990-09-30', 'wikipedia'),
        ];

        $this->assertSame(['closure_passenger', 'closure_passenger', 'closure_freight'], $this->types($events));
    }

    /** @return array<string, array{string, string}> */
    public static function massSources(): array
    {
        return [
            'osm' => ['closure', 'osm'],
            'note OSM' => ['closure', 'osm:note'],
            'OHM (Pouilly-sous-Charlieu)' => ['closure', 'ohm'],
            'démolition Wikidata (Le Lion-d\'Or)' => ['demolition', 'wikidata'],
        ];
    }

    #[DataProvider('massSources')]
    public function test_each_mass_source_is_retyped_when_a_better_source_dates_the_freight_later(string $type, string $source): void
    {
        $events = [
            $this->ev($type, '1940-01-01', $source),
            $this->ev('closure_passenger', '1940-05-20', 'wikipedia'),
            $this->ev('closure_freight', '2005-01-01', 'wikipedia'),
        ];

        $this->assertSame(['closure_passenger', 'closure_passenger', 'closure_freight'], $this->types($events));
    }

    public function test_an_ohm_demolition_or_a_wikidata_qualified_closure_is_not_a_mass_closure(): void
    {
        $events = [
            $this->ev('demolition', '1940-01-01', 'ohm'),
            $this->ev('closure_passenger', '1940-05-20', 'wikipedia'),
            $this->ev('closure_freight', '2005-01-01', 'wikipedia'),
        ];
        $this->assertSame(['demolition', 'closure_passenger', 'closure_freight'], $this->types($events));

        $events[0] = $this->ev('closure', '1940-01-01', 'wikidata:qualifiers');
        $this->assertSame(['closure', 'closure_passenger', 'closure_freight'], $this->types($events));
    }

    public function test_the_closure_stays_total_when_no_freight_is_dated_later(): void
    {
        $same = [
            $this->ev('closure', '1938-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1938-05-05', 'wikipedia'),
            $this->ev('closure_freight', '1938-09-01', 'wikipedia'),
        ];
        $this->assertSame(['closure', 'closure_passenger', 'closure_freight'], $this->types($same));

        $alone = [$this->ev('closure', '1938-01-01', 'wikidata'), $this->ev('closure_passenger', '1938-05-05', 'wikipedia')];
        $this->assertSame(['closure', 'closure_passenger'], $this->types($alone));
    }

    public function test_a_later_total_closure_of_a_better_source_counts_as_later_service(): void
    {
        $events = [
            $this->ev('closure', '1938-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1938-05-05', 'wikipedia'),
            $this->ev('closure', '1975-01-01', 'wikipedia'),
        ];

        $this->assertSame(['closure_passenger', 'closure_passenger', 'closure'], $this->types($events));
    }

    public function test_a_total_closure_of_a_better_source_the_same_year_blocks_the_rule(): void
    {
        // Paris-Tolbiac : démolie en 2001, la fermeture de la même année est bien totale.
        $events = [
            $this->ev('closure', '2001-01-01', 'wikidata'),
            $this->ev('closure_passenger', '2001-03-01', 'wikipedia'),
            $this->ev('closure', '2001-06-01', 'wikipedia'),
            $this->ev('closure_freight', '2010-01-01', 'wikipedia'),
        ];

        $this->assertSame('closure', $this->types($events)[0]);
    }

    public function test_the_year_of_the_passenger_end_may_differ_by_one_but_not_two(): void
    {
        $near = [
            $this->ev('closure', '1939-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1938-12-31', 'wikipedia'),
            $this->ev('closure_freight', '1990-01-01', 'wikipedia'),
        ];
        $this->assertSame('closure_passenger', $this->types($near)[0]);

        $far = [
            $this->ev('closure', '1940-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1938-12-31', 'wikipedia'),
            $this->ev('closure_freight', '1990-01-01', 'wikipedia'),
        ];
        $this->assertSame('closure', $this->types($far)[0]);
    }

    public function test_a_worse_or_equal_source_does_not_decide(): void
    {
        $events = [
            $this->ev('closure', '1938-01-01', 'wikidata'),
            $this->ev('closure_passenger', '1938-05-05', 'osm'),
            $this->ev('closure_freight', '1990-01-01', 'osm'),
        ];

        $this->assertSame(['closure', 'closure_passenger', 'closure_freight'], $this->types($events));
    }

    public function test_inferred_events_and_line_candidates_are_ignored(): void
    {
        $base = [$this->ev('closure', '1938-01-01', 'wikidata'), $this->ev('closure_passenger', '1938-05-05', 'wikipedia')];

        $inferred = [...$base, $this->ev('closure_freight', '1990-01-01', 'wikipedia', ['inferred' => true])];
        $this->assertSame('closure', $this->types($inferred)[0]);

        $line = [...$base, $this->ev('closure_freight', '1990-01-01', 'wikipedia', ['line' => true])];
        $this->assertSame('closure', $this->types($line)[0]);

        $inferredClosure = [$this->ev('closure', '1938-01-01', 'wikidata', ['inferred' => true]),
            $this->ev('closure_passenger', '1938-05-05', 'wikipedia'), $this->ev('closure_freight', '1990-01-01', 'wikipedia')];
        $this->assertSame('closure', $this->types($inferredClosure)[0]);
    }

    public function test_a_freight_only_station_today_keeps_the_first_case(): void
    {
        $events = [$this->ev('closure', '1992-01-01', 'wikidata'), $this->ev('closure_passenger', '1992-01-01', 'wikipedia')];

        $this->assertSame(['closure_passenger', 'closure_passenger'], $this->types($events, true));
        $this->assertSame(['closure', 'closure_passenger'], $this->types($events, false));
    }

    public function test_events_without_a_mass_closure_are_returned_unchanged(): void
    {
        $events = [$this->ev('opening', '1860-01-01', 'wikidata'), $this->ev('closure_passenger', '1938-05-05', 'wikipedia')];

        $this->assertSame($events, StationTimeline::massClosureAsPassenger($events, true));
        $this->assertSame([], StationTimeline::massClosureAsPassenger([], false));
    }
}
