<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\SegmentAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Réglages T1 et T4 du 21/11/2026 mesurés en base (SegmentAuditor, sans écriture du moteur) :
 *
 *   ligne voisine N : voie BD TOPO et OSM rattachées à N, sur y = 48.0 ;
 *   ligne L, ère 2030 :
 *     M  (fermé, forme SNCF) posé sur la voie de N, voie BD TOPO de L à 20 m au nord : on_other_line_track ;
 *     T  (en service, forme SNCF) posé sur la voie OSM de N plus à l'est, aucune autre voie : shared_track ;
 *   ligne L, ère 2000 : P (en service, seule Wikidata la date), même tracé que T, sûr en 2030 : continuous_service.
 */
class AuditReglagesTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Line $neighbour;

    private Era $current;

    private Era $past;

    protected function setUp(): void
    {
        parent::setUp();
        $this->past = Era::factory()->create(['year' => 2000, 'label' => 'Années 2000']);
        $this->current = Era::factory()->create(['year' => 2030, 'label' => 'Aujourd’hui', 'is_current' => true]);
        $this->line = Line::factory()->create(['slug' => 'rfn-654321', 'code' => '654321', 'name' => 'Ligne de Lima', 'is_lgv' => false, 'network' => 'principal']);
        $this->neighbour = Line::factory()->create(['slug' => 'rfn-111111', 'code' => '111111', 'name' => 'Ligne de Mike', 'is_lgv' => false, 'network' => 'principal']);

        $this->trace('bdtopo', 'Voie ferrée principale', 'LINESTRING(2.0 48.0, 2.01 48.0)', $this->neighbour->id);
        $this->trace('osm', 'rail', 'LINESTRING(2.0 48.0, 2.03 48.0)', $this->neighbour->id);
        $this->trace('bdtopo', 'Voie ferrée principale', 'LINESTRING(2.0 48.00018, 2.01 48.00018)', $this->line->id);
        // Wikidata seule date l'ouverture de L ; aucune fin datée.
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'opening', 'event_date' => '1900-01-01', 'description' => 'ouverture',
            'source' => 'wikidata', 'section_resolution' => 'whole_line']);
    }

    private function trace(string $source, string $layer, string $wkt, int $lineId): void
    {
        static $n = 0;
        DB::table('source_traces')->insert([
            'source' => $source, 'source_ref' => $source.'-r-'.(++$n), 'layer' => $layer, 'properties' => json_encode(['etat_de_l_objet' => 'En service']),
            'line_id' => $lineId, 'seen_at' => now(), 'geom' => DB::raw("ST_Multi(ST_GeomFromText('{$wkt}', 4326))"),
        ]);
    }

    private function segment(Era $era, string $wkt, string $status, string $traffic): int
    {
        return (int) DB::table('line_segments')->insertGetId([
            'line_id' => $this->line->id, 'era_id' => $era->id, 'status' => $status, 'traffic_type' => $traffic, 'source' => 'sncf',
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
            'length_meters' => (int) DB::selectOne('SELECT ST_Length(ST_GeomFromText(?, 4326)::geography) AS m', [$wkt])->m,
        ]);
    }

    /**
     * @param  list<array{era_id: int, segments: list<array<string, mixed>>}>  $audit
     * @return array<string, mixed>
     */
    private static function row(array $audit, int $eraId, int $segmentId): array
    {
        foreach ($audit as $era) {
            foreach ($era['segments'] as $s) {
                if ($era['era_id'] === $eraId && $s['segment_id'] === $segmentId) {
                    return $s;
                }
            }
        }
        self::fail("tronçon {$segmentId} absent");
    }

    public function test_placement_on_another_line_track(): void
    {
        $m = $this->segment($this->current, 'LINESTRING(2.0 48.0, 2.01 48.0)', 'abandoned', 'unknown');
        $t = $this->segment($this->current, 'LINESTRING(2.015 48.0, 2.025 48.0)', 'in_service', 'mixed');

        $audit = app(SegmentAuditor::class)->auditLine($this->line->id, [2030]);

        $branch = self::row($audit, $this->current->id, $m);
        $this->assertSame('poor', $branch['placement_grade']);
        $this->assertSame(['on_other_line_track'], $branch['evidence']['placement']['reasons']);
        $this->assertEqualsWithDelta(20.0, $branch['evidence']['placement']['median_m'], 0.5, 'écart à sa propre voie');
        $this->assertEqualsWithDelta(0.0, $branch['evidence']['placement']['references']['bdtopo']['all']['median_m'], 0.1, 'collé à la voie de N');
        $this->assertEqualsWithDelta(20.0, $branch['evidence']['placement']['references']['bdtopo']['median_m'], 0.5, 'la voie de N ne sert pas de référence');
        $this->assertNotSame('good', $branch['trace_grade'], 'pas de recalage sur la voie de N');

        $shared = self::row($audit, $this->current->id, $t);
        $this->assertSame(['good', ['shared_track'], 'osm'], [$shared['placement_grade'], $shared['evidence']['placement']['reasons'], $shared['evidence']['placement']['reference']]);
    }

    public function test_continuity_with_the_current_era(): void
    {
        $wkt = 'LINESTRING(2.015 48.0, 2.025 48.0)';
        $p = $this->segment($this->past, $wkt, 'in_service', 'mixed');
        $now = $this->segment($this->current, $wkt, 'in_service', 'mixed');
        $auditor = app(SegmentAuditor::class);

        // Sans audit enregistré de l'ère courante : sources faibles seules.
        $before = self::row($auditor->auditLine($this->line->id, [2000]), $this->past->id, $p);
        $this->assertSame(['fair', ['weak_sources_only']], [$before['classification_grade'], $before['evidence']['classification']['reasons']]);

        DB::table('segment_audits')->insert([
            'era_id' => $this->current->id, 'line_id' => $this->line->id, 'segment_id' => $now, 'scope' => 'segment', 'status' => 'in_service',
            'length_m' => 740, 'classification_grade' => 'good', 'placement_grade' => 'good', 'accuracy_grade' => 'good', 'trace_grade' => 'good',
            'worst_grade' => 'good', 'evidence' => '{}', 'rule_version' => 'test', 'audited_at' => now(),
            'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)"),
        ]);
        $after = self::row($auditor->auditLine($this->line->id, [2000]), $this->past->id, $p);
        $this->assertSame(['good', ['continuous_service']], [$after['classification_grade'], $after['evidence']['classification']['reasons']]);

        // Une fin non localisée datée après l'ère ne dit rien de 2000 (règle R2, règles `.8`).
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'closure', 'event_date' => '2010-01-01', 'description' => 'fermeture',
            'source' => 'wikidata', 'section_resolution' => 'pk', 'section_from' => 'Lima']);
        $later = self::row($auditor->auditLine($this->line->id, [2000]), $this->past->id, $p);
        $this->assertSame('good', $later['classification_grade']);

        // Datée avant l'ère, une fin non localisée peut toucher le tronçon : elle rompt la continuité.
        DB::table('line_events')->insert(['line_id' => $this->line->id, 'type' => 'closure', 'event_date' => '1990-01-01', 'description' => 'fermeture',
            'source' => 'wikidata', 'section_resolution' => 'pk', 'section_from' => 'Lima']);
        $broken = self::row($auditor->auditLine($this->line->id, [2000]), $this->past->id, $p);
        $this->assertSame('fair', $broken['classification_grade']);
    }

    /**
     * Passe sur toutes les ères : la continuité lit la note de l'ère courante calculée dans la même passe, jamais
     * celle qu'une passe précédente a enregistrée (anciennes règles, anciennes sources). Sinon la première passe
     * après un changement de règles notait les ères passées d'après l'ancien classement de 2030.
     */
    public function test_continuity_follows_the_current_era_of_the_same_pass(): void
    {
        $wkt = 'LINESTRING(2.015 48.0, 2.025 48.0)';
        $p = $this->segment($this->past, $wkt, 'in_service', 'mixed');
        $now = $this->segment($this->current, $wkt, 'in_service', 'mixed');
        $auditor = app(SegmentAuditor::class);
        $stored = fn (string $grade) => DB::table('segment_audits')->updateOrInsert(
            ['era_id' => $this->current->id, 'segment_id' => $now, 'scope' => 'segment'],
            ['line_id' => $this->line->id, 'status' => 'in_service', 'length_m' => 740, 'classification_grade' => $grade,
                'placement_grade' => 'good', 'accuracy_grade' => 'good', 'trace_grade' => 'good', 'worst_grade' => $grade,
                'evidence' => '{}', 'rule_version' => 'ancienne', 'audited_at' => now(), 'geom' => DB::raw("ST_GeomFromText('{$wkt}', 4326)")],
        );

        // Ancien audit « sûr », mais 2030 n'a aujourd'hui que des sources faibles : pas de continuité.
        $stored('good');
        $audit = $auditor->auditLine($this->line->id);
        $this->assertSame('fair', self::row($audit, $this->current->id, $now)['classification_grade']);
        $this->assertSame(['fair', ['weak_sources_only']], [self::row($audit, $this->past->id, $p)['classification_grade'],
            self::row($audit, $this->past->id, $p)['evidence']['classification']['reasons']]);

        // Ancien audit « à revoir », mais une voie BD TOPO de la ligne, en service, confirme 2030 : continuité.
        $stored('fair');
        $this->trace('bdtopo', 'Voie ferrée principale', $wkt, $this->line->id);
        $audit = $auditor->auditLine($this->line->id);
        $this->assertSame('good', self::row($audit, $this->current->id, $now)['classification_grade']);
        $this->assertSame(['good', ['continuous_service']], [self::row($audit, $this->past->id, $p)['classification_grade'],
            self::row($audit, $this->past->id, $p)['evidence']['classification']['reasons']]);
        $this->assertSame([$this->past->id, $this->current->id], array_column($audit, 'era_id'), 'ères rendues dans l’ordre des années');
    }
}
