<?php

namespace Tests\Unit\SegmentAudit;

use App\Enums\AuditGrade;
use App\Services\SegmentAudit\AuditGrader;
use PHPUnit\Framework\TestCase;

/**
 * Frontières des seuils de notation (AuditGrader) : chaque seuil est-il inclus ou exclu, comme le dit
 * docs/AUDIT-TRONCONS.md ? Complète AuditGraderTest, qui couvre les cas typiques.
 */
class AuditGraderBoundariesTest extends TestCase
{
    /** @return array{points: int, found: int, median_m: ?float, p95_m: ?float} */
    private static function ref(float $median, float $p95, int $found = 10, int $points = 10): array
    {
        return ['points' => $points, 'found' => $found, 'median_m' => $median, 'p95_m' => $p95];
    }

    /** @return array{grade: AuditGrade, reasons: list<string>} */
    private static function gradeAndReasons(array $result): array
    {
        return ['grade' => $result['grade'], 'reasons' => $result['reasons']];
    }

    public function test_placement_tolerance_and_target_are_inclusive(): void
    {
        $this->assertSame(['grade' => AuditGrade::Good, 'reasons' => ['within_tolerance']],
            self::gradeAndReasons(AuditGrader::placement(['osm' => self::ref(AuditGrader::TOLERANCE_M, AuditGrader::TOLERANCE_M)], 'sncf', true)));
        $this->assertSame(['within_target'], AuditGrader::placement(['osm' => self::ref(AuditGrader::TARGET_M, 2.0)], 'sncf', true)['reasons']);
        $this->assertSame(['within_tolerance'], AuditGrader::placement(['osm' => self::ref(1.01, 2.0)], 'sncf', true)['reasons']);
        $this->assertSame(['grade' => AuditGrade::Poor, 'reasons' => ['median_over_tolerance']],
            self::gradeAndReasons(AuditGrader::placement(['osm' => self::ref(5.01, 5.01)], 'sncf', true)));
        $this->assertSame(['grade' => AuditGrade::Fair, 'reasons' => ['p95_over_tolerance']],
            self::gradeAndReasons(AuditGrader::placement(['osm' => self::ref(5.0, 5.01)], 'sncf', true)));
    }

    public function test_placement_coverage_thresholds(): void
    {
        // Couverture minimale (la moitié des points) : la référence compte ; juste en dessous, non.
        $half = AuditGrader::placement(['osm' => self::ref(0.5, 1.0, found: 5)], 'sncf', true);
        $this->assertSame(['osm', 0.5], [$half['reference'], $half['coverage']]);
        $this->assertSame(['within_target', 'partial_coverage'], $half['reasons']);
        $this->assertSame(AuditGrade::Fair, $half['grade']);
        $below = AuditGrader::placement(['osm' => self::ref(0.5, 1.0, found: 49, points: 100)], 'sncf', true);
        $this->assertSame([null, ['no_track_nearby'], AuditGrade::Poor, 0.0], [$below['reference'], $below['reasons'], $below['grade'], $below['coverage']]);
        // Couverture complète (80 %) : plus « partielle ».
        $full = AuditGrader::placement(['osm' => self::ref(0.5, 1.0, found: 8)], 'sncf', true);
        $this->assertSame([AuditGrade::Good, ['within_target']], [$full['grade'], $full['reasons']]);
        $this->assertSame(['partial_coverage'], array_slice(AuditGrader::placement(['osm' => self::ref(0.5, 1.0, found: 79, points: 100)], 'sncf', true)['reasons'], 1));
        // Une note déjà mauvaise reste mauvaise sur une couverture partielle.
        $this->assertSame(AuditGrade::Poor, AuditGrader::placement(['osm' => self::ref(9.0, 9.0, found: 6)], 'sncf', true)['grade']);
        // Aucun point mesuré : couverture nulle, jamais une division par zéro.
        $this->assertSame(0.0, AuditGrader::coverage(['points' => 0, 'found' => 0]));
    }

    public function test_placement_reference_order_and_best_match(): void
    {
        $refs = ['bdtopo' => self::ref(3.0, 4.0), 'osm' => self::ref(1.0, 2.0), 'ign-historique' => self::ref(0.5, 1.0)];
        // Ère courante : la première référence dans l'ordre de préférence, même moins proche.
        $this->assertSame('bdtopo', AuditGrader::placement($refs, 'sncf', true)['reference']);
        // Ère passée : la plus proche ; à égalité, la première dans l'ordre.
        $this->assertSame('ign-historique', AuditGrader::placement($refs, 'sncf', false, bestMatch: true)['reference']);
        $tie = ['bdtopo' => self::ref(1.0, 2.0), 'osm' => self::ref(1.0, 2.0)];
        $this->assertSame('bdtopo', AuditGrader::placement($tie, 'sncf', false, bestMatch: true)['reference']);
        // La source du tracé n'est jamais sa propre référence tant qu'une autre existe.
        $this->assertSame('osm', AuditGrader::placement($refs, 'bdtopo', true)['reference']);
        // Médiane inconnue : la référence passe après celles dont la médiane est connue.
        $unknownMedian = ['bdtopo' => ['points' => 10, 'found' => 10, 'median_m' => null, 'p95_m' => null], 'osm' => self::ref(2.0, 3.0)];
        $this->assertSame('osm', AuditGrader::placement($unknownMedian, 'sncf', false, bestMatch: true)['reference']);
        // Seule la source du tracé, mais trop peu couverte : aucune référence, pas « même source ».
        $thin = AuditGrader::placement(['osm' => self::ref(0.5, 1.0, found: 2)], 'osm', false);
        $this->assertSame([null, false, ['no_reference']], [$thin['reference'], $thin['same_source'], $thin['reasons']]);
        // Hors de France : inconnue, même en service.
        $this->assertSame(['abroad'], AuditGrader::placement([], null, false, abroad: true)['reasons']);
    }

    public function test_accuracy_thresholds_are_exclusive(): void
    {
        $facts = ['max_turn_deg' => null, 'hairpins' => 0, 'kinks' => 0, 'junction_gaps' => [], 'holes' => [], 'stations' => []];
        $at = fn (array $over): array => self::gradeAndReasons(AuditGrader::accuracy($over + $facts));
        $this->assertSame(['grade' => AuditGrade::Fair, 'reasons' => ['junction_gap_small']], $at(['junction_gaps' => [['m' => AuditGrader::JUNCTION_POOR_M]]]));
        $this->assertSame(['grade' => AuditGrade::Good, 'reasons' => ['no_defect']], $at(['junction_gaps' => [['m' => AuditGrader::JUNCTION_MIN_M]]]));
        $this->assertSame(['grade' => AuditGrade::Poor, 'reasons' => ['junction_gap']], $at(['junction_gaps' => [['m' => 1.01]]]));
        $this->assertSame(['grade' => AuditGrade::Fair, 'reasons' => ['station_off_track']], $at(['stations' => [['distance_m' => AuditGrader::STATION_FAR_M]]]));
        $this->assertSame(['grade' => AuditGrade::Good, 'reasons' => ['no_defect']], $at(['stations' => [['distance_m' => AuditGrader::STATION_NEAR_M]]]));
        // Deux gares loin : la raison une seule fois.
        $this->assertSame(['station_far'], $at(['stations' => [['distance_m' => 600.0], ['distance_m' => 700.0]]])['reasons']);
        // Un coude avec un aller-retour : seul l'aller-retour est dit.
        $this->assertSame(['hairpin'], $at(['hairpins' => 2, 'kinks' => 3])['reasons']);
    }

    public function test_trace_coverage_threshold_and_recalibration(): void
    {
        $this->assertSame(['grade' => AuditGrade::Poor, 'reasons' => ['precise_trace_available']],
            self::gradeAndReasons(AuditGrader::trace('sncf', AuditGrader::MIN_COVERAGE)));
        $this->assertSame(['grade' => AuditGrade::Fair, 'reasons' => ['best_available']],
            self::gradeAndReasons(AuditGrader::trace('sncf', 0.49)));
        // Source moyenne calée sur la voie réelle : recalée, donc sûre.
        $this->assertSame(['grade' => AuditGrade::Good, 'reasons' => ['recalibrated']],
            self::gradeAndReasons(AuditGrader::trace('ohm', 0.0, aligned: true)));
        // Source inconnue : le calage ne la rend pas sûre.
        $this->assertSame(['grade' => AuditGrade::Unknown, 'reasons' => ['unknown_source']],
            self::gradeAndReasons(AuditGrader::trace('inconnue', 1.0, aligned: true)));
        // Trace inutilisée dans un trou : une note déjà « à revoir » ou fausse ne change pas.
        $this->assertSame(AuditGrade::Poor, AuditGrader::trace('sncf', 0.9, unusedTraceInGap: true)['grade']);
        $this->assertSame(['medium_source', 'unused_trace_in_gap'], AuditGrader::trace('contrib', 0.0, unusedTraceInGap: true)['reasons']);
        $this->assertSame([165.0, 'low'], array_values(array_intersect_key(AuditGrader::trace('sncf', 0.0), ['precision_m' => 0, 'precision_class' => 0])));
    }

    public function test_line_length_thresholds(): void
    {
        // Écart d'un quart exactement : « à revoir », pas faux.
        $this->assertSame(['length_off'], AuditGrader::lineAccuracy(75_000, 100_000, 0)['reasons']);
        $this->assertSame(['length_mismatch'], AuditGrader::lineAccuracy(74_900, 100_000, 0)['reasons']);
        $this->assertSame(['grade' => AuditGrade::Good, 'reasons' => ['no_defect']], self::gradeAndReasons(AuditGrader::lineAccuracy(95_000, 100_000, 0)));
        $this->assertSame(['length_mismatch'], AuditGrader::lineAccuracy(130_000, 100_000, 0)['reasons']);
        // Longueur officielle nulle ou inconnue : pas de rapport.
        $this->assertNull(AuditGrader::lineAccuracy(1000, 0, 0)['length_ratio']);
        $this->assertNull(AuditGrader::lineAccuracy(null, 1000, 0)['length_ratio']);
        // Minimum (étendue des PK) : un tracé un peu plus court est « à revoir ».
        $this->assertSame(['length_off'], AuditGrader::lineAccuracy(80_000, 100_000, 0, lowerBound: true)['reasons']);
        // Inversion de l'ordre des gares : fausse, même avec une longueur juste, les deux raisons dites.
        $this->assertSame(['grade' => AuditGrade::Poor, 'reasons' => ['length_off', 'station_order']],
            self::gradeAndReasons(AuditGrader::lineAccuracy(85_000, 100_000, 1)));
    }

    public function test_line_grade_shares_are_inclusive(): void
    {
        $this->assertSame(AuditGrade::Poor, AuditGrader::lineGrade(['good' => 900, 'poor' => 100]));
        // Juste sous le seuil, des tronçons faux ne font pas une ligne « à revoir ».
        $this->assertSame(AuditGrade::Good, AuditGrader::lineGrade(['good' => 901, 'poor' => 99]));
        $this->assertSame(AuditGrade::Fair, AuditGrader::lineGrade(['good' => 900, 'fair' => 50, 'poor' => 50]));
        $this->assertSame(AuditGrade::Good, AuditGrader::lineGrade(['good' => 901, 'fair' => 99]));
        $this->assertSame(AuditGrade::Unknown, AuditGrader::lineGrade(['good' => 500, 'unknown' => 500]));
        $this->assertSame(AuditGrade::Good, AuditGrader::lineGrade(['good' => 501, 'unknown' => 499]));
        // Une ligne « à revoir » l'emporte sur l'inconnu.
        $this->assertSame(AuditGrade::Fair, AuditGrader::lineGrade(['fair' => 200, 'unknown' => 800]));
        $this->assertSame(AuditGrade::Unknown, AuditGrader::lineGrade(['good' => 0]));
    }

    public function test_score_rounding_and_empty(): void
    {
        $this->assertSame(0, AuditGrader::score());
        // 1 point sur 16 : 6,25 arrondi à 6 ; 2 sur 16 : 12,5 arrondi à 13.
        $this->assertSame(6, AuditGrader::score(AuditGrade::Unknown, AuditGrade::Good, AuditGrade::Good, AuditGrade::Good));
        $this->assertSame(13, AuditGrader::score(AuditGrade::Fair, AuditGrade::Good, AuditGrade::Good, AuditGrade::Good));
        $this->assertSame(50, AuditGrader::score(AuditGrade::Fair, AuditGrade::Fair, AuditGrade::Fair, AuditGrade::Fair));
    }

    public function test_classification_priorities_and_ties(): void
    {
        $v = fn (bool $agrees, ?int $priority, bool $weak = false, bool $near = false): array => array_filter(
            ['source' => 'x', 'agrees' => $agrees, 'weak' => $weak, 'near_date' => $near, 'priority' => $priority], fn ($x) => $x !== null);
        // Contradiction forte d'une source de même rang que l'accord : « à revoir », pas faux.
        $this->assertSame(['grade' => AuditGrade::Fair, 'reasons' => ['sources_disagree']],
            AuditGrader::classification([$v(true, 3), $v(false, 3)]));
        $this->assertSame(['grade' => AuditGrade::Poor, 'reasons' => ['source_contradicts']],
            AuditGrader::classification([$v(true, 4), $v(false, 3)]));
        // Rang absent : 50, après toutes les sources classées.
        $this->assertSame(AuditGrade::Fair, AuditGrader::classification([$v(true, 49), $v(false, null)])['grade']);
        $this->assertSame(AuditGrade::Poor, AuditGrader::classification([$v(true, null), $v(false, 49)])['grade']);
        // Contradiction seule, sans aucun accord : fausse.
        $this->assertSame(AuditGrade::Poor, AuditGrader::classification([$v(false, 9)])['grade']);
        // Avis faible contraire d'une source de même rang que l'accord : sûr.
        $this->assertSame(['grade' => AuditGrade::Good, 'reasons' => ['sources_agree']],
            AuditGrader::classification([$v(true, 3), $v(false, 3, weak: true)]));
        $this->assertSame(['grade' => AuditGrade::Fair, 'reasons' => ['weak_contradiction']],
            AuditGrader::classification([$v(true, 3), $v(false, 2, near: true)]));
        // Contradiction faible seule : « à revoir » (faible), jamais fausse.
        $this->assertSame(['grade' => AuditGrade::Fair, 'reasons' => ['weak_contradiction']],
            AuditGrader::classification([$v(false, 1, weak: true)]));
    }

    public function test_classification_rules_keep_their_order_and_soft_rules_never_lower_a_poor_grade(): void
    {
        $agree = ['source' => 'sncf', 'agrees' => true, 'priority' => 3];
        $both = AuditGrader::classification([$agree], ['track_unconfirmed', 'passenger_only_off_lgv']);
        $this->assertSame(['grade' => AuditGrade::Poor, 'reasons' => ['rule_passenger_only_off_lgv', 'rule_track_unconfirmed']], $both);
        $this->assertSame(['grade' => AuditGrade::Poor, 'reasons' => ['rule_passenger_only_off_lgv', 'no_source']],
            AuditGrader::classification([], ['passenger_only_off_lgv']));
        $contra = ['source' => 'sncf', 'agrees' => false, 'priority' => 3];
        $this->assertSame(AuditGrade::Poor, AuditGrader::classification([$contra], ['track_unconfirmed'])['grade']);
    }

    public function test_expected_state_and_traffic(): void
    {
        // Première ouverture l'année même de l'ère : pas « pas encore ouverte ».
        $this->assertSame('gone', AuditGrader::expectedFromState(null, 1900, 1900)['class']);
        $this->assertSame('gone', AuditGrader::expectedFromState(null, null, 1900)['class']);
        // Le trafic n'est dit que pour une voie ouverte.
        $this->assertSame(['class' => 'not_open', 'traffic' => null], AuditGrader::expectedFromState(['status' => 'construction', 'traffic' => 'mixed'], 1900, 1950));
        // Voyageurs attendus, voyageurs seuls observés : d'accord (ni l'un ni l'autre n'est « fret seul »).
        $this->assertSame([true, null], AuditGrader::compare('in_service', 'passenger', ['class' => 'open', 'traffic' => 'mixed']));
        $this->assertSame([true, null], AuditGrader::compare('single_track', 'freight', ['class' => 'open', 'traffic' => 'freight']));
        // Trafic attendu, mais voie fermée et d'accord sur la fermeture : le trafic ne compte pas.
        $this->assertSame([true, null], AuditGrader::compare('abandoned', 'freight', ['class' => 'in_place', 'traffic' => 'mixed']));
        $this->assertSame('gone', AuditGrader::statusClass('razed'));
    }
}
