<?php

namespace Tests\Feature\Commands;

use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/** Import du patrimoine des gares : passe à blanc sans écriture, remplacement, rattachement, licence contrôlée. */
class ImportStationHeritageTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    private const CACHE = 'tests/Fixtures/Heritage';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::preventStrayRequests();
    }

    private function station(string $slug, string $name, string $city, string $dep, float $lat, float $lng, ?string $qid = null): Station
    {
        return Station::factory()->create(['slug' => $slug, 'name' => $name, 'city' => $city, 'department_code' => $dep, 'wikidata_id' => $qid,
            'geom' => Point::makeGeodetic($lat, $lng), 'status' => 'active']);
    }

    private function stations(): void
    {
        $this->station('alpha', 'Alpha', 'Alpha', '75', 48.8, 2.3, 'Q1001');
        $this->station('bravo-ville', 'Bravo-Ville', 'Bravo', '13', 43.3005, 5.4005);
        $this->station('charlie', 'Charlie', 'Charlie', '33', 44.801, -0.56);
        $this->station('delta', 'Delta', 'Delta', '69', 45.75, 4.85);
        $this->station('sans-patrimoine', 'Sans patrimoine', 'Ailleurs', '11', 44.0, 4.0, 'Q1009');
    }

    public function test_dry_run_measures_without_writing_anything(): void
    {
        $this->stations();
        $before = $this->snapshot();

        $this->artisan('vff:import-station-heritage', ['--dry-run' => true, '--cache-dir' => self::CACHE, '--show' => 'alpha'])
            ->expectsOutputToContain('Lignes à écrire dans station_heritage : 5, pour 4 gares avec bâtiment voyageurs')
            ->expectsOutputToContain('Rattachement : wikidata 2, name-commune 2, proximity 1')
            ->expectsOutputToContain('[building] registered 1984-12-28 (partielle) ref PA00000001')
            ->expectsOutputToContain('Notices Mérimée sans gare de VFF : 1')
            ->expectsOutputToContain('Passe à blanc : aucune écriture en base.')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::table('station_heritage')->count());
    }

    public function test_import_writes_the_rows_and_a_second_run_changes_nothing(): void
    {
        $this->stations();

        $this->artisan('vff:import-station-heritage', ['--cache-dir' => self::CACHE])->assertSuccessful();

        $this->assertSame(5, DB::table('station_heritage')->count());
        $alpha = DB::table('station_heritage')->where('ref', 'PA00000001')->first();
        $this->assertSame(Station::where('slug', 'alpha')->value('id'), $alpha->station_id);
        $this->assertSame('registered', $alpha->protection);
        $this->assertSame('1984-12-28', substr((string) $alpha->protection_date, 0, 10));
        $this->assertSame('Gare Alpha.jpg', $alpha->image_file);
        $this->assertSame('Victor Laloux', json_decode((string) $alpha->architects, true)[0]['name']);
        $this->assertSame('merimee', $alpha->source);
        $this->assertSame('inventory', DB::table('station_heritage')->where('ref', 'IA00000009')->value('protection'));

        $this->artisan('vff:import-station-heritage', ['--cache-dir' => self::CACHE])->assertSuccessful();
        $this->assertSame(5, DB::table('station_heritage')->count(), 'relancer ne double rien');
    }

    public function test_a_second_run_replaces_what_the_first_one_wrote(): void
    {
        $this->stations();
        $alpha = Station::where('slug', 'alpha')->firstOrFail();
        DB::table('station_heritage')->insert(['station_id' => $alpha->id, 'protection' => 'classified', 'ref' => 'PA99999999', 'ref_kind' => 'PA',
            'source' => 'merimee', 'match_method' => 'wikidata']);

        $this->artisan('vff:import-station-heritage', ['--cache-dir' => self::CACHE])->assertSuccessful();

        $this->assertSame(0, DB::table('station_heritage')->where('ref', 'PA99999999')->count());
    }

    public function test_csv_reports_list_the_rows_and_the_unmatched_notices(): void
    {
        $this->stations();
        $dir = $this->sandbox.'/rapports';

        $this->artisan('vff:import-station-heritage', ['--dry-run' => true, '--cache-dir' => self::CACHE, '--csv' => $dir])->assertSuccessful();

        $this->assertStringContainsString('PA00000007', (string) file_get_contents($dir.'/non-rattachees.csv'));
        $this->assertStringContainsString('PA00000002', (string) file_get_contents($dir.'/rapport.csv'));
    }

    public function test_a_dataset_under_another_licence_is_refused(): void
    {
        $this->stations();
        Http::fake(['*' => Http::response(['license' => 'notspecified', 'organization' => ['name' => 'Toulouse métropole'], 'resources' => []])]);

        $this->artisan('vff:import-station-heritage', ['--cache-dir' => $this->sandbox.'/cache-vide'])
            ->expectsOutputToContain('Licence ou éditeur inattendus')
            ->assertFailed();
        $this->assertSame(0, DB::table('station_heritage')->count());
    }
}
