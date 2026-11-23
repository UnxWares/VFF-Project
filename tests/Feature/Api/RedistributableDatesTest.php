<?php

namespace Tests\Feature\Api;

use App\Services\StationSheet;
use App\Support\RedistributableDates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Dates servies hors du site (App\Support\RedistributableDates) : une date de ligne ou de gare qui ne repose que sur
 * une source à usage non commercial ne sort ni par REST, ni par GraphQL, ni par le serveur MCP ; le site la garde.
 *
 * Réseau de test (BuildsRailNetwork) : Paris-Saint-Lazare - Versailles-Rive-Droite. Faits ajoutés :
 *   - une fermeture de la ligne en 1938 (année seule) d'une source non commerciale ;
 *   - Paris-Saint-Lazare fermée en 1938 d'après cette seule fermeture (date déduite par VFF) : date retirée ;
 *   - Versailles-Rive-Droite fermée en 1938 de même, mais Wikipédia date sa fermeture du 15/03/1938 : cette date est servie ;
 *   - une ouverture en 1880 de la même source, qui borne la desserte de Versailles-Rive-Droite : borne nulle.
 */
class RedistributableDatesTest extends TestCase
{
    use BuildsRailNetwork;
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private const NON_COMMERCIAL = 'archeoferroviaire';

    private int $closure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();

        $this->closure = $this->lineEvent('closure', '1938-01-01', self::NON_COMMERCIAL);
        // Ouverture de la même source : elle borne la desserte de Versailles-Rive-Droite (station_lines plus bas).
        $this->lineEvent('opening', '1880-01-01', self::NON_COMMERCIAL);

        foreach ([$this->origin, $this->destination] as $station) {
            $station->update(['closed_at' => '1938-01-01', 'closed_precision' => 'year', 'status' => 'declassified',
                'provenance' => ['closed_at' => 'inferred:line_event']]);
            $this->stationEvent($station->id, 'closure', '1938-01-01', 'year', 'inferred:line_event', 'line_event:'.$this->closure);
            $this->stationEvent($station->id, 'opening', $station->opened_at->format('Y-m-d'), 'day', 'wikidata', 'Q1#P1619');
        }
        $this->stationEvent($this->destination->id, 'closure_passenger', '1938-03-15', 'day', 'wikipedia', null);

        DB::table('station_lines')->insert([
            'station_id' => $this->origin->id, 'line_id' => $this->line->id, 'method' => 'stop', 'pk_m' => 0,
            'first_year' => 1839, 'last_year' => null, 'years_source' => 'line_events', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('station_lines')->insert([
            'station_id' => $this->destination->id, 'line_id' => $this->line->id, 'method' => 'stop', 'pk_m' => 17_250,
            'first_year' => 1880, 'last_year' => null, 'years_source' => 'line_events',
            'first_event_date' => '1880-01-01', 'first_event_precision' => 'year', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function lineEvent(string $type, string $date, string $source): int
    {
        return (int) DB::table('line_events')->insertGetId(['line_id' => $this->line->id, 'type' => $type, 'event_date' => $date,
            'date_precision' => 'year', 'description' => 'Fait daté', 'source' => $source, 'source_url' => null,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function stationEvent(int $stationId, string $type, string $date, string $precision, string $source, ?string $ref): void
    {
        DB::table('station_events')->insert(['station_id' => $stationId, 'type' => $type, 'event_date' => $date, 'date_precision' => $precision,
            'source' => $source, 'source_ref' => $ref, 'source_url' => null, 'is_inferred' => str_starts_with($source, 'inferred:'),
            'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_rest_never_serves_a_line_event_without_a_redistributable_source(): void
    {
        $dates = array_column($this->getJson('/api/v1/events?line='.$this->line->slug)->assertOk()->json('data'), 'date');
        $this->assertSame(['1839-08-02'], $dates);
        $this->getJson('/api/v1/events/'.$this->closure)->assertNotFound();
        $this->getJson('/api/v1/lines/'.$this->line->slug)->assertOk()->assertJsonPath('data.key_figures.event_count', 1);
    }

    public function test_rest_station_events_leave_out_a_date_inferred_from_such_an_event(): void
    {
        $events = $this->getJson("/api/v1/stations/{$this->origin->slug}/events")->assertOk()->json('data');
        $this->assertSame(['opening'], array_column($events, 'type'));
        $this->assertSame(['opening', 'closure_passenger'],
            array_column($this->getJson("/api/v1/stations/{$this->destination->slug}/events")->assertOk()->json('data'), 'type'));
    }

    public function test_rest_station_sheet_directory_and_line_sheet_null_or_replace_the_dates(): void
    {
        $this->getJson("/api/v1/stations/{$this->origin->slug}")->assertOk()
            ->assertJsonPath('data.closed_at', null)->assertJsonPath('data.closed_precision', null)
            ->assertJsonPath('data.opened_at', '1837-08-26');
        $sheet = $this->getJson("/api/v1/stations/{$this->destination->slug}")->assertOk()
            ->assertJsonPath('data.closed_at', '1938-03-15')->assertJsonPath('data.closed_precision', 'day')
            ->assertJsonPath('data.lines.0.first_year', 1880)
            ->assertJsonPath('data.lines.0.service_start', null)->json('data');
        $this->assertNotContains('1938-01-01', array_column($sheet['chronology'], 'date'));

        $directory = $this->getJson('/api/v1/stations?per_page=10')->assertOk()->json('data');
        $this->assertCount(2, $directory, json_encode($directory) ?: '');
        $directory = array_column($directory, null, 'slug');
        $this->assertSame([null, null], [$directory[$this->origin->slug]['closed_at'], $directory[$this->origin->slug]['closed_precision']]);
        $this->assertSame(['1938-03-15', 'day'], [$directory[$this->destination->slug]['closed_at'], $directory[$this->destination->slug]['closed_precision']]);

        $stations = array_column($this->getJson('/api/v1/lines/'.$this->line->slug)->assertOk()->json('data.stations'), null, 'slug');
        $this->assertNull($stations[$this->origin->slug]['closed_at']);
        $this->assertNull($stations[$this->origin->slug]['closed_precision']);
        $this->assertSame('1938-03-15', $stations[$this->destination->slug]['closed_at']);
        $this->assertNull($stations[$this->destination->slug]['service_start']);
    }

    public function test_graphql_applies_the_same_rule(): void
    {
        $data = $this->graphQL('query ($id: ID!, $slug: String!, $o: String!) {
                lineEvent(id: $id) { id }
                line(slug: $slug) { events { event_date } }
                origin: station(slug: $o) { opened_at closed_at events { type } sheet { closed_precision chronology { date } } }
            }', ['id' => (string) $this->closure, 'slug' => $this->line->slug, 'o' => $this->origin->slug])
            ->json();
        $this->assertArrayNotHasKey('errors', $data, json_encode($data['errors'] ?? null) ?: '');
        $data = $data['data'];
        $this->assertNull($data['lineEvent']);
        $this->assertSame(['1839-08-02'], array_column($data['line']['events'], 'event_date'));
        $this->assertSame('1837-08-26', $data['origin']['opened_at']);
        $this->assertNull($data['origin']['closed_at']);
        $this->assertSame(['opening'], array_column($data['origin']['events'], 'type'));
        $this->assertNull($data['origin']['sheet']['closed_precision']);
        $this->assertNotContains('1938-01-01', array_column($data['origin']['sheet']['chronology'], 'date'));
    }

    public function test_mcp_list_and_sheet_of_stations_apply_the_same_rule(): void
    {
        $list = array_column($this->tool('list_stations', ['q' => 'Versailles'])['stations'], null, 'slug');
        $this->assertSame(['1938-03-15', 'day'], [$list[$this->destination->slug]['closed_at'], $list[$this->destination->slug]['closed_precision']]);

        $sheet = $this->tool('get_station', ['station' => $this->origin->slug]);
        $this->assertNull($sheet['station']['closed']);
        $this->assertSame(['opening'], array_column($sheet['events'], 'type'));
        $this->assertSame(['opening'], array_column($this->tool('station_events', ['station' => $this->origin->slug])['events'], 'type'));
    }

    public function test_a_department_closure_only_takes_a_served_closure_of_the_same_scope(): void
    {
        // Seul le fret est daté par une source servie cette année-là : il ne date pas la fermeture totale.
        $this->lineEvent('closure_freight', '1938-06-01', 'wikipedia');
        $sheet = app(RedistributableDates::class)->departmentSheet(['lines' => [[
            'slug' => $this->line->slug, 'opened' => ['date' => '1839-08-02', 'precision' => 'day'],
            'closed' => ['date' => '1938-01-01', 'precision' => 'year', 'scope' => 'all'], 'electrified' => null,
        ]], 'milestones' => ['first_opening' => null, 'reopenings' => []]]);
        $this->assertNull($sheet['lines'][0]['closed']);
        $this->assertSame('1839-08-02', $sheet['lines'][0]['opened']['date']);

        $freight = app(RedistributableDates::class)->departmentSheet(['lines' => [[
            'slug' => $this->line->slug, 'closed' => ['date' => '1938-01-01', 'precision' => 'year', 'scope' => 'freight'],
        ]]]);
        $this->assertSame(['date' => '1938-06-01', 'precision' => 'year', 'scope' => 'freight'], $freight['lines'][0]['closed']);
    }

    public function test_the_directory_sorts_on_the_served_date(): void
    {
        $slugs = array_column($this->getJson('/api/v1/stations?sort=closed&per_page=10')->assertOk()->json('data'), 'slug');
        // Paris-Saint-Lazare n'a plus de date servie : en dernier, comme toute date manquante.
        $this->assertSame([$this->destination->slug, $this->origin->slug], $slugs);
    }

    public function test_the_site_keeps_every_date(): void
    {
        $page = app(StationSheet::class)->page($this->origin->fresh(), withNeighbors: false);
        $this->assertSame('1938-01-01', $page['station']['closed_at']);
        $this->assertContains('closure', array_column($page['events']->map(fn ($e): array => (array) $e)->all(), 'type'));
    }

    /** @return array<string, mixed> */
    private function tool(string $tool, array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) ($result['isError'] ?? false), $result['content'][0]['text'] ?? '');

        return json_decode($result['content'][0]['text'], true);
    }
}
