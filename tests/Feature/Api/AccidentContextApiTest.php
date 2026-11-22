<?php

namespace Tests\Feature\Api;

use App\Models\Accident;
use App\Models\AccidentContextEntry;
use App\Models\AccidentImage;
use App\Models\AccidentReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Contexte sourcé d'un accident partout où la fiche est servie : API REST (`context`), GraphQL
 * (`Accident.context`), page du site, serveur MCP (langue demandée, sans citation : décision Q22).
 */
class AccidentContextApiTest extends TestCase
{
    use RefreshDatabase;

    private Accident $accident;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->accident = Accident::factory()->on('1891-07-26')->create([
            'slug' => '1891-07-26-saint-mande', 'kind' => 'collision', 'deaths' => 49,
            'context_checked_on' => '2026-11-21',
            'context_corrections' => [[
                'field' => 'deaths', 'before' => 43, 'before_meta' => ['toll_source' => 'wikidata'], 'value' => 49,
                'references' => ['rapport'], 'reason' => ['fr' => 'Bilan du rapport.', 'en' => 'Toll in the report.', 'de' => 'Bilanz des Berichts.'],
            ]],
        ]);
        AccidentReference::query()->create([
            'accident_id' => $this->accident->id, 'key' => 'rapport', 'position' => 0, 'provider' => 'bea-tt', 'kind' => 'report',
            'reliability' => 'official', 'title' => 'Rapport d’essai', 'publisher' => 'BEA-TT', 'published' => '1891-08',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/essai.pdf', 'access' => 'open', 'consulted_on' => '2026-11-21',
        ]);
        AccidentReference::query()->create([
            'accident_id' => $this->accident->id, 'key' => 'payant', 'position' => 1, 'provider' => 'retronews', 'kind' => 'press',
            'reliability' => 'press', 'title' => 'Le Petit Journal', 'url' => 'https://www.retronews.fr/x', 'access' => 'paywall',
            'consulted_on' => '2026-11-21',
        ]);
        AccidentContextEntry::query()->create([
            'accident_id' => $this->accident->id, 'section' => 'causes', 'position' => 0,
            'text' => ['fr' => 'Un signal mal observé.', 'en' => 'A signal not observed.', 'de' => 'Ein nicht beachtetes Signal.'],
            'reference_keys' => ['rapport'], 'quote' => ['text' => 'Le signal était fermé.', 'lang' => 'fr', 'reference' => 'rapport'],
        ]);
        AccidentImage::query()->create([
            'accident_id' => $this->accident->id, 'position' => 0, 'file' => 'File:Essai.jpg',
            'page_url' => 'https://commons.wikimedia.org/wiki/File:Essai.jpg', 'image_url' => 'https://upload.wikimedia.org/essai.jpg',
            'thumb_url' => 'https://upload.wikimedia.org/essai-500.jpg', 'width' => 1200, 'height' => 800, 'author' => 'Jean Dupont',
            'licence' => 'CC BY-SA 4.0', 'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0', 'date' => '1891',
            'caption' => ['fr' => 'Gravure', 'en' => 'Engraving', 'de' => 'Stich'], 'retrieved_at' => now(),
        ]);
    }

    public function test_rest_sheet_serves_the_context(): void
    {
        $context = $this->getJson('/api/v1/accidents/1891-07-26-saint-mande')->assertOk()->json('data.context');

        $this->assertSame('2026-11-21', $context['checked_on']);
        $this->assertSame('causes', $context['sections'][0]['section']);
        $this->assertSame('A signal not observed.', $context['sections'][0]['entries'][0]['text']['en']);
        $this->assertSame(['rapport'], $context['sections'][0]['entries'][0]['references']);
        $this->assertSame('Le signal était fermé.', $context['sections'][0]['entries'][0]['quote']['text']);
        $this->assertSame(['rapport', 'payant'], array_column($context['references'], 'key'));
        $this->assertSame('verified', $context['references'][0]['license_status']);
        $this->assertSame('paywall', $context['references'][1]['access']);
        $this->assertSame('Jean Dupont, CC BY-SA 4.0, via Wikimedia Commons', $context['images'][0]['attribution']);
        $this->assertSame(['field' => 'deaths', 'before' => 43, 'value' => 49], array_intersect_key($context['corrections'][0], array_flip(['field', 'before', 'value'])));
        $this->assertArrayNotHasKey('before_meta', $context['corrections'][0]);
        // Fiche enregistrée avant la marque « source unique » : fausse par défaut.
        $this->assertFalse($context['corrections'][0]['single_source']);
    }

    public function test_a_sheet_without_context_has_empty_lists(): void
    {
        Accident::factory()->on('2013-07-12')->create(['slug' => '2013-07-12-bretigny-sur-orge']);

        $context = $this->getJson('/api/v1/accidents/2013-07-12-bretigny-sur-orge')->assertOk()->json('data.context');

        $this->assertSame(['checked_on' => null, 'sections' => [], 'references' => [], 'images' => [], 'corrections' => []], $context);
    }

    public function test_graphql_serves_the_context_in_the_requested_language(): void
    {
        $data = $this->postJson('/graphql', ['query' => '{ accident(slug: "1891-07-26-saint-mande") { context {
            checked_on sections { section entries { text(lang: "de") references quote { text reference } } }
            references { key provider access license_status } images { author license caption(lang: "en") attribution }
            corrections { field before value reason(lang: "en") } } } }'])->assertOk()->json('data.accident.context');

        $this->assertSame('2026-11-21', $data['checked_on']);
        $this->assertSame('Ein nicht beachtetes Signal.', $data['sections'][0]['entries'][0]['text']);
        $this->assertSame('rapport', $data['sections'][0]['entries'][0]['quote']['reference']);
        $this->assertSame('Engraving', $data['images'][0]['caption']);
        $this->assertSame(['field' => 'deaths', 'before' => '43', 'value' => '49', 'reason' => 'Toll in the report.'], $data['corrections'][0]);
    }

    public function test_the_page_receives_the_context(): void
    {
        $this->get('/accidents/1891-07-26-saint-mande')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Accidents/Show')
                ->where('accident.context.sections.0.section', 'causes')
                ->where('accident.context.references.1.key', 'payant')
                ->where('accident.context.images.0.author', 'Jean Dupont'));
    }

    public function test_mcp_serves_the_context_in_one_language_without_quotes(): void
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'get_accident', 'arguments' => (object) ['accident' => '1891-07-26-saint-mande', 'lang' => 'en']]])
            ->assertOk()->json('result');
        $text = $result['content'][0]['text'];
        $data = json_decode($text, true);

        $this->assertSame('A signal not observed.', $data['accident']['context']['sections'][0]['entries'][0]['text']);
        $this->assertStringNotContainsString('Le signal était fermé', $text);
        $this->assertSame('Engraving', $data['accident']['context']['images'][0]['caption']);
        $this->assertSame('Toll in the report.', $data['accident']['context']['corrections'][0]['reason']);
        $providers = array_column($data['attribution']['sources'] ?? $data['_attribution']['sources'] ?? [], 'provider');
        $this->assertContains('retronews', $providers);
        $this->assertContains('commons', $providers);
    }
}
