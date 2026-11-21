<?php

namespace Tests\Feature\Api;

use App\Models\LineSegment;
use App\Services\MapDataCache;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Performances de la carte /maps (rapport matin/carte-performance.md) : tuiles et gares d'une ère gardées
 * en cache jusqu'au prochain recalcul, requêtes conditionnelles (ETag, 304), réponses compressées, requête
 * de tuile sans compilation JIT et sous un temps borné.
 */
class MapDataCacheTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    /** Tuile du zoom 8 qui contient le réseau témoin (Paris Saint-Lazare → Versailles). */
    private const TILE = '/api/v1/eras/2030/tiles/8/129/88.mvt';

    private const STATIONS = '/api/v1/eras/2030/stations.geojson';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

    /** Nombre de requêtes SQL qui calculent une tuile (ST_AsMVT) pendant `$run`. */
    private function tileQueries(callable $run): int
    {
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            if (str_contains($query->sql, 'ST_AsMVT(')) {
                $count++;
            }
        });
        $run();

        return $count;
    }

    public function test_a_low_zoom_tile_is_computed_once_then_served_from_the_cache(): void
    {
        $first = '';
        $this->assertSame(1, $this->tileQueries(function () use (&$first): void {
            $first = (string) $this->get(self::TILE)->assertOk()->getContent();
        }));
        $this->assertStringContainsString('vff-lines', $first);

        $second = '';
        $this->assertSame(0, $this->tileQueries(function () use (&$second): void {
            $second = (string) $this->get(self::TILE)->assertOk()->getContent();
        }));
        $this->assertSame($first, $second);
    }

    public function test_a_fine_tile_is_not_kept_in_the_cache(): void
    {
        $z = MapDataCache::CACHED_MAX_ZOOM + 1;
        $this->assertSame(2, $this->tileQueries(function () use ($z): void {
            $this->get("/api/v1/eras/2030/tiles/{$z}/0/0.mvt")->assertOk();
            $this->get("/api/v1/eras/2030/tiles/{$z}/0/0.mvt")->assertOk();
        }));
    }

    public function test_a_recalculation_changes_the_version_and_the_tile(): void
    {
        $before = $this->get(self::TILE)->assertOk();
        $etag = (string) $before->headers->get('ETag');

        // Un tracé change (recalcul) une fois passée la durée de la version : la tuile est recalculée.
        $this->travel(MapDataCache::VERSION_TTL + 1)->seconds();
        LineSegment::where('era_id', $this->currentEra->id)->firstOrFail()->update(['status' => 'abandoned']);

        $after = $this->get(self::TILE)->assertOk();
        $this->assertNotSame($etag, $after->headers->get('ETag'));
        $this->assertNotSame($before->getContent(), $after->getContent());
    }

    public function test_a_conditional_request_gets_304_without_computing_the_tile(): void
    {
        $etag = (string) $this->get(self::TILE)->assertOk()->headers->get('ETag');
        $this->assertNotSame('', $etag);

        $this->assertSame(0, $this->tileQueries(function () use ($etag): void {
            $this->get(self::TILE, ['If-None-Match' => $etag])->assertStatus(304)->assertContent('');
        }));
    }

    public function test_the_tile_is_sent_compressed_when_the_client_accepts_it(): void
    {
        $plain = $this->get(self::TILE)->assertOk();
        $this->assertNull($plain->headers->get('Content-Encoding'));

        $packed = $this->get(self::TILE, ['Accept-Encoding' => 'gzip, deflate, br'])->assertOk()
            ->assertHeader('Content-Encoding', 'gzip')
            ->assertHeader('Vary', 'Accept-Encoding');
        $this->assertSame($plain->getContent(), gzdecode((string) $packed->getContent()));
        $this->assertLessThan(strlen((string) $plain->getContent()), strlen((string) $packed->getContent()));
        // Deux corps différents, deux ETag différents.
        $this->assertNotSame($plain->headers->get('ETag'), $packed->headers->get('ETag'));
    }

    public function test_a_versioned_address_is_cached_one_day_by_the_browser(): void
    {
        $this->get(self::TILE.'?v=1700000000-42')->assertOk()->assertHeader('Cache-Control', 'max-age=86400, public');
        $this->get(self::TILE)->assertOk()->assertHeader('Cache-Control', 'max-age=60, public');
        $this->get(self::STATIONS.'?v=1700000000-42')->assertOk()->assertHeader('Cache-Control', 'max-age=86400, public');
    }

    public function test_an_empty_tile_stays_empty_even_compressed(): void
    {
        $this->get('/api/v1/eras/2030/tiles/14/16383/16383.mvt', ['Accept-Encoding' => 'gzip'])->assertOk()
            ->assertContent('')
            ->assertHeaderMissing('Content-Encoding');
    }

    /** @return list<string> noms des gares d'une FeatureCollection décodée */
    private function stationNames(mixed $features): array
    {
        $this->assertIsArray($features);

        return array_values(array_map(fn (array $f): string => (string) $f['properties']['name'], $features));
    }

    public function test_stations_are_cached_and_keep_their_content(): void
    {
        $first = $this->getJson(self::STATIONS)->assertOk()->assertHeader('Content-Type', 'application/json');
        $names = $this->stationNames($first->json('features'));
        $this->assertNotEmpty($names);
        // Caractères accentués écrits tels quels (fichier plus léger), JSON toujours valide.
        $this->assertStringNotContainsString('\\u00', (string) $first->getContent());

        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'ST_AsGeoJSON')) {
                $queries++;
            }
        });
        $this->assertSame($names, $this->stationNames($this->getJson(self::STATIONS)->assertOk()->json('features')));
        $this->assertSame(0, $queries);
    }

    public function test_unknown_eras_still_answer_404(): void
    {
        $this->get('/api/v1/eras/1835/tiles/8/129/88.mvt')->assertNotFound();
        $this->get('/api/v1/eras/1835/stations.geojson')->assertNotFound();
    }

    public function test_the_tile_query_runs_without_jit_and_within_a_time_budget(): void
    {
        // Réseau témoin étoffé : 300 tracés de 50 sommets autour de Paris, dans la tuile du zoom 4.
        for ($i = 0; $i < 300; $i++) {
            $points = [];
            for ($k = 0; $k < 50; $k++) {
                $points[] = Point::makeGeodetic(48.0 + $i * 0.004 + sin($k / 5) * 0.001, 1.5 + $k * 0.004);
            }
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $this->currentEra->id, 'status' => 'in_service',
                'traffic_type' => 'mixed', 'geom' => LineString::make($points),
            ]);
        }

        $jit = [];
        DB::listen(function (QueryExecuted $query) use (&$jit): void {
            if (str_contains($query->sql, 'jit')) {
                $jit[] = $query->sql;
            }
        });
        $started = hrtime(true);
        $tile = (string) $this->get('/api/v1/eras/2030/tiles/4/8/5.mvt')->assertOk()->getContent();
        $elapsedMs = (hrtime(true) - $started) / 1e6;

        $this->assertSame(['SET LOCAL jit = off'], $jit);
        $this->assertGreaterThan(1000, strlen($tile));
        // Sur ces données, la tuile se calcule en quelques dizaines de millisecondes ; la borne large
        // garde le test stable sur une machine chargée et signale une régression d'un ordre de grandeur.
        $this->assertLessThan(1500, $elapsedMs);
    }
}
