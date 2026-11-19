<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Graphe par décennie (vff:build-network-graph) et itinéraires historiques
 * (/api/v1/route) sur un mini-réseau :
 *
 *   A ───────── B ───────── C   ─ 37 m ─   E      ligne AC (1930, 2030) ; ligne CE avec une lacune
 *               │
 *               D                              ligne BD : branchée en T au milieu de AC,
 *                                              en service en 1930, fermée en 2030
 */
class RouteApiTest extends TestCase
{
    use RefreshDatabase;

    private Era $era1930;

    private Era $era2030;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era1930 = Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);
        $this->era2030 = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);

        $ac = $this->line('Ligne de A à C', [[48.0, 2.00], [48.0, 2.20]], ['in_service', 'in_service']);
        $this->line('Ligne de B à D', [[48.0, 2.10], [48.2, 2.10]], ['in_service', 'abandoned']);
        $this->line('Ligne de C à E', [[48.0, 2.2005], [48.0, 2.30]], ['in_service', 'in_service']);

        foreach (['a' => 2.00, 'b' => 2.10, 'c' => 2.20, 'e' => 2.30] as $slug => $lng) {
            $this->station($slug, 48.0, $lng, ['active', 'active']);
        }
        $this->station('d', 48.2, 2.10, ['active', 'declassified']);
    }

    /** @param  array{0:string, 1:string}  $statuses  statut 1930, 2030 */
    private function line(string $name, array $coords, array $statuses): Line
    {
        $line = Line::factory()->create(['name' => $name, 'is_lgv' => false, 'network' => 'principal']);
        foreach ([$this->era1930, $this->era2030] as $i => $era) {
            LineSegment::factory()->create([
                'line_id' => $line->id,
                'era_id' => $era->id,
                'geom' => LineString::make(array_map(fn ($c) => Point::makeGeodetic($c[0], $c[1]), $coords)),
                'status' => $statuses[$i],
                'traffic_type' => 'mixed',
                'is_heritage' => false,
                'gauge_mm' => 1435,
                'source' => 'osm',
            ]);
        }

        return $line;
    }

    /** @param  array{0:string, 1:string}  $statuses  statut 1930, 2030 */
    private function station(string $slug, float $lat, float $lng, array $statuses): void
    {
        $station = Station::factory()->create([
            'slug' => $slug, 'name' => 'Gare '.strtoupper($slug), 'city' => 'Ville '.strtoupper($slug),
            'geom' => Point::makeGeodetic($lat, $lng), 'status' => $statuses[1],
        ]);
        foreach ([$this->era1930, $this->era2030] as $i => $era) {
            DB::table('station_eras')->insert([
                'station_id' => $station->id, 'era_id' => $era->id, 'status' => $statuses[$i],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function build(): void
    {
        $this->artisan('vff:build-network-graph')->assertSuccessful();
    }

    public function test_graph_is_built_per_era_with_measures(): void
    {
        $this->build();

        $b1930 = DB::table('network_graph_builds')->where('era_id', $this->era1930->id)->first();
        $this->assertSame(5, $b1930->station_nodes);
        $this->assertSame(1, $b1930->components);
        $this->assertSame(0, $b1930->unattached_stations);
        $this->assertGreaterThanOrEqual(1, $b1930->gap_joins);
        $this->assertSame('station_eras', $b1930->stations_source);
        // A-B, B-C, B-D, C-E
        $this->assertSame(4, DB::table('station_edges')->where('era_id', $this->era1930->id)->count());

        $b2030 = DB::table('network_graph_builds')->where('era_id', $this->era2030->id)->first();
        $this->assertSame(4, $b2030->station_nodes, 'D est fermée en 2030');
        $this->assertSame(3, DB::table('station_edges')->where('era_id', $this->era2030->id)->count());
        $this->assertNull(DB::table('station_edges')->where('era_id', $this->era2030->id)->whereNull('geom')->value('id'));

        // Rejouable : même résultat, pas de doublon.
        $this->build();
        $this->assertSame(4, DB::table('station_edges')->where('era_id', $this->era1930->id)->count());
        $this->assertSame(2, DB::table('network_graph_builds')->count());
    }

    public function test_route_uses_the_network_of_the_requested_year(): void
    {
        $this->build();

        $response = $this->getJson('/api/v1/route?from=a&to=d&era=1934')->assertOk();

        $response->assertJsonPath('era_year', 1930)
            ->assertJsonPath('requested_year', 1934)
            ->assertJsonPath('from.slug', 'a')
            ->assertJsonPath('to.slug', 'd')
            ->assertJsonPath('stations.*.slug', ['a', 'b', 'd'])
            ->assertJsonPath('lines.*.line_name', ['Ligne de A à C', 'Ligne de B à D'])
            ->assertJsonPath('lines.0.from_slug', 'a')
            ->assertJsonPath('lines.0.to_slug', 'b')
            ->assertJsonPath('lines.1.to_slug', 'd');
        // 0,1° de longitude à 48° N ≈ 7,45 km ; 0,2° de latitude ≈ 22,2 km.
        $this->assertEqualsWithDelta(29_700, $response->json('distance_m'), 400);
        $this->assertContains($response->json('geometry.type'), ['LineString', 'MultiLineString']);
    }

    public function test_closed_line_makes_the_route_impossible_later(): void
    {
        $this->build();

        $this->getJson('/api/v1/route?from=a&to=d&era=2030')
            ->assertNotFound()
            ->assertJsonPath('error', 'station_not_in_network')
            ->assertJsonPath('status', 'declassified');

        $this->getJson('/api/v1/route?from=a&to=c&era=2030')->assertOk()->assertJsonPath('hops', 2);
    }

    public function test_route_crosses_a_small_gap_between_two_traces_and_says_so(): void
    {
        $this->build();

        $this->getJson('/api/v1/route?from=Gare A&to=e&year=1930')
            ->assertOk()
            ->assertJsonPath('to.slug', 'e')
            ->assertJsonPath('gap_joins', 1)
            ->assertJsonFragment(['warnings' => ['gap_joins']]);
    }

    public function test_errors_are_explicit(): void
    {
        $this->getJson('/api/v1/route?from=a&to=c&era=1930')->assertStatus(409)->assertJsonPath('error', 'graph_not_built');

        $this->build();

        $this->getJson('/api/v1/route?from=nowhere&to=c&era=1930')->assertNotFound()->assertJsonPath('error', 'station_not_found');
        $this->getJson('/api/v1/route?from=a&to=c&era=1820')->assertNotFound()->assertJsonPath('error', 'era_not_found');
        $this->getJson('/api/v1/route?from=a&to=a&era=1930')->assertStatus(422)->assertJsonPath('error', 'same_station');
        $this->getJson('/api/v1/route?from=a&to=c&traffic=bus')->assertUnprocessable();
    }
}
