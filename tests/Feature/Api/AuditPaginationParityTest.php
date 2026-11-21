<?php

namespace Tests\Feature\Api;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\AuditPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit des tronçons d'une ligne : la même page de tronçons (filtre `grade`, `limit` de 50 par défaut et
 * 200 au plus, `offset`, `segments_total`) dans l'API REST, GraphQL et le serveur MCP.
 */
class AuditPaginationParityTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    /** Identifiants des tronçons dans l'ordre de l'audit (le plus mal noté d'abord). */
    private array $order = [];

    protected function setUp(): void
    {
        parent::setUp();
        $era = Era::factory()->create(['year' => 2030, 'label' => 'Aujourd’hui', 'is_current' => true]);
        $this->line = Line::factory()->create(['slug' => 'rfn-654321', 'code' => '654321', 'name' => 'Ligne de Test à Page']);
        $this->row($era->id, null, 'line', 'poor', 90, 5000);
        // Cinq tronçons : deux faux (scores 90 et 80), un à revoir, deux sûrs.
        foreach ([[1101, 'poor', 90], [1102, 'poor', 80], [1103, 'fair', 40], [1104, 'good', 0], [1105, 'good', 0]] as [$id, $grade, $score]) {
            $this->row($era->id, $id, 'segment', $grade, $score, 1000);
            $this->order[] = $id;
        }
    }

    private function row(int $eraId, ?int $segmentId, string $scope, string $grade, int $score, int $length): void
    {
        DB::table('segment_audits')->insert([
            'era_id' => $eraId, 'line_id' => $this->line->id, 'segment_id' => $segmentId, 'scope' => $scope,
            'status' => 'in_service', 'traffic_type' => 'mixed', 'trace_source' => 'bdtopo', 'length_m' => $length,
            'classification_grade' => $grade, 'placement_grade' => $grade, 'accuracy_grade' => $grade, 'trace_grade' => $grade,
            'worst_grade' => $grade, 'score' => $score, 'evidence' => json_encode(['shares' => [], 'accuracy' => []]),
            'rule_version' => '2026-11-21.4', 'audited_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return list<int> */
    private function rest(string $query = ''): array
    {
        return array_column($this->getJson('/api/v1/lines/rfn-654321/audit'.$query)->assertOk()->json('segments'), 'segment_id');
    }

    /** @return list<int> */
    private function graphql(string $args = ''): array
    {
        $segments = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-654321") { audit { segments'.$args.' { segment_id } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.line.audit.segments');

        return array_map('intval', array_column($segments, 'segment_id'));
    }

    /** @return array<string, mixed> */
    private function mcp(array $arguments): array
    {
        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_audit', 'arguments' => ['line' => 'rfn-654321'] + $arguments]])
            ->assertOk()->json('result');
        $this->assertFalse($result['isError'], $result['content'][0]['text'] ?? '');

        return json_decode($result['content'][0]['text'], true);
    }

    public function test_the_three_apis_serve_the_same_page(): void
    {
        $this->assertSame($this->order, $this->rest());
        $this->assertSame($this->order, $this->graphql());
        $this->assertSame($this->order, array_column($this->mcp([])['segments'], 'segment_id'));

        // Page de deux tronçons à partir du rang 1.
        $expected = [1102, 1103];
        $this->assertSame($expected, $this->rest('?limit=2&offset=1'));
        $this->assertSame($expected, $this->graphql('(limit: 2, offset: 1)'));
        $mcp = $this->mcp(['limit' => 2, 'offset' => 1]);
        $this->assertSame($expected, array_column($mcp['segments'], 'segment_id'));
        $this->assertSame(5, $mcp['segments_total']);
        $this->assertSame(2, $mcp['limit']);
        $this->assertSame(1, $mcp['offset']);
    }

    public function test_the_grade_filter_and_the_total_agree(): void
    {
        $this->assertSame([1101, 1102], $this->rest('?grade=poor'));
        $this->assertSame([1101, 1102], $this->graphql('(grade: POOR)'));
        $this->assertSame([1101, 1102], array_column($this->mcp(['grade' => 'poor'])['segments'], 'segment_id'));

        $this->getJson('/api/v1/lines/rfn-654321/audit?grade=good')->assertJsonPath('segments_total', 2);
        $this->assertSame(2, $this->mcp(['grade' => 'good'])['segments_total']);
        $totals = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-654321") { audit { all: segments_total poor: segments_total(grade: POOR) fair: segments_total(grade: FAIR) unknown: segments_total(grade: UNKNOWN) } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.line.audit');
        $this->assertSame(['all' => 5, 'poor' => 2, 'fair' => 1, 'unknown' => 0], $totals);

        // La synthèse de la ligne porte toujours sur tous les tronçons, quelle que soit la page.
        $this->getJson('/api/v1/lines/rfn-654321/audit?limit=1')->assertJsonPath('worst_grade', 'poor')->assertJsonPath('length_m', 5000);
    }

    public function test_the_page_size_limits_are_the_same_everywhere(): void
    {
        $this->assertSame(50, AuditPage::DEFAULT_LIMIT);
        $this->assertSame(200, AuditPage::MAX_LIMIT);
        $this->getJson('/api/v1/lines/rfn-654321/audit')->assertJsonPath('limit', 50);
        $this->assertSame(50, $this->mcp([])['limit']);

        $this->getJson('/api/v1/lines/rfn-654321/audit?limit=201')->assertUnprocessable();
        foreach (['limit: 201', 'limit: 0', 'offset: -1'] as $args) {
            $errors = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-654321") { audit { segments('.$args.') { segment_id } } } }'])
                ->assertOk()->json('errors');
            $this->assertNotEmpty($errors, $args);
            $this->assertArrayHasKey('validation', $errors[0]['extensions'], $args);
        }

        $refused = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_audit', 'arguments' => ['line' => 'rfn-654321', 'limit' => 201]]])
            ->assertOk()->json('result');
        $this->assertTrue($refused['isError'] ?? false);
    }

    public function test_the_page_helper_clamps_what_it_is_given(): void
    {
        $audit = ['line' => [], 'segments' => [['worst_grade' => 'good'], ['worst_grade' => 'poor']]];
        $page = AuditPage::apply($audit, null, 1000, -5);
        $this->assertSame(200, $page['limit']);
        $this->assertSame(0, $page['offset']);
        $this->assertSame(2, $page['segments_total']);
        $this->assertSame([['worst_grade' => 'poor']], AuditPage::apply($audit, 'poor', null, null)['segments']);
    }
}
