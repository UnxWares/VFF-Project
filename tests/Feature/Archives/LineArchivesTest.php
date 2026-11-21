<?php

namespace Tests\Feature\Archives;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Services\Archives\LineArchives;
use App\Support\SourceRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Documents de Gallica d'une ligne, partout où VFF les sert : fiche ligne (section « Dans les archives »,
 * absente quand il n'y a rien), API REST, GraphQL et outil MCP `line_archives`, avec la provenance et la
 * mention de la BnF ; jamais d'image.
 */
class LineArchivesTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-340000', 'code' => '340000', 'name' => 'Ligne de Paris-St-Lazare au Havre']);
        Line::factory()->create(['slug' => 'rfn-1000', 'code' => '001000', 'name' => 'Ligne sans archives']);

        $this->document('ark:/12148/btv1b53035187p', 'Chemin de fer de Paris au Hâvre', 'map', 1838, 'year', ['shelfmark' => 'GE DL 1838-38']);
        $this->document('ark:/12148/bpt6k372725k', 'Chemin de fer de Paris à Rouen et au Havre, par la vallée de la Seine', 'monograph', 1837, 'year',
            ['authors' => ['Polonceau, Antoine-Rémy'], 'catalogue_ark' => 'ark:/12148/cb30000001q']);
        $this->document('ark:/12148/btv1b104834123', 'Pont sur la ligne de Paris au Havre à Poissy', 'image', null, 'unknown');
        // Deux photographies d'un même déraillement, deux exemplaires aux cotes différentes : une seule entrée sur la fiche.
        $this->document('ark:/12148/btv1b90000001', 'Catastrophe de chemin de fer près de Rouen', 'image', 1926, 'year', ['shelfmark' => 'YE-54393']);
        $this->document('ark:/12148/btv1b90000002', 'Catastrophe de chemin de fer près de Rouen', 'image', 1926, 'year', ['shelfmark' => 'YE-54394']);
    }

    /** @param array<model-property<LineArchiveDocument>, mixed> $extra */
    private function document(string $ark, string $title, string $type, ?int $year, string $precision, array $extra = []): void
    {
        (new LineArchiveDocument)->forceFill([
            'line_id' => $this->line->id, 'source' => 'gallica', 'gallica_ark' => $ark, 'title' => $title, 'doc_type' => $type,
            'authors' => [], 'date_label' => $year === null ? null : (string) $year, 'year' => $year, 'date_precision' => $precision,
            'score' => 11, 'score_reasons' => [['code' => 'route', 'points' => 4], ['code' => 'ends', 'points' => 2]],
            'matched_places' => ['Paris', 'Le Havre'], 'fetched_at' => '2026-11-20 10:00:00', 'record_updated_on' => '2025-06-16',
            ...$extra,
        ])->save();
    }

    public function test_the_rest_api_lists_documents_by_date_with_links_reasons_and_rights(): void
    {
        $res = $this->getJson('/api/v1/lines/rfn-340000/archives')->assertOk()->assertHeader('Cache-Control');

        $this->assertSame(5, $res->json('total'));
        // Du plus ancien au plus récent, sans date en dernier.
        $this->assertSame([1837, 1838, 1926, 1926, null], array_map(fn (array $d) => $d['date']['year'], $res->json('documents')));
        $first = $res->json('documents.0');
        $this->assertSame('https://gallica.bnf.fr/ark:/12148/bpt6k372725k', $first['gallica_url']);
        $this->assertSame('https://catalogue.bnf.fr/ark:/12148/cb30000001q', $first['catalogue_url']);
        $this->assertSame(['Polonceau, Antoine-Rémy'], $first['authors']);
        $this->assertSame(['label' => '1837', 'year' => 1837, 'year_to' => null, 'exact' => null, 'precision' => 'year'], $first['date']);
        $this->assertSame(9, $first['match']['threshold']);
        $this->assertSame(11, $first['match']['max_score']);
        $this->assertSame('route', $first['match']['reasons'][0]['code']);
        $this->assertSame(LineArchives::ATTRIBUTION, $res->json('attribution'));
        $this->assertSame('gallica', $res->json('provenance.provider'));
        $this->assertSame('verified', $res->json('provenance.license_status'));
        // Aucune image ni vignette dans la réponse.
        $this->assertStringNotContainsString('thumbnail', (string) $res->getContent());
        $this->assertStringNotContainsString('iiif', (string) $res->getContent());
    }

    public function test_the_rest_api_gives_the_shelfmark_of_each_copy(): void
    {
        $res = $this->getJson('/api/v1/lines/rfn-340000/archives')->assertOk();

        $this->assertSame([null, 'GE DL 1838-38', 'YE-54393', 'YE-54394', null], array_column($res->json('documents'), 'shelfmark'));
    }

    public function test_the_rest_api_filters_by_type_and_validates_it(): void
    {
        $maps = $this->getJson('/api/v1/lines/rfn-340000/archives?type=map')->assertOk();
        $this->assertSame(1, $maps->json('total'));
        $this->assertSame('map', $maps->json('documents.0.type'));

        $this->getJson('/api/v1/lines/rfn-340000/archives?type=journal')->assertStatus(422)->assertJsonValidationErrors('type');
        $this->getJson('/api/v1/lines/rfn-999999/archives')->assertNotFound();
        $this->getJson('/api/v1/lines/rfn-1000/archives')->assertOk()->assertJson(['total' => 0, 'documents' => []]);
    }

    public function test_graphql_serves_the_same_documents_with_reasons_and_provenance(): void
    {
        $res = $this->graphQL('query { line(slug: "rfn-340000") { archives { title type year date_precision gallica_url score score_reasons { code points } attribution provenance { provider license_status } } maps: archives(type: MAP) { gallica_ark } } }');

        $docs = $res->json('data.line.archives');
        $this->assertCount(5, $docs);
        $this->assertSame(1837, $docs[0]['year']);
        $this->assertSame('monograph', $docs[0]['type']);
        $this->assertSame([['code' => 'route', 'points' => 4], ['code' => 'ends', 'points' => 2]], $docs[0]['score_reasons']);
        $this->assertSame(LineArchives::ATTRIBUTION, $docs[0]['attribution']);
        $this->assertSame(['provider' => 'gallica', 'license_status' => 'verified'], $docs[0]['provenance']);
        $this->assertSame([['gallica_ark' => 'ark:/12148/btv1b53035187p']], $res->json('data.line.maps'));
    }

    public function test_the_mcp_tool_returns_the_rest_content_with_the_bnf_mention(): void
    {
        $rest = $this->getJson('/api/v1/lines/rfn-340000/archives')->json();
        [$data, $error] = $this->tool('line_archives', ['line' => '340000']);

        $this->assertFalse($error);
        $this->assertSame($rest['documents'], $data['documents']);
        $this->assertSame(9, $data['method']['threshold']);
        $this->assertSame(LineArchives::ATTRIBUTION, $data['provenance']['mention']);
        $this->assertContains('gallica', array_column($data['attribution']['sources'], 'provider'));
        $this->assertStringEndsWith('/lines/rfn-340000#archives', $data['vff_url']);

        [$maps] = $this->tool('line_archives', ['line' => 'rfn-340000', 'type' => 'map']);
        $this->assertSame(1, $maps['total']);

        [$none, $noneError] = $this->tool('line_archives', ['line' => 'rfn-1000']);
        $this->assertFalse($noneError);
        $this->assertSame([], $none['documents']);

        [, $unknown] = $this->tool('line_archives', ['line' => 'rfn-999999']);
        $this->assertTrue($unknown);
    }

    public function test_the_line_page_groups_identical_documents_and_omits_the_section_when_empty(): void
    {
        $this->get('/lines/rfn-340000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Show')
            ->where('archives.total', 5)
            ->where('archives.fetched_on', '2026-11-20')
            ->has('archives.items', 4)
            ->where('archives.items.0.title', 'Chemin de fer de Paris à Rouen et au Havre, par la vallée de la Seine')
            ->where('archives.items.2.more_urls', ['https://gallica.bnf.fr/ark:/12148/btv1b90000002'])
            // Les deux cotes de l'entrée regroupée restent lisibles (revue du commit bb27386, point 8).
            ->where('archives.items.2.shelfmarks', ['YE-54393', 'YE-54394'])
            ->where('archives.items.1.shelfmarks', ['GE DL 1838-38'])
            ->where('archives.items.0.shelfmarks', [])
            ->where('archives.items.3.date', null)
            ->where('archives.items.3.date_precision', 'unknown'));

        $this->get('/lines/rfn-1000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('archives.total', 0)
            ->where('archives.items', []));
    }

    public function test_the_source_is_declared_with_verified_open_licence_metadata(): void
    {
        $rights = app(SourceRights::class);

        $this->assertSame('gallica', $rights->providerFor('line_archive_documents', 'gallica'));
        $this->assertSame(SourceRights::SAFE, $rights->provider('gallica')['statut'] ?? null);
        $this->artisan('vff:source-rights', ['--check' => true])->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments])->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [json_decode($text, true) ?? ['raw' => $text], (bool) ($result['isError'] ?? false)];
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
}
