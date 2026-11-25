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
 *   « Voie V2 » and « 4 »: the edges on « 3 » and « 4 » border V1BIS and V2BIS, which lie beside V1 and V2;
 * - Lyon-Part-Dieu (line 893000): the RINF names « A », « D » and « F » the tracks that SNCF Réseau's « Liste des
 *   quais » (2020-05-26, ODbL) calls V2BIS, V1BIS and V2 on the platforms of the same names;
 * - Bordeaux-St-Jean: the RINF puts tracks A, B and C of « Quai 1 » and « Quai B/C » under the point's 570000
 *   section, SNCF Réseau (A) and OpenStreetMap draw them on 655000.
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

    /** @param  list<array{0: int, 1: string, 2: string, 3: string, 4: int, 5: int, 6: int}>  $rows  id, line, track code, label, length, PK start, PK end */
    private static function sncfPlatforms(Station $station, string $name, array $rows): void
    {
        foreach ($rows as [$id, $code, $track, $label, $length, $from, $to]) {
            DB::table('station_platforms')->insert(['id' => $id, 'station_id' => $station->id, 'sncf_station_name' => $name, 'code_ligne' => $code,
                'code_voie' => $track, 'label' => $label, 'length_m' => $length, 'pk_start_m' => $from, 'pk_end_m' => $to, 'source' => 'sncf',
                'dataset_modified' => '2020-05-26', 'fetched_at' => '2026-11-22 15:47:11']);
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

    public function test_the_tracks_a_d_and_f_of_lyon_part_dieu_are_v2bis_v1bis_and_v2_after_sncf_reseau(): void
    {
        $line = Line::factory()->create(['slug' => 'rfn-893000', 'code' => '893000', 'name' => 'Ligne de Collonges-Fontaines à Lyon-Guillotière']);
        $station = Station::factory()->create(['slug' => 'lyon-part-dieu', 'name' => 'Lyon-Part-Dieu', 'uic_code' => '87723197']);
        self::points([['FR0000012153', 'Accès à Brotteaux remisages pair', 80, 'junction'], ['FR0000003887', 'Lyon-Part-Dieu', 10, 'station'],
            ['FR0000001007', 'Bif 830366/893000', 80, 'junction']], $station->id, 'FR0000003887');
        self::sections($line, [['893000-1_FR0000012153_FR0000003887', 'FR0000012153', 'FR0000003887', 507169, 507525],
            ['893000-1_FR0000003887_FR0000001007', 'FR0000003887', 'FR0000001007', 507525, 510142]]);
        self::rinfTracks([
            ['5f2339639d', '893000-1_FR0000012153_FR0000003887', null, 'Voie V1 de Collonges-au-Mont-d\'Or à', 'B', 90],
            ['320d3e2401', '893000-1_FR0000012153_FR0000003887', null, 'Voie V1BIS de Caluire-et-Cuire à Lyon-08', 'B', 90],
            ['c56c78634d', '893000-1_FR0000012153_FR0000003887', null, 'Voie V2 de Collonges-au-Mont-d\'Or à Lyon-07', 'B', 90],
            ['5233501a86', '893000-1_FR0000012153_FR0000003887', null, 'Voie V2BIS de Caluire-et-Cuire à Lyon-08', 'B', 90],
            ['f0432b1ec7', '893000-1_FR0000003887_FR0000001007', null, 'Voie V1 de Collonges-au-Mont-d\'Or à', 'B', 90],
            ['11a7766aeb', '893000-1_FR0000003887_FR0000001007', null, 'Voie V1BIS de Caluire-et-Cuire à Lyon-08', 'B', 90],
            ['bdd69eccfa', '893000-1_FR0000003887_FR0000001007', null, 'Voie V2 de Collonges-au-Mont-d\'Or à Lyon-07', 'B', 90],
            ['e350c0e7eb', '893000-1_FR0000003887_FR0000001007', null, 'Voie V2BIS de Caluire-et-Cuire à Lyon-08', 'B', 90],
            ['507fbe6191', null, 'FR0000003887', 'A', null, null], ['c4ed9dd537', null, 'FR0000003887', 'Voie B de Lyon-Part-Dieu', null, null],
            ['e7603240e5', null, 'FR0000003887', 'Voie C de Lyon-Part-Dieu', null, null], ['3b93843cd7', null, 'FR0000003887', 'D', null, null],
            ['2ac2ca7218', null, 'FR0000003887', 'Voie E de Lyon-Part-Dieu', null, null], ['e9025c5db6', null, 'FR0000003887', 'F', null, null],
            ['3418973734', null, 'FR0000003887', 'Voie G de Lyon-Part-Dieu', null, null],
        ]);
        self::platforms([
            ['6e1af0e1bc', '507fbe6191', 'FR0000003887', 'Quai A/B', 449], ['a575306991', 'c4ed9dd537', 'FR0000003887', 'Quai A/B', 449],
            ['7d9985887c', 'c4ed9dd537', 'FR0000003887', 'Quai B/C', 433], ['6a8b4ed4b5', 'e7603240e5', 'FR0000003887', 'Quai B/C', 433],
            ['13e61e9211', '3b93843cd7', 'FR0000003887', 'Quai C/D', 449], ['2eb2503c11', 'e7603240e5', 'FR0000003887', 'Quai C/D', 449],
            ['82e7e5b414', '3b93843cd7', 'FR0000003887', 'Quai D/E', 433], ['7ab2977868', '2ac2ca7218', 'FR0000003887', 'Quai D/E', 433],
            ['ad30c224a6', 'e9025c5db6', 'FR0000003887', 'Quai E/F', 449], ['4f0ba7e8ae', '2ac2ca7218', 'FR0000003887', 'Quai E/F', 449],
            ['7f28e70ba8', 'e9025c5db6', 'FR0000003887', 'Quai F/G', 433], ['c581335005', '3418973734', 'FR0000003887', 'Quai F/G', 433],
        ]);
        self::sncfPlatforms($station, 'Lyon Part Dieu', [
            [27, '893000', '893000-1-C', 'Quai C/D', 506, 507378, 507884], [28, '893000', '893000-1-V1BIS', 'Quai D/E', 433, 507393, 507826],
            [30, '893000', '893000-1-B', 'Quai B/C', 433, 507393, 507826], [31, '893000', '893000-1-E', 'Quai E/F', 509, 507317, 507826],
            [32, '893000', '893000-1-V2', 'Quai F/G', 433, 507393, 507826], [33, '893000', '893000-1-V1BIS', 'Quai C/D', 506, 507378, 507884],
            [34, '893000', '893000-1-V2BIS', 'Quai A/B', 449, 507378, 507827], [35, '893000', '893000-1-B', 'Quai A/B', 449, 507378, 507827],
            [36, '893000', '893000-1-C', 'Quai B/C', 433, 507393, 507826], [38, '893000', '893000-1-V2', 'Quai E/F', 509, 507317, 507826],
            [39, '893000', '893000-1-E', 'Quai D/E', 433, 507393, 507826],
        ]);
        // OpenStreetMap tracks B, C, E and G at the platforms: they replace the RINF station tracks (centred on the point).
        DB::table('osm_track_imports')->insert(['file' => 'france-latest.osm.pbf', 'data_at' => '2026-11-23 00:00:00', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[870562585, 'B', 507480, 507524, 1.2], [870562606, 'B', 507524, 507554, 1.0], [870562586, 'C', 507480, 507524, 7.8],
            [870562605, 'C', 507524, 507554, 7.6], [870562588, 'E', 507477, 507525, 25.5], [870562603, 'E', 507525, 507554, 25.1],
            [870562590, 'G', 507476, 507525, 42.8], [870562601, 'G', 507525, 507553, 42.6]] as [$way, $ref, $from, $to, $offset]) {
            DB::table('osm_tracks')->insert(['line_id' => $line->id, 'osm_way_id' => $way, 'part' => 0, 'kind' => 'running', 'ref' => $ref,
                'pk_from_m' => $from, 'pk_to_m' => $to, 'pk_up' => true, 'pk_precision' => 'derived', 'length_m' => $to - $from, 'offset_m' => $offset,
                'attach' => 'line', 'tags' => '{}', 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_GeomFromText('LINESTRING(4.859 45.759, 4.860 45.761)', 4326)")]);
        }

        $json = $this->getJson('/api/v1/stations/lyon-part-dieu/track-layout')->assertOk()->json();
        $tracks = collect((array) $json['lines'][0]['track_layout']['tracks'])->keyBy('id');
        $edges = collect((array) $json['platforms'])->keyBy(fn (array $e): string => $e['ref'].'|'.$e['track_ref']);

        // The RINF tracks A, D and F border the RINF line tracks SNCF Réseau names for them, with its kilometre points.
        foreach (['Quai A/B|A' => ['V2BIS', 507378, 507827], 'Quai C/D|D' => ['V1BIS', 507378, 507884], 'Quai D/E|D' => ['V1BIS', 507393, 507826],
            'Quai E/F|F' => ['V2', 507317, 507826], 'Quai F/G|F' => ['V2', 507393, 507826]] as $edge => [$ref, $from, $to]) {
            $this->assertNotNull($edges[$edge]['track'] ?? null, $edge);
            $this->assertSame([$ref, $from, $to, 'exact'], [$tracks[$edges[$edge]['track']]['ref'], $edges[$edge]['pk_from_m'], $edges[$edge]['pk_to_m'],
                $edges[$edge]['precision']], $edge);
        }
        // Each SNCF Réseau edge is one with its RINF edge: no second « Quai A/B » on V2BIS.
        $this->assertSame([], collect((array) $json['platforms'])->where('source', 'sncf')->pluck('id')->all());
        // Track B borders « Quai A/B » (449 m) and « Quai B/C » (433 m): each takes the kilometre points of its own platform.
        $this->assertSame([507378, 507827], [$edges['Quai A/B|B']['pk_from_m'], $edges['Quai A/B|B']['pk_to_m']]);
        $this->assertSame([507393, 507826], [$edges['Quai B/C|B']['pk_from_m'], $edges['Quai B/C|B']['pk_to_m']]);
    }

    public function test_the_track_a_of_bordeaux_st_jean_borders_its_platform_on_the_line_that_draws_it(): void
    {
        $paris = Line::factory()->create(['slug' => 'rfn-570000', 'code' => '570000', 'name' => 'Ligne de Paris-Austerlitz à Bordeaux-St-Jean']);
        $hendaye = Line::factory()->create(['slug' => 'rfn-655000', 'code' => '655000', 'name' => 'Ligne de Bordeaux-St-Jean à Irun']);
        $station = Station::factory()->create(['slug' => 'bordeaux-st-jean', 'name' => 'Bordeaux-St-Jean', 'uic_code' => '87581009']);
        self::points([['FR0000009198', 'Faisceau R2', 50, 'other'], ['FR0000003307', 'Bordeaux-St-Jean', 10, 'station'],
            ['FR0000001385', 'Jct 640000/655000', 80, 'junction']], $station->id, 'FR0000003307');
        self::sections($paris, [['570000-3_FR0000009198_FR0000003307', 'FR0000009198', 'FR0000003307', 583762, 583844]]);
        self::sections($hendaye, [['655000-1_FR0000003307_FR0000001385', 'FR0000003307', 'FR0000001385', 0, 381]]);
        self::rinfTracks([
            ['33e05aa065', '570000-3_FR0000009198_FR0000003307', null, 'Voie V1', 'B', 120],
            ['d03e16cea0', '570000-3_FR0000009198_FR0000003307', null, 'Voie V2', 'B', 160],
            ['edbfe9848f', '655000-1_FR0000003307_FR0000001385', null, 'Voie V1 de Bordeaux à Hendaye', 'B', 160],
            ['a2081a0bad', '655000-1_FR0000003307_FR0000001385', null, 'Voie V2 de Bordeaux à Hendaye', 'B', 160],
            ['fa39bcc415', null, 'FR0000003307', 'Voie A de Bordeaux-St-Jean', null, null],
            ['917f6f91c1', null, 'FR0000003307', 'Voie B de Bordeaux-St-Jean', null, null],
            ['67b5fe63b1', null, 'FR0000003307', 'Voie C de Bordeaux-St-Jean', null, null],
        ]);
        self::platforms([['2318b07810', 'fa39bcc415', 'FR0000003307', 'Quai 1', 174], ['ba3f8bfbb2', '917f6f91c1', 'FR0000003307', 'Quai B/C', 160],
            ['95becc2ff8', '67b5fe63b1', 'FR0000003307', 'Quai B/C', 160]]);
        self::sncfPlatforms($station, 'Bordeaux Saint-Jean', [[850, '655000', '655000-1-A', 'Quai 1', 183, 175, 358]]);
        DB::table('osm_track_imports')->insert(['file' => 'france-latest.osm.pbf', 'data_at' => '2026-11-23 00:00:00', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([
            // 570000: the yard track beside the point replaces its approximate RINF tracks; 655000: track A of OpenStreetMap.
            [$paris, 991560193, null, 'yard', 583569, 583768, 'derived', 24.3],
            [$hendaye, 78451006, 'A', 'running', 166, 497, 'derived', -7.5],
            [$hendaye, 79397111, 'B', 'running', 164, 430, 'derived', -12.0],
            [$hendaye, 78451004, 'C', 'running', 164, 400, 'derived', -20.0],
        ] as [$line, $way, $ref, $kind, $from, $to, $precision, $offset]) {
            DB::table('osm_tracks')->insert(['line_id' => $line->id, 'osm_way_id' => $way, 'part' => 0, 'kind' => $kind, 'ref' => $ref,
                'pk_from_m' => $from, 'pk_to_m' => $to, 'pk_up' => true, 'pk_precision' => $precision, 'length_m' => $to - $from, 'offset_m' => $offset,
                'attach' => 'line', 'tags' => '{}', 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_GeomFromText('LINESTRING(-0.556 44.824, -0.557 44.826)', 4326)")]);
        }

        $json = $this->getJson('/api/v1/stations/bordeaux-st-jean/track-layout')->assertOk()->json();
        $edge = collect((array) $json['platforms'])->firstWhere('id', 'rinf:pe:'.substr(md5('http://data.europa.eu/949/platformEdge/2318b07810'), 0, 12));

        $this->assertSame(['655000', 'osm:way:78451006', 175, 358, 'exact'],
            [$edge['line_code'], $edge['track'], $edge['pk_from_m'], $edge['pk_to_m'], $edge['precision']]);
        // « Quai B/C » is unknown to SNCF Réseau: the edges go to the line where OpenStreetMap carries their RINF tracks.
        $edges = collect((array) $json['platforms'])->where('ref', 'Quai B/C')->keyBy('track_ref');
        $this->assertSame([['655000', 'osm:way:79397111', 'approximate'], ['655000', 'osm:way:78451004', 'approximate']],
            [[$edges['B']['line_code'], $edges['B']['track'], $edges['B']['precision']], [$edges['C']['line_code'], $edges['C']['track'], $edges['C']['precision']]]);
        $this->assertSame([], collect((array) $json['platforms'])->where('source', 'sncf')->pluck('id')->all());
    }
}
