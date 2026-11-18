<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;
use ZipArchive;

/**
 * vff:import-station-service sur le mini flux tests/Fixtures/Gtfs/mini (servi depuis le cache,
 * aucune requête réseau) : classement rangé dans station_services, rapports CSV, statut des
 * gares jamais modifié.
 */
class ImportStationServiceCommandTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private Era $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
        $this->current = $this->era(2030, current: true);

        $gtfs = $this->sandbox.'/app/data/gtfs';
        File::ensureDirectoryExists($gtfs.'/sncf');
        $zip = new ZipArchive;
        $zip->open($gtfs.'/sncf/gtfs.zip', ZipArchive::CREATE);
        foreach (glob(base_path('tests/Fixtures/Gtfs/mini/*.txt')) ?: [] as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
        File::put($gtfs.'/sncf-frequentation-gares.csv', "nom_gare;code_uic_complet;total_voyageurs_2024;total_voyageurs_2025\nAlpha;87000018;900.0;1200.0\n");
    }

    private function vffStation(string $name, float $lat, float $lng, ?string $uic, string $status, string $department = '75'): Station
    {
        $station = $this->station(strtolower($name), $name, $lat, $lng, ['uic_code' => $uic, 'status' => $status, 'department_code' => $department]);
        DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $this->current->id, 'status' => $status]);

        return $station;
    }

    /** @return array<string, object> nom de la gare => ligne de station_services */
    private function services(): array
    {
        return DB::table('station_services as ss')->join('stations as s', 's.id', '=', 'ss.station_id')
            ->get(['s.name', 'ss.*'])->keyBy('name')->all();
    }

    /** @param  array<string, mixed>  $options */
    private function import(array $options = []): void
    {
        $this->artisan('vff:import-station-service', $options + ['--feed' => ['sncf'], '--from-cache' => true, '--today' => '2026-11-18'])
            ->assertExitCode(0);
    }

    public function test_each_station_gets_a_class_from_the_timetables(): void
    {
        $this->vffStation('Alpha', 48.0, 2.0, '87000018', 'active');
        $this->vffStation('Bravo', 48.1, 2.1, '87000026', 'declassified');
        $this->vffStation('Echo', 48.3, 2.3, '87000034', 'active');
        $this->vffStation('Delta', 47.0, 3.0, null, 'active');
        $this->vffStation('Bastia', 42.7, 9.45, null, 'active', '2B');

        $this->import();

        $s = $this->services();
        // Du 18/11 au 15/12 sauf le 25/11 : 27 jours, deux trains par jour (le flux SNCF ne lit pas « /car »).
        $this->assertSame(['regular', 'daily', 54], [$s['Alpha']->service_class, $s['Alpha']->pattern, $s['Alpha']->calls]);
        $this->assertSame(['2026-11-18', '2026-12-15'], [$s['Alpha']->window_start, $s['Alpha']->window_end]);
        $this->assertSame('regular', $s['Bravo']->service_class);
        $this->assertSame('bus_only', $s['Echo']->service_class);
        $this->assertSame('none', $s['Delta']->service_class);
        $this->assertSame(['unknown', 'Aucun flux à jour pour cette région'], [$s['Bastia']->service_class, $s['Bastia']->note]);

        $evidence = json_decode($s['Alpha']->evidence, true);
        $this->assertSame(['sncf', 'uic'], [$evidence[0]['feed'], $evidence[0]['method']]);
        $this->assertSame(['year' => 2025, 'travellers' => 1200], json_decode($s['Alpha']->indicators, true)['frequentation']);
    }

    public function test_the_status_of_the_stations_is_never_changed(): void
    {
        $this->vffStation('Bravo', 48.1, 2.1, '87000026', 'declassified');
        $before = $this->snapshot(['stations', 'station_eras']);

        $this->import();

        $this->assertSame($before, $this->snapshot(['stations', 'station_eras']));
        $this->assertSame('declassified', DB::table('stations')->value('status'));
    }

    public function test_the_reports_list_the_gaps_with_the_current_era(): void
    {
        $this->vffStation('Alpha', 48.0, 2.0, '87000018', 'active');
        $this->vffStation('Bravo', 48.1, 2.1, '87000026', 'declassified');
        $this->vffStation('Echo', 48.3, 2.3, '87000034', 'active');

        $this->import(['--report' => $this->sandbox.'/rapport', '--no-store' => true]);

        $this->assertSame(0, DB::table('station_services')->count(), '--no-store : rien en base');
        $gaps = array_map('str_getcsv', file($this->sandbox.'/rapport/ecarts.csv', FILE_IGNORE_NEW_LINES) ?: []);
        $byName = array_column(array_slice($gaps, 1), null, 1);
        $this->assertSame(['declassified', 'regular', 'active'], [$byName['Bravo'][3], $byName['Bravo'][4], $byName['Bravo'][6]]);
        $this->assertSame(['active', 'bus_only', 'declassified'], [$byName['Echo'][3], $byName['Echo'][4], $byName['Echo'][6]]);
        $this->assertArrayNotHasKey('Alpha', $byName);
        $this->assertFileExists($this->sandbox.'/rapport/gares.csv');
        // Tramways comptés comme trains dans le flux SNCF (tram-trains) : Charlie et Delta n'ont pas de gare dans VFF.
        $orphans = array_column(array_map('str_getcsv', array_slice(file($this->sandbox.'/rapport/arrets-non-rapproches.csv', FILE_IGNORE_NEW_LINES) ?: [], 1)), 2);
        $this->assertEqualsCanonicalizing(['Charlie', 'Delta'], $orphans);
    }

    public function test_a_second_run_replaces_the_table(): void
    {
        $this->vffStation('Alpha', 48.0, 2.0, '87000018', 'active');

        $this->import();
        $this->import();

        $this->assertSame(1, DB::table('station_services')->count());
    }
}
