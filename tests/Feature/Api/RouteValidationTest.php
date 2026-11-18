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
 * /api/v1/route : validation des paramètres (422 avec messages), gares
 * ambiguës, absence de chemin entre deux réseaux disjoints.
 *
 *   Gare Centrale (Lyon) ── b          Gare Centrale (Lille) ── d
 *   (composante 1)                      (composante 2, à 550 km)
 */
class RouteValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $era = Era::factory()->current()->create(['year' => 2030]);

        foreach ([['lyon', 45.76, 4.83], ['lille', 50.63, 3.07]] as [$city, $lat, $lng]) {
            $line = Line::factory()->create(['name' => "Ligne de {$city}", 'network' => 'principal', 'is_lgv' => false]);
            LineSegment::factory()->create([
                'line_id' => $line->id, 'era_id' => $era->id, 'status' => 'in_service', 'traffic_type' => 'mixed',
                'is_heritage' => false, 'gauge_mm' => 1435,
                'geom' => LineString::make([Point::makeGeodetic($lat, $lng), Point::makeGeodetic($lat, $lng + 0.1)]),
            ]);
            foreach ([["centrale-{$city}", 'Gare Centrale', $lng], ["terminus-{$city}", "Terminus {$city}", $lng + 0.1]] as [$slug, $name, $x]) {
                $station = Station::factory()->create(['slug' => $slug, 'name' => $name, 'city' => ucfirst($city),
                    'geom' => Point::makeGeodetic($lat, $x), 'status' => 'active']);
                DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $era->id, 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $this->artisan('vff:build-network-graph')->assertSuccessful();
    }

    public function test_parameters_are_validated_with_field_messages(): void
    {
        $this->getJson('/api/v1/route?from=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to'])
            ->assertJsonMissingValidationErrors(['from']);

        $long = str_repeat('x', 201);
        $this->getJson("/api/v1/route?from={$long}&to=b")->assertUnprocessable()->assertJsonValidationErrors(['from']);
        $this->getJson('/api/v1/route?from=a&to=b&era=1799')->assertUnprocessable()->assertJsonValidationErrors(['era']);
        $this->getJson('/api/v1/route?from=a&to=b&year=2101')->assertUnprocessable()->assertJsonValidationErrors(['year']);
        $this->getJson('/api/v1/route?from=a&to=b&era=abc')->assertUnprocessable()->assertJsonValidationErrors(['era']);
        $this->getJson('/api/v1/route?from=a&to=b&heritage=maybe')->assertUnprocessable()->assertJsonValidationErrors(['heritage']);
        $this->getJson('/api/v1/route?from[]=a&to=b')->assertUnprocessable()->assertJsonValidationErrors(['from']);
    }

    public function test_a_route_within_one_component(): void
    {
        $this->getJson('/api/v1/route?from=centrale-lyon&to=terminus-lyon')
            ->assertOk()
            ->assertJsonPath('era_year', 2030)
            ->assertJsonPath('requested_year', null)
            ->assertJsonPath('hops', 1)
            ->assertJsonPath('stations.*.slug', ['centrale-lyon', 'terminus-lyon'])
            ->assertJsonPath('lines.0.line_name', 'Ligne de lyon')
            ->assertHeader('Cache-Control', 'max-age=300, public');
    }

    public function test_same_name_far_apart_is_ambiguous_with_candidates(): void
    {
        $response = $this->getJson('/api/v1/route?from='.rawurlencode('Gare Centrale').'&to=terminus-lyon')
            ->assertStatus(422)
            ->assertJsonPath('error', 'ambiguous_station')
            ->assertJsonPath('side', 'from')
            ->assertJsonPath('query', 'Gare Centrale');

        $this->assertEqualsCanonicalizing(
            [['slug' => 'centrale-lyon', 'name' => 'Gare Centrale'], ['slug' => 'centrale-lille', 'name' => 'Gare Centrale']],
            $response->json('candidates'),
        );
        $this->assertStringContainsString('Gare Centrale', $response->json('message'));
    }

    public function test_disconnected_networks_have_no_path(): void
    {
        $this->getJson('/api/v1/route?from=centrale-lyon&to=terminus-lille')
            ->assertNotFound()
            ->assertJsonPath('error', 'no_path');
    }

    public function test_unknown_station_reports_its_side(): void
    {
        $this->getJson('/api/v1/route?from=centrale-lyon&to=nulle-part')
            ->assertNotFound()
            ->assertJsonPath('error', 'station_not_found')
            ->assertJsonPath('side', 'to')
            ->assertJsonPath('query', 'nulle-part');
    }
}
