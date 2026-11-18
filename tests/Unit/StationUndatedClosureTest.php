<?php

namespace Tests\Unit;

use App\Services\StationTimeline;
use PHPUnit\Framework\TestCase;

/**
 * Règle des fermetures non datées (StationTimeline::unconfirmedNote) : une
 * gare fermée aujourd'hui n'est jamais affirmée ouverte au-delà de la
 * dernière preuve d'ouverture. Cas réel : Courtonne-la-Meurdrac, halte de la
 * ligne de Mantes à Cherbourg ouverte en 1855, fermée à une date inconnue,
 * était « ouverte aux voyageurs » de 1860 à 2020.
 */
class StationUndatedClosureTest extends TestCase
{
    private const ERAS = [1850, 1860, 1870, 1880, 1890, 1900, 1910, 1920, 1930, 1940, 1950, 1960, 1970, 1980, 1990, 2000, 2010, 2020];

    private function ev(string $type, string $date, string $precision = 'day'): array
    {
        return ['type' => $type, 'date' => $date, 'precision' => $precision];
    }

    /** @return array<int, ?string> ère → note (null = statut affirmé) */
    private function notes(array $events, string $today, string $status = StationTimeline::ACTIVE): array
    {
        $out = [];
        foreach (self::ERAS as $y) {
            $out[$y] = StationTimeline::unconfirmedNote($events, self::ERAS, $y, $status, $today);
        }

        return $out;
    }

    public function test_opening_only_confirms_the_next_era(): void
    {
        $notes = $this->notes([$this->ev('opening', '1855-01-01', 'year')], StationTimeline::CLOSED);

        $this->assertNull($notes[1860]);
        $this->assertSame(StationTimeline::NOTE_UNDATED, $notes[1870]);
        $this->assertSame(StationTimeline::NOTE_UNDATED, $notes[2020]);
    }

    public function test_dated_closure_confirms_every_era_before_it(): void
    {
        $notes = $this->notes([$this->ev('opening', '1855-01-01'), $this->ev('closure', '1972-03-06')], StationTimeline::CLOSED);

        $this->assertSame([], array_filter($notes, fn ($n, $y) => $y < 1972 && $n !== null, ARRAY_FILTER_USE_BOTH));
    }

    public function test_after_and_decade_closures(): void
    {
        $after = $this->notes([$this->ev('opening', '1887-05-09'), $this->ev('closure', '1950-01-01', 'after')], StationTimeline::CLOSED);
        $this->assertNull($after[1950]);
        $this->assertSame('Fermeture non datée (après 1950)', $after[1960]);

        $decade = $this->notes([$this->ev('opening', '1879-12-22'), $this->ev('closure', '1980-01-01', 'decade')], StationTimeline::CLOSED);
        $this->assertNull($decade[1970]);
        $this->assertSame('Fermeture dans les années 1980', $decade[1980]);

        $before = $this->notes([$this->ev('opening', '1858-07-01'), $this->ev('closure', '1984-01-01', 'before')], StationTimeline::CLOSED);
        $this->assertNull($before[1860]);
        $this->assertSame('Fermeture non datée (avant 1984)', $before[1870]);
    }

    public function test_freight_only_after_a_dated_passenger_closure_is_not_confirmed(): void
    {
        $events = [$this->ev('opening', '1866-01-01'), $this->ev('closure_passenger', '1938-05-15')];

        $notes = $this->notes($events, StationTimeline::CLOSED, StationTimeline::FREIGHT);
        $this->assertNull($notes[1930]);
        $this->assertSame(StationTimeline::NOTE_UNDATED_FREIGHT, $notes[1940]);
    }

    public function test_freight_station_today_checks_only_passenger_service(): void
    {
        $events = [$this->ev('opening', '1866-01-01')];

        $this->assertSame(StationTimeline::NOTE_UNDATED_PASSENGER, $this->notes($events, StationTimeline::FREIGHT)[1950]);
        $this->assertNull($this->notes($events, StationTimeline::FREIGHT, StationTimeline::FREIGHT)[1950]);
    }

    public function test_open_station_or_closed_status_is_never_flagged(): void
    {
        $events = [$this->ev('opening', '1866-01-01')];

        $this->assertSame([], array_filter($this->notes($events, StationTimeline::ACTIVE)));
        $this->assertSame([], array_filter($this->notes($events, StationTimeline::CLOSED, StationTimeline::CLOSED)));
    }

    public function test_reopening_is_a_proof(): void
    {
        $events = [$this->ev('opening', '1866-01-01'), $this->ev('closure_passenger', '1939-01-01'), $this->ev('reopening', '1950-06-01')];

        $notes = $this->notes($events, StationTimeline::CLOSED);
        $this->assertNull($notes[1930]);
        $this->assertNull($notes[1950]);
        $this->assertSame(StationTimeline::NOTE_UNDATED, $notes[1960]);
    }

    public function test_timeline_ignores_after_and_shifts_decade_closures(): void
    {
        $events = StationTimeline::forTimeline([
            $this->ev('opening', '1879-12-22'), $this->ev('closure', '1950-01-01', 'after'), $this->ev('closure', '1980-01-01', 'decade'),
        ]);

        $this->assertSame([['opening', '1879-12-22'], ['closure', '1989-12-31']], array_map(fn ($e) => [$e['type'], $e['date']], $events));
        $this->assertSame(StationTimeline::ACTIVE, StationTimeline::statusFromEvents($events, 1980, true));
        $this->assertSame(StationTimeline::CLOSED, StationTimeline::statusFromEvents($events, 1990, true));
    }
}
