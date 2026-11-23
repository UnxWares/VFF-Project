<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Station;
use App\Services\Accessibility\StationAccessibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Accessibilité et services d'une gare : même contenu en REST (`/stations/{station}/accessibility` et `accessibility` de la fiche),
 * GraphQL (`Station.accessibility`), MCP (`station_accessibility`) ; nul sans donnée ; filtre `accessible` de l'annuaire ; aucune
 * erreur tant que la migration n'est pas appliquée.
 */
class StationAccessibilityApiTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        (new ReflectionProperty(StationAccessibility::class, 'ready'))->setValue(null, null);
        Era::factory()->current()->create(['year' => 2030]);
        $this->station = Station::factory()->create(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'uic_code' => '87592006']);
        Station::factory()->create(['slug' => 'sans-donnee', 'name' => 'Sans donnée']);
        $closed = Station::factory()->create(['slug' => 'inaccessible', 'name' => 'Inaccessible', 'uic_code' => '87000002']);
        $base = ['source' => 'sncf', 'computed_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'datasets' => json_encode([
            ['dataset' => 'accessibilite_gares', 'title' => 'Accessibilité des gares', 'publisher' => 'Gares & Connexions', 'license' => 'ODbL', 'modified' => '2026-05-21', 'page' => 'https://data.sncf.com/explore/dataset/accessibilite_gares/']])];
        DB::table('station_accessibility')->insert($base + [
            'station_id' => $this->station->id, 'uic_code' => '87592006', 'sncf_station_id' => 'aaaaaaaa-0000-4000-8000-000000000001',
            'mobility' => 'partial', 'accessible' => true, 'step_free' => true, 'tactile_guidance' => false, 'quays_total' => 4, 'quays_step_free' => 3,
            'lifts' => 6, 'escalators' => 0, 'travelators' => 0, 'netex_comment' => null,
            'elevators' => json_encode(['state_at' => '2026-11-23T10:00:00+01:00', 'ok' => 5, 'ko' => 1, 'unknown' => 0, 'items' => [['label' => 'Ascenseur - Quai 1', 'location' => 'Souterrain', 'state' => 'ko']]]),
            'amenities' => json_encode(['toilets', 'induction_loop', 'wheelchair_loan']),
            'assistance' => json_encode(['mode' => 'reserved', 'free' => true, 'meeting_point' => 'Guichet', 'first_to_last_train' => false,
                'hours' => ['sunday' => '08:00 - 12:00', 'monday' => '06:00 - 21:00'], 'since' => '2025-05-06']),
            'sales' => json_encode(['counter' => true, 'machines' => ['ter'], 'assisted' => false]),
            'bike_parking' => json_encode(['places' => 20, 'as_of' => '2024-12-30', 'lom' => true, 'lom_target' => 20]),
        ]);
        DB::table('station_accessibility')->insert($base + [
            'station_id' => $closed->id, 'uic_code' => '87000002', 'sncf_station_id' => 'aaaaaaaa-0000-4000-8000-000000000002',
            'mobility' => 'none', 'accessible' => false, 'step_free' => false, 'amenities' => json_encode([]),
        ]);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(StationAccessibility::class, 'ready'))->setValue(null, null);
        parent::tearDown();
    }

    public function test_rest_returns_access_elevators_amenities_assistance_and_sources(): void
    {
        $json = $this->getJson('/api/v1/stations/limoges-benedictins/accessibility')->assertOk()->json();

        $this->assertSame(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'uic_code' => '87592006'], $json['station']);
        $a = $json['accessibility'];
        $this->assertSame(['partial', true, true, false], [$a['access']['mobility'], $a['access']['accessible'], $a['access']['step_free'], $a['access']['tactile_guidance']]);
        $this->assertSame(['total' => 4, 'step_free' => 3], $a['access']['quays']);
        $this->assertSame(6, $a['elevators']['count']);
        $this->assertSame([5, 1, '2026-11-23T10:00:00+01:00'], [$a['elevators']['status']['ok'], $a['elevators']['status']['ko'], $a['elevators']['status']['state_at']]);
        $this->assertSame(['induction_loop', 'wheelchair_loan', 'toilets'], $a['amenities'], 'ordre stable des équipements');
        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], array_keys($a['assistance']['hours']), 'horaires dans l\'ordre de la semaine');
        $this->assertSame('06:00 - 21:00', $a['assistance']['hours']['monday']);
        $this->assertNull($a['assistance']['hours']['tuesday']);
        $this->assertSame(['counter' => true, 'machines' => ['ter'], 'assisted' => false], $a['sales']);
        $this->assertSame(20, $a['bike_parking']['places']);
        $this->assertSame('ODbL', $a['datasets'][0]['license']);
        $this->assertSame('sncf', $a['provenance']['provider']);
        $this->assertStringContainsString('ODbL', $a['attribution']);
        $this->assertStringContainsString('pas contractuel', $a['method']);
    }

    public function test_a_station_known_as_inaccessible_says_so_and_has_no_other_fact(): void
    {
        $a = $this->getJson('/api/v1/stations/inaccessible/accessibility')->assertOk()->json('accessibility');

        $this->assertSame(['none', false, false], [$a['access']['mobility'], $a['access']['accessible'], $a['access']['step_free']]);
        $this->assertSame([], $a['amenities']);
        $this->assertNull($a['assistance']);
        $this->assertNull($a['elevators']['status']);
        $this->assertNull($a['sales']);
        $this->assertNull($a['bike_parking']);
    }

    public function test_a_station_without_data_gets_null_not_an_error(): void
    {
        $this->assertNull($this->getJson('/api/v1/stations/sans-donnee/accessibility')->assertOk()->json('accessibility'));
        $this->getJson('/api/v1/stations/inconnue/accessibility')->assertNotFound();
    }

    public function test_the_station_sheet_carries_the_same_block_and_a_link(): void
    {
        $rest = $this->getJson('/api/v1/stations/limoges-benedictins/accessibility')->json('accessibility');
        $sheet = $this->getJson('/api/v1/stations/limoges-benedictins')->assertOk()->json();

        $this->assertSame($rest, $sheet['data']['accessibility']);
        $this->assertStringEndsWith('/api/v1/stations/limoges-benedictins/accessibility', $sheet['data']['links']['accessibility']);
        $this->assertNull($this->getJson('/api/v1/stations/sans-donnee')->json('data.accessibility'));
    }

    public function test_the_web_page_loads_nothing_more_for_it(): void
    {
        $this->get('/stations/limoges-benedictins')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Stations/Show')->missing('accessibility'));
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $rest = $this->getJson('/api/v1/stations/limoges-benedictins/accessibility')->json('accessibility');
        $gql = $this->postJson('/graphql', ['query' => '{ stations(first: 10) { data { slug accessibility {
            uic_code access { mobility accessible step_free tactile_guidance quays { total step_free } comment }
            elevators { count escalators travelators status { state_at ok ko unknown items { label location state } } }
            amenities assistance { mode free meeting_point first_to_last_train hours since } sales { counter machines assisted }
            bike_parking { places as_of lom lom_target } datasets { dataset title publisher license modified page } attribution method provenance { provider } } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.stations.data');
        $bySlug = array_column($gql, 'accessibility', 'slug');

        $this->assertSame($rest['access']['mobility'], $bySlug['limoges-benedictins']['access']['mobility']);
        $this->assertSame($rest['amenities'], $bySlug['limoges-benedictins']['amenities']);
        $this->assertSame(6, $bySlug['limoges-benedictins']['elevators']['count']);
        $this->assertSame('ko', $bySlug['limoges-benedictins']['elevators']['status']['items'][0]['state']);
        $this->assertSame(20, $bySlug['limoges-benedictins']['bike_parking']['places']);
        $this->assertSame('none', $bySlug['inaccessible']['access']['mobility']);
        $this->assertNull($bySlug['sans-donnee']);

        $call = fn (string $tool, array $args) => $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $args]])
            ->assertOk()->json('result');
        $mcp = json_decode($call('station_accessibility', ['station' => 'limoges-benedictins'])['content'][0]['text'], true);
        $this->assertSame($rest['amenities'], $mcp['accessibility']['amenities']);
        $this->assertStringContainsString('ODbL', $mcp['accessibility']['mention']);
        $this->assertNull(json_decode($call('station_accessibility', ['station' => 'sans-donnee'])['content'][0]['text'], true)['accessibility']);
        $this->assertTrue((bool) ($call('station_accessibility', ['station' => 'inconnue'])['isError'] ?? false));
    }

    public function test_the_directory_filters_accessible_stations_on_every_surface(): void
    {
        $slugs = fn (array $json): array => array_column($json['data'], 'slug');

        $this->assertSame(['limoges-benedictins'], $slugs($this->getJson('/api/v1/stations?accessible=true')->assertOk()->json()));
        $this->assertContains('sans-donnee', $slugs($this->getJson('/api/v1/stations?accessible=false')->assertOk()->json()));
        $this->get('/stations?accessible=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.accessible', true)->where('stations.total', 1)->where('stations.data.0.slug', 'limoges-benedictins'));

        $mcp = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list_stations', 'arguments' => ['accessible' => true]]])
            ->assertOk()->json('result');
        $this->assertStringContainsString('limoges-benedictins', $mcp['content'][0]['text']);
        $this->assertStringNotContainsString('sans-donnee', $mcp['content'][0]['text']);
    }

    public function test_a_missing_table_gives_nothing_and_no_error_then_is_checked_again(): void
    {
        $reset = fn () => (new ReflectionProperty(StationAccessibility::class, 'ready'))->setValue(null, null);
        $reset();
        Cache::flush();

        DB::beginTransaction();
        Schema::drop('station_accessibility');
        $this->assertFalse(StationAccessibility::ready());
        $this->assertNull(app(StationAccessibility::class)->forStation((int) $this->station->id));
        $this->get('/stations/limoges-benedictins')->assertOk();
        $this->getJson('/api/v1/stations/limoges-benedictins')->assertOk()->assertJsonPath('data.accessibility', null);
        $this->getJson('/api/v1/stations/limoges-benedictins/accessibility')->assertOk()->assertJsonPath('accessibility', null);
        $this->getJson('/api/v1/stations?accessible=true')->assertOk()->assertJsonCount(0, 'data');
        DB::rollBack();

        $this->assertTrue(StationAccessibility::ready(), 'un constat négatif ne reste pas en mémoire du processus');
    }
}
