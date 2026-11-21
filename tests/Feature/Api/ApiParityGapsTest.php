<?php

namespace Tests\Feature\Api;

use App\Models\Accident;
use App\Models\LineMaterial;
use App\Models\Material;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Champs et filtres qui n'existaient que dans une ou deux des trois interfaces (REST, GraphQL, serveur
 * MCP) et que les trois donnent maintenant : `pk_label` des gares d'une ligne, `end` des liens de
 * matériel, `provenance`, `imported_at`, `kind_label` et `department_name` des accidents, compteurs
 * des médias, filtre `featured` du serveur MCP.
 */
class ApiParityGapsTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /** @return array<string, mixed> */
    private function mcp(string $tool, array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse($result['isError'] ?? false, $result['content'][0]['text'] ?? '');

        return json_decode($result['content'][0]['text'], true);
    }

    public function test_line_stops_carry_the_same_pk_label_in_rest_and_graphql(): void
    {
        $rest = $this->getJson("/api/v1/lines/{$this->line->slug}/stops")->assertOk()->json('stops');
        $graphql = $this->graphQL('{ line(slug: "'.$this->line->slug.'") { stops { position pk_m pk_label } } }')
            ->assertGraphQLErrorFree()->json('data.line.stops');

        $this->assertSame(['0+000', '17+250'], array_column($graphql, 'pk_label'));
        $this->assertSame(array_column($rest, 'pk_label'), array_column($graphql, 'pk_label'));
    }

    public function test_line_material_links_carry_their_end_of_service_in_rest_graphql_and_mcp(): void
    {
        $material = Material::factory()->create(['slug' => 'bb-15000', 'name' => 'BB 15000', 'type' => 'locomotive_electrique', 'in_service_from' => 1971, 'in_service_to' => null]);
        LineMaterial::create([
            'line_id' => $this->line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'high',
            'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070',
            'evidence' => [['kind' => 'wikipedia_line_article', 'article' => 'Ligne', 'revid' => 239088070, 'section' => 'Matériel roulant', 'quote' => 'Des BB 15000.', 'url' => 'https://fr.wikipedia.org/w/index.php?oldid=239088070']],
        ]);

        $rest = $this->getJson("/api/v1/lines/{$this->line->slug}/materials")->assertOk()->json('data.0.end');
        $this->assertContains($rest['kind'], ['line', 'series_in_service', 'series_withdrawn', 'unknown']);

        $graphql = $this->graphQL('{ line(slug: "'.$this->line->slug.'") { material_links { material { slug } end { kind year since source source_url } } sheet { materials { slug end { kind year } } } } }')
            ->assertGraphQLErrorFree()->json('data.line');
        $this->assertEqualsCanonicalizing($rest, $graphql['material_links'][0]['end']);
        $this->assertSame($rest['kind'], $graphql['sheet']['materials'][0]['end']['kind']);

        $mcp = $this->mcp('line_materials', ['line' => $this->line->slug]);
        $this->assertSame($rest['kind'], $mcp['materials'][0]['end']['kind'] ?? null);
    }

    public function test_accident_fields_of_the_rest_sheet_are_in_graphql_and_mcp(): void
    {
        $accident = Accident::factory()->on('1892-08-21')->create([
            'slug' => '1892-08-21-saint-chinian', 'kind' => 'derailment', 'commune_name' => 'Saint-Chinian', 'department_code' => '34',
            'provenance' => ['date' => 'wikipedia', 'kind' => 'context', 'name' => 'vff'],
        ]);

        $rest = $this->getJson("/api/v1/accidents/{$accident->slug}")->assertOk()->json();
        $rest = $rest['data'] ?? $rest;
        $this->assertSame('context', $rest['provenance']['kind']);

        $graphql = $this->graphQL('{ accident(slug: "'.$accident->slug.'") { provenance imported_at kind_label department_name en: kind_label(lang: "en") } }')
            ->assertGraphQLErrorFree()->json('data.accident');
        $this->assertSame($rest['provenance'], $graphql['provenance']);
        $this->assertSame($rest['kind_label'], $graphql['kind_label']);
        $this->assertSame($rest['place']['department'], $graphql['department_name']);
        $this->assertSame('Hérault', $graphql['department_name']);
        $this->assertNotSame($graphql['kind_label'], $graphql['en']);
        $this->assertNotNull($graphql['imported_at']);
        $this->assertSame(strtotime($rest['imported_at']), strtotime($graphql['imported_at']));

        $mcp = $this->mcp('get_accident', ['accident' => $accident->slug]);
        $mcp = $mcp['accident'] ?? $mcp;
        $this->assertSame($rest['provenance'], $mcp['provenance'] ?? null);
    }

    public function test_media_counters_and_the_featured_filter_are_the_same_in_the_three_apis(): void
    {
        $on = ['mediable_type' => get_class($this->line), 'mediable_id' => $this->line->id];
        Media::factory()->create($on + ['type' => 'photo', 'license' => 'CC BY-SA 4.0', 'author' => 'Spendeau', 'is_featured' => true]);
        Media::factory()->create($on + ['type' => 'photo', 'license' => 'CC BY-SA 4.0', 'author' => 'Spendeau', 'is_featured' => false]);
        Media::factory()->create($on + ['type' => 'postcard', 'license' => null, 'author' => null, 'is_featured' => false]);

        $rest = $this->getJson('/api/v1/media?facets=true')->assertOk()->json('meta.facets');
        $graphql = $this->graphQL('{ mediaFacets { total types { code count } licenses { license count } authors { author count } } }')
            ->assertGraphQLErrorFree()->json('data.mediaFacets');
        $this->assertSame($rest['total'], $graphql['total']);
        $this->assertSame($rest['types'], array_column($graphql['types'], 'count', 'code'));
        $this->assertSame($rest['licenses'], $graphql['licenses']);
        $this->assertSame($rest['authors'], $graphql['authors']);
        $mcp = $this->mcp('media', ['facets' => true]);
        $this->assertSame($rest, $mcp['facets']);
        $this->assertArrayNotHasKey('facets', $this->mcp('media', []));

        // Les compteurs suivent les filtres, dans les trois interfaces.
        $this->assertSame(1, $this->getJson('/api/v1/media?facets=true&type=postcard')->json('meta.facets.total'));
        $this->assertSame(1, $this->graphQL('{ mediaFacets(filter: {type: "postcard"}) { total } }')->json('data.mediaFacets.total'));
        $this->assertSame(1, $this->mcp('media', ['facets' => true, 'type' => 'postcard'])['facets']['total']);

        // `featured` : REST, GraphQL et MCP rendent les mêmes médias.
        $restIds = array_column($this->getJson('/api/v1/media?featured=true')->assertOk()->json('data'), 'id');
        $this->assertCount(1, $restIds);
        $graphqlIds = array_column($this->graphQL('{ media(first: 10, filter: {featured: true}) { edges { node { id } } } }')->json('data.media.edges'), 'node');
        $this->assertSame($restIds, array_map('intval', array_column($graphqlIds, 'id')));
        $mcpIds = array_column($this->mcp('media', ['featured' => true])['media'], 'id');
        $this->assertSame($restIds, $mcpIds);
        $this->assertCount(2, $this->mcp('media', ['featured' => false])['media']);
    }
}
