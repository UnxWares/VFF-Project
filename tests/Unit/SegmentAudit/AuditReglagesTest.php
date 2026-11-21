<?php

namespace Tests\Unit\SegmentAudit;

use App\Enums\AuditGrade;
use App\Services\SegmentAudit\AuditGrader;
use App\Services\SegmentAudit\SegmentAuditor;
use PHPUnit\Framework\TestCase;

/**
 * Réglages du 21/11/2026 (règles 2026-11-21.4), tirés du second contrôle à la main :
 * T1 voie d'une autre ligne, T2 tracé confirmé par une source précise, T3 observation seule contre
 * deux, T4 continuité aux ères passées.
 */
class AuditReglagesTest extends TestCase
{
    /**
     * @param  array{0: int, 1: ?float, 2: ?float}|null  $all  [found, médiane, p95] sur toutes les voies
     * @param  array{0: int, 1: ?float}|null  $own  [found, médiane] sur les voies de la ligne
     * @return array<string, mixed>
     */
    private static function ref(int $found, ?float $median, ?float $p95, ?array $all = null, ?array $own = null, int $points = 10): array
    {
        return ['points' => $points, 'found' => $found, 'median_m' => $median, 'p95_m' => $p95,
            'all' => ['found' => $all[0] ?? $found, 'median_m' => $all[1] ?? $median, 'p95_m' => $all[2] ?? $p95],
            'own' => ['found' => $own[0] ?? 0, 'median_m' => $own[1] ?? null]];
    }

    public function test_t1_a_track_of_another_line_is_not_a_reference(): void
    {
        // Wingen, Boisleux : la voie BD TOPO voisine est celle d'une autre ligne ; la plate-forme propre est dans OSM, à 0 m.
        $refs = [
            'bdtopo' => self::ref(0, null, null, all: [10, 6.5, 8.0]),
            'osm' => self::ref(10, 0.0, 0.4),
        ];
        $r = AuditGrader::placement($refs, 'archeoferroviaire', false);
        $this->assertSame([AuditGrade::Good, ['within_target'], 'osm'], [$r['grade'], $r['reasons'], $r['reference']]);
    }

    public function test_t1_a_branch_recalibrated_on_the_neighbour_line_is_poor_when_its_own_track_runs_alongside(): void
    {
        // Vitrolles, Saint-Amand, Kerpont : 0 m jusqu'à la voie de la ligne principale, voie-mère propre à 9 à 30 m.
        $refs = ['bdtopo' => self::ref(0, null, null, all: [10, 0.0, 0.3], own: [10, 11.0])];
        $r = AuditGrader::placement($refs, 'sncf', false);
        $this->assertSame([AuditGrade::Poor, ['on_other_line_track'], 'bdtopo', 11.0], [$r['grade'], $r['reasons'], $r['reference'], $r['median_m']]);
        // Pas de recalage pour le tracé : il n'est pas sur sa voie.
        $this->assertSame(AuditGrade::Poor, AuditGrader::trace('sncf', 1.0, $r['grade'] === AuditGrade::Good)['grade']);
    }

    public function test_t1_own_track_window_is_bounded(): void
    {
        $at = fn (float $m): string => AuditGrader::placement(['bdtopo' => self::ref(0, null, null, all: [10, 0.0, 0.3], own: [10, $m])], 'sncf', false)['reasons'][0];
        $this->assertSame('on_other_line_track', $at(AuditGrader::OWN_TRACK_MAX_M));
        $this->assertSame('on_other_line_track', $at(AuditGrader::OWN_TRACK_MIN_M));
        $this->assertSame('shared_track', $at(AuditGrader::OWN_TRACK_MAX_M + 0.1));
        // Voie de la ligne à moins de 8 m : le tronçon est sur sa voie, voie partagée.
        $this->assertSame('shared_track', $at(AuditGrader::OWN_TRACK_MIN_M - 0.1));
        // Voie propre trop peu suivie (moins de la moitié des points) : pas de preuve.
        $thin = AuditGrader::placement(['bdtopo' => self::ref(0, null, null, all: [10, 0.0, 0.3], own: [4, 20.0])], 'sncf', false);
        $this->assertSame(['shared_track'], $thin['reasons']);
    }

    public function test_t1_a_section_on_a_track_of_its_own_line_is_never_on_the_wrong_track(): void
    {
        // Gare de Sathonay : tracé BD TOPO posé sur la voie BD TOPO de sa ligne (0 m) ; OSM n'a que la voie voisine.
        $refs = [
            'osm' => self::ref(3, 9.0, 12.2, all: [3, 0.8, 0.9], own: [2, 35.3], points: 3),
            'bdtopo' => self::ref(3, 0.0, 0.0, all: [3, 0.0, 0.0], own: [3, 0.0], points: 3),
        ];
        $r = AuditGrader::placement($refs, 'bdtopo', false);
        $this->assertSame([AuditGrade::Good, ['shared_track'], 'osm'], [$r['grade'], $r['reasons'], $r['reference']]);
    }

    public function test_t1_a_section_in_service_next_to_its_own_track_stays_good_but_is_flagged(): void
    {
        // T1d (règles .7) : « à revoir » avant, « sûr » avec la raison depuis.
        $r = AuditGrader::placement(['bdtopo' => self::ref(0, null, null, all: [10, 0.0, 0.3], own: [10, 12.0])], 'osm', true);
        $this->assertSame([AuditGrade::Good, ['on_other_line_track']], [$r['grade'], $r['reasons']]);
    }

    public function test_t1_without_an_own_track_the_section_keeps_its_grade_but_is_flagged(): void
    {
        $refs = ['osm' => self::ref(0, null, null, all: [10, 0.2, 0.8])];
        foreach ([true, false] as $active) {
            $r = AuditGrader::placement($refs, 'bdtopo', $active);
            $this->assertSame([AuditGrade::Good, ['shared_track'], 'osm'], [$r['grade'], $r['reasons'], $r['reference']]);
        }
        // Une voie libre suit le tronçon, mais à 9 m : la voie de l'autre ligne explique le tracé.
        $parallel = AuditGrader::placement(['osm' => self::ref(10, 9.0, 12.0, all: [10, 0.2, 0.8])], 'bdtopo', true);
        $this->assertSame(['shared_track'], $parallel['reasons']);
    }

    public function test_t1_needs_a_full_fit_on_the_other_line_and_ignores_the_trace_source(): void
    {
        // Couverture partielle ou p95 hors tolérance sur l'autre ligne : la règle ne joue pas.
        $partial = AuditGrader::placement(['bdtopo' => self::ref(0, null, null, all: [7, 0.1, 0.2])], 'sncf', true);
        $this->assertSame(['no_track_nearby'], $partial['reasons']);
        $p95 = AuditGrader::placement(['bdtopo' => self::ref(0, null, null, all: [10, 1.0, 5.5])], 'sncf', true);
        $this->assertSame(['no_track_nearby'], $p95['reasons']);
        // La source du tracé ne se confirme pas elle-même, même sur une autre ligne.
        $same = AuditGrader::placement(['osm' => self::ref(0, null, null, all: [10, 0.0, 0.0])], 'osm', true);
        $this->assertSame(['no_track_nearby'], $same['reasons']);
        // Placement déjà sûr sur une voie libre : inchangé, même si une autre ligne passe dessus.
        $good = AuditGrader::placement(['bdtopo' => self::ref(10, 0.5, 1.0, all: [10, 0.0, 0.1], own: [10, 20.0])], 'sncf', true);
        $this->assertSame(['within_target'], $good['reasons']);
    }

    public function test_t2_a_precise_independent_source_confirms_the_trace(): void
    {
        $confirm = ['osm' => self::ref(8, 1.0, 5.0)];
        $this->assertTrue(AuditGrader::confirmedBy($confirm, 'archeoferroviaire'));
        $this->assertFalse(AuditGrader::confirmedBy(['osm' => self::ref(7, 0.5, 1.0)], 'ohm'), 'couverture < 80 %');
        $this->assertFalse(AuditGrader::confirmedBy(['osm' => self::ref(10, 1.01, 2.0)], 'ohm'), 'médiane > 1 m');
        $this->assertFalse(AuditGrader::confirmedBy(['osm' => self::ref(10, 0.5, 5.01)], 'ohm'), 'coude : p95 > 5 m');
        $this->assertFalse(AuditGrader::confirmedBy($confirm, 'osm'), 'même source que le tracé');
        $this->assertFalse(AuditGrader::confirmedBy(['ign-historique' => self::ref(10, 0.0, 0.0)], 'sncf'), 'source précise seulement');
        // La voie d'une autre ligne n'est jamais dans `found` (T1) : elle ne confirme rien.
        $this->assertFalse(AuditGrader::confirmedBy(['bdtopo' => self::ref(0, null, null, all: [10, 0.0, 0.0])], 'sncf'));

        $this->assertSame([AuditGrade::Good, ['confirmed_by_precise_source']],
            array_values(array_intersect_key(AuditGrader::trace('ohm', 1.0, confirmed: true), ['grade' => 0, 'reasons' => 0])));
        $this->assertSame([AuditGrade::Good, ['confirmed_by_precise_source']],
            array_values(array_intersect_key(AuditGrader::trace('sncf', 1.0, confirmed: true), ['grade' => 0, 'reasons' => 0])));
        // Le recalage l'emporte comme raison ; une source précise reste « précise » ; source inconnue : rien.
        $this->assertSame(['recalibrated'], AuditGrader::trace('sncf', 1.0, aligned: true, confirmed: true)['reasons']);
        $this->assertSame(['precise_source'], AuditGrader::trace('bdtopo', 1.0, confirmed: true)['reasons']);
        $this->assertSame(AuditGrade::Unknown, AuditGrader::trace(null, 1.0, confirmed: true)['grade']);
        // Trace inutilisée dans un trou voisin : à revoir malgré la confirmation.
        $this->assertSame(AuditGrade::Fair, AuditGrader::trace('ohm', 1.0, unusedTraceInGap: true, confirmed: true)['grade']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function current(string $source, bool $agrees, array $extra = []): array
    {
        return $extra + ['source' => $source, 'kind' => 'current', 'agrees' => $agrees, 'weak' => false, 'near_date' => false,
            'priority' => $source === 'osm' ? 5 : 3, 'observed' => []];
    }

    public function test_t3_a_lone_bdtopo_or_sold_sncf_observation_is_outvoted_by_two(): void
    {
        // Saint-Maur : SNCF « déclassée vendue » (cédée à la RATP), BD TOPO et OSM disent la voie en service.
        $sold = [self::current('sncf', false, ['observed' => ['statut' => 'Déclassée vendue']]), self::current('bdtopo', true), self::current('osm', true)];
        $out = SegmentAuditor::outvoted($sold);
        $this->assertTrue($out[0]['weak'] && $out[0]['outvoted']);
        $c = AuditGrader::classification(array_map(fn (array $v): array => array_intersect_key($v, array_flip(['source', 'agrees', 'weak', 'near_date', 'priority', 'outvoted'])), $out));
        $this->assertSame([AuditGrade::Good, ['sources_agree', 'observation_outvoted']], [$c['grade'], $c['reasons']]);

        // Aix-Marchandises : BD TOPO « en service » seule contre SNCF et OSM.
        $bd = SegmentAuditor::outvoted([self::current('sncf', true), self::current('bdtopo', false), self::current('osm', true)]);
        $this->assertTrue($bd[1]['outvoted']);
    }

    public function test_t3_keeps_real_contradictions(): void
    {
        $unchanged = fn (array $verdicts): bool => SegmentAuditor::outvoted($verdicts) === $verdicts;
        // OSM « abandoned » reste une vraie contradiction.
        $this->assertTrue($unchanged([self::current('sncf', true), self::current('bdtopo', true), self::current('osm', false)]));
        // Autre statut SNCF que « déclassée vendue ».
        $this->assertTrue($unchanged([self::current('sncf', false, ['observed' => ['statut' => 'Fermée']]), self::current('bdtopo', true), self::current('osm', true)]));
        // Un seul accord actuel fort.
        $this->assertTrue($unchanged([self::current('bdtopo', false), self::current('osm', true), self::current('sncf', true, ['weak' => true])]));
        // Deux contradictions fortes.
        $this->assertTrue($unchanged([self::current('bdtopo', false), self::current('osm', true), self::current('sncf', true),
            ['source' => 'wikipedia', 'kind' => 'events', 'agrees' => false, 'weak' => false, 'near_date' => false, 'priority' => 1]]));
        // Une chronologie ne compte pas comme observation actuelle d'accord.
        $this->assertTrue($unchanged([self::current('bdtopo', false), self::current('osm', true),
            ['source' => 'wikipedia', 'kind' => 'events', 'agrees' => true, 'weak' => false, 'near_date' => false, 'priority' => 1]]));
    }

    public function test_t4_continuity_keeps_a_never_closed_line_good_in_past_eras(): void
    {
        $weak = [['source' => 'wikidata', 'agrees' => true, 'weak' => true]];
        $this->assertSame([AuditGrade::Good, ['continuous_service']], array_values(AuditGrader::classification($weak, [], true)));
        $this->assertSame([AuditGrade::Fair, ['weak_sources_only']], array_values(AuditGrader::classification($weak)));
        // Un avis contraire, même faible, garde le doute ; aucune source : toujours inconnu.
        $contra = [...$weak, ['source' => 'estimation-reseau', 'agrees' => false, 'weak' => true]];
        $this->assertSame(['weak_contradiction'], AuditGrader::classification($contra, [], true)['reasons']);
        $this->assertSame(AuditGrade::Unknown, AuditGrader::classification([], [], true)['grade']);
        // Une règle métier violée l'emporte.
        $this->assertSame(AuditGrade::Poor, AuditGrader::classification($weak, ['passenger_only_off_lgv'], true)['grade']);
    }

    /** Événement au format de SegmentAuditor::events. */
    private static function ev(string $type, string $date, bool $whole = true, bool $unlocated = false): array
    {
        return ['type' => $type, 'date' => $date, 'whole' => $whole, 'unlocated' => $unlocated];
    }

    public function test_r2_only_an_end_dated_before_the_era_breaks_continuity(): void
    {
        $this->assertFalse(SegmentAuditor::blocksContinuity([1 => self::ev('opening', '1880-01-01'), 2 => self::ev('electrification', '1960-01-01')], [], '2000-12-31'));
        foreach (['closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling'] as $type) {
            $this->assertTrue(SegmentAuditor::blocksContinuity([1 => self::ev('opening', '1880-01-01'), 2 => self::ev($type, '1990-01-01')], [], '2000-12-31'), $type);
            // Après l'ère, la fin ne dit rien de cette ère.
            $this->assertFalse(SegmentAuditor::blocksContinuity([1 => self::ev('opening', '1880-01-01'), 2 => self::ev($type, '2015-01-01')], [], '2000-12-31'), $type);
        }
        // Le dernier jour de l'ère compte, le lendemain non.
        $this->assertTrue(SegmentAuditor::blocksContinuity([2 => self::ev('closure', '2000-12-31')], [], '2000-12-31'));
        $this->assertFalse(SegmentAuditor::blocksContinuity([2 => self::ev('closure', '2001-01-01')], [], '2000-12-31'));
    }

    public function test_r2_a_localized_end_counts_only_on_its_own_section(): void
    {
        $events = [7 => self::ev('closure', '1970-01-01', whole: false)];
        $this->assertFalse(SegmentAuditor::blocksContinuity($events, [], '2000-12-31'));
        $this->assertFalse(SegmentAuditor::blocksContinuity($events, [8], '2000-12-31'));
        $this->assertTrue(SegmentAuditor::blocksContinuity($events, [7], '2000-12-31'));
        // Non localisée : on ne sait pas où, elle compte partout.
        $this->assertTrue(SegmentAuditor::blocksContinuity([7 => self::ev('closure', '1970-01-01', whole: false, unlocated: true)], [], '2000-12-31'));
    }

    public function test_r2_a_reopening_before_the_era_lifts_the_end(): void
    {
        $end = [1 => self::ev('closure', '1970-01-01')];
        $this->assertFalse(SegmentAuditor::blocksContinuity($end + [2 => self::ev('reopening', '1985-01-01')], [], '2000-12-31'));
        $this->assertFalse(SegmentAuditor::blocksContinuity($end + [2 => self::ev('opening', '1985-01-01')], [], '2000-12-31'));
        // Réouverture après l'ère, ou localisée ailleurs : la fin tient.
        $this->assertTrue(SegmentAuditor::blocksContinuity($end + [2 => self::ev('reopening', '2010-01-01')], [], '2000-12-31'));
        $this->assertTrue(SegmentAuditor::blocksContinuity($end + [2 => self::ev('reopening', '1985-01-01', whole: false)], [], '2000-12-31'));
        $this->assertFalse(SegmentAuditor::blocksContinuity($end + [2 => self::ev('reopening', '1985-01-01', whole: false)], [2], '2000-12-31'));
        // Une réouverture plus ancienne que la fin ne la lève pas.
        $this->assertTrue(SegmentAuditor::blocksContinuity([2 => self::ev('reopening', '1960-01-01')] + $end, [], '2000-12-31'));
    }
}
