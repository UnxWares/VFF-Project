<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/** Import de la liaison radio sol-train SNCF : passe à blanc sans écriture, remplacement des portions, licence contrôlée. */
class ImportSncfRadioTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
    }

    /** @param  array<string, mixed>  $meta */
    private function serve(array $meta = []): void
    {
        $records = [
            ['code_ligne' => '100000', 'lib_ligne' => '100000', 'rst' => 'GSMR (Global System for Mobile Raywails)', 'rg_troncon' => 1, 'pkd' => '000-250', 'pkf' => '030+000'],
            ['code_ligne' => '100000', 'lib_ligne' => '100000', 'rst' => 'Sans transmission de données', 'rg_troncon' => 1, 'pkd' => '030+000', 'pkf' => '040+000'],
            ['code_ligne' => '200000', 'lib_ligne' => '200000', 'rst' => "Global System for Mobil- Groupe Fermé d'utilisateurs", 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '020+000'],
            ['code_ligne' => '200000', 'lib_ligne' => '200000', 'rst' => 'Radio inconnue', 'rg_troncon' => 2, 'pkd' => '020+000', 'pkf' => '021+000'],
            ['code_ligne' => '999999', 'lib_ligne' => '999999', 'rst' => 'GSMR (Global System for Mobile Raywails)', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '001+000'],
        ];
        $default = $meta + ['license' => 'Open Database License (ODbL)', 'publisher' => 'SNCF Réseau', 'modified' => '2022-03-24T10:26:20+00:00'];
        Http::fake([
            '*/exports/json*' => Http::response($records),
            '*' => Http::response(['metas' => ['default' => $default]]),
        ]);
    }

    public function test_dry_run_measures_without_writing_anything(): void
    {
        $this->rfnLine('100000', 'A');
        $this->rfnLine('200000', 'B');
        $this->serve();
        $before = $this->snapshot();

        $this->artisan('vff:import-sncf-radio', ['--dry-run' => true])
            ->expectsOutputToContain('Lignes touchées : 2 (codes du jeu sans ligne dans VFF : 1)')
            ->expectsOutputToContain('Portions écrites dans line_radio_portions : 3')
            ->expectsOutputToContain('Lignes équipées du GSM-R : 1, dont 1 en partie seulement')
            ->expectsOutputToContain('libellé inconnu, écarté : « Radio inconnue » (1)')
            ->expectsOutputToContain('Passe à blanc : aucune écriture en base.')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::table('line_radio_portions')->count());
        $this->assertFileExists(storage_path('app/sources/sncf-radio/.nobackup'));
    }

    public function test_import_replaces_the_portions(): void
    {
        $line = $this->rfnLine('100000', 'A');
        $this->rfnLine('200000', 'B');
        DB::table('line_radio_portions')->insert(['line_id' => $line->id, 'code_ligne' => '100000', 'system' => 'rst', 'label' => 'ancienne', 'source' => 'sncf']);
        $this->serve();

        $this->artisan('vff:import-sncf-radio')->assertSuccessful();

        $this->assertSame(3, DB::table('line_radio_portions')->count());
        $gsmr = DB::table('line_radio_portions')->where('system', 'gsm-r')->first();
        $this->assertSame([-250, 30000, '2022-03-24'], [(int) $gsmr->pk_start_m, (int) $gsmr->pk_end_m, substr((string) $gsmr->dataset_modified, 0, 10)]);
        $this->assertSame('GSMR (Global System for Mobile Raywails)', $gsmr->label);
        $this->assertSame(0, DB::table('line_radio_portions')->where('label', 'ancienne')->count());
    }

    public function test_it_stops_on_an_unexpected_publisher(): void
    {
        $this->serve(['publisher' => 'Quelqu\'un d\'autre']);

        $this->artisan('vff:import-sncf-radio', ['--dry-run' => true])
            ->expectsOutputToContain('Licence ou éditeur inattendus')
            ->assertFailed();
        $this->assertFileDoesNotExist(storage_path('app/sources/sncf-radio/lignes-equipees-de-liaison-radio-sol-train.json'));
    }
}
