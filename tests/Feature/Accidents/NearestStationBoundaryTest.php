<?php

namespace Tests\Feature\Accidents;

use App\Enums\AccidentLocationPrecision;
use App\Models\Station;
use App\Services\Accidents\AccidentCandidate;
use App\Services\Accidents\AccidentLineMatcher;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas limites de la gare la plus proche d'un accident : précisions de position, dates d'ouverture et de fermeture
 * (inclusives), 10 km, choix de la plus proche, arrondi, nulls.
 */
class NearestStationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 51.03;

    private const LNG = 2.37;

    private function candidate(?AccidentLocationPrecision $precision = AccidentLocationPrecision::Exact, ?string $date = '1950-06-15'): AccidentCandidate
    {
        $c = new AccidentCandidate('test:nearest');
        $c->date = $date;
        $c->lat = self::LAT;
        $c->lng = self::LNG;
        $c->locationPrecision = $precision;

        return $c;
    }

    /** Gare à `$meters` du point de l'accident, dans la direction `$bearingDeg` (0 nord, 90 est). */
    private function stationAt(float $meters, string $slug, ?string $opened = null, ?string $closed = null, float $bearingDeg = 90): Station
    {
        $p = DB::selectOne('SELECT ST_Y(g::geometry) AS lat, ST_X(g::geometry) AS lng FROM (SELECT ST_Project(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?::float8, radians(?::float8)) AS g) t',
            [self::LNG, self::LAT, $meters, $bearingDeg]);

        return Station::factory()->create(['slug' => $slug, 'name' => $slug, 'geom' => Point::makeGeodetic((float) $p->lat, (float) $p->lng),
            'opened_at' => $opened, 'closed_at' => $closed]);
    }

    private function nearest(AccidentCandidate $c): ?array
    {
        return app(AccidentLineMatcher::class)->nearestStation($c);
    }

    public function test_precision_enum_pinpoints_everything_but_the_commune_and_has_its_radii(): void
    {
        $this->assertTrue(AccidentLocationPrecision::Exact->pinpointsStation());
        $this->assertTrue(AccidentLocationPrecision::LevelCrossing->pinpointsStation());
        $this->assertTrue(AccidentLocationPrecision::Station->pinpointsStation());
        $this->assertFalse(AccidentLocationPrecision::Commune->pinpointsStation());
        $this->assertSame([800, 150, 1500, 5000], array_map(fn (AccidentLocationPrecision $p): int => $p->matchRadiusM(), AccidentLocationPrecision::cases()));
    }

    public function test_missing_position_date_or_precision_gives_no_station(): void
    {
        $this->stationAt(10, 'a');

        $noLat = $this->candidate();
        $noLat->lat = null;
        $noLng = $this->candidate();
        $noLng->lng = null;

        $this->assertNull($this->nearest($noLat));
        $this->assertNull($this->nearest($noLng));
        $this->assertNull($this->nearest($this->candidate(date: null)));
        $this->assertNull($this->nearest($this->candidate(null)));
        $this->assertNull($this->nearest($this->candidate(AccidentLocationPrecision::Commune)));
        $this->assertNotNull($this->nearest($this->candidate()));
    }

    public function test_an_empty_table_gives_no_station(): void
    {
        $this->assertNull($this->nearest($this->candidate()));
    }

    public function test_the_limit_is_10_kilometres_east_or_north(): void
    {
        $in = $this->stationAt(9950, 'in');
        $this->assertSame($in->id, $this->nearest($this->candidate())['id']);
        $this->assertLessThanOrEqual(10000, $this->nearest($this->candidate())['distance_m']);
        $this->assertGreaterThan(9900, $this->nearest($this->candidate())['distance_m']);

        $in->delete();
        $this->stationAt(10050, 'out');
        $this->assertNull($this->nearest($this->candidate()));

    }

    public function test_the_limit_is_also_10_kilometres_to_the_north(): void
    {
        $north = $this->stationAt(9950, 'north', bearingDeg: 0);

        $this->assertSame($north->id, $this->nearest($this->candidate())['id'] ?? null);
    }

    public function test_the_nearest_wins_whatever_the_creation_order_and_the_distance_is_rounded(): void
    {
        $this->stationAt(800, 'far');
        $near = $this->stationAt(120.7, 'near', bearingDeg: 200);
        $this->stationAt(5000, 'farther', bearingDeg: 10);

        $found = $this->nearest($this->candidate());

        $this->assertSame($near->id, $found['id']);
        $this->assertIsInt($found['distance_m']);
        $this->assertSame(121, $found['distance_m']);
    }

    public function test_a_station_on_the_exact_point_is_at_zero_metres(): void
    {
        $s = Station::factory()->create(['geom' => Point::makeGeodetic(self::LAT, self::LNG), 'opened_at' => null]);

        $this->assertSame(['id' => $s->id, 'distance_m' => 0], $this->nearest($this->candidate()));
    }

    public function test_the_opening_date_is_inclusive_and_the_day_before_excludes(): void
    {
        $this->stationAt(100, 'same-day', opened: '1950-06-15');
        $this->assertNotNull($this->nearest($this->candidate()));

        DB::table('stations')->update(['opened_at' => '1950-06-16']);
        $this->assertNull($this->nearest($this->candidate()));
        $this->assertNotNull($this->nearest($this->candidate(date: '1950-06-16')));
    }

    public function test_the_closing_date_is_inclusive_and_the_day_after_excludes(): void
    {
        $this->stationAt(100, 'last-day', opened: '1900-01-01', closed: '1950-06-15');
        $this->assertNotNull($this->nearest($this->candidate()));

        DB::table('stations')->update(['closed_at' => '1950-06-14']);
        $this->assertNull($this->nearest($this->candidate()));
        $this->assertNotNull($this->nearest($this->candidate(date: '1950-06-14')));
    }

    public function test_unknown_dates_never_exclude_a_station(): void
    {
        $this->stationAt(100, 'undated', opened: null, closed: null);

        $this->assertNotNull($this->nearest($this->candidate(date: '1830-01-01')));
        $this->assertNotNull($this->nearest($this->candidate(date: '2030-12-31')));
    }

    public function test_a_closer_station_closed_before_the_accident_is_skipped_for_a_farther_open_one(): void
    {
        $this->stationAt(50, 'closed', opened: '1880-01-01', closed: '1940-01-01');
        $open = $this->stationAt(700, 'open', opened: '1880-01-01');

        $this->assertSame($open->id, $this->nearest($this->candidate())['id']);
    }

    public function test_each_pinpointing_precision_gives_the_same_station(): void
    {
        $s = $this->stationAt(300, 'x');

        foreach ([AccidentLocationPrecision::Exact, AccidentLocationPrecision::LevelCrossing, AccidentLocationPrecision::Station] as $p) {
            $this->assertSame($s->id, $this->nearest($this->candidate($p))['id'], $p->value);
        }
    }
}
