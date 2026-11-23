<?php

namespace Tests\Feature\Api;

use App\Enums\TileFields;
use App\Services\MapDataCache;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/** Réchauffage du cache des petits zooms (vff:warm-tiles) : mêmes clés que la route, rien de recalculé ensuite. */
class WarmTilesTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
    }

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

    public function test_warmed_tiles_are_served_without_a_new_computation(): void
    {
        $this->artisan('vff:warm-tiles', ['--era' => [2030], '--max-zoom' => 8, '--fields' => 'map', '--pause-ms' => 0])->assertSuccessful();

        $queries = $this->tileQueries(function (): void {
            $this->get('/api/v1/eras/2030/tiles/8/129/88.mvt?fields=map')->assertOk();
            $this->get('/api/v1/eras/2030/tiles/4/8/5.mvt?fields=map')->assertOk();
        });
        $this->assertSame(0, $queries);
    }

    public function test_a_second_run_finds_everything_in_the_cache(): void
    {
        $args = ['--era' => [2030], '--max-zoom' => 5, '--pause-ms' => 0];
        $this->artisan('vff:warm-tiles', $args)->assertSuccessful();

        $this->assertSame(0, $this->tileQueries(fn () => $this->artisan('vff:warm-tiles', $args)->assertSuccessful()));
    }

    public function test_dry_run_computes_and_stores_nothing(): void
    {
        $this->assertSame(0, $this->tileQueries(fn () => $this->artisan('vff:warm-tiles', ['--era' => [2030], '--max-zoom' => 5, '--dry-run' => true])->assertSuccessful()));
        $this->assertFalse(app(MapDataCache::class)->hasTile('lines:2030:0:0:0:map:'.TileFields::MAP_FORMAT));
    }

    public function test_it_refuses_a_zoom_that_is_not_cached(): void
    {
        $this->artisan('vff:warm-tiles', ['--max-zoom' => MapDataCache::CACHED_MAX_ZOOM + 1])->assertExitCode(2);
        $this->artisan('vff:warm-tiles', ['--fields' => 'nope'])->assertExitCode(2);
    }
}
