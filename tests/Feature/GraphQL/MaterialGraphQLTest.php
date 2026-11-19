<?php

namespace Tests\Feature\GraphQL;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * GraphQL du matériel roulant : mêmes valeurs que l'API REST (MaterialResource), filtres
 * partagés (MaterialQuery), liens avec preuve depuis la série et depuis la ligne.
 */
class MaterialGraphQLTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Material $bb15000;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bb15000 = Material::factory()->fromWikidata()->create([
            'slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'builder' => 'Alstom',
            'in_service_from' => 1971, 'in_service_to' => null, 'max_speed_kmh' => 160,
            'provenance' => ['power_kw' => ['source' => 'wikidata', 'ref' => 'Q746572#P2109', 'original' => ['amount' => 6280.0, 'unit' => 'Q160857']]],
        ]);
        Material::factory()->create(['slug' => 'x-3800', 'name' => 'X 3800', 'type' => 'autorail', 'max_speed_kmh' => 120, 'in_service_from' => 1950, 'in_service_to' => 1988]);
        $this->line = Line::factory()->create(['slug' => 'rfn-67000', 'name' => 'Ligne de Lunéville à St-Dié']);
        LineMaterial::create([
            'line_id' => $this->line->id, 'material_id' => $this->bb15000->id, 'source' => 'wikipedia', 'confidence' => 'high',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070',
            'evidence' => [['kind' => 'wikipedia_line_article', 'article' => 'Ligne de Lunéville à Saint-Dié', 'revid' => 239088070, 'section' => 'Trafic > Matériel roulant', 'quote' => 'Rames Corail tractées par des BB 15000.', 'url' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070']],
        ]);
    }

    public function test_materials_can_be_filtered_and_sorted_like_the_rest_api(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            { materials(first: 10, filter: {sort: "-max_speed_kmh", in_service_at: 1990}) {
                pageInfo { hasNextPage endCursor }
                edges { node { slug type_label lines_count max_speed_kmh } }
            } }')->assertGraphQLErrorFree();

        $this->assertFalse($response->json('data.materials.pageInfo.hasNextPage'));
        $this->assertSame([['node' => ['slug' => 'bb-15000', 'type_label' => 'Locomotive électrique', 'lines_count' => 1, 'max_speed_kmh' => 160]]], $response->json('data.materials.edges'));

        $this->graphQL('{ materials(first: 10, type: "autorail") { edges { node { slug } } } }')
            ->assertJsonPath('data.materials.edges', [['node' => ['slug' => 'x-3800']]]);

        // Pagination par curseur : endCursor repassé dans after donne la suite, sans doublon.
        $first = $this->graphQL('{ materials(first: 1) { pageInfo { hasNextPage endCursor } edges { node { slug } } } }');
        $this->assertTrue($first->json('data.materials.pageInfo.hasNextPage'));
        $next = $this->graphQL('query ($after: String) { materials(first: 1, after: $after) { edges { node { slug } } } }',
            ['after' => $first->json('data.materials.pageInfo.endCursor')]);
        $this->assertNotSame($first->json('data.materials.edges.0.node.slug'), $next->json('data.materials.edges.0.node.slug'));
    }

    public function test_an_invalid_filter_is_a_validation_error(): void
    {
        $this->graphQL('{ materials(first: 10, filter: {sort: "prix"}) { edges { node { slug } } } }')
            ->assertGraphQLValidationKeys(['sort']);
    }

    public function test_a_material_exposes_its_sourced_characteristics_and_line_links(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            { material(slug: "bb-15000") {
                name designations builder builders { name wikidata_id units }
                operators { name wikidata_id from_year to_year }
                electrification { label } gauges { label mm } gauge_mm wheel_arrangement fleet_numbers
                image { file license attribution rights_status share_alike }
                wikidata_url wikipedia_fr_url commons_category_url lines_count
                provenance { provider license license_status }
                field_sources { field source reference url original_amount original_unit license }
                lines { slug }
                line_links { confidence source line { slug } evidence { kind section quote revid url } provenance { provider } }
            } }')->assertGraphQLErrorFree();

        $material = $response->json('data.material');
        $this->assertSame('BB 15000', $material['name']);
        $this->assertSame(['SNCF BB 15000'], $material['designations']);
        $this->assertSame([['name' => 'Alstom', 'wikidata_id' => 'Q309084', 'units' => null]], $material['builders']);
        $this->assertSame(1435, $material['gauge_mm']);
        $this->assertSame('CC BY-SA 4.0', $material['image']['license']);
        $this->assertSame('https://fr.wikipedia.org/wiki/BB_15000', $material['wikipedia_fr_url']);
        $this->assertSame(['provider' => 'wikidata', 'license' => 'CC0 1.0', 'license_status' => 'verified'], $material['provenance']);
        $this->assertSame([[
            'field' => 'power_kw', 'source' => 'wikidata', 'reference' => 'Q746572#P2109', 'url' => 'https://www.wikidata.org/wiki/Q746572',
            'original_amount' => 6280, 'original_unit' => 'Q160857', 'license' => 'CC0 1.0',
        ]], $material['field_sources']);
        $this->assertSame([['slug' => 'rfn-67000']], $material['lines']);
        $this->assertSame('high', $material['line_links'][0]['confidence']);
        $this->assertSame('Rames Corail tractées par des BB 15000.', $material['line_links'][0]['evidence'][0]['quote']);
        $this->assertSame('wikipedia', $material['line_links'][0]['provenance']['provider']);
    }

    public function test_a_line_exposes_its_material_links(): void
    {
        $this->graphQL('{ line(slug: "rfn-67000") { materials { slug } material_links { material { slug lines_count } evidence { kind } } } }')
            ->assertGraphQLErrorFree()
            ->assertJsonPath('data.line.materials', [['slug' => 'bb-15000']])
            ->assertJsonPath('data.line.material_links', [['material' => ['slug' => 'bb-15000', 'lines_count' => 1], 'evidence' => [['kind' => 'wikipedia_line_article']]]]);
    }
}
