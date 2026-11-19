<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Ce que montrent la page /credits/media et l'accueil, par l'API : compteurs des médias
 * (`GET /api/v1/media?facets=true`) et médias mis en avant (`featured`, REST et GraphQL).
 */
class MediaFacetsApiTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        $on = ['mediable_type' => get_class($this->line), 'mediable_id' => $this->line->id];
        Media::factory()->create($on + ['type' => 'photo', 'license' => 'CC BY-SA 4.0', 'author' => 'Spendeau', 'is_featured' => true]);
        Media::factory()->create($on + ['type' => 'photo', 'license' => 'CC BY-SA 4.0', 'author' => 'Spendeau', 'is_featured' => false]);
        Media::factory()->create($on + ['type' => 'postcard', 'license' => null, 'author' => null, 'is_featured' => false]);
    }

    public function test_media_facets_match_the_credits_page(): void
    {
        $facets = $this->getJson('/api/v1/media?facets=true')->assertOk()->json('meta.facets');
        $page = $this->get('/credits/media')->assertOk()->viewData('page')['props'];

        $this->assertSame($page['stats']['total'], $facets['total']);
        $this->assertSame(['photo' => 2, 'postcard' => 1], $facets['types']);
        $this->assertSame($page['stats']['photo_count'], $facets['types']['photo']);
        $this->assertSame([['license' => 'CC BY-SA 4.0', 'count' => 2], ['license' => null, 'count' => 1]], $facets['licenses']);
        $this->assertEqualsCanonicalizing(
            array_map(fn (array $r): array => ['license' => $r['license'], 'count' => (int) $r['count']], $page['byLicense']),
            $facets['licenses'],
        );
        $this->assertSame([['author' => 'Spendeau', 'count' => 2]], $facets['authors']);

        // Compteurs de la recherche en cours, objet JSON même vide.
        $this->assertSame(1, $this->getJson('/api/v1/media?facets=true&type=postcard')->json('meta.facets.total'));
        $empty = $this->getJson('/api/v1/media?facets=true&station=paris-saint-lazare')->assertOk();
        $this->assertStringContainsString('"types":{}', $empty->getContent());
        $this->getJson('/api/v1/media')->assertOk()->assertJsonMissingPath('meta.facets');
        $this->getJson('/api/v1/media?facets=peut-etre')->assertUnprocessable()->assertJsonValidationErrors('facets');
    }

    public function test_featured_media_filter_in_rest_and_graphql(): void
    {
        $this->assertSame([true], $this->getJson('/api/v1/media?featured=true')->assertOk()->json('data.*.featured'));
        $this->assertSame([false, false], $this->getJson('/api/v1/media?featured=false')->assertOk()->json('data.*.featured'));
        $this->getJson('/api/v1/media?featured=oui')->assertUnprocessable()->assertJsonValidationErrors('featured');

        $nodes = $this->graphQL('{ media(filter: { featured: true }) { edges { node { featured } } } }')
            ->assertJsonMissingPath('errors')->json('data.media.edges.*.node.featured');
        $this->assertSame([true], $nodes);
    }
}
