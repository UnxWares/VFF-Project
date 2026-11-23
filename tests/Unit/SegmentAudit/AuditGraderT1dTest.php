<?php

namespace Tests\Unit\SegmentAudit;

use App\Enums\AuditGrade;
use App\Services\SegmentAudit\AuditGrader;
use PHPUnit\Framework\TestCase;

/**
 * Règle T1d (règles 2026-11-21.7, aprem-2111/audit-t1d.md) sur AuditGrader::placement, sans base : un
 * tronçon en service à l'ère courante posé sur la voie d'une autre ligne, sa propre voie à 8 à 60 m, garde
 * un placement « sûr » avec la raison `on_other_line_track` (8 alertes fausses sur 12 au troisième
 * contrôle à la main : doubles voies dont chaque voie est rattachée à une ligne différente). Fermé,
 * déposé ou ère passée : toujours « faux ». Ce « sûr » ne recale jamais le tracé. Aussi (règles .7) : à l'ère
 * courante, une référence indépendante qui colle sur ses voies libres ou de la ligne l'emporte sur une BD TOPO
 * lointaine (régression des règles .5, cas 33 et 34).
 */
class AuditGraderT1dTest extends TestCase
{
    /**
     * Mesure d'une référence : voies libres ou de la ligne (found, médiane, 95e centile), toutes les
     * voies (`all`) et voies de la ligne seules (`own`), sur $points points.
     *
     * @param  array{0: int, 1: float, 2: float}|null  $all
     * @param  array{0: int, 1: float}|null  $own
     * @return array<string, mixed>
     */
    private static function ref(int $found, ?float $median, ?float $p95, ?array $all = null, ?array $own = null, int $points = 10): array
    {
        return ['points' => $points, 'found' => $found, 'median_m' => $median, 'p95_m' => $p95,
            'all' => ['found' => $all[0] ?? $found, 'median_m' => $all[1] ?? $median, 'p95_m' => $all[2] ?? $p95],
            'own' => ['found' => $own[0] ?? 0, 'median_m' => $own[1] ?? null]];
    }

    /**
     * Double voie : le tronçon colle à la voie BD TOPO d'une autre ligne, sa propre voie passe à $own m.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function onOtherLine(float $own = 21.0): array
    {
        return ['bdtopo' => self::ref(0, null, null, all: [10, 0.4, 0.9], own: [10, $own])];
    }

    public function test_in_service_today_the_placement_stays_good_with_the_reason(): void
    {
        $r = AuditGrader::placement(self::onOtherLine(), 'sncf', true);

        $this->assertSame([AuditGrade::Good, ['on_other_line_track'], 'bdtopo', false], [$r['grade'], $r['reasons'], $r['reference'], $r['same_source']]);
        // L'écart affiché reste celui de la propre voie de la ligne, pour juger le cas à la main.
        $this->assertSame(21.0, $r['median_m']);
        $this->assertSame(1.0, $r['coverage']);
    }

    public function test_closed_gone_or_past_the_placement_stays_poor(): void
    {
        // SegmentAuditor passe activeToday = false pour un tronçon fermé ou déposé, et pour toute ère passée.
        $r = AuditGrader::placement(self::onOtherLine(), 'sncf', false);
        $this->assertSame([AuditGrade::Poor, ['on_other_line_track']], [$r['grade'], $r['reasons']]);

        $past = AuditGrader::placement(self::onOtherLine(), 'sncf', false, bestMatch: true);
        $this->assertSame([AuditGrade::Poor, ['on_other_line_track']], [$past['grade'], $past['reasons']]);
    }

    public function test_the_own_track_bounds_are_unchanged(): void
    {
        foreach ([AuditGrader::OWN_TRACK_MIN_M, AuditGrader::OWN_TRACK_MAX_M] as $m) {
            $r = AuditGrader::placement(self::onOtherLine($m), 'sncf', true);
            $this->assertSame([AuditGrade::Good, ['on_other_line_track']], [$r['grade'], $r['reasons']], "voie propre à {$m} m");
        }
        // Voie propre au-delà de 60 m : rien à côté, tronc commun.
        $r = AuditGrader::placement(self::onOtherLine(AuditGrader::OWN_TRACK_MAX_M + 1), 'sncf', true);
        $this->assertSame([AuditGrade::Good, ['shared_track']], [$r['grade'], $r['reasons']]);
    }

    public function test_a_good_placement_on_another_line_track_never_recalibrates_the_trace(): void
    {
        $other = AuditGrader::placement(self::onOtherLine(), 'sncf', true);
        $shared = AuditGrader::placement(self::onOtherLine(AuditGrader::OWN_TRACK_MAX_M + 1), 'sncf', true);
        $own = AuditGrader::placement(['bdtopo' => self::ref(10, 0.4, 0.9)], 'sncf', true);

        $this->assertFalse(AuditGrader::alignedOnOwnTrack($other));
        $this->assertTrue(AuditGrader::alignedOnOwnTrack($shared));
        $this->assertTrue(AuditGrader::alignedOnOwnTrack($own));
        $this->assertFalse(AuditGrader::alignedOnOwnTrack(AuditGrader::placement(self::onOtherLine(), 'sncf', false)));

        // Formes SNCF (peu précises) et voie BD TOPO à côté : « précise disponible », pas « recalé ».
        $trace = AuditGrader::trace('sncf', 1.0, AuditGrader::alignedOnOwnTrack($other));
        $this->assertSame([AuditGrade::Poor, ['precise_trace_available']], [$trace['grade'], $trace['reasons']]);
    }

    public function test_a_free_track_under_the_section_wins_over_the_other_line(): void
    {
        // Une voie libre ou de la ligne colle aussi (règles .5) : placement ordinaire, sans la raison.
        $r = AuditGrader::placement(['bdtopo' => self::ref(10, 0.4, 0.9, all: [10, 0.4, 0.9], own: [10, 21.0])], 'sncf', true);
        $this->assertSame([AuditGrade::Good, ['within_target']], [$r['grade'], $r['reasons']]);
    }

    public function test_today_an_independent_fitting_reference_wins_over_a_distant_bdtopo(): void
    {
        // Cas 34 du troisième contrôle (tracé OHM) : BD TOPO à 24 m, voie OSM libre à 0,4 m.
        $refs = ['bdtopo' => self::ref(4, 23.84, 24.26, points: 4), 'osm' => self::ref(4, 0.4, 1.73, points: 4)];
        $r = AuditGrader::placement($refs, 'ohm', true);
        $this->assertSame([AuditGrade::Good, ['within_target'], 'osm', 0.4], [$r['grade'], $r['reasons'], $r['reference'], $r['median_m']]);
        $this->assertTrue(AuditGrader::alignedOnOwnTrack($r));

        // Cas 33 (formes SNCF) : BD TOPO à 0,07 m mais 95e centile à 6,6 m, OSM à 0,6 m et 1,8 m.
        $refs = ['bdtopo' => self::ref(9, 0.07, 6.63, points: 9), 'osm' => self::ref(9, 0.6, 1.81, points: 9)];
        $this->assertSame([AuditGrade::Good, ['within_target'], 'osm'], array_values(array_intersect_key(AuditGrader::placement($refs, 'sncf', true), ['grade' => 0, 'reasons' => 0, 'reference' => 0])));

        // La source du tracé ne se confirme pas elle-même, et l'OSM qui ne colle pas ne change rien.
        $this->assertSame(AuditGrade::Poor, AuditGrader::placement(['bdtopo' => self::ref(4, 23.84, 24.26, points: 4), 'osm' => self::ref(4, 0.4, 1.73, points: 4)], 'osm', true)['grade']);
        $this->assertSame(AuditGrade::Poor, AuditGrader::placement(['bdtopo' => self::ref(4, 23.84, 24.26, points: 4), 'osm' => self::ref(4, 6.0, 7.0, points: 4)], 'ohm', true)['grade']);
        // Voie BD TOPO de la ligne à 20 m : la voie OSM libre est peut-être celle d'une autre ligne (T1b), rien ne change.
        $beside = ['bdtopo' => self::ref(4, 20.0, 20.5, own: [4, 20.0], points: 4), 'osm' => self::ref(4, 0.4, 1.73, points: 4)];
        $this->assertSame([AuditGrade::Poor, 'bdtopo'], array_values(array_intersect_key(AuditGrader::placement($beside, 'ohm', true), ['grade' => 0, 'reference' => 0])));
        // Relevé IGN ancien : il ne compte pas à l'ère courante.
        $this->assertSame(AuditGrade::Poor, AuditGrader::placement(['bdtopo' => self::ref(4, 23.84, 24.26, points: 4), 'ign-historique' => self::ref(4, 0.4, 1.73, points: 4)], 'ohm', true)['grade']);
        // Couverture partielle de l'OSM : pas assez pour l'emporter.
        $this->assertSame(AuditGrade::Poor, AuditGrader::placement(['bdtopo' => self::ref(10, 23.84, 24.26), 'osm' => self::ref(7, 0.4, 1.73)], 'ohm', true)['grade']);
    }

    public function test_in_a_past_era_the_closest_reference_rule_is_unchanged(): void
    {
        // Ère passée : la référence la plus proche est déjà retenue (bestMatch), la règle ne s'applique pas.
        $refs = ['bdtopo' => self::ref(4, 23.84, 24.26, points: 4), 'osm' => self::ref(4, 0.4, 1.73, points: 4)];
        $r = AuditGrader::placement($refs, 'ohm', false, bestMatch: true);
        $this->assertSame([AuditGrade::Good, ['within_target'], 'osm'], [$r['grade'], $r['reasons'], $r['reference']]);
    }

    public function test_rule_version(): void
    {
        $this->assertSame('2026-11-23.1', AuditGrader::RULE_VERSION);
    }
}
