<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Station;
use App\Services\Media\Panoramax\PanoramaxClient;
use App\Services\Media\Panoramax\PanoramaxSceneBuilder;
use App\Services\Media\Panoramax\PanoramaxVision;
use App\Services\StationAudit\BdtopoStationZones;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tri par vision de vff:import-panoramax-media (--vision) : vignettes IGN téléchargées une fois (User-Agent de VFF),
 * notes relues dans le cache (scores.json, écrit par scripts/panoramax-vision), photos sous le seuil écartées, photos
 * sans note listées dans a-noter.txt, instance OpenStreetMap France jamais lue.
 */
class PanoramaxVisionImportTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 48.0;

    private const LNG = 2.0;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/vff-panoramax-vision-'.bin2hex(random_bytes(6));
        $dir = $this->dir;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        $this->app->bind(PanoramaxClient::class, fn () => new PanoramaxClient($dir, 0));
        $this->app->bind(PanoramaxSceneBuilder::class, fn () => new PanoramaxSceneBuilder(new BdtopoStationZones($dir.'/zones', 0), $dir, 0, $dir.'/aucun-quai.json'));
        $this->app->bind(PanoramaxVision::class, fn () => new PanoramaxVision($dir.'/vision', 0));
        Http::preventStrayRequests();
    }

    /** @return array<string, mixed> */
    private static function photo(string $id, float $northM, int $azimuth, string $instance = 'ign', string $seq = ''): array
    {
        return [
            'id' => $id,
            'collection' => $seq !== '' ? $seq : 'seq-'.$id,
            'geometry' => ['type' => 'Point', 'coordinates' => [self::LNG, self::LAT + $northM / 111_320]],
            'links' => [['rel' => 'via', 'href' => 'https://example.org', 'instance_name' => $instance]],
            'providers' => [['name' => 'contributeur '.$id, 'roles' => ['producer']]],
            'assets' => [
                'thumb' => ['href' => "https://panoramax.ign.fr/derivates/{$id}/thumb.jpg"],
                'sd' => ['href' => "https://panoramax.ign.fr/derivates/{$id}/sd.jpg"],
            ],
            'properties' => [
                'license' => $instance === 'ign' ? 'etalab-2.0' : 'CC-BY-SA-4.0', 'datetime' => '2024-07-17T10:00:00+00:00',
                'view:azimuth' => $azimuth, 'geovisio:status' => 'ready', 'geovisio:visibility' => 'anyone',
                'pers:interior_orientation' => ['field_of_view' => 80, 'sensor_array_dimensions' => [4000, 3000]],
                'quality:horizontal_accuracy' => 3.0,
            ],
        ];
    }

    private function fakeNetwork(): void
    {
        Http::fake([
            'api.panoramax.xyz/api/search*' => Http::response(['features' => [
                self::photo('gare-vue', -50, 0),
                self::photo('route', -30, 10),
                self::photo('pas-notee', -40, 5),
                self::photo('dos', -30, 180),
                self::photo('osm-1', -20, 0, 'osm-fr'),
            ]]),
            'panoramax.ign.fr/derivates/*' => Http::response("\xFF\xD8\xFF\xE0 vignette"),
            // Abords inconnus (aucun bâtiment) : le tri par vision ne s'en sert pas mais ne s'arrête pas.
            'data.geopf.fr/wfs/*' => fn (Request $r) => Http::response(['type' => 'FeatureCollection', 'features' => []]),
            'overpass-api.de/*' => Http::response(['elements' => []]),
        ]);
    }

    private function writeScores(): void
    {
        File::ensureDirectoryExists($this->dir.'/vision');
        File::put($this->dir.'/vision/scores.json', json_encode(['model' => 'test', 'prompts' => 'v0', 'scores' => [
            'gare-vue' => 0.97, 'route' => 0.12,
        ]]));
    }

    public function test_vision_downloads_thumbnails_once_and_keeps_only_well_scored_photos(): void
    {
        Station::factory()->create(['slug' => 'gare-vision', 'geom' => Point::makeGeodetic(self::LAT, self::LNG)]);
        $this->fakeNetwork();
        $this->writeScores();

        $this->artisan('vff:import-panoramax-media', ['--dry-run' => true, '--vision' => true])->assertSuccessful();
        $this->assertSame(0, Media::query()->count());
        $this->assertFileExists($this->dir.'/vision/thumbs/gare-vue.jpg');
        $this->assertFileExists($this->dir.'/vision/thumbs/pas-notee.jpg');
        $this->assertFileDoesNotExist($this->dir.'/vision/thumbs/dos.jpg', 'dos à la gare : pas candidate');
        $this->assertFileDoesNotExist($this->dir.'/vision/thumbs/osm-1.jpg', 'OpenStreetMap France jamais lue');
        $this->assertSame("pas-notee\n", File::get($this->dir.'/vision/a-noter.txt'));
        $thumbs = fn (Request $r): bool => str_contains($r->url(), 'panoramax.ign.fr/derivates/');
        Http::assertSent(fn (Request $r): bool => $thumbs($r) && str_contains($r->header('User-Agent')[0] ?? '', 'VFF-Bot'));
        $sent = Http::recorded(fn (Request $r): bool => $thumbs($r))->count();
        $this->assertSame(3, $sent);

        $this->artisan('vff:import-panoramax-media', ['--vision' => true, '--cache-only' => true])->assertSuccessful();
        $this->assertSame($sent, Http::recorded(fn (Request $r): bool => $thumbs($r))->count(), 'cache seul : aucune vignette téléchargée');

        $this->assertSame(['gare-vue'], Media::query()->where('provider', 'panoramax')->pluck('external_id')->all());
        $media = Media::query()->where('external_id', 'gare-vue')->firstOrFail();
        $this->assertSame('vision', $media->provider_meta['rule'] ?? null);
        $this->assertSame(0.97, $media->provider_meta['vision_score'] ?? null);
        $this->assertSame('Licence Ouverte 2.0', $media->license);
    }

    public function test_vision_without_scores_imports_nothing_and_lists_candidates(): void
    {
        Station::factory()->create(['geom' => Point::makeGeodetic(self::LAT, self::LNG)]);
        $this->fakeNetwork();

        $this->artisan('vff:import-panoramax-media', ['--vision' => true])->assertSuccessful();

        $this->assertSame(0, Media::query()->count());
        $this->assertSame(['gare-vue', 'pas-notee', 'route'], collect(explode("\n", trim(File::get($this->dir.'/vision/a-noter.txt'))))->sort()->values()->all());
    }

    public function test_min_score_raises_the_threshold_and_the_csv_names_the_author(): void
    {
        Station::factory()->create(['slug' => 'gare-vision', 'geom' => Point::makeGeodetic(self::LAT, self::LNG)]);
        $this->fakeNetwork();
        $this->writeScores();
        $csv = $this->dir.'/photos.csv';

        $this->artisan('vff:import-panoramax-media', ['--dry-run' => true, '--vision' => true, '--cache-only' => true, '--csv' => $csv])->assertSuccessful();
        $this->assertCount(1, file($csv, FILE_SKIP_EMPTY_LINES) ?: [], 'sans cache : en-tête seul');

        $this->artisan('vff:import-panoramax-media', ['--dry-run' => true, '--vision' => true, '--csv' => $csv, '--min-score' => '0.9'])->assertSuccessful();
        $rows = array_map(fn (string $l): array => str_getcsv($l, escape: '\\'), file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        $header = array_shift($rows);
        $this->assertSame('author', $header[array_search('author', $header, true)]);
        $this->assertCount(1, $rows);
        $this->assertSame('contributeur gare-vue', $rows[0][array_search('author', $header, true)]);

        $this->artisan('vff:import-panoramax-media', ['--dry-run' => true, '--vision' => true, '--csv' => $csv, '--min-score' => '0.99'])->assertSuccessful();
        $this->assertCount(1, file($csv, FILE_SKIP_EMPTY_LINES) ?: [], 'note 0,97 sous le seuil 0,99');

        $this->artisan('vff:import-panoramax-media', ['--dry-run' => true, '--vision' => true, '--min-score' => '0.3'])->assertFailed();
    }

    public function test_razed_station_gets_no_candidate(): void
    {
        Station::factory()->create(['lifecycle' => 'razed', 'geom' => Point::makeGeodetic(self::LAT, self::LNG)]);
        $this->fakeNetwork();
        $this->writeScores();

        $this->artisan('vff:import-panoramax-media', ['--vision' => true])->assertSuccessful();

        $this->assertSame(0, Media::query()->count());
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/derivates/'));
    }

    public function test_catalogue_timeout_counts_the_station_as_failed_without_stopping(): void
    {
        Station::factory()->create(['geom' => Point::makeGeodetic(self::LAT, self::LNG)]);
        Http::fake(['api.panoramax.xyz/api/search*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

        // Une seule gare, en erreur : la commande échoue proprement (pas d'exception), rien n'est écrit.
        $this->artisan('vff:import-panoramax-media', ['--dry-run' => true, '--vision' => true])
            ->expectsOutputToContain('1 en erreur')
            ->assertFailed();
        $this->assertSame(0, Media::query()->count());
    }
}
