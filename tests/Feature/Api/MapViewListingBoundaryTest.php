<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Map\ViewListing;
use App\Services\StationLineCoverage;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas limites de la liste des lignes et gares d'une emprise : bornes de largeur, états des lignes, kilomètres dans
 * l'emprise, plafond de 300 entrées, règle des gares fermées (300 m), ère sans calcul, ordre.
 */
class MapViewListingBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private const BOX = [1.9, 47.9, 2.3, 48.1];

    private Era $past;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 1960]);
        $this->current = Era::factory()->current()->create(['year' => 2030]);
    }

    private function line(string $name, ?string $code = null, bool $lgv = false): Line
    {
        return Line::factory()->create(['name' => $name, 'slug' => 'l-'.md5($name), 'code' => $code, 'is_lgv' => $lgv]);
    }

    private function segment(Line $line, Era $era, string $status, string $traffic = 'unknown', bool $heritage = false, int $speed = 100, string $wkt = 'LINESTRING(2.0 48.0, 2.1 48.0)'): void
    {
        DB::table('line_segments')->insert([
            'line_id' => $line->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => $traffic, 'is_heritage' => $heritage,
            'max_speed_kmh' => $speed, 'length_meters' => 7400, 'electrified' => false, 'source' => 'osm',
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function list(int $year = 2030, ?array $bbox = null): array
    {
        return app(ViewListing::class)->list($year, $bbox ?? self::BOX);
    }

    /** @return array<string, string> nom de la ligne => état */
    private function states(): array
    {
        $out = [];
        foreach ($this->list()['lines']['items'] as $i) {
            $out[$i['name']] = $i['state'];
        }

        return $out;
    }

    private function station(string $name, float $lat, float $lng, string $status = 'active', ?Era $era = null, bool $uncertain = false): Station
    {
        $station = Station::factory()->create(['slug' => 's-'.md5($name), 'name' => $name, 'city' => null, 'status' => $status, 'geom' => Point::makeGeodetic($lat, $lng)]);
        if ($era !== null) {
            DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $era->id, 'status' => $status, 'uncertain' => $uncertain, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $station;
    }

    /** Point à `$meters` au nord d'un point de la voie (2,05 ; 48,0). */
    private function northOfTrack(float $meters): float
    {
        return (float) DB::selectOne('SELECT ST_Y(ST_Project(ST_SetSRID(ST_MakePoint(2.05, 48.0), 4326)::geography, ?::float8, 0::float8)::geometry) AS lat', [$meters])->lat;
    }

    public function test_the_width_limits_are_inclusive_for_the_view_and_exclusive_above(): void
    {
        $this->assertFalse(ViewListing::tooWide([0.0, 0.0, 4.0, 3.0]));
        $this->assertTrue(ViewListing::tooWide([0.0, 0.0, 4.0000001, 3.0]));
        $this->assertTrue(ViewListing::tooWide([0.0, 0.0, 4.0, 3.0000001]));
        $this->assertFalse(ViewListing::tooWide([10.0, 10.0, 4.0, 3.0]), 'une emprise à l\'envers n\'est pas « trop large »');
        $this->assertSame([4.0, 3.0, 300], [ViewListing::MAX_LON_SPAN, ViewListing::MAX_LAT_SPAN, ViewListing::LIMIT]);
    }

    public function test_a_too_wide_view_computes_nothing_and_says_so(): void
    {
        $line = $this->line('Ligne A');
        $this->segment($line, $this->current, 'in_service');

        $r = $this->list(2030, [0.0, 45.0, 4.5, 46.0]);

        $this->assertTrue($r['too_wide']);
        $this->assertSame(['total' => 0, 'items' => []], $r['lines']);
        $this->assertSame(['total' => 0, 'items' => []], $r['stations']);
        $this->assertSame([0.0, 45.0, 4.5, 46.0], $r['bbox']);
        $this->assertSame(2030, $r['era']);
        $this->assertSame(300, $r['limit']);
    }

    public function test_an_unknown_era_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->list(1999);
    }

    public function test_an_inverted_view_is_read_as_the_same_rectangle_without_error(): void
    {
        $line = $this->line('Ligne A');
        $this->segment($line, $this->current, 'in_service');

        $r = $this->list(2030, [2.3, 48.1, 1.9, 47.9]);

        $this->assertFalse($r['too_wide']);
        $this->assertSame(1, $r['lines']['total']);
    }

    public function test_the_state_of_a_line_depends_on_status_heritage_traffic_and_speed(): void
    {
        $cases = [
            'heritage en service' => ['in_service', 'unknown', true, 100, 'heritage'],
            'heritage voie unique' => ['single_track', 'mixed', true, 100, 'heritage'],
            'heritage abandonnée' => ['abandoned', 'unknown', true, 100, 'velorail'],
            'heritage déposée' => ['dismantled', 'unknown', true, 100, 'dismantled'],
            'arasée' => ['razed', 'unknown', false, 100, 'dismantled'],
            'voyageurs' => ['in_service', 'passenger', false, 100, 'lgv'],
            'mixte' => ['in_service', 'mixed', false, 100, 'mixed'],
            'fret' => ['single_track', 'freight', false, 100, 'freight'],
            'inconnu' => ['in_service', 'unknown', false, 100, 'unknown'],
            'fret à 250' => ['in_service', 'freight', false, 250, 'lgv'],
            'fret à 249' => ['in_service', 'freight', false, 249, 'freight'],
            'voie de service' => ['service_track', 'freight', false, 100, 'service_track'],
            'en construction' => ['construction', 'unknown', false, 100, 'construction'],
            'abandonnée' => ['abandoned', 'passenger', false, 100, 'abandoned'],
        ];
        $expected = [];
        foreach ($cases as $name => [$status, $traffic, $heritage, $speed, $state]) {
            $this->segment($this->line($name), $this->current, $status, $traffic, $heritage, $speed);
            $expected[$name] = $state;
        }

        $actual = $this->states();

        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_a_line_flagged_lgv_or_named_lgv_is_lgv_whatever_its_traffic(): void
    {
        $this->segment($this->line('Ligne marquée', null, true), $this->current, 'in_service', 'freight');
        $this->segment($this->line('LGV Sud-Est'), $this->current, 'in_service', 'mixed');
        $this->segment($this->line('Ligne à grande vitesse X'), $this->current, 'in_service', 'mixed');
        $this->segment($this->line('Ligne SLGV'), $this->current, 'in_service', 'mixed');

        $states = $this->states();

        $this->assertSame(['lgv', 'lgv', 'lgv', 'mixed'], [$states['Ligne marquée'], $states['LGV Sud-Est'], $states['Ligne à grande vitesse X'], $states['Ligne SLGV']]);
    }

    public function test_only_the_segments_of_the_era_count(): void
    {
        $line = $this->line('Ligne A');
        $this->segment($line, $this->past, 'abandoned');

        $this->assertSame(0, $this->list(2030)['lines']['total']);
        $this->assertSame(1, $this->list(1960)['lines']['total']);
    }

    public function test_kilometres_are_cut_at_the_view_and_rounded_to_a_tenth(): void
    {
        $line = $this->line('Ligne A');
        $this->segment($line, $this->current, 'in_service', 'mixed', false, 100, 'LINESTRING(2.0 48.0, 2.1 48.0)');

        $whole = $this->list()['lines']['items'][0]['km'];
        $half = $this->list(2030, [1.9, 47.9, 2.05, 48.1])['lines']['items'][0]['km'];

        $this->assertEqualsWithDelta(7.4, $whole, 0.2);
        $this->assertEqualsWithDelta($whole / 2, $half, 0.1);
        $this->assertSame(round($whole, 1), $whole);
    }

    public function test_a_segment_only_touching_the_view_gives_zero_kilometres_but_a_line_entry(): void
    {
        $line = $this->line('Ligne A');
        $this->segment($line, $this->current, 'in_service', 'mixed', false, 100, 'LINESTRING(2.3 48.0, 2.4 48.0)');

        $items = $this->list()['lines']['items'];

        $this->assertSame(0.0, $items[0]['km'] ?? 0.0);
    }

    public function test_the_lines_are_ordered_by_kilometres_then_name_and_capped_at_300_with_the_full_total(): void
    {
        for ($i = 0; $i < 305; $i++) {
            $line = $this->line(sprintf('Ligne %03d', $i));
            // 304 lignes de même longueur, une plus courte en dernier par la longueur.
            $this->segment($line, $this->current, 'in_service', 'mixed', false, 100, $i === 304 ? 'LINESTRING(2.0 48.0, 2.0001 48.0)' : 'LINESTRING(2.0 48.0, 2.1 48.0)');
        }

        $r = $this->list();

        $this->assertSame(305, $r['lines']['total']);
        $this->assertCount(300, $r['lines']['items']);
        $this->assertSame('Ligne 000', $r['lines']['items'][0]['name']);
        $this->assertSame('Ligne 299', $r['lines']['items'][299]['name']);
        $this->assertNotContains('Ligne 304', array_column($r['lines']['items'], 'name'));
    }

    public function test_a_past_era_without_computed_stations_shows_none(): void
    {
        $this->station('Gare A', 48.0, 2.0, 'active');

        $this->assertSame(['total' => 0, 'items' => []], $this->list(1960)['stations']);
    }

    public function test_the_current_era_without_computed_stations_uses_their_current_status_without_the_declassified(): void
    {
        $this->station('Gare A', 48.0, 2.05, 'active');
        $this->station('Gare B', 48.0, 2.06, 'tourist');
        $this->station('Gare C', 48.0, 2.07, 'declassified');

        $r = $this->list()['stations'];

        $this->assertSame(['Gare A', 'Gare B'], array_column($r['items'], 'name'));
        $this->assertSame(['active', 'tourist'], array_column($r['items'], 'state'));
        $this->assertSame(2, $r['total']);
    }

    public function test_the_fallback_never_shows_a_declassified_station_even_beside_a_track(): void
    {
        $this->segment($this->line('Ligne A'), $this->current, 'abandoned');
        $this->station('Déclassée', $this->northOfTrack(10), 2.05, 'declassified');

        $this->assertSame(0, $this->list()['stations']['total']);
    }

    public function test_a_declassified_station_is_shown_only_with_a_track_within_300_metres(): void
    {
        $line = $this->line('Ligne A');
        $this->segment($line, $this->current, 'abandoned');
        $radius = StationLineCoverage::ISOLATION_RADIUS_M;
        $this->station('Proche', $this->northOfTrack($radius - 1), 2.05, 'declassified', $this->current);
        $this->station('Loin', $this->northOfTrack($radius + 2), 2.07, 'declassified', $this->current);
        $this->station('Active loin', $this->northOfTrack(5000), 2.06, 'active', $this->current);

        $names = array_column($this->list()['stations']['items'], 'name');

        $this->assertSame(['Active loin', 'Proche'], $names);
        $this->assertSame(300, $radius);
    }

    public function test_a_dismantled_or_razed_or_other_era_track_does_not_keep_a_declassified_station(): void
    {
        $a = $this->line('Ligne A');
        $b = $this->line('Ligne B');
        $c = $this->line('Ligne C');
        $this->segment($a, $this->current, 'dismantled');
        $this->segment($b, $this->current, 'razed');
        $this->segment($c, $this->past, 'in_service');
        $this->station('Déclassée', $this->northOfTrack(10), 2.05, 'declassified', $this->current);

        $this->assertSame(0, $this->list()['stations']['total']);
    }

    public function test_the_uncertain_flag_shows_as_a_state_except_for_a_declassified_station(): void
    {
        $line = $this->line('Ligne A');
        $this->segment($line, $this->current, 'in_service');
        $this->station('A douteuse', 48.0, 2.04, 'active', $this->current, true);
        $this->station('B sûre', 48.0, 2.05, 'active', $this->current, false);
        $this->station('C déclassée douteuse', $this->northOfTrack(10), 2.06, 'declassified', $this->current, true);

        $states = [];
        foreach ($this->list()['stations']['items'] as $i) {
            $states[$i['name']] = $i['state'];
        }

        $this->assertSame(['A douteuse' => 'uncertain', 'B sûre' => 'active', 'C déclassée douteuse' => 'declassified'], $states);
    }

    public function test_stations_are_ordered_by_name_and_limited_with_the_full_total(): void
    {
        $line = $this->line('Ligne A');
        $this->segment($line, $this->current, 'in_service');
        for ($i = 0; $i < 302; $i++) {
            $this->station(sprintf('Gare %03d', 301 - $i), 48.0, 2.0 + $i * 0.0001, 'active', $this->current);
        }

        $r = $this->list()['stations'];

        $this->assertSame(302, $r['total']);
        $this->assertCount(300, $r['items']);
        $this->assertSame('Gare 000', $r['items'][0]['name']);
        $this->assertSame('Gare 299', $r['items'][299]['name']);
    }

    public function test_a_station_outside_the_view_or_on_its_edge(): void
    {
        $this->station('Dehors', 48.2, 2.0, 'active', $this->current);
        $this->station('Au bord', 48.1, 2.3, 'active', $this->current);

        $this->assertSame(['Au bord'], array_column($this->list()['stations']['items'], 'name'));
    }

    public function test_a_station_without_city_gives_null_and_a_city_is_passed_through(): void
    {
        $this->station('Sans ville', 48.0, 2.0, 'active', $this->current);
        $this->station('Avec ville', 48.0, 2.01, 'active', $this->current)->update(['city' => 'Orléans']);

        $cities = array_column($this->list()['stations']['items'], 'city', 'name');

        $this->assertSame(['Avec ville' => 'Orléans', 'Sans ville' => null], $cities);
    }
}
