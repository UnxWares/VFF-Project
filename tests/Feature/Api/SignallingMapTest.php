<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Services\Signalling\SignallingMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsLevelCrossings;
use Tests\TestCase;

/**
 * Calque « Signalisation » : tranches de `line_signalling` placées sur la forme SNCF Réseau de la
 * ligne (vue matérialisée `line_signalling_map`), PK calés sur les passages à niveau ; tuiles par
 * ère (seule l'ère courante est renseignée), famille de cantonnement, contrôle de vitesse à part.
 */
class SignallingMapTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLevelCrossings;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->current = Era::factory()->current()->create(['year' => 2030]);
        Era::factory()->create(['year' => 1930]);
        $line = Line::factory()->create(['slug' => 'rfn-999000', 'code' => '999000', 'name' => 'Ligne d’essai']);
        // Forme de 10 km, d'ouest en est. Le PN au PK 5+000 est aux 7/10 du tracé : le PK n'est pas
        // la distance, la limite des tranches doit suivre le PN, pas le milieu de la forme.
        $this->seedSncfShape('999000', 0, 10_000, 48.0, 2.0, 2.1);
        $this->seedLevelCrossing('30000099001', '999000', 5_000, 48.0, 2.07);
        // Seconde tranche : ETCS niveau 2 et TVM 430 installés ensemble (LGV Est).
        foreach ([[0, 5_000, 'BAL', ['BAL'], ['KVB'], true], [5_000, 10_000, 'TVM-430', ['TVM-430', 'ETCS_L2'], ['TVM-430', 'ERTMS_L2'], false]] as [$a, $b, $sig, $systems, $tp, $kvb]) {
            DB::table('line_signalling')->insert([
                'line_id' => $line->id, 'era_id' => $this->current->id, 'pk_start_m' => $a, 'pk_end_m' => $b,
                'signalling' => $sig, 'signalling_systems' => json_encode($systems), 'train_protection' => json_encode($tp), 'kvb_equipped' => $kvb,
                'note' => 'Source : SNCF Open Data, mode-de-cantonnement-des-lignes', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->artisan('vff:refresh-signalling-map')
            ->expectsOutputToContain('Carte de la signalisation : 2 morceaux de tracé recalculés')
            ->assertSuccessful();
    }

    public function test_range_limits_follow_the_level_crossing_calibration(): void
    {
        $ends = DB::table('line_signalling_map')->orderBy('pk_start_m')
            ->selectRaw('signalling, ST_X(ST_StartPoint(geom)) AS x0, ST_X(ST_EndPoint(geom)) AS x1')->get();

        $this->assertSame(['BAL', 'TVM-430'], $ends->pluck('signalling')->all());
        $this->assertEqualsWithDelta(2.0, $ends[0]->x0, 1e-6);
        $this->assertEqualsWithDelta(2.07, $ends[0]->x1, 1e-6, 'limite calée sur le PN, pas au milieu de la forme');
        $this->assertEqualsWithDelta(2.07, $ends[1]->x0, 1e-6);
        $this->assertEqualsWithDelta(2.1, $ends[1]->x1, 1e-6);
    }

    public function test_current_era_tile_carries_block_family_and_speed_control_separately(): void
    {
        $tile = (string) $this->get('/api/v1/eras/2030/signalling/tiles/8/129/88.mvt')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/x-protobuf')
            ->getContent();

        $this->assertStringContainsString('vff-signalling', $tile);
        foreach (['automatic_block', 'cab', 'BAL', 'TVM-430', 'KVB', 'rfn-999000', 'signalling_systems', 'TVM-430,ETCS_L2'] as $value) {
            $this->assertStringContainsString($value, $tile);
        }
    }

    public function test_tiles_are_cached_for_a_day_like_the_level_crossing_tiles(): void
    {
        $this->get('/api/v1/eras/2030/signalling/tiles/8/129/88.mvt')->assertOk()->assertHeader('Cache-Control', 'max-age=86400, public');
    }

    public function test_refresh_is_concurrent_once_populated_and_falls_back_while_empty(): void
    {
        $this->assertSame(1, (int) DB::scalar("SELECT COUNT(*) FROM pg_indexes WHERE indexname = 'line_signalling_map_piece_unique'"));
        $map = app(SignallingMap::class);

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });
        $this->assertSame(2, $map->refresh());
        $this->assertContains('REFRESH MATERIALIZED VIEW CONCURRENTLY line_signalling_map', $statements);

        DB::statement('REFRESH MATERIALIZED VIEW line_signalling_map WITH NO DATA');
        $statements = [];
        $this->assertSame(2, $map->refresh(), 'vue jamais remplie : repli sans CONCURRENTLY');
        $this->assertContains('REFRESH MATERIALIZED VIEW line_signalling_map', $statements);
    }

    public function test_other_eras_are_empty_and_unknown_eras_are_404(): void
    {
        $this->assertSame('', $this->get('/api/v1/eras/1930/signalling/tiles/8/129/88.mvt')->assertOk()->getContent());
        $this->getJson('/api/v1/eras/1935/signalling/tiles/8/129/88.mvt')->assertNotFound()
            ->assertJsonPath('message', 'Unknown era 1935: eras are the decades 1830 to 2030.');
        $this->get('/api/v1/eras/2030/signalling/tiles/2/9/1.mvt')->assertNotFound();
    }
}
