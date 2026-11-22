<?php

namespace Tests\Feature\Media;

use App\Http\Controllers\CommonsImageController;
use App\Services\Media\CommonsImageCache;
use App\Support\Media\CommonsImagePath;
use App\Support\Security\ContentSecurityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Relais des vignettes de Commons (GET /media/commons/{chemin}, CommonsImageController) : liste blanche,
 * types, cache disque, en-têtes, absence de cookie, limites. Wikimedia est simulé (Http::fake), le cache vit
 * dans un dossier temporaire propre à chaque test.
 */
class CommonsImageRelayTest extends TestCase
{
    use RefreshDatabase;

    private const THUMB = 'thumb/3/36/Gare-Roscoff-CPancienne.jpg/500px-Gare-Roscoff-CPancienne.jpg';

    private const ORIGINAL = '7/79/CFA-Morlaix.jpg';

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/vff-commons-relay-'.bin2hex(random_bytes(6));
        $dir = $this->cacheDir;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        config([
            'commons_proxy.enabled' => true,
            'commons_proxy.cache_path' => $dir,
            'commons_proxy.cache_max_mb' => 1,
            'commons_proxy.ttl_days' => 30,
            'commons_proxy.requests_per_minute' => 600,
            'commons_proxy.upstream_per_minute' => 300,
            'commons_proxy.upstream_per_client_per_minute' => 120,
            'commons_proxy.max_bytes' => 8_388_608,
            'commons_proxy.accel_redirect' => '',
        ]);
        Http::preventStrayRequests();
    }

    private static function jpeg(int $width = 500, int $height = 300): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    private static function webp(int $width = 500, int $height = 300): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagewebp($image);

        return (string) ob_get_clean();
    }

    private static function png(int $width = 330, int $height = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** @var array{string, int, array<string, string>} réponse simulée de Commons, modifiable dans un même test */
    private array $upstream = ['', 500, []];

    private bool $faked = false;

    /** @param  array<string, string>  $headers */
    private function fakeCommons(string $body, string $type = 'image/jpeg', int $status = 200, array $headers = []): void
    {
        $this->upstream = [$body, $status, ['Content-Type' => $type] + $headers];
        if (! $this->faked) {
            $this->faked = true;
            Http::fake(['https://upload.wikimedia.org/*' => fn () => Http::response(...$this->upstream)]);
        }
    }

    /** @return TestResponse<Response> */
    private function relay(string $path): TestResponse
    {
        return $this->get(CommonsImageController::PREFIX.$path);
    }

    public function test_disabled_relay_answers_404_without_calling_commons(): void
    {
        config(['commons_proxy.enabled' => false]);
        Http::fake();

        $this->relay(self::THUMB)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_serves_a_thumbnail_with_long_cache_nosniff_sandbox_and_no_cookie(): void
    {
        $this->fakeCommons(self::jpeg());

        $response = $this->relay(self::THUMB)->assertOk();

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=2592000', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertSame([], $response->headers->getCookies());

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === CommonsImagePath::UPSTREAM_BASE.self::THUMB
                && str_starts_with($request->header('User-Agent')[0] ?? '', 'VFF-ImageRelay/');
        });
    }

    public function test_serves_the_cached_copy_without_a_second_request(): void
    {
        $this->fakeCommons(self::jpeg());
        $this->relay(self::THUMB)->assertOk();

        $second = $this->relay(self::THUMB)->assertOk();

        Http::assertSentCount(1);
        $this->assertSame('image/jpeg', $second->headers->get('Content-Type'));
        $this->assertFileExists($this->cacheDir.'/.nobackup');
        $this->assertFileExists($this->cacheDir.'/'.CommonsImageCache::MARKER);
    }

    public function test_answers_304_to_a_conditional_request(): void
    {
        $this->fakeCommons(self::jpeg());
        $etag = (string) $this->relay(self::THUMB)->assertOk()->headers->get('ETag');

        $this->get(CommonsImageController::PREFIX.self::THUMB, ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_serves_a_png_thumbnail_of_an_svg_file_as_png(): void
    {
        $this->fakeCommons(self::png(), 'image/png');

        $this->relay('thumb/3/3f/Plan.svg/330px-Plan.svg.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_serves_a_jpeg_thumbnail_that_commons_converted_to_webp_but_never_a_webp_original(): void
    {
        $this->fakeCommons(self::webp(), 'image/webp');

        $this->relay(self::THUMB)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->relay(self::THUMB)->assertOk()->assertHeader('Content-Type', 'image/webp');
        Http::assertSentCount(1);
        Http::assertSent(fn (ClientRequest $request): bool => ($request->header('Accept')[0] ?? '') === 'image/webp,image/jpeg');

        $this->relay(self::ORIGINAL)->assertNotFound();
    }

    public function test_refuses_a_foreign_host_or_a_trapped_path_without_calling_commons(): void
    {
        Http::fake();

        foreach ([
            'https://evil.example/x.jpg',
            '..%2F..%2F.env',
            '7/79/..%2F..%2F.env',
            '7/79/%2E%2E',
            '7/80/CFA-Morlaix.jpg',
            '7/79/CFA%252FMorlaix.jpg',
            '7/79/CFA-Morlaix%00.jpg',
            '3/3f/Plan.svg',
            'thumb/7/79/CFA-Morlaix.jpg/4000px-CFA-Morlaix.jpg',
            '//127.0.0.1/7/79/CFA-Morlaix.jpg',
        ] as $path) {
            $response = $this->relay($path);
            $this->assertSame(404, $response->status(), $path);
            $this->assertFalse($response->headers->has('Set-Cookie'), $path);
        }
        $this->get(CommonsImageController::PREFIX.self::ORIGINAL.'?host=evil.example')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_refuses_svg_html_or_a_lying_content_type(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        foreach ([[$svg, 'image/svg+xml'], ['<html><script>alert(1)</script></html>', 'text/html'], [$svg, 'image/jpeg'], [self::png(), 'image/jpeg']] as [$body, $type]) {
            $this->fakeCommons($body, $type);
            $response = $this->relay(self::ORIGINAL);
            $this->assertSame(404, $response->status(), $type);
            $this->assertStringNotContainsString('<script>', (string) $response->getContent());
        }
        $this->assertNull(CommonsImageCache::fromConfig()->find(CommonsImagePath::parse(self::ORIGINAL)));
    }

    public function test_refuses_a_thumbnail_wider_than_requested_or_too_heavy(): void
    {
        $this->fakeCommons(self::jpeg(800, 400));
        $this->relay(self::THUMB)->assertNotFound();

        config(['commons_proxy.max_bytes' => 100]);
        $this->fakeCommons(self::jpeg());
        $this->relay('thumb/7/79/CFA-Morlaix.jpg/500px-CFA-Morlaix.jpg')->assertNotFound();
    }

    public function test_follows_no_redirect_outside_the_whitelist(): void
    {
        $this->fakeCommons('', 'text/html', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']);
        $this->relay(self::ORIGINAL)->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_follows_one_redirect_to_the_same_image_on_the_fixed_host(): void
    {
        $url = CommonsImagePath::UPSTREAM_BASE.self::ORIGINAL;
        Http::fake([
            $url => Http::sequence()
                ->push('', 301, ['Location' => 'https://thumb.wikimedia.org/wikipedia/commons/'.self::ORIGINAL])
                ->push(self::jpeg(400, 300), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->relay(self::ORIGINAL)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_missing_image_is_remembered_and_unreachable_commons_gives_502(): void
    {
        $this->fakeCommons('Not Found', 'text/plain', 404);
        $this->relay(self::ORIGINAL)->assertNotFound();
        $this->relay(self::ORIGINAL)->assertNotFound();
        Http::assertSentCount(1);

        $this->fakeCommons('Error', 'text/plain', 503);
        $this->relay(self::THUMB)->assertStatus(502);
    }

    public function test_serves_a_stale_copy_when_commons_is_down(): void
    {
        $this->fakeCommons(self::jpeg());
        $this->relay(self::THUMB)->assertOk();
        $file = (string) CommonsImageCache::fromConfig()->find(CommonsImagePath::parse(self::THUMB));
        touch($file, time() - 40 * 86_400);

        $this->fakeCommons('Error', 'text/plain', 503);
        $this->relay(self::THUMB)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_upstream_limit_answers_429_with_retry_after(): void
    {
        config(['commons_proxy.upstream_per_minute' => 1]);
        $this->fakeCommons(self::jpeg());
        $this->relay(self::THUMB)->assertOk();

        $this->relay('thumb/7/79/CFA-Morlaix.jpg/500px-CFA-Morlaix.jpg')->assertStatus(429)->assertHeader('Retry-After');
        // Une vignette déjà gardée reste servie.
        $this->relay(self::THUMB)->assertOk();
    }

    public function test_commons_429_pauses_every_upstream_read_and_keeps_stale_copies(): void
    {
        $this->fakeCommons('Too Many Requests', 'text/plain', 429, ['Retry-After' => '60']);
        $this->relay(self::THUMB)->assertStatus(429)->assertHeader('Retry-After');
        $this->relay(self::ORIGINAL)->assertStatus(429);
        Http::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->fakeCommons(self::jpeg(400, 300));
        $this->relay(self::ORIGINAL)->assertOk();
    }

    public function test_one_client_cannot_take_the_whole_upstream_allowance(): void
    {
        config(['commons_proxy.upstream_per_client_per_minute' => 1]);
        $this->fakeCommons(self::jpeg());
        $this->relay(self::THUMB)->assertOk();

        $this->relay('thumb/7/79/CFA-Morlaix.jpg/500px-CFA-Morlaix.jpg')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.7'])
            ->get(CommonsImageController::PREFIX.'thumb/7/79/CFA-Morlaix.jpg/500px-CFA-Morlaix.jpg')->assertOk();
    }

    public function test_per_ip_limit_answers_429_in_plain_text(): void
    {
        config(['commons_proxy.requests_per_minute' => 2]);
        $this->fakeCommons(self::jpeg());
        $this->relay(self::THUMB)->assertOk();
        $this->relay(self::THUMB)->assertOk();

        $response = $this->relay(self::THUMB)->assertStatus(429);
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertFalse($response->headers->has('Set-Cookie'));
    }

    public function test_accel_redirect_hands_the_cached_file_to_nginx(): void
    {
        config(['commons_proxy.accel_redirect' => '/_commons/']);
        $this->fakeCommons(self::jpeg());

        $response = $this->relay(self::THUMB)->assertOk();

        $relative = CommonsImageCache::fromConfig()->relativePath(CommonsImagePath::parse(self::THUMB), 'image/jpeg');
        $this->assertSame('/_commons/'.$relative, $response->headers->get('X-Accel-Redirect'));
        $this->assertSame('', (string) $response->getContent());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_prune_removes_expired_then_oldest_entries_and_nothing_else(): void
    {
        $cache = CommonsImageCache::fromConfig();
        $old = $cache->put(CommonsImagePath::parse('thumb/7/79/CFA-Morlaix.jpg/500px-CFA-Morlaix.jpg'), str_repeat('a', 600_000), 'image/jpeg');
        $recent = $cache->put(CommonsImagePath::parse(self::THUMB), str_repeat('b', 600_000), 'image/jpeg');
        $expired = $cache->put(CommonsImagePath::parse(self::ORIGINAL), 'c', 'image/jpeg');
        touch($old, time() - 3600);
        touch($expired, time() - 31 * 86_400);
        $foreign = $this->cacheDir.'/ab/notes.txt';
        File::ensureDirectoryExists(dirname($foreign));
        file_put_contents($foreign, 'à garder');

        $this->artisan('vff:commons-cache-prune')->assertExitCode(0);

        $this->assertFileDoesNotExist($expired);
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
        $this->assertFileExists($foreign);
        $this->assertFileExists($this->cacheDir.'/.nobackup');
    }

    public function test_prune_does_nothing_in_a_folder_without_the_cache_marker(): void
    {
        $dir = $this->cacheDir.'-autre';
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        File::ensureDirectoryExists($dir.'/ab');
        $file = $dir.'/ab/'.str_repeat('a', 64).'.jpg';
        file_put_contents($file, 'x');
        touch($file, time() - 400 * 86_400);

        $result = (new CommonsImageCache($dir, 1, 60))->prune();

        $this->assertSame(0, $result['removed']);
        $this->assertFileExists($file);
    }

    public function test_pages_share_the_relay_prefix_and_csp_drops_wikimedia_hosts_only_when_enabled(): void
    {
        $sources = (array) config('csp.sources.img-src');
        $this->assertNotContains('https://upload.wikimedia.org', ContentSecurityPolicy::imageSources($sources));
        $this->assertNotContains('https://thumb.wikimedia.org', ContentSecurityPolicy::imageSources($sources));
        $this->assertContains('https://gallica.bnf.fr', ContentSecurityPolicy::imageSources($sources));

        $page = $this->get('/journal')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('commons_proxy', '/media/commons/'));
        $csp = (string) $page->headers->get(ContentSecurityPolicy::headerName());
        $this->assertStringNotContainsString('upload.wikimedia.org', $csp);

        config(['commons_proxy.enabled' => false]);
        $this->assertContains('https://upload.wikimedia.org', ContentSecurityPolicy::imageSources($sources));
        $page = $this->get('/journal')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('commons_proxy', null));
        $this->assertStringContainsString('https://upload.wikimedia.org', (string) $page->headers->get(ContentSecurityPolicy::headerName()));
    }
}
