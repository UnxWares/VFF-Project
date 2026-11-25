<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Platform tracks in the track layout, on real extracts read on 25/11/2026 (RINF © ERA, graph of SNCF Réseau;
 * OpenStreetMap © contributors, ODbL):
 * - Paris-Nord (line 272000): the OpenStreetMap tracks 3 and 4 along « Quai 3/4 » lie in a stretch the RINF covers;
 *   they were dropped, so their platform parts vanished and the signals C103 and C104 cited an absent track. They
 *   now replace the RINF station tracks 3 and 4 (identifier, point, platform) and sit side by side; the track « DO »
 *   of OpenStreetMap is the RINF track « VDO de Paris-10 à St-Denis » and is served once;
 * - Brétigny (line 570000): the RINF calls the tracks of « Quai V1/V1BIS » and « Quai V2/V2BIS » « Voie V1 » and « 3 »,
 *   « Voie V2 » and « 4 »: the edges on « 3 » and « 4 » border V1BIS and V2BIS, which lie beside V1 and V2.
 */
class TrackLayoutPlatformTracksTest extends TestCase
{
    use RefreshDatabase;

    private const META = ['dataset' => 'http://data.europa.eu/949/graph/0087 (https://graph.data.era.europa.eu/repositories/rinf-plus)', 'fetched_at' => '2026-11-24 23:04:53'];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
    }

    /** @param  list<array{0: string, 1: string, 2: ?int, 3: string}>  $points  uopid, name, type, kind */
    private static function points(array $points, ?int $stationId = null, ?string $stationUopid = null): void
    {
        foreach ($points as [$uopid, $name, $type, $kind]) {
            DB::table('rinf_operational_points')->insert(['uopid' => $uopid, 'name' => $name, 'op_type' => $type, 'kind' => $kind,
                'station_id' => $uopid === $stationUopid ? $stationId : null, 'station_match' => $uopid === $stationUopid ? 'name_distance' : null,
                'uri' => 'http://data.europa.eu/949/operationalPoint/'.$uopid] + self::META);
        }
    }

    /** @param  list<array{0: string, 1: string, 2: string, 3: int, 4: int}>  $sections  uri suffix, start, end, PK start, PK end */
    private static function sections(Line $line, array $sections): void
    {
        foreach ($sections as [$uri, $start, $end, $from, $to]) {
            DB::table('rinf_sections')->insert(['uri' => 'http://data.europa.eu/949/sectionOfLine/'.$uri, 'national_line' => $line->code.'-1',
                'line_code' => $line->code, 'line_id' => $line->id, 'op_start_uopid' => $start, 'op_end_uopid' => $end, 'pk_start_m' => $from,
                'pk_end_m' => $to, 'length_m' => abs($to - $from), 'nature' => 'regular'] + self::META);
        }
    }

    /** @param  list<array{0: string, 1: ?string, 2: ?string, 3: string, 4: ?string, 5: ?int}>  $tracks  uri, section, point, ref, direction, speed */
    private static function rinfTracks(array $tracks): void
    {
        foreach ($tracks as [$uri, $section, $op, $ref, $direction, $speed]) {
            DB::table('rinf_tracks')->insert(['uri' => 'http://data.europa.eu/949/track/'.$uri, 'parent' => $section === null ? 'op' : 'section',
                'section_uri' => $section === null ? null : 'http://data.europa.eu/949/sectionOfLine/'.$section, 'op_uopid' => $op,
                'rinf_class' => 'running', 'ref' => $ref, 'direction' => $direction, 'max_speed_kmh' => $speed, 'length_m' => null] + self::META);
        }
    }

    /** @param  list<array{0: string, 1: string, 2: string, 3: string, 4: int}>  $edges  uri, track uri, point, ref, length */
    private static function platforms(array $edges): void
    {
        foreach ($edges as [$uri, $track, $op, $ref, $length]) {
            DB::table('rinf_platforms')->insert(['uri' => 'http://data.europa.eu/949/platformEdge/'.$uri, 'track_uri' => 'http://data.europa.eu/949/track/'.$track,
                'op_uopid' => $op, 'ref' => $ref, 'length_m' => $length, 'height_mm' => 580] + self::META);
        }
    }

    private static function rinfId(string $uri): string
    {
        return 'rinf:t:'.substr(md5('http://data.europa.eu/949/track/'.$uri), 0, 12);
    }

    /** Paris-Nord, line 272000: the RINF of the point and of two sections, and the OpenStreetMap tracks 3, 4 and DO. */
    private function seedParisNord(): Line
    {
        $line = Line::factory()->create(['slug' => 'rfn-272000', 'code' => '272000', 'name' => 'Ligne de Paris-Nord à Lille']);
        self::points([['FR0000002153', 'Paris-Nord', 10, 'station'], ['FR0000007003', 'Chantier Inter', 50, 'other'],
            ['FR0000003083', 'Paris-Nord-Souterraine', 10, 'station'], ['FRZZY0000104', 'Bif 3395av', 80, 'junction']]);
        self::sections($line, [['272000-1_FR0000002153_FR0000007003', 'FR0000002153', 'FR0000007003', 0, 1115],
            ['272000-1_FR0000003083_FRZZY0000104', 'FR0000003083', 'FRZZY0000104', 0, 1656]]);
        self::rinfTracks([
            ['c3fcadbfd8', '272000-1_FR0000002153_FR0000007003', null, 'Voie 1 de Paris-Nord', 'B', 60],
            ['55561ea37c', '272000-1_FR0000002153_FR0000007003', null, 'Voie V2 de Paris-18 à Lille', 'O', 120],
            ['211818418a', '272000-1_FR0000003083_FRZZY0000104', null, 'Voie VDO de Paris-10 à St-Denis', 'B', 90],
            ['ccaecc72a9', null, 'FR0000002153', 'Voie 3 de Paris-Nord', null, null],
            ['9364257179', null, 'FR0000002153', 'Voie 4 de Paris-Nord', null, null],
        ]);
        self::platforms([['41f105a67e', 'ccaecc72a9', 'FR0000002153', 'Quai 3/4', 403], ['414c950e95', '9364257179', 'FR0000002153', 'Quai 3/4', 406]]);

        DB::table('osm_track_imports')->insert(['file' => 'france-latest.osm.pbf', 'data_at' => '2026-11-23 00:00:00', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([
            [73556237, '3', 'running', -35, -31, 123.6], [112596568, '3', 'running', -31, 377, 115.9], [402673032, '3', 'running', 377, 426, 57.9],
            [256877275, '4', 'running', -35, -31, 113.4], [256877274, '4', 'running', -31, 378, 101.1],
            [539777604, 'DO', 'running', 440, 640, 2.5], [1522781045, 'DO', 'running', 640, 713, -24.3], [539777620, 'DO', 'running', 713, 1012, -22.8],
            [1039681086, null, 'loop', 69, 104, -318.2],
        ] as [$way, $ref, $kind, $from, $to, $offset]) {
            DB::table('osm_tracks')->insert(['line_id' => $line->id, 'osm_way_id' => $way, 'part' => 0, 'kind' => $kind, 'ref' => $ref,
                'pk_from_m' => $from, 'pk_to_m' => $to, 'pk_up' => true, 'pk_precision' => 'derived', 'length_m' => $to - $from, 'offset_m' => $offset,
                'attach' => 'line', 'tags' => '{}', 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_GeomFromText('LINESTRING(2.355 48.881, 2.356 48.884)', 4326)")]);
        }
        foreach ([
            [1262313054, 'buffer_stop', null, null, null, -35, 73556237, null, null],
            [2625006893, 'buffer_stop', null, null, null, -35, 256877275, null, null],
            [1262312324, 'signal', 'main', 'FR:CARRE', 'C103', 377, 112596568, null, 'up'],
            [2625006882, 'signal', 'main', 'FR:CARRE', 'C104', 378, 256877274, null, 'up'],
            [5222677031, 'switch', 'simple', null, '3028b', 730, 1522781045, 539777604, 'down'],
        ] as [$node, $kind, $subtype, $detail, $ref, $pk, $way, $toWay, $direction]) {
            DB::table('osm_track_nodes')->insert(['line_id' => $line->id, 'osm_node_id' => $node, 'kind' => $kind, 'subtype' => $subtype, 'detail' => $detail,
                'ref' => $ref, 'pk_m' => $pk, 'pk_precision' => 'derived', 'way_id' => $way, 'to_way_id' => $toWay, 'direction' => $direction,
                'tags' => '{}', 'created_at' => now(), 'updated_at' => now(), 'geom' => DB::raw("ST_GeomFromText('POINT(2.355 48.881)', 4326)")]);
        }

        return $line;
    }

    public function test_the_platform_tracks_of_paris_nord_are_kept_side_by_side_and_nothing_cites_an_absent_track(): void
    {
        $line = $this->seedParisNord();
        $json = $this->getJson("/api/v1/lines/{$line->slug}/track-layout")->assertOk()->json();
        $tracks = collect((array) $json['tracks'])->keyBy('id');

        // Tracks 3 and 4 of OpenStreetMap along their platform, with the identifier, point and platform of the RINF.
        foreach (['osm:way:112596568' => 'ccaecc72a9', 'osm:way:256877274' => '9364257179'] as $id => $uri) {
            $this->assertArrayHasKey($id, $tracks->all());
            $this->assertSame([self::rinfId($uri), 'rinf:op:FR0000002153', true], [$tracks[$id]['rinf_id'], $tracks[$id]['op_id'], $tracks[$id]['platform']]);
        }
        $this->assertSame(1, abs($tracks['osm:way:112596568']['lane'] - $tracks['osm:way:256877274']['lane']));
        $this->assertSame($tracks['osm:way:112596568']['lane'], $tracks['osm:way:73556237']['lane']);

        // « DO » of OpenStreetMap is the RINF track VDO: served once, and the switch 3028b leads from it to itself.
        $this->assertSame([], $tracks->filter(fn (array $t): bool => $t['ref'] === 'DO')->keys()->all());
        $switch = collect((array) $json['switches'])->firstWhere('ref', '3028b');
        $this->assertSame([self::rinfId('211818418a'), null], [$switch['from_track'], $switch['to_track']]);

        // Every signal, buffer stop and switch cites a served track.
        $cited = [...array_column((array) $json['signals'], 'track'), ...array_column((array) $json['buffer_stops'], 'track'),
            ...array_column((array) $json['switches'], 'from_track'), ...array_column((array) $json['switches'], 'to_track')];
        $this->assertSame([], array_values(array_diff(array_filter($cited), $tracks->keys()->all())));
        $this->assertSame(['C103', 'C104'], collect((array) $json['signals'])->pluck('ref')->sort()->values()->all());
        $this->assertArrayNotHasKey('platform_keys', $tracks['osm:way:112596568']);
        $this->assertArrayNotHasKey('aliases', $tracks['osm:way:112596568']);
    }

    public function test_the_tracks_3_and_4_of_bretigny_are_v1bis_and_v2bis_beside_v1_and_v2(): void
    {
        $line = Line::factory()->create(['slug' => 'rfn-570000', 'code' => '570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean']);
        $station = Station::factory()->create(['slug' => 'bretigny', 'name' => 'Brétigny', 'uic_code' => '87545194']);
        DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $line->id, 'method' => 'sncf_pk', 'pk_m' => 31248,
            'pk_source' => 'projected', 'created_at' => now(), 'updated_at' => now()]);
        self::points([['FR0000009123', 'CHANTIER « PAIR »', 50, 'other'], ['FR0000002724', 'Brétigny', 10, 'station'],
            ['FR0000001687', 'Bif 550000/570000', 80, 'junction']], $station->id, 'FR0000002724');
        self::sections($line, [['570000-1_FR0000009123_FR0000002724', 'FR0000009123', 'FR0000002724', 31183, 31336],
            ['570000-1_FR0000002724_FR0000001687', 'FR0000002724', 'FR0000001687', 31336, 31508]]);
        self::rinfTracks([
            ['f43b8d9328', '570000-1_FR0000009123_FR0000002724', null, 'Voie V1', 'N', 100],
            ['5a26745c46', '570000-1_FR0000009123_FR0000002724', null, 'Voie V2', 'O', 100],
            ['464f250d37', '570000-1_FR0000009123_FR0000002724', null, 'Voie V1BIS', 'N', 100],
            ['5893960a6d', '570000-1_FR0000009123_FR0000002724', null, 'Voie V2BIS', 'O', 100],
            ['deaf7ebb68', '570000-1_FR0000002724_FR0000001687', null, 'Voie V1', 'N', 120],
            ['23a8c25bcf', '570000-1_FR0000002724_FR0000001687', null, 'Voie V2', 'O', 100],
            ['2d501e4d5e', '570000-1_FR0000002724_FR0000001687', null, 'Voie V1BIS', 'N', 100],
            ['28d1aefe27', '570000-1_FR0000002724_FR0000001687', null, 'Voie V2BIS', 'O', 100],
            ['ac0a010e29', null, 'FR0000002724', 'Voie V1', null, null], ['bc31541c57', null, 'FR0000002724', '3', null, null],
            ['0d88d18b29', null, 'FR0000002724', 'Voie V2', null, null], ['2600ffa5c4', null, 'FR0000002724', '4', null, null],
        ]);
        self::platforms([['d14ee683aa', 'ac0a010e29', 'FR0000002724', 'Quai V1/V1BIS', 235], ['9ec3ef1828', 'bc31541c57', 'FR0000002724', 'Quai V1/V1BIS', 235],
            ['f3ebeea0db', '0d88d18b29', 'FR0000002724', 'Quai V2/V2BIS', 235], ['3721a8582a', '2600ffa5c4', 'FR0000002724', 'Quai V2/V2BIS', 228]]);

        $json = $this->getJson('/api/v1/stations/bretigny/track-layout')->assertOk()->json();
        $tracks = collect((array) $json['lines'][0]['track_layout']['tracks'])->keyBy('id');
        $edges = collect((array) $json['platforms'])->keyBy(fn (array $e): string => $e['ref'].'|'.$e['track_ref']);

        foreach (['Quai V1/V1BIS|3' => 'V1BIS', 'Quai V2/V2BIS|4' => 'V2BIS', 'Quai V1/V1BIS|V1' => 'V1', 'Quai V2/V2BIS|V2' => 'V2'] as $edge => $ref) {
            $this->assertNotNull($edges[$edge]['track'], $edge);
            $this->assertSame($ref, $tracks[$edges[$edge]['track']]['ref'], $edge);
        }
        // Each platform between its two tracks: V1BIS just beyond V1, V2BIS just beyond V2.
        $lane = fn (string $ref): array => $tracks->where('ref', $ref)->pluck('lane')->unique()->values()->all();
        $this->assertSame([[-1], [0], [1], [2]], [$lane('V1BIS'), $lane('V1'), $lane('V2'), $lane('V2BIS')]);
        $this->assertSame(['V1', 'V1BIS', 'V2', 'V2BIS'], $tracks->pluck('ref')->unique()->sort()->values()->all());
    }
}
