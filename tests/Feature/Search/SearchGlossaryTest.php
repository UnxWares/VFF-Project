<?php

namespace Tests\Feature\Search;

use App\Services\SearchResultsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Le glossaire dans la recherche : groupe « Glossaire » de la page /search et champ `glossary` de la requête GraphQL
 * `search`, avec la même recherche (GlossarySearch) que la barre de l'en-tête et l'API REST.
 */
class SearchGlossaryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    public function test_the_search_page_has_a_glossary_group_with_the_name_in_the_language_of_the_page(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr'])->get('/search?q=overhead+line')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.glossary', 1)
                ->where('total', 1)
                ->where('groups.0.type', 'glossary')
                ->where('groups.0.total', 1)
                ->where('groups.0.approximate', false)
                ->where('groups.0.items.0.slug', 'catenaire')
                ->where('groups.0.items.0.name', 'Caténaire'));

        $this->withHeaders(['Accept-Language' => 'en'])->get('/search?q=overhead+line')
            ->assertInertia(fn (Assert $page) => $page->where('groups.0.items.0.name', 'Overhead line (catenary)'));
    }

    public function test_the_name_follows_the_locale_and_the_abbreviation_is_served(): void
    {
        $this->withHeaders(['Accept-Language' => 'de'])->get('/search?q=kvb&type=glossary')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'glossary')
                ->where('groups.0.items.0.slug', 'kvb')
                ->where('groups.0.items.0.abbr', 'KVB'));
    }

    public function test_the_type_view_paginates_and_the_total_counts_the_glossary(): void
    {
        $this->get('/search?q=tvm&type=glossary')
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.0.type', 'glossary')
                ->where('groups.0.per_page', SearchResultsPage::PER_PAGE)
                ->where('groups.0.total', fn ($n) => $n >= 3)
                ->where('groups.0.items.0.highlight.0.mark', true));
    }

    public function test_a_query_without_a_term_has_no_glossary_group(): void
    {
        $this->get('/search?q=zzqxkw')
            ->assertInertia(fn (Assert $page) => $page->where('counts.glossary', 0)->where('groups', []));
    }

    public function test_graphql_search_returns_glossary_terms_in_the_requested_language(): void
    {
        $terms = $this->graphQL('{ search(query: "overhead line") { glossary { slug name nameEn: name(lang: "en") nameDe: name(lang: "de") abbr } } }')
            ->assertGraphQLErrorFree()->json('data.search.glossary');

        $this->assertSame('catenaire', $terms[0]['slug']);
        $this->assertSame('Caténaire', $terms[0]['name']);
        $this->assertSame('Overhead line (catenary)', $terms[0]['nameEn']);
        $this->assertSame('Fahrleitung (Oberleitung)', $terms[0]['nameDe']);
        $this->assertNull($terms[0]['abbr']);
    }

    public function test_graphql_glossary_is_the_rest_glossary_and_respects_the_limit(): void
    {
        $rest = $this->getJson('/api/v1/search?q=tvm')->assertOk()->json('glossary');
        $graphql = $this->graphQL('{ search(query: "tvm", limit: 5) { glossary { slug abbr } } }')->assertGraphQLErrorFree()->json('data.search.glossary');

        $this->assertSame(array_column($rest, 'slug'), array_column($graphql, 'slug'));
        $this->assertCount(1, $this->graphQL('{ search(query: "tvm", limit: 1) { glossary { slug } } }')->json('data.search.glossary'));
        $this->graphQL('{ search(query: "x") { glossary { slug } } }')->assertJsonPath('data.search.glossary', []);
    }
}
