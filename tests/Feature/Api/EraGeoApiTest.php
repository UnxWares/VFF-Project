<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * API cartographique par ère : GeoJSON (lignes, gares) et tuiles MVT.
 */
class EraGeoApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    public function test_lines_geojson_is_a_feature_collection(): void
    {
        $response = $this->get('/api/v1/eras/2030/lines.geojson');

        $response->assertOk()->assertHeader('Content-Type', 'application/json');

        // Réponse streamée (curseur DB) : on décode le flux complet.
        $json = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('FeatureCollection', $json['type']);
        $this->assertCount(1, $json['features']);

        $feature = $json['features'][0];
        $this->assertSame('Feature', $feature['type']);
        $this->assertSame('LineString', $feature['geometry']['type']);
        $this->assertCount(3, $feature['geometry']['coordinates']);
        // Ordre GeoJSON : [lng, lat]
        $this->assertEqualsWithDelta([2.3253, 48.8763], $feature['geometry']['coordinates'][0], 1e-6);

        $this->assertSame($this->line->slug, $feature['properties']['line_slug']);
        $this->assertSame('in_service', $feature['properties']['status']);
        $this->assertSame('passenger', $feature['properties']['traffic_type']);
        $this->assertTrue($feature['properties']['electrified']);
        $this->assertFalse($feature['properties']['is_lgv']);
        $this->assertFalse($feature['properties']['is_heritage']);
        $this->assertSame(1500, $feature['properties']['voltage_v']);
    }

    /**
     * Régression : lines.is_lgv vaut false par défaut tant que vff:classify-lines
     * n'a pas tourné ; la catégorie SNCF (catlig) ou le nom suffisent pour que
     * la carte dessine la LGV en violet (cf. App\Support\Lgv).
     */
    public function test_lgv_is_detected_without_prior_classification(): void
    {
        $isLgv = function (): bool {
            $json = json_decode($this->get('/api/v1/eras/2030/lines.geojson')->streamedContent(), true);

            return $json['features'][0]['properties']['is_lgv'];
        };
        $this->assertFalse($isLgv());

        DB::table('sncf_line_catlig')->insert(['code_ligne' => '999 000', 'catlig' => 'Ligne à grande vitesse']);
        $this->assertFalse((bool) $this->line->fresh()->is_lgv, 'drapeau non calculé');
        $this->assertTrue($isLgv());

        DB::table('sncf_line_catlig')->delete();
        $this->line->update(['name' => 'Ligne de Combs-la-Ville à St-Louis (LGV)']);
        $this->assertTrue($isLgv());
    }

    public function test_lines_geojson_for_era_without_segments_is_empty(): void
    {
        Era::factory()->create(['year' => 1900]);

        $response = $this->get('/api/v1/eras/1900/lines.geojson')->assertOk();

        $this->assertSame(
            ['type' => 'FeatureCollection', 'features' => []],
            json_decode($response->streamedContent(), true),
        );
    }

    public function test_lines_geojson_unknown_era_is_404_not_a_truncated_stream(): void
    {
        $this->get('/api/v1/eras/1850/lines.geojson')->assertNotFound();
    }

    public function test_stations_geojson_for_current_era(): void
    {
        $this->getJson('/api/v1/eras/2030/stations.geojson')
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(2, 'features')
            ->assertJsonStructure([
                'type',
                'features' => [[
                    'type', 'id',
                    'geometry' => ['type', 'coordinates'],
                    'properties' => ['slug', 'name', 'type', 'status', 'city', 'department_code'],
                ]],
            ])
            ->assertJsonPath('features.0.geometry.type', 'Point');
    }

    public function test_stations_geojson_for_past_era_only_lists_curated_stations(): void
    {
        // Ère passée : seules les gares avec une entrée station_eras explicite.
        $this->getJson('/api/v1/eras/1960/stations.geojson')
            ->assertOk()
            ->assertJsonCount(0, 'features');

        \DB::table('station_eras')->insert([
            'station_id' => $this->origin->id, 'era_id' => $this->pastEra->id, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/eras/1960/stations.geojson')
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.slug', 'paris-saint-lazare');
    }

    public function test_stations_geojson_unknown_era_is_404(): void
    {
        $this->getJson('/api/v1/eras/1850/stations.geojson')->assertNotFound();
    }

    public function test_mvt_tile_covering_the_line_is_not_empty(): void
    {
        // Tuile z8 qui couvre Paris / Versailles.
        $response = $this->get('/api/v1/eras/2030/tiles/8/129/88.mvt');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/x-protobuf')
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $body = $response->getContent();
        $this->assertNotEmpty($body);
        // Le nom de couche protobuf est encodé en clair dans la tuile.
        $this->assertStringContainsString('vff-lines', $body);
        $this->assertStringContainsString($this->line->slug, $body);
    }

    public function test_mvt_tile_outside_the_network_is_empty(): void
    {
        $this->get('/api/v1/eras/2030/tiles/8/0/0.mvt')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-protobuf')
            ->assertContent('');
    }

    public function test_mvt_unknown_era_is_404(): void
    {
        $this->get('/api/v1/eras/1850/tiles/8/129/88.mvt')->assertNotFound();
    }

    public function test_non_numeric_era_does_not_match_any_route(): void
    {
        $this->get('/api/v1/eras/abc/lines.geojson')->assertNotFound();
    }
}
