<?php

namespace Tests\Feature\Api;

use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Contrat des propriétés GeoJSON par ère (clés exactes, types, service des
 * gares dérivé du statut), en complément d'EraGeoApiTest et GeoExporterTest.
 */
class EraGeoContractTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    public function test_line_feature_properties_contract(): void
    {
        $json = json_decode($this->get('/api/v1/eras/2030/lines.geojson')->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $feature = $json['features'][0];

        $this->assertSame(['type', 'id', 'geometry', 'properties'], array_keys($feature));
        $this->assertIsInt($feature['id']);
        $this->assertSame([
            'line_id', 'line_slug', 'line_name', 'line_code', 'network', 'status', 'traffic_type', 'is_lgv',
            'is_heritage', 'heritage_kind', 'max_speed_kmh', 'electrified', 'voltage_v', 'gauge_mm', 'operator',
            'source', 'source_trace_id', 'geometry_withheld', 'geometry_withheld_reason',
        ], array_keys($feature['properties']));
        $this->assertSame($this->line->id, $feature['properties']['line_id']);
        $this->assertSame('999 000', $feature['properties']['line_code']);
        $this->assertSame(120, $feature['properties']['max_speed_kmh']);

        // Précision 6 décimales (≈ 0,1 m) : pas plus.
        foreach ($feature['geometry']['coordinates'] as [$lng, $lat]) {
            $this->assertLessThanOrEqual(6, strlen(substr(strrchr((string) $lng, '.') ?: '.', 1)));
            $this->assertLessThanOrEqual(6, strlen(substr(strrchr((string) $lat, '.') ?: '.', 1)));
        }
    }

    public function test_station_service_follows_the_era_status(): void
    {
        $statuses = ['active' => 'passenger', 'tourist' => 'tourist', 'no_commercial' => 'freight', 'declassified' => 'closed'];
        foreach (array_keys($statuses) as $i => $status) {
            $station = Station::factory()->create([
                'slug' => "gare-{$status}", 'uic_code' => '8700000'.$i, 'opened_at' => '1880-05-01', 'closed_at' => $status === 'declassified' ? '1938-01-01' : null,
                // Sur le tracé : une gare fermée loin de toute voie en place n'est pas montrée.
                'geom' => Point::makeGeodetic(48.8530, 2.2400 + $i / 10_000),
            ]);
            DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $this->currentEra->id, 'status' => $status,
                'note' => $status === 'tourist' ? 'Train touristique' : null, 'created_at' => now(), 'updated_at' => now()]);
        }

        /** @var list<array<string, mixed>> $rawFeatures */
        $rawFeatures = $this->getJson('/api/v1/eras/2030/stations.geojson')->assertOk()->json('features');
        $features = collect($rawFeatures)
            ->keyBy('properties.slug');

        // Ère calculée : seules les gares de station_eras (pas Saint-Lazare ni Versailles).
        $this->assertCount(4, $features);
        foreach ($statuses as $status => $service) {
            $p = $features["gare-{$status}"]['properties'];
            $this->assertSame($status, $p['status']);
            $this->assertSame($status, $p['sources_status']);
            $this->assertSame($service, $p['service'], "service de la gare {$status}");
            $this->assertSame(1880, $p['opened_year']);
        }
        $this->assertSame(1938, $features['gare-declassified']['properties']['closed_year']);
        $this->assertNull($features['gare-active']['properties']['closed_year']);
        $this->assertSame('Train touristique', $features['gare-tourist']['properties']['note']);
        $this->assertSame('87000000', $features['gare-active']['properties']['uic']);
        $this->assertSame([
            'slug', 'name', 'type', 'status', 'sources_status', 'service', 'city', 'department_code', 'uic',
            'opened_year', 'closed_year', 'closed_at', 'closed_precision', 'closed_after', 'note',
        ], array_keys($features['gare-active']['properties']));
        // Date de fermeture retenue (StationTimeline::resolveClosure), la même que la fiche gare et la fiche ligne.
        $this->assertSame(1938, (int) substr((string) $features['gare-declassified']['properties']['closed_at'], 0, 4));
        $this->assertNull($features['gare-active']['properties']['closed_at']);
    }

    public function test_current_era_without_station_eras_hides_closed_stations(): void
    {
        Station::factory()->create(['slug' => 'gare-fermee', 'status' => 'declassified', 'geom' => Point::makeGeodetic(47.0, 1.0)]);

        /** @var list<array<string, mixed>> $rawFeatures */
        $rawFeatures = $this->getJson('/api/v1/eras/2030/stations.geojson')->json('features');
        $slugs = collect($rawFeatures)->pluck('properties.slug')->sort()->values()->all();

        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], $slugs);
    }

    public function test_world_tile_at_zoom_zero_contains_the_network(): void
    {
        $tile = $this->get('/api/v1/eras/2030/tiles/0/0/0.mvt')->assertOk()->getContent();

        $this->assertStringContainsString('vff-lines', $tile);
        $this->assertStringContainsString($this->line->slug, $tile);
        $this->assertStringNotContainsString($this->line->slug, $this->get('/api/v1/eras/1960/tiles/1/1/1.mvt')->getContent(),
            'tuile de l\'hémisphère sud-est : vide');
    }
}
