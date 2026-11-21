<?php

namespace Tests\Feature\Web;

use App\Support\Basemap\BasemapArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fond de carte libre auto-hébergé : l'archive PMTiles configurée est servie par plages d'octets (206), sans
 * session ni cookie, avec un cache HTTP long ; aucun autre fichier n'est servi ; les pages la décrivent dans
 * la propriété partagée `basemap` (null sans archive).
 */
class BasemapArchiveTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'essai-20261120.pmtiles';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/vff-basemap-test-'.getmypid();
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0o755, true);
        }
        file_put_contents($this->directory.'/'.self::NAME, 'PMTiles'.str_repeat('x', 93));
        file_put_contents($this->directory.'/autre-20261120.pmtiles', 'secret');

        config([
            'maps.basemap.archive' => self::NAME,
            'maps.basemap.directory' => $this->directory,
            'maps.basemap.online' => false,
            'maps.basemap.max_age' => 2_592_000,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach ([self::NAME, 'autre-20261120.pmtiles'] as $file) {
                if (is_file($this->directory.'/'.$file)) {
                    unlink($this->directory.'/'.$file);
                }
            }
            if (is_dir($this->directory)) {
                rmdir($this->directory);
            }
        }
    }

    public function test_a_byte_range_is_served_as_partial_content(): void
    {
        $response = $this->withHeaders(['Range' => 'bytes=0-6'])->get('/basemap/'.self::NAME);

        $response->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-6/100')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertSame('PMTiles', $response->streamedContent());
        $this->assertNotEmpty($response->headers->get('ETag'));

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=2592000', $cache);
        $this->assertSame([], $response->headers->getCookies(), 'ni session ni cookie');
    }

    public function test_the_whole_file_is_served_without_range(): void
    {
        $response = $this->get('/basemap/'.self::NAME);

        $response->assertOk()->assertHeader('Content-Length', '100');
        $this->assertSame(100, strlen($response->streamedContent()));
    }

    public function test_an_unsatisfiable_range_is_refused(): void
    {
        $this->withHeaders(['Range' => 'bytes=500-600'])->get('/basemap/'.self::NAME)->assertStatus(416);
    }

    public function test_only_the_configured_archive_is_served(): void
    {
        $this->get('/basemap/autre-20261120.pmtiles')->assertNotFound();
        $this->get('/basemap/inconnue.pmtiles')->assertNotFound();
        $this->get('/basemap/..%2F.env')->assertNotFound();
        $this->get('/basemap/'.strtoupper(self::NAME))->assertNotFound();
    }

    public function test_a_missing_file_or_an_invalid_name_disables_the_basemap(): void
    {
        config(['maps.basemap.archive' => 'absente-20261120.pmtiles']);
        $this->get('/basemap/absente-20261120.pmtiles')->assertNotFound();
        $this->assertNull(BasemapArchive::fromConfig()->clientConfig());

        config(['maps.basemap.archive' => '../'.self::NAME]);
        $this->assertNull(BasemapArchive::fromConfig()->path());
        $this->assertNull(BasemapArchive::fromConfig()->clientConfig());

        config(['maps.basemap.archive' => '']);
        $this->assertNull(BasemapArchive::fromConfig()->clientConfig());
    }

    public function test_pages_share_the_basemap_settings(): void
    {
        $page = $this->get('/maps')->assertOk()->viewData('page');

        $this->assertSame([
            'url' => '/basemap/'.self::NAME,
            'online' => false,
            'attribution' => BasemapArchive::ATTRIBUTION,
        ], $page['props']['basemap']);
        $this->assertStringContainsString('© OpenStreetMap', BasemapArchive::ATTRIBUTION);
    }

    public function test_the_online_option_reaches_the_pages(): void
    {
        config(['maps.basemap.online' => true]);

        $page = $this->get('/maps')->assertOk()->viewData('page');

        $this->assertTrue($page['props']['basemap']['online']);
    }

    public function test_pages_share_null_without_archive(): void
    {
        config(['maps.basemap.archive' => null]);

        $page = $this->get('/maps')->assertOk()->viewData('page');

        $this->assertNull($page['props']['basemap']);
    }
}
