<?php

namespace Tests\Unit\Mutation;

use App\Services\EraTimeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règles du moteur de la carte historique (EraTimeline) prises une à une, avec leurs bornes :
 * dépose déduite d'une disparition observée, réouvertures, arbitrage des fermetures entre sources,
 * ouvertures « toute la ligne », fermetures partielles contredites. Complète les tests
 * EraTimeline*Test (cas réels), écrit pour les tests de mutation (tests/Mutation/Critical).
 */
class EraTimelineEngineRulesTest extends TestCase
{
    private static function ev(string $type, string $date, array $extra = []): array
    {
        return ['type' => $type, 'date' => $date] + $extra;
    }

    /** @param  list<array<string, mixed>>  $events */
    private static function statusAt(array $events, int $year, ?string $present = null, bool $isCurrent = false, ?int $since = null): ?string
    {
        $timeline = new EraTimeline($events);
        if ($present !== null) {
            $timeline->withPresentState($present, $since);
        }

        return $timeline->stateAt($year, $isCurrent)['status'] ?? null;
    }

    /** @param  list<array<string, mixed>>  $events */
    private static function traffic(array $events, int $year): ?string
    {
        return (new EraTimeline($events))->stateAt($year)['traffic'] ?? null;
    }

    // ─── Disparition observée aujourd'hui ───────────────────────────────────

    public function test_without_observation_a_closed_line_is_not_gone(): void
    {
        $timeline = (new EraTimeline([self::ev('opening', '1900-01-01'), self::ev('closure', '1950-01-01')]))->withPresentState(null);

        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], $timeline->stateAt(2030, true));
    }

    public function test_a_greenway_today_is_dated_just_after_the_closure(): void
    {
        $events = [self::ev('opening', '1900-01-01'), self::ev('closure', '1950-01-01'), self::ev('declassification', '1990-01-01')];

        // Fermée en 1950 : fermée dans l'ère 1950, déposée dans l'ère 1960, absente ensuite.
        $this->assertSame('abandoned', self::statusAt($events, 1950, 'greenway'));
        $this->assertSame(['status' => 'dismantled', 'traffic' => 'unknown', 'reuse' => 'greenway'],
            (new EraTimeline($events))->withPresentState('greenway')->stateAt(1960));
        $this->assertNull(self::statusAt($events, 1970, 'greenway'));
    }

    public function test_the_era_twenty_years_after_the_closure_no_longer_shows_the_line(): void
    {
        $events = [self::ev('opening', '1900-01-01'), self::ev('closure', '1960-06-01')];

        $this->assertSame('dismantled', self::statusAt($events, 1970, 'road'));
        $this->assertNull(self::statusAt($events, 1980, 'road'));
    }

    public function test_the_closure_of_the_previous_era_is_dismantled_in_this_one(): void
    {
        $this->assertSame('dismantled', self::statusAt([self::ev('opening', '1900-01-01'), self::ev('closure', '1970-12-31')], 1980, 'greenway'));
    }

    public function test_a_freight_only_closure_is_a_closure_too(): void
    {
        $events = [self::ev('opening_freight', '1900-01-01'), self::ev('closure', '1950-01-01')];

        $this->assertSame('abandoned', self::statusAt($events, 1960));
        $this->assertSame('dismantled', self::statusAt($events, 1960, 'greenway'));
    }

    public function test_the_closure_before_the_last_reopening_does_not_date_the_removal(): void
    {
        $events = [self::ev('opening', '1900-01-01'), self::ev('closure', '1940-01-01'),
            self::ev('reopening_freight', '1950-01-01'), self::ev('closure', '1980-01-01')];

        $this->assertSame('dismantled', self::statusAt($events, 1990, 'greenway'));
    }

    public function test_an_observed_date_of_disappearance_counts_from_its_own_era(): void
    {
        $events = [self::ev('opening', '1900-01-01'), self::ev('closure', '1970-01-01')];

        $this->assertSame('dismantled', self::statusAt($events, 1980, 'greenway', since: 1980));
        $this->assertSame('abandoned', self::statusAt($events, 1970, 'greenway', since: 1980));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function laterReopenings(): iterable
    {
        yield 'réouverture' => [self::ev('reopening', '1901-01-01')];
        yield 'ouverture aux voyageurs' => [self::ev('opening_passenger', '1901-01-01')];
        yield 'exploitation associative' => [self::ev('heritage_start', '1901-01-01')];
    }

    /** @param  array<string, mixed>  $later */
    #[DataProvider('laterReopenings')]
    public function test_a_later_reopening_of_the_portion_delays_the_deduced_removal(array $later): void
    {
        // Pontorson - Moidrey : fermée en 1886, plateforme reprise en 1901.
        $events = [self::ev('opening', '1880-01-01'), self::ev('closure', '1886-01-01'), $later];

        $this->assertSame('abandoned', self::statusAt($events, 1900, 'built_over'));
    }

    public function test_a_later_reopening_of_the_whole_line_does_not_delay_it(): void
    {
        $events = [self::ev('opening', '1880-01-01'), self::ev('closure', '1886-01-01'), self::ev('reopening', '1901-01-01', ['whole' => true])];

        $this->assertSame('dismantled', self::statusAt($events, 1900, 'built_over'));
    }

    public function test_a_line_gone_today_without_known_closure(): void
    {
        $this->assertNull(self::statusAt([self::ev('opening_freight', '1900-01-01')], 2030, 'dismantled', isCurrent: true));

        // Exploitée par une association, jamais par une compagnie : aucune fermeture datée.
        $heritage = [self::ev('heritage_start', '1970-01-01', ['heritage_kind' => 'tourist_train']), self::ev('heritage_end', '1990-01-01')];
        $this->assertNull(self::statusAt($heritage, 2030, 'dismantled', isCurrent: true));
        $this->assertSame('abandoned', self::statusAt($heritage, 2000, 'dismantled'));
    }

    // ─── Trafic ─────────────────────────────────────────────────────────────

    public function test_a_declassification_ends_all_traffic(): void
    {
        $this->assertSame('abandoned', self::statusAt([self::ev('opening', '1900-01-01'), self::ev('declassification', '1950-01-01')], 1960));
    }

    public function test_passengers_added_to_a_freight_line(): void
    {
        $this->assertSame('mixed', self::traffic([self::ev('opening_freight', '1900-01-01'), self::ev('opening_passenger', '1920-01-01')], 1930));
        $this->assertSame('freight', self::traffic([self::ev('opening_freight', '1900-01-01')], 1930));
    }

    public function test_a_reopening_forgets_the_former_conversion(): void
    {
        $events = [self::ev('opening', '1900-01-01'), self::ev('conversion', '1950-01-01', ['reuse' => 'greenway']),
            self::ev('reopening', '1960-01-01'), self::ev('closure', '1970-01-01')];

        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], (new EraTimeline($events))->stateAt(1980));
    }

    public function test_a_freight_reopening_forgets_the_former_conversion_too(): void
    {
        $events = [self::ev('opening', '1900-01-01'), self::ev('conversion', '1950-01-01', ['reuse' => 'greenway']),
            self::ev('reopening_freight', '1960-01-01'), self::ev('closure', '1970-01-01')];

        $this->assertSame(['status' => 'abandoned', 'traffic' => 'unknown'], (new EraTimeline($events))->stateAt(1980));
    }

    public function test_passengers_from_the_first_day_of_passenger_service(): void
    {
        $timeline = new EraTimeline([self::ev('opening', '1831-01-01')]);

        $this->assertSame('mixed', $timeline->stateAt(1832, asOf: EraTimeline::FIRST_PASSENGER_SERVICE)['traffic'] ?? null);
        $this->assertSame('freight', $timeline->stateAt(1832, asOf: '1832-02-29')['traffic'] ?? null);
    }

    // ─── Arbitrage des sources ──────────────────────────────────────────────

    public function test_same_events_of_one_source_within_three_years_are_one(): void
    {
        $kept = fn (array $events): array => array_column(EraTimeline::dedupe($events), 'date');

        $this->assertSame(['1950-01-01'], $kept([self::ev('opening', '1953-01-01', ['source' => 'sncf']), self::ev('opening', '1950-01-01', ['source' => 'sncf'])]));
        $this->assertSame(['1950-01-01', '1954-01-01'], $kept([self::ev('opening', '1954-01-01', ['source' => 'sncf']), self::ev('opening', '1950-01-01', ['source' => 'sncf'])]));
    }

    public function test_reopenings_and_openings_are_families_of_their_own(): void
    {
        $kept = EraTimeline::dedupe([
            self::ev('opening', '1900-01-01', ['source' => 'sncf']),
            self::ev('opening_passenger', '1910-01-01', ['source' => 'wikidata']),
            self::ev('reopening', '1960-01-01', ['source' => 'sncf']),
            self::ev('reopening_passenger', '1970-01-01', ['source' => 'wikidata']),
        ]);

        $this->assertSame([['opening', '1900-01-01'], ['reopening', '1960-01-01']], array_map(fn (array $e): array => [$e['type'], $e['date']], $kept));
    }

    public function test_an_opening_twenty_years_earlier_is_not_yet_a_conflict(): void
    {
        $exactly = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('opening', '1880-01-01', ['source' => 'wikipedia'])];
        $earlier = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('opening', '1879-01-01', ['source' => 'wikipedia'])];

        $this->assertNull(self::statusAt($exactly, 1890));
        $this->assertSame('in_service', self::statusAt($earlier, 1890));
    }

    public function test_the_oldest_conflicting_opening_wins(): void
    {
        $events = [self::ev('opening', '1900-01-01', ['source' => 'sncf']),
            self::ev('opening', '1860-01-01', ['source' => 'wikipedia']), self::ev('opening', '1850-01-01', ['source' => 'wikidata'])];

        $this->assertSame('in_service', self::statusAt($events, 1850));
    }

    public function test_only_openings_of_the_portion_can_conflict(): void
    {
        $whole = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('opening', '1850-01-01', ['source' => 'wikidata', 'whole' => true])];
        $otherType = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure_passenger', '1850-01-01', ['source' => 'wikidata'])];

        $this->assertNull(self::statusAt($whole, 1860));
        $this->assertNull(self::statusAt($otherType, 1860));
    }

    public function test_a_later_total_closure_of_another_source_ends_a_freight_only_line(): void
    {
        $base = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure_passenger', '1938-01-01', ['source' => 'lignes-oubliees'])];

        // Plus de trois ans après la fermeture aux voyageurs : une autre fermeture.
        $this->assertSame('abandoned', self::statusAt([...$base, self::ev('closure', '1942-01-01', ['source' => 'wikipedia'])], 1950));
        // Trois ans tout juste : la même fermeture, en moins précis.
        $this->assertSame('freight', self::traffic([...$base, self::ev('closure', '1941-01-01', ['source' => 'wikipedia'])], 1950));
    }

    public function test_the_earliest_late_total_closure_is_kept(): void
    {
        $events = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure_passenger', '1938-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure', '1970-01-01', ['source' => 'wikipedia']), self::ev('closure', '1960-01-01', ['source' => 'wikidata'])];

        $this->assertSame('abandoned', self::statusAt($events, 1965));
    }

    public function test_two_passenger_closures_of_the_best_source_still_allow_a_late_total_closure(): void
    {
        $events = [self::ev('opening', '1900-01-01', ['source' => 'sncf']),
            self::ev('closure_passenger', '1938-01-01', ['source' => 'lignes-oubliees']), self::ev('closure_passenger', '1950-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure', '1970-01-01', ['source' => 'wikipedia'])];

        $this->assertSame('abandoned', self::statusAt($events, 1980));
    }

    public function test_a_total_closure_of_the_best_source_is_not_overridden(): void
    {
        $events = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure', '1950-01-01', ['source' => 'lignes-oubliees']),
            self::ev('reopening', '1960-01-01', ['source' => 'lignes-oubliees']), self::ev('closure', '1970-01-01', ['source' => 'wikipedia'])];

        $this->assertSame('in_service', self::statusAt($events, 1975));
    }

    public function test_only_total_closures_of_other_sources_are_kept_late(): void
    {
        $events = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure_passenger', '1938-01-01', ['source' => 'lignes-oubliees']),
            self::ev('opening', '1890-01-01', ['source' => 'wikidata']), self::ev('opening_passenger', '1960-01-01', ['source' => 'wikidata'])];

        $this->assertNull(self::statusAt($events, 1890));
        $this->assertSame('freight', self::traffic($events, 1970));
    }

    public function test_a_whole_line_closure_does_not_close_a_dated_portion(): void
    {
        $portion = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure_passenger', '1938-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure', '1970-01-01', ['source' => 'wikidata', 'whole' => true])];
        $wholeOnly = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure_passenger', '1938-01-01', ['source' => 'manual', 'whole' => true]),
            self::ev('closure', '1970-01-01', ['source' => 'wikidata', 'whole' => true])];

        $this->assertSame('freight', self::traffic($portion, 1980));
        $this->assertSame('abandoned', self::statusAt($wholeOnly, 1980));
    }

    public function test_a_freight_closure_the_best_source_does_not_give_comes_from_another(): void
    {
        $events = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure_passenger', '1938-01-01', ['source' => 'lignes-oubliees']),
            self::ev('closure_freight', '1960-01-01', ['source' => 'wikipedia'])];

        $this->assertSame('abandoned', self::statusAt($events, 1970));
    }

    public function test_a_service_closure_on_the_day_of_the_total_closure_is_not_added(): void
    {
        $kept = EraTimeline::dedupe([self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure', '1970-01-01', ['source' => 'sncf']),
            self::ev('closure_passenger', '1970-01-01', ['source' => 'wikipedia']), self::ev('closure_passenger', '1969-12-31', ['source' => 'wikidata'])]);

        $passengerClosures = array_filter($kept, fn (array $e): bool => $e['type'] === 'closure_passenger');
        $this->assertSame(['1969-12-31'], array_column($passengerClosures, 'date'));
        $this->assertCount(3, $kept);
    }

    // ─── Exploitation associative « toute la ligne » ────────────────────────

    public function test_a_more_reliable_whole_line_heritage_date_wins(): void
    {
        $kept = fn (array $events): array => array_column(array_filter(EraTimeline::dedupe($events), fn (array $e): bool => $e['type'] === 'heritage_start'), 'date');

        // Pas de clé `whole` : un événement localisé.
        $this->assertSame(['1971-07-04'], $kept([self::ev('heritage_start', '2026-01-01', ['source' => 'ign']),
            self::ev('heritage_start', '1971-07-04', ['source' => 'manual', 'whole' => true])]));
        // La meilleure des dates « toute la ligne » est comparée à la meilleure date localisée.
        $this->assertSame(['1971-07-04'], $kept([self::ev('heritage_start', '1971-07-04', ['source' => 'manual', 'whole' => true]),
            self::ev('heritage_start', '1975-01-01', ['source' => 'wikidata', 'whole' => true]), self::ev('heritage_start', '2026-01-01', ['source' => 'wikipedia'])]));
        $this->assertSame(['1990-01-01'], $kept([self::ev('heritage_start', '1971-07-04', ['source' => 'wikipedia', 'whole' => true]),
            self::ev('heritage_start', '1990-01-01', ['source' => 'lignes-oubliees']), self::ev('heritage_start', '2026-01-01', ['source' => 'ign'])]));
    }

    // ─── Ouvertures « toute la ligne » ──────────────────────────────────────

    public function test_the_first_whole_line_opening_by_date(): void
    {
        $this->assertSame('1890-01-01', EraTimeline::wholeLineOpening([self::ev('closure', '1880-01-01'),
            self::ev('opening', '1900-01-01'), self::ev('opening_passenger', '1890-01-01')])['date'] ?? null);
        $this->assertNull(EraTimeline::wholeLineOpening([self::ev('closure', '1880-01-01')]));
    }

    /** @return iterable<string, array{list<array<string, mixed>>, string}> */
    public static function completions(): iterable
    {
        $first = self::ev('opening', '1856-06-15');
        yield 'quarante ans jour pour jour' => [[self::ev('opening', '1896-06-15'), $first], '1896-06-15'];
        yield 'au-delà de quarante ans' => [[self::ev('opening', '1896-12-31'), self::ev('opening', '1870-01-01'), $first], '1870-01-01'];
        yield 'même année que la première' => [[self::ev('opening', '1856-12-31'), $first], '1856-06-15'];
        yield 'avant une fermeture le même jour' => [[$first, self::ev('opening', '1880-01-01'), self::ev('closure', '1880-01-01')], '1880-01-01'];
        yield 'après la première fermeture' => [[$first, self::ev('opening', '1871-01-01'), self::ev('closure', '1866-01-01'), self::ev('closure', '1880-01-01')], '1856-06-15'];
        yield 'fermetures dans le désordre' => [[$first, self::ev('opening', '1870-01-01'), self::ev('dismantling', '1866-01-01'), self::ev('closure', '1880-01-01')], '1856-06-15'];
        yield 'une fermeture n\'est pas une ouverture' => [[self::ev('closure', '1890-01-01'), $first], '1856-06-15'];
    }

    /** @param  list<array<string, mixed>>  $events */
    #[DataProvider('completions')]
    public function test_completion_of_a_line_opened_in_stages(array $events, string $expected): void
    {
        $this->assertSame($expected, EraTimeline::completionOpening($events)['date'] ?? null);
    }

    /** @return iterable<string, array{list<array<string, mixed>>, string}> */
    public static function wholeOpenings(): iterable
    {
        $first = self::ev('opening', '1856-06-15');
        $closure = self::ev('closure', '1856-12-01');
        yield 'un an jour pour jour après la première' => [[$first, $closure, self::ev('opening', '1857-06-15', ['whole' => true])], 'opening'];
        yield 'le lendemain' => [[$first, $closure, self::ev('opening', '1857-06-16', ['whole' => true])], 'reopening'];
        yield 'bien plus tard' => [[$first, $closure, self::ev('opening_passenger', '1948-05-01', ['whole' => true])], 'reopening_passenger'];
        yield 'ouverture de la portion' => [[$first, $closure, self::ev('opening', '1900-01-01')], 'opening'];
        yield 'sans fermeture entre les deux' => [[$first, self::ev('opening', '1900-01-01', ['whole' => true])], 'opening'];
        yield 'fermeture le jour de la première ouverture' => [[$first, self::ev('closure', '1856-06-15'), self::ev('opening', '1900-01-01', ['whole' => true])], 'opening'];
        yield 'fermeture avant la première ouverture' => [[$first, self::ev('closure', '1850-01-01'), self::ev('opening', '1900-01-01', ['whole' => true])], 'opening'];
        yield 'fermeture le jour même' => [[$first, self::ev('closure', '1900-01-01'), self::ev('opening', '1900-01-01', ['whole' => true])], 'opening'];
    }

    /** @param  list<array<string, mixed>>  $events */
    #[DataProvider('wholeOpenings')]
    public function test_whole_line_openings_after_a_closure_are_reopenings(array $events, string $type): void
    {
        $this->assertSame($type, array_last(EraTimeline::wholeOpeningsAsReopenings($events))['type']);
    }

    // ─── Fermetures partielles contredites ──────────────────────────────────

    public function test_only_the_partial_closure_is_removed(): void
    {
        $events = [self::ev('opening', '1900-01-01', ['source' => 'sncf']), self::ev('closure_passenger', '1980-01-01', ['source' => 'sncf']),
            self::ev('closure_freight', '1990-01-01', ['source' => 'sncf']), self::ev('heritage_end', '1990-01-01', ['source' => 'sncf']),
            self::ev('heritage_end', '2005-01-01', ['source' => 'sncf'])];

        $kept = EraTimeline::withoutContradictedPartialClosures($events, 'PF', true, 2030);

        $this->assertSame([$events[0], $events[1], $events[3], $events[4]], $kept);
    }

    /** @return iterable<string, array{array<string, mixed>, bool}> */
    public static function recentClosures(): iterable
    {
        yield 'dix ans avant' => [self::ev('closure', '2020-03-01', ['source' => 'wikipedia']), false];
        yield 'neuf ans avant' => [self::ev('closure', '2021-03-01', ['source' => 'wikipedia']), true];
        yield 'source des réseaux secondaires' => [self::ev('closure', '2021-03-01', ['source' => 'wikipedia-reseaux']), true];
        yield 'source OpenHistoricalMap' => [self::ev('closure', '2021-03-01', ['source' => 'ohm']), false];
    }

    /** @param  array<string, mixed>  $closure */
    #[DataProvider('recentClosures')]
    public function test_recent_sourced_closures(array $closure, bool $recent): void
    {
        $this->assertSame($recent, EraTimeline::isRecentSourcedClosure($closure, 2030));
    }
}
