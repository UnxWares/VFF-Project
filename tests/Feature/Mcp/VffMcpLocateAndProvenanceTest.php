<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Outil `locate_on_line` (point kilométrique, même service que l'API REST et GraphQL), provenance brute
 * des sources d'une gare dans `get_station`, chiffres d'ensemble dans `network_at_era`.
 */
class VffMcpLocateAndProvenanceTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
    }

    public function test_locate_on_line_returns_the_same_point_as_the_rest_api(): void
    {
        $rest = $this->getJson("/api/v1/lines/{$this->line->slug}/pk?lat=48.853&lng=2.24&era=1960")->assertOk()->json();
        [$data, $error] = $this->tool('locate_on_line', ['line' => $this->line->slug, 'lat' => 48.853, 'lng' => 2.24, 'year' => 1963]);

        $this->assertFalse($error);
        $this->assertSame(1960, $data['era_year'], 'année ramenée à l\'ère');
        $this->assertSame($rest['pk_m'], $data['pk_m']);
        $this->assertSame($rest['pk_label'], $data['pk_label']);
        $this->assertSame($rest['total_m'], $data['total_m']);
        $this->assertSame($this->line->slug, $data['line']['slug']);
        $this->assertArrayHasKey('attribution', $data);

        [$today] = $this->tool('locate_on_line', ['line' => $this->line->slug, 'lat' => 48.853, 'lng' => 2.24]);
        $this->assertSame(2030, $today['era_year']);
    }

    public function test_locate_on_line_reports_unknown_lines_and_rejects_impossible_points(): void
    {
        [, $error] = $this->tool('locate_on_line', ['line' => 'ligne-inconnue', 'lat' => 48.8, 'lng' => 2.2]);
        $this->assertTrue($error);

        $response = $this->rpc('tools/call', ['name' => 'locate_on_line', 'arguments' => (object) ['line' => $this->line->slug, 'lat' => 148.0, 'lng' => 2.2]]);
        $this->assertTrue($response->json('result.isError') ?? false, 'latitude impossible refusée');
    }

    public function test_get_station_publishes_the_raw_provenance_of_each_source(): void
    {
        DB::table('station_sources')->insert([
            'station_id' => $this->origin->id, 'source' => 'osm', 'source_ref' => 'n123', 'name' => 'Paris Saint-Lazare', 'lifecycle' => 'active',
            'match_method' => 'near', 'seen_at' => '2026-10-15 17:21:48', 'properties' => json_encode(['railway' => 'station']),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.3253, 48.8763), 4326)'),
        ]);
        $this->origin->update(['provenance' => ['name' => 'osm', 'geom' => 'osm'], 'position_source' => 'osm', 'position_spread_m' => 12, 'position_flagged' => true]);

        [$data] = $this->tool('get_station', ['station' => 'paris-saint-lazare']);

        $source = $data['sources'][0];
        $this->assertSame('n123', $source['ref']);
        $this->assertSame('2026-10-15', $source['fetched_at']);
        $this->assertSame('near', $source['match_method']);
        $this->assertSame(['railway' => 'station'], $source['raw']);
        $this->assertEquals(['name' => 'osm', 'geom' => 'osm'], $data['station']['attribute_sources']);
        $this->assertSame(['source' => 'osm', 'spread_m' => 12, 'flagged' => true], $data['station']['position']);
    }

    public function test_network_at_era_adds_the_site_totals_and_the_network_curve(): void
    {
        [$data] = $this->tool('network_at_era', ['year' => 1960]);

        $this->assertSame(1, $data['site_totals']['lines_count']);
        $this->assertSame(2, $data['site_totals']['stations_count']);
        $this->assertSame([1960, 2030], array_column($data['network_profile'], 'year'));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments])->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [json_decode($text, true) ?? ['raw' => $text], (bool) ($result['isError'] ?? false)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    private function rpc(string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }
}
