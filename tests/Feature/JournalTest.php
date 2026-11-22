<?php

namespace Tests\Feature;

use App\Mcp\Servers\VffServer;
use App\Mcp\Tools\JournalTool;
use App\Models\Line;
use App\Models\Station;
use App\Services\Journal\JournalRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Journal de bord sur les articles d'essai de tests/Fixtures/Journal : pages, flux, plan du site, API
 * REST, GraphQL et MCP, résolution des liens vers les fiches, échappement du HTML écrit à la main.
 */
class JournalTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        // Visiteur francophone par défaut (le navigateur de test n'envoie aucune langue).
        $this->withHeader('Accept-Language', 'fr');
        $this->app->instance(JournalRepository::class, new JournalRepository(base_path('tests/Fixtures/Journal')));
        $this->line = Line::factory()->create(['code' => '123456', 'slug' => 'rfn-123456', 'name' => 'Ligne d’essai']);
        $this->station = Station::factory()->create(['slug' => 'gare-essai', 'name' => 'Gare d’essai', 'city' => 'Essai']);
        DB::table('station_redirects')->insert(['old_slug' => 'ancien-slug', 'station_id' => $this->station->id]);
    }

    public function test_index_lists_published_articles_newest_first(): void
    {
        $this->get('/journal')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Journal/Index')
            ->where('pagination', ['current' => 1, 'last' => 1, 'total' => 2, 'per_page' => 10])
            ->where('articles.0.slug', 'essai-recent')
            ->where('articles.1.slug', 'essai-ancien')
            ->where('articles.1.lines.0', ['code' => '123456', 'slug' => 'rfn-123456', 'name' => 'Ligne d’essai'])
            ->where('articles.1.image.licence', 'CC0 1.0')
            ->etc());
    }

    public function test_index_rejects_pages_out_of_range(): void
    {
        $this->get('/journal?page=2')->assertNotFound();
        $this->get('/journal?page=0')->assertNotFound();
        $this->get('/journal?page=abc')->assertNotFound();
    }

    public function test_future_article_is_not_published(): void
    {
        $this->get('/journal/essai-futur')->assertNotFound();
        $this->get('/journal/inconnu')->assertNotFound();
    }

    public function test_show_resolves_sheet_links_and_escapes_html(): void
    {
        $response = $this->get('/journal/essai-ancien')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Journal/Show')
            ->where('article.lang', 'fr')
            ->where('article.author', 'L’équipe VFF')
            ->where('article.lines', [['code' => '123456', 'slug' => 'rfn-123456', 'name' => 'Ligne d’essai']])
            ->where('article.stations', [['slug' => 'gare-essai', 'name' => 'Gare d’essai', 'city' => 'Essai']])
            ->where('article.toc', [['id' => 'premiere-partie', 'label' => 'Première partie'], ['id' => 'premiere-partie-2', 'label' => 'Première partie']])
            ->where('article.json_ld.@type', 'Article')
            ->where('article.json_ld.author.name', 'L’équipe VFF')
            ->where('older', null)
            ->where('newer.slug', 'essai-recent')
            ->etc());

        $html = $response->viewData('page')['props']['article']['html'];
        $this->assertStringContainsString('<a href="/lines/rfn-123456#chronicle">ligne d’essai</a>', $html);
        // Ancien slug de gare : lien vers le slug actuel.
        $this->assertStringContainsString('<a href="/stations/gare-essai">gare renommée</a>', $html);
        // Fiche absente : le texte reste, sans lien.
        $this->assertStringContainsString('une ligne absente et', $html);
        $this->assertStringNotContainsString('999999', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('vff-ext', $html);
        $this->assertStringContainsString('role="doc-endnotes"', $html);
        $this->assertStringContainsString('aria-label="Revenir au texte"', $html);
        $this->assertStringContainsString('<h2 id="premiere-partie-2">', $html);
    }

    public function test_show_writes_share_tags_and_structured_data_on_the_server(): void
    {
        $page = $this->get('/journal/essai-ancien')->assertOk()->getContent();
        $this->assertStringContainsString('<title inertia>Essai ancien &amp; &lt;ancien&gt; - Journal de bord - VFF</title>', (string) $page);
        $this->assertStringContainsString('property="og:type" content="article"', (string) $page);
        $this->assertStringContainsString('"@type":"Article"', (string) $page);
        $this->assertStringContainsString('upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Essai.jpg', (string) $page);
        // Aucune balise fermante possible dans le JSON-LD (titre avec « < »).
        $this->assertStringNotContainsString('<ancien>"', (string) $page);
    }

    public function test_translation_is_used_when_it_exists_and_french_otherwise(): void
    {
        $this->withCookie('locale', 'en')->get('/journal/essai-ancien')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('article.lang', 'en')->where('article.title', 'Old test')->where('article.locales', ['fr', 'en'])->etc());
        $this->withCookie('locale', 'de')->get('/journal/essai-ancien')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('article.lang', 'fr')->where('article.author', 'Das VFF-Team')->etc());
    }

    public function test_disclosure_is_passed_to_the_page(): void
    {
        $this->get('/journal/essai-recent')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('article.disclosure', 'Un membre de l’équipe est adhérent d’une association citée.')
            ->where('article.image', null)
            ->etc());
    }

    public function test_feeds_are_valid_xml_with_absolute_links(): void
    {
        $atom = $this->get('/journal/atom.xml?lang=fr')->assertOk()->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8')->getContent();
        $xml = simplexml_load_string((string) $atom);
        $this->assertNotFalse($xml);
        $this->assertCount(2, $xml->entry);
        $this->assertSame('Essai récent', (string) $xml->entry[0]->title);
        $this->assertStringContainsString('href="'.url('/lines/rfn-123456').'#chronicle"', (string) $xml->entry[1]->content);
        $this->assertStringNotContainsString('Essai futur', (string) $atom);

        $rss = $this->get('/journal/rss.xml?lang=en')->assertOk()->getContent();
        $xml = simplexml_load_string((string) $rss);
        $this->assertNotFalse($xml);
        $this->assertSame('Old test', (string) $xml->channel->item[1]->title);

        $this->get('/journal/atom.xml?lang=xx')->assertNotFound();
    }

    public function test_sitemaps_list_the_articles(): void
    {
        $index = simplexml_load_string((string) $this->get('/sitemap.xml')->assertOk()->getContent());
        $this->assertNotFalse($index);
        $this->assertSame(url('/journal/sitemap.xml'), (string) $index->sitemap->loc);

        $map = simplexml_load_string((string) $this->get('/journal/sitemap.xml')->assertOk()->getContent());
        $this->assertNotFalse($map);
        $locs = array_map('strval', iterator_to_array($map->xpath('//*[local-name()="loc"]'), false));
        $this->assertSame([url('/journal'), url('/journal/essai-recent'), url('/journal/essai-ancien')], $locs);
    }

    public function test_line_sheet_links_to_its_articles(): void
    {
        $this->get('/lines/rfn-123456')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('journal', [['slug' => 'essai-ancien', 'title' => 'Essai ancien & <ancien>', 'lang' => 'fr']])
            ->etc());
    }

    public function test_rest_api_lists_and_shows_articles(): void
    {
        $this->getJson('/api/v1/journal?lang=en&per_page=1')->assertOk()
            ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 2, 'per_page' => 1, 'total' => 2])
            ->assertJsonPath('data.0.slug', 'essai-recent')
            ->assertJsonPath('data.0.url', url('/journal/essai-recent'))
            ->assertJsonMissingPath('data.0.html');
        $this->getJson('/api/v1/journal?line=123456')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.lines', ['123456']);
        $this->getJson('/api/v1/journal/essai-ancien?lang=en')->assertOk()
            ->assertJsonPath('lang', 'en')
            ->assertJsonPath('author', 'The VFF team')
            ->assertJsonPath('license', 'CC BY-SA 4.0')
            ->assertJsonPath('json_ld.@type', 'Article');
        $this->getJson('/api/v1/journal/essai-futur')->assertNotFound()->assertJsonPath('message', 'Article inconnu.');
        $this->getJson('/api/v1/journal?lang=xx')->assertUnprocessable()->assertJsonValidationErrors('lang');
        $this->getJson('/api/v1/journal?per_page=51')->assertUnprocessable();
        $this->getJson('/api/v1/journal?line=12')->assertUnprocessable();
    }

    public function test_graphql_lists_and_shows_articles(): void
    {
        $this->postJson('/graphql', ['query' => '{ journalArticles(line: "123456") { slug line_codes html lines { code } } journalArticle(slug: "essai-ancien", lang: "en") { title lang lines { slug } toc { id } image { licence } } }'])
            ->assertOk()
            ->assertJsonPath('data.journalArticles', [['slug' => 'essai-ancien', 'line_codes' => ['123456'], 'html' => null, 'lines' => null]])
            ->assertJsonPath('data.journalArticle.title', 'Old test')
            ->assertJsonPath('data.journalArticle.lines', [['slug' => 'rfn-123456']])
            ->assertJsonPath('data.journalArticle.image', null);
        $this->postJson('/graphql', ['query' => '{ journalArticle(slug: "essai-futur") { title } }'])
            ->assertOk()->assertJsonPath('data.journalArticle', null);
    }

    public function test_mcp_tool_lists_and_shows_articles(): void
    {
        VffServer::tool(JournalTool::class, [])->assertOk()->assertSee('essai-recent')->assertSee('essai-ancien')->assertDontSee('essai-futur');
        VffServer::tool(JournalTool::class, ['slug' => 'essai-ancien', 'lang' => 'en'])->assertOk()->assertSee('Old test')->assertSee('The VFF team');
        VffServer::tool(JournalTool::class, ['slug' => 'inconnu'])->assertHasErrors();
    }
}
