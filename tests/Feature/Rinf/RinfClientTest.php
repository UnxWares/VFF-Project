<?php

namespace Tests\Feature\Rinf;

use App\Services\Rinf\RinfClient;
use App\Services\Rinf\RinfDataset;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Client du point SPARQL de l'ERA : User-Agent du projet, cache disque et manifeste, pagination, budget de
 * requêtes, mode hors ligne sans réseau.
 */
class RinfClientTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/rinf-client-'.getmypid().'/fr');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*.json') ?: [] as $file) {
            unlink($file);
        }
        foreach ([$this->dir, dirname($this->dir)] as $d) {
            if (is_file($d.'/.nobackup')) {
                unlink($d.'/.nobackup');
            }
            if (is_dir($d)) {
                rmdir($d);
            }
        }
        parent::tearDown();
    }

    /** @param  list<int>  $values */
    private static function page(array $values): string
    {
        return (string) json_encode(['head' => ['vars' => ['n']], 'results' => ['bindings' => array_map(fn (int $v): array => ['n' => ['type' => 'literal', 'value' => (string) $v]], $values)]]);
    }

    public function test_pages_are_fetched_politely_then_read_from_the_cache(): void
    {
        Http::fake([RinfDataset::ENDPOINT => Http::sequence()->push(self::page([1, 2]))->push(self::page([3]))]);
        $client = new RinfClient($this->dir, pauseS: 0);

        $rows = $client->selectAll('q', 'SELECT ?n WHERE {}', 2);

        $this->assertSame(['1', '2', '3'], array_map(fn (array $r): string => $r['n']['value'], $rows));
        $this->assertSame(2, $client->requests());
        Http::assertSent(fn (Request $r): bool => $r->header('User-Agent')[0] === 'VFF-Bot/1.0 (+https://vff-project.org)'
            && str_contains((string) $r['query'], 'LIMIT 2 OFFSET 2'));
        $this->assertFileExists($this->dir.'/q-001.json');
        $this->assertArrayHasKey('q-000', (array) json_decode((string) File::get($this->dir.'/manifest.json'), true));
        $this->assertFileExists(dirname($this->dir).'/.nobackup');

        // Deuxième passage, hors ligne : tout vient du cache, aucune requête.
        $offline = new RinfClient($this->dir, offline: true);
        $this->assertCount(3, $offline->selectAll('q', 'SELECT ?n WHERE {}', 2));
        $this->assertSame(0, $offline->requests());
        $this->assertNotNull($offline->fetchedAt());
    }

    public function test_the_request_budget_and_offline_mode_stop_before_the_network(): void
    {
        Http::fake([RinfDataset::ENDPOINT => Http::response(self::page([1]))]);
        $client = new RinfClient($this->dir, budget: 1, pauseS: 0);
        $client->select('a', 'SELECT 1');

        try {
            $client->select('b', 'SELECT 2');
            $this->fail('budget dépassé');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Budget de 1 requêtes', $e->getMessage());
        }
        Http::assertSentCount(1);

        $this->expectException(RuntimeException::class);
        (new RinfClient($this->dir, offline: true))->select('absente', 'SELECT 3');
    }

    public function test_an_http_error_is_reported_and_nothing_is_cached(): void
    {
        Http::fake([RinfDataset::ENDPOINT => Http::response('busy', 503)]);

        try {
            (new RinfClient($this->dir, pauseS: 0))->select('x', 'SELECT 1');
            $this->fail('erreur attendue');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
        }
        $this->assertFileDoesNotExist($this->dir.'/x.json');
    }
}
