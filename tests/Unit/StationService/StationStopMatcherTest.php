<?php

namespace Tests\Unit\StationService;

use App\Enums\StopMatchMethod;
use App\Services\StationService\GtfsStop;
use App\Services\StationService\StationCandidate;
use App\Services\StationService\StationStopMatcher;
use App\Support\StationNames;
use PHPUnit\Framework\TestCase;

/**
 * Rapprochement des arrêts GTFS et des gares de VFF (StationStopMatcher) : une règle par test.
 * Un millième de degré de latitude vaut 111 m.
 */
class StationStopMatcherTest extends TestCase
{
    /** @param  list<string>  $uics */
    private static function station(int $id, string $name, float $lat, float $lon, array $uics = []): StationCandidate
    {
        return new StationCandidate($id, $lat, $lon, [(string) StationNames::normalize($name)], $uics);
    }

    private static function stop(string $name, ?float $lat = 45.0, ?float $lon = 5.0, ?string $uic = null): GtfsStop
    {
        return new GtfsStop('k', $name, $lat, $lon, $uic, []);
    }

    public function test_the_uic_code_matches_whatever_the_name_and_ignores_the_check_digit(): void
    {
        $match = (new StationStopMatcher([self::station(1, 'Alpha', 45.0, 5.0, ['8700001'])]))->match(self::stop('ALPHA-VILLE', uic: '87000018'), false);

        $this->assertSame([[1], StopMatchMethod::Uic, 0, []], [$match?->stationIds, $match?->method, $match?->distanceM, $match?->flags]);
    }

    public function test_a_shared_uic_keeps_the_nearest_station_and_those_within_200_m(): void
    {
        $matcher = new StationStopMatcher([
            self::station(1, 'Gare', 45.0, 5.0, ['8700001']),
            self::station(2, 'Gare souterraine', 45.001, 5.0, ['8700001']),
            self::station(3, 'Ancienne gare', 45.003, 5.0, ['8700001']),
        ]);
        $match = $matcher->match(self::stop('Gare', uic: '87000018'), false);

        $this->assertSame([1, 2], $match?->stationIds);
        $this->assertSame(['uic_multiple'], $match->flags);
    }

    public function test_a_uic_match_far_away_is_kept_but_flagged(): void
    {
        $match = (new StationStopMatcher([self::station(1, 'Alpha', 45.1, 5.0, ['8700001'])]))->match(self::stop('Alpha', uic: '87000018'), false);

        $this->assertSame(['uic_far'], $match?->flags);
    }

    public function test_the_same_name_matches_within_two_kilometres(): void
    {
        $matcher = new StationStopMatcher([self::station(1, 'Saint-Pierre-des-Corps', 45.015, 5.0)]);

        $this->assertSame(StopMatchMethod::Name, $matcher->match(self::stop('St Pierre des Corps'), false)?->method);
        $this->assertNull($matcher->match(self::stop('St Pierre des Corps', 44.995), false), 'à 2,2 km : aucune gare');
    }

    public function test_a_name_contained_in_the_other_matches_within_800_m_only(): void
    {
        $matcher = new StationStopMatcher([self::station(1, 'Lille-Flandres', 45.005, 5.0)]);

        $this->assertSame([1], $matcher->match(self::stop('Lille', 45.0), false)?->stationIds);
        $this->assertNull($matcher->match(self::stop('Lille', 44.99), false));
    }

    public function test_two_stations_of_the_same_name_nearby_are_flagged_ambiguous(): void
    {
        $matcher = new StationStopMatcher([self::station(1, 'Alpha', 45.001, 5.0), self::station(2, 'Alpha', 45.004, 5.0)]);
        $match = $matcher->match(self::stop('Alpha'), false);

        $this->assertSame([1], $match?->stationIds);
        $this->assertContains('ambiguous', $match->flags);
    }

    public function test_a_namesake_far_away_is_flagged_homonym(): void
    {
        $matcher = new StationStopMatcher([self::station(1, 'Saint-Benoît', 45.001, 5.0), self::station(2, 'Saint-Benoît', 47.0, 1.0)]);

        $this->assertSame(['homonym'], $matcher->match(self::stop('Saint-Benoit'), false)?->flags);
    }

    public function test_the_position_alone_needs_a_lone_station_within_150_m_and_is_asked_for(): void
    {
        $lone = new StationStopMatcher([self::station(1, 'Ailleurs', 45.001, 5.0)]);
        $crowded = new StationStopMatcher([self::station(1, 'Ailleurs', 45.001, 5.0), self::station(2, 'Autre', 44.998, 5.0)]);

        $this->assertSame(StopMatchMethod::Position, $lone->match(self::stop('Quai 3'), true)?->method);
        $this->assertNull($lone->match(self::stop('Quai 3'), false));
        $this->assertNull($crowded->match(self::stop('Quai 3'), true));
    }

    public function test_a_stop_without_position_matches_by_uic_only(): void
    {
        $matcher = new StationStopMatcher([self::station(1, 'Alpha', 45.0, 5.0, ['8700001'])]);

        $this->assertNull($matcher->match(self::stop('Alpha', null, null), true));
        $this->assertSame([1], $matcher->match(self::stop('Alpha', null, null, '87000018'), true)?->stationIds);
    }

    public function test_the_provence_railway_station_name_is_abbreviated_like_in_vff(): void
    {
        $matcher = new StationStopMatcher([self::station(1, 'Nice - CP', 45.0, 5.0)]);

        $this->assertSame([1], $matcher->match(self::stop('Nice - Gare des Chemins de fer de Provence'), false)?->stationIds);
    }
}
