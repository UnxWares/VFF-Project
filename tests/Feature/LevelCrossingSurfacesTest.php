<?php

namespace Tests\Feature;

use App\Models\Era;
use App\Models\Line;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\Support\SeedsLevelCrossings;
use Tests\TestCase;

/**
 * Passages à niveau hors de l'API REST : section de la fiche ligne (propriété `level_crossings`),
 * champs et requête GraphQL, outil MCP `level_crossings` (attribution SNCF Réseau sous ODbL).
 */
class LevelCrossingSurfacesTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;
    use SeedsLevelCrossings;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Era::factory()->current()->create(['year' => 2030]);
        Line::factory()->create(['slug' => 'rfn-70000', 'code' => '070000', 'name' => 'Ligne de Noisy-le-Sec à Strasbourg-Ville']);
        $this->seedLevelCrossing('30000010436', '070000', 112866, 49.062227, 3.602119, 'CLASSE 17');
        $this->seedLevelCrossing('30000010435', '070000', 112190, 49.059126, 3.594152, 'CLASSE 11', ['commune' => 'Crézancy']);
        $this->seedLevelCrossing('30000020001', '001000', 2100, 50.50, 3.00, 'CLASSE 32');
    }

    public function test_line_sheet_has_the_counts_and_the_list_in_pk_order(): void
    {
        $this->getWithDeferredProps('/lines/rfn-70000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Show')
                ->where('level_crossings.total', 2)
                ->where('level_crossings.summary', [['group' => 'automatic', 'count' => 1], ['group' => 'guarded', 'count' => 1]])
                ->where('level_crossings.items.0', [
                    'id' => '30000010435', 'label' => 'PN 35', 'class' => 11, 'group' => 'guarded',
                    'pk' => '112+190', 'commune' => 'Crézancy', 'crossed_way' => 'VC',
                ])
                ->where('level_crossings.items.1.id', '30000010436'));
    }

    public function test_graphql_line_field_summary_and_filtered_query(): void
    {
        $this->graphQL('{ line(slug: "rfn-70000") {
                level_crossing_summary { group count }
                level_crossings { id label class class_label(lang: "en") group pk_m pk_label commune crossed_way line_code lat lng provenance { provider } }
            }
            levelCrossings(filter: { group: "pedestrian" }, first: 10) { data { id line_code line { slug } } paginatorInfo { total } }
        }')
            ->assertJsonPath('data.line.level_crossing_summary', [['group' => 'automatic', 'count' => 1], ['group' => 'guarded', 'count' => 1]])
            ->assertJsonPath('data.line.level_crossings.1', [
                'id' => '30000010436', 'label' => 'PN 36', 'class' => 17,
                'class_label' => 'Public road crossing, automatic barriers or half barriers, SAL 2 or SAL 2B warning lights',
                'group' => 'automatic', 'pk_m' => 112866, 'pk_label' => '112+866', 'commune' => 'Courthiézy', 'crossed_way' => 'VC',
                'line_code' => '070000', 'lat' => 49.062227, 'lng' => 3.602119, 'provenance' => ['provider' => 'sncf'],
            ])
            ->assertJsonPath('data.levelCrossings.paginatorInfo.total', 1)
            ->assertJsonPath('data.levelCrossings.data.0', ['id' => '30000020001', 'line_code' => '001000', 'line' => null]);

        $this->graphQL('{ levelCrossings(filter: { group: "nope" }) { data { id } } }')
            ->assertGraphQLValidationKeys(['group']);
    }

    public function test_graphql_reads_a_line_crossings_once_for_both_fields(): void
    {
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            $queries += str_contains($query->sql, 'from "sncf_level_crossings"') ? 1 : 0;
        });

        $this->graphQL('{ line(slug: "rfn-70000") { level_crossing_summary { group count } level_crossings { id line { slug } } } }')
            ->assertJsonPath('data.line.level_crossings.0.line.slug', 'rfn-70000')
            ->assertJsonCount(2, 'data.line.level_crossings');

        $this->assertSame(1, $queries);
    }

    public function test_graphql_and_mcp_give_no_label_for_an_unknown_class(): void
    {
        $this->seedLevelCrossing('30000030001', '070000', 200000, 49.1, 3.7, 'CLASSE 50');

        $this->graphQL('{ line(slug: "rfn-70000") { level_crossings { id class class_label group } } }')
            ->assertJsonPath('data.line.level_crossings.2', ['id' => '30000030001', 'class' => 50, 'class_label' => null, 'group' => null]);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'level_crossings', 'arguments' => ['line' => 'rfn-70000', 'lang' => 'de'],
            ]])->assertOk()->json('result');
        $this->assertFalse($result['isError']);
        $items = array_column(json_decode($result['content'][0]['text'], true)['level_crossings'], null, 'id');
        $unknown = $items['30000030001'];

        $this->assertSame(50, $unknown['class']);
        $this->assertNull($unknown['class_label']);
        $this->assertNull($unknown['group_label']);
    }

    public function test_line_sheet_lists_an_unknown_class_without_group(): void
    {
        $this->seedLevelCrossing('30000030001', '070000', 200000, 49.1, 3.7, 'CLASSE 50');

        $this->getWithDeferredProps('/lines/rfn-70000')->assertInertia(fn (Assert $page) => $page
            ->where('level_crossings.total', 3)
            ->where('level_crossings.items.2', [
                'id' => '30000030001', 'label' => 'PN 01', 'class' => 50, 'group' => null,
                'pk' => '200+000', 'commune' => 'Courthiézy', 'crossed_way' => 'VC',
            ]));
    }

    public function test_mcp_tool_lists_crossings_with_summary_and_sncf_attribution(): void
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'level_crossings', 'arguments' => ['line' => 'rfn-70000', 'lang' => 'de', 'limit' => 1],
            ]])
            ->assertOk()->json('result');
        $data = json_decode($result['content'][0]['text'], true);

        $this->assertFalse($result['isError']);
        $this->assertSame(1, $data['count']);
        $this->assertTrue($data['has_more']);
        $this->assertSame('30000010435', $data['level_crossings'][0]['id']);
        $this->assertSame('Bewacht', $data['level_crossings'][0]['group_label']);
        $this->assertSame([['group' => 'automatic', 'count' => 1, 'label' => 'Automatische Schranken'], ['group' => 'guarded', 'count' => 1, 'label' => 'Bewacht']], $data['summary']);
        $this->assertStringEndsWith('/lines/rfn-70000#level-crossings', $data['vff_url']);
        $this->assertContains('sncf', array_column($data['attribution']['sources'], 'provider'));

        $next = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => [
                'name' => 'level_crossings', 'arguments' => ['line' => 'rfn-70000', 'limit' => 1, 'cursor' => $data['next_cursor']],
            ]])
            ->assertOk()->json('result.content.0.text');
        $this->assertSame('30000010436', json_decode($next, true)['level_crossings'][0]['id']);
    }
}
