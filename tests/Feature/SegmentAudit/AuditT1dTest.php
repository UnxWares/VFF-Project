<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\SegmentAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Règle T1d (règles 2026-11-21.7) mesurée en base sur un réseau d'essai, puis servie par les trois API :
 * un tronçon en service posé sur la voie BD TOPO d'une autre ligne, sa propre voie BD TOPO à 20 m au nord
 * (double voie dont chaque voie est rattachée à une ligne différente), garde un placement « sûr » avec la
 * raison `on_other_line_track` ; fermé ou à une ère passée, il reste « faux ».
 *
 * Ligne auditée L (code 654350) et ligne voisine N (654351) ; tronçons est-ouest sur 48° N entre
 * 2,00° et 2,01° E. 0,00018° de latitude vaut 20 m.
 */
class AuditT1dTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const AS_OF = '2026-11-21';

    private const TRACK = 'LINESTRING(2.0 48.0, 2.01 48.0)';

    private const BESIDE = 'LINESTRING(2.0 48.00018, 2.01 48.00018)';

    private Era $past;

    private Era $current;

    private Line $audited;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 2000, 'label' => 'Années 2000']);
        $this->current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
        $this->audited = Line::factory()->create(['slug' => 'rfn-654350', 'code' => '654350', 'name' => 'Ligne de Papa', 'is_lgv' => false, 'network' => 'principal']);
        $neighbour = Line::factory()->create(['slug' => 'rfn-654351', 'code' => '654351', 'name' => 'Ligne de Québec', 'is_lgv' => false, 'network' => 'principal']);
        $this->auditTrack('bdtopo', self::TRACK, $neighbour->id);
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
    }

    /** @return array{0: string, 1: list<string>} */
    private static function grade(array $row, string $check): array
    {
        return [$row[$check.'_grade'], $row['evidence'][$check]['reasons']];
    }

    /** @return array<string, mixed> */
    private function audit(int $segment, Era $era): array
    {
        return self::auditedSegment(app(SegmentAuditor::class)->auditLine($this->audited->id, [$era->year], self::AS_OF), $era->id, $segment);
    }

    public function test_a_segment_in_service_on_another_line_track_is_good_with_the_reason(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'sncf');

        $row = $this->audit($segment, $this->current);

        $this->assertSame(['good', ['on_other_line_track']], self::grade($row, 'placement'));
        $this->assertEqualsWithDelta(20.0, $row['evidence']['placement']['median_m'], 0.5);
        // Le tracé SNCF n'est pas « recalé » par une voie qui n'est pas la sienne.
        $this->assertSame(['poor', ['precise_trace_available']], self::grade($row, 'trace'));
    }

    public function test_a_closed_segment_or_a_past_era_stays_poor(): void
    {
        $closed = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'mixed', 'sncf');
        $this->assertSame(['poor', ['on_other_line_track']], self::grade($this->audit($closed, $this->current), 'placement'));

        $past = $this->auditSegment($this->audited, $this->past, self::TRACK, 'in_service', 'mixed', 'sncf');
        $this->assertSame(['poor', ['on_other_line_track']], self::grade($this->audit($past, $this->past), 'placement'));
    }

    public function test_the_apis_show_the_reason_on_a_good_placement(): void
    {
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'ohm');
        $auditor = app(SegmentAuditor::class);
        $auditor->write($this->audited->id, $auditor->auditLine($this->audited->id, [$this->current->year], self::AS_OF));

        $rest = $this->getJson('/api/v1/lines/rfn-654350/audit')->assertOk()
            ->assertJsonPath('rule_version', '2026-11-21.7')->json('segments');
        $this->assertSame([$segment], array_column($rest, 'segment_id'));
        $this->assertSame('good', $rest[0]['grades']['placement']);
        $this->assertSame(['on_other_line_track'], $rest[0]['evidence']['placement']['reasons']);

        $graphql = $this->postJson('/graphql', ['query' => '{ line(slug: "rfn-654350") { audit { segments { segment_id grades { placement } evidence } } } }'])
            ->assertOk()->assertJsonMissingPath('errors')->json('data.line.audit.segments.0');
        $this->assertSame('GOOD', $graphql['grades']['placement']);
        $this->assertSame(['on_other_line_track'], $graphql['evidence']['placement']['reasons']);

        $result = $this->withHeaders(['Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'line_audit', 'arguments' => ['line' => 'rfn-654350']]])
            ->assertOk()->json('result');
        $this->assertFalse($result['isError'], $result['content'][0]['text'] ?? '');
        $mcp = json_decode($result['content'][0]['text'], true)['segments'][0];
        $this->assertSame('good', $mcp['grades']['placement']);
        $this->assertSame(['on_other_line_track'], $mcp['evidence']['placement']['reasons']);

        // Les trois langues ont un texte pour la raison.
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->assertNotSame('audit_segments.reasons.on_other_line_track', __('audit_segments.reasons.on_other_line_track', [], $locale), $locale);
        }
    }
}
