<?php

namespace Tests\Feature\Signalling;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Historique de la signalisation daté par des documents (`vff:import-signalling-sources`) : import d'un
 * fichier de faits d'essai, coexistence avec les faits de Wikipédia (aucun import ne retire les faits de
 * l'autre), fait de Wikipédia redit par un document non répété, surfaces REST et GraphQL.
 */
class SignallingSourcesImportTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private string $file;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-752000', 'code' => '752000', 'name' => 'Ligne de Combs-la-Ville à Saint-Louis (LGV)']);
        $this->file = sys_get_temp_dir().'/vff-signalling-sources-'.bin2hex(random_bytes(4)).'.php';
        $this->write([self::fact()]);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if (is_file($this->file)) {
                unlink($this->file);
            }
        }
    }

    /** @param  array<string, mixed>  $over */
    private static function fact(array $over = []): array
    {
        return $over + [
            'line' => '752000', 'system' => 'TVM-300', 'date' => '1981-09-27', 'scope' => 'whole_line',
            'summary' => 'Ouverture commerciale de la première section, équipée de la TVM 300.',
            'sources' => [
                ['publisher' => 'SNCF Réseau', 'title' => 'LGV Sud-Est', 'url' => 'https://www.sncf-reseau.com/fr/lgv-sud-est', 'published' => '2021-09', 'official' => true],
                ['publisher' => 'INA', 'title' => 'Inauguration du TGV', 'url' => 'https://www.ina.fr/video/exemple'],
            ],
        ];
    }

    /** @param  list<array<string, mixed>>  $facts */
    private function write(array $facts): void
    {
        file_put_contents($this->file, '<?php return '.var_export($facts, true).';');
    }

    private function wikipediaFact(string $system, string $date, string $precision): void
    {
        DB::table('line_signalling_events')->insert([
            'line_id' => $this->line->id, 'system' => $system, 'role' => 'cab', 'event_date' => $date, 'date_precision' => $precision,
            'scope' => 'unspecified', 'confidence' => 'medium', 'origin' => 'text', 'source' => 'wikipedia',
            'source_title' => 'LGV Sud-Est', 'source_revision' => 1, 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1',
            'source_excerpt' => 'La TVM est en service en 1981.', 'fingerprint' => sha1($system.$date), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_import_stores_document_facts_with_their_documents(): void
    {
        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file])->assertSuccessful();

        $row = DB::table('line_signalling_events')->sole();
        $this->assertSame(['manual', 'document', 'TVM-300', 'cab', '1981-09-27', 'day', 'high', 'official'],
            [$row->source, $row->origin, $row->system, $row->role, $row->event_date, $row->date_precision, $row->confidence, $row->verification]);
        $this->assertNull($row->source_revision);
        $this->assertSame('https://www.sncf-reseau.com/fr/lgv-sud-est', $row->source_url);
        $this->assertCount(2, json_decode($row->documents, true));
        Http::assertNothingSent();
    }

    public function test_the_import_is_replayable_and_drops_facts_removed_from_the_file_only(): void
    {
        $this->wikipediaFact('ETCS', '2024-01-01', 'year');
        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file])->assertSuccessful();
        $first = DB::table('line_signalling_events')->where('source', 'manual')->pluck('fingerprint')->all();
        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file])->assertSuccessful();
        $this->assertSame($first, DB::table('line_signalling_events')->where('source', 'manual')->pluck('fingerprint')->all());

        $this->write([self::fact(['system' => 'TVM-430', 'date' => '1996'])]);
        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file])->assertSuccessful();

        $this->assertSame(['ETCS', 'TVM-430'], DB::table('line_signalling_events')->orderBy('system')->pluck('system')->all(), 'le fait de Wikipédia reste');
        $this->assertSame('medium', DB::table('line_signalling_events')->where('system', 'TVM-430')->value('confidence'));
    }

    public function test_the_wikipedia_import_never_removes_document_facts(): void
    {
        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file])->assertSuccessful();
        $this->wikipediaFact('ETCS', '2024-01-01', 'year');
        // Article absent du cache : la ligne n'est pas lue, l'import complet retire ses faits de Wikipédia
        // (orphelins), jamais ceux des documents.
        $this->line->update(['wikipedia_title' => 'LGV Sud-Est']);
        $this->artisan('vff:import-signalling-history', ['--offline' => true, '--cache-dir' => sys_get_temp_dir().'/vff-cache-absent-'.bin2hex(random_bytes(4))])->assertSuccessful();

        $this->assertSame(['manual'], DB::table('line_signalling_events')->pluck('source')->all());
    }

    public function test_a_file_with_an_unverified_fact_writes_nothing(): void
    {
        $this->write([self::fact(['sources' => [['publisher' => 'Blog', 'title' => 'Billet', 'url' => 'https://example.org/billet']]])]);

        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file])->assertFailed();
        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_an_unknown_line_is_reported(): void
    {
        $this->write([self::fact(['line' => '999999'])]);

        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file])
            ->expectsOutputToContain('Ligne inconnue : 999999')->assertFailed();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_rest_and_graphql_give_the_documents_and_hide_the_restated_wikipedia_fact(): void
    {
        $this->wikipediaFact('TVM', '1981-01-01', 'year');
        $this->wikipediaFact('ETCS', '2024-01-01', 'year');
        $this->artisan('vff:import-signalling-sources', ['--file' => $this->file])->assertSuccessful();

        $this->getJson('/api/v1/lines/rfn-752000/signalling-history')->assertOk()
            ->assertJsonCount(2, 'history')
            ->assertJsonPath('history.0.system', 'TVM-300')
            ->assertJsonPath('history.0.origin', 'document')
            ->assertJsonPath('history.0.source.provider', 'manual')
            ->assertJsonPath('history.0.source.revision', null)
            ->assertJsonPath('history.0.source.verification', 'official')
            ->assertJsonPath('history.0.source.documents.0', ['publisher' => 'SNCF Réseau', 'title' => 'LGV Sud-Est', 'url' => 'https://www.sncf-reseau.com/fr/lgv-sud-est', 'published' => '2021-09'])
            ->assertJsonPath('history.0.source.documents.1.published', null)
            ->assertJsonPath('history.1.system', 'ETCS')
            ->assertJsonPath('history.1.source.documents', []);

        $this->graphQL('{ line(slug: "rfn-752000") { signalling_history { system origin source { provider revision verification documents { publisher url published } } } } }')
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.line.signalling_history.0.source.provider', 'manual')
            ->assertJsonPath('data.line.signalling_history.0.source.revision', null)
            ->assertJsonPath('data.line.signalling_history.0.source.documents.0.publisher', 'SNCF Réseau')
            ->assertJsonPath('data.line.signalling_history.1.source.documents', []);
    }
}
