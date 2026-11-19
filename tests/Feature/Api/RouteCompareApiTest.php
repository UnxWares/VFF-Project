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
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Comparaison d'un trajet entre deux époques (/api/v1/route/compare et champ GraphQL
 * `routeComparison`) sur un mini-réseau :
 *
 *   A ───────── B ───────── C        ligne AC en service en 1930 et en 2030
 *               │
 *               D                    ligne BD en service en 1930, fermée (voie en place) en 2030 ;
 *                                    la gare D est déclassée en 2030
 */
class RouteCompareApiTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Era $era1930;

    private Era $era2030;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era1930 = Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);
        $this->era2030 = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);

        $this->line('ligne-a-c', 'Ligne de A à C', [[48.0, 2.00], [48.0, 2.20]], ['in_service', 'in_service']);
        $this->line('ligne-b-d', 'Ligne de B à D', [[48.0, 2.10], [48.2, 2.10]], ['in_service', 'abandoned']);
        foreach (['a' => 2.00, 'b' => 2.10, 'c' => 2.20] as $slug => $lng) {
            $this->station($slug, 48.0, $lng, ['active', 'active']);
        }
        $this->station('d', 48.2, 2.10, ['active', 'declassified']);
    }

    /** @param  array{0:string, 1:string}  $statuses  statut 1930, 2030 */
    private function line(string $slug, string $name, array $coords, array $statuses): void
    {
        $line = Line::factory()->create(['slug' => $slug, 'name' => $name, 'is_lgv' => false, 'network' => 'principal']);
        foreach ([$this->era1930, $this->era2030] as $i => $era) {
            LineSegment::factory()->create([
                'line_id' => $line->id,
                'era_id' => $era->id,
                'geom' => LineString::make(array_map(fn ($c) => Point::makeGeodetic($c[0], $c[1]), $coords)),
                'status' => $statuses[$i],
                'traffic_type' => 'mixed',
                'is_heritage' => false,
                'gauge_mm' => 1435,
            ]);
        }
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

    public function test_route_existing_at_both_eras_is_compared(): void
    {
        $this->build();

        $response = $this->getJson('/api/v1/route/compare?from=a&to=c&era=1934&compare=2099')->assertOk();

        $response->assertJsonPath('from', ['slug' => 'a', 'name' => 'Gare A'])
            ->assertJsonPath('to.slug', 'c')
            ->assertJsonPath('earlier.requested_year', 1934)
            ->assertJsonPath('earlier.era_year', 1930)
            ->assertJsonPath('earlier.era_is_current', false)
            ->assertJsonPath('later.era_year', 2030)
            ->assertJsonPath('later.era_is_current', true)
            ->assertJsonPath('earlier.unavailable', null)
            ->assertJsonPath('later.route.stations.*.slug', ['a', 'b', 'c'])
            ->assertJsonPath('differences.outcome', 'both')
            ->assertJsonPath('differences.distance_change_m', 0)
            ->assertJsonPath('differences.same_path', true)
            ->assertJsonPath('differences.shared_lines.*.line_slug', ['ligne-a-c'])
            ->assertJsonPath('differences.lines_only_earlier', [])
            ->assertJsonPath('differences.lines_only_later', [])
            ->assertJsonPath('differences.shared_stations', 3);
        // Même itinéraire que /api/v1/route pour chaque époque.
        $this->assertSame(
            $this->getJson('/api/v1/route?from=a&to=c&era=1934')->json('distance_m'),
            $response->json('earlier.route.distance_m'),
        );
        $this->assertContains($response->json('later.route.geometry.type'), ['LineString', 'MultiLineString']);
    }

    public function test_route_that_disappeared_says_why_and_which_line_closed(): void
    {
        $this->build();

        // L'ordre des paramètres n'importe pas : les époques sont rangées chronologiquement.
        foreach (['era=1930&compare=2030', 'era=2030&compare=1930'] as $years) {
            $response = $this->getJson("/api/v1/route/compare?from=a&to=d&{$years}")->assertOk();

            $response->assertJsonPath('earlier.era_year', 1930)
                ->assertJsonPath('earlier.route.stations.*.slug', ['a', 'b', 'd'])
                ->assertJsonPath('later.era_year', 2030)
                ->assertJsonPath('later.route', null)
                ->assertJsonPath('later.unavailable.error', 'station_not_in_network')
                ->assertJsonPath('later.unavailable.station.slug', 'd')
                ->assertJsonPath('later.unavailable.status', 'declassified')
                ->assertJsonPath('differences.outcome', 'lost')
                ->assertJsonPath('differences.distance_change_m', null)
                ->assertJsonPath('differences.same_path', false)
                ->assertJsonPath('differences.shared_lines', [])
                ->assertJsonPath('differences.lines_only_later', []);
            $this->assertSame(
                ['ligne-a-c' => 'open', 'ligne-b-d' => 'closed'],
                array_column((array) $response->json('differences.lines_only_earlier'), 'other_era', 'line_slug'),
            );
        }
        // Le point d'arrivée est donné même sans trajet à l'une des époques.
        $this->getJson('/api/v1/route/compare?from=a&to=d&era=1930&compare=2030')->assertJsonPath('to.name', 'Gare D');
    }

    public function test_unknown_stations_and_same_era_are_refused_for_the_whole_comparison(): void
    {
        $this->build();

        $this->getJson('/api/v1/route/compare?from=nowhere&to=c&era=1930&compare=2030')
            ->assertNotFound()
            ->assertJsonPath('error', 'station_not_found')
            ->assertJsonPath('side', 'from');
        $this->getJson('/api/v1/route/compare?from=a&to=nulle-part&era=1930&compare=2030')
            ->assertNotFound()
            ->assertJsonPath('error', 'station_not_found')
            ->assertJsonPath('side', 'to');
        $this->getJson('/api/v1/route/compare?from=a&to=c&era=1931&compare=1938')
            ->assertStatus(422)
            ->assertJsonPath('error', 'same_era')
            ->assertJsonPath('era_year', 1930);
        $this->getJson('/api/v1/route/compare?from=a&to=a&era=1930&compare=2030')
            ->assertStatus(422)
            ->assertJsonPath('error', 'same_station');
        $this->getJson('/api/v1/route/compare?from=a&to=c&era=1820&compare=2030')
            ->assertNotFound()
            ->assertJsonPath('error', 'era_not_found');
    }

    public function test_parameters_are_validated_and_a_missing_graph_is_reported(): void
    {
        $this->getJson('/api/v1/route/compare?from=a&to=c&era=1930&compare=2030')
            ->assertStatus(409)
            ->assertJsonPath('error', 'graph_not_built');

        $this->getJson('/api/v1/route/compare?from=a&to=c&era=1930')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['compare']);
        $this->getJson('/api/v1/route/compare?from=a&to=c&compare=1799')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['compare']);
        $this->getJson('/api/v1/route/compare?to=c&compare=1930&traffic=bus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'traffic']);
    }

    public function test_graphql_field_compares_and_returns_null_when_refused(): void
    {
        $this->build();
        $query = '{ routeComparison(from: "a", to: "d", era: 1930, compare: 2030) {
            from { slug } to { name }
            earlier { era_year route { distance_m lines { line_slug } } unavailable_reason }
            later { era_year era_is_current route { distance_m } unavailable_reason unavailable_message }
            differences { outcome distance_change_m same_path lines_only_earlier { line_slug other_era } shared_stations }
        } }';

        $data = $this->graphQL($query)->assertGraphQLErrorFree()->json('data.routeComparison');

        $this->assertSame('a', $data['from']['slug']);
        $this->assertSame(1930, $data['earlier']['era_year']);
        $this->assertNull($data['earlier']['unavailable_reason']);
        $this->assertNull($data['later']['route']);
        $this->assertSame('station_not_in_network', $data['later']['unavailable_reason']);
        $this->assertStringContainsString('Gare D', $data['later']['unavailable_message']);
        $this->assertSame('lost', $data['differences']['outcome']);
        $this->assertSame('closed', array_column($data['differences']['lines_only_earlier'], 'other_era', 'line_slug')['ligne-b-d']);

        $this->graphQL('{ routeComparison(from: "a", to: "c", era: 1931, compare: 1935) { from { slug } } }')
            ->assertGraphQLErrorFree()
            ->assertExactJson(['data' => ['routeComparison' => null]]);
    }
}
