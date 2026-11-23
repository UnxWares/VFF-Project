<?php

namespace Tests\Feature\Commands;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/** Import de la fréquentation des gares : passe à blanc sans écriture, remplacement, rattachement, licence contrôlée. */
class ImportSncfAttendanceTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const CACHE = 'tests/Fixtures/SncfAttendance';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
    }

    private function station(string $slug, string $name, ?string $uic, string $dep, string $status = 'active'): Station
    {
        return Station::factory()->create(['slug' => $slug, 'name' => $name, 'uic_code' => $uic, 'department_code' => $dep, 'status' => $status]);
    }

    private function stations(): void
    {
        $this->station('alpha-centre', 'Alpha-Centre', '87000001', '75');
        $this->station('doublon-06', 'Doublon', '87000002', '06');
        $this->station('doublon-13', 'Doublon', '87000002', '13', 'declassified');
        $this->station('sans-code-connu', 'Sans code connu', null, '06');
        $this->station('ouverte-sans-chiffre', 'Ouverte sans chiffre', '87000050', '33');
    }

    public function test_dry_run_measures_without_writing_anything(): void
    {
        $this->stations();
        $before = $this->snapshot();

        $this->artisan('vff:import-sncf-attendance', ['--dry-run' => true, '--cache-dir' => self::CACHE])
            ->expectsOutputToContain('Lignes à écrire dans station_attendances : 7, pour 3 gares')
            ->expectsOutputToContain('Gares par année : 2017 1, 2023 1, 2024 3, 2025 2')
            ->expectsOutputToContain('unmatched : Gare absente de VFF (87999000, 29150)')
            ->expectsOutputToContain('Gares ouvertes (active, tourist) sans fréquentation : 1')
            ->expectsOutputToContain('Passe à blanc : aucune écriture en base.')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::table('station_attendances')->count());
    }

    public function test_import_writes_both_series_and_replaces_the_previous_rows(): void
    {
        $this->stations();
        $alpha = Station::where('slug', 'alpha-centre')->firstOrFail();
        DB::table('station_attendances')->insert(['station_id' => $alpha->id, 'year' => 2010, 'passengers' => 1, 'series' => '2015-2024',
            'uic_code' => '87000001', 'match_method' => 'uic', 'source' => 'sncf', 'dataset' => 'ancien']);

        $this->artisan('vff:import-sncf-attendance', ['--cache-dir' => self::CACHE])->assertSuccessful();

        $this->assertSame(7, DB::table('station_attendances')->count());
        $this->assertSame(0, DB::table('station_attendances')->where('year', 2010)->count());
        $rows = DB::table('station_attendances')->where('station_id', $alpha->id)->orderBy('year')->get()->keyBy('year');
        $this->assertSame([2017, 2023, 2024, 2025], $rows->keys()->map(fn ($y) => (int) $y)->all());
        $this->assertSame([800, 1000], [(int) $rows[2017]->passengers, (int) $rows[2017]->footfall]);
        $this->assertSame([1000, 1250, '2015-2024', 'frequentation-gares-archive'], [(int) $rows[2024]->passengers, (int) $rows[2024]->footfall, $rows[2024]->series, $rows[2024]->dataset]);
        $this->assertSame([1100, 1100, '2025', 'frequentation-gares'], [(int) $rows[2025]->passengers, (int) $rows[2025]->footfall, $rows[2025]->series, $rows[2025]->dataset]);
        $this->assertSame('2026-11-13', substr((string) $rows[2025]->dataset_modified, 0, 10));

        $doublon = DB::table('station_attendances')->where('uic_code', '87000002')->first();
        $this->assertSame(Station::where('slug', 'doublon-06')->value('id'), $doublon->station_id, 'le doublon de fiche est départagé par le département');
        $this->assertSame('uic-department', $doublon->match_method);
        $this->assertSame('name-department', DB::table('station_attendances')->where('uic_code', '87000999')->value('match_method'));

        $this->artisan('vff:import-sncf-attendance', ['--cache-dir' => self::CACHE])->assertSuccessful();
        $this->assertSame(7, DB::table('station_attendances')->count(), 'relancer ne double rien');
    }

    public function test_csv_reports_list_unmatched_stations_and_open_stations_without_figures(): void
    {
        $this->stations();
        $dir = $this->sandbox.'/rapports';

        $this->artisan('vff:import-sncf-attendance', ['--dry-run' => true, '--cache-dir' => self::CACHE, '--csv' => $dir])->assertSuccessful();

        $this->assertStringContainsString('87999000', (string) file_get_contents($dir.'/non-rattachees.csv'));
        $this->assertStringContainsString('ouverte-sans-chiffre', (string) file_get_contents($dir.'/sans-frequentation.csv'));
        $this->assertFileExists($dir.'/rapport.csv');
    }

    public function test_a_dataset_under_another_licence_is_refused(): void
    {
        $this->stations();
        Http::fake([
            '*/exports/json*' => Http::response([['nom_gare' => 'X', 'code_uic_complet' => '87000001']]),
            '*' => Http::response(['metas' => ['default' => ['license' => 'Tous droits réservés', 'publisher' => 'Gares & Connexions']]]),
        ]);
        $cache = $this->sandbox.'/cache-vide';

        $this->artisan('vff:import-sncf-attendance', ['--cache-dir' => $cache])
            ->expectsOutputToContain('Licence ou éditeur inattendus')
            ->assertFailed();
        $this->assertSame(0, DB::table('station_attendances')->count());
    }
}
