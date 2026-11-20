<?php

namespace Tests\Feature\Mcp;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Outils MCP du matériel roulant (materials, get_material) par le transport HTTP réel : mêmes
 * filtres et mêmes fiches que l'API REST (MaterialQuery, MaterialResource, MaterialDetailResource).
 */
class VffMcpMaterialToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $bb = Material::factory()->fromWikidata()->create([
            'slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'builder' => 'Alstom',
            'in_service_from' => 1971, 'max_speed_kmh' => 160,
        ]);
        Material::factory()->create(['slug' => 'x-3800', 'name' => 'X 3800', 'type' => 'autorail', 'in_service_from' => 1950,
            'in_service_to' => 1988, 'max_speed_kmh' => null, 'designations' => ['Autorail Picasso']]);
        $line = Line::factory()->create(['slug' => 'rfn-67000', 'code' => '067000', 'name' => 'Ligne de Lunéville à St-Dié']);
        LineMaterial::create([
            'line_id' => $line->id, 'material_id' => $bb->id, 'source' => 'wikipedia', 'confidence' => 'high',
            'evidence' => [['url' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070', 'kind' => 'wikipedia_line_article',
                'quote' => 'Rames Corail tractées par des BB 15000.', 'revid' => 239088070, 'article' => 'Ligne de Lunéville à Saint-Dié', 'section' => 'Matériel roulant']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [json_decode($text, true) ?? ['raw' => $text], (bool) $result['isError']];
    }

    public function test_line_materials_lists_a_series_once_with_its_contributed_link_as_is(): void
    {
        $bb = Material::query()->where('slug', 'bb-15000')->firstOrFail();
        $line = Line::query()->where('slug', 'rfn-67000')->firstOrFail();
        $contributed = LineMaterial::create([
            'line_id' => $line->id, 'material_id' => $bb->id, 'source' => 'contrib', 'confidence' => 'high', 'source_url' => null,
            'evidence' => [['kind' => 'contribution', 'contribution' => 'contribution:7', 'title' => 'La Vie du Rail, n° 640', 'source_kind' => 'print', 'quote' => 'Les BB 15000 en tête des Corail.', 'doubt' => null, 'url' => null]],
        ]);
        LineMaterialPeriod::query()->create(['line_material_id' => $contributed->id, 'fingerprint' => 'contribution:7', 'start_date' => '1975-01-01', 'start_precision' => 'year',
            'confidence' => 'medium', 'origin' => 'contribution', 'source' => 'contrib', 'source_title' => 'La Vie du Rail, n° 640', 'source_url' => null]);

        [$data, $error] = $this->tool('line_materials', ['line' => 'rfn-67000']);

        $this->assertFalse($error);
        $this->assertSame(1, $data['total'], 'une série reliée par deux sources compte une fois');
        $this->assertSame(1, $data['dated']);
        $this->assertSame(['bb-15000'], array_column(array_column($data['materials'], 'material'), 'slug'));
        $this->assertEqualsCanonicalizing(['wikipedia', 'contrib'], array_column($data['materials'][0]['links'], 'source'));
        $this->assertSame('contribution', $data['materials'][0]['periods'][0]['origin'], 'période du lien contribué, à côté de celles de l’import');
        $this->assertStringNotContainsString('Les BB 15000 en tête', json_encode($data, JSON_UNESCAPED_UNICODE), 'aucune phrase citée');

        [$sheet] = $this->tool('get_material', ['material' => 'bb-15000']);
        $this->assertSame(1, $sheet['material']['lines_count']);
        $this->assertContains('contrib', array_column($sheet['material']['lines'], 'source'));
        $this->assertContains('vff', array_column($sheet['attribution']['sources'], 'provider'));
    }

    public function test_materials_filter_by_bimode_category_service_state_and_scope(): void
    {
        Material::factory()->fromWikidata()->inService(12, '2026-07-13')->attested()->create(['slug' => 'db-407', 'name' => 'DB 407', 'type' => 'bimode']);

        [$data, $error] = $this->tool('materials', ['type' => 'bimode', 'service_status' => 'in_service', 'scope' => 'attested']);
        $this->assertFalse($error);
        $this->assertSame(['db-407'], array_column($data['materials'], 'slug'));
        $this->assertSame('in_service', $data['materials'][0]['service']['status']);
        $this->assertSame('attested', $data['materials'][0]['scope']);

        [, $error] = $this->tool('materials', ['service_status' => 'en_service']);
        $this->assertTrue($error, 'valeur inconnue refusée');
    }

    public function test_materials_filtered_sorted_and_paginated(): void
    {
        [$data, $error] = $this->tool('materials', ['q' => 'picasso']);
        $this->assertFalse($error);
        $this->assertSame(['x-3800'], array_column($data['materials'], 'slug'));

        [$data] = $this->tool('materials', ['line' => 'rfn-67000']);
        $this->assertSame(['bb-15000'], array_column($data['materials'], 'slug'));
        $this->assertSame('CC0 1.0', $data['materials'][0]['provenance']['license']);
        $this->assertContains('wikidata', array_column($data['attribution']['sources'], 'provider'));

        [$first] = $this->tool('materials', ['sort' => '-max_speed_kmh', 'limit' => 1]);
        $this->assertTrue($first['has_more']);
        [$data] = $this->tool('materials', ['sort' => '-max_speed_kmh', 'limit' => 1, 'cursor' => $first['next_cursor']]);
        $this->assertSame(['x-3800'], array_column($data['materials'], 'slug'), 'vitesse inconnue en dernier');
        $this->assertFalse($data['has_more']);
        $this->assertNull($data['next_cursor']);
    }

    public function test_material_sheet_with_lines_and_evidence(): void
    {
        [$data, $error] = $this->tool('get_material', ['material' => 'bb-15000']);
        $this->assertFalse($error);
        $this->assertSame('BB 15000', $data['material']['name']);
        $this->assertSame('rfn-67000', $data['material']['lines'][0]['line']['slug']);
        // Preuve sans la phrase de l'article (décision Q22) : le permalien de la révision lue suffit.
        $evidence = $data['material']['lines'][0]['evidence'][0];
        $this->assertSame('https://fr.wikipedia.org/w/index.php?oldid=239088070', $evidence['url']);
        $this->assertSame(['kind', 'article', 'revid', 'section', 'url'], array_keys($evidence));
        $this->assertStringNotContainsString('Rames Corail', json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->assertSame('https://vff-project.org/materials/bb-15000', $data['vff_url']);

        [$data, $error] = $this->tool('get_material', ['material' => 'bb-15001']);
        $this->assertTrue($error);
        $this->assertSame('material_not_found', $data['error']);
    }

    public function test_material_tools_validate_their_arguments(): void
    {
        foreach ([['materials', ['type' => 'fusee']], ['materials', ['limit' => 51]], ['materials', ['to_year' => 1900, 'from_year' => 1950]],
            ['get_material', []], ['get_material', ['material' => 'bb-15000', 'extra' => 1]]] as [$tool, $arguments]) {
            [, $error] = $this->tool($tool, $arguments);
            $this->assertTrue($error, $tool.' '.json_encode($arguments));
        }
    }
}
