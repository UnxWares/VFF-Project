<?php

namespace Tests\Unit;

use App\Services\LineStations;
use PHPUnit\Framework\TestCase;

/**
 * Fermetures nommées dans la vue « décennie par décennie » (LineStations::closureEntries) :
 * chaque gare comptée fermée à une décennie, et pas à la précédente, y figure. La fermeture
 * retenue (date de la fiche gare) va à la même décennie que le compteur ; une fermeture sans date
 * retenue (provisoire, ou non datée) prend l'intervalle de la frise, jamais une date inventée.
 */
class LineStationsClosureEntriesTest extends TestCase
{
    private const YEARS = [1900, 1910, 1920, 1930, 1940, 1950, 2030];

    /**
     * @param  array<int, ?string>  $statuses  statut affiché par année d'ère
     * @return array<string, mixed>
     */
    private static function station(array $statuses, ?string $closedAt = null, ?string $precision = null, ?int $after = null): array
    {
        return [
            'slug' => 'gare', 'name' => 'Gare', 'pk_label' => '3+000',
            'closed_at' => $closedAt, 'closed_precision' => $precision, 'closed_after' => $after,
            'closed_year' => $closedAt === null ? null : (int) substr($closedAt, 0, 4),
            'eras' => array_map(fn (?string $s): array => ['status' => $s], $statuses),
        ];
    }

    public function test_the_retained_closure_goes_to_the_decade_where_the_counter_shows_it(): void
    {
        $entries = LineStations::closureEntries(self::station(
            [1900 => 'active', 1910 => 'active', 1920 => 'active', 1930 => 'declassified', 1940 => 'declassified', 1950 => 'declassified', 2030 => 'declassified'],
            '1925-05-26', 'day',
        ), self::YEARS);

        $this->assertSame([1930], array_keys($entries));
        $this->assertSame(['1925-05-26', 'day', null, false], [$entries[1930]['closed_at'], $entries[1930]['closed_precision'], $entries[1930]['closed_after'], $entries[1930]['reopened']]);
        $this->assertSame('26/05/1925', $entries[1930]['label']);
    }

    public function test_a_closure_followed_by_a_reopening_is_named_with_the_interval_of_the_timeline(): void
    {
        // Fermée pendant la guerre, rouverte ensuite, ouverte aujourd'hui (pas de date retenue).
        $entries = LineStations::closureEntries(self::station(
            [1900 => 'active', 1910 => 'active', 1920 => 'active', 1930 => 'active', 1940 => 'declassified', 1950 => 'active', 2030 => 'active'],
        ), self::YEARS);

        $this->assertSame([1940], array_keys($entries));
        $this->assertSame(['1940-12-31', 'range', 1930, true], [$entries[1940]['closed_at'], $entries[1940]['closed_precision'], $entries[1940]['closed_after'], $entries[1940]['reopened']]);
        $this->assertSame('entre 1931 et 1940', $entries[1940]['label']);
    }

    public function test_a_provisional_closure_and_the_final_dated_one_are_both_named(): void
    {
        $entries = LineStations::closureEntries(self::station(
            [1900 => 'active', 1910 => 'uncertain', 1920 => 'declassified', 1930 => 'no_commercial', 1940 => 'no_commercial', 1950 => 'declassified', 2030 => 'declassified'],
            '1948-10-03', 'day',
        ), self::YEARS);

        $this->assertSame([1920, 1950], array_keys($entries));
        $this->assertTrue($entries[1920]['reopened']);
        $this->assertSame([1910, 'range'], [$entries[1920]['closed_after'], $entries[1920]['closed_precision']]);
        $this->assertSame(['1948-10-03', false], [$entries[1950]['closed_at'], $entries[1950]['reopened']]);
    }

    public function test_an_undated_closure_today_takes_the_interval_and_is_not_reopened(): void
    {
        $entries = LineStations::closureEntries(self::station(
            [1900 => 'active', 1910 => 'active', 1920 => 'active', 1930 => 'active', 1940 => 'active', 1950 => 'declassified', 2030 => 'declassified'],
        ), self::YEARS);

        $this->assertSame([1950], array_keys($entries));
        $this->assertSame(['1950-12-31', 1940, false], [$entries[1950]['closed_at'], $entries[1950]['closed_after'], $entries[1950]['reopened']]);
    }

    public function test_a_station_first_known_closed_is_not_a_closure_in_between(): void
    {
        // Première ère connue déjà « fermée » (aucune ligne en service) : rien n'a fermé entre-temps.
        $entries = LineStations::closureEntries(self::station(
            [1900 => null, 1910 => 'declassified', 1920 => 'active', 1930 => 'active', 1940 => 'active', 1950 => 'active', 2030 => 'active'],
        ), self::YEARS);

        $this->assertSame([], $entries);
    }

    public function test_a_retained_date_without_any_closure_in_the_timeline_goes_to_its_decade(): void
    {
        // Données contradictoires (frise jamais « fermée ») : la décennie de la date retenue, comme avant.
        $entries = LineStations::closureEntries(self::station(
            [1900 => 'active', 1910 => 'active', 1920 => 'active', 1930 => 'active', 1940 => 'active', 1950 => 'active', 2030 => 'active'],
            '1937-01-01', 'year',
        ), self::YEARS);

        $this->assertSame([1940], array_keys($entries));
        $this->assertSame('1937', $entries[1940]['label']);
    }
}
