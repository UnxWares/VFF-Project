<?php

namespace Tests\Feature\SegmentAudit;

use App\Models\Era;
use App\Models\Line;
use App\Services\SegmentAudit\SegmentAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Règles `2026-11-23.1` (aprem-2111/relecture-audits.md, quatrième contrôle à la main : 120 constats) :
 * R5 (une ouverture localisée tardive battue par une plus ancienne n'est plus un avis ferme), R6 (une source
 * qui ne date que la fin des voyageurs confirme le fret seul), R7 (une source sans événement des voyageurs ne
 * contredit pas le trafic), et `vff:audit-segments --stale`.
 *
 * Ligne auditée L (code 654360) ; tronçons est-ouest sur 48° N entre 2,00° et 2,01° E.
 */
class AuditRelectureTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    private const AS_OF = '2026-11-23';

    private const TRACK = 'LINESTRING(2.0 48.0, 2.01 48.0)';

    private Era $e1850;

    private Era $e1970;

    private Line $audited;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e1850 = Era::factory()->create(['year' => 1850, 'label' => 'Années 1850']);
        $this->e1970 = Era::factory()->create(['year' => 1970, 'label' => 'Années 1970']);
        Era::factory()->current()->create(['year' => 2030, 'label' => 'Aujourd’hui']);
        $this->audited = Line::factory()->create(['slug' => 'rfn-654360', 'code' => '654360', 'name' => 'Ligne de Saint-Étienne', 'is_lgv' => false, 'network' => 'principal']);
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

    public function test_late_located_opening_is_outvoted_by_an_earlier_one_of_another_source(): void
    {
        // Saint-Étienne - Lyon en 1850 : Wikipédia ne localise que le « tracé définitif » de 1857, OpenHistoricalMap date 1827.
        $this->auditEvent($this->audited, 'opening', '1857-11-24', 'wikipedia', 'places', self::TRACK);
        $this->auditEvent($this->audited, 'opening', '1827-01-01', 'ohm', 'trace', self::TRACK);
        $seg = $this->auditSegment($this->audited, $this->e1850, self::TRACK, 'in_service');

        $row = $this->audit($seg, $this->e1850);

        $wikipedia = self::verdict($row, 'wikipedia');
        $this->assertFalse($wikipedia['agrees']);
        $this->assertTrue($wikipedia['weak']);
        $this->assertSame('ohm', $wikipedia['late_opening_outvoted']);
        $this->assertNotSame('poor', $row['classification_grade']);
    }

    public function test_late_located_opening_alone_stays_a_firm_contradiction(): void
    {
        $this->auditEvent($this->audited, 'opening', '1857-11-24', 'wikipedia', 'places', self::TRACK);
        $seg = $this->auditSegment($this->audited, $this->e1850, self::TRACK, 'in_service');

        $row = $this->audit($seg, $this->e1850);

        $this->assertFalse(self::verdict($row, 'wikipedia')['weak']);
        $this->assertSame('poor', $row['classification_grade']);
    }

    public function test_openings_within_twenty_years_are_a_real_disagreement(): void
    {
        $this->auditEvent($this->audited, 'opening', '1857-11-24', 'wikipedia', 'places', self::TRACK);
        $this->auditEvent($this->audited, 'opening', '1840-01-01', 'ohm', 'trace', self::TRACK);
        $seg = $this->auditSegment($this->audited, $this->e1850, self::TRACK, 'in_service');

        $this->assertFalse(self::verdict($this->audit($seg, $this->e1850), 'wikipedia')['weak']);
    }

    public function test_source_dating_only_the_end_of_passenger_service_confirms_a_freight_section(): void
    {
        // Beaulieu-le-Coudray - Auneau en 1970 : fin des voyageurs 1939 saisie à la main, aucune ouverture.
        $this->auditEvent($this->audited, 'closure_passenger', '1939-08-01', 'manual', 'places', self::TRACK);
        $seg = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'freight');

        $verdict = self::verdict($this->audit($seg, $this->e1970), 'manual');

        $this->assertNotNull($verdict);
        $this->assertTrue($verdict['agrees']);
        $this->assertSame('freight', $verdict['expected']['traffic']);
    }

    public function test_source_dating_only_the_end_of_passenger_service_contradicts_a_mixed_section(): void
    {
        $this->auditEvent($this->audited, 'closure_passenger', '1939-08-01', 'manual', 'places', self::TRACK);
        $seg = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'mixed');

        $verdict = self::verdict($this->audit($seg, $this->e1970), 'manual');

        $this->assertFalse($verdict['agrees']);
        $this->assertSame('expected_freight_only', $verdict['why']);
    }

    public function test_source_without_passenger_event_does_not_contradict_the_freight_traffic(): void
    {
        // Compiègne - Soissons en 1970 : OpenHistoricalMap date l'ouverture, pas la fin des voyageurs que Wikipédia date.
        $this->auditEvent($this->audited, 'opening', '1881-04-14', 'ohm', 'trace', self::TRACK);
        $this->auditEvent($this->audited, 'closure', '1973-07-24', 'ohm', 'trace', self::TRACK);
        $this->auditEvent($this->audited, 'closure_passenger', '1939-05-15', 'wikipedia', 'places', self::TRACK);
        $seg = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'freight');

        $row = $this->audit($seg, $this->e1970);

        $ohm = self::verdict($row, 'ohm');
        $this->assertFalse($ohm['agrees']);
        $this->assertTrue($ohm['traffic_assumed']);
        $this->assertTrue($ohm['weak']);
        $this->assertSame('good', $row['classification_grade']);
    }

    public function test_a_source_that_dates_passenger_service_keeps_its_traffic_contradiction(): void
    {
        $this->auditEvent($this->audited, 'opening_passenger', '1881-04-14', 'wikipedia', 'places', self::TRACK);
        $this->auditEvent($this->audited, 'closure', '1995-01-01', 'wikipedia', 'places', self::TRACK);
        $seg = $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service', 'freight');

        $verdict = self::verdict($this->audit($seg, $this->e1970), 'wikipedia');

        $this->assertFalse($verdict['agrees']);
        $this->assertFalse($verdict['traffic_assumed']);
        $this->assertFalse($verdict['weak']);
    }

    public function test_stale_lines_are_the_ones_whose_audited_segment_disappeared_or_was_never_audited(): void
    {
        $fresh = Line::factory()->create(['slug' => 'rfn-654361', 'code' => '654361', 'is_lgv' => false, 'network' => 'principal']);
        $never = Line::factory()->create(['slug' => 'rfn-654362', 'code' => '654362', 'is_lgv' => false, 'network' => 'principal']);
        $auditor = app(SegmentAuditor::class);
        $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service');
        $this->auditSegment($fresh, $this->e1970, self::TRACK, 'in_service');
        $this->auditSegment($never, $this->e1970, self::TRACK, 'in_service');
        foreach ([$this->audited, $fresh] as $line) {
            $auditor->write($line->id, $auditor->auditLine($line->id, [1970], self::AS_OF));
        }
        $this->assertSame([$never->id], $auditor->staleLineIds());

        // Le moteur est rejoué : le tronçon audité est remplacé par un autre.
        DB::table('line_segments')->where('line_id', $this->audited->id)->delete();
        $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service');

        $this->assertEqualsCanonicalizing([$this->audited->id, $never->id], $auditor->staleLineIds());
    }

    public function test_stale_option_audits_only_the_stale_lines(): void
    {
        $fresh = Line::factory()->create(['slug' => 'rfn-654361', 'code' => '654361', 'is_lgv' => false, 'network' => 'principal']);
        $auditor = app(SegmentAuditor::class);
        $this->auditSegment($this->audited, $this->e1970, self::TRACK, 'in_service');
        $this->auditSegment($fresh, $this->e1970, self::TRACK, 'in_service');
        $auditor->write($fresh->id, $auditor->auditLine($fresh->id, [1970], self::AS_OF));

        $this->artisan('vff:audit-segments', ['--stale' => true])->assertSuccessful();

        $this->assertSame([], $auditor->staleLineIds());
        $this->assertSame(1, DB::table('segment_audits')->where('line_id', $this->audited->id)->where('scope', 'segment')->count());
    }
}
