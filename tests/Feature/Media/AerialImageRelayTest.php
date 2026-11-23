<?php

namespace Tests\Feature\Media;

use App\Models\Station;
use App\Services\Media\Aerial\AerialImages;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Vues aériennes historiques des gares (/media/aerial/{gare}/{période}.jpg) : emprise tirée de la seule position
 * de la gare, PNG du WMS réencodé en JPEG, vue hors couverture retenue en 404, en-têtes du relais d'images.
 */
class AerialImageRelayTest extends TestCase
{
    use RefreshDatabase;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/vff-aerial-'.bin2hex(random_bytes(6));
        $dir = $this->cacheDir;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        config(['vff.media.aerial.enabled' => true, 'vff.media.aerial.cache_path' => $dir, 'vff.media.aerial.upstream_per_minute' => 30]);
        Cache::flush();
        Http::preventStrayRequests();
        Station::factory()->create(['slug' => 'gare-aerienne', 'name' => 'Aérienne', 'geom' => Point::makeGeodetic(48.0, 2.0)]);
    }

    /** PNG de 640 x 384 : `photo` (bruit de gris), `blank` (transparent) ou `flat` (gris uni). */
    private static function png(string $kind): string
    {
        $img = imagecreatetruecolor(640, 384);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        for ($y = 0; $y < 384; $y += 4) {
            for ($x = 0; $x < 640; $x += 4) {
                $color = match ($kind) {
                    'blank' => imagecolorallocatealpha($img, 0, 0, 0, 127),
                    'flat' => imagecolorallocate($img, 200, 200, 200),
                    default => imagecolorallocate($img, ($x * 7 + $y * 13) % 256, ($x * 3) % 256, ($y * 5) % 256),
                };
                imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, (int) $color);
            }
        }
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    public function test_view_is_fetched_once_reencoded_and_served_with_image_headers(): void
    {
        Http::fake(['data.geopf.fr/wms-r*' => Http::response(self::png('photo'), 200, ['Content-Type' => 'image/png'])]);

        $res = $this->get('/media/aerial/gare-aerienne/1950-1965.jpg');
        $res->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeaderMissing('Set-Cookie');
        $file = $res->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);
        $this->assertStringStartsWith("\xFF\xD8", (string) file_get_contents($file->getFile()->getPathname()));

        $this->get('/media/aerial/gare-aerienne/1950-1965.jpg')->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(function ($r): bool {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return str_starts_with($r->url(), AerialImages::WMS.'?')
                && $q['LAYERS'] === 'ORTHOIMAGERY.ORTHOPHOTOS.1950-1965'
                && $q['CRS'] === 'EPSG:4326'
                && str_starts_with((string) $q['BBOX'], '47.99');
        });
    }

    public function test_view_out_of_coverage_is_remembered_as_missing(): void
    {
        Http::fakeSequence('data.geopf.fr/*')
            ->push(self::png('blank'), 200, ['Content-Type' => 'image/png'])
            ->push(self::png('flat'), 200, ['Content-Type' => 'image/png']);

        $this->get('/media/aerial/gare-aerienne/1965-1980.jpg')->assertNotFound();
        $this->get('/media/aerial/gare-aerienne/1965-1980.jpg')->assertNotFound();
        $this->get('/media/aerial/gare-aerienne/1980-1995.jpg')->assertNotFound();
        Http::assertSentCount(2);
    }

    public function test_sheet_omits_periods_already_known_to_be_missing_but_the_api_keeps_all_three(): void
    {
        Http::fakeSequence('data.geopf.fr/*')->push(self::png('blank'), 200, ['Content-Type' => 'image/png']);
        $this->get('/media/aerial/gare-aerienne/1965-1980.jpg')->assertNotFound();

        $this->get('/stations/gare-aerienne')->assertOk()
            ->assertInertia(fn ($page) => $page->has('aerial_views', 2)
                ->where('aerial_views.0.period', '1950-1965')->where('aerial_views.1.period', '1980-1995'));
        $this->getJson('/api/v1/stations/gare-aerienne/aerial')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_upstream_failure_is_not_cached(): void
    {
        Http::fakeSequence('data.geopf.fr/*')
            ->push('erreur', 500)
            ->push('<html>', 200, ['Content-Type' => 'text/html'])
            ->push(self::png('photo'), 200, ['Content-Type' => 'image/png']);

        $this->get('/media/aerial/gare-aerienne/1980-1995.jpg')->assertStatus(503)->assertHeader('Retry-After', '60');
        $this->get('/media/aerial/gare-aerienne/1980-1995.jpg')->assertStatus(503);
        $this->get('/media/aerial/gare-aerienne/1980-1995.jpg')->assertOk();
    }

    public function test_unknown_station_period_or_disabled_relay_answers_404_without_request(): void
    {
        $this->get('/media/aerial/gare-inconnue/1950-1965.jpg')->assertNotFound();
        $this->get('/media/aerial/gare-aerienne/1995-2000.jpg')->assertNotFound();
        $this->get('/media/aerial/gare-aerienne/..%2Fetc.jpg')->assertNotFound();
        config(['vff.media.aerial.enabled' => false]);
        $this->get('/media/aerial/gare-aerienne/1950-1965.jpg')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_upstream_reads_are_capped(): void
    {
        config(['vff.media.aerial.upstream_per_minute' => 1]);
        Http::fake(['data.geopf.fr/*' => Http::response(self::png('photo'), 200, ['Content-Type' => 'image/png'])]);

        $this->get('/media/aerial/gare-aerienne/1950-1965.jpg')->assertOk();
        $this->get('/media/aerial/gare-aerienne/1965-1980.jpg')->assertStatus(503);
        Http::assertSentCount(1);
    }

    public function test_api_and_sheet_list_the_three_periods_with_credit(): void
    {
        $this->getJson('/api/v1/stations/gare-aerienne/aerial')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.url', '/media/aerial/gare-aerienne/1950-1965.jpg')
            ->assertJsonPath('data.0.attribution', 'IGN, photographies aériennes 1950-1965, Licence Ouverte 2.0')
            ->assertJsonPath('data.2.layer', 'ORTHOIMAGERY.ORTHOPHOTOS.1980-1995');

        $this->get('/stations/gare-aerienne')->assertOk()
            ->assertInertia(fn ($page) => $page->has('aerial_views', 3)->where('aerial_views.1.period', '1965-1980'));

        config(['vff.media.aerial.enabled' => false]);
        $this->getJson('/api/v1/stations/gare-aerienne/aerial')->assertOk()->assertJsonCount(0, 'data');
    }
}
