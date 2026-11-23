<?php

namespace Tests\Feature\Commands;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;
use ZipArchive;

/**
 * vff:import-station-timetables sur le mini flux tests/Fixtures/Timetable/mini (servi depuis le cache, aucune requête réseau) :
 * mardi type choisi dans les horaires, tables station_timetables et line_timetables, rien d'écrit avec --no-store.
 */
class ImportStationTimetablesCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
        $this->era(2030, current: true);

        $gtfs = $this->sandbox.'/app/data/gtfs';
        foreach (['sncf', 'transilien', 'zou-trains', 'zou-express', 'transdev-rail-sud', 'cfc'] as $key) {
            File::ensureDirectoryExists("{$gtfs}/{$key}");
            $zip = new ZipArchive;
            $zip->open("{$gtfs}/{$key}/gtfs.zip", ZipArchive::CREATE);
            foreach (glob(base_path('tests/Fixtures/Timetable/mini/*.txt')) ?: [] as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        }
        File::put($gtfs.'/sncf-frequentation-gares.csv', "nom_gare;code_uic_complet;total_voyageurs_2025\n");
        $this->station('alpha', 'Alpha', 45.0, 1.0, ['uic_code' => '87100010']);
        $this->station('bravo', 'Bravo', 45.0, 2.0, ['uic_code' => '87200020']);
        $this->station('charlie', 'Charlie', 45.0, 3.0, ['uic_code' => '87300030']);
    }

    /** @param  array<string, mixed>  $options */
    private function import(array $options = []): void
    {
        $this->artisan('vff:import-station-timetables', $options + ['--from-cache' => true, '--today' => '2026-11-18'])->assertExitCode(0);
    }

    public function test_the_typical_tuesday_is_stored_for_each_served_station(): void
    {
        $this->import();

        $rows = DB::table('station_timetables as t')->join('stations as s', 's.id', '=', 't.station_id')->orderBy('s.name')
            ->get(['s.name', 't.*'])->keyBy('name');
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $rows->keys()->all());
        $this->assertSame(2, $rows['Alpha']->trains);
        $this->assertSame(1, $rows['Bravo']->trains);
        $this->assertSame('2', date('N', (int) strtotime((string) $rows['Alpha']->service_date)));
        $this->assertSame(['ter' => 1, 'tgv' => 1], json_decode((string) $rows['Alpha']->by_type, true));
        $this->assertSame('sncf', $rows['Alpha']->source);
        $this->assertSame('sncf', json_decode((string) $rows['Alpha']->feeds, true)[0]['feed'], 'les flux régionaux, copie du même service, ne doublent rien');
    }

    public function test_no_store_writes_nothing_but_the_report(): void
    {
        $dir = $this->sandbox.'/report';
        $this->import(['--no-store' => true, '--report' => $dir]);

        $this->assertSame(0, DB::table('station_timetables')->count());
        $this->assertSame(0, DB::table('line_timetables')->count());
        $this->assertFileExists("{$dir}/couverture.csv");
        $this->assertFileExists("{$dir}/flux.json");
    }

    public function test_a_second_run_replaces_the_tables_instead_of_adding_to_them(): void
    {
        $this->import();
        $this->import();

        $this->assertSame(3, DB::table('station_timetables')->count());
        $this->assertSame(Station::query()->count(), 3);
    }
}
