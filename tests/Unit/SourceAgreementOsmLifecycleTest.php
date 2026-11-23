<?php

namespace Tests\Unit;

use App\Services\SourceAgreement;
use PHPUnit\Framework\TestCase;

/**
 * Voies OSM abandonnées, déposées ou inutilisées (décision du 22/11/2026) : un morceau qui n'a qu'elles pour
 * géométrie précise reste « estimé » (low), sauf confirmation indépendante à 3 m près : BD TOPO, LiDAR HD ou axe
 * affiné sur photo recalée (ign-historique, classe ortho-precis). archeo et OHM ne confirment pas : à Courseulles,
 * ils « concordent » à 2 m avec une voie OSM abandonnée qui passe 10 à 20 m à côté de la plateforme.
 */
class SourceAgreementOsmLifecycleTest extends TestCase
{
    private function evaluate(object $sample, bool $lifecycle, string $own = 'osm'): array
    {
        return (new \ReflectionMethod(SourceAgreement::class, 'evaluate'))->invoke(new SourceAgreement, $sample, $own, 1, $lifecycle);
    }

    /**
     * @param  array<string, array{0: float, 1?: string}>  $nearest  famille → [écart (m), classe cf_pool]
     */
    private function sample(array $nearest): object
    {
        $s = ['lon' => '-0.38', 'lat' => '49.3'];
        foreach (SourceAgreement::FAMILIES as $i => $f) {
            $a = match ($f) {
                'archeoferroviaire' => 'archeo',
                'ign-historique' => 'ignh',
                default => $f,
            };
            $s["nd_{$a}"] = isset($nearest[$f]) ? $nearest[$f][0] : null;
            $s["ni_{$a}"] = isset($nearest[$f]) ? (string) (100 + $i) : null;
            $s["nl_{$a}"] = isset($nearest[$f]) ? 3181 : null;
            $s["nc_{$a}"] = isset($nearest[$f]) ? ($nearest[$f][1] ?? 'main') : null;
            $s["md_{$a}"] = null;
            $s["mi_{$a}"] = null;
        }

        return (object) $s;
    }

    public function test_abandoned_osm_backed_only_by_archeo_and_ohm_is_estimated(): void
    {
        $e = $this->evaluate($this->sample(['archeoferroviaire' => [1.9], 'ohm' => [2.1, 'coarse']]), true);

        $this->assertSame('low', $e['class']);
    }

    public function test_abandoned_osm_confirmed_by_bdtopo_within_three_metres_is_high(): void
    {
        $this->assertSame('high', $this->evaluate($this->sample(['bdtopo' => [2.4], 'archeoferroviaire' => [1.0]]), true)['class']);
        $this->assertSame('low', $this->evaluate($this->sample(['bdtopo' => [3.6]]), true)['class']);
    }

    public function test_abandoned_osm_confirmed_by_a_registered_photo_axis_is_medium(): void
    {
        $e = $this->evaluate($this->sample(['ign-historique' => [2.0, 'ortho-precis']]), true);

        $this->assertSame('medium', $e['class']);
    }

    public function test_pixel_extraction_does_not_confirm_abandoned_osm(): void
    {
        $e = $this->evaluate($this->sample(['ign-historique' => [2.0, 'ortho']]), true);

        $this->assertSame('low', $e['class']);
    }

    public function test_osm_in_service_is_unchanged(): void
    {
        $this->assertSame('medium', $this->evaluate($this->sample(['archeoferroviaire' => [1.9]]), false)['class']);
        $this->assertSame('high', $this->evaluate($this->sample(['bdtopo' => [4.0]]), false)['class']);
    }

    public function test_a_fused_piece_is_precise_and_never_downgraded_by_the_osm_rule(): void
    {
        $this->assertSame('medium', $this->evaluate($this->sample(['archeoferroviaire' => [1.9]]), true, 'fusion')['class']);
        $this->assertSame('high', $this->evaluate($this->sample(['osm' => [2.0]]), true, 'fusion')['class']);
    }

    public function test_every_dead_osm_state_of_the_engine_is_covered(): void
    {
        foreach (['dismantled', 'dismantled:railway', 'demolished:railway', 'removed:railway', 'was:railway'] as $layer) {
            $this->assertContains($layer, SourceAgreement::OSM_LIFECYCLE_LAYERS);
        }
    }
}
