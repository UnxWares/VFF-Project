<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/** Passe à blanc du régime d'exploitation SNCF : rapport exact, aucune écriture en base, licence contrôlée. */
class MeasureSncfTrackRegimeTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
    }

    /** @param array<string, mixed> $meta */
    private function serve(array $meta = []): void
    {
        $records = [
            ['code_ligne' => '100000', 'lib_ligne' => 'A', 'exploitati' => 'Double voie', 'pkd' => '000+000', 'pkf' => '050+000'],
            ['code_ligne' => '200000', 'lib_ligne' => 'B', 'exploitati' => 'Voie unique', 'pkd' => '000+000', 'pkf' => '050+000'],
            ['code_ligne' => '300000', 'lib_ligne' => 'C', 'exploitati' => 'Double voie', 'pkd' => '000+000', 'pkf' => '050+000'],
            ['code_ligne' => '400000', 'lib_ligne' => 'D', 'exploitati' => 'Voie banalisée', 'pkd' => '000+000', 'pkf' => '050+000'],
            ['code_ligne' => '999999', 'lib_ligne' => 'X', 'exploitati' => 'Voie unique', 'pkd' => '000+000', 'pkf' => '050+000'],
        ];
        $default = $meta + ['license' => 'Open Database License (ODbL)', 'publisher' => 'SNCF Réseau', 'modified' => '2022-03-24T09:50:03+00:00'];
        Http::fake([
            '*/exports/json*' => Http::response($records),
            '*' => Http::response(['metas' => ['default' => $default]]),
        ]);
    }

    public function test_it_measures_without_writing_anything(): void
    {
        $this->rfnLine('100000', 'A', ['nb_voies_default' => 2]);      // confirmée
        $this->rfnLine('200000', 'B', ['nb_voies_default' => 2]);      // contradiction
        $this->rfnLine('300000', 'C');                                  // fait nouveau
        $this->rfnLine('400000', 'D');                                  // banalisée : écartée
        $this->serve();
        $before = $this->snapshot();

        $this->artisan('vff:measure-sncf-track-regime', ['--dry-run' => true])
            ->expectsOutputToContain('Lignes touchées : 3 (codes du jeu sans ligne dans VFF : 1)')
            ->expectsOutputToContain('faits nouveaux (nombre de voies absent de la fiche) : 1')
            ->expectsOutputToContain('confirmés par la fiche : 1')
            ->expectsOutputToContain('contradictions (régime uniforme, nombre différent) : 1')
            ->expectsOutputToContain('« voie banalisée » (nombre de voies non établi, écartées) : 1')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertFileExists(storage_path('app/sources/sncf-regime/.nobackup'));
    }

    public function test_it_refuses_to_run_without_dry_run(): void
    {
        Http::fake();
        $this->artisan('vff:measure-sncf-track-regime')->assertExitCode(2);
        Http::assertNothingSent();
    }

    public function test_it_stops_on_an_unexpected_licence(): void
    {
        $this->serve(['license' => 'Licence propriétaire']);

        $this->artisan('vff:measure-sncf-track-regime', ['--dry-run' => true])
            ->expectsOutputToContain('Licence ou éditeur inattendus')
            ->assertFailed();
        $this->assertFileDoesNotExist(storage_path('app/sources/sncf-regime/regime-dexploitation-des-lignes.json'));
    }

    public function test_second_run_reads_the_local_cache(): void
    {
        $this->serve();
        $this->artisan('vff:measure-sncf-track-regime', ['--dry-run' => true])->assertSuccessful();
        Http::fake(fn () => Http::response('', 500));

        $this->artisan('vff:measure-sncf-track-regime', ['--dry-run' => true])->assertSuccessful();
    }
}
