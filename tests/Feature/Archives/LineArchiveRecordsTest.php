<?php

namespace Tests\Feature\Archives;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Models\LineArchiveRecord;
use App\Services\Archives\LineArchives;
use App\Support\SourceRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Notices des Archives SNCF : import (vff:import-sncf-archives) sur un extrait réel du jeu de données
 * (tests/Fixtures/Sncf/archives-sncf-sardo-extrait.json), puis partout où VFF les sert : fiche ligne (même
 * section que Gallica), API REST, GraphQL et outil MCP `line_archive_records`, avec la provenance ODbL ; ni
 * description ni image.
 */
class LineArchiveRecordsTest extends TestCase
{
    use CommandSandbox;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const FIXTURE = 'tests/Fixtures/Sncf/archives-sncf-sardo-extrait.json';

    private Line $mans;

    private Line $argentan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->mans = Line::factory()->create(['slug' => 'rfn-430000', 'code' => '430000', 'name' => 'Ligne du Mans à Mézidon']);
        $this->argentan = Line::factory()->create(['slug' => 'rfn-425000', 'code' => '425000', 'name' => "Ligne d'Argentan à Granville"]);
        Line::factory()->create(['slug' => 'rfn-301000', 'code' => '301000', 'name' => "Ligne d'Arras à Hazebrouck"]);
        // Une ligne entre Paris et Orléans : le nom de la compagnie du PO ne doit pas la désigner.
        Line::factory()->create(['slug' => 'rfn-570000', 'code' => '570000', 'name' => 'Ligne de Paris à Orléans']);
        Line::factory()->create(['slug' => 'rfn-1000', 'code' => '001000', 'name' => 'Ligne sans archives']);
    }

    private function import(array $options = []): void
    {
        $this->artisan('vff:import-sncf-archives', ['--file' => self::FIXTURE, ...$options])->assertSuccessful();
    }

    public function test_the_import_attaches_the_records_that_name_a_line_and_nothing_else(): void
    {
        $this->artisan('vff:import-sncf-archives', ['--file' => self::FIXTURE])
            ->expectsOutputToContain('Notices : 7 ; lues : 6 (1 écartées : sans titre ni lien, type inconnu ou thème sur des personnes) ; jugées : 3 ; retenues : 4 sur 3 ligne(s) ; ambiguës : 0.')
            ->expectsOutputToContain('notices des Archives SNCF en base : 0 avant, 4 après.')
            ->assertSuccessful();

        $this->assertSame(2, $this->mans->archiveRecords()->count());
        $this->assertSame(1, $this->argentan->archiveRecords()->count());
        $this->assertSame(0, LineArchiveRecord::query()->whereHas('line', fn ($q) => $q->where('slug', 'rfn-570000'))->count());
        $alencon = LineArchiveRecord::query()->where('reference', '33453')->sole();
        $this->assertSame('sncf-archives', $alencon->source);
        $this->assertSame('image', $alencon->doc_type);
        $this->assertSame('SNCF-MEDIATHEQUE', $alencon->rights);
        $this->assertSame('month', $alencon->date_precision);
        // Ligne sans date connue dans ce jeu d'essai : pas de point de date.
        $this->assertSame(9, $alencon->score);
        $this->assertStringStartsWith('https://www.archives.sncf.com/', $alencon->url);
        $hazebrouck = LineArchiveRecord::query()->where('title', 'like', 'Electrification Nord-Paris%')->sole();
        $this->assertSame('document', $hazebrouck->doc_type);
        $this->assertSame('day', $hazebrouck->date_precision);

        // Une relance remplace sans doublon ; Gallica n'est jamais touché.
        (new LineArchiveDocument)->forceFill(['line_id' => $this->mans->id, 'source' => 'gallica', 'gallica_ark' => 'ark:/12148/x', 'title' => 'T',
            'doc_type' => 'map', 'score' => 9, 'fetched_at' => now()])->save();
        $this->import();
        $this->assertSame(4, LineArchiveRecord::query()->count());
        $this->assertSame(1, LineArchiveDocument::query()->count());
    }

    public function test_dry_run_report_and_threshold(): void
    {
        $report = storage_path('app/data/archives-sncf/candidats.csv');
        $this->artisan('vff:import-sncf-archives', ['--file' => self::FIXTURE, '--dry-run' => true, '--report' => $report])
            ->expectsOutputToContain('rien n\'est écrit')
            ->assertSuccessful();
        $this->assertSame(0, LineArchiveRecord::query()->count());
        $this->assertStringContainsString('oui,rfn-430000', File::get($report));

        $this->artisan('vff:import-sncf-archives', ['--threshold' => 12])->assertExitCode(2);
        $this->artisan('vff:import-sncf-archives', ['--file' => 'storage/absent.json'])->assertFailed();
    }

    public function test_download_saves_the_export_with_a_generic_user_agent(): void
    {
        Http::fake(['ressources.data.sncf.com/*' => Http::sequence()->push(File::get(base_path(self::FIXTURE)))->push('nope', 503)]);

        $this->artisan('vff:import-sncf-archives', ['--download' => true, '--dry-run' => true])->assertSuccessful();

        Http::assertSent(fn (Request $r): bool => ! str_contains($r->header('User-Agent')[0] ?? '', '@')
            && str_starts_with($r->url(), 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/archives-sncf-sardo/exports/json'));
        $this->assertFileExists(storage_path('app/data/archives-sncf/archives-sncf-sardo.json'));
        $this->assertFileExists(storage_path('app/data/archives-sncf/.nobackup'));

        // Un refus du serveur garde la copie locale.
        $this->artisan('vff:import-sncf-archives', ['--download' => true, '--dry-run' => true])->assertFailed();
        $this->assertStringContainsString('archives.sncf.com', File::get(storage_path('app/data/archives-sncf/archives-sncf-sardo.json')));
        Http::assertSentCount(2);
    }

    public function test_the_rest_api_serves_records_with_reasons_and_rights(): void
    {
        $this->import();

        $res = $this->getJson('/api/v1/lines/rfn-430000/archive-records')->assertOk()->assertHeader('Cache-Control');
        $this->assertSame(2, $res->json('total'));
        $this->assertSame([1960, 1986], array_map(fn (array $r) => $r['date']['year'], $res->json('records')));
        $first = $res->json('records.0');
        $this->assertSame("Gare d'Argentan située sur les lignes du Mans à Mézidon et d'Argentan à Granville", $first['title']);
        $this->assertSame(['label' => '1960-09-22', 'year' => 1960, 'year_to' => null, 'exact' => '1960-09-22', 'precision' => 'day'], $first['date']);
        $this->assertSame(9, $first['match']['threshold']);
        $this->assertSame('rail_route', $first['match']['reasons'][0]['code']);
        $this->assertSame(LineArchives::RECORDS_ATTRIBUTION, $res->json('attribution'));
        $this->assertSame('sncf-archives', $res->json('provenance.provider'));
        $this->assertSame('verified', $res->json('provenance.license_status'));
        $this->assertArrayNotHasKey('description', $first);

        $this->assertSame(2, $this->getJson('/api/v1/lines/rfn-430000/archive-records?type=image&source=sncf-archives')->json('total'));
        $this->assertSame(0, $this->getJson('/api/v1/lines/rfn-430000/archive-records?type=video')->json('total'));
        $this->getJson('/api/v1/lines/rfn-430000/archive-records?type=map')->assertStatus(422)->assertJsonValidationErrors('type');
        $this->getJson('/api/v1/lines/rfn-430000/archive-records?source=gallica')->assertStatus(422)->assertJsonValidationErrors('source');
        $this->getJson('/api/v1/lines/rfn-999999/archive-records')->assertNotFound();
        $this->getJson('/api/v1/lines/rfn-1000/archive-records')->assertOk()->assertJson(['total' => 0, 'records' => []]);
    }

    public function test_graphql_and_mcp_serve_the_same_records(): void
    {
        $this->import();

        $gql = $this->graphQL('query { line(slug: "rfn-430000") { archive_records { title type reference date_precision url score score_reasons { code points } attribution provenance { provider license_status } } videos: archive_records(type: VIDEO) { url } } }');
        $records = $gql->json('data.line.archive_records');
        $this->assertCount(2, $records);
        $this->assertSame('image', $records[0]['type']);
        $this->assertSame(LineArchives::RECORDS_ATTRIBUTION, $records[0]['attribution']);
        $this->assertSame(['provider' => 'sncf-archives', 'license_status' => 'verified'], $records[0]['provenance']);
        $this->assertSame([], $gql->json('data.line.videos'));

        $rest = $this->getJson('/api/v1/lines/rfn-430000/archive-records')->json();
        [$data, $error] = $this->tool('line_archive_records', ['line' => '430000']);
        $this->assertFalse($error);
        $this->assertSame($rest['records'], $data['records']);
        $this->assertSame(LineArchives::RECORDS_ATTRIBUTION, $data['provenance']['mention']);
        $this->assertContains('sncf-archives', array_column($data['attribution']['sources'], 'provider'));
        $this->assertStringEndsWith('/lines/rfn-430000#archives', $data['vff_url']);

        [$none, $noneError] = $this->tool('line_archive_records', ['line' => 'rfn-1000']);
        $this->assertFalse($noneError);
        $this->assertSame([], $none['records']);
        [, $unknown] = $this->tool('line_archive_records', ['line' => 'rfn-999999']);
        $this->assertTrue($unknown);
    }

    public function test_the_line_page_shows_the_records_in_the_archives_section(): void
    {
        $this->import();

        $this->getWithDeferredProps('/lines/rfn-430000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lines/Show')
            ->where('archives.total', 0)
            ->where('archive_records.total', 2)
            ->where('archive_records.fetched_on', date('Y-m-d', (int) filemtime(base_path(self::FIXTURE))))
            ->has('archive_records.items', 2)
            ->where('archive_records.items.1.type', 'image')
            ->where('archive_records.items.1.reference', '33453')
            ->where('archive_records.items.1.date_precision', 'month'));

        $this->getWithDeferredProps('/lines/rfn-1000')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('archive_records.total', 0)
            ->where('archive_records.items', []));
    }

    public function test_the_source_is_declared_under_odbl(): void
    {
        $rights = app(SourceRights::class);

        $this->assertSame('sncf-archives', $rights->providerFor('line_archive_records', 'sncf-archives'));
        $this->assertSame(SourceRights::SAFE, $rights->provider('sncf-archives')['statut'] ?? null);
        $this->assertStringContainsString('ODbL', (string) ($rights->provider('sncf-archives')['licence'] ?? ''));
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
