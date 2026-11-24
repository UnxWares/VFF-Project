<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Services\TrackLayout\TrackLayout;
use App\Services\TrackLayout\TrackLayoutPart;
use App\Services\TrackLayout\TrackLayoutSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schéma des voies (`GET /api/v1/lines/{line}/track-layout`, contrat agents-commun/contrat-schema-voies.md) :
 * réponse vide conforme pour une ligne sans donnée, voies et points d'exploitation du RINF avec leurs rangées,
 * une source OSM branchée par l'étiquette du conteneur qui ne fait que compléter, filtres de portion et de familles.
 * Extrait réel de la ligne 579000 (Beillant - Angoulême), RINF © ERA, graphe de SNCF Réseau, lu le 24/11/2026.
 */
class LineTrackLayoutApiTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        FakeOsmSource::$extraTracks = [];
        FakeOsmSource::$citedTrack = null;
        Era::factory()->current()->create(['year' => 2030]);
        $this->line = Line::factory()->create(['slug' => 'rfn-579000', 'code' => '579000', 'name' => 'Ligne de Beillant à Angoulême']);
        Line::factory()->create(['slug' => 'rfn-001000', 'code' => '001000', 'name' => 'Ligne sans données']);
    }

    /** Extrait de la ligne 579000 autour de Cognac : trois sections, la gare et ses voies. */
    private function seedRinf(): void
    {
        $meta = ['dataset' => 'http://data.europa.eu/949/graph/0087 (https://graph.data.era.europa.eu/repositories/rinf-plus)', 'fetched_at' => '2026-11-24 20:00:00'];
        foreach ([
            ['FR0000009305', 'Faisceau Pair', null, 'other'], ['FR0000003513', 'Cognac', 10, 'station'],
            ['FR9900004367', 'Accès à Faisceau Pair', null, 'other'], ['FR0000006311', 'Faisceau Impair', null, 'other'],
        ] as [$uopid, $name, $type, $kind]) {
            DB::table('rinf_operational_points')->insert(['uopid' => $uopid, 'name' => $name, 'op_type' => $type, 'kind' => $kind,
                'uri' => 'http://data.europa.eu/949/operationalPoint/'.$uopid] + $meta);
        }
        foreach ([
            ['579000-1_FR0000009305_FR0000003513', 'FR0000009305', 'FR0000003513', 17095, 17277, 'regular'],
            ['579000-1_FR0000003513_FR9900004367', 'FR0000003513', 'FR9900004367', 17277, 17917, 'regular'],
            ['579000-1_FR9900004367_FR0000006311', 'FR9900004367', 'FR0000006311', 17917, 17919, 'regular'],
            ['579000-1_FR0000009305_FR9900004367', 'FR0000009305', 'FR9900004367', 17095, 17917, 'link'],
        ] as [$uri, $start, $end, $from, $to, $nature]) {
            DB::table('rinf_sections')->insert(['uri' => 'http://data.europa.eu/949/sectionOfLine/'.$uri, 'national_line' => '579000-1', 'line_code' => '579000',
                'line_id' => $this->line->id, 'op_start_uopid' => $start, 'op_end_uopid' => $end, 'pk_start_m' => $from, 'pk_end_m' => $to,
                'length_m' => $to - $from, 'nature' => $nature] + $meta);
        }
        $section = fn (string $s): string => 'http://data.europa.eu/949/sectionOfLine/579000-1_'.$s;
        foreach ([
            ['t1', 'section', $section('FR0000009305_FR0000003513'), null, 'running', 'Voie UNIQUE de Beillant à St-Michel-sur-Charente', 'B', 110, null],
            ['t2', 'section', $section('FR0000003513_FR9900004367'), null, 'running', 'Voie UNIQUE de Beillant à St-Michel-sur-Charente', 'B', 140, null],
            ['t3', 'section', $section('FR9900004367_FR0000006311'), null, 'running', 'Voie UNIQUE de Beillant à St-Michel-sur-Charente', 'B', 140, null],
            ['t4', 'op', null, 'FR0000003513', 'running', 'Voie E de Cognac', null, null, null],
            ['t5', 'op', null, 'FR0000003513', 'running', 'D', null, null, null],
            ['s3', 'op', null, 'FR0000003513', 'siding', '3 - COGNAC', null, null, 413],
            ['s4', 'op', null, 'FR0000003513', 'siding', '4 - COGNAC', null, null, 530],
            ['s5', 'op', null, 'FR0000003513', 'siding', '5 - COGNAC', null, null, 370],
            ['l1', 'op', null, 'FR0000003513', 'siding', 'Liaison BS83/84', null, null, 0],
        ] as [$id, $parent, $sectionUri, $op, $class, $ref, $dir, $speed, $length]) {
            DB::table('rinf_tracks')->insert(['uri' => 'http://data.europa.eu/949/track/'.$id, 'parent' => $parent, 'section_uri' => $sectionUri,
                'op_uopid' => $op, 'rinf_class' => $class, 'ref' => $ref, 'direction' => $dir, 'max_speed_kmh' => $speed, 'length_m' => $length,
                'electrified' => $parent === 'section' ? false : null] + $meta);
        }
        DB::table('rinf_platforms')->insert(['uri' => 'http://data.europa.eu/949/platformEdge/pe', 'track_uri' => 'http://data.europa.eu/949/track/t4',
            'op_uopid' => 'FR0000003513', 'ref' => 'Quai VE', 'length_m' => 145, 'height_mm' => 300] + $meta);
    }

    public function test_a_line_without_data_gets_an_empty_layout_in_the_contract_shape(): void
    {
        $json = $this->getJson('/api/v1/lines/rfn-001000/track-layout')->assertOk()->json();

        $this->assertSame(['code' => '001000', 'slug' => 'rfn-001000', 'name' => 'Ligne sans données'], $json['line']);
        $this->assertSame(['line', 'pk', 'sources', 'operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones', 'strip', 'coverage', 'counts'], array_keys($json));
        $this->assertSame(array_fill_keys(TrackLayout::COUNTED, 0), $json['counts']);
        $this->assertSame([], $json['sources']);
        $this->assertSame([], $json['tracks']);
        $this->assertSame(['speeds' => [], 'gradients' => []], $json['strip']);
        $this->assertSame(['total_m' => 0, 'rinf_m' => 0, 'osm_m' => 0, 'vff_m' => 0, 'missing' => []], $json['coverage']);
        $this->assertNull($json['pk']['start_m']);
        $this->getJson('/api/v1/lines/inconnue/track-layout')->assertNotFound();
    }

    public function test_rinf_tracks_points_and_lanes_follow_the_contract(): void
    {
        $this->seedRinf();
        $json = $this->getJson('/api/v1/lines/rfn-579000/track-layout')->assertOk()->json();

        $this->assertSame(['start_m' => 17012, 'end_m' => 17919, 'start_label' => '17+012', 'end_label' => '17+919'], $json['pk']);
        $this->assertSame('rinf', $json['sources'][0]['key']);
        $this->assertStringContainsString('European Union Agency for Railways', $json['sources'][0]['attribution']);
        $this->assertSame('2026-11-24', $json['sources'][0]['fetched_at']);

        $cognac = collect((array) $json['operational_points'])->firstWhere('uopid', 'FR0000003513');
        $this->assertSame(['id' => 'rinf:op:FR0000003513', 'name' => 'Cognac', 'kind' => 'station', 'pk_m' => 17277, 'uopid' => 'FR0000003513',
            'station_slug' => null, 'rinf_type' => 10, 'source' => 'rinf', 'precision' => 'exact'], $cognac);

        $tracks = collect((array) $json['tracks'])->keyBy('rinf_ref');
        // Voie unique : rangée 0, position exacte, une pièce par section (la liaison de longueur nulle est écartée).
        $main = collect((array) $json['tracks'])->where('ref', 'VU')->values();
        $this->assertCount(3, $main);
        $this->assertSame([0], $main->pluck('lane')->unique()->values()->all());
        $this->assertSame('both', $main[0]['direction']);
        $this->assertSame('exact', $main[0]['precision']);
        // Voies de la gare : centrées sur le PK de Cognac, approchées ; impaires au-dessus, paires au-dessous.
        $this->assertSame(['loop', 17205, 17350, true, 'approximate'], [$tracks['Voie E de Cognac']['kind'], $tracks['Voie E de Cognac']['pk_from_m'],
            $tracks['Voie E de Cognac']['pk_to_m'], $tracks['Voie E de Cognac']['platform'], $tracks['Voie E de Cognac']['precision']]);
        $this->assertLessThan(0, $tracks['3 - COGNAC']['lane']);
        $this->assertLessThan(0, $tracks['5 - COGNAC']['lane']);
        $this->assertNotSame($tracks['3 - COGNAC']['lane'], $tracks['5 - COGNAC']['lane']);
        $this->assertSame(1, $tracks['4 - COGNAC']['lane']);
        $this->assertSame('crossover', $tracks['Liaison BS83/84']['kind']);
        $this->assertSame('rinf:op:FR0000003513', $tracks['4 - COGNAC']['op_id']);

        $this->assertSame([['pk_from_m' => 17095, 'pk_to_m' => 17277, 'kmh' => 110, 'source' => 'rinf'],
            ['pk_from_m' => 17277, 'pk_to_m' => 17917, 'kmh' => 140, 'source' => 'rinf'],
            ['pk_from_m' => 17917, 'pk_to_m' => 17919, 'kmh' => 140, 'source' => 'rinf']], $json['strip']['speeds']);

        // Ni aiguillage ni signal au RINF français : familles absentes sur toute la ligne.
        $this->assertSame(907, $json['coverage']['total_m']);
        $this->assertSame(824, $json['coverage']['rinf_m']);
        $missing = collect((array) $json['coverage']['missing']);
        $this->assertTrue($missing->contains(fn (array $m): bool => $m['what'] === 'switches' && $m['pk_from_m'] === 17012 && $m['pk_to_m'] === 17919));
        $this->assertTrue($missing->contains(fn (array $m): bool => $m['what'] === 'signals'));
    }

    public function test_a_tagged_osm_source_only_completes_the_rinf(): void
    {
        $this->seedRinf();
        $this->app->tag([FakeOsmSource::class], TrackLayout::TAG);
        $json = $this->getJson('/api/v1/lines/rfn-579000/track-layout')->assertOk()->json();

        $this->assertSame(['rinf', 'osm'], array_column($json['sources'], 'key'));
        // Voie OSM sous une portion décrite par le RINF : écartée ; au-delà : gardée, avec sa rangée.
        $osm = collect((array) $json['tracks'])->where('source', 'osm')->values();
        $this->assertSame(['osm:way:2'], $osm->pluck('id')->all());
        $this->assertSame(0, $osm[0]['lane']);
        // Aiguillages : le RINF n'en a pas, ceux d'OSM sont servis ; le doublon à 10 m de même référence est écarté.
        $this->assertSame(['osm:node:10'], array_column($json['switches'], 'id'));
        $this->assertSame(['pk_from_m' => 17919, 'pk_to_m' => 19000, 'kmh' => 100, 'source' => 'osm'], $json['strip']['speeds'][4]);
        $this->assertSame(['pk_from_m' => 17000, 'pk_to_m' => 17095, 'kmh' => 100, 'source' => 'osm'], $json['strip']['speeds'][0]);
        $this->assertSame(1081, $json['coverage']['osm_m']);
        $this->assertFalse(collect((array) $json['coverage']['missing'])->contains(fn (array $m): bool => $m['what'] === 'switches' && $m['pk_from_m'] <= 17500 && $m['pk_to_m'] >= 17600));
    }

    public function test_osm_service_tracks_are_kept_on_rinf_portions_and_replace_approximate_station_tracks(): void
    {
        $this->seedRinf();
        FakeOsmSource::$extraTracks = [
            FakeOsmSource::track('osm:way:3', 17150, 17500, '3', 'siding'),
            FakeOsmSource::track('osm:way:4', 17880, 17915, null, 'yard'),
        ];
        $this->app->tag([FakeOsmSource::class], TrackLayout::TAG);
        $tracks = collect((array) $this->getJson('/api/v1/lines/rfn-579000/track-layout')->assertOk()->json('tracks'));

        // Voies de service d'OSM sur une portion décrite par le RINF : gardées.
        $this->assertSame(['osm:way:2', 'osm:way:3', 'osm:way:4'], $tracks->where('source', 'osm')->pluck('id')->sort()->values()->all());
        // À Cognac, elles remplacent les voies approchées du RINF ; la voie 3 garde l'identifiant et la référence RINF.
        $this->assertSame([], $tracks->where('source', 'rinf')->where('precision', 'approximate')->values()->all());
        $this->assertCount(3, $tracks->where('source', 'rinf')->where('kind', 'running'));
        $three = $tracks->firstWhere('id', 'osm:way:3');
        $this->assertSame('rinf:t:'.substr(md5('http://data.europa.eu/949/track/s3'), 0, 12), $three['rinf_id']);
        $this->assertSame('3 - COGNAC', $three['rinf_ref']);
        $this->assertSame('rinf:op:FR0000003513', $three['op_id']);
        $this->assertSame(-1, $three['lane']);
        $this->assertNull($tracks->firstWhere('id', 'osm:way:4')['op_id']);
    }

    public function test_a_track_cited_by_a_kept_switch_is_kept(): void
    {
        $this->seedRinf();
        FakeOsmSource::$citedTrack = 'osm:way:1';
        $this->app->tag([FakeOsmSource::class], TrackLayout::TAG);
        $json = $this->getJson('/api/v1/lines/rfn-579000/track-layout')->assertOk()->json();

        $this->assertSame('osm:way:1', $json['switches'][0]['from_track']);
        $this->assertSame(['osm:way:1', 'osm:way:2'], collect((array) $json['tracks'])->where('source', 'osm')->pluck('id')->sort()->values()->all());
    }

    public function test_track_numbers_for_matching_sources(): void
    {
        $this->assertSame('3', TrackLayout::trackNumber('3 - COGNAC'));
        $this->assertSame('3', TrackLayout::trackNumber('V3'));
        $this->assertSame('1BIS', TrackLayout::trackNumber('Voie V1BIS'));
        $this->assertSame('E', TrackLayout::trackNumber('E de Cognac'));
        $this->assertSame('', TrackLayout::trackNumber(''));
    }

    public function test_graphql_and_mcp_serve_the_same_content(): void
    {
        $this->seedRinf();
        $this->app->tag([FakeOsmSource::class], TrackLayout::TAG);
        $rest = $this->getJson('/api/v1/lines/rfn-579000/track-layout?from_pk_m=17000&to_pk_m=18000')->assertOk()->json();
        $gql = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-579000") { track_layout(from_pk_m: 17000, to_pk_m: 18000) {
            line { code slug name } pk { start_m end_m start_label end_label }
            sources { key label licence dataset fetched_at url attribution licence_url }
            operational_points { id name kind pk_m uopid station_slug rinf_type source precision }
            tracks { id ref rinf_ref rinf_id kind pk_from_m pk_to_m lane op_id station_slug length_m electrified platform direction max_speed_kmh source precision }
            switches { id ref pk_m kind from_track to_track facing source precision }
            signals { id ref pk_m kind form track direction source precision }
            crossings { id pk_m kind ref use tracks source precision }
            buffer_stops { id pk_m track source precision }
            milestones { id pk_m label source precision }
            strip { speeds { pk_from_m pk_to_m kmh source } gradients { pk_from_m pk_to_m permille source precision } }
            coverage { total_m rinf_m osm_m vff_m missing { pk_from_m pk_to_m what } }
            counts { operational_points tracks switches signals crossings buffer_stops milestones } } } }'])->assertOk()->assertJsonMissingPath('errors')->json('data.line.track_layout');

        foreach (['line', 'pk', 'sources', 'operational_points', 'tracks', 'switches', 'signals', 'crossings', 'buffer_stops', 'milestones', 'strip', 'coverage', 'counts'] as $key) {
            $this->assertSame($rest[$key], $gql[$key], $key);
        }
        $this->assertSame([['id' => 'osm:node:20', 'pk_m' => 17950, 'track' => 'osm:way:2', 'source' => 'osm', 'precision' => 'derived']], $rest['buffer_stops']);
        $this->assertSame('17.5', $rest['milestones'][0]['label']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_track_layout',
                'arguments' => ['line' => '579000', 'from_pk_m' => 17000, 'to_pk_m' => 18000]]])
            ->assertOk()->json('result');
        $this->assertFalse((bool) ($result['isError'] ?? false));
        $mcp = json_decode((string) $result['content'][0]['text'], true);
        $this->assertSame($rest['tracks'], $mcp['tracks']);
        $this->assertSame($rest['coverage'], $mcp['coverage']);
        $this->assertSame($rest['milestones'], $mcp['milestones']);
        $this->assertSame($rest['counts'], $mcp['counts']);
        $this->assertContains('rinf', array_column((array) $mcp['attribution']['sources'], 'provider'));
        $this->assertStringContainsString("Agence de l'Union européenne pour les chemins de fer", (string) json_encode($mcp['attribution'], JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('European Union Agency for Railways', $mcp['sources'][0]['attribution']);
    }

    public function test_a_missing_table_never_breaks_the_endpoint(): void
    {
        $this->seedRinf();
        // Table d'une source absente (migration d'osm-voies pas encore passée) : la source est ignorée, le RINF reste.
        $this->app->tag([MissingTableSource::class], TrackLayout::TAG);
        $json = $this->getJson('/api/v1/lines/rfn-579000/track-layout')->assertOk()->json();
        $this->assertSame(['rinf'], array_column($json['sources'], 'key'));
        $this->assertNotSame([], $json['tracks']);

        // Table du RINF absente (migration pas encore passée en production) : 200, listes vides et couverture.
        Schema::drop('rinf_platforms');
        $json = $this->getJson('/api/v1/lines/rfn-579000/track-layout')->assertOk()->json();
        $this->assertSame([], $json['sources']);
        $this->assertSame([], $json['tracks']);
        $this->assertSame(0, $json['coverage']['total_m']);
    }

    public function test_portion_and_kinds_filters(): void
    {
        $this->seedRinf();
        $json = $this->getJson('/api/v1/lines/rfn-579000/track-layout?from_pk=17%2B500&to_pk_m=17700&kinds=tracks,speeds')->assertOk()->json();

        $this->assertSame(17500, $json['pk']['start_m']);
        $this->assertSame(17700, $json['pk']['end_m']);
        $this->assertSame([], $json['operational_points']);
        $this->assertNotSame([], $json['tracks']);
        $this->assertSame([['pk_from_m' => 17500, 'pk_to_m' => 17700, 'kmh' => 140, 'source' => 'rinf']], $json['strip']['speeds']);
        $this->assertSame(200, $json['coverage']['total_m']);

        $this->getJson('/api/v1/lines/rfn-579000/track-layout?kinds=tracks,aiguilles')->assertStatus(422);
        $this->getJson('/api/v1/lines/rfn-579000/track-layout?from_pk=abc')->assertStatus(422);
    }
}

/** Source OSM de test : une voie sous le RINF, une au-delà, deux aiguillages dont un doublon, une vitesse ; voies en plus au besoin. */
final class FakeOsmSource implements TrackLayoutSource
{
    /** @var list<array<string, mixed>> */
    public static array $extraTracks = [];

    /** Voie citée par le premier aiguillage (`from_track`). */
    public static ?string $citedTrack = null;

    public function key(): string
    {
        return 'osm';
    }

    /** @return array<string, mixed> */
    public static function track(string $id, int $from, int $to, ?string $ref = '1', string $kind = 'running'): array
    {
        return ['id' => $id, 'ref' => $ref, 'kind' => $kind, 'pk_from_m' => $from, 'pk_to_m' => $to,
            'op_id' => null, 'length_m' => $to - $from, 'electrified' => false, 'platform' => false, 'source' => 'osm', 'precision' => 'derived'];
    }

    public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart
    {
        $switch = fn (string $id, int $pk, ?string $from = null): array => ['id' => $id, 'ref' => '12', 'pk_m' => $pk, 'kind' => 'simple', 'from_track' => $from,
            'to_track' => null, 'facing' => null, 'source' => 'osm', 'precision' => 'derived'];

        return new TrackLayoutPart(
            source: ['key' => 'osm', 'label' => 'OpenStreetMap', 'licence' => 'ODbL', 'dataset' => null, 'fetched_at' => '2026-11-24', 'url' => 'https://www.openstreetmap.org/copyright'],
            tracks: [self::track('osm:way:1', 17300, 17400), self::track('osm:way:2', 17919, 19000), ...self::$extraTracks],
            switches: [$switch('osm:node:10', 17550, self::$citedTrack), $switch('osm:node:11', 17560)],
            speeds: [['pk_from_m' => 17000, 'pk_to_m' => 19000, 'kmh' => 100, 'source' => 'osm']],
            covered: ['tracks' => [[17919, 19000]], 'switches' => [[17000, 19000]]],
            bufferStops: [['id' => 'osm:node:20', 'pk_m' => 17950, 'track' => 'osm:way:2', 'source' => 'osm', 'precision' => 'derived']],
            milestones: [['id' => 'osm:node:30', 'pk_m' => 17500, 'label' => '17.5', 'source' => 'osm', 'precision' => 'derived']],
        );
    }
}

/** Source de test dont la table n'existe pas. */
final class MissingTableSource implements TrackLayoutSource
{
    public function key(): string
    {
        return 'osm';
    }

    public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart
    {
        DB::table('osm_track_layout_absente')->where('line_id', $line->id)->get();

        return TrackLayoutPart::empty();
    }
}
