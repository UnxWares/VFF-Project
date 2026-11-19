<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Tools\LineGreenwaysTool;
use App\Models\Era;
use App\Models\Line;
use App\Services\Greenways\GreenwayNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Outil `line_greenways` : mêmes voies vertes que l'API REST (`/lines/{line}/greenways`), avec la
 * provenance OpenStreetMap, la mention ODbL et la méthode de rattachement ; nom de voie verte filtré
 * (jamais un lieu, un type de voie ou une rue).
 */
class VffMcpGreenwaysToolTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-436000', 'code' => '436000', 'name' => 'Ligne de La Chapelle-Anthenaise à Flers']);
        Line::factory()->create(['slug' => 'rfn-1000', 'code' => '001000', 'name' => 'Ligne sans voie verte']);

        $this->greenway(11, 'Voie Verte de Mayenne', 'Voie verte de Mayenne à Javron', 253186, 11200, null);
        $this->greenway(12, 'Chemin de halage', 'Voie verte de Mayenne à Javron', 253186, 3961, null);
        $this->greenway(13, 'Rue du Denier', 'Camping', 777, 700, 2006);
        $this->greenway(14, null, null, null, 300, null);
    }

    private function greenway(int $way, ?string $name, ?string $relationName, ?int $relation, int $shared, ?int $year): void
    {
        DB::insert("INSERT INTO line_greenways (line_id, osm_way_id, osm_relation_id, name, relation_name, display_name, highway, shared_length_m, way_length_m,
                shared_percent, opened_year, source, osm_seen_at, created_at, updated_at, geom)
            VALUES (?, ?, ?, ?, ?, ?, 'path', ?, ?, 100, ?, 'osm', '2026-11-18', now(), now(), ST_Multi(ST_GeomFromText(?, 4326)))", [
            $this->line->id, $way, $relation, $name, $relationName, GreenwayNames::displayName($relationName, $name), $shared, $shared, $year,
            sprintf('LINESTRING(%F 48.5, %F 48.5)', 1.0 + $way / 100, 1.0 + $way / 100 + 0.01),
        ]);
    }

    public function test_the_tool_returns_the_greenways_of_the_rest_api_with_provenance_and_odbl_attribution(): void
    {
        $rest = $this->getJson('/api/v1/lines/rfn-436000/greenways')->assertOk()->json();
        [$data, $error] = $this->tool('line_greenways', ['line' => '436000']);

        $this->assertFalse($error);
        $this->assertSame(['slug' => 'rfn-436000', 'code' => '436000', 'name' => 'Ligne de La Chapelle-Anthenaise à Flers'], $data['line']);
        $this->assertSame($rest['total_shared_length_m'], $data['total_shared_length_m']);
        $this->assertSame('2026-11-18', $data['osm_date']);
        // Mêmes groupes que le REST, sans le détail des ways par défaut.
        $this->assertSame(array_column($rest['greenways'], 'name'), array_column($data['greenways'], 'name'));
        $this->assertSame('Voie verte de Mayenne à Javron', $data['greenways'][0]['name']);
        $this->assertSame(15161, $data['greenways'][0]['shared_length_m']);
        $this->assertSame('https://www.openstreetmap.org/relation/253186', $data['greenways'][0]['osm_url']);
        $this->assertArrayNotHasKey('ways', $data['greenways'][0]);

        $this->assertSame('osm', $data['provenance']['provider']);
        $this->assertStringContainsString('ODbL', $data['provenance']['license']);
        $this->assertStringContainsString('contributeurs OpenStreetMap', $data['provenance']['mention']);
        $this->assertSame('https://vff-project.org/lines/rfn-436000#greenways', $data['vff_url']);
        $providers = array_column($data['attribution']['sources'], 'provider');
        $this->assertContains('osm', $providers);
        $osm = array_values(array_filter($data['attribution']['sources'], fn (array $s): bool => $s['provider'] === 'osm'))[0];
        $this->assertStringContainsString('ODbL', $osm['mention']);
        $this->assertSame(['axis_tolerance_m' => 8, 'service_clearance_m' => 30, 'min_way_shared_m' => 50, 'min_way_share_percent' => 50, 'min_line_total_m' => 300], $data['method']);
    }

    public function test_a_name_that_is_not_an_itinerary_does_not_name_the_group_and_a_missing_year_stays_null(): void
    {
        [$data] = $this->tool('line_greenways', ['line' => 'rfn-436000']);

        $unnamed = $data['greenways'][1];
        $this->assertNull($unnamed['name'], '« Camping » (relation) et « Rue du Denier » (way) ne nomment pas la voie verte');
        $this->assertSame(1000, $unnamed['shared_length_m']);
        $this->assertSame(2, $unnamed['way_count']);
        $this->assertSame(2006, $unnamed['opened_year']);
        $this->assertSame('https://www.openstreetmap.org/way/13', $unnamed['osm_url'], 'pas de lien vers la relation « Camping »');
        $this->assertNull($data['greenways'][0]['opened_year']);
    }

    public function test_include_ways_adds_the_osm_detail_capped_per_greenway(): void
    {
        [$data] = $this->tool('line_greenways', ['line' => 'rfn-436000', 'include_ways' => true]);

        $ways = $data['greenways'][0]['ways'];
        $this->assertSame([11, 12], array_column($ways, 'osm_way_id'));
        $this->assertSame('https://www.openstreetmap.org/way/11', $ways[0]['osm_url']);
        $this->assertSame(0, $data['greenways'][0]['ways_not_shown']);

        for ($i = 0; $i < LineGreenwaysTool::MAX_WAYS_PER_GREENWAY + 5; $i++) {
            $this->greenway(100 + $i, null, 'Via Longue', 999, 100, null);
        }
        [$long] = $this->tool('line_greenways', ['line' => 'rfn-436000', 'include_ways' => true]);
        $via = array_values(array_filter($long['greenways'], fn (array $g): bool => $g['name'] === 'Via Longue'))[0];
        $this->assertCount(LineGreenwaysTool::MAX_WAYS_PER_GREENWAY, $via['ways']);
        $this->assertSame(5, $via['ways_not_shown']);
        $this->assertSame(LineGreenwaysTool::MAX_WAYS_PER_GREENWAY + 5, $via['way_count']);
    }

    public function test_a_line_without_greenway_gives_an_empty_list_and_an_unknown_line_an_error(): void
    {
        [$data, $error] = $this->tool('line_greenways', ['line' => 'rfn-1000']);
        $this->assertFalse($error);
        $this->assertSame([], $data['greenways']);
        $this->assertSame(0, $data['total_shared_length_m']);
        $this->assertNull($data['osm_date']);
        $this->assertSame('osm', $data['provenance']['provider']);

        [$missing, $notFound] = $this->tool('line_greenways', ['line' => 'ligne-inconnue']);
        $this->assertTrue($notFound);
        $this->assertSame('line_not_found', $missing['error']);
    }

    public function test_answers_follow_the_requested_language_and_unknown_arguments_are_refused(): void
    {
        [$data] = $this->tool('line_greenways', ['line' => 'rfn-436000', 'lang' => 'en']);
        $this->assertStringContainsString('OpenStreetMap', $data['attribution']['notice'].json_encode($data['attribution']['sources']));

        $response = $this->rpc('tools/call', ['name' => 'line_greenways', 'arguments' => (object) ['line' => 'rfn-436000', 'colour' => 'rose']]);
        $this->assertTrue($response->json('result.isError') ?? false);
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
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]);
    }
}
