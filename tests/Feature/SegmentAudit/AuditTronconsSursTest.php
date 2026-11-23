<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\AuditGrader;
use App\Services\SegmentAudit\SegmentAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Règles `2026-11-23.2` (aprem-2111/troncons-surs.md, décomposition des kilomètres non sûrs de 2030) :
 * R8 (points en souterrain hors de l'écart du placement), R9 (train touristique confirmé : une observation actuelle
 * qui dit la voie fermée parle du trafic commercial), R10 (l'avis d'une chronologie que toutes les observations
 * actuelles fortes contredisent ne retient plus la note), R11 (étiquette OSM seule contre SNCF Réseau et une autre
 * observation forte).
 *
 * Ligne auditée L (code 654370) ; tronçons est-ouest sur 48° N entre 2,00° et 2,01° E (745 m).
 */
class AuditTronconsSursTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const AS_OF = '2026-11-23';

    private const TRACK = 'LINESTRING(2.0 48.0, 2.01 48.0)';

    /** La même voie relevée 3 m plus au nord (0,000027° de latitude). */
    private const NEAR = 'LINESTRING(2.0 48.000027, 2.01 48.000027)';

    /** La voie en tunnel, relevée 20 m plus au nord. */
    private const TUNNEL = 'LINESTRING(2.006 48.00018, 2.01 48.00018)';

    private Era $now;

    private Line $audited;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
        $this->audited = Line::factory()->create(['slug' => 'rfn-654370', 'code' => '654370', 'name' => 'Ligne de Livron', 'is_lgv' => false, 'network' => 'principal']);
        $this->auditCommune(1.99, 47.99, 2.02, 48.01);
    }

    /** @return array<string, mixed> */
    private function audit(int $segment): array
    {
        return self::auditedSegment(app(SegmentAuditor::class)->auditLine($this->audited->id, [2030], self::AS_OF), $this->now->id, $segment);
    }

    private function sncfForm(string $libelle): void
    {
        DB::table('sncf_line_geometries')->insert(['code_ligne' => $this->audited->code, 'libelle' => $libelle, 'pk_debut_m' => 0, 'pk_fin_m' => 745,
            'geom' => DB::raw("ST_GeomFromText('".self::TRACK."', 4326)"), 'created_at' => now(), 'updated_at' => now()]);
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

    public function test_rule_version_is_bumped(): void
    {
        $this->assertSame('2026-11-23.2', AuditGrader::RULE_VERSION);
    }

    public function test_points_in_a_tunnel_do_not_count_in_the_placement(): void
    {
        // Livron - Aspres : la voie OSM suit le tracé BD TOPO à 3 m à l'air libre, à 20 m dans le tunnel (40 % du tronçon).
        $this->auditTrack('osm', 'LINESTRING(2.0 48.000027, 2.006 48.000027)', $this->audited->id);
        $this->auditTrack('osm', self::TUNNEL, $this->audited->id, properties: ['tunnel' => 'yes']);
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'in_service', 'mixed', 'bdtopo');

        $row = $this->audit($seg);

        $this->assertSame('good', $row['placement_grade']);
        $this->assertContains('underground_excluded', $row['evidence']['placement']['reasons']);
        $this->assertGreaterThan(0, $row['evidence']['placement']['references']['osm']['underground']);
        $this->assertLessThanOrEqual(5.0, $row['evidence']['placement']['p95_m']);
    }

    public function test_the_same_gap_in_the_open_air_stays_over_tolerance(): void
    {
        $this->auditTrack('osm', 'LINESTRING(2.0 48.000027, 2.006 48.000027)', $this->audited->id);
        $this->auditTrack('osm', self::TUNNEL, $this->audited->id);
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'in_service', 'mixed', 'bdtopo');

        $row = $this->audit($seg);

        $this->assertSame('fair', $row['placement_grade']);
        $this->assertSame(['p95_over_tolerance'], $row['evidence']['placement']['reasons']);
    }

    public function test_a_section_entirely_underground_is_not_judged(): void
    {
        // BD TOPO sous le sol sur tout le tronçon : aucun point relevé d'avion, ni « faux » ni « sûr ».
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['position_par_rapport_au_sol' => '-1']);
        $this->auditTrack('osm', 'LINESTRING(2.0 48.00018, 2.01 48.00018)', $this->audited->id, properties: ['tunnel' => 'yes']);
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($seg);

        $this->assertSame('unknown', $row['placement_grade']);
        $this->assertSame(['underground'], $row['evidence']['placement']['reasons']);
    }

    public function test_a_confirmed_tourist_train_is_not_contradicted_by_a_closed_track_observation(): void
    {
        // Train du Pays Cathare et du Fenouillèdes : SNCF Réseau « fermée et mise à disposition de tiers », OSM « disused ».
        $this->auditEvent($this->audited, 'opening', '1904-01-01', 'wikipedia');
        $this->auditEvent($this->audited, 'closure', '1990-01-01', 'wikipedia');
        $this->auditEvent($this->audited, 'heritage_start', '2001-06-01', 'heritage');
        $this->sncfForm('Fermée et mise à disposition de tiers');
        $this->auditTrack('osm', self::NEAR, $this->audited->id, 'disused');
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['etat_de_l_objet' => 'En service']);
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'in_service', 'unknown', 'bdtopo', heritage: true);

        $row = $this->audit($seg);

        $this->assertTrue(self::verdict($row, 'sncf')['agrees']);
        $this->assertTrue(self::verdict($row, 'sncf')['heritage']);
        $this->assertTrue(self::verdict($row, 'osm')['agrees']);
        $this->assertSame('good', $row['classification_grade']);
    }

    public function test_a_tourist_train_without_heritage_source_keeps_the_contradiction(): void
    {
        $this->auditEvent($this->audited, 'opening', '1904-01-01', 'wikipedia');
        $this->sncfForm('Neutralisée');
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['etat_de_l_objet' => 'En service']);
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'in_service', 'unknown', 'bdtopo', heritage: true);

        $row = $this->audit($seg);

        $this->assertFalse(self::verdict($row, 'sncf')['agrees']);
        $this->assertNotSame('good', $row['classification_grade']);
    }

    public function test_a_removed_track_still_contradicts_a_tourist_train(): void
    {
        $this->auditEvent($this->audited, 'heritage_start', '2001-06-01', 'heritage');
        $this->auditTrack('osm', self::NEAR, $this->audited->id, 'abandoned');
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'in_service', 'unknown', 'bdtopo', heritage: true);

        $this->assertFalse(self::verdict($this->audit($seg), 'osm')['agrees']);
    }

    public function test_an_outweighed_chronology_no_longer_holds_back_the_grade(): void
    {
        // Gisors - Serqueux : la saisie à la main finissait sur la fermeture de 2009 ; SNCF Réseau, BD TOPO et OSM
        // voient la section exploitée.
        $this->auditEvent($this->audited, 'opening', '1870-01-01', 'manual', 'places', self::TRACK);
        $this->auditEvent($this->audited, 'closure', '2009-01-19', 'manual', 'places', self::TRACK);
        $this->auditEvent($this->audited, 'opening', '1870-01-01', 'ohm', 'trace', self::TRACK);
        $this->sncfForm('Exploitée');
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['etat_de_l_objet' => 'En service']);
        $this->auditTrack('osm', self::NEAR, $this->audited->id);
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'in_service', 'freight', 'bdtopo');

        $row = $this->audit($seg);

        $manual = self::verdict($row, 'manual');
        $this->assertFalse($manual['agrees']);
        $this->assertTrue($manual['outweighed']);
        $this->assertSame('good', $row['classification_grade']);
    }

    public function test_a_weak_chronology_that_is_not_outweighed_still_holds_back_the_grade(): void
    {
        $this->assertSame('fair', AuditGrader::classification([
            ['source' => 'manual', 'agrees' => false, 'weak' => true, 'priority' => 0],
            ['source' => 'sncf', 'agrees' => true, 'priority' => 3],
        ])['grade']->value);
        $this->assertSame('good', AuditGrader::classification([
            ['source' => 'manual', 'agrees' => false, 'weak' => true, 'priority' => 0, 'outweighed' => true],
            ['source' => 'sncf', 'agrees' => true, 'priority' => 3],
        ])['grade']->value);
    }

    public function test_a_lone_osm_disused_tag_is_outvoted_by_the_network_register(): void
    {
        // Castelsarrasin - Beaumont-de-Lomagne : SNCF Réseau « exploitée », BD TOPO « en service », OSM « disused ».
        $this->sncfForm('Exploitée');
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['etat_de_l_objet' => 'En service']);
        $this->auditTrack('osm', self::NEAR, $this->audited->id, 'disused');
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'in_service', 'freight', 'bdtopo');

        $row = $this->audit($seg);

        $osm = self::verdict($row, 'osm');
        $this->assertTrue($osm['outvoted']);
        $this->assertContains('observation_outvoted', $row['evidence']['classification']['reasons']);
        $this->assertSame('good', $row['classification_grade']);
    }

    public function test_a_lone_osm_abandoned_tag_is_outvoted_only_when_sncf_keeps_the_track_in_place(): void
    {
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['etat_de_l_objet' => 'Non exploité']);
        $this->auditTrack('osm', self::NEAR, $this->audited->id, 'abandoned');
        $seg = $this->auditSegment($this->audited, $this->now, self::TRACK, 'abandoned', 'unknown', 'bdtopo');

        $this->sncfForm('Fermée');
        $this->assertFalse(self::verdict($this->audit($seg), 'osm')['weak']);

        DB::table('sncf_line_geometries')->update(['libelle' => 'Fermée avec maintien en place de la voie']);
        $row = $this->audit($seg);
        $this->assertTrue(self::verdict($row, 'osm')['outvoted']);
        $this->assertNotSame('poor', $row['classification_grade']);
    }
}
