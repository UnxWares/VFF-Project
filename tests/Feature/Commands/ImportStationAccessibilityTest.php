<?php

namespace Tests\Feature\Commands;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/** Import de l'accessibilité des gares : passe à blanc sans écriture, remplacement, rattachement par code UIC, licence contrôlée. */
class ImportStationAccessibilityTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const CACHE = 'tests/Fixtures/SncfAccessibility';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
        foreach ([['alpha-centre', 'Alpha-Centre', '87000001'], ['bravo', 'Bravo', '87000002'], ['deux-codes', 'Deux codes', '87000005'], ['sans-information', 'Sans information', '87000050']] as [$slug, $name, $uic]) {
            Station::factory()->create(['slug' => $slug, 'name' => $name, 'uic_code' => $uic, 'status' => 'active']);
        }
    }

    public function test_dry_run_measures_without_writing_anything(): void
    {
        $this->artisan('vff:import-station-accessibility', ['--dry-run' => true, '--cache-dir' => self::CACHE])
            ->expectsOutputToContain('Lignes à écrire dans station_accessibility : 3')
            ->expectsOutputToContain('2 accessibles en partie, 1 non déclarées accessibles')
            ->expectsOutputToContain('Gares du jeu sans gare de VFF : 1')
            ->expectsOutputToContain('Gares ouvertes (active, tourist) sans information : 1')
            ->expectsOutputToContain('Passe à blanc : aucune écriture en base.')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('station_accessibility')->count());
    }

    public function test_import_writes_one_row_per_station_and_replaces_the_previous_ones(): void
    {
        $alpha = Station::where('slug', 'alpha-centre')->firstOrFail();
        DB::table('station_accessibility')->insert(['station_id' => 999999, 'uic_code' => '87000099', 'sncf_station_id' => 'bbbbbbbb-0000-4000-8000-000000000001',
            'amenities' => '[]', 'datasets' => '[]', 'source' => 'sncf', 'computed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('vff:import-station-accessibility', ['--cache-dir' => self::CACHE])->expectsOutputToContain('Écrit : 3 gares.')->assertSuccessful();

        $this->assertSame(3, DB::table('station_accessibility')->count(), 'l\'ancienne ligne est remplacée');
        $row = DB::table('station_accessibility')->where('station_id', $alpha->id)->first();
        $this->assertSame(['87000001', 'partial', true, true], [$row->uic_code, $row->mobility, (bool) $row->accessible, (bool) $row->step_free]);
        $this->assertSame(2, $row->lifts);
        $this->assertSame(['induction_loop', 'tactile_strips', 'toilets'], json_decode($row->amenities, true));
        $this->assertEquals(['ok' => 1, 'ko' => 1, 'unknown' => 0], array_intersect_key(json_decode($row->elevators, true), array_flip(['ok', 'ko', 'unknown'])));
        $this->assertSame(['accessibilite_gares', 'equipements-accessibilite-en-gare', 'assistance-psh-pmr', 'etat-fonctionnement-elevatique-gare', 'points-vente', 'stationnement-securise-velo-en-gare-au-30-06-24'],
            array_column(json_decode($row->datasets, true), 'dataset'));
        $this->assertSame('ODbL', json_decode($row->datasets, true)[0]['license']);

        $second = DB::table('station_accessibility')->where('station_id', Station::where('slug', 'deux-codes')->value('id'))->first();
        $this->assertSame('87000005', $second->uic_code, 'rattachée par son second code UIC');

        $bravo = DB::table('station_accessibility')->where('station_id', Station::where('slug', 'bravo')->value('id'))->first();
        $this->assertSame(['none', false], [$bravo->mobility, (bool) $bravo->accessible]);
        $this->assertNull($bravo->assistance);
        $this->assertNull(DB::table('station_accessibility')->where('station_id', Station::where('slug', 'sans-information')->value('id'))->first());

        $this->artisan('vff:import-station-accessibility', ['--cache-dir' => self::CACHE])->assertSuccessful();
        $this->assertSame(3, DB::table('station_accessibility')->count(), 'relancer ne double rien');
    }

    public function test_the_import_never_touches_the_stations(): void
    {
        $before = DB::table('stations')->orderBy('id')->get()->toJson();

        $this->artisan('vff:import-station-accessibility', ['--cache-dir' => self::CACHE])->assertSuccessful();

        $this->assertSame($before, DB::table('stations')->orderBy('id')->get()->toJson());
    }

    public function test_an_unexpected_licence_stops_everything_before_any_write(): void
    {
        $dir = sys_get_temp_dir().'/vff-acc-'.bin2hex(random_bytes(4));
        mkdir($dir);
        Http::fake(['*' => Http::response(['metas' => ['default' => ['license' => 'Licence fermée', 'publisher' => 'Gares & Connexions']]])]);

        $this->artisan('vff:import-station-accessibility', ['--cache-dir' => $dir])
            ->expectsOutputToContain('Licence ou éditeur inattendus')
            ->assertFailed();

        $this->assertSame(0, DB::table('station_accessibility')->count());
        foreach (glob($dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($dir.'/.nobackup') ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}
