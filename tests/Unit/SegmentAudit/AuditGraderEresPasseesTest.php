<?php

namespace Tests\Unit\SegmentAudit;

use App\Enums\AuditGrade;
use App\Services\SegmentAudit\AuditGrader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règle R4 des ères passées (règles 2026-11-21.6) sur AuditGrader::placement, sans base : tracé OSM rattaché à
 * la ligne qui le suit sur tous les points, seule référence BD TOPO à plus de DISTANT_REFERENCE_M.
 */
class AuditGraderEresPasseesTest extends TestCase
{
    /**
     * @param  array{found?: int, median_m?: ?float}  $own
     * @return array<string, array<string, mixed>>
     */
    private static function refs(float $bdtopo, array $own = [], ?float $osmP95 = 0.3): array
    {
        return [
            'bdtopo' => ['points' => 10, 'found' => 10, 'median_m' => $bdtopo, 'p95_m' => $bdtopo + 2,
                'all' => ['found' => 10, 'median_m' => $bdtopo, 'p95_m' => $bdtopo + 2], 'own' => ['found' => 0, 'median_m' => null]],
            'osm' => ['points' => 10, 'found' => 10, 'median_m' => 0.2, 'p95_m' => $osmP95,
                'all' => ['found' => 10, 'median_m' => 0.2, 'p95_m' => $osmP95], 'own' => $own + ['found' => 10, 'median_m' => 0.2]],
        ];
    }

    public function test_distant_bdtopo_alone_makes_a_past_osm_trace_unknown(): void
    {
        $r = AuditGrader::placement(self::refs(20.0), 'osm', false, bestMatch: true);

        $this->assertSame(AuditGrade::Unknown, $r['grade']);
        $this->assertSame(['distant_reference_only'], $r['reasons']);
        $this->assertSame('bdtopo', $r['reference']);
        $this->assertSame(20.0, $r['median_m']);
    }

    /** @return array<string, array{0: array<string, array<string, mixed>>, 1: ?string, 2: bool}> */
    public static function keptPoor(): array
    {
        return [
            'BD TOPO à 15 m pile' => [self::refs(15.0), 'osm', true],
            'BD TOPO à 12 m' => [self::refs(12.0), 'osm', true],
            'ère courante' => [self::refs(20.0), 'osm', false],
            'tracé BD TOPO, pas OSM' => [self::refs(20.0), 'sncf', true],
            'voie OSM de la ligne sur 9 points sur 10' => [self::refs(20.0, ['found' => 9]), 'osm', true],
            'voie OSM de la ligne à 1,5 m' => [self::refs(20.0, ['median_m' => 1.5]), 'osm', true],
            'voie OSM de la ligne sans mesure' => [self::refs(20.0, ['median_m' => null]), 'osm', true],
            '95e centile OSM à 1,2 m' => [self::refs(20.0, [], 1.2), 'osm', true],
            '95e centile OSM inconnu' => [self::refs(20.0, [], null), 'osm', true],
        ];
    }

    /** @param  array<string, array<string, mixed>>  $refs */
    #[DataProvider('keptPoor')]
    public function test_other_cases_keep_the_poor_placement(array $refs, ?string $source, bool $bestMatch): void
    {
        $r = AuditGrader::placement($refs, $source, false, bestMatch: $bestMatch);

        $this->assertNotContains('distant_reference_only', $r['reasons']);
    }

    public function test_osm_trace_at_one_metre_still_counts(): void
    {
        $r = AuditGrader::placement(self::refs(20.0, ['median_m' => 1.0], 1.0), 'osm', false, bestMatch: true);

        $this->assertSame(AuditGrade::Unknown, $r['grade']);
    }

    public function test_closer_historical_reference_is_not_overridden(): void
    {
        $refs = self::refs(20.0) + ['ign-historique' => ['points' => 10, 'found' => 10, 'median_m' => 8.0, 'p95_m' => 9.0,
            'all' => ['found' => 10, 'median_m' => 8.0, 'p95_m' => 9.0], 'own' => ['found' => 0, 'median_m' => null]]];

        $r = AuditGrader::placement($refs, 'osm', false, bestMatch: true);

        $this->assertSame(AuditGrade::Poor, $r['grade']);
        $this->assertSame('ign-historique', $r['reference']);
    }

    public function test_rule_version(): void
    {
        $this->assertSame('2026-11-21.7', AuditGrader::RULE_VERSION);
    }
}
