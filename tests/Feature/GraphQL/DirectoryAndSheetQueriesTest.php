<?php

namespace Tests\Feature\GraphQL;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * GraphQL : annuaires (`lineDirectory`, `stationDirectory`), fiches complètes (`Line.sheet`,
 * `Station.sheet`), tronçons d'une ère (`Line.segments(era)`), statistiques d'ère (`Era.stats`) et
 * registre des sources (`sources`, `source`), au même contrat que l'API REST.
 */
class DirectoryAndSheetQueriesTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->origin->update(['department_code' => '75']);
        $this->destination->update(['department_code' => '78']);
        Line::factory()->create(['slug' => 'rfn-000001', 'code' => '000001', 'name' => 'Ancienne ligne sans tracé', 'network' => 'secondaire']);
        DB::table('lines')->where('id', $this->line->id)->update(['network' => 'principal']);
    }

    public function test_line_directory_matches_the_rest_directory(): void
    {
        $query = '
            query ($filter: LineDirectoryFilter, $after: String) {
                lineDirectory(filter: $filter, first: 1, after: $after) {
                    data { slug state traffic heritage regions }
                    next_cursor
                    era_year
                    facets { total open unknown networks { code count } traffics { code count } heritage }
                }
            }';

        $first = $this->graphQL($query, ['filter' => ['sort' => 'code']])->assertJsonMissingPath('errors');
        $this->assertSame('rfn-000001', $first->json('data.lineDirectory.data.0.slug'));
        $this->assertSame('unknown', $first->json('data.lineDirectory.data.0.state'));
        $this->assertSame(2030, $first->json('data.lineDirectory.era_year'));
        $this->assertSame(2, $first->json('data.lineDirectory.facets.total'));
        $this->assertContains(['code' => 'principal', 'count' => 1], $first->json('data.lineDirectory.facets.networks'));

        // Le curseur GraphQL est celui de l'API REST : il mène à la même page suivante.
        $cursor = $first->json('data.lineDirectory.next_cursor');
        $rest = $this->getJson('/api/v1/lines?per_page=1&sort=code')->json('meta.next_cursor');
        $this->assertSame($rest, $cursor);
        $second = $this->graphQL($query, ['filter' => ['sort' => 'code'], 'after' => $cursor])->assertJsonMissingPath('errors');
        $this->assertSame($this->line->slug, $second->json('data.lineDirectory.data.0.slug'));
        $this->assertSame('passenger', $second->json('data.lineDirectory.data.0.traffic'));
        $this->assertNull($second->json('data.lineDirectory.next_cursor'));

        $this->assertSame([$this->line->slug], array_column(
            $this->graphQL('{ lineDirectory(filter: {status: "open", order: DESC}) { data { slug } } }')->json('data.lineDirectory.data'), 'slug'));
    }

    public function test_directories_reject_invalid_filters_with_validation_errors(): void
    {
        $this->graphQL('{ lineDirectory(filter: {network: "autre", era: 1935}) { data { slug } } }')
            ->assertGraphQLValidationKeys(['network', 'era']);
        $this->graphQL('{ stationDirectory(filter: {status: "ouverte"}) { data { slug } } }')
            ->assertGraphQLValidationKeys(['status']);
        $this->graphQL('{ lineDirectory(first: 500, after: "nimporte") { data { slug } } }')
            ->assertGraphQLValidationKeys(['first', 'after']);
    }

    public function test_station_directory_and_station_sheet(): void
    {
        $response = $this->graphQL('{
            stationDirectory(filter: {department: "78"}) { data { slug status city lat lng lines { slug } lines_more } facets { code count } }
            station(slug: "paris-saint-lazare") { sheet { status position { flagged } eras { year status } lines { slug } sources { source } neighbors { slug } } }
        }')->assertJsonMissingPath('errors');

        $this->assertSame('versailles-rive-droite', $response->json('data.stationDirectory.data.0.slug'));
        $this->assertSame([['code' => 'active', 'count' => 1]], $response->json('data.stationDirectory.facets'));
        $this->assertSame('active', $response->json('data.station.sheet.status'));
        $this->assertSame([1960, 2030], array_column($response->json('data.station.sheet.eras'), 'year'));
    }

    public function test_line_description_source_and_station_raw_provenance_are_typed_fields(): void
    {
        $this->line->update(['description' => 'La ligne relie Paris à Brest.']);
        DB::table('line_sources')->insert(['line_id' => $this->line->id, 'source_type' => 'wikipedia-intro',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_Paris_%C3%A0_Brest&oldid=42', 'fetched_at' => '2026-10-19 23:00:00',
            'fields_provided' => json_encode(['title' => 'Ligne de Paris à Brest'])]);
        DB::table('station_sources')->insert([
            'station_id' => $this->origin->id, 'source' => 'osm', 'source_ref' => 'n123', 'name' => 'Paris Saint-Lazare', 'lifecycle' => 'active',
            'match_method' => 'near', 'seen_at' => '2026-10-15 17:21:48', 'properties' => json_encode(['railway' => 'station']),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.3253, 48.8763), 4326)'),
        ]);

        $response = $this->graphQL('query ($slug: String!) {
            line(slug: $slug) { sheet { description description_source { type title revision_id permalink history_url author attribution license license_url fetched_at urls } } }
            station(slug: "paris-saint-lazare") { sheet { sources { source ref fetched_at raw match_method pk_m precision_m provenance { license_status } } } }
        }', ['slug' => $this->line->slug])->assertJsonMissingPath('errors');

        $source = $response->json('data.line.sheet.description_source');
        $this->assertSame('wikipedia', $source['type']);
        $this->assertSame(42, $source['revision_id']);
        $this->assertSame('Contributeurs de Wikipédia', $source['author']);
        $this->assertSame('CC BY-SA 4.0', $source['license']);
        $this->assertStringContainsString('oldid=42', $source['permalink']);
        $this->assertStringContainsString('action=history', $source['history_url']);
        $this->assertSame('2026-10-15', $response->json('data.station.sheet.sources.0.fetched_at'));
        $this->assertSame('osm', $response->json('data.station.sheet.sources.0.source'));
        $this->assertSame(['railway' => 'station'], $response->json('data.station.sheet.sources.0.raw'));
        $this->assertSame('verified', $response->json('data.station.sheet.sources.0.provenance.license_status'));
    }

    public function test_line_sheet_segments_of_an_era_and_era_stats(): void
    {
        $response = $this->graphQL('query ($slug: String!) {
            line(slug: $slug) {
                sheet { slug today { era_year state } key_figures { event_count station_count } signalling { kvb_equipped ranges { signalling } }
                        era_summary { year state } materials { slug } phases portions links }
                segments(era: 1960) { id length_m status provenance { provider license_status } }
            }
            era(year: 2030) { snapshot_date has_data stats { active_lines open_km_by_traffic { code km } stations_by_status { code count } } }
            past: era(year: 1960) { snapshot_date }
        }', ['slug' => $this->line->slug])->assertJsonMissingPath('errors');

        $this->assertSame('open', $response->json('data.line.sheet.today.state'));
        $this->assertSame(1, $response->json('data.line.sheet.key_figures.event_count'));
        $this->assertSame([1960, 2030], array_column($response->json('data.line.sheet.era_summary'), 'year'));
        $this->assertSame('z-6400-test', $response->json('data.line.sheet.materials.0.slug'));
        $this->assertIsArray($response->json('data.line.sheet.phases'));
        $this->assertArrayNotHasKey('geometry', $response->json('data.line.sheet.portions.0') ?? []);
        $this->assertCount(1, $response->json('data.line.segments'));
        $this->assertSame('in_service', $response->json('data.line.segments.0.status'));
        $this->assertNull($response->json('data.era.snapshot_date'));
        $this->assertSame('1960-12-31', $response->json('data.past.snapshot_date'));
        $this->assertTrue($response->json('data.era.has_data'));
        $this->assertSame(1, $response->json('data.era.stats.active_lines'));
        $this->assertSame('passenger', $response->json('data.era.stats.open_km_by_traffic.0.code'));

        $geometry = $this->graphQL('query ($slug: String!) { line(slug: $slug) { sheet(geometry: true) { portions } } }', ['slug' => $this->line->slug]);
        $this->assertSame('LineString', $geometry->json('data.line.sheet.portions.0.geometry.type'));

        $this->graphQL('query ($slug: String!) { line(slug: $slug) { segments(era: 1935) { id } } }', ['slug' => $this->line->slug])
            ->assertGraphQLErrorMessage('Aucune ère 1935.');
    }

    public function test_source_registry(): void
    {
        $response = $this->graphQL('{
            sources { provider license_status open_licence source_values { table values } }
            source(provider: "osm") { name licence mention proof checked_on domains }
            missing: source(provider: "inconnu") { name }
        }')->assertJsonMissingPath('errors');

        $osm = self::provider($response->json('data.sources'), 'osm');
        $this->assertSame('verified', $osm['license_status']);
        $this->assertContains(['table' => 'line_segments', 'values' => ['osm', 'declared']], $osm['source_values']);
        $this->assertSame('OpenStreetMap', $response->json('data.source.name'));
        $this->assertNull($response->json('data.missing'));
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
