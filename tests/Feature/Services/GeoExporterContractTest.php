<?php

namespace Tests\Feature\Services;

use App\Models\LineSegment;
use App\Services\GeoExporter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * GeoExporter : contrat COMPLET des features exportées (toutes les clés,
 * leurs types et valeurs) pour le GeoJSON streamé des tracés et les gares, en
 * complément de GeoExporterTest (règles d'affichage).
 */
class GeoExporterContractTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private GeoExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->exporter = app(GeoExporter::class);
    }

    public function test_streamed_geojson_of_an_era_without_segments_is_empty_and_an_unknown_era_fails(): void
    {
        DB::table('line_segments')->where('era_id', $this->pastEra->id)->delete();

        $this->assertSame([], iterator_to_array($this->exporter->streamLinesGeoJson(1960), false));

        $this->expectException(ModelNotFoundException::class);
        iterator_to_array($this->exporter->streamLinesGeoJson(1955), false);
    }

    public function test_streamed_feature_of_a_withheld_source_keeps_its_properties_without_geometry(): void
    {
        LineSegment::where('era_id', $this->currentEra->id)->update(['source' => 'archeoferroviaire']);

        [$feature] = iterator_to_array($this->exporter->streamLinesGeoJson(2030), false);

        $this->assertNull($feature['geometry']);
        $this->assertTrue($feature['properties']['geometry_withheld']);
        $this->assertSame('licence_not_open', $feature['properties']['geometry_withheld_reason']);
        $this->assertSame('in_service', $feature['properties']['status']);

        config(['api.withhold_unlicensed_geometry' => false]);
        [$restored] = iterator_to_array($this->exporter->streamLinesGeoJson(2030), false);
        $this->assertSame('LineString', $restored['geometry']['type'], 'règle désactivée : géométrie de nouveau donnée');
        $this->assertFalse($restored['properties']['geometry_withheld']);
    }

    public function test_streamed_feature_has_every_map_property(): void
    {
        $this->line->update(['network' => 'principal']);
        $segment = LineSegment::where('era_id', $this->currentEra->id)->firstOrFail();
        $segment->update(['operator' => 'SNCF Voyageurs', 'source' => 'osm']);

        $features = iterator_to_array($this->exporter->streamLinesGeoJson(2030), false);

        $this->assertCount(1, $features);
        $this->assertSame($segment->id, $features[0]['id']);
        $this->assertSame([
            'line_id' => $this->line->id,
            'line_slug' => 'paris-saint-lazare-versailles-rd',
            'line_name' => 'Paris-Saint-Lazare – Versailles-Rive-Droite',
            'line_code' => '999 000',
            'network' => 'principal',
            'status' => 'in_service',
            'traffic_type' => 'passenger',
            'is_lgv' => false,
            'is_heritage' => false,
            'heritage_kind' => null,
            'max_speed_kmh' => 120,
            'electrified' => true,
            'voltage_v' => 1500,
            'gauge_mm' => $segment->gauge_mm,
            'operator' => 'SNCF Voyageurs',
            'source' => 'osm',
            'source_trace_id' => null,
            'geometry_withheld' => false,
            'geometry_withheld_reason' => null,
        ], $features[0]['properties']);
        $this->assertSame(['type' => 'LineString', 'coordinates' => [[2.3253, 48.8763], [2.24, 48.853], [2.1347, 48.8091]]], $features[0]['geometry']);
    }

    public function test_station_feature_has_every_map_property(): void
    {
        $this->origin->update(['uic_code' => '87384008', 'department_code' => '75']);

        $features = collect($this->exporter->stationsAsGeoJson(2030)['features'])->keyBy('properties.slug');

        $this->assertSame(['paris-saint-lazare', 'versailles-rive-droite'], $features->keys()->sort()->values()->all());
        $origin = $features['paris-saint-lazare'];
        $this->assertSame('Feature', $origin['type']);
        $this->assertSame($this->origin->id, $origin['id']);
        $this->assertSame(['type' => 'Point', 'coordinates' => [2.3253, 48.8763]], $origin['geometry']);
        $this->assertSame([
            'slug' => 'paris-saint-lazare',
            'name' => 'Paris-Saint-Lazare',
            'type' => 'gare',
            'status' => 'active',
            'sources_status' => 'active',
            'service' => 'passenger',
            'city' => 'Paris',
            'department_code' => '75',
            'uic' => '87384008',
            'opened_year' => 1837,
            'closed_year' => null,
            'note' => null,
        ], array_intersect_key($origin['properties'], array_flip(['slug', 'name', 'type', 'status', 'sources_status', 'service', 'city',
            'department_code', 'uic', 'opened_year', 'closed_year', 'note'])));
    }
}
