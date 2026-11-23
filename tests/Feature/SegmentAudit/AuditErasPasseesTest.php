<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\SegmentAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Règles des ères passées (version 2026-11-21.6, aprem-2111/audit-eres-passees.md) mesurées en base sur le
 * réseau d'essai : R1 (le train touristique ne parle qu'à partir de son début), R3 (une fin des voyageurs
 * seule n'est pas une fermeture), R4 (tracé OSM de la ligne, seule la BD TOPO à plus de 15 m).
 *
 * Ligne auditée L (code 654350) ; tronçons est-ouest sur 48° N entre 2,00° et 2,01° E. 0,00018° de latitude
 * vaut 20 m, 0,00009° environ 10 m.
 */
class AuditErasPasseesTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const AS_OF = '2026-11-21';

    private const TRACK = 'LINESTRING(2.0 48.0, 2.01 48.0)';

    private Era $e1950;

    private Era $e1970;

    private Era $e2000;

    private Era $current;

    private Line $audited;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e1950 = Era::factory()->create(['year' => 1950, 'label' => 'Années 1950']);
        $this->e1970 = Era::factory()->create(['year' => 1970, 'label' => 'Années 1970']);
        $this->e2000 = Era::factory()->create(['year' => 2000, 'label' => 'Années 2000']);
        $this->current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
        $this->audited = Line::factory()->create(['slug' => 'rfn-654350', 'code' => '654350', 'name' => 'Ligne de Roméo', 'is_lgv' => false, 'network' => 'principal']);
    }

    /** @return array<string, mixed> */
    private function audit(int $segment, Era $era): array
    {
        return self::auditedSegment(app(SegmentAuditor::class)->auditLine($this->audited->id, [$era->year], self::AS_OF), $era->id, $segment);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private static function verdict(array $row, string $source): ?array
    {
        foreach ($row['evidence']['classification']['verdicts'] as $v) {
            if ($v['source'] === $source) {
                return $v;
            }
        }

        return null;
    }

    public function test_heritage_source_is_silent_before_the_tourist_train_starts(): void
    {
        // Salbris - Le Blanc (#88) : ligne commerciale de 1902, train touristique du Bas-Berry en 1995.
        $this->auditEvent($this->audited, 'opening', '1902-10-06');
        $this->auditEvent($this->audited, 'heritage_start', '1995-01-01', 'heritage');
        $seg = $this->auditSegment($this->audited, $this->e1950, self::TRACK, 'in_service');

        $row = $this->audit($seg, $this->e1950);

        $this->assertNull(self::verdict($row, 'heritage'));
        $this->assertSame('good', $row['classification_grade']);
        $this->assertContains('sources_agree', $row['evidence']['classification']['reasons']);
    }

    public function test_heritage_source_speaks_once_the_tourist_train_runs(): void
    {
        $this->auditEvent($this->audited, 'opening', '1902-10-06');
        $this->auditEvent($this->audited, 'closure', '1980-09-27');
        $this->auditEvent($this->audited, 'heritage_start', '1995-01-01', 'heritage');
        $seg = $this->auditSegment($this->audited, $this->e2000, self::TRACK, 'in_service', 'unknown', heritage: true);

        $verdict = self::verdict($this->audit($seg, $this->e2000), 'heritage');

        $this->assertNotNull($verdict);
        $this->assertTrue($verdict['agrees']);
        $this->assertSame(1995, $verdict['first_opening_year']);
    }

    public function test_heritage_start_of_a_source_with_ends_only_counts_its_ends(): void
    {
        // Vertaizon - Billom (#69) : saisie à la main, fin des voyageurs 1960, fermeture 2002, vélorail 2013.
        // Avant le vélorail, elle ne date que des fins : aucune n'est encore une fermeture en 1970.
        $this->auditEvent($this->audited, 'closure_passenger', '1960-12-12', 'manual');
        $this->auditEvent($this->audited, 'closure', '2002-12-12', 'manual');
        $this->auditEvent($this->audited, 'heritage_start', '2013-04-17', 'manual');
        $open = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'freight');

        // Elle ne dit rien d'une fermeture, mais sa fin des voyageurs de 1960 confirme le fret seul du tronçon (R6, règles .9).
        $verdict = self::verdict($this->audit($open, $this->e1970), 'manual');
        $this->assertTrue($verdict['agrees']);
        $this->assertSame(['class' => 'open', 'traffic' => 'freight'], $verdict['expected']);
    }

    public function test_heritage_start_does_not_hide_an_earlier_full_closure(): void
    {
        $this->auditEvent($this->audited, 'closure', '1960-12-12', 'manual');
        $this->auditEvent($this->audited, 'heritage_start', '2013-04-17', 'manual');
        $open = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'freight');

        $verdict = self::verdict($this->audit($open, $this->e1970), 'manual');

        $this->assertNotNull($verdict);
        $this->assertFalse($verdict['agrees']);
        $this->assertSame('expected_closed', $verdict['why']);
    }

    public function test_passenger_end_alone_agrees_with_a_freight_section(): void
    {
        // Ouverture aux voyageurs seulement, puis fin des voyageurs : sans R3, la source attendait une ligne fermée.
        $this->auditEvent($this->audited, 'opening_passenger', '1900-05-01');
        $this->auditEvent($this->audited, 'closure_passenger', '1960-12-12');
        $seg = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'freight');

        $row = $this->audit($seg, $this->e1970);
        $verdict = self::verdict($row, 'wikipedia');

        $this->assertNotNull($verdict);
        $this->assertTrue($verdict['agrees']);
        $this->assertTrue($verdict['passenger_end_only']);
        $this->assertSame(['class' => 'open', 'traffic' => 'freight'], $verdict['expected']);
        $this->assertSame('good', $row['classification_grade']);
    }

    public function test_passenger_end_rule_leaves_closed_and_mixed_sections_alone(): void
    {
        $this->auditEvent($this->audited, 'opening_passenger', '1900-05-01');
        $this->auditEvent($this->audited, 'closure_passenger', '1960-12-12');
        $mixed = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'mixed');
        $closed = $this->auditSegment($this->audited, $this->e1970, 'LINESTRING(2.02 48.0, 2.03 48.0)', 'abandoned', 'unknown');

        $audit = app(SegmentAuditor::class)->auditLine($this->audited->id, [1970], self::AS_OF);
        $mixedVerdict = self::verdict(self::auditedSegment($audit, $this->e1970->id, $mixed), 'wikipedia');
        $closedVerdict = self::verdict(self::auditedSegment($audit, $this->e1970->id, $closed), 'wikipedia');

        $this->assertNotNull($mixedVerdict);
        $this->assertFalse($mixedVerdict['agrees']);
        $this->assertFalse($mixedVerdict['passenger_end_only']);
        $this->assertNotNull($closedVerdict);
        $this->assertTrue($closedVerdict['agrees']);
        $this->assertFalse($closedVerdict['passenger_end_only']);
    }

    public function test_passenger_end_followed_by_a_closure_still_contradicts_a_freight_section(): void
    {
        $this->auditEvent($this->audited, 'opening_passenger', '1900-05-01');
        $this->auditEvent($this->audited, 'closure_passenger', '1960-12-12');
        $this->auditEvent($this->audited, 'closure', '1965-06-01');
        $seg = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'freight');

        $verdict = self::verdict($this->audit($seg, $this->e1970), 'wikipedia');

        $this->assertNotNull($verdict);
        $this->assertFalse($verdict['agrees']);
        $this->assertFalse($verdict['passenger_end_only']);
    }

    public function test_passenger_and_freight_ends_together_still_contradict_a_freight_section(): void
    {
        // Aubrais - Malesherbes (lignes-oubliées) : fin des voyageurs et fin du fret le même jour, c'est une fermeture.
        $this->auditEvent($this->audited, 'opening', '1872-08-12');
        $this->auditEvent($this->audited, 'closure_passenger', '1969-11-04');
        $this->auditEvent($this->audited, 'closure_freight', '1969-11-04');
        $seg = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'freight');

        $verdict = self::verdict($this->audit($seg, $this->e1970), 'wikipedia');

        $this->assertNotNull($verdict);
        $this->assertFalse($verdict['agrees']);
        $this->assertFalse($verdict['passenger_end_only']);
    }

    public function test_osm_trace_of_the_line_with_only_a_distant_bdtopo_track_is_unknown_in_a_past_era(): void
    {
        // Bédarieux (#78) : tracé OSM rattaché à la ligne, voie BD TOPO libre à 20 m.
        $this->auditTrack('osm', self::TRACK, $this->audited->id);
        $this->auditTrack('bdtopo', 'LINESTRING(2.0 48.00018, 2.01 48.00018)');
        $seg = $this->auditSegment($this->audited, $this->e1950, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($seg, $this->e1950);

        $this->assertSame('unknown', $row['placement_grade']);
        $this->assertSame(['distant_reference_only'], $row['evidence']['placement']['reasons']);
        $this->assertSame('bdtopo', $row['evidence']['placement']['reference']);
        $this->assertEqualsWithDelta(20.0, $row['evidence']['placement']['median_m'], 1.0);
    }

    public function test_bdtopo_track_within_fifteen_metres_keeps_the_poor_placement(): void
    {
        $this->auditTrack('osm', self::TRACK, $this->audited->id);
        $this->auditTrack('bdtopo', 'LINESTRING(2.0 48.00009, 2.01 48.00009)');
        $seg = $this->auditSegment($this->audited, $this->e1950, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($seg, $this->e1950);

        $this->assertSame('poor', $row['placement_grade']);
        $this->assertContains('median_over_tolerance', $row['evidence']['placement']['reasons']);
    }

    public function test_osm_track_not_attached_to_the_line_keeps_the_poor_placement(): void
    {
        $this->auditTrack('osm', self::TRACK);
        $this->auditTrack('bdtopo', 'LINESTRING(2.0 48.00018, 2.01 48.00018)');
        $seg = $this->auditSegment($this->audited, $this->e1950, self::TRACK, 'in_service', 'mixed', 'osm');

        $this->assertSame('poor', $this->audit($seg, $this->e1950)['placement_grade']);
    }

    public function test_distant_reference_rule_does_not_apply_to_the_current_era(): void
    {
        $this->auditTrack('osm', self::TRACK, $this->audited->id);
        $this->auditTrack('bdtopo', 'LINESTRING(2.0 48.00018, 2.01 48.00018)');
        $seg = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($seg, $this->current);

        $this->assertSame('poor', $row['placement_grade']);
        $this->assertNotContains('distant_reference_only', $row['evidence']['placement']['reasons']);
    }
}
