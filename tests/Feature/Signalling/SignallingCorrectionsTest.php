<?php

namespace Tests\Feature\Signalling;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * Corrections sourcées de l'historique de la signalisation : un fait (de Wikipédia ou d'un document) que des
 * documents contredisent n'est plus montré nulle part, sa correction reste consultable, un fait visé introuvable
 * est une erreur et un `replace` range le fait juste.
 */
class SignallingCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private const ARTICLE = <<<'WIKI'
{{Infobox Ligne ferroviaire
| numéro = 974 000
}}
La '''ligne de Saint-Cloud à Saint-Nom-la-Bretèche''' est une ligne ferroviaire française.
== Histoire ==
En juin 1962, le block automatique lumineux est étendu de Garches à Saint-Nom-la-Bretèche.
Le KVB est installé en 1989.
WIKI;

    private string $dir;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-974000', 'code' => '974000', 'name' => 'Ligne de Saint-Cloud', 'wikipedia_title' => 'Ligne de Saint-Cloud']);
        $this->dir = sys_get_temp_dir().'/vff-signalling-corr-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir.'/'.md5('Ligne de Saint-Cloud').'.json', json_encode([
            'requested' => 'Ligne de Saint-Cloud', 'missing' => false, 'title' => 'Ligne de Saint-Cloud', 'revid' => 240844633, 'content' => self::ARTICLE, 'fetched_at' => '2026-11-19 10:00:00',
        ]));
        $this->writeSources([]);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach (glob($this->dir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_file($this->dir.'/.nobackup')) {
                unlink($this->dir.'/.nobackup');
            }
            if (is_dir($this->dir)) {
                rmdir($this->dir);
            }
        }
    }

    /** @param  list<array<string, mixed>>  $facts */
    private function writeSources(array $facts): void
    {
        file_put_contents($this->dir.'/sources.php', '<?php return '.var_export($facts, true).';');
    }

    /** @param  array<string, mixed>  $over */
    private function correction(array $over = []): array
    {
        return $over + [
            'line' => '974000', 'action' => 'neutralize',
            'target' => ['source' => 'wikipedia', 'system' => 'KVB', 'date' => '1989'],
            'sources' => [
                ['publisher' => 'Presse A', 'title' => 'Article A', 'url' => 'https://a.example/x', 'published' => '2024-11-13'],
                ['publisher' => 'Presse B', 'title' => 'Article B', 'url' => 'https://b.example/y'],
            ],
            'quote' => 'le KVB date de 1991', 'reason' => 'le KVB est de 1991, pas de 1989',
        ];
    }

    /** @param  list<array<string, mixed>>  $corrections */
    private function writeCorrections(array $corrections): string
    {
        $path = $this->dir.'/corrections.php';
        file_put_contents($path, '<?php return '.var_export($corrections, true).';');

        return $path;
    }

    private function importWikipedia(string $corrections): PendingCommand
    {
        return $this->artisan('vff:import-signalling-history', ['--offline' => true, '--cache-dir' => $this->dir, '--corrections' => $corrections]);
    }

    public function test_a_neutralized_wikipedia_fact_is_kept_hidden_with_its_correction(): void
    {
        $this->importWikipedia($this->writeCorrections([$this->correction()]))->assertSuccessful();

        $row = DB::table('line_signalling_events')->where('system', 'KVB')->sole();
        $this->assertSame('hidden', $row->confidence);
        $this->assertSame('le KVB est de 1991, pas de 1989', json_decode($row->correction, true)['reason']);
        $this->assertSame(1, DB::table('line_signalling_events')->whereNotNull('correction')->count());
        $this->assertSame('high', DB::table('line_signalling_events')->where('system', 'BAL')->value('confidence'), 'les autres faits ne bougent pas');
    }

    public function test_the_hidden_fact_is_neither_on_the_sheet_nor_in_the_api_but_its_correction_is(): void
    {
        $this->importWikipedia($this->writeCorrections([$this->correction()]))->assertSuccessful();

        $body = $this->getJson('/api/v1/lines/rfn-974000/signalling-history?era=2030')->assertOk()->json();
        $this->assertSame(['BAL'], array_column($body['history'], 'system'));
        $this->assertSame(['BAL'], array_column($body['in_force'], 'system'));
        $this->assertCount(1, $body['corrections']);
        $c = $body['corrections'][0];
        $this->assertSame(['KVB', '1989-01-01', 'year', 'neutralize'], [$c['system'], $c['date'], $c['date_precision'], $c['action']]);
        $this->assertSame('le KVB est de 1991, pas de 1989', $c['reason']);
        $this->assertSame('wikipedia', $c['original']['provider']);
        $this->assertSame(['Presse A', 'Presse B'], array_column($c['sources'], 'publisher'));
        $this->assertSame('2024-11-13', $c['sources'][0]['published']);
        $this->assertNull($c['sources'][1]['published']);
    }

    public function test_without_a_correction_the_fact_comes_back_on_the_next_import(): void
    {
        $this->importWikipedia($this->writeCorrections([$this->correction()]))->assertSuccessful();
        $this->importWikipedia($this->writeCorrections([]))->assertSuccessful();

        $row = DB::table('line_signalling_events')->where('system', 'KVB')->sole();
        $this->assertSame('medium', $row->confidence);
        $this->assertNull($row->correction);
    }

    public function test_a_target_that_is_not_found_is_an_error_and_the_run_reports_it(): void
    {
        $path = $this->writeCorrections([$this->correction(['target' => ['source' => 'wikipedia', 'system' => 'KVB', 'date' => '1990']])]);

        $this->importWikipedia($path)->expectsOutputToContain('Correction sans fait visé : 974000 KVB du 1990-01-01 (wikipedia)')->assertFailed();
    }

    public function test_an_invalid_corrections_file_writes_nothing(): void
    {
        $path = $this->writeCorrections([$this->correction(['reason' => ''])]);

        $this->importWikipedia($path)->assertFailed();
        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }

    public function test_a_correction_of_a_line_that_is_not_in_the_run_is_not_an_error(): void
    {
        $path = $this->writeCorrections([$this->correction(['line' => '752000', 'target' => ['source' => 'wikipedia', 'system' => 'ETCS', 'date' => '2024-11-12']])]);

        $this->artisan('vff:import-signalling-history', ['--offline' => true, '--cache-dir' => $this->dir, '--corrections' => $path, '--line' => ['974000']])->assertSuccessful();
    }

    public function test_a_document_fact_can_be_neutralized_and_replaced_by_the_right_fact(): void
    {
        $official = [['publisher' => 'SNCF Réseau', 'title' => 'Communiqué', 'url' => 'https://www.sncf-reseau.com/x', 'official' => true]];
        $this->writeSources([[
            'line' => '974000', 'system' => 'BAL', 'date' => '1962-06-15', 'scope' => 'whole_line', 'summary' => 'Mise en service du BAL.', 'sources' => $official,
        ]]);
        $path = $this->writeCorrections([$this->correction([
            'action' => 'replace',
            'target' => ['source' => 'manual', 'system' => 'BAL', 'date' => '1962-06-15'],
            'with' => ['system' => 'BAL', 'date' => '1963-06-15', 'scope' => 'whole_line', 'summary' => 'Mise en service du BAL, un an plus tard.', 'sources' => $official],
        ])]);

        $this->artisan('vff:import-signalling-sources', ['--file' => $this->dir.'/sources.php', '--corrections' => $path])->assertSuccessful();

        $this->assertSame(['hidden', 'high'], DB::table('line_signalling_events')->orderBy('event_date')->pluck('confidence')->all());
        $body = $this->getJson('/api/v1/lines/rfn-974000/signalling-history')->assertOk()->json();
        $this->assertSame(['1963-06-15'], array_column($body['history'], 'date'));
        $this->assertSame(['replace'], array_column($body['corrections'], 'action'));
    }

    public function test_a_manual_target_that_is_not_found_writes_nothing(): void
    {
        $path = $this->writeCorrections([$this->correction(['target' => ['source' => 'manual', 'system' => 'BAL', 'date' => '1962-06-15']])]);

        $this->artisan('vff:import-signalling-sources', ['--file' => $this->dir.'/sources.php', '--corrections' => $path])
            ->expectsOutputToContain('Correction sans fait visé')->assertFailed();
        $this->assertSame(0, DB::table('line_signalling_events')->count());
    }
}
