<?php

namespace Tests\Feature\Mcp;

use App\Models\Line;
use App\Models\LineEvent;
use App\Models\Material;
use App\Models\Station;
use App\Services\Lines\LineSlugRedirects;
use App\Services\Materials\PreservedRollingStock;
use App\Services\RailProjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Outils MCP ajoutés pour couvrir ce que REST expose déjà : `rail_projects` (GET /projects), `preserved_rolling_stock`
 * (GET /materials/preserved et /materials/{material}/preserved), `station_events` (GET /stations/{station}/events),
 * `preserved_count` de get_material, et le bloc `redirected` d'un appel fait avec une ancienne adresse.
 */
class VffMcpCoverageToolsTest extends TestCase
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

    /** @return array<string, mixed> */
    private static function project(string $id, string $kind, string $state, array $regions, array $lines, array $stages): array
    {
        return ['id' => $id, 'name' => 'Projet '.$id, 'kind' => $kind, 'state' => $state, 'regions' => $regions, 'porteurs' => [],
            'lines' => $lines, 'summary' => 'Résumé.', 'stages' => $stages, 'next_stage' => null, 'planned_commissioning' => null, 'checked' => '2026-11-23'];
    }

    public function test_rail_projects_filters_pages_and_answers_an_old_line_slug(): void
    {
        $this->app->instance(LineSlugRedirects::class, new LineSlugRedirects(['ancien-slug-essai' => 'ligne-essai']));
        Line::factory()->create(['slug' => 'ligne-essai', 'name' => 'Ligne d\'Essai', 'code' => null]);
        $stage = ['kind' => 'works', 'date' => '2025-12', 'precision' => 'month', 'done' => true, 'note' => null,
            'source' => ['label' => 'Journal officiel', 'url' => 'https://www.legifrance.gouv.fr/jorf/id/X', 'published' => null]];
        $planned = ['kind' => 'commissioning', 'date' => '2028', 'precision' => 'year', 'done' => false, 'note' => null,
            'source' => ['label' => 'Région', 'url' => 'https://example.org/p', 'published' => null]];
        Cache::put(RailProjects::CACHE_KEY, [
            'projects' => [
                self::project('a', 'reopening', 'works', ['Occitanie'], [['slug' => 'ligne-essai', 'name' => 'Ligne d\'Essai']], [$stage, $planned]),
                self::project('b', 'lgv', 'debate', ['Bretagne'], [], [$planned]),
            ],
            'regions' => ['Bretagne', 'Occitanie'], 'kinds' => RailProjects::KINDS, 'states' => RailProjects::STATES, 'checked' => '2026-11-23',
        ], 600);

        [$all, $error] = $this->tool('rail_projects', []);
        $this->assertFalse($error);
        $this->assertSame(2, $all['total']);
        $this->assertSame(['a', 'b'], array_column($all['projects'], 'id'));
        $this->assertNotEmpty($all['planned_dates_note']);
        $this->assertSame('https://vff-project.org/projects', $all['vff_url']);
        $this->assertArrayNotHasKey('redirected', $all);

        [$works] = $this->tool('rail_projects', ['kind' => 'reopening', 'state' => 'works', 'region' => 'Occitanie']);
        $this->assertSame(['a'], array_column($works['projects'], 'id'));
        // La source de l'étape (Journal officiel) figure dans l'attribution.
        $this->assertContains('jorf', array_column($works['attribution']['sources'], 'provider'));

        [$page] = $this->tool('rail_projects', ['limit' => 1, 'offset' => 1]);
        $this->assertSame(['b'], array_column($page['projects'], 'id'));
        $this->assertFalse($page['has_more']);

        [$byOld] = $this->tool('rail_projects', ['line' => 'ancien-slug-essai', 'lang' => 'en']);
        $this->assertSame(['a'], array_column($byOld['projects'], 'id'));
        $this->assertSame('ligne-essai', $byOld['filters']['line']);
        $this->assertSame(['kind' => 'line', 'requested' => 'ancien-slug-essai', 'current' => 'ligne-essai', 'reason' => 'renamed'],
            array_diff_key($byOld['redirected'][0], ['note' => true]));
        $this->assertStringContainsString('renamed line', $byOld['redirected'][0]['note']);
        $this->assertSame('redirected', array_key_first($byOld), 'le bloc est en tête de réponse');

        foreach ([['state' => 'done'], ['kind' => 'tram'], ['limit' => 51], ['offset' => -1], ['line' => 'nulle-part'], ['x' => 1]] as $arguments) {
            [, $error] = $this->tool('rail_projects', $arguments);
            $this->assertTrue($error, json_encode($arguments));
        }
    }

    private function preserved(): void
    {
        Material::factory()->create(['slug' => '241-p', 'name' => '241 P']);
        Material::factory()->create(['slug' => 'cc-40100', 'name' => 'CC 40100']);
        $wp = ['wikipedia', '241 P', 1];
        $this->app->instance(PreservedRollingStock::class, new PreservedRollingStock(null, [
            'venues' => [
                'cite-du-train' => ['name' => 'Cité du train', 'type' => 'museum', 'commune' => 'Mulhouse'],
                'cfc' => ['name' => 'Chemins de fer du Creusot', 'type' => 'tourist_railway', 'commune' => 'Le Creusot'],
            ],
            'units' => [
                '241-p' => [
                    ['number' => '241 P 17', 'venue' => 'cfc', 'state' => 'running', 'mh' => ['kind' => 'classified', 'date' => '1990-03-27', 'ref' => 'PM71000825'], 'src' => [$wp, ['palissy', 'PM71000825']]],
                    ['number' => '241 P 9', 'venue' => 'cite-du-train', 'state' => 'exposed', 'src' => [$wp]],
                    ['number' => '241 P 30', 'venue' => null, 'where' => 'Dépôt de X', 'src' => [$wp]],
                ],
                'cc-40100' => [['number' => 'CC 40101', 'venue' => 'cite-du-train', 'state' => 'exposed', 'src' => [['wikidata', 'Q1']]]],
            ],
            'others' => [],
        ]));
    }

    public function test_preserved_rolling_stock_lists_units_of_a_series_and_venues_with_sources(): void
    {
        $this->preserved();

        [$series, $error] = $this->tool('preserved_rolling_stock', ['material' => '241-p']);
        $this->assertFalse($error);
        $this->assertSame(3, $series['total']);
        $this->assertSame(['241 P 17', '241 P 9', '241 P 30'], array_column($series['units'], 'number'));
        $this->assertSame('classified', $series['units'][0]['protection']['kind']);
        $this->assertSame('https://www.pop.culture.gouv.fr/notice/palissy/PM71000825', $series['units'][0]['protection']['url']);
        $this->assertSame('https://vff-project.org/materials/241-p', $series['vff_url']);
        $this->assertNotEmpty($series['coverage_note']);
        $providers = array_column($series['attribution']['sources'], 'provider');
        $this->assertContains('wikipedia', $providers);
        $this->assertContains('palissy', $providers);

        [$protected] = $this->tool('preserved_rolling_stock', ['material' => '241-p', 'protected' => true]);
        $this->assertSame(['241 P 17'], array_column($protected['units'], 'number'));
        [$running] = $this->tool('preserved_rolling_stock', ['material' => '241-p', 'state' => 'running', 'venue_type' => 'tourist_railway']);
        $this->assertSame(['241 P 17'], array_column($running['units'], 'number'));

        [$venues] = $this->tool('preserved_rolling_stock', []);
        $this->assertSame(['cite-du-train', 'cfc'], array_column($venues['venues'], 'slug'));
        $this->assertSame(2, $venues['venues'][0]['count']);
        $this->assertSame([['slug' => '241-p', 'name' => '241 P']], $venues['venues'][0]['pieces'][0]['series']);
        $this->assertSame(4, $venues['figures']['units']);
        $this->assertSame('https://vff-project.org/materials/preserved', $venues['vff_url']);

        [$one] = $this->tool('preserved_rolling_stock', ['venue' => 'cite-du-train', 'state' => 'exposed', 'limit' => 1]);
        $this->assertSame(1, $one['total']);
        $this->assertSame(['241 P 9', 'CC 40101'], array_column($one['venues'][0]['pieces'], 'number'));

        [$museums] = $this->tool('preserved_rolling_stock', ['venue_type' => 'association']);
        $this->assertSame([], $museums['venues']);

        foreach ([['material' => 'inconnu'], ['material' => '241-p', 'venue' => 'cfc'], ['state' => 'scrapped'], ['venue_type' => 'zoo'], ['limit' => 0], ['x' => 1]] as $arguments) {
            [, $error] = $this->tool('preserved_rolling_stock', $arguments);
            $this->assertTrue($error, json_encode($arguments));
        }

        [$sheet] = $this->tool('get_material', ['material' => '241-p']);
        $this->assertSame(3, $sheet['preserved_count']);
    }

    public function test_station_events_filters_pages_hides_non_commercial_facts_and_follows_a_merged_station(): void
    {
        $station = Station::factory()->create(['slug' => 'gare-essai', 'name' => 'Gare d\'Essai']);
        $row = fn (string $type, string $date, string $source, bool $inferred = false, ?string $url = null): array => [
            'station_id' => $station->id, 'type' => $type, 'event_date' => $date, 'date_precision' => 'day', 'source' => $source,
            'source_ref' => null, 'source_url' => $url, 'is_inferred' => $inferred, 'note' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('station_events')->insert([
            $row('opening', '1865-04-26', 'wikidata', url: 'https://www.wikidata.org/wiki/Q1'),
            $row('closure_passenger', '1938-05-15', 'inferred:line_event', true),
            $row('reopening', '1950-01-01', 'wikipedia', url: 'http://archeoferroviaire.free.fr/page.html'),
            $row('demolition', '1972-06-01', 'osm'),
        ]);
        DB::table('station_redirects')->insert(['old_slug' => 'osm-1-gare-essai', 'station_id' => $station->id]);

        [$all, $error] = $this->tool('station_events', ['station' => 'gare-essai', 'lang' => 'en']);
        $this->assertFalse($error);
        // L'événement dont le lien vient de la source non commerciale n'est jamais servi.
        $this->assertSame(['opening', 'closure_passenger', 'demolition'], array_column($all['events'], 'type'));
        $this->assertStringNotContainsString('archeoferroviaire', (string) json_encode($all));
        $this->assertSame('26 April 1865', $all['events'][0]['when']);
        $this->assertSame('Opening', $all['events'][0]['label']);
        $this->assertSame('wikidata', $all['events'][0]['provenance']['provider']);
        $this->assertTrue($all['events'][1]['is_inferred']);
        $this->assertSame('https://vff-project.org/stations/gare-essai', $all['vff_url']);

        [$sourced] = $this->tool('station_events', ['station' => 'gare-essai', 'inferred' => false, 'order' => 'desc']);
        $this->assertSame(['demolition', 'opening'], array_column($sourced['events'], 'type'));

        [$period] = $this->tool('station_events', ['station' => 'gare-essai', 'from' => '1930', 'to' => '1939']);
        $this->assertSame(['closure_passenger'], array_column($period['events'], 'type'));

        [$first] = $this->tool('station_events', ['station' => 'gare-essai', 'limit' => 2]);
        $this->assertTrue($first['has_more']);
        [$next] = $this->tool('station_events', ['station' => 'gare-essai', 'limit' => 2, 'cursor' => $first['next_cursor']]);
        $this->assertSame(['demolition'], array_column($next['events'], 'type'));

        [$merged] = $this->tool('station_events', ['station' => 'osm-1-gare-essai']);
        $this->assertSame('gare-essai', $merged['station']['slug']);
        $this->assertSame('merged', $merged['redirected'][0]['reason']);
        $this->assertSame('osm-1-gare-essai', $merged['redirected'][0]['requested']);

        foreach ([['station' => 'inconnue'], [], ['station' => 'gare-essai', 'type' => 'electrification'], ['station' => 'gare-essai', 'from' => '30 juin'],
            ['station' => 'gare-essai', 'limit' => 101], ['station' => 'gare-essai', 'cursor' => 'abc']] as $arguments) {
            [, $error] = $this->tool('station_events', $arguments);
            $this->assertTrue($error, json_encode($arguments));
        }
    }

    public function test_an_old_address_answers_with_the_current_sheet_and_says_so(): void
    {
        $this->app->instance(LineSlugRedirects::class, new LineSlugRedirects(['ancien-slug-essai' => 'ligne-essai']));
        $line = Line::factory()->create(['slug' => 'ligne-essai', 'name' => 'Ligne d\'Essai', 'code' => null]);
        LineEvent::factory()->create(['line_id' => $line->id, 'type' => 'opening', 'event_date' => '1880-01-01', 'date_precision' => 'day',
            'source' => 'wikidata', 'source_url' => null, 'section_from' => null, 'section_to' => null]);

        [$sheet, $error] = $this->tool('get_line', ['line' => 'ancien-slug-essai']);
        $this->assertFalse($error);
        $this->assertSame('ligne-essai', $sheet['redirected'][0]['current']);
        $this->assertStringContainsString('ligne-essai', $sheet['redirected'][0]['note']);

        [$current] = $this->tool('get_line', ['line' => 'ligne-essai']);
        $this->assertArrayNotHasKey('redirected', $current, 'le slug actuel ne signale rien');

        // Filtre d'un outil de liste : même règle.
        [$events] = $this->tool('network_events', ['line' => 'ancien-slug-essai']);
        $this->assertSame('ligne-essai', $events['filters']['line']);
        $this->assertCount(1, $events['events']);
        $this->assertSame('line', $events['redirected'][0]['kind']);
    }
}
