<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Station;
use App\Services\Sncf\StationAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fréquentation annuelle d'une gare : même contenu en REST (`/stations/{station}/attendance` et `attendance` de la
 * fiche), GraphQL (`Station.attendance`), MCP (`station_attendance`) et page de la fiche ; rupture de série entre
 * 2024 et 2025 ; nul quand les jeux n'ont aucun chiffre.
 */
class StationAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->station = Station::factory()->create(['slug' => 'angouleme', 'name' => 'Angoulême', 'uic_code' => '87584003']);
        Station::factory()->create(['slug' => 'sans-chiffre', 'name' => 'Sans chiffre']);
        $this->year(2023, 1900000, 2090000, '2015-2024', 'frequentation-gares-archive', '2025-07-03');
        $this->year(2024, 1948021, 2142823, '2015-2024', 'frequentation-gares-archive', '2025-07-03');
        $this->year(2025, 1991037, 1991037, '2025', 'frequentation-gares', '2026-11-13');
    }

    private function year(int $year, int $passengers, int $footfall, string $series, string $dataset, string $modified): void
    {
        DB::table('station_attendances')->insert(['station_id' => $this->station->id, 'year' => $year, 'passengers' => $passengers,
            'footfall' => $footfall, 'series' => $series, 'uic_code' => '87584003', 'match_method' => 'uic', 'source' => 'sncf',
            'dataset' => $dataset, 'dataset_modified' => $modified]);
    }

    public function test_rest_returns_every_year_the_latest_and_the_series_break(): void
    {
        $json = $this->getJson('/api/v1/stations/angouleme/attendance')->assertOk()->json();

        $this->assertSame(['slug' => 'angouleme', 'name' => 'Angoulême', 'uic_code' => '87584003'], $json['station']);
        $a = $json['attendance'];
        $this->assertSame([2023, 2024, 2025], array_column($a['years'], 'year'));
        $this->assertSame(['year' => 2024, 'passengers' => 1948021, 'footfall' => 2142823, 'non_passengers' => 194802, 'series' => '2015-2024'], $a['years'][1]);
        $this->assertSame(0, $a['years'][2]['non_passengers']);
        $this->assertSame(['year' => 2025, 'passengers' => 1991037, 'footfall' => 1991037, 'series' => '2025', 'published_year' => 2026], $a['latest']);
        $this->assertTrue($a['series_break']);
        $this->assertSame(['frequentation-gares-archive', 'frequentation-gares'], array_column($a['sources'], 'dataset'));
        $this->assertSame('sncf', $a['provenance']['provider']);
        $this->assertStringContainsString('ODbL', $a['provenance']['license']);
        $this->assertStringContainsString('SNCF Gares & Connexions', $a['attribution']);
    }

    public function test_a_station_without_figures_gets_null_not_an_error(): void
    {
        $this->assertNull($this->getJson('/api/v1/stations/sans-chiffre/attendance')->assertOk()->json('attendance'));
        $this->getJson('/api/v1/stations/inconnue/attendance')->assertNotFound();
    }

    public function test_a_single_series_has_no_break(): void
    {
        DB::table('station_attendances')->where('year', 2025)->delete();

        $this->assertFalse($this->getJson('/api/v1/stations/angouleme/attendance')->json('attendance.series_break'));
    }

    public function test_the_station_sheet_carries_the_same_block_and_a_link(): void
    {
        $rest = $this->getJson('/api/v1/stations/angouleme/attendance')->json('attendance');
        $sheet = $this->getJson('/api/v1/stations/angouleme')->assertOk()->json();

        $this->assertSame($rest, $sheet['data']['attendance']);
        $this->assertStringEndsWith('/api/v1/stations/angouleme/attendance', $sheet['data']['links']['attendance']);
        $this->assertNull($this->getJson('/api/v1/stations/sans-chiffre')->json('data.attendance'));
    }

    public function test_the_web_page_gets_the_attendance_prop(): void
    {
        $this->getWithDeferredProps('/stations/angouleme')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Stations/Show')->where('attendance.latest.year', 2025)->where('attendance.series_break', true));
        $this->getWithDeferredProps('/stations/sans-chiffre')->assertOk()->assertInertia(fn (Assert $page) => $page->where('attendance', null));
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $rest = $this->getJson('/api/v1/stations/angouleme/attendance')->json('attendance');
        $gql = $this->postJson('/graphql', ['query' => '{ stations(first: 10) { data { slug attendance {
            uic_code years { year passengers footfall non_passengers series } latest { year passengers footfall series published_year }
            series_break sources { series dataset dataset_modified } attribution provenance { provider license } } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.stations.data');
        $bySlug = array_column($gql, 'attendance', 'slug');

        $this->assertSame($rest['years'], $bySlug['angouleme']['years']);
        $this->assertSame($rest['latest'], $bySlug['angouleme']['latest']);
        $this->assertSame($rest['sources'], $bySlug['angouleme']['sources']);
        $this->assertTrue($bySlug['angouleme']['series_break']);
        $this->assertNull($bySlug['sans-chiffre']);

        $call = fn (string $slug) => $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'station_attendance', 'arguments' => ['station' => $slug]]])
            ->assertOk()->json('result');
        $result = $call('angouleme');
        $this->assertFalse((bool) ($result['isError'] ?? false));
        $mcp = json_decode($result['content'][0]['text'], true);
        $this->assertSame($rest['years'], $mcp['attendance']['years']);
        $this->assertTrue($mcp['attendance']['series_break']);
        $this->assertStringContainsString('SNCF Gares & Connexions', $mcp['attendance']['mention']);

        $none = json_decode($call('sans-chiffre')['content'][0]['text'], true);
        $this->assertNull($none['attendance']);
        $this->assertTrue((bool) ($call('inconnue')['isError'] ?? false));
    }

    public function test_a_missing_table_is_checked_again_once_the_migration_has_run(): void
    {
        $reset = fn () => (new \ReflectionProperty(StationAttendance::class, 'ready'))->setValue(null, null);
        $reset();
        Cache::flush();

        DB::beginTransaction();
        Schema::drop('station_attendances');
        $this->assertFalse(StationAttendance::ready());
        $this->assertNull(app(StationAttendance::class)->forStation((int) $this->station->id));
        DB::rollBack();

        $this->assertTrue(StationAttendance::ready(), 'un constat négatif ne reste pas en mémoire du processus');
        $reset();
    }
}
