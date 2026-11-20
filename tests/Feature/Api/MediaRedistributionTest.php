<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Une image que le site montre avec une simple autorisation de son auteur (`rights_status` = `permission`)
 * n'est jamais diffusée par l'API : ni `GET /api/v1/media` (liste, compteurs, fiche), ni GraphQL (`media`,
 * `mediaItem`, `Line.media`), ni l'outil MCP `media`. Même règle que les histoires d'événements.
 */
class MediaRedistributionTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private int $open;

    private int $permission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $this->open = $this->media('open_license', 'CC BY-SA 4.0', 1);
        $this->permission = $this->media('permission', null, 2);
    }

    private function media(string $rights, ?string $license, int $n): int
    {
        return DB::table('media')->insertGetId([
            'mediable_type' => Line::class, 'mediable_id' => $this->line->id, 'provider' => 'commons', 'external_id' => "file-{$n}",
            'type' => 'photo', 'title' => "Photo {$n}", 'license' => $license, 'rights_status' => $rights,
            'attribution' => "Auteur {$n}", 'source_url' => "https://commons.wikimedia.org/wiki/File:{$n}.jpg",
            'thumb_url' => "https://upload.wikimedia.org/thumb-{$n}.jpg", 'depicted_year' => 1950,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_rest_api_never_serves_an_image_shown_with_a_mere_permission(): void
    {
        $list = $this->getJson('/api/v1/media?facets=true')->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');
        $this->assertSame([$this->open], array_column($list->json('data'), 'id'));
        $this->assertSame(1, $list->json('meta.facets.total'));

        $this->getJson("/api/v1/media?line={$this->line->slug}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/media/{$this->open}")->assertOk();
        $this->getJson("/api/v1/media/{$this->permission}")->assertNotFound();
        $this->getJson('/api/v1/media?rights_status=permission')->assertUnprocessable()->assertJsonValidationErrors('rights_status');
    }

    public function test_graphql_and_mcp_never_serve_it_either(): void
    {
        $gql = $this->graphQL('query($line: String!, $id: ID!, $other: ID!) {
            media { edges { node { id } } }
            mine: mediaItem(id: $id) { id }
            other: mediaItem(id: $other) { id }
            line(slug: $line) { media { edges { node { id } } } }
        }', ['line' => $this->line->slug, 'id' => $this->permission, 'other' => $this->open])->assertOk();
        $this->assertNull($gql->json('errors'));
        $this->assertSame([(string) $this->open], array_column(array_column($gql->json('data.media.edges'), 'node'), 'id'));
        $this->assertNull($gql->json('data.mine'));
        $this->assertSame((string) $this->open, $gql->json('data.other.id'));
        $this->assertSame([(string) $this->open], array_column(array_column($gql->json('data.line.media.edges'), 'node'), 'id'));

        $text = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'media', 'arguments' => ['line' => $this->line->slug]]])
            ->assertOk()->json('result.content.0.text');
        $data = json_decode((string) $text, true);
        $this->assertIsArray($data);
        $this->assertSame([$this->open], array_column($data['media'], 'id'));
    }

    public function test_the_site_gallery_keeps_the_image(): void
    {
        // Le site montre l'image (la galerie de la fiche lit son propre point d'accès) : seule la redistribution
        // par l'API publique est exclue.
        /** @var TestResponse<Response> $gallery */
        $gallery = $this->getJson("/api/v1/lines/{$this->line->slug}/media")->assertOk();
        $this->assertEqualsCanonicalizing([$this->open, $this->permission], array_column($gallery->json('data'), 'id'));
    }
}
