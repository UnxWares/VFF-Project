<?php

namespace Tests\Feature\Commands;

use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:match-current-network : options (sources, --line, --dry-run,
 * --explain) et contrôle du « ref » OSM contre le vote géométrique.
 */
class MatchCurrentNetworkOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function osm(string $ref, ?string $codeRef, float $lat): int
    {
        return (int) DB::selectOne(<<<'SQL'
            INSERT INTO source_traces (source, source_ref, layer, properties, length_m, seen_at, created_at, updated_at, geom)
            VALUES ('osm', ?, 'rail', CAST(? AS jsonb), 7000, NOW(), NOW(), NOW(),
                    ST_Multi(ST_SetSRID(ST_MakeLine(ST_MakePoint(2.0, ?), ST_MakePoint(2.1, ?)), 4326)))
            RETURNING id
            SQL, [$ref, json_encode($codeRef ? ['ref' => $codeRef] : []), $lat, $lat])->id;
    }

    public function test_unknown_source_or_line_is_refused(): void
    {
        $this->artisan('vff:match-current-network', ['--source' => ['osm', 'ign']])
            ->expectsOutputToContain('(reçu : ign)')
            ->assertFailed();
        $this->artisan('vff:match-current-network', ['--line' => ['999999']])
            ->expectsOutputToContain('Aucune ligne trouvée pour --line=999999')
            ->assertFailed();
    }

    public function test_dry_run_explains_and_checks_osm_refs_without_writing(): void
    {
        Line::factory()->create(['code' => '420000', 'name' => 'Ligne de Paris à Brest']);
        DB::insert("INSERT INTO sncf_line_geometries (code_ligne, libelle, geom, created_at, updated_at)
            VALUES ('420000', 'Ligne de Paris à Brest', ST_SetSRID(ST_MakeLine(ST_MakePoint(2.0, 48.0), ST_MakePoint(2.1, 48.0)), 4326), now(), now())");
        $agree = $this->osm('1', '420000', 48.0);
        $this->osm('2', '420 000;999999', 48.00005);
        $before = DB::table('source_traces')->orderBy('id')->pluck('line_id')->all();

        $this->artisan('vff:match-current-network', ['--source' => ['osm'], '--dry-run' => true, '--explain' => ['420000']])
            ->expectsOutputToContain('→ osm : échantillonnage et plus proches formes SNCF…')
            ->expectsOutputToContain('  Contrôle ref OSM : le vote géométrique désigne le code du ref pour 2/2 ways (100.0 %).')
            ->expectsOutputToContain('  Tracés à ≤ buffer de la forme 420000 :')
            ->assertSuccessful();

        $this->assertSame($before, DB::table('source_traces')->orderBy('id')->pluck('line_id')->all(), '--dry-run : aucun rattachement');
        unset($agree);
    }
}
