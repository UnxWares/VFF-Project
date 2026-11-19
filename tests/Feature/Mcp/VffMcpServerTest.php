<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\VffServer;
use App\Mcp\Tools\SearchTool;
use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Serveur MCP (app/Mcp) par son transport HTTP (POST /mcp, JSON-RPC), sur un mini-réseau :
 *
 *   A ───────── B ───────── C      ligne 420000 « Ligne de A à C » : en service en 1930 et en 2030,
 *               │                  deux événements sourcés (Wikipédia, archeoferroviaire)
 *               D                  ligne « Ligne de B à D » : en service en 1930, fermée en 2030
 */
class VffMcpServerTest extends TestCase
{
    use RefreshDatabase;

    private Era $era1930;

    private Era $era2030;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era1930 = Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);
        $this->era2030 = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);

        $ac = $this->line('rfn-420000', '420000', 'Ligne de A à C', [[48.0, 2.00], [48.0, 2.20]], ['in_service', 'in_service'], true);
        $this->line('ligne-b-d', null, 'Ligne de B à D', [[48.0, 2.10], [48.2, 2.10]], ['in_service', 'abandoned'], false);
        foreach (['a' => [48.0, 2.00], 'b' => [48.0, 2.10], 'c' => [48.0, 2.20]] as $slug => [$lat, $lng]) {
            $this->station($slug, $lat, $lng, ['active', 'active']);
        }
        $this->station('d', 48.2, 2.10, ['active', 'declassified']);

        DB::table('line_events')->insert([
            ['line_id' => $ac->id, 'type' => 'opening', 'event_date' => '1865-04-01', 'date_precision' => 'day',
                'description' => 'Ouverture de la ligne', 'section_from' => 'A', 'section_to' => 'C', 'source' => 'wikipedia',
                'source_url' => 'https://fr.wikipedia.org/wiki/Ligne_de_A_%C3%A0_C', 'created_at' => now(), 'updated_at' => now()],
            ['line_id' => $ac->id, 'type' => 'electrification', 'event_date' => '1935-01-01', 'date_precision' => 'year',
                'description' => 'Électrification', 'section_from' => null, 'section_to' => null, 'source' => 'archeoferroviaire',
                'source_url' => 'http://archeoferroviaire.free.fr/x', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** @param  array{0:string, 1:string}  $statuses  statut 1930, 2030 */
    private function line(string $slug, ?string $code, string $name, array $coords, array $statuses, bool $electrified): Line
    {
        $line = Line::factory()->create(['slug' => $slug, 'code' => $code, 'name' => $name, 'is_lgv' => false, 'network' => 'principal']);
        foreach ([$this->era1930, $this->era2030] as $i => $era) {
            LineSegment::factory()->create([
                'line_id' => $line->id, 'era_id' => $era->id, 'status' => $statuses[$i], 'traffic_type' => 'mixed',
                'geom' => LineString::make(array_map(fn ($c) => Point::makeGeodetic($c[0], $c[1]), $coords)),
                'is_heritage' => false, 'gauge_mm' => 1435, 'electrified' => $electrified, 'voltage_v' => $electrified ? 1500 : null,
                'max_speed_kmh' => 120, 'source' => 'osm',
            ]);
        }

        return $line;
    }

    /** @param  array{0:string, 1:string}  $statuses  statut 1930, 2030 */
    private function station(string $slug, float $lat, float $lng, array $statuses): void
    {
        $station = Station::factory()->create([
            'slug' => $slug, 'name' => 'Gare '.strtoupper($slug), 'city' => 'Ville '.strtoupper($slug), 'type' => 'gare',
            'geom' => Point::makeGeodetic($lat, $lng), 'status' => $statuses[1], 'opened_at' => '1865-04-01', 'opened_precision' => 'day',
        ]);
        foreach ([$this->era1930, $this->era2030] as $i => $era) {
            DB::table('station_eras')->insert([
                'station_id' => $station->id, 'era_id' => $era->id, 'status' => $statuses[$i],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    private function rpc(string $method, array $params = [], array $headers = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'] + $headers)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]);
    }

    /**
     * Appel d'outil : JSON décodé de la réponse, et indicateur d'erreur.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments])->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [json_decode($text, true) ?? ['raw' => $text], (bool) $result['isError']];
    }

    public function test_initialize_and_list_every_read_only_tool(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'VFF - Voies Ferrées de France')
            ->assertHeader('X-RateLimit-Limit', '300');

        $list = $this->rpc('tools/list')->assertOk();
        $this->assertNull($list->json('result.nextCursor'), 'tous les outils tiennent dans la première page');
        $tools = collect((array) $list->json('result.tools'))->keyBy('name');
        $this->assertEqualsCanonicalizing(
            ['search', 'get_line', 'line_events', 'get_station', 'network_at_era', 'historical_route', 'sources_and_licences',
                'network_events', 'media', 'list_lines', 'list_stations', 'materials', 'get_material', 'accidents', 'get_accident', 'compare_eras', 'level_crossings', 'line_profile', 'line_greenways', 'line_materials', 'locate_on_line'],
            $tools->keys()->all(),
        );
        foreach ($tools as $name => $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint'], "{$name} : lecture seule");
            $this->assertGreaterThan(200, mb_strlen($tool['description']), "{$name} : description détaillée");
            $this->assertSame(['fr', 'en', 'de'], $tool['inputSchema']['properties']['lang']['enum']);
            foreach ($tool['inputSchema']['properties'] as $arg => $schema) {
                $this->assertNotEmpty($schema['description'] ?? null, "{$name}.{$arg} : description");
            }
        }
        $this->assertSame(['query'], $tools['search']['inputSchema']['required']);
    }

    public function test_resources_are_listed(): void
    {
        $this->assertSame('vff://licences', $this->rpc('resources/list')->assertOk()->json('result.resources.0.uri'));
        $this->assertSame('vff://{lang}/lines/{slug}', $this->rpc('resources/templates/list')->assertOk()->json('result.resourceTemplates.0.uriTemplate'));
    }

    public function test_search_finds_lines_and_stations_with_links_and_attribution(): void
    {
        [$data, $error] = $this->tool('search', ['query' => '420 000']);
        $this->assertFalse($error);
        $this->assertSame('rfn-420000', $data['lines'][0]['slug']);
        $this->assertSame('https://vff-project.org/lines/rfn-420000', $data['lines'][0]['vff_url']);
        $this->assertSame(['vff', 'osm', 'sncf'], array_column($data['attribution']['sources'], 'provider'));

        [$data] = $this->tool('search', ['query' => 'gare b', 'kinds' => ['stations'], 'limit' => 1]);
        $this->assertSame([], $data['lines']);
        $this->assertSame(['b'], array_column($data['stations'], 'slug'));
    }

    public function test_inputs_are_strictly_validated(): void
    {
        [$data, $error] = $this->tool('search', ['query' => 'a']);
        $this->assertTrue($error);

        [$data, $error] = $this->tool('search', ['query' => 'gare', 'drop' => 'table']);
        $this->assertTrue($error);
        $this->assertStringContainsString('drop', $data['raw']);

        [, $error] = $this->tool('search', ['query' => 'gare', 'kinds' => ['users']]);
        $this->assertTrue($error);
        [, $error] = $this->tool('line_events', ['line' => 'rfn-420000', 'from_year' => 1950, 'to_year' => 1900]);
        $this->assertTrue($error);
        [, $error] = $this->tool('network_at_era', ['year' => 'mille']);
        $this->assertTrue($error);
        [, $error] = $this->tool('search', ['query' => 'gare', 'lang' => 'es']);
        $this->assertTrue($error);
    }

    public function test_line_sheet_by_code_with_sourced_chronology(): void
    {
        [$data, $error] = $this->tool('get_line', ['line' => '420000']);
        $this->assertFalse($error);
        $line = $data['line'];
        $this->assertSame('rfn-420000', $line['slug']);
        $this->assertSame(1500, $line['electrification']['voltages'][0]['voltage_v']);
        $this->assertSame('full', $line['electrification']['state']);
        $this->assertSame(120, $line['max_speed_kmh']);
        $this->assertEqualsWithDelta(14.9, $line['length']['trace_km'], 0.3);
        $this->assertSame(2, $line['event_count']);
        $this->assertSame('1865-04-01', $line['events'][0]['date']);
        $this->assertSame('1er avril 1865', $line['events'][0]['when']);
        $this->assertSame('A - C', $line['events'][0]['section']);
        $this->assertSame('wikipedia', $line['events'][0]['provider']);
        $this->assertSame('https://vff-project.org/lines/rfn-420000', $data['vff_url']);

        $sources = collect((array) $data['attribution']['sources'])->keyBy('provider');
        $this->assertTrue($sources['osm']['open_licence']);
        $this->assertFalse($sources['archeoferroviaire']['open_licence'], 'source sans licence ouverte signalée');
        $this->assertStringContainsString('ODbL', $data['attribution']['notice']);
    }

    public function test_line_sheet_in_english_and_unknown_line(): void
    {
        [$data] = $this->tool('get_line', ['line' => 'rfn-420000', 'lang' => 'en', 'include_events' => false]);
        $this->assertArrayNotHasKey('events', $data['line']);
        $this->assertSame('Electrified', $data['line']['electrification']['state_label']);
        $this->assertStringContainsString('OpenStreetMap contributors', json_encode($data['attribution'], JSON_UNESCAPED_UNICODE));
        $this->assertSame('fr', app()->getLocale(), 'langue rétablie après l\'appel');

        [$data, $error] = $this->tool('get_line', ['line' => 'ligne de a']);
        $this->assertTrue($error);
        $this->assertSame('line_not_found', $data['error']);
        $this->assertSame('rfn-420000', $data['suggestions'][0]['slug']);
    }

    public function test_line_events_filtered_by_period_and_type(): void
    {
        [$data] = $this->tool('line_events', ['line' => 'rfn-420000', 'from_year' => 1900, 'to_year' => 1950]);
        $this->assertSame(1, $data['total']);
        $this->assertSame('electrification', $data['events'][0]['type']);
        $this->assertSame('1935', $data['events'][0]['when']);
        $this->assertSame('archeoferroviaire', $data['events'][0]['provider']);

        // Une seule borne : l'autre reste ouverte (avant, `to_year` seul était refusé).
        [$data, $error] = $this->tool('line_events', ['line' => 'rfn-420000', 'to_year' => 1900]);
        $this->assertFalse($error);
        $this->assertSame(['opening'], array_column($data['events'], 'type'));
        [$data, $error] = $this->tool('line_events', ['line' => 'rfn-420000', 'from_year' => 1900]);
        $this->assertFalse($error);
        $this->assertSame(['electrification'], array_column($data['events'], 'type'));

        [$data] = $this->tool('line_events', ['line' => 'rfn-420000', 'types' => ['opening'], 'lang' => 'de']);
        $this->assertSame(['opening'], array_column($data['events'], 'type'));
        $this->assertSame('1. April 1865', $data['events'][0]['when']);
    }

    public function test_station_sheet(): void
    {
        [$data, $error] = $this->tool('get_station', ['station' => 'd']);
        $this->assertFalse($error);
        $this->assertSame('Gare D', $data['station']['name']);
        $this->assertSame([['year' => 1930, 'status' => 'active'], ['year' => 2030, 'status' => 'declassified']], $data['eras']);
        $this->assertSame('1865-04-01', $data['station']['opened']['date']);
        $this->assertSame('https://vff-project.org/stations/d', $data['vff_url']);

        [$data, $error] = $this->tool('get_station', ['station' => 'gare-inconnue']);
        $this->assertTrue($error);
        $this->assertSame('station_not_found', $data['error']);
    }

    public function test_network_at_era(): void
    {
        [$data] = $this->tool('network_at_era', ['year' => 1937]);
        $this->assertSame(1930, $data['era_year']);
        $this->assertSame(2, $data['active_lines']);
        $this->assertEqualsWithDelta(37, $data['open_km'], 1);
        $this->assertSame('1930-12-31', $data['snapshot']);
        $this->assertSame(4, collect((array) $data['stations_by_status'])->firstWhere('code', 'active')['count']);

        [$data] = $this->tool('network_at_era', ['year' => 2099]);
        $this->assertSame(2030, $data['era_year']);
        $this->assertSame(1, $data['active_lines']);

        [$data, $error] = $this->tool('network_at_era', ['year' => 1850]);
        $this->assertTrue($error);
        $this->assertSame([1930, 2030], $data['available_eras']);
    }

    public function test_historical_route(): void
    {
        $this->artisan('vff:build-network-graph')->assertSuccessful();

        [$data, $error] = $this->tool('historical_route', ['from' => 'a', 'to' => 'd', 'year' => 1934]);
        $this->assertFalse($error);
        $this->assertSame(1930, $data['era_year']);
        $this->assertSame(['a', 'b', 'd'], array_column($data['stations'], 'slug'));
        $this->assertSame(['rfn-420000', 'ligne-b-d'], array_column($data['lines'], 'slug'));
        $this->assertStringStartsWith('https://vff-project.org/maps?from=a&to=d&year=1930', $data['vff_url']);

        [$data, $error] = $this->tool('historical_route', ['from' => 'a', 'to' => 'd', 'year' => 2030]);
        $this->assertTrue($error);
        $this->assertSame('station_not_in_network', $data['error']);
    }

    public function test_historical_route_compares_two_eras(): void
    {
        $this->artisan('vff:build-network-graph')->assertSuccessful();

        [$data, $error] = $this->tool('historical_route', ['from' => 'a', 'to' => 'd', 'year' => 2026, 'compare_year' => 1930, 'lang' => 'en']);
        $this->assertFalse($error);
        $this->assertSame(1930, $data['earlier']['era_year']);
        $this->assertTrue($data['earlier']['available']);
        $this->assertSame(['a', 'b', 'd'], array_column($data['earlier']['stations'], 'slug'));
        $this->assertFalse($data['later']['available']);
        $this->assertSame('station_not_in_network', $data['later']['unavailable']['error']);
        $this->assertSame('declassified', $data['later']['unavailable']['status']);
        $this->assertSame('lost', $data['differences']['outcome']);
        $this->assertSame(['rfn-420000' => 'open', 'ligne-b-d' => 'closed'], array_column($data['differences']['lines_only_earlier'], 'other_era', 'slug'));
        $this->assertStringContainsString('year=1930&compare_year=2030', $data['vff_url']);

        [$data, $error] = $this->tool('historical_route', ['from' => 'a', 'to' => 'c', 'year' => 1931, 'compare_year' => 1935]);
        $this->assertTrue($error);
        $this->assertSame('same_era', $data['error']);
        $this->assertStringContainsString('1930', $data['message']);
    }

    public function test_sources_and_licences_hide_internal_notes(): void
    {
        [$data] = $this->tool('sources_and_licences');
        $providers = collect((array) $data['providers'])->keyBy('provider');
        $this->assertTrue($providers->has('osm'));
        $this->assertSame('ODbL 1.0 (Open Database License)', $providers['osm']['licence']);
        $this->assertNotEmpty($providers['osm']['proof']);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        foreach (['remarques', 'obligations', 'accord'] as $internal) {
            $this->assertStringNotContainsString('"'.$internal.'"', $json);
        }

        [$data] = $this->tool('sources_and_licences', ['provider' => 'wikidata', 'lang' => 'en']);
        $this->assertSame(['wikidata'], array_column($data['providers'], 'provider'));
    }

    public function test_markdown_resources(): void
    {
        $md = $this->rpc('resources/read', ['uri' => 'vff://fr/lines/rfn-420000'])->assertOk()->json('result.contents.0.text');
        $this->assertStringStartsWith('# Ligne de A à C', $md);
        $this->assertStringContainsString('https://vff-project.org/lines/rfn-420000', $md);
        $this->assertStringContainsString('© contributeurs OpenStreetMap', $md);

        $licences = $this->rpc('resources/read', ['uri' => 'vff://licences'])->assertOk()->json('result.contents.0.text');
        $this->assertStringContainsString('## OpenStreetMap', $licences);
    }

    public function test_responses_are_capped_in_size(): void
    {
        config(['mcp.vff.max_response_bytes' => 2500]);
        $line = Line::where('slug', 'rfn-420000')->firstOrFail();
        foreach (range(1, 60) as $i) {
            DB::table('line_events')->insert(['line_id' => $line->id, 'type' => 'other', 'event_date' => (1870 + $i).'-01-01',
                'date_precision' => 'year', 'description' => 'Événement '.$i, 'source' => 'wikipedia', 'created_at' => now(), 'updated_at' => now()]);
        }
        [$data] = $this->tool('line_events', ['line' => 'rfn-420000']);
        $this->assertSame(62, $data['total']);
        $this->assertGreaterThan(0, $data['truncated']['events']);
        $this->assertLessThan(62, count($data['events']));
        $this->assertArrayHasKey('truncated_note', $data);
    }

    public function test_foreign_browser_origin_is_refused(): void
    {
        $this->rpc('tools/list', [], ['Origin' => 'https://evil.example'])->assertForbidden();
    }

    public function test_server_class_registers_the_tools(): void
    {
        VffServer::tool(SearchTool::class, ['query' => 'gare a'])->assertOk()->assertSee('Gare A');
    }
}
