<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Fiches complètes et données de référence de l'API publique : GET /api/v1/lines/{line},
 * /lines/{line}/segments, /stations/{station}, /eras, /eras/{year}, /sources, /sources/{provider}.
 */
class SheetsApiTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    public function test_line_sheet_returns_what_the_page_shows(): void
    {
        $response = $this->getJson("/api/v1/lines/{$this->line->slug}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('data.slug', $this->line->slug)
            ->assertJsonPath('data.today.era_year', 2030)
            ->assertJsonPath('data.today.state', 'open')
            ->assertJsonPath('data.key_figures.event_count', 1)
            ->assertJsonPath('data.materials.0.slug', 'z-6400-test')
            ->assertJsonStructure(['data' => [
                'slug', 'code', 'name', 'names', 'wikidata', 'network', 'is_lgv', 'is_threatened', 'description',
                'description_source', 'advocacy', 'historical_operators', 'regions', 'departements',
                'today' => ['era_year', 'state', 'traffic'],
                'key_figures' => ['length_km', 'length_definition', 'active_length_km', 'max_speed_kmh', 'gauge_mm',
                    'station_count', 'station_open_count', 'event_count', 'segment_count'],
                'electrification', 'signalling' => ['block_systems_km', 'train_protection_km', 'kvb_equipped', 'ranges'],
                'facts', 'era_summary', 'portions', 'phases', 'best_phase', 'phases_unexplained', 'decades',
                'chronicle' => ['events', 'groups', 'notes'], 'stations', 'sections', 'heritage', 'materials', 'sources',
                'links' => ['web', 'events', 'history', 'segments', 'stops', 'signalling', 'media'],
            ]]);

        $this->assertSame([1960, 2030], $response->json('data.era_summary.*.year'));
        $this->assertArrayNotHasKey('geometry', $response->json('data.portions.0') ?? []);
        $this->assertStringEndsWith('/api/v1/events?line='.$this->line->slug, $response->json('data.links.events'));
    }

    public function test_line_sheet_adds_portion_geometry_on_request_and_validates_the_flag(): void
    {
        $portion = $this->getJson("/api/v1/lines/{$this->line->slug}?geometry=true")->assertOk()->json('data.portions.0');
        $this->assertSame('LineString', $portion['geometry']['type'] ?? null);

        $this->getJson("/api/v1/lines/{$this->line->slug}?geometry=parfois")->assertUnprocessable()->assertJsonValidationErrors(['geometry']);
        $this->getJson('/api/v1/lines/ligne-inconnue')->assertNotFound();
    }

    public function test_segments_of_a_line_at_an_era_with_provenance(): void
    {
        $this->getJson("/api/v1/lines/{$this->line->slug}/segments")
            ->assertOk()
            ->assertJsonPath('era_year', 2030)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'in_service')
            ->assertJsonPath('data.0.traffic_type', 'passenger')
            ->assertJsonPath('data.0.voltage_v', 1500)
            ->assertJsonMissingPath('data.0.geometry')
            ->assertJsonStructure(['line' => ['slug', 'code', 'name'], 'data' => [['id', 'pk_start_m', 'pk_end_m', 'length_m',
                'electrified', 'max_speed_kmh', 'gauge_mm', 'source', 'provenance' => ['provider', 'name', 'license', 'license_status']]]]);

        $this->getJson("/api/v1/lines/{$this->line->slug}/segments?era=1960&geometry=1")
            ->assertOk()
            ->assertJsonPath('era_year', 1960)
            ->assertJsonPath('data.0.geometry.type', 'LineString');

        $this->getJson("/api/v1/lines/{$this->line->slug}/segments?era=1935")->assertNotFound();
        $this->getJson("/api/v1/lines/{$this->line->slug}/segments?era=vers1930")->assertUnprocessable()->assertJsonValidationErrors(['era']);
    }

    public function test_station_sheet_returns_what_the_page_shows_with_provenance(): void
    {
        DB::table('station_sources')->insert([
            'station_id' => $this->origin->id, 'source' => 'osm', 'source_ref' => 'n123', 'name' => 'Paris Saint-Lazare',
            'kind' => 'station', 'lifecycle' => 'active', 'seen_at' => now(), 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.3253, 48.8763), 4326)'),
        ]);

        $this->getJson('/api/v1/stations/paris-saint-lazare')
            ->assertOk()
            ->assertJsonPath('data.slug', 'paris-saint-lazare')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.opened_at', '1837-08-26')
            ->assertJsonPath('data.sources.0.source', 'osm')
            ->assertJsonPath('data.sources.0.url', 'https://www.openstreetmap.org/node/123')
            ->assertJsonPath('data.sources.0.provenance.provider', 'osm')
            ->assertJsonPath('data.sources.0.provenance.license_status', 'verified')
            ->assertJsonCount(0, 'data.neighbors')
            ->assertJsonStructure(['data' => ['slug', 'name', 'alt_names', 'type', 'status', 'city', 'department_code',
                'opened_at', 'opened_precision', 'closed_at', 'closed_precision', 'closed_after', 'uic_code', 'wikidata_id',
                'lat', 'lng', 'position' => ['source', 'spread_m', 'flagged'], 'attribute_sources', 'eras', 'events', 'lines',
                'sources', 'neighbors', 'links' => ['web', 'events', 'media']]]);

        $this->getJson('/api/v1/stations/gare-inconnue')->assertNotFound();
    }

    public function test_eras_list_and_one_era_with_network_stats(): void
    {
        $this->getJson('/api/v1/eras')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.year', 1960)
            ->assertJsonPath('data.0.snapshot_date', '1960-12-31')
            ->assertJsonPath('data.0.has_data', true)
            ->assertJsonPath('data.1.is_current', true)
            ->assertJsonPath('data.1.snapshot_date', null)
            ->assertJsonStructure(['data' => [['year', 'label', 'is_current', 'snapshot_date', 'has_data', 'description',
                'links' => ['self', 'map', 'lines', 'stations', 'gtfs']]]]);

        $this->getJson('/api/v1/eras/2030')
            ->assertOk()
            ->assertJsonPath('data.year', 2030)
            ->assertJsonPath('data.stats.active_lines', 1)
            ->assertJsonPath('data.stats.open_km_by_traffic.passenger', $this->getJson('/api/v1/eras/2030')->json('data.stats.open_km'))
            ->assertJsonStructure(['data' => ['stats' => ['open_km', 'closed_km', 'gone_km', 'electrified_km', 'active_lines',
                'open_km_by_traffic', 'stations_by_status'], 'links']]);

        $this->getJson('/api/v1/eras/1935')->assertNotFound()->assertJsonPath('message', 'Unknown era 1935: eras are the decades 1830 to 2030.');
    }

    public function test_source_registry_publishes_licences_without_internal_notes(): void
    {
        $response = $this->getJson('/api/v1/sources')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertJsonPath('meta.database_licence.name', 'Open Database License (ODbL) 1.0');

        $osm = self::provider($response->json('data'), 'osm');
        $this->assertSame('verified', $osm['license_status']);
        $this->assertTrue($osm['open_licence']);
        $this->assertContains('osm', $osm['source_values']['line_segments']);
        $this->assertNotEmpty($osm['mention']);

        $archeo = self::provider($response->json('data'), 'archeoferroviaire');
        $this->assertSame('uncertain', $archeo['license_status']);
        $this->assertFalse($archeo['open_licence']);

        foreach ($response->json('data') as $entry) {
            $this->assertSame(['provider', 'name', 'licence', 'license_status', 'open_licence', 'mention', 'citation', 'proof',
                'checked_on', 'domains', 'source_values'], array_keys($entry), $entry['provider']);
        }

        $this->getJson('/api/v1/sources/sncf')->assertOk()->assertJsonPath('data.provider', 'sncf');
        $this->getJson('/api/v1/sources/inconnu')->assertNotFound()->assertJsonPath('message', 'Unknown source provider.');
    }

    /**
     * Fiche d'un fournisseur dans la liste du registre.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private static function provider(array $entries, string $key): array
    {
        $found = array_values(array_filter($entries, fn (array $e): bool => $e['provider'] === $key));
        self::assertNotEmpty($found, "fournisseur {$key} absent");

        return $found[0];
    }
}
