<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\Timetable\Timetables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Desserte d'un jour type : même contenu en REST (`/stations/{station}/timetable` et `timetable` de la fiche), GraphQL
 * (`Station.timetable`, `LineSheet.timetable`), MCP (`station_timetable`, `line_timetable`) et page de la fiche ; nul sans train ;
 * aucune erreur tant que la migration n'est pas appliquée.
 */
class StationTimetableApiTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        (new ReflectionProperty(Timetables::class, 'ready'))->setValue(null, null);
        Era::factory()->current()->create(['year' => 2030]);
        $this->station = Station::factory()->create(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'uic_code' => '87592006']);
        Station::factory()->create(['slug' => 'sans-train', 'name' => 'Sans train']);
        $this->line = Line::factory()->create(['slug' => 'paris-bordeaux', 'name' => 'Paris - Bordeaux']);
        DB::table('station_timetables')->insert([
            'station_id' => $this->station->id, 'service_date' => '2026-12-08', 'trains' => 144,
            'by_type' => json_encode(['intercites' => 20, 'ter' => 124]), 'first_time' => '05:03', 'last_time' => '23:02',
            'directions' => json_encode([['trains' => 72, 'by_type' => ['ter' => 62, 'intercites' => 10], 'first' => '05:03', 'last' => '21:28', 'heading' => 'ne',
                'destinations' => [['name' => 'Paris-Austerlitz', 'slug' => 'paris-austerlitz', 'trains' => 10]], 'origins' => []]]),
            'destinations' => json_encode([['name' => 'Brive-la-Gaillarde', 'slug' => 'brive-la-gaillarde', 'trains' => 14]]),
            'origins' => json_encode([]),
            'feeds' => json_encode([['feed' => 'sncf', 'version' => '2026-11-22', 'date' => '2026-12-08', 'valid_to' => '2027-04-30', 'name' => 'SNCF', 'licence' => 'ODbL', 'page' => 'https://transport.data.gouv.fr/datasets/horaires-sncf']]),
            'source' => 'sncf', 'computed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('line_timetables')->insert(['line_id' => $this->line->id, 'service_date' => '2026-12-08', 'trains' => 41,
            'by_type' => json_encode(['ter' => 30, 'tgv' => 11]), 'source' => 'sncf', 'computed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Timetables::class, 'ready'))->setValue(null, null);
        parent::tearDown();
    }

    public function test_rest_returns_the_typical_day_with_types_directions_and_sources(): void
    {
        $json = $this->getJson('/api/v1/stations/limoges-benedictins/timetable')->assertOk()->json();

        $this->assertSame(['slug' => 'limoges-benedictins', 'name' => 'Limoges-Bénédictins', 'uic_code' => '87592006'], $json['station']);
        $t = $json['timetable'];
        $this->assertSame(['2026-12-08', 'tuesday', 144], [$t['service_date'], $t['weekday'], $t['trains']]);
        $this->assertSame(['intercites' => 20, 'ter' => 124], $t['by_type'], 'ordre stable : TGV, Intercités, TER, Transilien, autres');
        $this->assertSame(['05:03', '23:02'], [$t['first'], $t['last']]);
        $this->assertSame('Paris-Austerlitz', $t['directions'][0]['destinations'][0]['name']);
        $this->assertSame('Brive-la-Gaillarde', $t['destinations'][0]['name']);
        $this->assertSame('2026-11-22', $t['feeds'][0]['version']);
        $this->assertSame('sncf', $t['provenance']['provider']);
        $this->assertStringContainsString('ODbL', $t['attribution']);
        $this->assertStringContainsString('mardi', $t['method']);
    }

    public function test_a_station_without_trains_gets_null_not_an_error(): void
    {
        $this->assertNull($this->getJson('/api/v1/stations/sans-train/timetable')->assertOk()->json('timetable'));
        $this->getJson('/api/v1/stations/inconnue/timetable')->assertNotFound();
    }

    public function test_the_station_sheet_carries_the_same_block_and_a_link(): void
    {
        $rest = $this->getJson('/api/v1/stations/limoges-benedictins/timetable')->json('timetable');
        $sheet = $this->getJson('/api/v1/stations/limoges-benedictins')->assertOk()->json();

        $this->assertSame($rest, $sheet['data']['timetable']);
        $this->assertStringEndsWith('/api/v1/stations/limoges-benedictins/timetable', $sheet['data']['links']['timetable']);
        $this->assertNull($this->getJson('/api/v1/stations/sans-train')->json('data.timetable'));
    }

    public function test_the_line_sheet_carries_the_trains_per_day(): void
    {
        $t = $this->getJson('/api/v1/lines/paris-bordeaux')->assertOk()->json('data.timetable');

        $this->assertSame(41, $t['trains']);
        $this->assertSame(['tgv' => 11, 'ter' => 30], $t['by_type']);
        $this->assertSame('2026-12-08', $t['service_date']);
    }

    public function test_the_web_page_loads_the_timetable_on_demand(): void
    {
        // Aucune lecture de plus pour la fiche : la section lit l'API quand la page est affichée.
        $this->get('/stations/limoges-benedictins')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Stations/Show')->missing('timetable'));
        $this->getJson('/api/v1/stations/limoges-benedictins/timetable')->assertOk()
            ->assertJsonPath('timetable.trains', 144)->assertJsonPath('timetable.first', '05:03');
        $this->getJson('/api/v1/stations/sans-train/timetable')->assertOk()->assertJsonPath('timetable', null);
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $rest = $this->getJson('/api/v1/stations/limoges-benedictins/timetable')->json('timetable');
        $gql = $this->postJson('/graphql', ['query' => '{ stations(first: 10) { data { slug timetable {
            service_date weekday trains by_type first last directions { heading trains first last destinations { name slug trains } origins { name } }
            destinations { name slug trains } feeds { feed version date valid_to licence } attribution method provenance { provider } } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.stations.data');
        $bySlug = array_column($gql, 'timetable', 'slug');

        $this->assertSame($rest['trains'], $bySlug['limoges-benedictins']['trains']);
        $this->assertSame($rest['by_type'], $bySlug['limoges-benedictins']['by_type']);
        $this->assertSame($rest['destinations'], $bySlug['limoges-benedictins']['destinations']);
        $this->assertSame('ne', $bySlug['limoges-benedictins']['directions'][0]['heading']);
        $this->assertNull($bySlug['sans-train']);

        $call = fn (string $tool, array $args) => $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $args]])
            ->assertOk()->json('result');
        $mcp = json_decode($call('station_timetable', ['station' => 'limoges-benedictins'])['content'][0]['text'], true);
        $this->assertSame($rest['trains'], $mcp['timetable']['trains']);
        $this->assertStringContainsString('ODbL', $mcp['timetable']['mention']);
        $this->assertNull(json_decode($call('station_timetable', ['station' => 'sans-train'])['content'][0]['text'], true)['timetable']);
        $this->assertTrue((bool) ($call('station_timetable', ['station' => 'inconnue'])['isError'] ?? false));

        $line = json_decode($call('line_timetable', ['line' => 'paris-bordeaux'])['content'][0]['text'], true);
        $this->assertSame(41, $line['timetable']['trains']);
    }

    public function test_graphql_serves_the_line_count(): void
    {
        $t = $this->postJson('/graphql', ['query' => '{ line(slug: "paris-bordeaux") { slug sheet { timetable { trains by_type service_date } } } }'])
            ->json('data.line.sheet.timetable');

        $this->assertSame(41, $t['trains']);
    }

    public function test_a_missing_table_gives_nothing_and_no_error_then_is_checked_again(): void
    {
        $reset = fn () => (new ReflectionProperty(Timetables::class, 'ready'))->setValue(null, null);
        $reset();
        Cache::flush();

        DB::beginTransaction();
        Schema::drop('station_timetables');
        Schema::drop('line_timetables');
        $this->assertFalse(Timetables::ready());
        $this->assertNull(app(Timetables::class)->forStation((int) $this->station->id));
        $this->assertNull(app(Timetables::class)->forLineSlug('paris-bordeaux'));
        $this->get('/stations/limoges-benedictins')->assertOk();
        $this->getJson('/api/v1/stations/limoges-benedictins')->assertOk()->assertJsonPath('data.timetable', null);
        DB::rollBack();

        $this->assertTrue(Timetables::ready(), 'un constat négatif ne reste pas en mémoire du processus');
    }
}
