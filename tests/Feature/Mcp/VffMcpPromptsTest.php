<?php

namespace Tests\Feature\Mcp;

use App\Models\Accident;
use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Serveur MCP : invites (prompts/list, prompts/get, completion/complete), outil list_departments et
 * filtres `region` / `station` des outils de liste, par le transport HTTP (POST /mcp).
 */
class VffMcpPromptsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->create(['year' => 1930, 'label' => 'Années 1930']);
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);
        Line::factory()->create(['slug' => 'rfn-447000', 'code' => '447000', 'name' => 'Ligne de Morlaix à Roscoff', 'departements' => json_encode(['Finistère'])]);
        $brest = Station::factory()->create(['slug' => 'brest', 'name' => 'Brest', 'city' => 'Brest', 'department_code' => '29', 'geom' => Point::makeGeodetic(48.388, -4.479)]);
        Station::factory()->create(['slug' => 'morlaix', 'name' => 'Morlaix', 'city' => 'Morlaix', 'department_code' => '29', 'geom' => Point::makeGeodetic(48.578, -3.832)]);
        Station::factory()->create(['slug' => 'saint-pol', 'name' => 'Saint-Pol', 'city' => 'Saint-Pol-de-Léon', 'department_code' => '29', 'geom' => Point::makeGeodetic(48.68, -3.98)]);
        Station::factory()->create(['slug' => 'saint-pol-2', 'name' => 'Saint-Pol', 'city' => 'Saint-Pol-sur-Ternoise', 'department_code' => '62', 'geom' => Point::makeGeodetic(50.38, 2.33)]);
        Accident::factory()->create(['slug' => '1950-01-01-brest', 'department_code' => '29', 'nearest_station_id' => $brest->id]);
        Accident::factory()->create(['slug' => '1960-01-01-arras', 'department_code' => '62']);
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

    /** @param  array<string, string>  $arguments */
    private function promptText(string $name, array $arguments): string
    {
        $messages = $this->rpc('prompts/get', ['name' => $name, 'arguments' => (object) $arguments])->assertOk()
            ->assertJsonMissingPath('error')->json('result.messages');
        $this->assertCount(1, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('text', $messages[0]['content']['type']);

        return $messages[0]['content']['text'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments])->assertOk()->json('result');
        $this->assertFalse($result['isError'], $result['content'][0]['text']);

        return json_decode($result['content'][0]['text'], true);
    }

    public function test_server_declares_prompts_and_completions(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']])
            ->assertOk()
            ->assertJsonPath('result.capabilities.prompts.listChanged', false)
            ->assertJsonPath('result.capabilities.completions', []);

        $prompts = collect((array) $this->rpc('prompts/list')->assertOk()->json('result.prompts'))->keyBy('name');
        $this->assertSame(['present_line', 'compare_region_eras', 'station_story'], $prompts->keys()->all());
        foreach ($prompts as $name => $prompt) {
            $this->assertNotEmpty($prompt['title'], $name);
            $this->assertGreaterThan(150, mb_strlen($prompt['description']), "{$name} : description détaillée");
            $this->assertSame('lang', end($prompt['arguments'])['name']);
            foreach ($prompt['arguments'] as $argument) {
                $this->assertNotEmpty($argument['description'], "{$name}.{$argument['name']}");
            }
        }
        $required = fn (string $name): array => array_column(array_filter($prompts[$name]['arguments'], fn ($a) => $a['required']), 'name');
        $this->assertSame(['line'], $required('present_line'));
        $this->assertSame(['region', 'from', 'to'], $required('compare_region_eras'));
        $this->assertSame(['station'], $required('station_story'));
    }

    public function test_present_line_names_the_line_found_and_its_tools(): void
    {
        $text = $this->promptText('present_line', ['line' => '447000']);
        $this->assertStringContainsString('« Ligne de Morlaix à Roscoff »', $text);
        $this->assertStringContainsString('identifiant rfn-447000', $text);
        $this->assertStringContainsString('vff://fr/lines/rfn-447000', $text);
        $this->assertStringContainsString('get_line', $text);
        $this->assertStringContainsString('Répondez en français.', $text);

        $this->assertStringContainsString('vff://en/lines/rfn-447000', $this->promptText('present_line', ['line' => 'ligne de morlaix a roscoff', 'lang' => 'en']));
        $this->assertStringContainsString('Antworten Sie auf Deutsch.', $this->promptText('present_line', ['line' => 'rfn-447000', 'lang' => 'de']));

        // Ligne inconnue : le message demande de la chercher, sans rien inventer.
        $unknown = $this->promptText('present_line', ['line' => 'Paris - Lyon']);
        $this->assertStringContainsString('search {"query": "Paris - Lyon", "kinds": ["lines"]}', $unknown);
        $this->assertStringNotContainsString('rfn-', $unknown);
    }

    public function test_station_story_resolves_a_unique_name_and_searches_homonyms(): void
    {
        $text = $this->promptText('station_story', ['station' => 'brest']);
        $this->assertStringContainsString('« Brest » (Brest, identifiant brest)', $text);
        foreach (['get_station', 'accidents {"station"', 'media {"station"'] as $tool) {
            $this->assertStringContainsString($tool, $text);
        }
        $this->assertStringContainsString('identifiant morlaix', $this->promptText('station_story', ['station' => 'MORLAIX']));
        $this->assertStringContainsString('search {"query": "Saint-Pol", "kinds": ["stations"]}', $this->promptText('station_story', ['station' => 'Saint-Pol']));
        $this->assertStringContainsString('Tell the history of the station "Brest"', $this->promptText('station_story', ['station' => 'brest', 'lang' => 'en']));
    }

    public function test_compare_region_eras_resolves_region_and_eras(): void
    {
        $text = $this->promptText('compare_region_eras', ['region' => 'bretagne', 'from' => '2026', 'to' => '1937']);
        $this->assertStringContainsString('région Bretagne (code INSEE 53) en 1930 et en 2030', $text);
        $this->assertStringContainsString('Finistère (29)', $text);
        $this->assertStringContainsString('list_lines {"region": "53"', $text);
        $this->assertStringContainsString('network_events {"region": "53", "from": "1931", "to": "'.date('Y').'"}', $text);
        $this->assertStringContainsString('compare_eras {"from": 1930, "to": 2030}', $text);
        $this->assertStringContainsString('réseau d\'aujourd\'hui', $text);

        $this->assertStringContainsString('Île-de-France', $this->promptText('compare_region_eras', ['region' => '11', 'from' => '1930', 'to' => '2030', 'lang' => 'de']));
        $this->assertStringContainsString('Provence-Alpes-Côte d\'Azur', $this->promptText('compare_region_eras', ['region' => 'provence alpes cote d’azur', 'from' => '1930', 'to' => '2030']));
    }

    public function test_prompt_arguments_are_validated(): void
    {
        $error = fn (string $name, array $arguments) => $this->rpc('prompts/get', ['name' => $name, 'arguments' => (object) $arguments])
            ->assertJsonPath('error.code', -32602)->json('error.message');

        $this->assertStringContainsString('line', $error('present_line', []));
        $this->assertStringContainsString('lang', $error('present_line', ['line' => '447000', 'lang' => 'es']));
        $this->assertStringContainsString('Unknown region', $error('compare_region_eras', ['region' => 'Atlantide', 'from' => '1930', 'to' => '2030']));
        $this->assertStringContainsString('same map era', $error('compare_region_eras', ['region' => '53', 'from' => '1931', 'to' => '1935']));
        $this->assertStringContainsString('from', $error('compare_region_eras', ['region' => '53', 'from' => '1700', 'to' => '2030']));
        $this->rpc('prompts/get', ['name' => 'inconnue'])->assertJsonPath('error.code', -32602);
    }

    public function test_prompt_arguments_are_completed(): void
    {
        $complete = fn (string $prompt, string $argument, string $value): array => $this->rpc('completion/complete', [
            'ref' => ['type' => 'ref/prompt', 'name' => $prompt],
            'argument' => ['name' => $argument, 'value' => $value],
        ])->assertOk()->json('result.completion.values');

        $this->assertSame(['Bretagne'], $complete('compare_region_eras', 'region', 'Bre'));
        $this->assertSame(['53'], $complete('compare_region_eras', 'region', '53'));
        $this->assertSame(['en'], $complete('present_line', 'lang', 'e'));
        $this->assertSame(['rfn-447000'], $complete('present_line', 'line', 'roscoff'));
        $this->assertSame(['saint-pol', 'saint-pol-2'], $complete('station_story', 'station', 'saint-p'));
        $this->assertSame([], $complete('station_story', 'station', 'b'), 'deux caractères au moins');
    }

    public function test_list_departments_tool_and_region_filters(): void
    {
        $data = $this->tool('list_departments', ['region' => '53']);
        $this->assertCount(1, $data['regions']);
        $this->assertSame(['code' => '53', 'name' => 'Bretagne'], array_intersect_key($data['regions'][0], ['code' => 1, 'name' => 1]));
        $this->assertSame(['22', '29', '35', '56'], array_column($data['regions'][0]['departments'], 'code'));
        $this->assertSame(['lines' => 1, 'stations' => 3, 'accidents' => 1], $data['regions'][0]['counts']);
        $this->assertContains('insee', array_column($data['attribution']['sources'], 'provider'));
        $this->assertCount(18, $this->tool('list_departments')['regions']);

        $this->assertSame(['rfn-447000'], array_column($this->tool('list_lines', ['region' => '53'])['lines'], 'slug'));
        $stations = $this->tool('list_stations', ['region' => '53', 'facets' => true]);
        $this->assertSame(['brest', 'morlaix', 'saint-pol'], array_column($stations['stations'], 'slug'));
        $this->assertSame(['29' => 3], $stations['department_facets'], 'filtre region gardé, department retiré');
        $this->assertSame(['1950-01-01-brest'], array_column($this->tool('accidents', ['station' => 'brest'])['accidents'], 'slug'));
        $this->assertSame(['1960-01-01-arras'], array_column($this->tool('accidents', ['region' => '32'])['accidents'], 'slug'));
        $this->assertSame([], $this->tool('network_events', ['region' => '84'])['events']);
    }

    public function test_department_and_region_enums_are_strings_in_the_tool_schemas(): void
    {
        $tools = collect((array) $this->rpc('tools/list')->assertOk()->json('result.tools'))->keyBy('name');
        foreach (['list_lines', 'list_stations'] as $name) {
            $this->assertContainsOnlyString($tools[$name]['inputSchema']['properties']['department']['enum'], $name);
            $this->assertContains('29', $tools[$name]['inputSchema']['properties']['department']['enum']);
        }
        foreach (['list_lines', 'list_stations', 'network_events', 'accidents', 'list_departments'] as $name) {
            $this->assertContainsOnlyString($tools[$name]['inputSchema']['properties']['region']['enum'], $name);
            $this->assertCount(18, $tools[$name]['inputSchema']['properties']['region']['enum']);
        }
    }
}
