<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Models\Station;
use App\Services\TrackLayout\StationTrackLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Voies d'une gare (`GET /api/v1/stations/{station}/track-layout`, GraphQL `Station.track_layout`, MCP
 * `station_track_layout`) : une fenêtre par ligne autour de la gare, lue par TrackLayout::forLine (mêmes règles que le
 * schéma d'une ligne), la gare de bifurcation avec ses deux lignes, la ligne fermée du passé écartée, les bords de quai
 * du RINF placés par la « Liste des quais » de SNCF Réseau et rattachés aux voies dessinées.
 * Extrait réel de Cognac (ligne 579000) : RINF © ERA, graphe de SNCF Réseau, lu le 24/11/2026 ; quai VE de la « Liste
 * des quais » de SNCF Réseau (ODbL). La ligne 580000 et le quai A sont inventés pour la bifurcation.
 */
class StationTrackLayoutApiTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Line $branch;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-579000', 'code' => '579000', 'name' => 'Ligne de Beillant à Angoulême']);
        $this->branch = Line::factory()->create(['slug' => 'rfn-580000', 'code' => '580000', 'name' => 'Ligne de Cognac à l’embranchement']);
        $closed = Line::factory()->create(['slug' => 'ligne-de-saint-jean-dangely-a-cognac', 'code' => null, 'name' => 'Ligne de Saint-Jean-d’Angély à Cognac']);
        $this->station = Station::factory()->create(['slug' => 'cognac', 'name' => 'Cognac', 'uic_code' => '87491449']);
        Station::factory()->create(['slug' => 'sans-voies', 'name' => 'Sans voies']);
        foreach ([[$this->line, 17277, 'sncf'], [$this->branch, 2000, 'projected'], [$closed, 45900, 'wikipedia_schema']] as [$line, $pk, $source]) {
            DB::table('station_lines')->insert(['station_id' => $this->station->id, 'line_id' => $line->id, 'method' => 'sncf_pk',
                'pk_m' => $pk, 'pk_source' => $source, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** RINF de Cognac (comme LineTrackLayoutApiTest), le point rattaché à la gare, et ses deux bords de quai. */
    private function seedRinf(): void
    {
        $meta = ['dataset' => 'http://data.europa.eu/949/graph/0087 (https://graph.data.era.europa.eu/repositories/rinf-plus)', 'fetched_at' => '2026-11-24 20:00:00'];
        foreach ([
            ['FR0000009305', 'Faisceau Pair', null, 'other', null], ['FR0000003513', 'Cognac', 10, 'station', $this->station->id],
            ['FR9900004367', 'Accès à Faisceau Pair', null, 'other', null], ['FR0000006311', 'Faisceau Impair', null, 'other', null],
        ] as [$uopid, $name, $type, $kind, $stationId]) {
            DB::table('rinf_operational_points')->insert(['uopid' => $uopid, 'name' => $name, 'op_type' => $type, 'kind' => $kind,
                'station_id' => $stationId, 'station_match' => $stationId === null ? null : 'name_distance',
                'uri' => 'http://data.europa.eu/949/operationalPoint/'.$uopid] + $meta);
        }
        foreach ([
            ['FR0000009305_FR0000003513', 'FR0000009305', 'FR0000003513', 17095, 17277],
            ['FR0000003513_FR9900004367', 'FR0000003513', 'FR9900004367', 17277, 17917],
            ['FR9900004367_FR0000006311', 'FR9900004367', 'FR0000006311', 17917, 17919],
        ] as [$uri, $start, $end, $from, $to]) {
            DB::table('rinf_sections')->insert(['uri' => 'http://data.europa.eu/949/sectionOfLine/579000-1_'.$uri, 'national_line' => '579000-1',
                'line_code' => '579000', 'line_id' => $this->line->id, 'op_start_uopid' => $start, 'op_end_uopid' => $end, 'pk_start_m' => $from,
                'pk_end_m' => $to, 'length_m' => $to - $from, 'nature' => 'regular'] + $meta);
        }
        $section = fn (string $s): string => 'http://data.europa.eu/949/sectionOfLine/579000-1_'.$s;
        $unique = 'Voie UNIQUE de Beillant à St-Michel-sur-Charente';
        foreach ([
            ['t1', 'section', $section('FR0000009305_FR0000003513'), null, 'running', $unique, 'B', 110, null],
            ['t2', 'section', $section('FR0000003513_FR9900004367'), null, 'running', $unique, 'B', 140, null],
            ['t3', 'section', $section('FR9900004367_FR0000006311'), null, 'running', $unique, 'B', 140, null],
            ['t4', 'op', null, 'FR0000003513', 'running', 'Voie E de Cognac', null, null, null],
            ['t5', 'op', null, 'FR0000003513', 'running', 'D', null, null, null],
            ['s4', 'op', null, 'FR0000003513', 'siding', '4 - COGNAC', null, null, 530],
        ] as [$id, $parent, $sectionUri, $op, $class, $ref, $direction, $speed, $length]) {
            DB::table('rinf_tracks')->insert(['uri' => 'http://data.europa.eu/949/track/'.$id, 'parent' => $parent, 'section_uri' => $sectionUri,
                'op_uopid' => $op, 'rinf_class' => $class, 'ref' => $ref, 'direction' => $direction, 'max_speed_kmh' => $speed, 'length_m' => $length] + $meta);
        }
        foreach ([['ve', 't4', 'Quai VE'], ['vd', 't5', 'Quai VD']] as [$id, $track, $ref]) {
            DB::table('rinf_platforms')->insert(['uri' => 'http://data.europa.eu/949/platformEdge/'.$id, 'track_uri' => 'http://data.europa.eu/949/track/'.$track,
                'op_uopid' => 'FR0000003513', 'ref' => $ref, 'length_m' => 145, 'height_mm' => 300] + $meta);
        }
    }

    /** Voies d'OpenStreetMap rattachées à la gare sur ses deux lignes, et les quais de SNCF Réseau. */
    private function seedOsmAndSncf(): void
    {
        DB::table('osm_track_imports')->insert(['file' => 'france-261124.osm.pbf', 'data_at' => '2026-11-24 00:00:00', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([
            [$this->line, 101, 'E', 'loop', 17100, 17600, 2.5, $this->station->id],
            [$this->line, 102, null, 'yard', 17700, 18000, 9.0, $this->station->id],
            [$this->branch, 201, '1', 'running', 1000, 3000, 0.0, $this->station->id],
            [$this->branch, 202, null, 'siding', 1800, 2300, 4.5, $this->station->id],
        ] as [$line, $way, $ref, $kind, $from, $to, $offset, $stationId]) {
            DB::table('osm_tracks')->insert(['line_id' => $line->id, 'osm_way_id' => $way, 'part' => 0, 'kind' => $kind, 'ref' => $ref,
                'pk_from_m' => $from, 'pk_to_m' => $to, 'pk_up' => true, 'pk_precision' => 'derived', 'length_m' => $to - $from, 'offset_m' => $offset,
                'station_id' => $stationId, 'attach' => 'station', 'tags' => '{}', 'created_at' => now(), 'updated_at' => now(),
                'geom' => DB::raw("ST_GeomFromText('LINESTRING(-0.33 45.68, -0.32 45.69)', 4326)")]);
        }
        DB::table('osm_track_nodes')->insert(['line_id' => $this->line->id, 'osm_node_id' => 900, 'kind' => 'switch', 'subtype' => 'default', 'ref' => '12',
            'pk_m' => 17120, 'pk_precision' => 'derived', 'way_id' => 101, 'tags' => '{}', 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_GeomFromText('POINT(-0.33 45.68)', 4326)")]);
        foreach ([
            ['579000', '579000-1-E', 'Quai VE', 210, 17240, 17450],
            // Enregistrement au PK faux (comme les deux « Quai 4 » de Brétigny au PK 0+000) : ni position ni quai.
            ['579000', '579000-1-D', 'Quai VD', 145, 0, 145],
            ['580000', '580000-1-1', 'Quai A', 120, 1900, 2020],
        ] as [$code, $track, $label, $length, $from, $to]) {
            DB::table('station_platforms')->insert(['station_id' => $this->station->id, 'sncf_station_name' => 'Cognac', 'code_ligne' => $code,
                'code_voie' => $track, 'label' => $label, 'length_m' => $length, 'pk_start_m' => $from, 'pk_end_m' => $to, 'source' => 'sncf',
                'dataset_modified' => '2020-05-26', 'fetched_at' => '2026-11-22 15:47:11']);
        }
    }

    public function test_each_line_through_the_station_gets_its_window_and_the_closed_line_none(): void
    {
        $this->seedRinf();
        $this->seedOsmAndSncf();
        $json = $this->getJson('/api/v1/stations/cognac/track-layout')->assertOk()
            ->json();

        $this->assertSame(['station', 'operational_points', 'lines', 'platforms', 'sources', 'counts'], array_keys($json));
        $this->assertSame(['slug' => 'cognac', 'name' => 'Cognac', 'uic_code' => '87491449'], $json['station']);
        $this->assertSame([['uopid' => 'FR0000003513', 'name' => 'Cognac', 'source' => 'rinf']], $json['operational_points']);
        // La ligne qui a le plus de voies d'abord ; la ligne fermée (aucune voie décrite) n'a pas de bloc.
        $this->assertSame(['579000', '580000'], array_column(array_column($json['lines'], 'line'), 'code'));

        $main = $json['lines'][0];
        $this->assertSame(['line', 'station_pk_m', 'station_pk_label', 'station_pk_source', 'window', 'track_layout'], array_keys($main));
        $this->assertSame([17277, '17+277', 'sncf'], [$main['station_pk_m'], $main['station_pk_label'], $main['station_pk_source']]);
        // Voies propres à la gare : voies du point RINF autour de son PK (530 m au plus : 17 012 à 17 542), faisceau
        // d'OSM jusqu'au PK 18+000 ; 200 m de marge de chaque côté, et 500 m au moins de chaque côté du PK de la gare.
        $this->assertSame(['from_m' => 16777, 'to_m' => 18200], $main['window']);
        // Même contrat que le schéma d'une ligne, sur la fenêtre.
        $this->assertSame(['line', 'pk', 'sources', 'operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones', 'strip', 'coverage', 'counts'],
            array_keys($main['track_layout']));
        $line = $this->getJson('/api/v1/lines/rfn-579000/track-layout?from_pk_m=16777&to_pk_m=18200')->assertOk()->json();
        $this->assertSame($line['tracks'], $main['track_layout']['tracks']);
        $this->assertSame($line['counts'], $main['track_layout']['counts']);
        $this->assertSame(['osm:node:900'], array_column($main['track_layout']['switches'], 'id'));

        // Bifurcation sans RINF : la voie de service d'OSM rattachée à la gare (1 800 à 2 300) et la demi-fenêtre minimale.
        $branch = $json['lines'][1];
        $this->assertSame([2000, 'projected'], [$branch['station_pk_m'], $branch['station_pk_source']]);
        $this->assertSame(['from_m' => 1500, 'to_m' => 2500], $branch['window']);
        $this->assertSame(['osm:way:201', 'osm:way:202'], collect((array) $branch['track_layout']['tracks'])->pluck('id')->sort()->values()->all());

        $this->assertSame(['rinf', 'osm', 'sncf'], array_values(array_diff(array_column($json['sources'], 'key'), ['vff'])));
        $this->assertSame(2, $json['counts']['lines']);
        $this->assertSame(count($main['track_layout']['tracks']) + 2, $json['counts']['tracks']);
        $this->assertSame(3, $json['counts']['platforms']);
    }

    public function test_platform_edges_get_rinf_length_and_height_sncf_positions_and_their_drawn_track(): void
    {
        $this->seedRinf();
        $this->seedOsmAndSncf();
        $json = $this->getJson('/api/v1/stations/cognac/track-layout')->assertOk()->json();
        $edges = collect((array) $json['platforms'])->keyBy('ref');
        $this->assertSame(['Quai VD', 'Quai VE', 'Quai A'], collect((array) $json['platforms'])->pluck('ref')->all());
        $this->assertSame(StationTrackLayout::PLATFORM_FIELDS, array_keys($edges['Quai VE']));

        // Quai VE : voie E du RINF, remplacée dans le schéma par la voie E d'OSM (qui garde son identifiant RINF) ;
        // longueur et hauteur du RINF, PK de la « Liste des quais ».
        $this->assertSame(['rinf:pe:'.substr(md5('http://data.europa.eu/949/platformEdge/ve'), 0, 12), 'E', '579000', 'osm:way:101', 17240, 17450, 145, 300, 'rinf', 'sncf', 'exact'],
            array_values(array_diff_key($edges['Quai VE'], ['ref' => 1])));
        $e = collect((array) $json['lines'][0]['track_layout']['tracks'])->firstWhere('id', 'osm:way:101');
        $this->assertSame('rinf:t:'.substr(md5('http://data.europa.eu/949/track/t4'), 0, 12), $e['rinf_id']);
        // Quai VD : la voie D du RINF est la voie unique en gare (seule voie principale du point sans voie dessinée, sur
        // une ligne à voie unique) ; le PK 0+000 de SNCF Réseau est loin de la gare : placé autour du PK de la gare
        // (17 277, à la coupure des deux sections de la voie unique), à peu près.
        $this->assertSame(['D', '579000', 'rinf:t:'.substr(md5('http://data.europa.eu/949/track/t1'), 0, 12), 17205, 17350, 145, 300, 'rinf', 'vff', 'approximate'],
            [$edges['Quai VD']['track_ref'], $edges['Quai VD']['line_code'], $edges['Quai VD']['track'], $edges['Quai VD']['pk_from_m'],
                $edges['Quai VD']['pk_to_m'], $edges['Quai VD']['length_m'], $edges['Quai VD']['height_mm'], $edges['Quai VD']['source'],
                $edges['Quai VD']['pk_source'], $edges['Quai VD']['precision']]);
        // Quai que SNCF Réseau seul connaît : ses PK et sa longueur, sans hauteur, le long de la voie 1 de la bifurcation.
        $this->assertSame(['sncf:quai:', '1', '580000', 'osm:way:201', 1900, 2020, 120, null, 'sncf', 'sncf', 'exact'],
            [substr($edges['Quai A']['id'], 0, 10), $edges['Quai A']['track_ref'], $edges['Quai A']['line_code'], $edges['Quai A']['track'],
                $edges['Quai A']['pk_from_m'], $edges['Quai A']['pk_to_m'], $edges['Quai A']['length_m'], $edges['Quai A']['height_mm'],
                $edges['Quai A']['source'], $edges['Quai A']['pk_source'], $edges['Quai A']['precision']]);
        $sncf = collect((array) $json['sources'])->firstWhere('key', 'sncf');
        $this->assertSame('ODbL 1.0', $sncf['licence']);
        $this->assertStringContainsString('Liste des quais', $sncf['attribution']);
    }

    public function test_a_rinf_edge_without_sncf_position_is_placed_around_the_station_on_its_track(): void
    {
        $this->seedRinf();
        $this->seedOsmAndSncf();
        DB::table('station_platforms')->where('code_voie', '579000-1-E')->delete();
        $edge = collect((array) $this->getJson('/api/v1/stations/cognac/track-layout')->assertOk()->json('platforms'))->firstWhere('ref', 'Quai VE');

        // Centré sur le PK de la gare (17 277), 145 m, dans la voie E d'OSM (17 100 à 17 600).
        $this->assertSame([17205, 17350, 'vff', 'approximate'], [$edge['pk_from_m'], $edge['pk_to_m'], $edge['pk_source'], $edge['precision']]);
    }

    public function test_one_line_and_light_overview(): void
    {
        $this->seedRinf();
        $this->seedOsmAndSncf();

        $one = $this->getJson('/api/v1/stations/cognac/track-layout?line=580000')->assertOk()->json();
        $this->assertSame(['rfn-580000'], array_column(array_column($one['lines'], 'line'), 'slug'));
        $this->assertSame(['Quai A'], array_column($one['platforms'], 'ref'));
        $this->assertSame(['rfn-580000'], array_column(array_column($this->getJson('/api/v1/stations/cognac/track-layout?line=rfn-580000')
            ->assertOk()->json('lines'), 'line'), 'slug'));

        // Aperçu de la fiche : nombres, fenêtres et quais (rattachés aux voies) sans les voies elles-mêmes.
        $light = $this->getJson('/api/v1/stations/cognac/track-layout?kinds=operational_points')->assertOk()->json();
        $this->assertSame([], $light['lines'][0]['track_layout']['tracks']);
        $this->assertGreaterThan(0, $light['lines'][0]['track_layout']['counts']['tracks']);
        $this->assertSame('osm:way:101', collect((array) $light['platforms'])->firstWhere('ref', 'Quai VE')['track']);

        $this->getJson('/api/v1/stations/cognac/track-layout?kinds=tracks,aiguilles')->assertStatus(422);
        $this->getJson('/api/v1/stations/inconnue/track-layout')->assertNotFound();
    }

    public function test_a_station_without_described_tracks_gets_an_empty_answer_and_a_missing_table_no_error(): void
    {
        $json = $this->getJson('/api/v1/stations/sans-voies/track-layout')->assertOk()->json();
        $this->assertSame([], $json['lines']);
        $this->assertSame([], $json['platforms']);
        $this->assertSame(['lines' => 0, 'tracks' => 0, 'switches' => 0, 'signals' => 0, 'crossings' => 0, 'buffer_stops' => 0, 'platforms' => 0], $json['counts']);

        $this->seedRinf();
        Schema::drop('rinf_platforms');
        Schema::drop('station_platforms');
        $json = $this->getJson('/api/v1/stations/cognac/track-layout')->assertOk()->json();
        $this->assertSame([], $json['lines']);
        $this->assertSame([], $json['platforms']);
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $this->seedRinf();
        $this->seedOsmAndSncf();
        $rest = $this->getJson('/api/v1/stations/cognac/track-layout?kinds=tracks,switches,operational_points')->assertOk()->json();
        $gql = $this->postJson('/graphql', ['query' => '{ station(slug: "cognac") { track_layout(kinds: [TRACKS, SWITCHES, OPERATIONAL_POINTS]) {
            station { slug name uic_code } operational_points { uopid name source }
            lines { line { code slug name } station_pk_m station_pk_label station_pk_source window { from_m to_m }
                track_layout { line { code slug name } pk { start_m end_m start_label end_label }
                    sources { key label licence dataset fetched_at url attribution licence_url }
                    operational_points { id name kind pk_m uopid station_slug rinf_type source precision }
                    tracks { id ref rinf_ref rinf_id kind pk_from_m pk_to_m lane op_id station_slug length_m electrified platform direction max_speed_kmh source precision }
                    switches { id ref pk_m kind from_track to_track facing source precision }
                    counts { operational_points tracks switches signals crossings buffer_stops milestones } } }
            platforms { id ref track_ref line_code track pk_from_m pk_to_m length_m height_mm source pk_source precision }
            sources { key label licence dataset fetched_at url attribution licence_url }
            counts { lines tracks switches signals crossings buffer_stops platforms } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.station.track_layout');

        foreach (['station', 'operational_points', 'platforms', 'sources', 'counts'] as $key) {
            $this->assertSame($rest[$key], $gql[$key], $key);
        }
        foreach ($rest['lines'] as $i => $block) {
            foreach (['line', 'station_pk_m', 'station_pk_label', 'station_pk_source', 'window'] as $key) {
                $this->assertSame($block[$key], $gql['lines'][$i][$key], "lines.{$i}.{$key}");
            }
            foreach (['line', 'pk', 'sources', 'operational_points', 'tracks', 'switches', 'counts'] as $key) {
                $this->assertSame($block['track_layout'][$key], $gql['lines'][$i]['track_layout'][$key], "lines.{$i}.track_layout.{$key}");
            }
        }

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'station_track_layout',
                'arguments' => ['station' => 'cognac', 'kinds' => ['tracks', 'switches', 'operational_points']]]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) ($result['isError'] ?? false));
        $mcp = json_decode((string) $result['content'][0]['text'], true);
        $this->assertSame($rest['lines'], $mcp['lines']);
        $this->assertSame($rest['platforms'], $mcp['platforms']);
        $this->assertSame($rest['counts'], $mcp['counts']);
        $this->assertSame(['osm', 'rinf', 'sncf'], collect((array) $mcp['attribution']['sources'])->pluck('provider')->intersect(['rinf', 'osm', 'sncf'])->sort()->values()->all());
        $this->assertStringEndsWith('/stations/cognac/voies', (string) $mcp['vff_url']);
    }

    public function test_pages_of_the_station_tracks_and_of_a_line_opened_at_a_kilometre_point(): void
    {
        $this->get('/stations/cognac/voies')->assertOk()->assertInertia(fn ($page) => $page->component('Stations/Tracks')
            ->where('station.slug', 'cognac')->where('station.name', 'Cognac'));
        $this->get('/stations/inconnue/voies')->assertNotFound();
        $this->get('/lines/rfn-579000/voies?pk=17277')->assertOk()->assertInertia(fn ($page) => $page->component('Lines/Tracks')->where('start_pk', 17277));
        $this->get('/lines/rfn-579000/voies?pk=abc')->assertOk()->assertInertia(fn ($page) => $page->where('start_pk', null));
    }

    public function test_window_and_platform_track_references(): void
    {
        // Voies de la gare et marge ; demi-fenêtre minimale autour du PK ; plafond loin du PK.
        $this->assertSame([16777, 18200], StationTrackLayout::window(17277, [[17012, 17542], [17700, 18000]]));
        $this->assertSame([16612, 18200], StationTrackLayout::window(17277, [[16812, 17542], [17700, 18000]]));
        $this->assertSame([1500, 2500], StationTrackLayout::window(2000, [[1900, 2100]]));
        $this->assertSame([-2500, 2500], StationTrackLayout::window(0, [[-9000, 9000]]));
        $this->assertSame([800, 2200], StationTrackLayout::window(null, [[1000, 2000]]));

        $this->assertSame('Z', StationTrackLayout::platformTrackRef('Voie Z de Poitiers'));
        $this->assertSame('V1', StationTrackLayout::platformTrackRef('Voie V1'));
        $this->assertSame('43', StationTrackLayout::platformTrackRef('Voie 43 Poitiers'));
        $this->assertSame('D', StationTrackLayout::platformTrackRef('D'));
        $this->assertSame('V1', StationTrackLayout::platformTrackRef('570000-3-V1'));
        $this->assertSame('41', StationTrackLayout::platformTrackRef('570000-3-41'));
    }
}
