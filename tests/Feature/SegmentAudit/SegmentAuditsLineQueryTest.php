<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\SegmentAudits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `SegmentAudits::lineQuery` : filtres `status` (ligne qui a au moins un tronçon dans cet état) et `q`
 * (nom sans casse, code ou slug exacts), avec les filtres de note et de région déjà couverts ailleurs.
 */
class SegmentAuditsLineQueryTest extends TestCase
{
    use RefreshDatabase;

    private int $eraId;

    /** @var array<string, int> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->eraId = Era::factory()->create(['year' => 2030, 'label' => 'Aujourd’hui', 'is_current' => true])->id;
        $other = Era::factory()->create(['year' => 1930, 'label' => '1930'])->id;
        $this->lines['flers'] = $this->line('rfn-111111', '111111', 'Ligne de Flers à Condé', 'in_service', 'poor', 60);
        $this->lines['brest'] = $this->line('rfn-222222', '222222', 'Ligne de Brest à Quimper', 'dismantled', 'good', 10);
        $this->lines['caen'] = $this->line('rfn-333333', '333333', 'Ligne de Caen à Cherbourg', 'abandoned', 'fair', 30);
        // Un tronçon déposé d'une autre ère ne compte pas pour la ligne de Flers à l'ère 2030.
        $this->segment($other, $this->lines['flers'], 9, 'dismantled', 'poor');
    }

    private function line(string $slug, string $code, string $name, string $status, string $grade, int $score): int
    {
        $id = Line::factory()->create(['slug' => $slug, 'code' => $code, 'name' => $name])->id;
        $this->row($this->eraId, $id, null, 'line', 'in_service', $grade, $score);
        $this->segment($this->eraId, $id, $id * 10, $status, $grade, $score);

        return $id;
    }

    private function segment(int $eraId, int $lineId, int $segmentId, string $status, string $grade, int $score = 50): void
    {
        $this->row($eraId, $lineId, $segmentId, 'segment', $status, $grade, $score);
    }

    private function row(int $eraId, int $lineId, ?int $segmentId, string $scope, string $status, string $grade, int $score): void
    {
        DB::table('segment_audits')->insert([
            'era_id' => $eraId, 'line_id' => $lineId, 'segment_id' => $segmentId, 'scope' => $scope, 'status' => $status, 'traffic_type' => 'mixed',
            'trace_source' => 'bdtopo', 'length_m' => 1000, 'classification_grade' => $grade, 'placement_grade' => $grade, 'accuracy_grade' => $grade,
            'trace_grade' => $grade, 'worst_grade' => $grade, 'score' => $score, 'evidence' => json_encode(['segments' => 1]),
            'rule_version' => '2026-11-21.5', 'audited_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return list<string> slugs, dans l'ordre de la liste */
    private function slugs(array $filters): array
    {
        return app(SegmentAudits::class)->lineQuery($this->eraId, $filters)->pluck('l.slug')->all();
    }

    public function test_without_filter_the_worst_line_comes_first(): void
    {
        $this->assertSame(['rfn-111111', 'rfn-333333', 'rfn-222222'], $this->slugs([]));
    }

    public function test_the_status_filter_keeps_lines_with_a_segment_in_that_state(): void
    {
        $this->assertSame(['rfn-222222'], $this->slugs(['status' => 'dismantled']));
        $this->assertSame(['rfn-333333'], $this->slugs(['status' => 'abandoned']));
        $this->assertSame(['rfn-111111'], $this->slugs(['status' => 'in_service']));
        $this->assertSame([], $this->slugs(['status' => 'razed']));
        // Le tronçon déposé de Flers est d'une autre ère : il ne compte pas.
        $this->assertNotContains('rfn-111111', $this->slugs(['status' => 'dismantled']));
    }

    public function test_the_text_filter_matches_the_name_ignoring_case_and_the_code_or_slug_exactly(): void
    {
        $this->assertSame(['rfn-222222'], $this->slugs(['q' => 'BREST']));
        $this->assertSame(['rfn-111111', 'rfn-333333', 'rfn-222222'], $this->slugs(['q' => 'ligne de']));
        $this->assertSame(['rfn-333333'], $this->slugs(['q' => '333333']));
        $this->assertSame(['rfn-111111'], $this->slugs(['q' => 'rfn-111111']));
        $this->assertSame([], $this->slugs(['q' => '3333']), 'un code partiel ne correspond pas');
        $this->assertSame([], $this->slugs(['q' => 'introuvable']));
        $this->assertSame(['rfn-111111', 'rfn-333333', 'rfn-222222'], $this->slugs(['q' => '   ']), 'un texte vide ne filtre pas');
    }

    public function test_wildcards_in_the_text_are_taken_literally(): void
    {
        $this->assertSame([], $this->slugs(['q' => '%']));
        $this->assertSame([], $this->slugs(['q' => 'Flers_Condé']));
    }

    public function test_filters_combine(): void
    {
        $this->assertSame(['rfn-333333'], $this->slugs(['status' => 'abandoned', 'q' => 'caen']));
        $this->assertSame([], $this->slugs(['status' => 'abandoned', 'q' => 'brest']));
        $this->assertSame(['rfn-333333'], $this->slugs(['status' => 'abandoned', 'grade' => 'fair', 'check' => 'placement']));
        $this->assertSame([], $this->slugs(['status' => 'abandoned', 'grade' => 'poor']));
    }
}
