<?php

namespace Tests\Unit\SegmentAudit;

use App\Enums\AuditGrade;
use App\Services\SegmentAudit\AuditGrader;
use PHPUnit\Framework\TestCase;

/**
 * Cas qui tuent les mutants survivants d'AuditGrader (tests de mutation, 21/11/2026) : clés absentes
 * des avis, placement posé sur la voie d'une autre ligne (arrondis, seuils de couverture, médiane
 * et 95e centile à la limite), source du tracé seule, listes d'identifiants.
 */
class AuditGraderSurvivorsTest extends TestCase
{
    /** @return array{points: int, found: int, median_m: ?float, p95_m: ?float} */
    private static function ref(float $median, float $p95, int $found = 10, int $points = 10): array
    {
        return ['points' => $points, 'found' => $found, 'median_m' => $median, 'p95_m' => $p95];
    }

    public function test_verdict_without_optional_keys_is_strong(): void
    {
        // Sans clé « weak » ni « near_date » : un avis d'accord est fort, un avis contraire aussi.
        $this->assertSame(['grade' => AuditGrade::Good, 'reasons' => ['sources_agree']],
            AuditGrader::classification([['source' => 'x', 'agrees' => true]]));
        $this->assertSame(['grade' => AuditGrade::Poor, 'reasons' => ['source_contradicts']],
            AuditGrader::classification([['source' => 'x', 'agrees' => false]]));
        $this->assertSame(AuditGrade::Poor,
            AuditGrader::classification([['source' => 'x', 'agrees' => false, 'weak' => false]])['grade']);
        $this->assertSame(AuditGrade::Poor,
            AuditGrader::classification([['source' => 'x', 'agrees' => false, 'near_date' => false]])['grade']);
    }

    public function test_missing_priority_counts_as_fifty_on_both_sides(): void
    {
        $this->assertSame(AuditGrade::Fair, AuditGrader::classification([
            ['source' => 'a', 'agrees' => true],
            ['source' => 'b', 'agrees' => false, 'priority' => 50],
        ])['grade']);
        $this->assertSame(AuditGrade::Fair, AuditGrader::classification([
            ['source' => 'a', 'agrees' => true, 'priority' => 50],
            ['source' => 'b', 'agrees' => false],
        ])['grade']);
    }

    public function test_weak_and_near_date_contradiction_of_a_better_source_is_soft(): void
    {
        $this->assertSame(['grade' => AuditGrade::Fair, 'reasons' => ['weak_contradiction']],
            AuditGrader::classification([
                ['source' => 'a', 'agrees' => true, 'priority' => 5],
                ['source' => 'b', 'agrees' => false, 'weak' => true, 'near_date' => true, 'priority' => 3],
            ]));
    }

    public function test_reason_lists_are_lists(): void
    {
        $result = AuditGrader::classification([['source' => 'x', 'agrees' => true]], ['track_unconfirmed', 'passenger_only_off_lgv']);
        $this->assertSame(['rule_passenger_only_off_lgv', 'rule_track_unconfirmed'], $result['reasons']);
        $this->assertTrue(array_is_list($result['reasons']));
        $soft = AuditGrader::classification([], ['track_unconfirmed']);
        $this->assertSame(['rule_track_unconfirmed', 'no_source'], $soft['reasons']);
        // Deux défauts identiques : une seule raison, et la liste reste indexée de 0.
        $accuracy = AuditGrader::accuracy(['max_turn_deg' => null, 'hairpins' => 0, 'kinks' => 0,
            'junction_gaps' => [['m' => 2.0], ['m' => 3.0]], 'holes' => [['m' => 10.0]], 'stations' => [['distance_m' => 900.0]]]);
        $this->assertSame(['junction_gap', 'hole', 'station_far'], $accuracy['reasons']);
        $dup = AuditGrader::accuracy(['max_turn_deg' => null, 'hairpins' => 0, 'kinks' => 1,
            'junction_gaps' => [['m' => 2.0], ['m' => 3.0]], 'holes' => [], 'stations' => []]);
        $this->assertSame(['junction_gap', 'kink'], $dup['reasons']);
        $this->assertTrue(array_is_list($dup['reasons']));
    }

    public function test_a_good_placement_is_not_replaced_by_another_reference_on_another_line(): void
    {
        // BD TOPO suit peu (30 %) mais toutes ses voies, celles d'autres lignes comprises, collent ;
        // OSM donne un placement sûr : la note vient d'OSM, pas de « voie partagée ».
        $bdtopo = self::ref(1.0, 1.0, found: 3) + ['all' => ['found' => 10, 'median_m' => 1.0, 'p95_m' => 1.0]];
        $result = AuditGrader::placement(['bdtopo' => $bdtopo, 'osm' => self::ref(0.5, 1.0)], 'sncf', true);
        $this->assertSame(AuditGrade::Good, $result['grade']);
        $this->assertSame(['within_target'], $result['reasons']);
        $this->assertSame('osm', $result['reference']);
    }

    public function test_other_line_track_measure_is_rounded_to_three_decimals_and_not_same_source(): void
    {
        // 6 points sur 7 collent à toutes les voies : 0,857 (pas 0,86, 0,8571, 0 ni 1).
        $osm = self::ref(20.0, 20.0, found: 0, points: 7) + ['all' => ['found' => 6, 'median_m' => 2.0, 'p95_m' => 3.0]];
        $result = AuditGrader::placement(['osm' => $osm], 'sncf', true);
        $this->assertSame(['reasons' => ['shared_track'], 'reference' => 'osm', 'same_source' => false, 'coverage' => 0.857, 'median_m' => 2.0, 'p95_m' => 3.0],
            ['reasons' => $result['reasons'], 'reference' => $result['reference'], 'same_source' => $result['same_source'],
                'coverage' => $result['coverage'], 'median_m' => $result['median_m'], 'p95_m' => $result['p95_m']]);
        $this->assertSame(AuditGrade::Good, $result['grade']);
        // Un seul point mesuré : couverture 1,0 (jamais divisée par un minimum de 2).
        $one = self::ref(20.0, 20.0, found: 0, points: 1) + ['all' => ['found' => 1, 'median_m' => 2.0, 'p95_m' => 2.0]];
        $this->assertSame(1.0, AuditGrader::placement(['osm' => $one], 'sncf', true)['coverage']);
    }

    public function test_other_line_track_with_own_track_keeps_same_source_false(): void
    {
        $bdtopo = self::ref(20.0, 20.0, found: 0) + ['all' => ['found' => 10, 'median_m' => 1.0, 'p95_m' => 1.0],
            'own' => ['found' => 5, 'median_m' => 20.0]];
        $result = AuditGrader::placement(['bdtopo' => $bdtopo], 'sncf', false);
        $this->assertSame([AuditGrade::Poor, ['on_other_line_track'], false, 20.0], [$result['grade'], $result['reasons'], $result['same_source'], $result['median_m'] ?? null]);
    }

    public function test_own_track_needs_half_of_the_points_not_more(): void
    {
        // Voie propre à 20 m sur exactement la moitié des points : elle compte (on_other_line_track).
        $bdtopo = self::ref(20.0, 20.0, found: 0) + ['all' => ['found' => 10, 'median_m' => 1.0, 'p95_m' => 1.0],
            'own' => ['found' => 5, 'median_m' => 20.0]];
        $this->assertSame(['on_other_line_track'], AuditGrader::placement(['bdtopo' => $bdtopo], 'sncf', true)['reasons']);
        // Voie propre à 3 m sur exactement la moitié des points : le tronçon est sur sa voie, même
        // si une autre référence trouve une voie propre à 20 m : voie partagée.
        $bdtopoOwn = self::ref(3.0, 3.0, found: 5) + ['own' => ['found' => 5, 'median_m' => 3.0]];
        $osm = self::ref(20.0, 20.0, found: 0) + ['all' => ['found' => 10, 'median_m' => 1.0, 'p95_m' => 1.0],
            'own' => ['found' => 10, 'median_m' => 20.0]];
        $this->assertSame(['shared_track'], AuditGrader::placement(['bdtopo' => $bdtopoOwn, 'osm' => $osm], 'sncf', true)['reasons']);
    }

    public function test_a_short_own_track_does_not_make_the_segment_sit_on_its_own_track(): void
    {
        // Voie propre à 3 m sur 2 points sur 10 seulement : ce n'est pas la voie du tronçon ; la voie propre
        // à 20 m d'OSM décide, le tronçon est sur la voie d'une autre ligne.
        $bdtopo = self::ref(3.0, 3.0, found: 2) + ['own' => ['found' => 2, 'median_m' => 3.0]];
        $osm = self::ref(20.0, 20.0) + ['all' => ['found' => 10, 'median_m' => 1.0, 'p95_m' => 1.0],
            'own' => ['found' => 10, 'median_m' => 20.0]];
        $result = AuditGrader::placement(['bdtopo' => $bdtopo, 'osm' => $osm], 'sncf', false);
        $this->assertSame([AuditGrade::Poor, ['on_other_line_track']], [$result['grade'], $result['reasons']]);
    }

    public function test_a_reference_without_points_never_divides_by_zero(): void
    {
        $empty = ['points' => 0, 'found' => 0, 'median_m' => null, 'p95_m' => null,
            'own' => ['found' => 0, 'median_m' => 20.0], 'all' => ['found' => 0, 'median_m' => null, 'p95_m' => null]];
        $result = AuditGrader::placement(['bdtopo' => $empty], 'sncf', true);
        $this->assertSame(['no_track_nearby'], $result['reasons']);
        $this->assertSame(AuditGrade::Poor, $result['grade']);
    }

    public function test_other_line_track_limits_are_inclusive(): void
    {
        $make = fn (int $found, float $median, float $p95): array => self::ref(30.0, 30.0)
            + ['all' => ['found' => $found, 'median_m' => $median, 'p95_m' => $p95]];
        // 80 % des points, médiane et 95e centile à 5 m pile : la voie colle.
        $this->assertSame(['shared_track'], AuditGrader::placement(['osm' => $make(8, 5.0, 5.0)], 'sncf', true)['reasons']);
        // Un cran de plus sur chaque limite : elle ne colle plus.
        $this->assertSame(['median_over_tolerance'], AuditGrader::placement(['osm' => $make(8, 5.01, 5.0)], 'sncf', true)['reasons']);
        $this->assertSame(['median_over_tolerance'], AuditGrader::placement(['osm' => $make(8, 5.0, 5.01)], 'sncf', true)['reasons']);
        $this->assertSame(['median_over_tolerance'], AuditGrader::placement(['osm' => $make(7, 5.0, 5.0)], 'sncf', true)['reasons']);
    }

    public function test_the_trace_source_alone_needs_half_of_the_points_and_is_rounded(): void
    {
        $half = AuditGrader::placement(['sncf' => self::ref(1.0, 1.0, found: 5)], 'sncf', false);
        $this->assertSame(['only_same_source', true, 0.5], [$half['reasons'][0], $half['same_source'], $half['coverage']]);
        $this->assertSame(AuditGrade::Unknown, $half['grade']);
        $this->assertSame('sncf', $half['reference']);
        $this->assertSame(0.857, AuditGrader::placement(['sncf' => self::ref(1.0, 1.0, found: 6, points: 7)], 'sncf', false)['coverage']);
        $below = AuditGrader::placement(['sncf' => self::ref(1.0, 1.0, found: 49, points: 100)], 'sncf', false);
        $this->assertSame(['no_reference', null], [$below['reasons'][0], $below['reference']]);
    }

    public function test_placement_coverage_is_rounded_to_three_decimals(): void
    {
        $result = AuditGrader::placement(['osm' => self::ref(0.5, 1.0, found: 6, points: 7)], 'sncf', true);
        $this->assertSame(0.857, $result['coverage']);
        $this->assertSame(['within_target'], $result['reasons']);
        $this->assertSame(0.857, AuditGrader::placement(['osm' => self::ref(9.0, 9.0, found: 6, points: 7)], 'sncf', true)['coverage']);
    }

    public function test_confirmation_by_a_precise_bdtopo_track(): void
    {
        $this->assertTrue(AuditGrader::confirmedBy(['bdtopo' => self::ref(0.5, 1.0)], 'sncf'));
        $this->assertTrue(AuditGrader::confirmedBy(['bdtopo' => self::ref(1.0, 5.0, found: 8)], 'osm'));
        $this->assertFalse(AuditGrader::confirmedBy(['bdtopo' => self::ref(0.5, 1.0)], 'bdtopo'));
        $this->assertFalse(AuditGrader::confirmedBy(['bdtopo' => self::ref(1.01, 1.0)], 'sncf'));
    }

    public function test_line_length_ratio_is_rounded_to_three_decimals(): void
    {
        $this->assertSame(0.857, AuditGrader::lineAccuracy(6, 7, 0)['length_ratio']);
    }

    public function test_line_grade_without_a_poor_entry_still_counts_a_tenth_of_fair(): void
    {
        $this->assertSame(AuditGrade::Fair, AuditGrader::lineGrade(['good' => 90, 'fair' => 10]));
        $this->assertSame(AuditGrade::Good, AuditGrader::lineGrade(['good' => 91, 'fair' => 9]));
    }
}
