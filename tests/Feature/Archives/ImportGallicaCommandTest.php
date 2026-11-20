<?php

namespace Tests\Feature\Archives;

use App\Models\Line;
use App\Models\LineArchiveDocument;
use App\Services\Archives\CatalogueSruClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-gallica : requêtes polies au catalogue de la BnF (User-Agent générique, une par seconde,
 * cache disque, reprise), rattachement des notices aux lignes et remplacement sans doublon ; --dry-run
 * n'écrit rien. Les réponses sont des réponses réelles enregistrées (tests/Fixtures/Bnf), jamais le réseau.
 */
class ImportGallicaCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Sleep::fake();
        $this->line = Line::factory()->create(['slug' => 'rfn-610000', 'code' => '610000', 'name' => "Ligne d'Angoulême à Limoges"]);
        Line::factory()->create(['slug' => 'rfn-9', 'code' => null, 'name' => 'Raccordement de Bourges à Pont-Vert']);
    }

    /** Réponse réelle du catalogue pour « Angoulême » et « Limoges » (une notice). */
    private function sruResponse(): string
    {
        return $this->fixture('Bnf/sru-angouleme-limoges.xml');
    }

    public function test_the_import_stores_the_metadata_and_the_link_only(): void
    {
        Http::fake(['catalogue.bnf.fr/*' => Http::response($this->sruResponse())]);

        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])
            ->expectsOutputToContain('documents retenus : 1 sur 1 ligne(s)')
            ->expectsOutputToContain('Documents enregistrés : 1 ; documents Gallica en base : 0 avant, 1 après.')
            ->assertSuccessful();

        $doc = LineArchiveDocument::query()->sole();
        $this->assertSame($this->line->id, $doc->line_id);
        $this->assertSame('gallica', $doc->source);
        $this->assertSame('ark:/12148/bpt6k1192344q', $doc->gallica_ark);
        $this->assertSame('https://gallica.bnf.fr/ark:/12148/bpt6k1192344q', $doc->gallica_url);
        $this->assertSame('https://catalogue.bnf.fr/ark:/12148/cb315821610', $doc->catalogue_url);
        $this->assertSame('monograph', $doc->doc_type);
        $this->assertSame(1860, $doc->year);
        $this->assertSame('year', $doc->date_precision);
        $this->assertSame('V-54984', $doc->shelfmark);
        $this->assertSame(['Villemandy, Alfred de'], $doc->authors);
        // Ligne sans date connue : pas de point de date, mais trajet entre ses extrémités et mot du rail.
        $this->assertSame(9, $doc->score);
        $this->assertSame(['rail_route', 'ends', 'rail_word', 'date_unknown', 'doc_type'], array_column($doc->score_reasons, 'code'));

        Http::assertSent(fn (Request $r): bool => $r->hasHeader('User-Agent', CatalogueSruClient::USER_AGENT)
            && str_starts_with($r->url(), CatalogueSruClient::ENDPOINT)
            && str_contains(urldecode($r->url()), 'bib.digitized any "freeAccess"')
            && str_contains(urldecode($r->url()), 'bib.recordtype any "mon"'));
    }

    public function test_a_second_run_reads_the_disk_cache_and_replaces_without_duplicates(): void
    {
        Http::fake(['catalogue.bnf.fr/*' => Http::response($this->sruResponse())]);

        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])->assertSuccessful();
        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])
            ->expectsOutputToContain('(0 au serveur, 1 lues dans le cache')
            ->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(1, LineArchiveDocument::query()->count());
        $this->assertFileExists(storage_path('app/data/gallica/.nobackup'));
    }

    public function test_dry_run_and_report_write_nothing_in_the_database(): void
    {
        Http::fake(['catalogue.bnf.fr/*' => Http::response($this->sruResponse())]);
        $report = storage_path('app/data/gallica/candidats.csv');

        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000'], '--dry-run' => true, '--report' => $report])
            ->expectsOutputToContain('rien n\'est écrit')
            ->assertSuccessful();

        $this->assertSame(0, LineArchiveDocument::query()->count());
        $csv = File::get($report);
        $this->assertStringContainsString('oui,rfn-610000', $csv);
        $this->assertStringContainsString('rail_route+4 ends+2 rail_word+2 date_unknown+0 doc_type+1', $csv);
    }

    public function test_a_higher_threshold_keeps_nothing_and_clears_the_line(): void
    {
        Http::fake(['catalogue.bnf.fr/*' => Http::response($this->sruResponse())]);
        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])->assertSuccessful();

        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000'], '--threshold' => 10])
            ->expectsOutputToContain('documents retenus : 0')
            ->assertSuccessful();
        $this->assertSame(0, LineArchiveDocument::query()->count());

        $this->artisan('vff:import-gallica', ['--threshold' => 12])->assertExitCode(2);
    }

    public function test_offline_mode_sends_no_request_and_connections_are_never_queried(): void
    {
        Http::fake();

        $this->artisan('vff:import-gallica', ['--offline' => true])
            // Une seule ligne interrogée (le raccordement ne l'est jamais), et sa requête manque au cache.
            ->expectsOutputToContain('Lignes : 1 ; requêtes : 1 (0 au serveur, 0 lues dans le cache, 1 absentes du cache)')
            ->expectsOutputToContain('1 ligne(s) non mise(s) à jour')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_an_unknown_line_is_refused(): void
    {
        $this->artisan('vff:import-gallica', ['--line' => ['rfn-999999']])
            ->expectsOutputToContain('Ligne inconnue : rfn-999999')
            ->assertExitCode(2);
    }

    public function test_a_blocked_server_is_reported_and_nothing_is_cached(): void
    {
        Http::fake(['catalogue.bnf.fr/*' => Http::response('<html>Attention Required! | Cloudflare</html>', 403)]);

        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])
            ->expectsOutputToContain('HTTP 403')
            ->expectsOutputToContain('erreurs : 1')
            ->expectsOutputToContain('non mise(s) à jour, documents gardés tels quels (requête en erreur ou absente du cache) : rfn-610000')
            ->assertFailed();

        $this->assertSame(0, LineArchiveDocument::query()->count());
        $this->assertSame([], File::glob(storage_path('app/data/gallica/sru/*.xml')));
    }

    /**
     * @return list<int> lignes des documents enregistrés
     *
     * @phpstan-impure
     */
    private function documentLines(): array
    {
        return LineArchiveDocument::query()->orderBy('id')->pluck('line_id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /** Réponse SRU valide sans notice. */
    private function emptySruResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">'
            .'<srw:version>1.2</srw:version><srw:numberOfRecords>0</srw:numberOfRecords></srw:searchRetrieveResponse>';
    }

    public function test_an_offline_run_without_cache_keeps_the_documents_and_names_the_line(): void
    {
        // Revue du commit bb27386, point 1 : un --offline sur un poste sans cache vidait la table.
        Http::fake(['catalogue.bnf.fr/*' => Http::response($this->sruResponse())]);
        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])->assertSuccessful();
        File::deleteDirectory(storage_path('app/data/gallica/sru'));

        $this->artisan('vff:import-gallica', ['--offline' => true])
            ->expectsOutputToContain('1 absentes du cache')
            ->expectsOutputToContain('1 ligne(s) non mise(s) à jour, documents gardés tels quels (requête en erreur ou absente du cache) : rfn-610000')
            ->expectsOutputToContain('documents Gallica en base : 1 avant, 1 après.')
            ->assertFailed();

        $this->assertSame(1, LineArchiveDocument::query()->count());
    }

    public function test_a_server_error_keeps_the_documents_and_fails(): void
    {
        Http::fakeSequence('catalogue.bnf.fr/*')
            ->push($this->sruResponse())
            ->push('', 503)->push('', 503)->push('', 503);
        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])->assertSuccessful();

        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000'], '--refresh' => true])
            ->expectsOutputToContain('HTTP 503')
            ->expectsOutputToContain('erreurs : 1')
            ->assertFailed();

        $this->assertSame(1, LineArchiveDocument::query()->count());
    }

    public function test_a_200_page_that_is_not_sru_is_an_error_of_its_line_and_the_import_goes_on(): void
    {
        // Revue du commit bb27386, point 6 : la page de maintenance arrêtait toute la passe.
        $other = Line::factory()->create(['slug' => 'rfn-1', 'code' => null, 'name' => 'Ligne de Rochefort à Limoges']);
        Http::fake(fn (Request $r) => str_contains(urldecode($r->url()), 'Angoul')
            ? Http::response('<?xml version="1.0"?><maintenance>Service indisponible</maintenance>')
            : Http::response($this->sruResponse()));

        $this->artisan('vff:import-gallica')
            ->expectsOutputToContain('pas une réponse SRU')
            ->expectsOutputToContain('non mise(s) à jour, documents gardés tels quels (requête en erreur ou absente du cache) : rfn-610000')
            ->assertFailed();

        // La notice trouvée par la ligne suivante est jugée et enregistrée ; la ligne en erreur ne reçoit rien.
        $this->assertSame([$other->id], $this->documentLines());
    }

    public function test_a_partial_import_never_removes_a_document_found_by_another_line(): void
    {
        // Revue du commit bb27386, point 2 : la notice n'est trouvée que par la requête de la ligne de Limoges à
        // Brive, mais elle parle de la ligne d'Angoulême à Limoges.
        Line::factory()->create(['slug' => 'rfn-2', 'code' => null, 'name' => 'Ligne de Limoges à Brive']);
        Http::fake(fn (Request $r) => str_contains(urldecode($r->url()), 'Brive')
            ? Http::response($this->sruResponse())
            : Http::response($this->emptySruResponse()));
        $this->artisan('vff:import-gallica')->assertSuccessful();
        $this->assertSame([$this->line->id], $this->documentLines());

        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])
            ->expectsOutputToContain('notices lues : 0')
            ->assertSuccessful();
        $this->assertSame([$this->line->id], $this->documentLines());

        $this->artisan('vff:import-gallica', ['--limit' => 1])->assertSuccessful();
        $this->assertSame(1, LineArchiveDocument::query()->count());
    }

    public function test_only_a_complete_import_removes_a_document_that_no_query_finds_any_more(): void
    {
        $responses = [$this->sruResponse()];
        Http::fake(function () use (&$responses) {
            return Http::response($responses[0]);
        });
        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000']])->assertSuccessful();
        $this->assertSame(1, LineArchiveDocument::query()->count());

        // La notice a disparu du catalogue.
        $responses = [$this->emptySruResponse()];
        $this->artisan('vff:import-gallica', ['--line' => ['rfn-610000'], '--refresh' => true])->assertSuccessful();
        $this->assertSame(1, LineArchiveDocument::query()->count(), 'un import partiel ne retire que les notices qu\'il a relues');

        $this->artisan('vff:import-gallica', ['--refresh' => true])
            ->expectsOutputToContain('documents Gallica en base : 1 avant, 0 après.')
            ->assertSuccessful();
        $this->assertSame(0, LineArchiveDocument::query()->count());
    }

    public function test_the_client_waits_a_second_between_requests_and_retries_after_a_429(): void
    {
        Http::fakeSequence('catalogue.bnf.fr/*')
            ->push('', 429, ['Retry-After' => '7'])
            ->push($this->sruResponse())
            ->push($this->sruResponse());
        $client = new CatalogueSruClient(storage_path('app/data/gallica'));

        $this->assertCount(1, $client->search('bib.title all "a"')['records']);
        $this->assertCount(1, $client->search('bib.title all "b"')['records']);

        $this->assertSame(3, $client->networkRequests);
        Sleep::assertSlept(fn ($duration): bool => (int) $duration->totalSeconds === 7, 1);
        // Une pause avant chaque requête qui suit la précédente de moins d'une seconde.
        Sleep::assertSlept(fn ($duration): bool => $duration->totalMicroseconds > 0 && $duration->totalMicroseconds <= 1_000_000, 2);
    }

    public function test_a_refused_query_raises_an_error(): void
    {
        Http::fake(['catalogue.bnf.fr/*' => Http::response('', 400)]);
        $client = new CatalogueSruClient(storage_path('app/data/gallica'));

        $this->expectException(RuntimeException::class);
        $client->search('bib.title all "a"');
    }
}
