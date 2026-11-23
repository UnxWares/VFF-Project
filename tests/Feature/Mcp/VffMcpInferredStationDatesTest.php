<?php

namespace Tests\Feature\Mcp;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Une date de gare déduite d'un événement de ligne (`inferred:line_event`, vff:build-station-eras) recopie la date de
 * cet événement : quand il vient de la source non commerciale, la date n'est servie ni par `get_station` (ouverture et
 * chronologie), ni par `station_events`, `list_stations` ou `get_commune` (relevé du 23/11/2026 : sept gares des voies
 * militaires de la Somme ouvertes « en 1914 » d'après la seule source non commerciale, dont Aveluy). La même date
 * déduite d'un événement d'une source servie l'est toujours.
 */
class VffMcpInferredStationDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Era::factory()->current()->create(['year' => 2030]);

        $line = Line::factory()->create(['slug' => 'voie-militaire', 'code' => null, 'name' => 'Voie militaire d\'Aveluy']);
        $event = fn (string $source, ?string $url): int => (int) DB::table('line_events')->insertGetId([
            'line_id' => $line->id, 'type' => 'opening', 'event_date' => '1914-01-01', 'date_precision' => 'year',
            'description' => 'Ouverture', 'source' => $source, 'source_url' => $url, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $nonCommercial = $event('archeoferroviaire', null);
        $served = $event('wikipedia', 'https://fr.wikipedia.org/wiki/Voie');

        foreach (['aveluy' => $nonCommercial, 'albert' => $served] as $slug => $eventId) {
            $station = Station::factory()->create([
                'slug' => $slug, 'name' => ucfirst($slug), 'city' => ucfirst($slug), 'type' => 'gare', 'department_code' => '80',
                'status' => 'declassified', 'geom' => Point::makeGeodetic(50.0, 2.65),
                'opened_at' => '1914-01-01', 'opened_precision' => 'year', 'provenance' => ['opened_at' => 'inferred:line_event'],
            ]);
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'test']);
            DB::table('station_events')->insert([
                'station_id' => $station->id, 'type' => 'opening', 'event_date' => '1914-01-01', 'date_precision' => 'year',
                'source' => 'inferred:line_event', 'source_ref' => 'line_event:'.$eventId, 'source_url' => null, 'is_inferred' => true,
                'note' => 'Ouverture de la ligne', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function tool(string $tool, array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) ($result['isError'] ?? false), $result['content'][0]['text']);

        return json_decode($result['content'][0]['text'], true);
    }

    public function test_get_station_never_serves_a_date_inferred_from_a_non_commercial_line_event(): void
    {
        $aveluy = $this->tool('get_station', ['station' => 'aveluy']);
        $this->assertNull($aveluy['station']['opened']);
        $this->assertSame([], $aveluy['events']);

        $albert = $this->tool('get_station', ['station' => 'albert']);
        $this->assertSame('1914-01-01', $albert['station']['opened']['date']);
        $this->assertSame(['opening'], array_column($albert['events'], 'type'));
    }

    public function test_station_events_and_list_stations_leave_the_inferred_date_out(): void
    {
        $this->assertSame([], $this->tool('station_events', ['station' => 'aveluy'])['events']);
        $this->assertSame(['1914-01-01'], array_column($this->tool('station_events', ['station' => 'albert'])['events'], 'date'));

        $stations = array_column($this->tool('list_stations', ['department' => '80'])['stations'], null, 'slug');
        $this->assertNull($stations['aveluy']['opened_at']);
        $this->assertNull($stations['aveluy']['opened_precision']);
        $this->assertSame('1914-01-01', $stations['albert']['opened_at']);
    }

    public function test_get_commune_leaves_the_inferred_opening_out_of_its_stations_and_chronology(): void
    {
        DB::table('communes')->insert(['geom' => DB::raw('ST_SetSRID(ST_MakePoint(2.65, 50.0), 4326)'), 'code_insee' => '80050',
            'nom' => 'Aveluy', 'nom_norm' => 'aveluy', 'type' => 'commune-actuelle', 'departement' => '80', 'population' => 500]);
        Cache::put('communes:rail:stations', ['80050'], 600);
        $station = fn (string $slug): array => ['slug' => $slug, 'name' => ucfirst($slug), 'type' => 'gare', 'status' => 'declassified',
            'opened_at' => '1914-01-01', 'opened_precision' => 'year', 'opened_after' => null,
            'closed_at' => null, 'closed_precision' => null, 'closed_after' => null, 'lng' => 2.65, 'lat' => 50.0];
        $opening = fn (string $slug): array => ['kind' => 'station_opening', 'date' => '1914-01-01', 'precision' => 'year', 'after' => null,
            'station' => ['slug' => $slug, 'name' => ucfirst($slug)]];
        Cache::put('communes:sheet:v1:80050:fr', [
            'commune' => ['insee' => '80050', 'slug' => '80050-aveluy', 'name' => 'Aveluy', 'department_code' => '80', 'department_name' => 'Somme', 'population' => 500, 'former_names' => []],
            'current_era' => 2030, 'counts' => ['stations' => 2, 'stations_open' => 0, 'lines' => 0, 'lines_open' => 0],
            'stations' => [$station('aveluy'), $station('albert')], 'lines' => [],
            'chronology' => [$opening('albert'), $opening('aveluy')], 'neighbors' => [], 'map' => ['stations' => [], 'lines' => []],
        ], 600);

        $sheet = $this->tool('get_commune', ['commune' => '80050']);
        $stations = array_column($sheet['stations'], null, 'slug');
        $this->assertNull($stations['aveluy']['opened_at']);
        $this->assertNull($stations['aveluy']['opened_precision']);
        $this->assertSame('1914-01-01', $stations['albert']['opened_at']);
        $this->assertSame(['albert'], array_column(array_column($sheet['chronology'], 'station'), 'slug'));
        $this->assertSame(1, $sheet['chronology_total']);
    }
}
