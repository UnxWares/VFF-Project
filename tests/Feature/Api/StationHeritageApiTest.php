<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Patrimoine d'une gare : même contenu en REST (`/stations/{station}/heritage` et `heritage` de la fiche), GraphQL
 * (`Station.heritage`), MCP (`station_heritage`) et page de la fiche ; filtre « gares protégées » de l'annuaire ;
 * nul quand VFF ne connaît aucun patrimoine.
 */
class StationHeritageApiTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->station = Station::factory()->create(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'wikidata_id' => 'Q658277']);
        // Gare sans patrimoine connu, pour l'annuaire filtré.
        Station::factory()->create(['slug' => 'sans-patrimoine', 'name' => 'Sans patrimoine']);
        $inventory = Station::factory()->create(['slug' => 'seulement-recensee', 'name' => 'Seulement recensée']);
        $this->row($this->station, ['element' => 'building', 'protection' => 'registered', 'protection_date' => '1975-01-15', 'ref' => 'PA00100349', 'ref_kind' => 'PA',
            'ref_url' => 'https://www.pop.culture.gouv.fr/notice/merimee/PA00100349', 'title' => 'Gare des Bénédictins', 'built_from' => 1925, 'source' => 'merimee',
            'architects' => json_encode([['name' => 'Roger Gonthier', 'role' => 'architecte']]), 'designations' => json_encode([['kind' => 'registered', 'date' => '1975-01-15']]),
            'image_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Gare.jpg/500px-Gare.jpg', 'image_file' => 'Gare.jpg', 'image_license' => 'CC BY-SA 4.0']);
        $this->row($this->station, ['element' => 'works', 'protection' => 'classified', 'ref' => 'PA00100350', 'ref_kind' => 'PA', 'title' => 'Remise', 'source' => 'merimee']);
        $this->row($inventory, ['element' => 'building', 'protection' => 'inventory', 'ref' => 'IA87000001', 'ref_kind' => 'IA', 'source' => 'wikidata', 'wikidata_id' => 'Q1']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function row(Station $station, array $attributes): void
    {
        DB::table('station_heritage')->insert($attributes + ['station_id' => $station->id, 'match_method' => 'wikidata', 'source' => 'merimee']);
    }

    public function test_rest_returns_the_building_first_with_its_protection_and_provenance(): void
    {
        $json = $this->getJson('/api/v1/stations/limoges-benedictins/heritage')->assertOk()->json();

        $this->assertSame(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'wikidata_id' => 'Q658277'], $json['station']);
        $h = $json['heritage'];
        $this->assertSame(['building', 'works'], array_column($h['items'], 'element'));
        $this->assertTrue($h['protected']);
        $building = $h['items'][0];
        $this->assertSame(['registered', '1975-01-15'], [$building['protection'], $building['protection_date']]);
        $this->assertSame([['name' => 'Roger Gonthier', 'role' => 'architecte']], $building['architects']);
        $this->assertSame(['from' => 1925, 'to' => null, 'period' => null], $building['built']);
        $this->assertSame('PA00100349', $building['reference']['id']);
        $this->assertSame('CC BY-SA 4.0', $building['image']['license']);
        $this->assertSame('pop', $building['provenance']['provider']);
        $this->assertSame('verified', $building['provenance']['license_status']);
        $this->assertStringContainsString('Licence Ouverte', $h['attribution']);
    }

    public function test_a_station_without_heritage_gets_null_not_an_error(): void
    {
        $this->assertNull($this->getJson('/api/v1/stations/sans-patrimoine/heritage')->assertOk()->json('heritage'));
        $this->getJson('/api/v1/stations/inconnue/heritage')->assertNotFound();
    }

    public function test_an_inventory_entry_alone_is_not_a_protection(): void
    {
        $h = $this->getJson('/api/v1/stations/seulement-recensee/heritage')->json('heritage');

        $this->assertFalse($h['protected']);
        $this->assertSame('wikidata', $h['items'][0]['provenance']['provider']);
    }

    public function test_the_station_sheet_carries_the_same_block_and_a_link(): void
    {
        $rest = $this->getJson('/api/v1/stations/limoges-benedictins/heritage')->json('heritage');
        $sheet = $this->getJson('/api/v1/stations/limoges-benedictins')->assertOk()->json();

        $this->assertSame($rest, $sheet['data']['heritage']);
        $this->assertStringEndsWith('/api/v1/stations/limoges-benedictins/heritage', $sheet['data']['links']['heritage']);
        $this->assertNull($this->getJson('/api/v1/stations/sans-patrimoine')->json('data.heritage'));
    }

    public function test_the_web_page_gets_the_heritage_prop(): void
    {
        $this->get('/stations/limoges-benedictins')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Stations/Show')->where('heritage.protected', true)->where('heritage.items.0.reference.id', 'PA00100349'));
        $this->get('/stations/sans-patrimoine')->assertOk()->assertInertia(fn (Assert $page) => $page->where('heritage', null));
    }

    public function test_the_protected_filter_keeps_classified_registered_and_labelled_stations_only(): void
    {
        $slugs = fn (array $json): array => array_column($json['data'], 'slug');

        $this->assertSame(['limoges-benedictins'], $slugs($this->getJson('/api/v1/stations?heritage=true')->assertOk()->json()));
        $this->assertCount(3, $this->getJson('/api/v1/stations?heritage=false')->json('data'));
        $this->get('/stations?heritage=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stations.total', 1)->where('filters.heritage', true)->where('stations.data.0.slug', 'limoges-benedictins'));
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $rest = $this->getJson('/api/v1/stations/limoges-benedictins/heritage')->json('heritage');
        $gql = $this->postJson('/graphql', ['query' => '{ stations(first: 10) { data { slug heritage {
            protected attribution items { element protection protection_date scope designations { kind date } title architects { name role }
            built { from to period } reference { id kind url } image { url license } source match_method provenance { provider license_status } } } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.stations.data');
        $bySlug = array_column($gql, 'heritage', 'slug');

        $this->assertTrue($bySlug['limoges-benedictins']['protected']);
        $this->assertSame($rest['items'][0]['protection_date'], $bySlug['limoges-benedictins']['items'][0]['protection_date']);
        $this->assertSame($rest['items'][0]['reference'], $bySlug['limoges-benedictins']['items'][0]['reference']);
        $this->assertSame('works', $bySlug['limoges-benedictins']['items'][1]['element']);
        $this->assertNull($bySlug['sans-patrimoine']);

        $call = fn (string $slug) => $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'station_heritage', 'arguments' => ['station' => $slug]]])
            ->assertOk()->json('result');
        $result = $call('limoges-benedictins');
        $this->assertFalse((bool) ($result['isError'] ?? false));
        $mcp = json_decode($result['content'][0]['text'], true);
        $this->assertSame($rest['items'], $mcp['heritage']['items']);
        $this->assertTrue($mcp['heritage']['protected']);

        $none = json_decode($call('sans-patrimoine')['content'][0]['text'], true);
        $this->assertNull($none['heritage']);
        $this->assertTrue((bool) ($call('inconnue')['isError'] ?? false));
    }
}
