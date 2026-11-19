<?php

namespace Tests\Feature\Api;

use App\Models\LineSegment;
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
                'description_source', 'historical_operators', 'regions', 'departements',
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

    public function test_line_description_comes_with_everything_the_cc_by_sa_licence_asks_for(): void
    {
        $this->line->update(['description' => 'La ligne relie Paris à Brest.']);
        DB::table('line_sources')->insert(['line_id' => $this->line->id, 'source_type' => 'wikipedia-intro',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris_%C3%A0_Brest&oldid=238010272', 'fetched_at' => '2026-10-19 23:00:00',
            'fields_provided' => json_encode(['fields' => ['description'], 'title' => 'Ligne de Paris à Brest', 'revision_id' => 238010272])]);

        $this->getJson("/api/v1/lines/{$this->line->slug}")
            ->assertOk()
            ->assertJsonPath('data.description', 'La ligne relie Paris à Brest.')
            ->assertJsonPath('data.description_source.type', 'wikipedia')
            ->assertJsonPath('data.description_source.title', 'Ligne de Paris à Brest')
            ->assertJsonPath('data.description_source.article_url', 'https://fr.wikipedia.org/wiki/Ligne_de_Paris_%C3%A0_Brest')
            ->assertJsonPath('data.description_source.revision_id', 238010272)
            ->assertJsonPath('data.description_source.permalink', 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris_%C3%A0_Brest&oldid=238010272')
            ->assertJsonPath('data.description_source.history_url', 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris_%C3%A0_Brest&action=history')
            ->assertJsonPath('data.description_source.author', 'Contributeurs de Wikipédia')
            ->assertJsonPath('data.description_source.license', 'CC BY-SA 4.0')
            ->assertJsonPath('data.description_source.license_url', 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr')
            ->assertJsonPath('data.description_source.fetched_at', '2026-10-19')
            ->assertJsonPath('data.description_source.attribution', '« Ligne de Paris à Brest », Wikipédia en français, par les contributeurs de Wikipédia, CC BY-SA 4.0');
    }

    public function test_line_description_without_a_known_revision_has_no_permalink(): void
    {
        $this->line->update(['description' => 'Texte.']);
        DB::table('line_sources')->insert(['line_id' => $this->line->id, 'source_type' => 'wikipedia-intro',
            'source_url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Paris_%C3%A0_Brest', 'fetched_at' => '2026-10-19 23:00:00',
            'fields_provided' => json_encode(['title' => 'Ligne de Paris à Brest'])]);

        $this->getJson("/api/v1/lines/{$this->line->slug}")
            ->assertOk()
            ->assertJsonPath('data.description_source.revision_id', null)
            ->assertJsonPath('data.description_source.permalink', null)
            ->assertJsonPath('data.description_source.article_url', 'https://fr.wikipedia.org/wiki/Ligne_de_Paris_%C3%A0_Brest')
            ->assertJsonPath('data.description_source.license', 'CC BY-SA 4.0');
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

        // Un tronçon sans source déclarée n'a pas de licence connue : sa géométrie n'est pas donnée.
        $this->getJson("/api/v1/lines/{$this->line->slug}/segments?era=1960&geometry=1")
            ->assertOk()
            ->assertJsonPath('data.0.geometry', null)
            ->assertJsonPath('data.0.geometry_withheld', true);

        LineSegment::query()->update(['source' => 'osm']);
        $this->getJson("/api/v1/lines/{$this->line->slug}/segments?era=1960&geometry=1")
            ->assertOk()
            ->assertJsonPath('era_year', 1960)
            ->assertJsonPath('data.0.geometry.type', 'LineString')
            ->assertJsonPath('data.0.geometry_withheld', false);

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

    public function test_station_sources_publish_the_raw_provenance_read_only(): void
    {
        DB::table('station_sources')->insert([
            'station_id' => $this->origin->id, 'source' => 'sncf', 'source_ref' => '87271007', 'name' => 'Paris Saint-Lazare',
            'kind' => 'gare', 'lifecycle' => 'active', 'match_method' => 'uic', 'seen_at' => '2026-10-15 17:08:34',
            'properties' => json_encode(['commune' => 'PARIS', 'lines' => [['code_ligne' => '287000', 'pk_m' => 0]], 'pk_m' => '35785', 'precision_m' => '428']),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.3253, 48.8763), 4326)'),
        ]);
        DB::table('station_sources')->insert([
            'station_id' => $this->origin->id, 'source' => 'ohm', 'source_ref' => 'n9', 'name' => 'Saint-Lazare', 'lifecycle' => 'unknown',
            'seen_at' => '2026-10-16 08:00:00', 'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.3253, 48.8763), 4326)'),
        ]);
        $this->origin->update(['provenance' => ['name' => 'sncf', 'geom' => 'sncf', 'geom_recalage' => ['mode' => 'concordance', 'moved_m' => 12]]]);

        $response = $this->getJson('/api/v1/stations/paris-saint-lazare')->assertOk();
        /** @var list<array<string, mixed>> $sources */
        $sources = $response->json('data.sources');
        $sncf = collect($sources)->firstWhere('source', 'sncf');
        $this->assertSame('87271007', $sncf['ref']);
        $this->assertSame('2026-10-15', $sncf['fetched_at']);
        $this->assertSame('uic', $sncf['match_method']);
        $this->assertEquals(['code_ligne' => '287000', 'pk_m' => 0], $sncf['raw']['lines'][0]);
        $this->assertSame(35785, $sncf['pk_m'], 'le point kilométrique est un entier, comme dans les autres réponses');
        $this->assertSame(428, $sncf['precision_m']);
        $this->assertSame('verified', $sncf['provenance']['license_status']);
        $ohm = collect($sources)->firstWhere('source', 'ohm');
        $this->assertSame([], $ohm['raw'], 'sans attribut brut, un objet vide et non une liste');
        $this->assertStringContainsString('"raw":{}', $response->getContent());
        $response->assertJsonPath('data.attribute_sources.geom', 'sncf')
            ->assertJsonPath('data.attribute_sources.geom_recalage.moved_m', 12);
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
