<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\RebuildHistory;
use App\Models\Era;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Les vitesses SNCF (vff:import-sncf-vmax) sont posées APRÈS le moteur des ères : un
 * line_segments régénéré les perdait (vitesse vide sur toutes les ères le 20/10/2026).
 * vff:rebuild-history les rejoue depuis un cache local, sans réseau.
 */
class SegmentEnrichmentDurabilityTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $current;

    private Era $previous;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->era(2010);
        $this->previous = $this->era(2020);
        $this->current = $this->era(2030, current: true);
        $this->sandboxStorage();
        $this->cacheDir = $this->sandbox.'/app/sources/sncf-vmax';
        // Aucune requête réelle : toute requête non simulée lève une exception.
        Http::preventStrayRequests();
    }

    public function test_vmax_is_a_non_blocking_offline_step_after_the_electrification(): void
    {
        $steps = (new \ReflectionClassConstant(RebuildHistory::class, 'DERIVATIONS'))->getValue();
        $names = array_column($steps, 0);
        $at = fn (string $n) => array_search($n, $names, true);

        $this->assertNotFalse($at('vff:import-sncf-vmax'), 'vitesses absentes du pipeline');
        // Après l'électrification (qui découpe des tronçons et en recopie les colonnes) et le moteur.
        $this->assertGreaterThan($at('vff:enrich-era-segments'), $at('vff:import-sncf-vmax'));
        $this->assertGreaterThan($at('vff:build-era-segments'), $at('vff:enrich-era-segments'));
        $this->assertSame(['--from-cache' => true, '--previous' => true], array_column($steps, 1, 0)['vff:import-sncf-vmax']);
        $this->assertContains('vff:import-sncf-vmax', (new \ReflectionClassConstant(RebuildHistory::class, 'NON_BLOCKING'))->getValue());
    }

    public function test_cached_speeds_restore_the_current_and_previous_eras_without_network(): void
    {
        Http::fake();
        $this->writeCache([
            ['code_ligne' => '752000', 'v_max' => '300', 'geo_shape' => ['geometry' => ['type' => 'LineString', 'coordinates' => [[4.00, 47.00], [4.00, 47.10]]]]],
            // Recouvre 2 km du tronçon seulement : la vitesse du plus long recouvrement l'emporte.
            ['code_ligne' => '752000', 'v_max' => '160', 'geo_shape' => ['geometry' => ['type' => 'LineString', 'coordinates' => [[4.00, 47.08], [4.00, 47.20]]]]],
            ['code_ligne' => '830000', 'v_max' => '220', 'geo_shape' => ['geometry' => ['type' => 'LineString', 'coordinates' => [[5.00, 47.00], [5.00, 47.10]]]]],
        ]);
        $lgv = $this->rfnLine('752000', 'LGV Sud-Est');
        $other = $this->rfnLine('830000', 'Ligne de Paris à Marseille');
        $segments = [];
        foreach ([$this->current, $this->previous, Era::where('year', 2010)->first()] as $era) {
            $segments[$era->year] = $this->track($lgv, $era, [[47.00, 4.0003], [47.10, 4.0003]], ['max_speed_kmh' => null]);
        }
        $otherSeg = $this->track($other, $this->current, [[47.00, 5.0], [47.10, 5.0]], ['max_speed_kmh' => null]);

        // Relance par ligne : seule la ligne visée, cache seul.
        $this->artisan('vff:import-sncf-vmax', ['--line' => '752000', '--cache-only' => true, '--previous' => true, '--cache-dir' => $this->cacheDir])
            ->expectsOutputToContain('✓ 2 segments mis à jour')->assertSuccessful();
        $speed = fn ($seg) => DB::table('line_segments')->where('id', $seg->id)->value('max_speed_kmh');
        $this->assertSame(300, $speed($segments[2030]));
        $this->assertSame(300, $speed($segments[2020]), 'décennie écoulée (--previous)');
        $this->assertNull($speed($segments[2010]), 'ères plus anciennes : jamais la vitesse du jour');
        $this->assertNull($speed($otherSeg), 'hors --line');

        // Étape du pipeline : toutes les lignes, toujours sans réseau.
        $this->artisan('vff:import-sncf-vmax', ['--from-cache' => true, '--cache-dir' => $this->cacheDir])->assertSuccessful();
        $this->assertSame(220, $speed($otherSeg));
        Http::assertNothingSent();
    }

    public function test_cache_only_without_cache_changes_nothing_and_never_calls_the_network(): void
    {
        Http::fake();
        $seg = $this->track($this->rfnLine('752000', 'LGV Sud-Est'), $this->current, [[47.00, 4.0], [47.10, 4.0]], ['max_speed_kmh' => null]);

        $this->artisan('vff:import-sncf-vmax', ['--cache-only' => true, '--cache-dir' => $this->cacheDir])
            ->expectsOutputToContain('Pas de cache local des vitesses SNCF')->assertSuccessful();

        $this->assertNull(DB::table('line_segments')->where('id', $seg->id)->value('max_speed_kmh'));
        Http::assertNothingSent();
    }

    public function test_download_fills_the_cache_used_by_the_next_offline_run(): void
    {
        Http::fake(['ressources.data.sncf.com/*' => Http::response(['results' => [
            ['code_ligne' => '752000', 'v_max' => '300', 'geo_shape' => ['geometry' => ['type' => 'LineString', 'coordinates' => [[4.00, 47.00], [4.00, 47.10]]]]],
        ]])]);
        $seg = $this->track($this->rfnLine('752000', 'LGV Sud-Est'), $this->current, [[47.00, 4.0], [47.10, 4.0]], ['max_speed_kmh' => null]);

        $this->artisan('vff:import-sncf-vmax', ['--from-cache' => true, '--cache-dir' => $this->cacheDir])->assertSuccessful();

        $this->assertSame(300, DB::table('line_segments')->where('id', $seg->id)->value('max_speed_kmh'));
        Http::assertSent(fn ($r) => $r->hasHeader('User-Agent', 'VFF-Bot/1.0 (+https://vff-project.org)'));
        $cached = json_decode((string) file_get_contents("{$this->cacheDir}/vitesse-maximale-nominale-sur-ligne.json"), true);
        $this->assertCount(1, $cached['records']);
    }

    /** @param  list<array<string, mixed>>  $records */
    private function writeCache(array $records): void
    {
        @mkdir($this->cacheDir, 0775, true);
        file_put_contents("{$this->cacheDir}/vitesse-maximale-nominale-sur-ligne.json",
            json_encode(['dataset' => 'vitesse-maximale-nominale-sur-ligne', 'fetched_at' => '2026-10-20 03:00:00', 'records' => $records]));
    }
}
