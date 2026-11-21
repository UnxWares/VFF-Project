<?php

namespace Tests\Unit\SegmentAudit;

use App\Enums\AuditGrade;
use App\Services\SegmentAudit\AuditGrader;
use App\Services\SegmentAudit\SegmentAuditor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règles de notation de l'audit des tronçons (AuditGrader), sans base de données.
 */
class AuditGraderTest extends TestCase
{
    /** @return iterable<string, array{string, string, array{class: string, traffic?: ?string}, bool, ?string}> */
    public static function comparisons(): iterable
    {
        yield 'ouvert attendu, en service' => ['in_service', 'mixed', ['class' => 'open', 'traffic' => 'mixed'], true, null];
        yield 'voie de service = ouverte' => ['service_track', 'unknown', ['class' => 'open'], true, null];
        yield 'fermé attendu, déposé : pas de contradiction' => ['dismantled', 'unknown', ['class' => 'closed'], true, null];
        yield 'disparu attendu, voie en place' => ['abandoned', 'unknown', ['class' => 'gone'], false, 'expected_gone'];
        yield 'fermé attendu, en service' => ['in_service', 'mixed', ['class' => 'closed'], false, 'expected_closed'];
        yield 'voie en place : ouverte ou fermée' => ['abandoned', 'unknown', ['class' => 'in_place'], true, null];
        yield 'voie en place, mais déposée' => ['dismantled', 'unknown', ['class' => 'in_place'], false, 'expected_in_place'];
        yield 'pas encore ouvert' => ['in_service', 'mixed', ['class' => 'not_open'], false, 'expected_not_open'];
        yield 'en construction = pas encore ouvert' => ['construction', 'unknown', ['class' => 'not_open'], true, null];
        yield 'fret seul attendu, mixte' => ['in_service', 'mixed', ['class' => 'open', 'traffic' => 'freight'], false, 'expected_freight_only'];
        yield 'voyageurs attendus, fret seul' => ['in_service', 'freight', ['class' => 'open', 'traffic' => 'mixed'], false, 'expected_passengers'];
        yield 'voie maintenue en place, fermé' => ['abandoned', 'unknown', ['class' => 'closed_in_place'], true, null];
        yield 'voie maintenue en place, mais déposé' => ['dismantled', 'unknown', ['class' => 'closed_in_place'], false, 'expected_closed_in_place'];
        yield 'voie de service SNCF, tronçon mixte' => ['in_service', 'mixed', ['class' => 'open', 'traffic' => 'freight'], false, 'expected_freight_only'];
        yield 'trafic inconnu : rien à redire' => ['in_service', 'unknown', ['class' => 'open', 'traffic' => 'freight'], true, null];
    }

    #[DataProvider('comparisons')]
    public function test_compare(string $status, string $traffic, array $expected, bool $agrees, ?string $why): void
    {
        $this->assertSame([$agrees, $why], AuditGrader::compare($status, $traffic, $expected));
    }

    public function test_expected_state_from_a_timeline(): void
    {
        $this->assertSame(['class' => 'not_open', 'traffic' => null], AuditGrader::expectedFromState(null, 1900, 1890));
        $this->assertSame(['class' => 'gone', 'traffic' => null], AuditGrader::expectedFromState(null, 1870, 1990));
        $this->assertSame(['class' => 'open', 'traffic' => 'freight'], AuditGrader::expectedFromState(['status' => 'in_service', 'traffic' => 'freight'], 1870, 1990));
        $this->assertSame(['class' => 'open', 'traffic' => null], AuditGrader::expectedFromState(['status' => 'in_service', 'traffic' => 'unknown'], 1870, 1990));
        $this->assertSame(['class' => 'closed', 'traffic' => null], AuditGrader::expectedFromState(['status' => 'abandoned', 'traffic' => 'unknown'], 1870, 1990));
    }

    public function test_classification_grades(): void
    {
        $v = fn (string $source, bool $agrees, int $priority, bool $weak = false, bool $near = false): array => ['source' => $source, 'agrees' => $agrees, 'priority' => $priority, 'weak' => $weak, 'near_date' => $near];

        $this->assertSame(AuditGrade::Unknown, AuditGrader::classification([])['grade']);
        $this->assertSame(['sources_agree'], AuditGrader::classification([$v('sncf', true, 3)])['reasons']);
        $this->assertSame(AuditGrade::Good, AuditGrader::classification([$v('sncf', true, 3), $v('wikidata', true, 8, weak: true)])['grade']);
        // Une source mieux classée contredit sans qu'aucune source aussi fiable soit d'accord.
        $poor = AuditGrader::classification([$v('wikipedia', false, 4), $v('ohm', true, 6)]);
        $this->assertSame([AuditGrade::Poor, ['source_contradicts']], [$poor['grade'], $poor['reasons']]);
        // L'accord vient d'une source mieux classée : à revoir seulement.
        $this->assertSame(AuditGrade::Fair, AuditGrader::classification([$v('sncf', true, 3), $v('wikipedia', false, 4)])['grade']);
        // Contradiction proche de la date : avis faible, l'accord plus fiable l'emporte.
        $this->assertSame(AuditGrade::Good, AuditGrader::classification([$v('sncf', true, 3), $v('wikipedia', false, 4, near: true)])['grade']);
        // Avis faible d'une source mieux classée que toutes celles qui sont d'accord.
        $this->assertSame(['weak_contradiction'], AuditGrader::classification([$v('ohm', true, 6), $v('lignes-oubliees', false, 2, near: true)])['reasons']);
        $this->assertSame(['weak_sources_only'], AuditGrader::classification([$v('wikidata', true, 8, weak: true)])['reasons']);
        $rule = AuditGrader::classification([$v('sncf', true, 3)], ['passenger_only_off_lgv']);
        $this->assertSame([AuditGrade::Poor, ['rule_passenger_only_off_lgv']], [$rule['grade'], $rule['reasons']]);
    }

    public function test_placement_takes_an_independent_reference(): void
    {
        $refs = [
            'bdtopo' => ['points' => 10, 'found' => 10, 'median_m' => 0.0, 'p95_m' => 0.0],
            'osm' => ['points' => 10, 'found' => 10, 'median_m' => 0.8, 'p95_m' => 2.0],
        ];
        $good = AuditGrader::placement($refs, 'bdtopo', true);
        $this->assertSame([AuditGrade::Good, 'osm', ['within_target']], [$good['grade'], $good['reference'], $good['reasons']]);

        $fair = AuditGrader::placement(['bdtopo' => ['points' => 10, 'found' => 10, 'median_m' => 3.0, 'p95_m' => 9.0]], 'osm', true);
        $this->assertSame([AuditGrade::Fair, ['p95_over_tolerance']], [$fair['grade'], $fair['reasons']]);

        $poor = AuditGrader::placement(['bdtopo' => ['points' => 10, 'found' => 10, 'median_m' => 12.0, 'p95_m' => 20.0]], 'sncf', false);
        $this->assertSame(AuditGrade::Poor, $poor['grade']);

        $partial = AuditGrader::placement(['bdtopo' => ['points' => 10, 'found' => 6, 'median_m' => 0.5, 'p95_m' => 1.0]], 'osm', true);
        $this->assertSame([AuditGrade::Fair, ['within_target', 'partial_coverage']], [$partial['grade'], $partial['reasons']]);

        $same = AuditGrader::placement(['osm' => ['points' => 10, 'found' => 10, 'median_m' => 0.0, 'p95_m' => 0.0]], 'osm', true);
        $this->assertSame([AuditGrade::Unknown, ['only_same_source'], true], [$same['grade'], $same['reasons'], $same['same_source']]);

        $none = ['bdtopo' => ['points' => 10, 'found' => 2, 'median_m' => 4.0, 'p95_m' => 4.0]];
        $this->assertSame(['no_track_nearby'], AuditGrader::placement($none, 'archeoferroviaire', true)['reasons']);
        $this->assertSame([AuditGrade::Unknown, ['no_reference']], array_values(array_intersect_key(AuditGrader::placement($none, 'archeoferroviaire', false), ['grade' => 0, 'reasons' => 0])));
    }

    public function test_accuracy_grades(): void
    {
        $facts = ['max_turn_deg' => 3.0, 'hairpins' => 0, 'kinks' => 0, 'junction_gaps' => [], 'holes' => [], 'stations' => []];
        $this->assertSame(['grade' => AuditGrade::Good, 'reasons' => ['no_defect']], AuditGrader::accuracy($facts));
        $this->assertSame(AuditGrade::Fair, AuditGrader::accuracy(['kinks' => 1] + $facts)['grade']);
        $this->assertSame(AuditGrade::Fair, AuditGrader::accuracy(['junction_gaps' => [['m' => 0.3]]] + $facts)['grade']);
        $this->assertSame(['junction_gap'], AuditGrader::accuracy(['junction_gaps' => [['m' => 4.0]]] + $facts)['reasons']);
        $this->assertSame(['hairpin', 'hole'], AuditGrader::accuracy(['hairpins' => 1, 'kinks' => 2, 'holes' => [['m' => 40.0]]] + $facts)['reasons']);
        $this->assertSame(['station_far', 'station_off_track'], AuditGrader::accuracy(['stations' => [['distance_m' => 900.0], ['distance_m' => 200.0], ['distance_m' => 10.0]]] + $facts)['reasons']);
    }

    public function test_trace_grades(): void
    {
        $this->assertSame([AuditGrade::Good, ['precise_source'], 2.5], array_values(array_intersect_key(AuditGrader::trace('bdtopo', 1.0), ['grade' => 0, 'reasons' => 0, 'precision_m' => 0])));
        $this->assertSame(['precise_trace_available'], AuditGrader::trace('sncf', 0.9)['reasons']);
        $this->assertSame(AuditGrade::Poor, AuditGrader::trace('archeoferroviaire', 0.9)['grade']);
        $this->assertSame([AuditGrade::Good, ['recalibrated']], array_values(array_intersect_key(AuditGrader::trace('archeoferroviaire', 1.0, aligned: true), ['grade' => 0, 'reasons' => 0])));
        $this->assertSame(['best_available'], AuditGrader::trace('archeoferroviaire', 0.1)['reasons']);
        $this->assertSame(AuditGrade::Fair, AuditGrader::trace('ohm', 0.0)['grade']);
        $this->assertSame(AuditGrade::Unknown, AuditGrader::trace(null, 0.0)['grade']);
        $this->assertSame([AuditGrade::Fair, ['precise_source', 'unused_trace_in_gap']], array_values(array_intersect_key(AuditGrader::trace('osm', 1.0, unusedTraceInGap: true), ['grade' => 0, 'reasons' => 0])));
    }

    public function test_line_checks(): void
    {
        $this->assertSame(AuditGrade::Good, AuditGrader::lineAccuracy(98_000, 100_000, 0)['grade']);
        $this->assertSame(['length_off'], AuditGrader::lineAccuracy(85_000, 100_000, 0)['reasons']);
        $this->assertSame(['length_mismatch'], AuditGrader::lineAccuracy(60_000, 100_000, 0)['reasons']);
        $this->assertSame(0.6, AuditGrader::lineAccuracy(60_000, 100_000, 0)['length_ratio']);
        // Étendue des PK de lignes-oubliées : un minimum, un tracé plus long n'est pas suspect.
        $this->assertSame(AuditGrade::Good, AuditGrader::lineAccuracy(300_000, 100_000, 0, lowerBound: true)['grade']);
        $this->assertSame(AuditGrade::Poor, AuditGrader::lineAccuracy(50_000, 100_000, 0, lowerBound: true)['grade']);
        $this->assertSame(['station_order'], AuditGrader::lineAccuracy(null, null, 2)['reasons']);
    }

    public function test_line_grade_by_length(): void
    {
        $this->assertSame(AuditGrade::Good, AuditGrader::lineGrade(['good' => 950, 'fair' => 50]));
        $this->assertSame(AuditGrade::Fair, AuditGrader::lineGrade(['good' => 850, 'fair' => 100, 'poor' => 50]));
        $this->assertSame(AuditGrade::Poor, AuditGrader::lineGrade(['good' => 800, 'poor' => 200]));
        $this->assertSame(AuditGrade::Unknown, AuditGrader::lineGrade(['good' => 400, 'unknown' => 600]));
        $this->assertSame(AuditGrade::Unknown, AuditGrader::lineGrade([]));
    }

    public function test_score_and_worst(): void
    {
        $this->assertSame(0, AuditGrader::score(AuditGrade::Good, AuditGrade::Good, AuditGrade::Good, AuditGrade::Good));
        $this->assertSame(100, AuditGrader::score(AuditGrade::Poor, AuditGrade::Poor, AuditGrade::Poor, AuditGrade::Poor));
        $this->assertSame(25, AuditGrader::score(AuditGrade::Poor, AuditGrade::Good, AuditGrade::Good, AuditGrade::Good));
        $this->assertSame(AuditGrade::Fair, AuditGrade::worst([AuditGrade::Good, AuditGrade::Unknown, AuditGrade::Fair]));
        $this->assertSame(AuditGrade::Unknown, AuditGrade::worst([AuditGrade::Good, AuditGrade::Unknown]));
    }

    public function test_station_order_inversions(): void
    {
        $this->assertSame(0, SegmentAuditor::orderInversions([0.0, 1000.0, 2500.0, 4000.0]));
        $this->assertSame(0, SegmentAuditor::orderInversions([4000.0, 2500.0, 1000.0]));
        $this->assertSame(1, SegmentAuditor::orderInversions([0.0, 3000.0, 1500.0, 4000.0]));
        // Un recul de moins de 200 m (gares voisines, projection) ne compte pas.
        $this->assertSame(0, SegmentAuditor::orderInversions([0.0, 1000.0, 900.0, 3000.0]));
        $this->assertSame(0, SegmentAuditor::orderInversions([0.0, 10.0]));
    }

    /** Règles réglées après le contrôle à la main du 21/11/2026. */
    public function test_rules_tuned_by_the_manual_check(): void
    {
        $v = ['source' => 'sncf', 'agrees' => true, 'priority' => 3, 'weak' => false, 'near_date' => false];
        $soft = AuditGrader::classification([$v], ['track_unconfirmed']);
        $this->assertSame([AuditGrade::Fair, ['rule_track_unconfirmed', 'sources_agree']], [$soft['grade'], $soft['reasons']]);
        $this->assertSame(AuditGrade::Fair, AuditGrader::classification([], ['track_unconfirmed'])['grade']);

        $none = ['bdtopo' => ['points' => 10, 'found' => 0, 'median_m' => null, 'p95_m' => null]];
        $this->assertSame([AuditGrade::Unknown, ['abroad']], array_values(array_intersect_key(AuditGrader::placement($none, 'ohm', true, abroad: true), ['grade' => 0, 'reasons' => 0])));

        // Ère passée : la gare a été déplacée, le relevé ancien est la bonne référence.
        $moved = [
            'bdtopo' => ['points' => 4, 'found' => 2, 'median_m' => 35.0, 'p95_m' => 47.0],
            'ign-historique' => ['points' => 4, 'found' => 4, 'median_m' => 3.0, 'p95_m' => 4.0],
        ];
        $this->assertSame('bdtopo', AuditGrader::placement($moved, 'ohm', false)['reference']);
        $past = AuditGrader::placement($moved, 'ohm', false, bestMatch: true);
        $this->assertSame(['ign-historique', AuditGrade::Good], [$past['reference'], $past['grade']]);
    }

    /** Chaque valeur de note est acceptée par la contrainte CHECK de la migration. */
    public function test_grades_match_the_migration_check(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 3).'/database/migrations/2026_12_04_040000_create_segment_audits_table.php');
        $this->assertStringContainsString("IN ('good', 'fair', 'poor', 'unknown')", $sql);
        $this->assertSame(['good', 'fair', 'poor', 'unknown'], AuditGrade::values());
    }
}
