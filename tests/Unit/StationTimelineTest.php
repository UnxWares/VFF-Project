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
}
