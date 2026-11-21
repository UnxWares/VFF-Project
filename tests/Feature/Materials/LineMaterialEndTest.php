<?php

namespace Tests\Feature\Materials;

use App\Models\Line;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Services\Materials\LineMaterialEnd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fin de circulation d'une série sur une ligne (LineMaterialEnd) et ordre de la section « Matériel roulant »
 * (le plus récent d'abord), identique sur la page, dans l'API REST, GraphQL et le serveur MCP.
 */
class LineMaterialEndTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{start_date: ?string, end_date: ?string, source: string, source_url: string} */
    private static function period(?string $start, ?string $end): array
    {
        return ['start_date' => $start, 'end_date' => $end, 'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?oldid=1'];
    }

    public function test_the_end_comes_from_the_line_then_the_series_and_is_never_invented(): void
    {
        $withdrawn = new Material(['in_service_to' => 2019, 'provenance' => ['in_service_to' => ['source' => 'wikidata', 'ref' => 'Q1#P730']]]);
        $running = new Material(['service_status' => 'in_service', 'provenance' => ['service_status' => ['source' => 'wikipedia', 'ref' => 'Régiolis#infobox:Effectif', 'revid' => 42]]]);

        $line = LineMaterialEnd::of($withdrawn, [self::period('1990-01-01', '2009-01-01'), self::period(null, '2004-01-01')]);
        $this->assertSame(['line', 2009, 'wikipedia'], [$line['kind'], $line['year'], $line['source']]);

        $series = LineMaterialEnd::of($withdrawn, [self::period('1990-01-01', null)]);
        $this->assertSame(['series_withdrawn', 2019, 'https://www.wikidata.org/wiki/Q1#P730'], [$series['kind'], $series['year'], $series['source_url']],
            'aucune fin sur la ligne : celle de la série, dite comme telle');

        $back = LineMaterialEnd::of($withdrawn, [self::period('1970-01-01', '1980-01-01'), self::period('1995-01-01', null)]);
        $this->assertSame('series_withdrawn', $back['kind'], 'la série est revenue sur la ligne après 1980 : 1980 n\'est pas sa fin');

        $inService = LineMaterialEnd::of($running, []);
        $this->assertSame(['series_in_service', null], [$inService['kind'], $inService['year']]);
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=R%C3%A9giolis&oldid=42', $inService['source_url']);

        $this->assertSame('series_withdrawn', LineMaterialEnd::of(new Material(['service_status' => 'withdrawn']), [])['kind']);
        $this->assertNull(LineMaterialEnd::of(new Material(['service_status' => 'withdrawn']), [])['year']);
        $this->assertSame(['kind' => 'unknown', 'year' => null, 'source' => null, 'source_url' => null], LineMaterialEnd::of(new Material, []));
    }

    public function test_the_most_recent_comes_first(): void
    {
        $row = fn (string $name, string $kind, ?int $year, ?int $from = null, array $periods = []): array => [
            'name' => $name, 'in_service_from' => $from, 'periods' => $periods, 'end' => ['kind' => $kind, 'year' => $year],
        ];
        $sorted = LineMaterialEnd::sort([
            $row('Inconnue', 'unknown', null),
            $row('X 2800', 'line', 1990),
            $row('A1AA1A 68000', 'series_withdrawn', 2010),
            $row('Régiolis', 'series_in_service', null, 2013),
            $row('X 72500', 'line', 2019),
            $row('BB 67000', 'line', 2019, 1965, [self::period('1970-01-01', '2019-01-01')]),
            $row('X 76500', 'series_in_service', null, 2004),
        ]);

        $this->assertSame(['Régiolis', 'X 76500', 'BB 67000', 'X 72500', 'A1AA1A 68000', 'X 2800', 'Inconnue'], array_column($sorted, 'name'));
    }

    public function test_the_same_order_and_end_are_served_by_the_page_and_the_apis(): void
    {
        $line = Line::factory()->create(['code' => '405000', 'slug' => 'rfn-405000']);
        $old = Material::factory()->create(['slug' => 'x-2800', 'name' => 'X 2800', 'in_service_from' => 1957, 'in_service_to' => 1993, 'service_status' => null]);
        $new = Material::factory()->create(['slug' => 'regiolis', 'name' => 'Régiolis', 'in_service_from' => 2013, 'in_service_to' => null, 'service_status' => 'in_service']);
        $mid = Material::factory()->create(['slug' => 'x-72500', 'name' => 'X 72500', 'in_service_from' => 1997, 'in_service_to' => null, 'service_status' => null]);
        foreach ([$old, $new, $mid] as $material) {
            $link = LineMaterial::query()->create(['line_id' => $line->id, 'material_id' => $material->id, 'source' => 'wikipedia', 'confidence' => 'medium', 'evidence' => []]);
            if ($material->is($mid)) {
                LineMaterialPeriod::query()->create([
                    'line_material_id' => $link->id, 'start_date' => '1997-01-01', 'start_precision' => 'year', 'end_date' => '2019-01-01', 'end_precision' => 'year',
                    'confidence' => 'medium', 'origin' => 'material_article', 'source' => 'wikipedia', 'fingerprint' => sha1('x72500'),
                    'source_url' => 'https://fr.wikipedia.org/w/index.php?title=X_72500&oldid=7',
                ]);
            }
        }
        $order = ['Régiolis', 'X 72500', 'X 2800'];

        $this->withCookie('locale', 'fr')->get('/lines/rfn-405000')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('materials.0.name', 'Régiolis')
                ->where('materials.0.end.kind', 'series_in_service')
                ->where('materials.1.end.year', 2019)
                ->where('materials.1.end.kind', 'line')
                ->where('materials.2.end.kind', 'series_withdrawn')
                ->where('materials.2.end.year', 1993)
                ->etc());
        $this->assertSame($order, array_column($this->getJson('/api/v1/lines/rfn-405000')->assertOk()->json('data.materials'), 'name'));
        $rest = $this->getJson('/api/v1/lines/rfn-405000/materials')->assertOk()->json('data');
        $this->assertSame($order, array_column(array_column($rest, 'material'), 'name'));
        $this->assertSame(['series_in_service', 'line', 'series_withdrawn'], array_column(array_column($rest, 'end'), 'kind'));
        $graphql = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-405000") { sheet { materials { name end { kind year source_url } } } } }'])->assertOk()->json('data.line.sheet.materials');
        $this->assertSame($order, array_column($graphql, 'name'));
        $this->assertSame('https://fr.wikipedia.org/w/index.php?title=X_72500&oldid=7', $graphql[1]['end']['source_url']);
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_materials', 'arguments' => ['line' => 'rfn-405000']]])
            ->assertOk()->json('result');
        $mcp = json_decode($result['content'][0]['text'], true)['materials'];
        $this->assertSame($order, array_column(array_column($mcp, 'material'), 'name'));
        $this->assertSame([null, 2019, 1993], array_column(array_column($mcp, 'end'), 'year'));
    }
}
