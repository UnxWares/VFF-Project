<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Services\Structures\LineStructures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ouvrages d'art d'une ligne : même contenu en REST (`/lines/{line}/structures`), GraphQL
 * (`Line.structures`) et MCP (`line_structures`), tri par PK, ouvrage sans PK à la fin, ouvrage d'une
 * ligne déposée gardé (`current` faux), mention IGN et Wikidata.
 */
class LineStructuresApiTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-722000', 'code' => '722000', 'name' => 'Ligne de Béziers à Neussargues']);
        Line::factory()->create(['slug' => 'rfn-001000', 'code' => '001000', 'name' => 'Ligne sans ouvrage']);

        $garabit = $this->structure([
            'kind' => 'viaduct', 'name' => 'viaduc de Garabit', 'name_source' => 'wikidata', 'length_m' => 565, 'length_source' => 'bdtopo',
            'built_on' => '1880-01-01', 'built_precision' => 'year', 'opened_on' => '1888-11-10', 'opened_precision' => 'day',
            'heritage' => 'classified', 'merimee_ref' => 'PA00093681', 'wikidata_id' => 'Q1333442', 'wikipedia_title' => 'Viaduc de Garabit',
            'match_key' => 'Q1333442', 'sources' => json_encode([['source' => 'bdtopo', 'url' => 'https://geoservices.ign.fr/bdtopo', 'fields' => ['geometry', 'length_m']]]),
        ]);
        $tunnel = $this->structure(['kind' => 'tunnel', 'name' => null, 'length_m' => 320, 'length_source' => 'bdtopo', 'match_key' => 'bdtopo:a']);
        $old = $this->structure(['kind' => 'tunnel', 'name' => 'Tunnel du Lioran', 'name_source' => 'bdtopo', 'length_m' => 1958, 'length_source' => 'bdtopo', 'match_key' => 'bdtopo:b']);
        $this->link($garabit, 653310, 653875, [1890, 2030]);
        $this->link($tunnel, 12000, 12320, [2030]);
        $this->link($old, null, null, [1900]);
    }

    /** @param  array<string, mixed>  $row */
    private function structure(array $row): int
    {
        return (int) DB::table('structures')->insertGetId($row + [
            'lat' => 44.97, 'lon' => 3.17, 'sources' => '[]', 'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  list<int>  $years */
    private function link(int $structure, ?int $from, ?int $to, array $years): void
    {
        DB::table('line_structures')->insert(['line_id' => $this->line->id, 'structure_id' => $structure, 'pk_start_m' => $from, 'pk_end_m' => $to,
            'distance_from_m' => $from, 'distance_to_m' => $to, 'offset_m' => 3, 'era_years' => json_encode($years), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_rest_lists_structures_by_pk_with_summary_and_attribution(): void
    {
        $json = $this->getJson('/api/v1/lines/rfn-722000/structures')->assertOk()->json();

        $this->assertSame(['tunnel' => 2, 'viaduct' => 1, 'bridge' => 0], $json['summary']['by_kind']);
        $this->assertSame(2278, $json['summary']['tunnel_length_m']);
        $this->assertSame(['name' => 'Tunnel du Lioran', 'kind' => 'tunnel', 'length_m' => 1958], $json['summary']['longest']);
        $this->assertSame([null, 'viaduc de Garabit', 'Tunnel du Lioran'], array_column($json['items'], 'name'), 'tri par PK, sans PK à la fin');
        $garabit = $json['items'][1];
        $this->assertSame('653+310', $garabit['pk_start_label']);
        $this->assertSame(['date' => '1888-11-10', 'precision' => 'day'], $garabit['opened']);
        $this->assertSame('classified', $garabit['heritage']);
        $this->assertSame('https://fr.wikipedia.org/wiki/Viaduc_de_Garabit', $garabit['wikipedia_url']);
        $this->assertTrue($garabit['current']);
        $this->assertFalse($json['items'][2]['current'], 'ouvrage d\'une ligne déposée : gardé, hors du tracé actuel');
        $this->assertSame('ign', $json['provenance']['provider']);
        $this->assertSame(LineStructures::ATTRIBUTION, $json['attribution']);

        $this->assertSame(0, $this->getJson('/api/v1/lines/rfn-001000/structures')->assertOk()->json('summary.count'));
        $this->getJson('/api/v1/lines/inconnue/structures')->assertNotFound();
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $rest = $this->getJson('/api/v1/lines/rfn-722000/structures')->json();
        $gql = $this->postJson('/graphql', ['query' => '{ lines(first: 10) { data { slug structures {
            summary { count by_kind { tunnel viaduct bridge } tunnel_length_m longest { name kind length_m } }
            items { id kind name length_m pk_start_label pk_end_label built { date precision } opened { date precision } heritage merimee_ref current era_years }
            attribution provenance { provider } } } } }'])->assertOk()->assertJsonMissingPath('errors')->json('data.lines.data');
        $bySlug = array_column($gql, 'structures', 'slug');

        $this->assertSame($rest['summary'], $bySlug['rfn-722000']['summary']);
        $this->assertSame(array_column($rest['items'], 'name'), array_column($bySlug['rfn-722000']['items'], 'name'));
        $this->assertSame($rest['items'][1]['opened'], $bySlug['rfn-722000']['items'][1]['opened']);
        $this->assertSame(0, $bySlug['rfn-001000']['summary']['count']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_structures', 'arguments' => ['line' => '722000', 'kind' => 'tunnel']]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) ($result['isError'] ?? false));
        $mcp = json_decode($result['content'][0]['text'], true);
        $this->assertSame($rest['summary'], $mcp['summary']);
        $this->assertSame(['tunnel', 'tunnel'], array_column($mcp['items'], 'kind'));
        $this->assertStringContainsString('Wikidata', $mcp['provenance']['mention']);
    }

    public function test_the_line_sheet_receives_the_structures(): void
    {
        $this->get('/lines/rfn-722000')->assertOk()->assertInertia(fn ($page) => $page
            ->where('structures.summary.count', 3)
            ->where('structures.items.1.name', 'viaduc de Garabit')
            ->missing('structures.items.0.sources'));
    }
}
