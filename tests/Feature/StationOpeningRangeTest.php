<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Ouverture encadrée (règle G3 de vff:build-station-eras) servie de bout en bout : `opened_precision` = `range` et
 * `opened_after` (l'année qui précède la borne basse) sur la fiche gare, l'annuaire, la liste des gares de la fiche
 * ligne, GraphQL et l'outil MCP get_station ; `opened_after` reste nul pour toute autre précision.
 *
 * Cas d'essai (modelé sur Balsièges-Bourg, ligne datée de 1955, voie hors service près de la gare jusqu'en 1960) :
 * la carte ne dessert la gare qu'à l'ère 1970, la règle borne donc l'ouverture « entre 1955 et 1970 ».
 */
class StationOpeningRangeTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private Line $line;

    private Station $bounded;

    private Station $sourced;

    protected function setUp(): void
    {
        parent::setUp();
        $eras = [];
        foreach ([1950, 1960, 1970, 1980, 1990] as $year) {
            $eras[$year] = Era::factory()->create(['year' => $year, 'label' => "Années {$year}"]);
        }
        $eras[2030] = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd\'hui']);

        $this->line = Line::factory()->create(['code' => '960000', 'name' => 'Ligne témoin de l\'intervalle', 'slug' => 'ligne-intervalle']);
        foreach ($eras as $year => $era) {
            $outOfService = $year <= 1960;
            LineSegment::factory()->create([
                'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => $outOfService ? 'abandoned' : 'in_service',
                'is_heritage' => false, 'traffic_type' => $outOfService ? 'unknown' : 'mixed',
                'geom' => LineString::make([Point::makeGeodetic(47.0, 2.00), Point::makeGeodetic(47.0, 2.20)]),
            ]);
        }
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'opening', 'event_date' => '1955-05-01', 'date_precision' => 'day',
            'description' => 'opening', 'confidence' => 'high', 'section_resolution' => 'whole_line', 'created_at' => now(), 'updated_at' => now()]);

        $this->bounded = $this->station('Gare bornée', 2.10, 1000, ['status' => 'sncf'], null, null);
        // Date sourcée : jamais bornée, même précision qu'avant.
        $this->sourced = $this->station('Gare sourcée', 2.15, 2000, ['status' => 'sncf', 'opened_at' => 'wikidata'], '1958-03-02', 'day');
    }

    /** @param  array<string, string>  $provenance */
    private function station(string $name, float $lng, int $pk, array $provenance, ?string $openedAt, ?string $precision): Station
    {
        $station = Station::factory()->create([
            'name' => $name, 'geom' => Point::makeGeodetic(47.0, $lng), 'status' => 'active', 'source_status' => 'active',
            'position_source' => 'sncf', 'provenance' => $provenance, 'opened_at' => $openedAt, 'opened_precision' => $precision,
        ]);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk',
            'pk_m' => $pk, 'pk_source' => 'sncf', 'distance_m' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return $station;
    }

    private function activateG3(): void
    {
        $this->artisan('vff:build-station-eras', ['--today' => '2026-11-22', '--rules' => 'g3'])->assertSuccessful();
    }

    public function test_the_rule_writes_the_interval_the_services_read(): void
    {
        $this->activateG3();

        $fresh = $this->bounded->fresh();
        $this->assertSame('range', $fresh->opened_precision);
        $this->assertSame('1970-12-31', $fresh->opened_at?->format('Y-m-d'));
        $this->assertSame(1954, $fresh->provenance['opened_after'] ?? null);
        $this->assertSame('day', $this->sourced->fresh()->opened_precision);
        $this->assertArrayNotHasKey('opened_after', $this->sourced->fresh()->provenance);
    }

    public function test_the_station_sheet_api_serves_opened_after_only_for_a_range(): void
    {
        $this->activateG3();

        $this->getJson("/api/v1/stations/{$this->bounded->slug}")->assertOk()
            ->assertJsonPath('data.opened_at', '1970-12-31')
            ->assertJsonPath('data.opened_precision', 'range')
            ->assertJsonPath('data.opened_after', 1954);
        $this->getJson("/api/v1/stations/{$this->sourced->slug}")->assertOk()
            ->assertJsonPath('data.opened_precision', 'day')
            ->assertJsonPath('data.opened_after', null);
    }

    public function test_the_station_directory_serves_opened_after(): void
    {
        $this->activateG3();

        $rows = array_column((array) $this->getJson('/api/v1/stations')->assertOk()->json('data'), null, 'slug');
        $this->assertSame(1954, $rows[$this->bounded->slug]['opened_after']);
        $this->assertSame('range', $rows[$this->bounded->slug]['opened_precision']);
        $this->assertNull($rows[$this->sourced->slug]['opened_after']);
    }

    public function test_the_web_directory_serves_the_interval_and_sorts_by_the_upper_bound(): void
    {
        $this->activateG3();

        $this->get('/stations?sort=opened')->assertOk()->assertInertia(function (Assert $page) {
            $rows = array_column((array) $page->toArray()['props']['stations']['data'], null, 'slug');
            $this->assertSame([1970, 'range', 1954], [$rows[$this->bounded->slug]['opened_year'], $rows[$this->bounded->slug]['opened_precision'], $rows[$this->bounded->slug]['opened_after']]);
            $this->assertNull($rows[$this->sourced->slug]['opened_after']);
            // Tri par date d'ouverture : la borne haute (opened_at), jamais la borne basse.
            $years = array_column(array_values((array) $page->toArray()['props']['stations']['data']), 'opened_year');
            $sorted = $years;
            sort($sorted);
            $this->assertSame($sorted, $years);
        });
    }

    public function test_the_line_station_list_serves_opened_after_to_the_api_and_the_page(): void
    {
        $this->activateG3();

        $stations = array_column((array) $this->getJson("/api/v1/lines/{$this->line->slug}")->assertOk()->json('data.stations'), null, 'slug');
        $this->assertSame(1954, $stations[$this->bounded->slug]['opened_after']);
        $this->assertSame('range', $stations[$this->bounded->slug]['opened_precision']);
        $this->assertNull($stations[$this->sourced->slug]['opened_after']);

        // Les props de la page : le composant écrit « entre 1955 et 1970 » avec ces trois champs.
        $this->get("/lines/{$this->line->slug}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stations.0.slug', $this->bounded->slug)
            ->where('stations.0.opened_at', '1970-12-31')
            ->where('stations.0.opened_precision', 'range')
            ->where('stations.0.opened_after', 1954)
            ->where('stations.1.opened_after', null));
    }

    public function test_graphql_serves_opened_after_on_the_station_and_its_sheet(): void
    {
        $this->activateG3();

        $slug = $this->bounded->slug;
        $this->graphQL("{ station(slug: \"{$slug}\") { opened_precision opened_after sheet { opened_precision opened_after } } }")
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.station.opened_precision', 'range')
            ->assertJsonPath('data.station.opened_after', 1954)
            ->assertJsonPath('data.station.sheet.opened_after', 1954);
        $this->graphQL("{ station(slug: \"{$this->sourced->slug}\") { opened_precision opened_after sheet { opened_after } } }")
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.station.opened_precision', 'day')
            ->assertJsonPath('data.station.opened_after', null)
            ->assertJsonPath('data.station.sheet.opened_after', null);
        $this->graphQL('{ stationDirectory { data { slug opened_after } } }')->assertJsonMissingPath('errors')
            ->assertJsonFragment(['slug' => $slug, 'opened_after' => 1954]);
        $this->graphQL("{ line(slug: \"{$this->line->slug}\") { sheet { stations { slug opened_after } } } }")->assertJsonMissingPath('errors')
            ->assertJsonFragment(['slug' => $slug, 'opened_after' => 1954]);
    }

    public function test_the_mcp_station_sheet_writes_the_interval_in_each_language(): void
    {
        $this->activateG3();

        foreach (['fr' => 'entre 1955 et 1970', 'en' => 'between 1955 and 1970', 'de' => 'zwischen 1955 und 1970'] as $lang => $when) {
            $station = $this->mcp('get_station', ['station' => $this->bounded->slug, 'lang' => $lang])['station'];
            $this->assertSame(1954, $station['opened_after']);
            $this->assertSame('range', $station['opened']['precision']);
            $this->assertNull($station['opened']['date']);
            $this->assertSame(1955, $station['opened']['from_year']);
            $this->assertSame(1970, $station['opened']['to_year']);
            $this->assertSame($when, $station['opened']['when']);
        }

        $sourced = $this->mcp('get_station', ['station' => $this->sourced->slug])['station'];
        $this->assertNull($sourced['opened_after']);
        $this->assertSame('day', $sourced['opened']['precision']);
        $this->assertSame('1958-03-02', $sourced['opened']['date']);
    }

    public function test_the_mcp_directory_serves_opened_after(): void
    {
        $this->activateG3();

        $rows = array_column((array) $this->mcp('list_stations')['stations'], null, 'slug');
        $this->assertSame(1954, $rows[$this->bounded->slug]['opened_after']);
        $this->assertNull($rows[$this->sourced->slug]['opened_after']);
    }

    /** @param  array<string, mixed>  $arguments */
    private function mcp(string $tool, array $arguments = []): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => (object) ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) $result['isError'], (string) $result['content'][0]['text']);

        return json_decode((string) $result['content'][0]['text'], true) ?? [];
    }
}
