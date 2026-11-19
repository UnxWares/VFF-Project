<?php

namespace Tests\Feature\Mcp;

use App\Models\Era;
use App\Models\Line;
use App\Models\LineSegment;
use App\Models\Station;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Outils MCP de liste (network_events, media, list_lines, list_stations) par le transport HTTP réel
 * (POST /mcp) : données, filtres partagés avec l'API REST, pagination par curseur, validation stricte,
 * attribution des licences.
 *
 * Mini-réseau : « Ligne de A à C » (rfn-420000, Finistère, en service), « Ligne de B à D » (fermée en
 * 2030), trois événements, gares A (29), B (29), D (22, fermée), deux photos de la gare A.
 */
class VffMcpListToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $era1930 = Era::factory()->create(['year' => 1930]);
        $era2030 = Era::factory()->current()->create(['year' => 2030]);

        $ac = Line::factory()->create(['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de A à C', 'network' => 'principal', 'departements' => json_encode(['Finistère'])]);
        $bd = Line::factory()->create(['slug' => 'ligne-b-d', 'code' => null, 'name' => 'Ligne de B à D', 'network' => 'secondaire']);
        foreach ([[$ac, ['in_service', 'in_service']], [$bd, ['in_service', 'abandoned']]] as [$line, $statuses]) {
            foreach ([$era1930, $era2030] as $i => $era) {
                LineSegment::factory()->create([
                    'line_id' => $line->id, 'era_id' => $era->id, 'status' => $statuses[$i], 'traffic_type' => 'mixed',
                    'geom' => LineString::make([Point::makeGeodetic(48.0, 2.0), Point::makeGeodetic(48.0, 2.1)]),
                    'is_heritage' => false, 'source' => 'osm',
                ]);
            }
        }

        foreach (['a' => ['29', 'active'], 'b' => ['29', 'active'], 'd' => ['22', 'declassified']] as $slug => [$dept, $status]) {
            $station = Station::factory()->create([
                'slug' => $slug, 'name' => 'Gare '.strtoupper($slug), 'city' => 'Ville '.strtoupper($slug), 'type' => 'gare',
                'department_code' => $dept, 'status' => $status, 'geom' => Point::makeGeodetic(48.0, 2.0),
                'opened_at' => '1865-04-01', 'opened_precision' => 'day',
            ]);
            DB::table('station_lines')->insert(['station_id' => $station->id, 'line_id' => $slug === 'd' ? $bd->id : $ac->id, 'method' => 'test']);
            foreach ([$era1930, $era2030] as $era) {
                DB::table('station_eras')->insert(['station_id' => $station->id, 'era_id' => $era->id,
                    'status' => $era->is_current ? $status : 'active', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $event = fn (Line $line, string $type, string $date, string $source, string $url) => [
            'line_id' => $line->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day', 'description' => $type,
            'source' => $source, 'source_url' => $url, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('line_events')->insert([
            $event($ac, 'opening', '1865-04-01', 'wikipedia', 'https://fr.wikipedia.org/wiki/A'),
            $event($ac, 'closure_passenger', '1938-05-15', 'archeoferroviaire', 'http://archeoferroviaire.free.fr/x'),
            $event($bd, 'closure_passenger', '1970-01-01', 'wikidata', 'https://www.wikidata.org/wiki/Q1'),
        ]);

        $a = Station::where('slug', 'a')->value('id');
        foreach ([1, 2] as $n) {
            DB::table('media')->insert([
                'mediable_type' => Station::class, 'mediable_id' => $a, 'provider' => 'commons', 'external_id' => "ext-{$n}",
                'type' => 'photo', 'title' => "Gare A {$n}", 'mime_type' => 'image/jpeg', 'thumb_url' => "https://upload.wikimedia.org/a{$n}.jpg",
                'source_url' => "https://commons.wikimedia.org/wiki/File:A{$n}.jpg", 'author' => 'Auteur', 'license' => 'CC BY-SA 4.0',
                'attribution' => 'Auteur, CC BY-SA 4.0', 'rights_status' => 'open_license', 'commercial_ok' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function tool(string $tool, array $arguments = []): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');
        $text = $result['content'][0]['text'];

        return [json_decode($text, true) ?? ['raw' => $text], (bool) $result['isError']];
    }

    public function test_network_events_filter_by_department_and_type_with_provenance(): void
    {
        [$data, $error] = $this->tool('network_events', ['department' => '29', 'type' => 'closure_passenger']);
        $this->assertFalse($error);
        $this->assertSame(['1938-05-15'], array_column($data['events'], 'date'));
        $this->assertSame('rfn-420000', $data['events'][0]['line']['slug']);
        $this->assertSame('archeoferroviaire', $data['events'][0]['provenance']['provider']);
        $this->assertSame('15 mai 1938', $data['events'][0]['when']);
        $this->assertFalse($data['has_more']);
        $providers = collect((array) $data['attribution']['sources'])->keyBy('provider');
        $this->assertFalse($providers['archeoferroviaire']['open_licence']);
    }

    public function test_network_events_paginate_with_cursor(): void
    {
        [$first] = $this->tool('network_events', ['limit' => 2, 'order' => 'desc']);
        $this->assertSame(['1970-01-01', '1938-05-15'], array_column($first['events'], 'date'));
        $this->assertTrue($first['has_more']);

        [$second] = $this->tool('network_events', ['limit' => 2, 'order' => 'desc', 'cursor' => $first['next_cursor']]);
        $this->assertSame(['1865-04-01'], array_column($second['events'], 'date'));
        $this->assertNull($second['next_cursor']);
    }

    public function test_an_oversized_page_is_shortened_without_losing_any_item_when_following_cursors(): void
    {
        $line = Line::where('slug', 'rfn-420000')->firstOrFail();
        DB::table('line_events')->insert(array_map(fn (int $n): array => [
            'line_id' => $line->id, 'type' => 'other', 'event_date' => sprintf('19%02d-01-01', $n), 'date_precision' => 'year',
            'description' => "Événement {$n} : ".str_repeat('texte long de la source ', 30), 'source' => 'wikipedia',
            'source_url' => 'https://fr.wikipedia.org/wiki/A', 'created_at' => now(), 'updated_at' => now(),
        ], range(1, 30)));
        config(['mcp.vff.max_response_bytes' => 12_000]);
        $expected = DB::table('line_events')->count();

        $seen = [];
        $calls = 0;
        $cursor = null;
        $shortened = false;
        do {
            [$data, $error] = $this->tool('network_events', ['limit' => 25] + ($cursor !== null ? ['cursor' => $cursor] : []));
            $this->assertFalse($error);
            $this->assertLessThanOrEqual(12_000, strlen((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
            $this->assertSame(count($data['events']), $data['count']);
            if (isset($data['truncated'])) {
                $shortened = true;
                $this->assertTrue($data['has_more']);
                $this->assertGreaterThan(0, $data['truncated']['events']);
                $this->assertNotEmpty($data['truncated_note']);
            }
            foreach ($data['events'] as $event) {
                $seen[] = $event['id'];
            }
            $cursor = $data['next_cursor'];
        } while ($cursor !== null && ++$calls < 50);

        $this->assertTrue($shortened, 'la première page doit dépasser le plafond');
        $this->assertCount($expected, $seen, 'aucun événement perdu');
        $this->assertSame($seen, array_values(array_unique($seen)), 'aucun événement en double');
    }

    public function test_media_of_a_station_keep_their_rights(): void
    {
        [$data, $error] = $this->tool('media', ['station' => 'a', 'limit' => 1]);
        $this->assertFalse($error);
        $this->assertCount(1, $data['media']);
        $this->assertSame('Auteur, CC BY-SA 4.0', $data['media'][0]['attribution']);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:A1.jpg', $data['media'][0]['source_url']);
        $this->assertSame(['type' => 'station', 'slug' => 'a', 'name' => 'Gare A'], $data['media'][0]['subject']);
        $this->assertContains('commons', array_column($data['attribution']['sources'], 'provider'));
        $this->assertSame('https://vff-project.org/stations/a', $data['vff_url']);

        [$next] = $this->tool('media', ['station' => 'a', 'limit' => 1, 'cursor' => $data['next_cursor']]);
        $this->assertSame('Gare A 2', $next['media'][0]['title']);
    }

    public function test_list_lines_with_era_status_and_facets(): void
    {
        [$data, $error] = $this->tool('list_lines', ['status' => 'closed', 'facets' => true]);
        $this->assertFalse($error);
        $this->assertSame(2030, $data['era_year']);
        $this->assertSame(['ligne-b-d'], array_column($data['lines'], 'slug'));
        $this->assertSame('closed', $data['lines'][0]['state']);
        $this->assertSame(['total' => 1, 'open' => 0, 'closed' => 1, 'unknown' => 0], array_slice($data['facets'], 0, 4));

        [$data] = $this->tool('list_lines', ['status' => 'open', 'era' => 1930]);
        $this->assertSame(['rfn-420000', 'ligne-b-d'], array_column($data['lines'], 'slug'));
        $this->assertSame('https://vff-project.org/lines/rfn-420000', $data['lines'][0]['vff_url']);

        [$page] = $this->tool('list_lines', ['limit' => 1, 'sort' => 'code']);
        $this->assertSame(['rfn-420000'], array_column($page['lines'], 'slug'));
        [$page] = $this->tool('list_lines', ['limit' => 1, 'sort' => 'code', 'cursor' => $page['next_cursor']]);
        $this->assertSame(['ligne-b-d'], array_column($page['lines'], 'slug'), 'ligne sans code en dernier, atteinte par le curseur');
    }

    public function test_list_stations_by_department_and_era(): void
    {
        [$data, $error] = $this->tool('list_stations', ['department' => '29', 'facets' => true]);
        $this->assertFalse($error);
        $this->assertSame(['a', 'b'], array_column($data['stations'], 'slug'));
        $this->assertSame([['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de A à C']], $data['stations'][0]['lines']);
        $this->assertSame(['active' => 2], $data['facets']);

        [$data] = $this->tool('list_stations', ['era' => 1930, 'status' => 'active']);
        $this->assertSame(1930, $data['era_year']);
        $this->assertCount(3, $data['stations']);
    }

    public function test_list_tools_validate_their_arguments(): void
    {
        foreach ([
            ['network_events', ['limit' => 500]],
            ['network_events', ['type' => 'explosion']],
            ['network_events', ['cursor' => 'pas-un-curseur']],
            ['media', ['line' => 'rfn-420000', 'station' => 'a']],
            ['list_lines', ['era' => 1935]],
            ['list_lines', ['network' => 'tramway']],
            ['list_stations', ['status' => 'open']],
            ['list_stations', ['unknown' => 1]],
        ] as [$tool, $arguments]) {
            [, $error] = $this->tool($tool, $arguments);
            $this->assertTrue($error, $tool.' '.json_encode($arguments));
        }
    }
}
