<?php

namespace Tests\Unit;

use App\Services\StationTimeline as T;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Chronologie d'une gare (StationTimeline) : cas limites des règles métier.
 *   - état au 31/12 de l'année : un événement de l'année compte ;
 *   - ouverture inconnue : gare montrée dans la seule décennie qui précède sa fermeture ;
 *   - fermeture aux voyageurs : fret seul tant que la gare existe ;
 *   - une gare ne peut pas être plus active que ses lignes (fermées → fermée,
 *     fret seul → au mieux fret, ligne touristique → touristique).
 */
class StationTimelineRulesTest extends TestCase
{
    /** @param  list<array{0: string, 1: string}>  $events */
    private static function ev(array $events): array
    {
        return array_map(fn ($e) => ['type' => $e[0], 'date' => $e[1]], $events);
    }

    public function test_events_of_the_year_count_at_the_31st_of_december(): void
    {
        $events = self::ev([['opening', '1900-12-31'], ['closure', '1950-12-31']]);

        $this->assertNull(T::statusFromEvents($events, 1899, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1900, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1949, false));
        $this->assertNull(T::statusFromEvents($events, 1950, false));
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1950, true), 'fermée mais existe encore');
    }

    public function test_events_are_sorted_by_date_before_being_replayed(): void
    {
        $events = self::ev([['closure', '1960-01-01'], ['reopening', '1990-06-01'], ['opening', '1880-01-01']]);

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1950, false));
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1970, false), 'rouverte plus tard : existe entre-temps');
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 2000, false));
    }

    /** @return array<string, array{0: string, 1: int, 2: ?int}> */
    public static function unknownOpeningProvider(): array
    {
        return [
            // date de fermeture, décennie montrée, décennie précédente (absente)
            'fermeture en cours de décennie' => ['1938-05-01', 1930, 1920],
            'fermeture le 1er janvier d\'une décennie' => ['1940-01-01', 1930, 1920],
            'fermeture l\'année d\'une décennie' => ['1930-06-15', 1920, 1910],
            'fermeture en fin de décennie' => ['1939-12-31', 1930, 1920],
            'fermeture l\'année suivant une décennie' => ['1931-03-01', 1930, 1920],
        ];
    }

    #[DataProvider('unknownOpeningProvider')]
    public function test_unknown_opening_is_assumed_at_the_decade_before_closure(string $closure, int $shown, int $before): void
    {
        $events = self::ev([['closure', $closure]]);

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, $shown, false));
        $this->assertNull(T::statusFromEvents($events, $before, false));
        $this->assertNull(T::statusFromEvents($events, $shown + 10, false));
    }

    public function test_unknown_opening_with_passenger_closure_or_demolition(): void
    {
        $this->assertSame(T::ACTIVE, T::statusFromEvents(self::ev([['closure_passenger', '1955-01-01']]), 1950, true));
        $this->assertSame(T::FREIGHT, T::statusFromEvents(self::ev([['closure_passenger', '1955-01-01']]), 1960, true));
        $this->assertSame(T::ACTIVE, T::statusFromEvents(self::ev([['demolition', '1972-01-01']]), 1970, true));
        $this->assertNull(T::statusFromEvents(self::ev([['demolition', '1972-01-01']]), 1980, true), 'démolie : n\'existe plus');
    }

    public function test_unknown_opening_is_assumed_before_the_first_closure_only(): void
    {
        $events = self::ev([['closure_passenger', '1955-01-01'], ['closure', '1975-01-01']]);

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1950, false), 'ouverture supposée avant la PREMIÈRE fermeture');
        $this->assertSame(T::FREIGHT, T::statusFromEvents($events, 1960, false));
        $this->assertNull(T::statusFromEvents($events, 1980, false));
    }

    public function test_events_after_a_closure_that_are_not_reopenings_do_not_revive_the_station(): void
    {
        // Fermeture totale suivie d'une simple démolition : disparue dès la fermeture.
        $gone = self::ev([['opening', '1870-01-01'], ['closure', '1950-01-01'], ['demolition', '1960-01-01']]);
        $this->assertNull(T::statusFromEvents($gone, 1955, false));

        // Fermeture aux voyageurs suivie d'un événement sans effet (« other ») : pas une suite connue.
        $other = self::ev([['opening', '1870-01-01'], ['closure_passenger', '1950-01-01'], ['other', '1960-01-01']]);
        $this->assertNull(T::statusFromEvents($other, 1955, false));
        $this->assertSame(T::FREIGHT, T::statusFromEvents($other, 1955, true));
    }

    public function test_station_without_opening_nor_closure_does_not_exist(): void
    {
        $this->assertNull(T::statusFromEvents([], 2030, true));
        $this->assertNull(T::statusFromEvents(self::ev([['closure_freight', '1980-01-01'], ['other', '1990-01-01']]), 2030, true));
    }

    public function test_freight_closure_keeps_passengers(): void
    {
        $events = self::ev([['opening', '1870-01-01'], ['closure_freight', '1970-01-01']]);

        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1980, true));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 1980, false));
    }

    public function test_passenger_then_freight_closure_gives_a_closed_station(): void
    {
        $events = self::ev([['opening', '1870-01-01'], ['closure_passenger', '1950-01-01'], ['closure_freight', '1975-01-01']]);

        $this->assertSame(T::FREIGHT, T::statusFromEvents($events, 1960, false), 'une suite est connue : la gare existe encore');
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1980, true));
        $this->assertSame(T::CLOSED, T::statusFromEvents($events, 1980, false));
    }

    public function test_passenger_closure_followed_by_a_reopening_keeps_the_station(): void
    {
        $events = self::ev([['opening', '1870-01-01'], ['closure_passenger', '1950-01-01'], ['reopening', '2010-01-01']]);

        $this->assertSame(T::FREIGHT, T::statusFromEvents($events, 1980, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($events, 2020, false));
    }

    public function test_demolished_station_reappears_only_if_rebuilt(): void
    {
        $gone = self::ev([['opening', '1870-01-01'], ['closure', '1950-01-01'], ['demolition', '1960-01-01']]);
        $this->assertSame(T::CLOSED, T::statusFromEvents($gone, 1950, true));
        $this->assertNull(T::statusFromEvents($gone, 1960, true), 'démolie : disparue même si la gare « existe » ailleurs');

        $rebuilt = self::ev([['opening', '1870-01-01'], ['demolition', '1960-01-01'], ['opening', '2000-01-01']]);
        $this->assertSame(T::CLOSED, T::statusFromEvents($rebuilt, 1970, false));
        $this->assertSame(T::ACTIVE, T::statusFromEvents($rebuilt, 2000, false));
    }

    /** @return array<string, array{0: ?string, 1: int, 2: bool, 3: ?string}> */
    public static function capProvider(): array
    {
        return [
            'touristique sur ligne fret' => [T::TOURIST, T::LINES_FREIGHT, false, T::FREIGHT],
            'touristique sur ligne fermée' => [T::TOURIST, T::LINES_CLOSED, false, T::CLOSED],
            'touristique sur ligne voyageurs' => [T::TOURIST, T::LINES_PASSENGER, false, T::TOURIST],
            'fermée sur ligne voyageurs' => [T::CLOSED, T::LINES_PASSENGER, false, T::CLOSED],
            'fret sur ligne fret' => [T::FREIGHT, T::LINES_FREIGHT, false, T::FREIGHT],
            'voyageurs sur ligne touristique' => [T::ACTIVE, T::LINES_PASSENGER, true, T::TOURIST],
            'fret sur ligne touristique' => [T::FREIGHT, T::LINES_PASSENGER, true, T::FREIGHT],
            'fermée sur ligne touristique' => [T::CLOSED, T::LINES_PASSENGER, true, T::CLOSED],
            'desserte inconnue : comme fermée' => [T::ACTIVE, 7, false, T::CLOSED],
            'desserte négative : comme fermée' => [T::FREIGHT, -1, false, T::CLOSED],
            'fret seul + patrimoine' => [T::ACTIVE, T::LINES_FREIGHT, true, T::FREIGHT],
            'absente' => [null, T::LINES_FREIGHT, true, null],
        ];
    }

    #[DataProvider('capProvider')]
    public function test_station_is_capped_by_its_lines(?string $status, int $lines, bool $heritageOnly, ?string $expected): void
    {
        $this->assertSame($expected, T::capByLines($status, $lines, $heritageOnly));
    }
}
