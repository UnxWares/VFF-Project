<?php

namespace Tests\Feature\Services;

use App\Services\StationService\FeedDefinition;
use App\Services\StationService\GtfsArchive;
use App\Services\StationService\GtfsFeedStore;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use Throwable;
use ZipArchive;

/**
 * Cache local des flux GTFS (GtfsFeedStore) et lecture d'une archive (GtfsArchive), sans réseau :
 *   - un flux en cache n'est pas retéléchargé quand on le demande ;
 *   - un téléchargement refusé ou qui n'est pas un zip lève une erreur et ne laisse pas de fichier ;
 *   - le dossier de cache porte un .nobackup ;
 *   - un flux archivé est choisi dans l'historique de transport.data.gouv.fr, au plus près du jour visé.
 */
class GtfsFeedStoreTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/vff-gtfs-store-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->root);
        $root = $this->root;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));
    }

    private function store(): GtfsFeedStore
    {
        return new GtfsFeedStore(app(Factory::class), $this->root);
    }

    /** @param  array<string, mixed>  $overrides */
    private function feed(array $overrides = []): FeedDefinition
    {
        return FeedDefinition::fromArray($overrides + [
            'key' => 'ter', 'name' => 'TER', 'provider' => 'sncf', 'licence' => 'ODbL', 'page' => 'https://example.org',
            'url' => 'https://example.org/ter.zip', 'modes' => ['rail'],
        ]);
    }

    private function zipBytes(): string
    {
        $path = $this->root.'/fixture.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('stops.txt', "\xEF\xBB\xBFstop_id, stop_name\nA,Alpha\nB\n\n");
        $zip->addFromString('sub/routes.txt', "route_id,route_short_name\nR1,TER1\n");
        $zip->close();

        return (string) file_get_contents($path);
    }

    public function test_a_feed_is_downloaded_then_cached_with_its_source(): void
    {
        Http::fake(['example.org/*' => Http::response($this->zipBytes())]);

        $path = $this->store()->fetch($this->feed(), fromCache: false, today: 20261118);

        $this->assertSame($this->root.'/ter/gtfs.zip', $path);
        $this->assertFileExists($path);
        $this->assertFileDoesNotExist($path.'.part');
        $this->assertFileExists($this->root.'/.nobackup');
        $source = json_decode((string) file_get_contents($this->root.'/ter/source.json'), true);
        $this->assertSame('https://example.org/ter.zip', $source['url']);
        $this->assertNotEmpty($source['fetched_at']);
    }

    public function test_a_cached_feed_is_not_downloaded_again_on_request(): void
    {
        Http::fake(['example.org/*' => Http::response($this->zipBytes())]);
        $this->store()->fetch($this->feed(), false, 20261118);

        $this->store()->fetch($this->feed(), true, 20261118);

        Http::assertSentCount(1);
    }

    public function test_without_the_cache_option_the_feed_is_always_downloaded(): void
    {
        // Une réponse par appel : le corps d'une réponse simulée ne se relit pas.
        Http::fake(['example.org/*' => Http::sequence()->push($this->zipBytes())->push($this->zipBytes())]);

        $this->store()->fetch($this->feed(), false, 20261118);
        $this->store()->fetch($this->feed(), false, 20261118);

        Http::assertSentCount(2);
    }

    public function test_a_refused_download_is_an_error_and_leaves_no_feed(): void
    {
        Http::fake(['example.org/*' => Http::response('', 503)]);

        try {
            $this->store()->fetch($this->feed(), false, 20261118);
            $this->fail('Une exception était attendue');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }
        $this->assertFileDoesNotExist($this->root.'/ter/gtfs.zip');
    }

    public function test_something_that_is_not_a_zip_is_refused(): void
    {
        Http::fake(['example.org/*' => Http::response('<html>Maintenance</html>')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas une archive zip");

        $this->store()->fetch($this->feed(), false, 20261118);
    }

    public function test_an_arbitrary_file_follows_the_same_cache_rule(): void
    {
        Http::fake(['example.org/*' => Http::response('a,b')]);

        $first = $this->store()->fetchFile('https://example.org/f.csv', 'freq.csv', false);
        $this->store()->fetchFile('https://example.org/f.csv', 'freq.csv', true);

        $this->assertSame($this->root.'/freq.csv', $first);
        Http::assertSentCount(1);
    }

    // ── Flux archivé ────────────────────────────────────────────────────────

    private const HISTORY = <<<'CSV'
        resource_id,permanent_url,inserted_at
        10,https://data.example/ancien.zip,2026-05-20T10:00:00Z
        10,https://data.example/proche.zip,2026-07-12T10:00:00Z
        10,https://data.example/tard.zip,2026-10-01T10:00:00Z
        11,https://data.example/autre-ressource.zip,2026-07-15T10:00:00Z
        10,,2026-07-14T10:00:00Z
        CSV;

    public function test_the_closest_archived_version_of_the_resource_is_chosen(): void
    {
        $csv = $this->root.'/history.csv';
        file_put_contents($csv, self::HISTORY);

        $this->assertSame('https://data.example/proche.zip', GtfsFeedStore::closestVersion($csv, 10, 20260715));
        $this->assertSame('https://data.example/ancien.zip', GtfsFeedStore::closestVersion($csv, 10, 20260101));
        $this->assertSame('https://data.example/tard.zip', GtfsFeedStore::closestVersion($csv, 10, 20261231));
        $this->assertSame('https://data.example/autre-ressource.zip', GtfsFeedStore::closestVersion($csv, 11, 20260715));
    }

    public function test_an_unknown_resource_gives_no_version(): void
    {
        $csv = $this->root.'/history.csv';
        file_put_contents($csv, self::HISTORY);

        $this->assertNull(GtfsFeedStore::closestVersion($csv, 99, 20260715));
    }

    public function test_a_missing_history_file_is_an_error(): void
    {
        $this->expectException(Throwable::class);

        GtfsFeedStore::closestVersion($this->root.'/absent.csv', 10, 20260715);
    }

    /** Un historique absent lève l'exception documentée, pas l'avertissement de fopen converti par Laravel. */
    public function test_a_missing_history_file_raises_the_documented_runtime_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Historique illisible');

        GtfsFeedStore::closestVersion($this->root.'/absent.csv', 10, 20260715);
    }

    public function test_an_archived_feed_is_fetched_from_the_history_of_the_dataset(): void
    {
        Http::fake([
            'transport.data.gouv.fr/*' => Http::response(self::HISTORY),
            'data.example/*' => Http::response($this->zipBytes()),
        ]);
        $feed = $this->feed(['url' => null, 'history' => ['dataset' => 555, 'resource' => 10, 'day' => '07-15']]);

        $path = $this->store()->fetch($feed, false, 20261118);

        $this->assertFileExists($path);
        Http::assertSent(fn ($r) => $r->url() === 'https://data.example/proche.zip');
        $this->assertFileExists($this->root.'/history-555.csv');
        $source = json_decode((string) file_get_contents($this->root.'/ter/source.json'), true);
        $this->assertSame('https://data.example/proche.zip', $source['url']);
    }

    public function test_an_archived_feed_without_any_version_is_an_error(): void
    {
        Http::fake(['transport.data.gouv.fr/*' => Http::response("resource_id,permanent_url,inserted_at\n")]);
        $feed = $this->feed(['url' => null, 'history' => ['dataset' => 555, 'resource' => 10, 'day' => '07-15']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('aucune version archivée');

        $this->store()->fetch($feed, false, 20261118);
    }

    // ── Lecture de l'archive ────────────────────────────────────────────────

    public function test_archive_rows_are_keyed_by_a_trimmed_header_without_bom_and_padded(): void
    {
        $zipPath = $this->root.'/a.zip';
        file_put_contents($zipPath, $this->zipBytes());
        $archive = new GtfsArchive($zipPath);

        $this->assertTrue($archive->has('stops.txt'));
        $this->assertTrue($archive->has('routes.txt'), 'les fichiers d\'un sous-dossier sont trouvés par leur nom');
        $this->assertFalse($archive->has('trips.txt'));
        $this->assertSame([
            ['stop_id' => 'A', 'stop_name' => 'Alpha'],
            ['stop_id' => 'B', 'stop_name' => ''],
        ], iterator_to_array($archive->rows('stops.txt'), false));
        $this->assertSame([], iterator_to_array($archive->rows('trips.txt'), false));
    }

    public function test_an_unreadable_archive_is_an_error(): void
    {
        file_put_contents($this->root.'/mauvais.zip', 'pas un zip');

        $this->expectException(RuntimeException::class);

        new GtfsArchive($this->root.'/mauvais.zip');
    }
}
