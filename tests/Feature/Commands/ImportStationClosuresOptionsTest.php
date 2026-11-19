<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-station-closures, options et garde-fous (sources OSM seules, sans réseau) :
 * source ou gare inconnue refusée, --dry-run n'écrit rien, une seule source relue ne supprime
 * que les événements de cette source, une gare en service n'est jamais fermée.
 */
class ImportStationClosuresOptionsTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandboxStorage();
        Http::fake(['*' => Http::response([], 500)]);
    }

    private function osmSource(int $stationId, string $description): void
    {
        DB::table('station_sources')->insert([
            'source' => 'osm', 'source_ref' => 'n'.$stationId, 'station_id' => $stationId, 'name' => 'x', 'lifecycle' => 'disused',
            'properties' => json_encode(['description' => $description]), 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.0, 48.0), 4326)'),
        ]);
    }

    private function closedStation(string $slug): int
    {
        return $this->station($slug, ucfirst($slug), 48.0, 2.0, ['status' => 'declassified', 'source_status' => 'declassified'])->id;
    }

    public function test_an_unknown_source_is_refused(): void
    {
        $this->artisan('vff:import-station-closures', ['--source' => ['inconnue']])
            ->expectsOutputToContain('Source inconnue : inconnue')
            ->assertFailed();
    }

    public function test_an_unknown_station_is_refused(): void
    {
        $this->artisan('vff:import-station-closures', ['--source' => ['osm'], '--station' => 'nulle-part'])
            ->expectsOutputToContain('Gare introuvable : nulle-part')
            ->assertFailed();
    }

    public function test_osm_note_gives_a_passenger_closure_dated_to_the_year(): void
    {
        $station = $this->closedStation('fenouillet');
        $this->osmSource($station, 'Gare non desservie depuis 2016.');

        $this->artisan('vff:import-station-closures', ['--source' => ['osm']])->assertSuccessful();

        $event = DB::table('station_events')->where('station_id', $station)->first();
        $this->assertSame(['closure_passenger', '2016-01-01', 'year', 'osm:note'], [$event->type, substr($event->event_date, 0, 10), $event->date_precision, $event->source]);
        $this->assertFalse((bool) $event->is_inferred);
    }

    public function test_dry_run_lists_events_and_writes_nothing(): void
    {
        $station = $this->closedStation('fenouillet');
        $this->osmSource($station, 'Gare fermée en 1972.');
        $before = $this->snapshot(['station_events', 'stations']);

        $this->artisan('vff:import-station-closures', ['--source' => ['osm'], '--dry-run' => true])
            ->expectsOutputToContain('1972-01-01')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot(['station_events', 'stations']));
    }

    public function test_rereading_one_source_only_replaces_its_own_events(): void
    {
        $station = $this->closedStation('fenouillet');
        $this->osmSource($station, 'Gare fermée en 1972.');
        DB::table('station_events')->insert([
            ['station_id' => $station, 'type' => 'closure', 'event_date' => '1980-01-01', 'date_precision' => 'year',
                'source' => 'wikipedia', 'source_ref' => 'gare:Fenouillet', 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()],
            ['station_id' => $station, 'type' => 'closure', 'event_date' => '1950-01-01', 'date_precision' => 'year',
                'source' => 'osm:note', 'source_ref' => null, 'is_inferred' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('vff:import-station-closures', ['--source' => ['osm']])->assertSuccessful();

        $events = DB::table('station_events')->where('station_id', $station)->orderBy('event_date')
            ->get(['source', 'event_date'])->map(fn ($e) => $e->source.' '.substr($e->event_date, 0, 4))->all();
        $this->assertSame(['osm:note 1972', 'wikipedia 1980'], $events, 'l\'ancien événement OSM est remplacé, celui de Wikipédia est gardé');
    }

    public function test_a_station_open_today_is_never_closed_by_a_note(): void
    {
        $open = $this->station('ouverte', 'Ouverte', 48.0, 2.0, ['status' => 'active', 'source_status' => 'active'])->id;
        $this->osmSource($open, 'Gare fermée en 1972.');

        $this->artisan('vff:import-station-closures', ['--source' => ['osm']])->assertSuccessful();

        $this->assertSame(0, DB::table('station_events')->where('station_id', $open)->count());
    }
}
