<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\NetworkAudits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * NetworkAudits en direct (sans passer par REST, GraphQL ni MCP) : bornes de page, règles de validation
 * partagées, filtres de texte et normalisation des filtres des gares.
 */
class NetworkAuditsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Era $now;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Era::factory()->create(['year' => 2030, 'label' => 'Aujourd’hui', 'is_current' => true]);
        $this->line = Line::factory()->create(['slug' => 'rfn-111111', 'code' => '111111', 'name' => 'Ligne de 100% Étoile']);
        $other = Line::factory()->create(['slug' => 'rfn-222222', 'code' => '222222', 'name' => 'Ligne de Brest']);
        foreach ([[1, $this->line, 10, 0], [2, $this->line, 20, 50], [3, $other, 30, 90]] as [$id, $line, $length, $score]) {
            DB::table('segment_audits')->insert([
                'era_id' => $this->now->id, 'line_id' => $line->id, 'segment_id' => $id, 'scope' => 'segment', 'status' => 'in_service',
                'traffic_type' => 'mixed', 'trace_source' => 'bdtopo', 'length_m' => $length, 'classification_grade' => 'good',
                'placement_grade' => 'good', 'accuracy_grade' => 'good', 'trace_grade' => 'good', 'worst_grade' => 'good', 'score' => $score,
                'evidence' => '{}', 'rule_version' => 'x', 'audited_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function audits(): NetworkAudits
    {
        return app(NetworkAudits::class);
    }

    /** @return array<string, array{?int, ?int, int, int}> */
    public static function bounds(): array
    {
        return [
            'défaut' => [null, null, 50, 0],
            'zéro devient un' => [0, 0, 1, 0],
            'négatif devient un' => [-4, -9, 1, 0],
            'au plus 200' => [999, 7, 200, 7],
            'valeur ordinaire' => [2, 1, 2, 1],
        ];
    }

    #[DataProvider('bounds')]
    public function test_limit_and_offset_are_clamped(?int $limit, ?int $offset, int $expectedLimit, int $expectedOffset): void
    {
        $page = $this->audits()->segments([], $limit, $offset);

        $this->assertSame([$expectedLimit, $expectedOffset], [$page['limit'], $page['offset']]);
        $this->assertSame(3, $page['total']);
        $this->assertSame(NetworkAudits::DEFAULT_LIMIT, 50);
        $this->assertSame(NetworkAudits::MAX_LIMIT, 200);
    }

    public function test_an_era_without_audit_gives_an_empty_page_that_says_the_year(): void
    {
        $this->assertSame(['era_year' => 1850, 'total' => 0, 'limit' => 50, 'offset' => 0, 'items' => []], $this->audits()->segments(['era' => 1850]));
    }

    public function test_the_era_defaults_to_2030_and_unreadable_evidence_gives_empty_reasons(): void
    {
        $page = $this->audits()->segments([]);

        $this->assertSame(2030, $page['era_year']);
        $this->assertSame([3, 2, 1], array_column($page['items'], 'segment_id'));
        $this->assertSame(['classification' => [], 'placement' => [], 'accuracy' => [], 'trace' => []], $page['items'][0]['reasons']);
        $this->assertNotNull($page['items'][0]['audited_at']);
    }

    public function test_a_percent_sign_in_q_is_searched_literally_and_a_blank_line_is_ignored(): void
    {
        $this->assertSame([2, 1], array_column($this->audits()->segments(['q' => '100%'])['items'], 'segment_id'));
        $this->assertSame(0, $this->audits()->segments(['q' => '%zzz'])['total']);
        $this->assertSame(3, $this->audits()->segments(['line' => '  ', 'q' => ' '])['total']);
        $this->assertSame(2, $this->audits()->segments(['line' => ' rfn-111111 '])['total']);
    }

    public function test_an_unknown_check_filters_the_worst_grade_and_unknown_sort_falls_back_to_worst(): void
    {
        $page = $this->audits()->segments(['check' => 'nope', 'grade' => 'good', 'sort' => 'nope']);

        $this->assertSame(3, $page['total']);
        $this->assertSame([3, 2, 1], array_column($page['items'], 'segment_id'));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function badSegmentFilters(): array
    {
        return [
            'ère trop ancienne' => [['era' => 1829], 'era'],
            'ère trop récente' => [['era' => 2031], 'era'],
            'note inconnue' => [['grade' => 'great'], 'grade'],
            'contrôle inconnu' => [['check' => 'speed'], 'check'],
            'raison en majuscules' => [['reason' => 'On-Other'], 'reason'],
            'raison trop longue' => [['reason' => str_repeat('a', 61)], 'reason'],
            'état inconnu' => [['status' => 'flying'], 'status'],
            'département inconnu' => [['department' => '99'], 'department'],
            'région inconnue' => [['region' => '00'], 'region'],
            'ligne trop longue' => [['line' => str_repeat('a', 121)], 'line'],
            'tri inconnu' => [['sort' => 'random'], 'sort'],
            'limite 0' => [['limit' => 0], 'limit'],
            'limite 201' => [['limit' => 201], 'limit'],
            'décalage négatif' => [['offset' => -1], 'offset'],
        ];
    }

    /** @param  array<string, mixed>  $input */
    #[DataProvider('badSegmentFilters')]
    public function test_segment_rules_reject_bad_filters(array $input, string $field): void
    {
        $validator = Validator::make($input, NetworkAudits::segmentRules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    public function test_segment_and_station_rules_accept_a_full_valid_set_and_an_empty_one(): void
    {
        $this->assertFalse(Validator::make([], NetworkAudits::segmentRules())->fails());
        $this->assertFalse(Validator::make([], NetworkAudits::stationRules())->fails());
        $this->assertFalse(Validator::make(['era' => 2030, 'grade' => 'poor', 'check' => 'placement', 'reason' => 'on_other_line_track',
            'status' => 'abandoned', 'department' => '2A', 'region' => '28', 'line' => 'rfn-405000', 'q' => 'Argentan', 'sort' => 'length',
            'limit' => 200, 'offset' => 0], NetworkAudits::segmentRules())->fails());
        $this->assertTrue(Validator::make(['grade' => 'good'], NetworkAudits::stationRules())->fails(), 'good n’est pas une note de placement');
        $this->assertTrue(Validator::make(['sort' => 'worst'], NetworkAudits::stationRules())->fails());
        $this->assertTrue(Validator::make(['limit' => 201], NetworkAudits::stationRules())->fails());
    }

    public function test_station_filters_are_normalised_before_the_query(): void
    {
        $page = $this->audits()->stations(['department' => '2a', 'line' => '  ', 'q' => '  '], 500, -3);

        $this->assertSame([200, 0, 0], [$page['limit'], $page['offset'], $page['total']]);
        $this->assertSame([], $page['items']);
    }
}
