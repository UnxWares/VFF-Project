<?php

namespace Tests\Feature;

use App\Support\StationNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * vff:consolidate-stations : événements d'ouverture et de fermeture tirés d'OSM et d'OHM, option --fresh,
 * noms génériques sans gare voisine.
 */
class ConsolidateStationsEventsTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributes */
    private function source(string $source, string $ref, string $name, float $lat, float $lng, array $attributes = []): int
    {
        return DB::table('station_sources')->insertGetId($attributes + [
            'source' => $source, 'source_ref' => $ref, 'name' => $name, 'name_norm' => StationNames::normalize($name),
            'kind' => 'gare', 'lifecycle' => 'active', 'seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $lng, $lat)),
        ]);
    }

    private function stationId(int $source): int
    {
        return (int) DB::table('station_sources')->where('id', $source)->value('station_id');
    }

    /** @return list<string> */
    private function events(int $stationId): array
    {
        return DB::table('station_events')->where('station_id', $stationId)->orderBy('event_date')->orderBy('type')
            ->get(['type', 'event_date', 'source', 'source_url'])->map(fn ($e) => "{$e->type} {$e->event_date} {$e->source}")->all();
    }

    public function test_osm_opening_and_closure_dates_become_events_but_an_active_object_keeps_no_closure(): void
    {
        $closed = $this->source('osm', 'n10', 'Alpha-les-Bains', 46.0, 3.0, ['lifecycle' => 'disused', 'opened_on' => '1875-06-01',
            'opened_precision' => 'day', 'closed_on' => '1939-09-03', 'closed_precision' => 'day']);
        $active = $this->source('osm', 'n11', 'Bravo-sur-Loire', 47.0, 4.0, ['lifecycle' => 'active', 'opened_on' => '1880-01-01',
            'opened_precision' => 'year', 'closed_on' => '1950-01-01', 'closed_precision' => 'year']);
        $tooOld = $this->source('osm', 'n12', 'Charlie-en-Bresse', 48.0, 5.0, ['lifecycle' => 'disused', 'opened_on' => '1800-01-01', 'opened_precision' => 'year']);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $this->assertSame(['opening 1875-06-01 osm', 'closure 1939-09-03 osm'], $this->events($this->stationId($closed)));
        $this->assertSame(['opening 1880-01-01 osm'], $this->events($this->stationId($active)));
        $this->assertSame([], $this->events($this->stationId($tooOld)));
        $url = DB::table('station_events')->where('station_id', $this->stationId($closed))->where('type', 'opening')->value('source_url');
        $this->assertSame('https://www.openstreetmap.org/node/10', $url);
    }

    public function test_ohm_dates_open_the_station_and_close_it_only_when_every_ohm_object_ended(): void
    {
        $ended = $this->source('ohm', 'w20', 'Delta-Saint-Pierre', 45.0, 2.0, ['lifecycle' => 'unknown', 'opened_on' => '1860-01-01',
            'opened_precision' => 'year', 'closed_on' => '1938-01-01', 'closed_precision' => 'year']);
        $continues = $this->source('ohm', 'w21', 'Echo-la-Gare', 44.0, 1.0, ['lifecycle' => 'unknown', 'opened_on' => '1860-01-01',
            'opened_precision' => 'year', 'closed_on' => '1938-01-01', 'closed_precision' => 'year']);
        // Un second objet OHM de la même gare, sans fin : un changement de nom, pas une fermeture.
        $this->source('ohm', 'w22', 'Echo-la-Gare', 44.0, 1.0, ['lifecycle' => 'unknown', 'opened_on' => '1938-01-01', 'opened_precision' => 'year']);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $this->assertContains('opening 1860-01-01 ohm', $this->events($this->stationId($ended)));
        $this->assertContains('opening 1860-01-01 ohm', $this->events($this->stationId($continues)));
        $this->assertNotContains('closure 1938-01-01 ohm', $this->events($this->stationId($continues)));
        $url = DB::table('station_events')->where('station_id', $this->stationId($ended))->where('type', 'opening')->value('source_url');
        $this->assertSame('https://www.openhistoricalmap.org/way/20', $url);
    }

    public function test_fresh_drops_the_stations_and_rebuilds_them_from_the_sources(): void
    {
        $a = $this->source('osm', 'n30', 'Foxtrot-les-Pins', 46.5, 3.5);
        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $first = $this->stationId($a);
        $this->assertGreaterThan(0, $first);

        $this->artisan('vff:consolidate-stations', ['--fresh' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('stations')->where('name', 'Foxtrot-les-Pins')->count());
        $this->assertGreaterThan(0, $this->stationId($a));
    }

    public function test_a_second_run_changes_neither_the_stations_nor_the_events(): void
    {
        $this->source('osm', 'n40', 'Golf-sur-Mer', 46.0, 3.0, ['lifecycle' => 'disused', 'opened_on' => '1875-06-01',
            'opened_precision' => 'day', 'closed_on' => '1939-09-03', 'closed_precision' => 'day']);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();
        $snapshot = [DB::table('stations')->count(), DB::table('station_events')->orderBy('id')->get(['type', 'event_date', 'source'])->map(fn ($e) => "{$e->type} {$e->event_date} {$e->source}")->all()];
        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $this->assertSame($snapshot, [DB::table('stations')->count(), DB::table('station_events')->orderBy('id')->get(['type', 'event_date', 'source'])->map(fn ($e) => "{$e->type} {$e->event_date} {$e->source}")->all()]);
    }

    public function test_a_manual_station_stays_apart_from_a_same_named_station_and_carries_its_dates(): void
    {
        $current = $this->source('sncf', '87000001', 'Boulogne-Ville', 50.71527, 1.61, ['passenger' => true, 'opened_on' => '1962-01-01', 'opened_precision' => 'year']);
        $manual = $this->source('manual', 'boulogne-ville-ancienne-gare', 'Boulogne-Ville (ancienne gare)', 50.721798, 1.599333, [
            'lifecycle' => 'historic', 'passenger' => true, 'opened_on' => '1855-01-01', 'opened_precision' => 'year',
            'closed_on' => '1962-01-01', 'closed_precision' => 'year',
            'properties' => json_encode(['source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_de_Boulogne-Ville&oldid=228948464', 'quote' => 'construite en 1855']),
        ]);
        // Un objet OSM voisin à 20 m ne la rejoint pas non plus.
        $this->source('osm', 'n40', 'Boulogne-Ville', 50.7219, 1.5993, ['lifecycle' => 'disused']);

        $this->artisan('vff:consolidate-stations')->assertSuccessful();

        $this->assertNotSame($this->stationId($current), $this->stationId($manual));
        $station = DB::table('stations')->where('id', $this->stationId($manual))->first();
        $this->assertSame('boulogne-ville-ancienne-gare', $station->slug);
        $this->assertSame('declassified', $station->status);
        $this->assertSame(['1855-01-01', '1962-01-01'], [$station->opened_at, $station->closed_at]);
        $this->assertSame(['opening 1855-01-01 manual', 'closure 1962-01-01 manual'], $this->events($station->id));
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=Gare_de_Boulogne-Ville&oldid=228948464',
            DB::table('station_events')->where('station_id', $station->id)->where('type', 'opening')->value('source_url'));
        $this->assertSame([], $this->events($this->stationId($current)));
    }
}
