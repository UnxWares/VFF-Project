<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\SegmentAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Règles de midi du 21/11/2026 (version 2026-11-21.4, matin-2111/audit-reglages.md ; .5 l'après-midi) mesurées en base,
 * en complément de AuditReglagesTest : T1 (voie d'une autre ligne hors du placement) pour un tronçon
 * en service et pour une ère passée, T2 (source précise indépendante), T3 (observation seule mise en
 * minorité) et T4 (continuité des ères passées) avec leurs cas d'exclusion.
 *
 * Ligne auditée L (code 654340) et ligne voisine N (654341) ; tronçons est-ouest sur 48° N entre
 * 2,00° et 2,01° E. 0,00018° de latitude vaut 20 m, 0,00005° environ 5,5 m.
 */
class AuditReglagesPathsTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const AS_OF = '2026-11-21';

    private const TRACK = 'LINESTRING(2.0 48.0, 2.01 48.0)';

    /** Voie parallèle à 20 m au nord. */
    private const BESIDE = 'LINESTRING(2.0 48.00018, 2.01 48.00018)';

    private Era $past;

    private Era $current;

    private Line $audited;

    private Line $neighbour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 2000, 'label' => 'Années 2000']);
        $this->current = Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
        $this->audited = Line::factory()->create(['slug' => 'rfn-654340', 'code' => '654340', 'name' => 'Ligne de Papa', 'is_lgv' => false, 'network' => 'principal']);
        $this->neighbour = Line::factory()->create(['slug' => 'rfn-654341', 'code' => '654341', 'name' => 'Ligne de Québec', 'is_lgv' => false, 'network' => 'principal']);
    }

    /** @return array<string, mixed> */
    private function audit(int $segment, ?Era $era = null): array
    {
        $era ??= $this->current;

        return self::auditedSegment(app(SegmentAuditor::class)->auditLine($this->audited->id, [$era->year], self::AS_OF), $era->id, $segment);
    }

    /** Forme SNCF Réseau de la ligne auditée sur $wkt. */
    private function sncfForm(string $libelle, string $wkt = self::TRACK): void
    {
        DB::table('sncf_line_geometries')->insert(['code_ligne' => $this->audited->code, 'libelle' => $libelle, 'pk_debut_m' => 0, 'pk_fin_m' => 745,
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"), 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function verdict(array $row, string $source): array
    {
        foreach ($row['evidence']['classification']['verdicts'] as $v) {
            if ($v['source'] === $source) {
                return $v;
            }
        }
        self::fail("aucun avis de {$source}");
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: string, 1: list<string>}
     */
    private static function grade(array $row, string $check): array
    {
        return [$row[$check.'_grade'], $row['evidence'][$check]['reasons']];
    }

    // ─── T1 : voie d'une autre ligne ─────────────────────────────────────

    public function test_t1_a_segment_in_service_on_another_line_track_is_to_review(): void
    {
        $this->auditTrack('bdtopo', self::TRACK, $this->neighbour->id);
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed');

        $row = $this->audit($segment);

        $this->assertSame(['fair', ['on_other_line_track']], self::grade($row, 'placement'));
        $this->assertSame('bdtopo', $row['evidence']['placement']['reference']);
        $this->assertEqualsWithDelta(20.0, $row['evidence']['placement']['median_m'], 0.5);
        $this->assertSame(1.0, $row['evidence']['placement']['coverage']);
        $own = $row['evidence']['placement']['references']['bdtopo']['own'];
        $this->assertSame($row['evidence']['placement']['references']['bdtopo']['points'], $own['found']);
        $this->assertEqualsWithDelta(20.0, $own['median_m'], 0.5);
    }

    public function test_t1_in_a_past_era_the_segment_is_wrong(): void
    {
        $this->auditTrack('bdtopo', self::TRACK, $this->neighbour->id);
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'in_service', 'mixed');

        $this->assertSame(['poor', ['on_other_line_track']], self::grade($this->audit($segment, $this->past), 'placement'));
    }

    public function test_t1_a_line_track_under_8_m_makes_it_a_shared_track(): void
    {
        $this->auditTrack('osm', self::TRACK, $this->neighbour->id);
        $this->auditTrack('osm', 'LINESTRING(2.0 48.00005, 2.01 48.00005)', $this->audited->id);
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');

        $row = $this->audit($segment);

        $this->assertSame(['good', ['shared_track']], self::grade($row, 'placement'));
        $this->assertSame('osm', $row['evidence']['placement']['reference']);
        $this->assertEqualsWithDelta(0.0, $row['evidence']['placement']['median_m'], 0.1);
    }

    public function test_t1_a_line_track_beyond_60_m_is_not_its_own_track(): void
    {
        $this->auditTrack('bdtopo', self::TRACK, $this->neighbour->id);
        // 67 m au nord : hors de OWN_TRACK_MAX_M, et au-delà du rayon de référence de 50 m.
        $this->auditTrack('bdtopo', 'LINESTRING(2.0 48.0006, 2.01 48.0006)', $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');

        $row = $this->audit($segment);

        $this->assertSame(['good', ['shared_track']], self::grade($row, 'placement'));
        $this->assertSame(0, $row['evidence']['placement']['references']['bdtopo']['own']['found']);
        $this->assertSame(0, $row['evidence']['placement']['references']['bdtopo']['found']);
    }

    public function test_t1_measures_the_three_gaps_per_point(): void
    {
        $this->auditTrack('bdtopo', self::TRACK, $this->neighbour->id);
        $this->auditTrack('bdtopo', 'LINESTRING(2.0 48.00009, 2.01 48.00009)');
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
        // Relevé IGN historique lointain : écarté des références.
        $this->auditTrack('ign-historique', 'LINESTRING(2.0 48.01, 2.01 48.01)');
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'abandoned', 'unknown');

        $refs = $this->audit($segment)['evidence']['placement']['references'];

        $this->assertSame(['bdtopo', 'osm'], array_keys($refs), 'sans aucune voie proche, le relevé IGN historique est retiré ; OSM reste, vide');
        $this->assertSame([15, 0, 0], [$refs['osm']['points'], $refs['osm']['found'], $refs['osm']['all']['found']]);
        $this->assertSame(15, $refs['bdtopo']['points'], '745 m, un point tous les 50 m');
        $this->assertEqualsWithDelta(10.0, $refs['bdtopo']['median_m'], 0.2, 'voie libre la plus proche');
        $this->assertEqualsWithDelta(0.0, $refs['bdtopo']['all']['median_m'], 0.1, 'toutes les voies');
        $this->assertEqualsWithDelta(0.0, $refs['bdtopo']['all']['p95_m'], 0.1);
        $this->assertEqualsWithDelta(20.0, $refs['bdtopo']['own']['median_m'], 0.3, 'voie de la ligne');
        $this->assertSame([15, 15, 15], [$refs['bdtopo']['found'], $refs['bdtopo']['all']['found'], $refs['bdtopo']['own']['found']]);
    }

    // ─── T2 : source précise indépendante ────────────────────────────────

    /** @return iterable<string, array{0: string}> */
    public static function lessPreciseSources(): iterable
    {
        yield 'OHM (moyenne)' => ['ohm'];
        yield 'formes SNCF (peu précise)' => ['sncf'];
    }

    #[DataProvider('lessPreciseSources')]
    public function test_t2_a_free_precise_track_confirms_the_trace(string $source): void
    {
        $this->auditTrack('osm', self::TRACK);
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', $source);

        $row = $this->audit($segment);

        $this->assertSame(['good', ['confirmed_by_precise_source']], self::grade($row, 'trace'));
        $this->assertSame(1.0, $row['evidence']['trace']['precise_coverage']);
        $this->assertNotSame('good', $row['placement_grade'], 'sinon le tracé serait « recalé »');
    }

    public function test_t2_the_track_of_another_line_never_confirms(): void
    {
        $this->auditTrack('osm', self::TRACK, $this->neighbour->id);
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'ohm');

        $row = $this->audit($segment);

        $this->assertSame(['fair', ['medium_source']], self::grade($row, 'trace'));
        $this->assertSame(['fair', ['on_other_line_track']], self::grade($row, 'placement'));
    }

    public function test_t2_the_trace_source_never_confirms_itself(): void
    {
        $this->auditTrack('osm', self::TRACK);
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');

        $this->assertSame(['good', ['precise_source']], self::grade($this->audit($segment), 'trace'));
    }

    /**
     * Corrigé le 21/11 après-midi (règles 2026-11-21.5) : AuditGrader::onOtherLine prenait une voie libre
     * (rattachée à aucune ligne) qui colle au tronçon pour la voie d'une autre ligne. Désormais
     * `on_other_line_track` et `shared_track` demandent que la voie qui colle ne soit ni libre ni de la
     * ligne ; ici BD TOPO, première référence, garde sa note (voie de la ligne à 20 m), et le tracé reste
     * confirmé par la voie OSM libre (T2).
     */
    public function test_t2_a_free_track_is_not_the_track_of_another_line(): void
    {
        $this->auditTrack('osm', self::TRACK);
        $this->auditTrack('bdtopo', self::BESIDE, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'ohm');

        $row = $this->audit($segment);

        $this->assertSame(['poor', ['median_over_tolerance']], self::grade($row, 'placement'));
        $this->assertSame('bdtopo', $row['evidence']['placement']['reference']);
        $this->assertSame(['good', ['confirmed_by_precise_source']], self::grade($row, 'trace'));
    }

    // ─── T3 : observation seule mise en minorité ─────────────────────────

    public function test_t3_a_lone_bdtopo_contradiction_is_outvoted(): void
    {
        $this->sncfForm('Exploitée');
        $this->auditTrack('osm', self::TRACK, $this->audited->id);
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['etat_de_l_objet' => 'Non exploité']);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($segment);
        $bdtopo = self::verdict($row, 'bdtopo');

        $this->assertSame([false, true, true], [$bdtopo['agrees'], $bdtopo['weak'], $bdtopo['outvoted']]);
        $this->assertArrayNotHasKey('outvoted', self::verdict($row, 'sncf'));
        $this->assertSame(['good', ['sources_agree', 'observation_outvoted']], self::grade($row, 'classification'));
    }

    public function test_t3_a_sncf_sold_declassification_is_outvoted(): void
    {
        $this->sncfForm('Déclassée vendue');
        $this->auditTrack('osm', self::TRACK, $this->audited->id);
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($segment);
        $sncf = self::verdict($row, 'sncf');

        $this->assertSame(['statut' => 'Déclassée vendue'], $sncf['observed']);
        $this->assertSame([false, true], [$sncf['agrees'], $sncf['outvoted']]);
        $this->assertSame(['good', ['sources_agree', 'observation_outvoted']], self::grade($row, 'classification'));
    }

    /** @return iterable<string, array{0: string, 1: ?string, 2: string}> */
    public static function contradictionsThatStay(): iterable
    {
        yield 'SNCF fermée' => ['Fermée', null, 'sncf'];
        yield 'OSM abandonnée' => ['Exploitée', 'abandoned', 'osm'];
        yield 'OSM rasée' => ['Exploitée', 'razed', 'osm'];
    }

    #[DataProvider('contradictionsThatStay')]
    public function test_t3_other_lone_contradictions_stay(string $sncf, ?string $osmLayer, string $contradicting): void
    {
        $this->sncfForm($sncf);
        $this->auditTrack('osm', self::TRACK, $this->audited->id, $osmLayer);
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($segment);
        $verdict = self::verdict($row, $contradicting);

        $this->assertSame([false, false], [$verdict['agrees'], $verdict['weak']]);
        $this->assertArrayNotHasKey('outvoted', $verdict);
        $this->assertSame(['fair', ['sources_disagree']], self::grade($row, 'classification'));
    }

    public function test_t3_needs_two_strong_agreeing_observations(): void
    {
        $this->sncfForm('Exploitée');
        // Voie OSM d'une autre ligne : son accord est un avis faible et ne compte pas dans la majorité.
        $this->auditTrack('osm', self::TRACK, $this->neighbour->id);
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['etat_de_l_objet' => 'Non exploité']);
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($segment);

        $this->assertTrue(self::verdict($row, 'osm')['weak']);
        $this->assertArrayNotHasKey('outvoted', self::verdict($row, 'bdtopo'));
        $this->assertSame(['fair', ['sources_disagree']], self::grade($row, 'classification'));
    }

    public function test_t3_two_strong_contradictions_are_not_outvoted(): void
    {
        $this->sncfForm('Déclassée vendue');
        $this->auditTrack('osm', self::TRACK, $this->audited->id);
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id, properties: ['etat_de_l_objet' => 'Non exploité']);
        $this->auditEvent($this->audited, 'opening', '1900-01-01', 'lignes-oubliees');
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($segment);

        $this->assertArrayNotHasKey('outvoted', self::verdict($row, 'sncf'));
        $this->assertArrayNotHasKey('outvoted', self::verdict($row, 'bdtopo'));
        $this->assertNotContains('observation_outvoted', $row['evidence']['classification']['reasons']);
    }

    public function test_current_observations_outweigh_a_chronology(): void
    {
        $this->sncfForm('Exploitée');
        $this->auditTrack('bdtopo', self::TRACK, $this->audited->id);
        $this->auditEvent($this->audited, 'opening', '1900-01-01');
        $this->auditEvent($this->audited, 'closure', '1990-01-01');
        $segment = $this->auditSegment($this->audited, $this->current, self::TRACK, 'in_service', 'mixed', 'osm');

        $row = $this->audit($segment);
        $wikipedia = self::verdict($row, 'wikipedia');

        $this->assertSame([false, true, true], [$wikipedia['agrees'], $wikipedia['weak'], $wikipedia['outweighed']]);
        $this->assertSame(['good', ['sources_agree']], self::grade($row, 'classification'));

        // Une observation contraire (voie OSM rasée) : la chronologie garde son poids.
        $this->auditTrack('osm', self::TRACK, $this->audited->id, 'razed');
        $this->assertArrayNotHasKey('outweighed', self::verdict($this->audit($segment), 'wikipedia'));
    }

    // ─── T4 : continuité des ères passées ────────────────────────────────

    /** Audit enregistré de l'ère $era pour un tronçon posé sur $wkt. */
    private function recordedAudit(Era $era, Line $line, string $wkt, string $status = 'in_service', string $classification = 'good'): void
    {
        $segment = $this->auditSegment($line, $era, $wkt, $status, 'mixed', 'osm');
        DB::table('segment_audits')->insert([
            'era_id' => $era->id, 'line_id' => $line->id, 'segment_id' => $segment, 'scope' => 'segment', 'status' => $status,
            'length_m' => 745, 'classification_grade' => $classification, 'placement_grade' => 'good', 'accuracy_grade' => 'good', 'trace_grade' => 'good',
            'worst_grade' => $classification, 'evidence' => '{}', 'rule_version' => 'test', 'audited_at' => now(),
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
        ]);
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3: bool, 4: bool}> */
    public static function continuityCases(): iterable
    {
        // [tracé de l'audit de 2030, statut, note de classement, audit de la ligne auditée, attendu]
        yield 'même tracé, en service et sûr' => [self::TRACK, 'in_service', 'good', true, true];
        yield 'voie unique' => [self::TRACK, 'single_track', 'good', true, true];
        yield 'à 28 m' => ['LINESTRING(2.0 48.00025, 2.01 48.00025)', 'in_service', 'good', true, true];
        yield 'à 33 m' => ['LINESTRING(2.0 48.0003, 2.01 48.0003)', 'in_service', 'good', true, false];
        yield 'à revoir en 2030' => [self::TRACK, 'in_service', 'fair', true, false];
        yield 'fermé en 2030' => [self::TRACK, 'abandoned', 'good', true, false];
        yield 'tronçon d’une autre ligne' => [self::TRACK, 'in_service', 'good', false, false];
    }

    #[DataProvider('continuityCases')]
    public function test_t4_continuity_with_the_recorded_current_audit(string $wkt, string $status, string $grade, bool $sameLine, bool $continuous): void
    {
        $this->auditEvent($this->audited, 'opening', '1900-01-01', 'wikidata');
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'in_service', 'mixed', 'osm');
        $this->recordedAudit($this->current, $sameLine ? $this->audited : $this->neighbour, $wkt, $status, $grade);

        $this->assertSame($continuous ? ['good', ['continuous_service']] : ['fair', ['weak_sources_only']],
            self::grade($this->audit($segment, $this->past), 'classification'));
    }

    public function test_t4_only_the_current_era_audit_counts(): void
    {
        $older = Era::factory()->create(['year' => 1990, 'label' => 'Années 1990']);
        $this->auditEvent($this->audited, 'opening', '1900-01-01', 'wikidata');
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'in_service', 'mixed', 'osm');
        $this->recordedAudit($older, $this->audited, self::TRACK);

        $this->assertSame(['fair', ['weak_sources_only']], self::grade($this->audit($segment, $this->past), 'classification'));
    }

    public function test_t4_a_closed_past_segment_is_not_continuous(): void
    {
        $this->auditEvent($this->audited, 'opening', '1900-01-01', 'wikidata');
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'abandoned', 'unknown', 'osm');
        $this->recordedAudit($this->current, $this->audited, self::TRACK);

        // Wikidata ne date que l'ouverture : elle ne dit rien d'un tronçon fermé, et la continuité ne s'applique pas.
        $this->assertSame(['unknown', ['no_source']], self::grade($this->audit($segment, $this->past), 'classification'));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function endsThatBreakContinuity(): iterable
    {
        yield 'fermeture non localisée' => ['closure', 'unresolved'];
        yield 'déclassement' => ['declassification', 'whole_line'];
        yield 'dépose' => ['dismantling', 'whole_line'];
        yield 'fin des voyageurs' => ['closure_passenger', 'whole_line'];
    }

    #[DataProvider('endsThatBreakContinuity')]
    public function test_t4_any_dated_end_breaks_continuity(string $type, string $resolution): void
    {
        $this->auditEvent($this->audited, 'opening', '1900-01-01', 'wikidata');
        // Après l'ère : la fin ne parle pas de 2000, mais la ligne n'est plus « jamais fermée ».
        $this->auditEvent($this->audited, $type, '2015-01-01', 'wikidata', $resolution);
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'in_service', 'mixed', 'osm');
        $this->recordedAudit($this->current, $this->audited, self::TRACK);

        $this->assertNotContains('continuous_service', $this->audit($segment, $this->past)['evidence']['classification']['reasons']);
    }

    public function test_t4_a_reopening_does_not_break_continuity(): void
    {
        $this->auditEvent($this->audited, 'opening', '1900-01-01', 'wikidata');
        $this->auditEvent($this->audited, 'reopening', '2015-01-01', 'wikidata');
        $segment = $this->auditSegment($this->audited, $this->past, self::TRACK, 'in_service', 'mixed', 'osm');
        $this->recordedAudit($this->current, $this->audited, self::TRACK);

        $this->assertSame(['good', ['continuous_service']], self::grade($this->audit($segment, $this->past), 'classification'));
    }
}
