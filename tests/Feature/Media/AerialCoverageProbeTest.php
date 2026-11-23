<?php

namespace Tests\Feature\Media;

use App\Models\Station;
use App\Services\Media\Aerial\AerialCoverageProbe;
use App\Services\Media\Aerial\AerialImages;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Sonde de couverture des vues aériennes (vff:probe-aerial-coverage) : emprises des missions lues au WFS, marqueurs
 * négatifs lus par AerialImages, journal de reprise, dichotomie des groupes, rien d'écrit à blanc.
 */
class AerialCoverageProbeTest extends TestCase
{
    use RefreshDatabase;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/vff-aerial-probe-'.bin2hex(random_bytes(6));
        $dir = $this->cacheDir;
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
        config(['vff.media.aerial.enabled' => true, 'vff.media.aerial.cache_path' => $dir]);
        Sleep::fake();
        Http::preventStrayRequests();
    }

    private static function features(int $n): array
    {
        return ['type' => 'FeatureCollection', 'features' => $n === 0 ? [] : [['type' => 'Feature', 'properties' => ['nom' => '1954_F 2346-2546_P_25000', 'begin_date' => '1960-05-31Z', 'pva' => 1957, 'date_vol' => '1960-05-31Z']]]];
    }

    /** Le WFS répond « mission trouvée » seulement pour la couche 1950-1965. */
    private function fakeOnlyOldest(): void
    {
        Http::fake(fn (Request $r) => Http::response(self::features(str_contains($r->url(), '1950-1965') ? 1 : 0)));
    }

    public function test_absent_periods_get_a_probe_marker_that_hides_them_from_the_sheet(): void
    {
        $station = Station::factory()->create(['slug' => 'gare-a', 'name' => 'A', 'geom' => Point::makeGeodetic(45.83, 1.26)]);
        $this->fakeOnlyOldest();

        $this->artisan('vff:probe-aerial-coverage', ['--station' => 'gare-a'])->assertSuccessful();

        $this->assertFileDoesNotExist($this->cacheDir.'/1950-1965/'.$station->id.'.none');
        $this->assertSame(AerialImages::PROBE_MARK, file_get_contents($this->cacheDir.'/1965-1980/'.$station->id.'.none'));
        $periods = array_column(AerialImages::views('gare-a', 45.83, 1.26, (int) $station->id), 'period');
        $this->assertSame(['1950-1965'], $periods);
        $this->assertSame("{$station->id}\t1\t1960-05-31\t1954_F 2346-2546_P_25000\t".date('Y-m-d')."\n", file_get_contents($this->cacheDir.'/coverage/1950-1965.tsv'));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'POINT') || str_contains(urldecode($r->url()), 'POINT(1.260000 45.830000)'));
    }

    public function test_probe_marker_outlives_the_thirty_day_negative_ttl_but_not_a_year(): void
    {
        $station = Station::factory()->create(['slug' => 'gare-a', 'name' => 'A', 'geom' => Point::makeGeodetic(45.83, 1.26)]);
        $this->fakeOnlyOldest();
        $this->artisan('vff:probe-aerial-coverage', ['--station' => 'gare-a'])->assertSuccessful();
        $none = $this->cacheDir.'/1965-1980/'.$station->id.'.none';

        touch($none, time() - 90 * 86400);
        $this->assertCount(1, AerialImages::views('gare-a', 45.83, 1.26, (int) $station->id));
        touch($none, time() - 400 * 86400);
        touch($this->cacheDir.'/1980-1995/'.$station->id.'.none', time() - 400 * 86400);
        $this->assertCount(3, AerialImages::views('gare-a', 45.83, 1.26, (int) $station->id));
    }

    public function test_dry_run_writes_nothing_and_a_second_run_resumes_from_the_journal(): void
    {
        Station::factory()->create(['slug' => 'gare-a', 'name' => 'A', 'geom' => Point::makeGeodetic(45.83, 1.26)]);
        $this->fakeOnlyOldest();

        $this->artisan('vff:probe-aerial-coverage', ['--dry-run' => true])->assertSuccessful();
        $this->assertDirectoryDoesNotExist($this->cacheDir);

        $this->artisan('vff:probe-aerial-coverage')->assertSuccessful();
        Http::assertSentCount(6);   // 3 à blanc, 3 pour de bon
        $this->artisan('vff:probe-aerial-coverage')->assertSuccessful();
        Http::assertSentCount(6);   // reprise : plus rien à sonder
    }

    public function test_a_group_is_split_until_each_station_is_known(): void
    {
        $probe = new AerialCoverageProbe;
        // Gares 1 à 4 : seule la 3 est couverte ; la réponse dépend des points du filtre.
        Http::fake(fn (Request $r) => Http::response(self::features(str_contains(urldecode($r->url()), 'POINT(3.000000 3.000000)') ? 1 : 0)));

        $res = $probe->probe('1980-1995', [[1, 1.0, 1.0], [2, 2.0, 2.0], [3, 3.0, 3.0], [4, 4.0, 4.0]]);

        $this->assertSame([1 => false, 2 => false, 3 => true, 4 => false], array_map(fn ($r) => $r['covered'], $res));
        $this->assertSame(5, $probe->requests);
    }

    public function test_upstream_error_leaves_the_station_unknown_and_writes_nothing(): void
    {
        Station::factory()->create(['slug' => 'gare-a', 'name' => 'A', 'geom' => Point::makeGeodetic(45.83, 1.26)]);
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->artisan('vff:probe-aerial-coverage')->assertSuccessful();

        $this->assertFileDoesNotExist($this->cacheDir.'/coverage/1950-1965.tsv');
    }
}
