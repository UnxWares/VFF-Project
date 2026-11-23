<?php

namespace Tests\Feature\Mcp;

use App\Models\Line;
use App\Models\LineEvent;
use App\Services\TouristRailways;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Outils MCP `network_chronology` et `tourist_railways` : mêmes données que l'API, filtres, bornes, sources. */
class ChronologyTouristToolsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: array<string, mixed>, 1: bool} */
    private function tool(string $tool, array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]])
            ->assertOk()->json('result');

        return [json_decode($result['content'][0]['text'], true) ?? [], (bool) ($result['isError'] ?? false)];
    }

    private function chronology(): void
    {
        Cache::flush();
        $line = Line::factory()->create(['slug' => 'rfn-000001', 'name' => 'Ligne de Morlaix à Roscoff', 'departements' => json_encode(['Finistère']), 'length_official_m' => 28000]);
        $base = ['line_id' => $line->id, 'description' => 'Fait daté.', 'confidence' => 'high', 'date_precision' => 'day', 'source_url' => null, 'section_from' => null, 'section_to' => null];
        LineEvent::factory()->create($base + ['type' => 'opening', 'event_date' => '1883-06-10', 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/wiki/X']);
        LineEvent::factory()->create($base + ['type' => 'closure_passenger', 'event_date' => '1938-06-10', 'source' => 'wikipedia']);
        LineEvent::factory()->create($base + ['type' => 'closure', 'event_date' => '1938-01-01', 'date_precision' => 'year', 'source' => 'archeoferroviaire']);
        $this->artisan('vff:compute-network-chronology')->assertSuccessful();
    }

    public function test_chronology_overview_decade_and_year(): void
    {
        $this->chronology();

        [$all, $error] = $this->tool('network_chronology', []);
        $this->assertFalse($error);
        $this->assertSame(1820, $all['decades'][0]['decade']);
        $this->assertArrayNotHasKey('years', $all);

        [$decade] = $this->tool('network_chronology', ['decade' => 1930, 'types' => ['closure']]);
        $this->assertSame(1938, $decade['years'][0]['year']);
        $this->assertArrayNotHasKey('facts', $decade['years'][0]);

        [$year] = $this->tool('network_chronology', ['year' => 1938, 'lang' => 'en', 'region' => '53']);
        $this->assertSame(1, $year['total']);
        $this->assertSame('10 June 1938', $year['facts'][0]['when']);
        $this->assertSame('day', $year['facts'][0]['precision']);
        $this->assertSame('https://vff-project.org/chronology?decade=1930', $year['vff_url']);
        // La source à usage non commercial ne se détaille jamais.
        $this->assertStringNotContainsString('archeoferroviaire', (string) json_encode($year));

        [$page] = $this->tool('network_chronology', ['year' => 1938, 'offset' => 5]);
        $this->assertSame([], $page['facts']);
        $this->assertFalse($page['has_more']);
    }

    public function test_chronology_validates_its_input(): void
    {
        $this->chronology();

        foreach ([['year' => 1938, 'decade' => 1930], ['decade' => 1935], ['year' => 1500], ['decade' => 2990],
            ['types' => ['nope']], ['region' => '00'], ['limit' => 0], ['colour' => 'red']] as $arguments) {
            [, $error] = $this->tool('network_chronology', $arguments);
            $this->assertTrue($error, json_encode($arguments));
        }
    }

    public function test_tourist_railways_filters_pages_and_cites_sources(): void
    {
        $steam = ['id' => 'a-steam', 'name' => 'Train A', 'type' => 'steam', 'regions' => ['Occitanie'], 'sources' => ['type' => 'wp-list', 'length' => 'ign', 'operator' => null]];
        $velo = ['id' => 'b-velo', 'name' => 'Vélorail B', 'type' => 'velorail', 'regions' => ['Bretagne'], 'sources' => ['type' => 'wp-list']];
        $green = ['name' => 'Via X', 'regions' => ['Occitanie'], 'sources' => ['length' => 'osm']];
        Cache::put(TouristRailways::CACHE_KEY, [
            'railways' => [$steam, $velo], 'greenways' => [$green], 'regions' => ['Bretagne', 'Occitanie'], 'types' => ['steam', 'velorail'],
            'sources' => TouristRailways::SOURCES,
        ], 600);

        [$all, $error] = $this->tool('tourist_railways', []);
        $this->assertFalse($error);
        $this->assertSame(2, $all['total']);
        $this->assertArrayNotHasKey('greenways', $all);
        $this->assertSame(['wp-list', 'ign'], array_keys($all['sources']));

        [$occ] = $this->tool('tourist_railways', ['region' => 'Occitanie', 'greenways' => true]);
        $this->assertSame(['Train A'], array_column($occ['railways'], 'name'));
        $this->assertSame(['Via X'], array_column($occ['greenways'], 'name'));
        $this->assertContains('osm', array_keys($occ['sources']));

        [$typed] = $this->tool('tourist_railways', ['type' => 'velorail', 'greenways' => true]);
        $this->assertSame([], $typed['greenways']);

        [$page] = $this->tool('tourist_railways', ['limit' => 1, 'offset' => 1]);
        $this->assertSame(['Vélorail B'], array_column($page['railways'], 'name'));
        $this->assertFalse($page['has_more']);

        foreach ([['type' => 'nope'], ['limit' => 101], ['offset' => -1], ['x' => 1]] as $arguments) {
            [, $error] = $this->tool('tourist_railways', $arguments);
            $this->assertTrue($error, json_encode($arguments));
        }
    }
}
