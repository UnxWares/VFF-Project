<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Station;
use App\Services\Media\Panoramax\PanoramaxClient;
use App\Services\Media\Panoramax\PanoramaxSelection;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Photos Panoramax des gares (vff:import-panoramax-media) : instances IGN et OpenStreetMap France seulement,
 * 110 m, prise de vue tournée vers la gare, une par séquence, trois au plus, licence et crédit lus sur l'élément.
 */
class PanoramaxStationMediaTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 48.0;

    private const LNG = 2.0;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/vff-panoramax-'.bin2hex(random_bytes(6));
        $dir = $this->cacheDir;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        $this->app->bind(PanoramaxClient::class, fn () => new PanoramaxClient($dir, 0));
        Http::preventStrayRequests();
    }

    /**
     * Photo à `$northM` mètres au nord de la gare (négatif : au sud), azimut donné.
     *
     * @return array<string, mixed>
     */
    private static function photo(string $id, float $northM, ?int $azimuth, string $instance = 'ign', string $license = 'etalab-2.0', string $seq = 's1', int $fov = 80, string $date = '2024-07-17T10:00:00+00:00'): array
    {
        return [
            'id' => $id,
            'collection' => $seq,
            'geometry' => ['type' => 'Point', 'coordinates' => [self::LNG, self::LAT + $northM / 111_320]],
            'links' => [['rel' => 'via', 'href' => 'https://example.org', 'instance_name' => $instance]],
            'providers' => [['name' => 'contributeur '.$id, 'roles' => ['producer']]],
            'assets' => [
                'thumb' => ['href' => "https://panoramax.ign.fr/derivates/{$id}/thumb.jpg"],
                'sd' => ['href' => "https://panoramax.ign.fr/derivates/{$id}/sd.jpg"],
            ],
            'properties' => array_filter([
                'license' => $license, 'datetime' => $date, 'view:azimuth' => $azimuth,
                'geovisio:status' => 'ready', 'geovisio:visibility' => 'anyone',
                'pers:interior_orientation' => ['field_of_view' => $fov, 'sensor_array_dimensions' => [4000, 3000]],
                'panoramax:horizontal_pixel_density' => 30, 'quality:horizontal_accuracy' => 3.0,
            ], fn ($v) => $v !== null),
        ];
    }

    public function test_selection_rules(): void
    {
        $features = [
            // 50 m au sud, regarde au nord : vers la gare.
            self::photo('face', -50, 0, seq: 'a'),
            // Même séquence, moins bien tournée : écartée (une par séquence).
            self::photo('meme-sequence', -60, 20, seq: 'a'),
            // 50 m au sud, regarde au sud : dos à la gare.
            self::photo('dos', -50, 180, seq: 'b'),
            // 80 m : trop loin.
            self::photo('loin', -80, 0, seq: 'c'),
            // Instance non retenue (floutage non vérifié).
            self::photo('autre-instance', -40, 0, 'us-ne', 'CC-BY-SA-4.0', 'd'),
            // Licence inconnue.
            self::photo('licence', -40, 0, license: 'proprietary', seq: 'e'),
            // OpenStreetMap France, panorama, vers la gare.
            self::photo('osm', 30, 180, 'osm-fr', 'CC-BY-SA-4.0', 'f', 360),
            // Tout près mais tournée ailleurs : écartée aussi.
            self::photo('pres', 5, 90, seq: 'g'),
            self::photo('troisieme', -55, 5, seq: 'h'),
            self::photo('quatrieme', -58, 8, seq: 'i'),
        ];

        $rejected = [];
        $picked = array_column(PanoramaxSelection::pick($features, self::LAT, self::LNG, $rejected), 'id');

        $this->assertCount(3, $picked);
        $this->assertContains('face', $picked);
        $this->assertNotContains('meme-sequence', $picked);
        $this->assertSame(['facing_away' => 3, 'too_far' => 1, 'instance' => 1, 'license' => 1], $rejected);
        $this->assertNotContains('osm', $picked, 'le panorama passe après les photos plates');
        $this->assertEqualsWithDelta(0.0, PanoramaxSelection::angle(self::photo('x', -50, 0), self::LAT, self::LNG), 0.5);
    }

    public function test_import_writes_rights_and_credit_and_is_idempotent(): void
    {
        Station::factory()->create(['slug' => 'gare-panoramax', 'name' => 'Témoin', 'geom' => Point::makeGeodetic(self::LAT, self::LNG)]);
        Http::fake(['api.panoramax.xyz/api/search*' => Http::response(['features' => [
            self::photo('ign-1', -50, 0, seq: 'a'),
            self::photo('osm-1', 40, 180, 'osm-fr', 'CC-BY-SA-4.0', 'b'),
            self::photo('loin-1', -100, 0, seq: 'c'),
        ]])]);

        $this->artisan('vff:import-panoramax-media', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Media::query()->count());
        Http::assertSentCount(1);

        $this->artisan('vff:import-panoramax-media')->assertSuccessful();
        $this->artisan('vff:import-panoramax-media')->assertSuccessful();
        // Réponse gardée en cache : une seule requête pour les trois passages.
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->header('User-Agent')[0] ?? '', 'VFF-Bot'));

        $this->assertSame(2, Media::query()->where('provider', 'panoramax')->count());
        $ign = Media::query()->where('external_id', 'ign-1')->firstOrFail();
        $this->assertSame('Licence Ouverte 2.0', $ign->license);
        $this->assertSame('Photo : contributeur ign-1, Panoramax (IGN), Licence Ouverte 2.0', $ign->attribution);
        $this->assertSame('https://panoramax.ign.fr/#focus=pic&pic=ign-1', $ign->source_url);
        $this->assertTrue($ign->commercial_ok);
        $this->assertTrue($ign->derivatives_ok);
        $this->assertFalse($ign->share_alike);
        $this->assertSame('2024-07-17', $ign->captured_at?->toDateString());
        $osm = Media::query()->where('external_id', 'osm-1')->firstOrFail();
        $this->assertTrue($osm->share_alike);
        $this->assertSame('CC BY-SA 4.0', $osm->license);

        $this->getJson('/api/v1/stations/gare-panoramax/media')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.provider_label', 'Panoramax');
    }

    public function test_cache_only_makes_no_request(): void
    {
        Station::factory()->create(['geom' => Point::makeGeodetic(self::LAT, self::LNG)]);

        $this->artisan('vff:import-panoramax-media', ['--dry-run' => true, '--cache-only' => true])->assertSuccessful();

        Http::assertNothingSent();
    }
}
