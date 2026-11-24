<?php

namespace Tests\Feature\Commands;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommandSandbox;
use Tests\TestCase;

/**
 * vff:import-osm-track-detail on a real extract: the tracks of Luzenac-Garanou on the single track line
 * Portet-Saint-Simon - Puigcerda (672000), filtered by bin/osm-track-detail from the Geofabrik France extract of
 * 23/11/2026 (tests/Fixtures/Osm/track-detail-luzenac.opl, © OpenStreetMap contributors, ODbL), on the reference
 * trace of the line between Vebre and Le Castelet (VFF, era 2030, simplified to 2 m) and the SNCF Reseau PKs of
 * the three stations: the running track by its line code, the two passing loops, the station yard, the switches
 * with the tracks they join and the direction they face, the level crossings, and the track layout API.
 */
class ImportOsmTrackDetailTest extends TestCase
{
    use CommandSandbox;
    use RefreshDatabase;

    /** Reference trace of 672000 between Vebre and Le Castelet, [lat, lng]. */
    private const TRACE = [
        [42.773947, 1.715], [42.77375, 1.718309], [42.773756, 1.719226], [42.773817, 1.719823], [42.77399, 1.720748],
        [42.774188, 1.721507], [42.774632, 1.722711], [42.775134, 1.723946], [42.775546, 1.725255], [42.775742, 1.72609],
        [42.775904, 1.727279], [42.775952, 1.728524], [42.775901, 1.729633], [42.775734, 1.730786], [42.775429, 1.732116],
        [42.775039, 1.733323], [42.774776, 1.734005], [42.774566, 1.734436], [42.774311, 1.734816], [42.774036, 1.73514],
        [42.773432, 1.735739], [42.772915, 1.736326], [42.772567, 1.736953], [42.772322, 1.737603], [42.771701, 1.74005],
        [42.771373, 1.740858], [42.770762, 1.741861], [42.766731, 1.747966], [42.765973, 1.749349], [42.765533, 1.750348],
        [42.764487, 1.752905], [42.761548, 1.760588], [42.761418, 1.761096], [42.761352, 1.761532], [42.761319, 1.762148],
        [42.761316, 1.763541], [42.761277, 1.764026], [42.761103, 1.764835], [42.760919, 1.765333], [42.760612, 1.765888],
        [42.75935, 1.767851], [42.758646, 1.768854], [42.757975, 1.769543], [42.75198, 1.775299], [42.751541, 1.775624],
        [42.75044, 1.776144], [42.750112, 1.77637], [42.749671, 1.776805], [42.748829, 1.777854], [42.748631, 1.778058],
        [42.748283, 1.778373], [42.747825, 1.778698], [42.747435, 1.778924], [42.746993, 1.779083], [42.74653, 1.779213],
        [42.745456, 1.779426], [42.744907, 1.779612], [42.744721, 1.779705], [42.744439, 1.779908], [42.74423, 1.780092],
        [42.743975, 1.780391], [42.740609, 1.785318], [42.740328, 1.785781], [42.740158, 1.786134], [42.739975, 1.786649],
        [42.73984, 1.787291], [42.739774, 1.788277], [42.739729, 1.78979], [42.739671, 1.7905], [42.739618, 1.79083],
        [42.739451, 1.791392], [42.739359, 1.791646], [42.739131, 1.792096], [42.737976, 1.793924], [42.737738, 1.794385],
        [42.73747, 1.795011], [42.735536, 1.8],
    ];

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $era = Era::factory()->current()->create(['year' => 2030]);
        $this->line = $this->rfnLine('672000', 'Ligne de Portet-St-Simon à Puigcerda (frontière)', ['slug' => 'rfn-672000']);
        $this->track($this->line, $era, self::TRACE);
        foreach ([
            ['vebre', 'Vèbre', 42.7738012, 1.7193285, 111750],
            ['luzenac-garanou', 'Luzenac-Garanou', 42.7643032, 1.7532404, 114998],
            ['le-castelet', 'Le Castelet', 42.736063, 1.798341, 120150],
        ] as [$slug, $name, $lat, $lng, $pk]) {
            $station = $this->station($slug, $name, $lat, $lng);
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $this->line->id, 'method' => 'sncf', 'pk_m' => $pk, 'pk_source' => 'sncf']);
        }
    }

    private function import(string ...$options): void
    {
        $code = $this->artisan('vff:import-osm-track-detail', ['--file' => $this->fixturePath('Osm/track-detail-luzenac.opl')] + array_fill_keys($options, true))->run();
        $this->assertSame(0, $code);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->import('--dry-run');
        $this->assertSame(0, DB::table('osm_tracks')->count());
        $this->assertSame(0, DB::table('osm_track_nodes')->count());
        $this->assertSame(0, DB::table('osm_track_imports')->count());
    }

    public function test_tracks_of_luzenac_are_attached_with_their_kind_and_pk(): void
    {
        $this->import();
        $tracks = DB::table('osm_tracks')->where('line_id', $this->line->id)->get()->keyBy('osm_way_id');

        // Running track: ways tagged ref=672000, attached by their code, whole.
        $running = $tracks->where('kind', 'running');
        $this->assertNotEmpty($running);
        $this->assertSame(['code'], $running->pluck('attach')->unique()->values()->all());
        // Both passing loops of the station (service=siding, connected at both ends) are loops.
        $this->assertSame('loop', $tracks[417852648]->kind);
        $this->assertSame('loop', $tracks[417852658]->kind);
        // Station yard (service=yard, no line code) attached by the station rule.
        $this->assertSame(['yard', 'station'], [$tracks[417854272]->kind, $tracks[417854272]->attach]);
        $this->assertSame((int) Station::where('slug', 'luzenac-garanou')->value('id'), (int) $tracks[417854272]->station_id);
        // PKs: around the PK of the station (114,998), between the neighbouring stations, never guessed numbers.
        foreach ([417852648, 417852658, 417854272] as $way) {
            $this->assertGreaterThan(114_000, $tracks[$way]->pk_from_m);
            $this->assertLessThan(116_000, $tracks[$way]->pk_to_m);
            $this->assertSame('derived', $tracks[$way]->pk_precision);
            $this->assertNull($tracks[$way]->ref);
        }
        // Date of the data: the newest object of the extract (UTC in the file, local time in the table).
        $this->assertSame('2026-11-07 22:05:01', Carbon::parse((string) DB::table('osm_track_imports')->value('data_at'), (string) config('app.timezone'))->utc()->format('Y-m-d H:i:s'));
    }

    public function test_switches_join_tracks_and_face_a_direction(): void
    {
        $this->import();
        $switches = DB::table('osm_track_nodes')->where('line_id', $this->line->id)->where('kind', 'switch')->get()->keyBy('osm_node_id');

        // East switch of the first loop: on the running track (toe side), leading to the loop.
        $east = $switches[765053750];
        $this->assertSame('simple', $east->subtype);
        $this->assertSame(417852648, (int) $east->to_way_id);
        $this->assertNotSame(417852648, (int) $east->way_id);
        $this->assertContains($east->direction, ['up', 'down']);
        // Its points face the trains coming from the station side: the loop lies towards decreasing PKs.
        $loop = DB::table('osm_tracks')->where('osm_way_id', 417852648)->first();
        $this->assertGreaterThan($loop->pk_from_m, $east->pk_m + 50);
        $this->assertSame('down', $east->direction);

        $crossings = DB::table('osm_track_nodes')->where('line_id', $this->line->id)->whereIn('kind', ['level_crossing', 'crossing'])->pluck('subtype', 'osm_node_id');
        $this->assertSame('road', $crossings[4183289761]);
        $this->assertSame('pedestrian', $crossings[4183280736]);
        $this->assertGreaterThan(0, DB::table('osm_track_nodes')->where('kind', 'buffer_stop')->count());
    }

    public function test_reimport_replaces_the_rows_of_the_line(): void
    {
        $this->import();
        $first = DB::table('osm_tracks')->count();
        $this->import();
        $this->assertSame($first, DB::table('osm_tracks')->count());
        $this->assertSame(2, DB::table('osm_track_imports')->count());
    }

    public function test_track_layout_api_serves_the_osm_tracks_with_lanes(): void
    {
        $this->import();
        $json = $this->getJson('/api/v1/lines/rfn-672000/track-layout')->assertOk()->json();

        $this->assertSame(['osm'], array_column($json['sources'], 'key'));
        $this->assertSame('ODbL 1.0', $json['sources'][0]['licence']);
        $tracks = collect((array) $json['tracks'])->keyBy('id');
        $this->assertSame('loop', $tracks['osm:way:417852648']['kind']);
        $this->assertSame('derived', $tracks['osm:way:417852648']['precision']);
        // Single track (U) on lane 0; in the station, track E beside it (lane 1), the loop beyond track E (lane 2),
        // the other loop and the yard on the other side, outwards.
        $this->assertSame([0], collect((array) $json['tracks'])->where('ref', 'U')->pluck('lane')->unique()->values()->all());
        $this->assertSame(1, $tracks['osm:way:417852650']['lane']);
        $this->assertSame(2, $tracks['osm:way:417852658']['lane']);
        $this->assertSame(-1, $tracks['osm:way:417852648']['lane']);
        $this->assertLessThan(-1, $tracks['osm:way:417854272']['lane']);
        // Internal keys of the lane computation are not served.
        $this->assertArrayNotHasKey('offset_m', $tracks['osm:way:417852648']);
        $this->assertArrayNotHasKey('connects', $tracks['osm:way:417852648']);
        $switch = collect((array) $json['switches'])->firstWhere('id', 'osm:node:765053750');
        $this->assertSame('osm:way:417852648', $switch['to_track']);
        $this->assertNotNull($switch['from_track']);
        $this->assertSame('down', $switch['facing']);
        $this->assertGreaterThan(0, $json['coverage']['osm_m']);
        // Buffer stops of the yard, on their track.
        $buffer = collect((array) $json['buffer_stops'])->firstWhere('id', 'osm:node:4183289744');
        $this->assertSame('osm:way:433769074', $buffer['track']);
        $this->assertSame('osm', $buffer['source']);
        // Every switch points to tracks of the response.
        $ids = array_flip(array_column($json['tracks'], 'id'));
        foreach ($json['switches'] as $s) {
            foreach (['from_track', 'to_track'] as $k) {
                $this->assertTrue($s[$k] === null || isset($ids[$s[$k]]), $s['id'].' '.$k);
            }
        }
    }
}
