<?php

namespace Tests\Unit\Timetable;

use App\Services\StationService\FeedDefinition;
use App\Services\StationService\GtfsArchive;
use App\Services\StationService\StationCandidate;
use App\Services\StationService\StationStopMatcher;
use App\Services\Timetable\TimetableAggregator;
use App\Services\Timetable\TimetableProfile;
use App\Services\Timetable\TimetableResult;
use App\Services\Timetable\TypicalDay;
use App\Services\Timetable\TypicalDayReader;
use PHPUnit\Framework\TestCase;

/**
 * Rattachement par code UIC et agrégation par gare et par ligne, sur le flux de tests/Fixtures/Timetable/mini
 * (mardi 8 décembre 2026 : A - B - C en TER, C - A en TGV sans arrêt en B).
 */
class TimetableAggregatorTest extends TestCase
{
    private const DIR = __DIR__.'/../../Fixtures/Timetable/mini';

    private const STATIONS = [1 => ['name' => 'Alpha', 'slug' => 'alpha'], 2 => ['name' => 'Bravo', 'slug' => 'bravo'], 3 => ['name' => 'Charlie', 'slug' => 'charlie']];

    private function aggregate(array $stationLines = []): TimetableResult
    {
        $definition = FeedDefinition::fromArray(['key' => 'sncf', 'name' => 'Test', 'provider' => 'sncf', 'licence' => 'ODbL', 'page' => 'https://example.org',
            'url' => 'https://example.org/gtfs.zip', 'modes' => ['rail'], 'uic_fields' => ['parent_station', 'stop_id'], 'uic_pattern' => '/OCE(?:[^-]*-)?([0-9]{8})$/']);
        $day = (new TypicalDayReader)->read(new GtfsArchive(self::DIR), TimetableProfile::sncf(), $definition, 20261208);
        $matcher = new StationStopMatcher([
            new StationCandidate(1, 45.0, 1.0, ['alpha'], ['8710001']),
            new StationCandidate(2, 45.0, 2.0, ['bravo'], ['8720002']),
            new StationCandidate(3, 45.0, 3.0, ['charlie'], ['8730003']),
        ]);

        return (new TimetableAggregator)->aggregate([[$day, $matcher]], self::STATIONS, $stationLines, [['licence' => 'ODbL']]);
    }

    public function test_each_train_counts_once_per_station_with_its_type(): void
    {
        $r = $this->aggregate();

        $this->assertSame(2, $r->stations[1]['trains']);
        $this->assertSame(['ter' => 1, 'tgv' => 1], $r->stations[1]['by_type']);
        $this->assertSame(1, $r->stations[2]['trains'], 'le TGV ne s\'arrête pas à B');
        $this->assertSame(['ter' => 1], $r->stations[2]['by_type']);
    }

    public function test_first_and_last_times_come_from_departures_and_arrivals(): void
    {
        $r = $this->aggregate();

        $this->assertSame('07:00', $r->stations[1]['first']);
        $this->assertSame('19:00', $r->stations[1]['last']);
        $this->assertSame('08:00', $r->stations[3]['first']);
        $this->assertSame('18:00', $r->stations[3]['last']);
    }

    public function test_two_directions_with_their_destinations(): void
    {
        $dirs = $this->aggregate()->stations[2]['directions'];

        $this->assertCount(1, $dirs, 'un seul train à B : un seul sens');
        $a = $this->aggregate()->stations[1];
        $this->assertCount(2, $a['directions']);
        $east = array_values(array_filter($a['directions'], fn (array $d): bool => $d['heading'] === 'e'))[0];
        $this->assertSame('Charlie', $east['destinations'][0]['name']);
        $this->assertSame('charlie', $east['destinations'][0]['slug']);
        $west = array_values(array_filter($a['directions'], fn (array $d): bool => $d['heading'] === 'w'))[0];
        $this->assertSame([], $west['destinations'], 'le TGV termine à A');
        $this->assertSame('Charlie', $west['origins'][0]['name']);
    }

    public function test_destinations_are_the_terminus_of_the_trains_that_leave(): void
    {
        $r = $this->aggregate();

        $this->assertSame([['name' => 'Charlie', 'slug' => 'charlie', 'trains' => 1]], $r->stations[1]['destinations']);
        $this->assertSame([], $r->stations[3]['destinations'] === [] ? [] : array_filter($r->stations[3]['destinations'], fn (array $d): bool => $d['name'] === 'Charlie'));
        $this->assertSame('Alpha', $r->stations[3]['destinations'][0]['name']);
    }

    public function test_a_line_counts_each_train_once_when_two_consecutive_stops_are_on_it(): void
    {
        $r = $this->aggregate([1 => [10], 2 => [10], 3 => [10, 11]]);

        // TER A-B-C : couples A-B et B-C sur la ligne 10, une seule fois ; TGV C-A : couple sur la ligne 10.
        $this->assertSame(2, $r->lines[10]['trains']);
        $this->assertSame(['ter' => 1, 'tgv' => 1], $r->lines[10]['by_type']);
        $this->assertArrayNotHasKey(11, $r->lines, 'la ligne 11 n\'a qu\'une gare : aucun couple');
    }

    public function test_regional_feeds_only_fill_stations_the_primary_feeds_do_not_serve(): void
    {
        $definition = FeedDefinition::fromArray(['key' => 'sncf', 'name' => 'Test', 'provider' => 'sncf', 'licence' => 'ODbL', 'page' => 'https://example.org',
            'url' => 'https://example.org/gtfs.zip', 'modes' => ['rail'], 'uic_fields' => ['parent_station', 'stop_id'], 'uic_pattern' => '/OCE(?:[^-]*-)?([0-9]{8})$/']);
        $primary = (new TypicalDayReader)->read(new GtfsArchive(self::DIR), TimetableProfile::sncf(), $definition, 20261208);
        $regional = new TypicalDay('zou', null, 20261208, ['2026-11-16', '2027-01-31'], $primary->zones, $primary->runs);
        $matcher = new StationStopMatcher([new StationCandidate(1, 45.0, 1.0, ['alpha'], ['8710001']), new StationCandidate(4, 45.0, 9.0, ['delta'], ['8999999'])]);

        $r = (new TimetableAggregator)->aggregate([[$primary, $matcher], [$regional, $matcher]], self::STATIONS, []);

        $this->assertSame(2, $r->stations[1]['trains'], 'pas de double compte avec un flux régional qui republie les mêmes TER');
        $this->assertSame(['sncf'], $r->stations[1]['feeds']);
    }
}
