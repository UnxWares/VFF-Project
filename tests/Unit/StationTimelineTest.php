<?php

namespace Tests\Unit;

use App\Services\StationTimeline as T;
use PHPUnit\Framework\TestCase;

class StationTimelineTest extends TestCase
{
    public function test_station_exists_from_opening_to_closure(): void
    {
        $events = [['type' => 'opening', 'date' => '1881-06-01'], ['type' => 'closure', 'date' => '1938-10-02']];

        $this->assertNull(T::statusFromEvents($events, 1880, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1900, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1930, false));
        $this->assertNull(T::statusFromEvents($events, 1940, false));
    }

    public function test_closed_then_reopened_station_is_shown_closed_in_between(): void
    {
        $events = [
            ['type' => 'opening', 'date' => '1870-05-01'],
            ['type' => 'closure', 'date' => '1939-01-01'],
            ['type' => 'reopening', 'date' => '2007-12-09'],
        ];

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1930, true));
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1960, true));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 2010, true));
    }

    public function test_passenger_closure_leaves_freight_only_while_station_still_exists(): void
    {
        $events = [['type' => 'opening', 'date' => '1890-01-01'], ['type' => 'closure_passenger', 'date' => '1970-05-31']];

        $this->assertSame(T::FREIGHT, T::statusFromEvents($events, 1980, true));
        // Disparue aujourd'hui, sans suite connue : la fermeture aux voyageurs clôt son existence.
        $this->assertNull(T::statusFromEvents($events, 1980, false));
    }

    public function test_unknown_opening_shows_only_the_decade_before_closure(): void
    {
        $events = [['type' => 'closure', 'date' => '1938-01-01']];

        $this->assertNull(T::statusFromEvents($events, 1920, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1930, false));
        $this->assertNull(T::statusFromEvents($events, 1940, false));
    }

    public function test_station_cannot_be_more_active_than_its_lines(): void
    {
        $this->assertSame(T::CLOSED, T::capByLines(T::FREIGHT, T::LINES_CLOSED));
        $this->assertSame(T::CLOSED, T::capByLines(T::ACTIVE, T::LINES_CLOSED));
        $this->assertSame(T::FREIGHT, T::capByLines(T::ACTIVE, T::LINES_FREIGHT));
        $this->assertSame(T::ACTIVE, T::capByLines(T::ACTIVE, T::LINES_PASSENGER));
        $this->assertSame(T::FREIGHT, T::capByLines(T::FREIGHT, T::LINES_PASSENGER));
        $this->assertSame(T::TOURIST, T::capByLines(T::ACTIVE, T::LINES_PASSENGER, true));
        $this->assertNull(T::capByLines(null, T::LINES_PASSENGER));
    }

    /** La fin d'une autre ligne de la gare n'est pas sa fermeture tant qu'une de ses lignes la dessert (Vourey, La Praz). */
    public function test_line_closure_before_an_operated_era_is_not_the_station_closure(): void
    {
        $line = ['type' => 'dismantling', 'date' => '1959-01-01', 'precision' => 'year', 'source' => 'inferred:line_event', 'inferred' => true, 'line' => true];
        $own = ['type' => 'closure', 'date' => '1950-05-01', 'precision' => 'day', 'source' => 'wikipedia', 'inferred' => false, 'line' => false];
        $late = ['type' => 'closure', 'date' => '2021-06-01', 'precision' => 'day', 'source' => 'inferred:line_event', 'inferred' => true, 'line' => true];

        // Montrée ouverte (non confirmée) sur une ligne en service jusqu'en 2020 : seules les dates propres
        // à la gare et les fins de desserte postérieures restent candidates.
        $this->assertSame([$own, $late], T::dropLineClosuresBeforeService([$line, $own, $late], 2020));
        $this->assertSame([$line, $own], T::dropLineClosuresBeforeService([$line, $own], null));

        // Sans candidat : intervalle des ères, cohérent avec la frise.
        $eras = [1950 => ['status' => T::ACTIVE], 1960 => ['status' => T::ACTIVE, 'uncertain' => true],
            2020 => ['status' => T::ACTIVE, 'uncertain' => true], 2030 => ['status' => T::CLOSED]];
        $r = T::resolveClosure($eras, 2030, '2026-11-19', T::dropLineClosuresBeforeService([$line], 2020));
        $this->assertSame(['2026-11-19', T::RANGE, 'vff:eras', 1950], [$r['date'] ?? null, $r['precision'] ?? null, $r['source'] ?? null, $r['after'] ?? null]);
    }

    /** Wikidata « 1970 » (année) qui reprend Wikipédia « années 1970 » : la date retenue est la décennie, comme la frise. */
    public function test_round_year_repeating_a_decade_of_another_source_becomes_the_decade(): void
    {
        $wd = ['type' => 'closure', 'date' => '1970-01-01', 'precision' => 'year', 'source' => 'wikidata', 'inferred' => false, 'line' => false];
        $wp = ['type' => 'closure', 'date' => '1970-01-01', 'precision' => T::DECADE, 'source' => 'wikipedia', 'inferred' => false, 'line' => false];

        $this->assertSame([T::DECADE, T::DECADE], array_column(T::roundYearAsDecade([$wd, $wp]), 'precision'));
        // Seule, ou non ronde, ou décennie de la même source : l'année reste une année.
        $this->assertSame(['year'], array_column(T::roundYearAsDecade([$wd]), 'precision'));
        $this->assertSame(['year', T::DECADE], array_column(T::roundYearAsDecade([['date' => '1974-01-01'] + $wd, ['date' => '1970-01-01'] + $wp]), 'precision'));
        $this->assertSame(['year', T::DECADE], array_column(T::roundYearAsDecade([$wd, ['source' => 'wikidata'] + $wp]), 'precision'));

        // Ère 1970 non confirmée : la date ne la contredit plus (fermeture « entre 1969 et 1979 »).
        $eras = [1960 => ['status' => T::ACTIVE], 1970 => ['status' => T::ACTIVE, 'uncertain' => true], 1980 => ['status' => T::CLOSED],
            2030 => ['status' => T::CLOSED]];
        $r = T::resolveClosure($eras, 2030, '2026-11-19', T::roundYearAsDecade([$wd, $wp]));
        $this->assertSame(['1979-12-31', T::RANGE], [$r['date'] ?? null, $r['precision'] ?? null]);
        // Sans la règle : « fermée en 1970 », contredite par l'ère 1970.
        $before = T::resolveClosure($eras, 2030, '2026-11-19', [$wd, $wp]);
        $this->assertSame(['1970-01-01', 'year'], [$before['date'] ?? null, $before['precision'] ?? null]);
    }

    /** Fret seul non confirmé sur une ligne seulement touristique : fermée ; confirmé, ou ère courante : inchangé. */
    public function test_unconfirmed_freight_on_a_heritage_only_line_means_closed(): void
    {
        $this->assertTrue(T::heritageLineEndsFreight(T::FREIGHT, true, true, false));
        $this->assertFalse(T::heritageLineEndsFreight(T::FREIGHT, true, false, false));
        $this->assertFalse(T::heritageLineEndsFreight(T::FREIGHT, true, true, true));
        $this->assertFalse(T::heritageLineEndsFreight(T::FREIGHT, false, true, false));
        $this->assertFalse(T::heritageLineEndsFreight(T::TOURIST, true, true, false));
    }
}
