<?php

namespace Tests\Feature\Web;

use App\Support\Basemap\BasemapArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Requêtes qui téléchargeraient toute l'archive (6 Go en production) ou plus que le plafond : 416, avec la
     * taille du fichier (Content-Range) et un message qui dit quoi envoyer.
     *
     * @return array<string, array{0: array<string, string>}>
     */
    public static function refusedRanges(): array
    {
        return [
            'sans plage' => [[]],
            'plusieurs plages' => [['Range' => 'bytes=0-1,5-9']],
            'autre unité' => [['Range' => 'items=0-9']],
            'plage mal formée' => [['Range' => 'bytes=abc']],
            'plage vide' => [['Range' => 'bytes=-']],
            'plage à l’envers' => [['Range' => 'bytes=9-0']],
            'hors du fichier' => [['Range' => 'bytes=500-600']],
            'plus grande que le plafond' => [['Range' => 'bytes=0-50']],
            'ouverte, plus grande que le plafond' => [['Range' => 'bytes=10-']],
            'fin du fichier, plus grande que le plafond' => [['Range' => 'bytes=-41']],
        ];
    }

    /** @param  array<string, string>  $headers */
    #[DataProvider('refusedRanges')]
    public function test_requests_that_are_not_one_small_range_are_refused(array $headers): void
    {
        config(['maps.basemap.max_range_bytes' => 40]);

        $response = $this->withHeaders($headers)->get('/basemap/'.self::NAME);

        $response->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */100')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('at most 40 bytes', (string) $response->getContent());
    }

    public function test_ranges_up_to_the_ceiling_are_served(): void
    {
        config(['maps.basemap.max_range_bytes' => 40]);

        $this->withHeaders(['Range' => 'bytes=0-39'])->get('/basemap/'.self::NAME)
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-39/100');
        $this->withHeaders(['Range' => 'bytes=-40'])->get('/basemap/'.self::NAME)
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 60-99/100');
        $this->withHeaders(['Range' => 'bytes=70-'])->get('/basemap/'.self::NAME)
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 70-99/100');
    }

    public function test_the_default_ceiling_covers_the_largest_tile_and_the_client_header_read(): void
    {
        // Plus grosse tuile de l'archive France du 20/11/2026 : 388 105 octets ; en-tête lu par PMTiles : 16 384.
        $this->assertGreaterThan(388_105 * 2, config('maps.basemap.max_range_bytes'));
    }

    public function test_head_needs_no_range(): void
    {
        $this->head('/basemap/'.self::NAME)->assertOk()->assertHeader('Content-Length', '100');
    }

    public function test_a_stale_if_range_does_not_turn_into_a_full_download(): void
    {
        $etag = (string) $this->withHeaders(['Range' => 'bytes=0-6'])->get('/basemap/'.self::NAME)->headers->get('ETag');

        $this->withHeaders(['Range' => 'bytes=0-6', 'If-Range' => $etag])->get('/basemap/'.self::NAME)->assertStatus(206);
        $this->withHeaders(['Range' => 'bytes=0-6', 'If-Range' => '"ancienne"'])->get('/basemap/'.self::NAME)->assertStatus(412);
    }

    public function test_behind_nginx_the_rules_stay_and_nginx_sends_the_bytes(): void
    {
        config(['maps.basemap.accel_redirect' => '/_basemap/', 'maps.basemap.max_range_bytes' => 40]);

        $this->withHeaders(['Range' => 'bytes=0-6'])->get('/basemap/'.self::NAME)
            ->assertOk()
            ->assertHeader('X-Accel-Redirect', '/_basemap/'.self::NAME)
            ->assertContent('');
        $this->flushHeaders()->get('/basemap/'.self::NAME)->assertStatus(416)->assertHeaderMissing('X-Accel-Redirect');
        $this->withHeaders(['Range' => 'bytes=0-50'])->get('/basemap/'.self::NAME)->assertStatus(416);
        $this->withHeaders(['Range' => 'bytes=0-6', 'If-Range' => '"x"'])->get('/basemap/'.self::NAME)->assertStatus(412);
    }

    public function test_requests_per_minute_are_limited_per_address(): void
    {
        config(['maps.basemap.requests_per_minute' => 3]);

        foreach (range(1, 3) as $_) {
            $this->withHeaders(['Range' => 'bytes=0-6'])->get('/basemap/'.self::NAME)->assertStatus(206);
        }
        $refused = $this->withHeaders(['Range' => 'bytes=0-6'])->get('/basemap/'.self::NAME);

        $refused->assertStatus(429);
        $this->assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
        $this->assertStringContainsString('3 per minute', (string) $refused->getContent());

        // Une autre adresse a son propre compteur.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.7'])->withHeaders(['Range' => 'bytes=0-6'])
            ->get('/basemap/'.self::NAME)->assertStatus(206);
    }

    public function test_bytes_per_hour_are_limited_per_address(): void
    {
        config(['maps.basemap.bytes_per_hour' => 100]);

        $this->withHeaders(['Range' => 'bytes=0-59'])->get('/basemap/'.self::NAME)->assertStatus(206);
        $this->withHeaders(['Range' => 'bytes=60-99'])->get('/basemap/'.self::NAME)->assertStatus(206);
        $this->withHeaders(['Range' => 'bytes=0-0'])->get('/basemap/'.self::NAME)->assertStatus(429);
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
