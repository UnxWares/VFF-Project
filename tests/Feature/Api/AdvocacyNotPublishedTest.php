<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Le bloc de soutien du site à une ligne (`lines.advocacy` : campagne de réouverture, appel à agir)
 * est un engagement éditorial, pas une donnée : aucune interface programmable ne le publie (REST,
 * GraphQL, serveur MCP, spécification OpenAPI, schéma GraphQL). Il ne vit que sur la page de la ligne.
 */
class AdvocacyNotPublishedTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const TITLE = 'Rouvrons la ligne ensemble';

    private const MESSAGE = 'Signez la pétition de soutien à la réouverture';

    private const CTA_URL = 'https://exemple.test/petition-soutien';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->buildRailNetwork();
        $this->line->update([
            'description' => 'Une ligne.',
            'advocacy' => ['kind' => 'reopening', 'title' => self::TITLE, 'message' => self::MESSAGE, 'cta_label' => 'Soutenir', 'cta_url' => self::CTA_URL],
        ]);
    }

    public function test_the_web_page_still_shows_the_block(): void
    {
        $this->get('/lines/'.$this->line->slug)->assertOk()->assertSee(self::TITLE, false);
    }

    public function test_rest_responses_never_carry_the_block(): void
    {
        foreach (["/api/v1/lines/{$this->line->slug}", "/api/v1/lines/{$this->line->slug}?geometry=true", '/api/v1/lines', '/api/v1/lines?q=Paris',
            '/api/v1/search?q=Paris', "/api/v1/lines/{$this->line->slug}/history", "/api/v1/lines/{$this->line->slug}/segments"] as $uri) {
            $body = $this->getJson($uri)->assertOk()->getContent();
            $this->assertNoTrace($body, $uri);
        }
        $this->getJson("/api/v1/lines/{$this->line->slug}")->assertJsonMissingPath('data.advocacy');
    }

    public function test_graphql_does_not_expose_the_block(): void
    {
        $response = $this->graphQL('query ($slug: String!) { line(slug: $slug) { slug sheet { slug is_threatened description } } }', ['slug' => $this->line->slug])
            ->assertJsonMissingPath('errors');
        $this->assertNoTrace($response->getContent(), 'GraphQL');

        $this->graphQL('query ($slug: String!) { line(slug: $slug) { sheet { advocacy { title } } } }', ['slug' => $this->line->slug])
            ->assertJsonPath('errors.0.message', 'Cannot query field "advocacy" on type "LineSheet".');
        $this->assertNoTrace((string) file_get_contents(base_path('graphql/schema.graphql')), 'schema.graphql');
    }

    public function test_the_mcp_server_does_not_expose_the_block(): void
    {
        $call = fn (string $tool, array $arguments): string => $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->getContent();

        foreach ([['get_line', ['line' => $this->line->slug]], ['list_lines', []], ['search', ['query' => 'Paris']]] as [$tool, $arguments]) {
            $this->assertNoTrace($call($tool, $arguments), 'MCP '.$tool);
        }
    }

    public function test_the_openapi_specification_does_not_describe_the_block(): void
    {
        $this->assertNoTrace($this->get('/docs/api.json')->assertOk()->getContent(), '/docs/api.json');
    }

    /** Ni le contenu du bloc ni sa clé ne doivent apparaître. */
    private function assertNoTrace(string $body, string $where): void
    {
        foreach ([self::TITLE, self::MESSAGE, self::CTA_URL, 'advocacy', 'cta_label', 'cta_url', 'LineAdvocacy'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "« {$needle} » ne doit pas figurer dans {$where}");
        }
    }
}
