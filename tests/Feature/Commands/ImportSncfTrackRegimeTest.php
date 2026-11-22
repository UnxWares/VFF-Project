<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * Import du régime d'exploitation SNCF : passe à blanc exacte et sans écriture, écriture du nombre de voies
 * seulement là où le champ est vide ou vient de cette commande, licence contrôlée.
 */
class ImportSncfTrackRegimeTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array<string, mixed>>|null  $records
     */
    private function serve(array $meta = [], ?array $records = null): void
    {
        $records ??= [
            ['code_ligne' => '100000', 'lib_ligne' => 'A', 'exploitati' => 'Double voie', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '050+000'],
            ['code_ligne' => '200000', 'lib_ligne' => 'B', 'exploitati' => 'Voie unique', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '050+000'],
            ['code_ligne' => '300000', 'lib_ligne' => 'C', 'exploitati' => 'Double voie', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '050+000'],
            ['code_ligne' => '400000', 'lib_ligne' => 'D', 'exploitati' => 'Voie banalisée', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '050+000'],
            ['code_ligne' => '500000', 'lib_ligne' => 'E', 'exploitati' => 'Voie unique', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '010+000'],
            ['code_ligne' => '500000', 'lib_ligne' => 'E', 'exploitati' => 'Double voie', 'rg_troncon' => 1, 'pkd' => '010+000', 'pkf' => '020+000'],
            ['code_ligne' => '999999', 'lib_ligne' => 'X', 'exploitati' => 'Voie unique', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '050+000'],
        ];
        $default = $meta + ['license' => 'Open Database License (ODbL)', 'publisher' => 'SNCF Réseau', 'modified' => '2022-03-24T09:50:03+00:00'];
        Http::fake([
            '*/exports/json*' => Http::response($records),
            '*' => Http::response(['metas' => ['default' => $default]]),
        ]);
    }

    private function lines(): void
    {
        $this->rfnLine('100000', 'A', ['nb_voies_default' => 2]);      // confirmée
        $this->rfnLine('200000', 'B', ['nb_voies_default' => 2]);      // contradiction : jamais remplacée
        $this->rfnLine('300000', 'C');                                  // écrite
        $this->rfnLine('400000', 'D');                                  // banalisée : portions seulement
        $this->rfnLine('500000', 'E');                                  // régime partagé : champ laissé vide
    }

    public function test_dry_run_measures_without_writing_anything(): void
    {
        $this->lines();
        $this->serve();
        $before = $this->snapshot();

        $this->artisan('vff:import-sncf-track-regime', ['--dry-run' => true])
            ->expectsOutputToContain('Lignes touchées : 5 (codes du jeu sans ligne dans VFF : 1)')
            ->expectsOutputToContain('Portions écrites dans line_track_portions : 6')
            ->expectsOutputToContain('écrit (champ vide, régime uniforme) : 1')
            ->expectsOutputToContain('confirmé (même nombre) : 1')
            ->expectsOutputToContain('contradictions (autre source, nombre différent, jamais remplacé) : 1')
            ->expectsOutputToContain('b-200000 : fiche 2, SNCF Réseau 1')
            ->expectsOutputToContain('régime partagé, champ laissé tel quel (détail par portion) : 1')
            ->expectsOutputToContain('« voie banalisée » (nombre de voies non établi, écartées) : 1')
            ->expectsOutputToContain('Passe à blanc : aucune écriture en base.')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::table('line_track_portions')->count());
        $this->assertFileExists(storage_path('app/sources/sncf-regime/.nobackup'));
    }

    public function test_import_writes_portions_and_fills_only_empty_track_counts(): void
    {
        $this->lines();
        $this->serve();

        $this->artisan('vff:import-sncf-track-regime')->assertSuccessful();

        $tracks = DB::table('lines')->pluck('nb_voies_default', 'code')->map(fn ($v) => $v === null ? null : (int) $v)->all();
        $this->assertEquals(['100000' => 2, '200000' => 2, '300000' => 2, '400000' => null, '500000' => null], array_intersect_key($tracks, array_flip(['100000', '200000', '300000', '400000', '500000'])));
        $this->assertSame(6, DB::table('line_track_portions')->count());
        $banalised = DB::table('line_track_portions')->where('code_ligne', '400000')->first();
        $this->assertNull($banalised->tracks);
        $this->assertSame('Voie banalisée', $banalised->regime);
        $this->assertSame(50000, (int) $banalised->pk_end_m);

        $fields = DB::table('line_sources')->where('source_type', 'sncf-regime')->get()
            ->mapWithKeys(fn ($s) => [DB::table('lines')->where('id', $s->line_id)->value('code') => json_decode($s->fields_provided, true)])->all();
        $this->assertSame(['line_track_portions', 'nb_voies_default'], $fields['300000']);
        $this->assertSame(['line_track_portions'], $fields['200000'], 'la valeur d\'une autre source ne devient pas celle de SNCF');
        $this->assertCount(5, $fields);
    }

    public function test_second_import_refreshes_its_own_value_and_clears_it_when_the_line_leaves_the_dataset(): void
    {
        $this->lines();
        $this->serve();
        $this->artisan('vff:import-sncf-track-regime')->assertSuccessful();

        // Nouvelle version du jeu dans le cache local : seule la ligne 100000 y reste.
        file_put_contents(storage_path('app/sources/sncf-regime/regime-dexploitation-des-lignes.json'), json_encode(['license' => 'Open Database License (ODbL)',
            'modified' => '2026-01-01', 'records' => [['code_ligne' => '100000', 'lib_ligne' => 'A', 'exploitati' => 'Double voie', 'rg_troncon' => 1, 'pkd' => '000+000', 'pkf' => '050+000']]]));
        $this->artisan('vff:import-sncf-track-regime')
            ->expectsOutputToContain('effacé (valeur de cette commande que le jeu ne confirme plus) : 1')
            ->assertSuccessful();

        $this->assertNull(DB::table('lines')->where('code', '300000')->value('nb_voies_default'));
        $this->assertSame(2, (int) DB::table('lines')->where('code', '200000')->value('nb_voies_default'), 'valeur d\'une autre source intacte');
        $this->assertSame(1, DB::table('line_track_portions')->count());
        $this->assertSame(1, DB::table('line_sources')->where('source_type', 'sncf-regime')->count());
    }

    public function test_it_stops_on_an_unexpected_licence(): void
    {
        $this->serve(['license' => 'Licence propriétaire']);

        $this->artisan('vff:import-sncf-track-regime', ['--dry-run' => true])
            ->expectsOutputToContain('Licence ou éditeur inattendus')
            ->assertFailed();
        $this->assertFileDoesNotExist(storage_path('app/sources/sncf-regime/regime-dexploitation-des-lignes.json'));
    }

    public function test_second_run_reads_the_local_cache(): void
    {
        $this->serve();
        $this->artisan('vff:import-sncf-track-regime', ['--dry-run' => true])->assertSuccessful();
        Http::fake(fn () => Http::response('', 500));

        $this->artisan('vff:import-sncf-track-regime', ['--dry-run' => true])->assertSuccessful();
    }
}
