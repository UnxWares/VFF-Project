<?php

namespace Tests\Unit;

use App\Services\SourceAgreement;
use PHPUnit\Framework\TestCase;

/**
 * Divergences entre sources le long d'un tracé (SourceAgreement::divergences), règles pures :
 *   - « precise » : une autre source précise du même axe, non rattachée à une autre ligne,
 *     s'écarte de plus de 8 m sur au moins 4 échantillons consécutifs ;
 *   - « line » : une source moins précise RATTACHÉE À LA LIGNE s'écarte de plus de son seuil, puis
 *     rejoint la géométrie retenue des deux côtés (déviation, ancien tracé) ; une branche qui s'en
 *     va sans revenir n'est pas une divergence.
 */
class SourceAgreementDivergencesTest extends TestCase
{
    private const LINE_ID = 42;

    /**
     * Échantillon évalué (sortie de evaluate) : écarts par famille.
     *
     * @param  array<string, array{d: float, line?: ?int, cls?: string}>  $d  autre source la plus proche
     * @param  array<string, float>  $mine  écart d'une trace de la ligne, par famille
     * @return array<string, mixed>
     */
    private function eval(int $j, array $d = [], array $mine = []): array
    {
        $near = [];
        foreach ($d as $family => $v) {
            $near[$family] = ['d' => $v['d'], 'id' => 100 + $j, 'line' => $v['line'] ?? null, 'cls' => $v['cls'] ?? 'main'];
        }
        $own = [];
        foreach ($mine as $family => $distance) {
            $own[$family] = ['d' => $distance, 'id' => 200 + $j];
        }

        return ['class' => 'medium', 'd' => $near, 'agree' => [], 'mine' => $own, 'gap' => null,
            'lon' => 2.0 + $j / 1000, 'lat' => 48.0];
    }

    /** @return array<int, array<string, mixed>> */
    private function divergencesOf(array $evals, string $own, float $length = 1000.0): array
    {
        $seq = [(object) ['n' => count($evals), 'len' => $length]];
        $part = (object) ['line_id' => self::LINE_ID];

        return (new \ReflectionMethod(SourceAgreement::class, 'divergences'))
            ->invoke(new SourceAgreement, $seq, $evals, $part, $own);
    }

    /** n échantillons, dont ceux de la plage [a, b] écartés de $gap sur la famille donnée. */
    private function preciseSeries(int $n, int $a, int $b, float $gap, string $family = 'osm', array $extra = []): array
    {
        $evals = [];
        for ($j = 0; $j < $n; $j++) {
            $evals[] = $this->eval($j, $j >= $a && $j <= $b ? [$family => ['d' => $gap] + $extra] : []);
        }

        return $evals;
    }

    public function test_precise_source_far_from_the_retained_geometry_is_a_divergence(): void
    {
        $out = $this->divergencesOf($this->preciseSeries(10, 2, 6, 12.0), 'bdtopo');

        $this->assertCount(1, $out);
        $this->assertSame('precise', $out[0]['type']);
        $this->assertSame('bdtopo', $out[0]['retained']);
        $this->assertSame('osm', $out[0]['other']);
        $this->assertSame(self::LINE_ID, $out[0]['line_id']);
        // 5 échantillons sur 10 d'un tracé de 1000 m.
        $this->assertSame(500.0, $out[0]['length_m']);
        $this->assertSame(12.0, $out[0]['gap_median_m']);
        $this->assertSame(12.0, $out[0]['gap_max_m']);
        $this->assertSame(6.0, $out[0]['score']);
        $this->assertStringStartsWith('LINESTRING(', $out[0]['wkt']);
        // 5 points, donc 4 virgules entre eux.
        $this->assertSame(4, substr_count($out[0]['wkt'], ','));
    }

    public function test_a_gap_within_the_threshold_is_not_a_divergence(): void
    {
        $this->assertSame([], $this->divergencesOf($this->preciseSeries(10, 0, 9, 8.0), 'bdtopo'));
    }

    public function test_fewer_than_four_samples_are_not_a_divergence(): void
    {
        $this->assertSame([], $this->divergencesOf($this->preciseSeries(10, 2, 4, 30.0), 'bdtopo'));
        $this->assertCount(1, $this->divergencesOf($this->preciseSeries(10, 2, 5, 30.0), 'bdtopo'));
    }

    public function test_a_source_attached_to_another_line_is_ignored(): void
    {
        $this->assertSame([], $this->divergencesOf($this->preciseSeries(10, 0, 9, 30.0, 'osm', ['line' => 999]), 'bdtopo'));
    }

    public function test_a_source_attached_to_the_same_line_counts(): void
    {
        $this->assertCount(1, $this->divergencesOf($this->preciseSeries(10, 0, 9, 30.0, 'osm', ['line' => self::LINE_ID]), 'bdtopo'));
    }

    public function test_a_trace_that_is_not_the_main_track_is_ignored(): void
    {
        $this->assertSame([], $this->divergencesOf($this->preciseSeries(10, 0, 9, 30.0, 'osm', ['cls' => 'siding']), 'bdtopo'));
    }

    public function test_an_imprecise_retained_geometry_never_yields_a_precise_divergence(): void
    {
        // La géométrie retenue vient de l'OHM : l'écart à OSM n'est pas un défaut de OSM.
        $this->assertSame([], $this->divergencesOf($this->preciseSeries(10, 0, 9, 30.0, 'osm'), 'ohm'));
    }

    public function test_the_own_family_is_never_compared_to_itself(): void
    {
        $this->assertSame([], $this->divergencesOf($this->preciseSeries(10, 0, 9, 30.0, 'bdtopo'), 'bdtopo'));
    }

    public function test_the_gap_statistics_use_the_median_and_the_maximum(): void
    {
        $evals = [];
        foreach ([9.0, 10.0, 11.0, 40.0, 12.0] as $j => $gap) {
            $evals[] = $this->eval($j, ['osm' => ['d' => $gap]]);
        }

        $out = $this->divergencesOf($evals, 'bdtopo', 500.0);

        $this->assertSame(11.0, $out[0]['gap_median_m']);
        $this->assertSame(40.0, $out[0]['gap_max_m']);
        $this->assertSame(500.0, $out[0]['length_m']);
    }

    public function test_two_separate_runs_give_two_divergences(): void
    {
        $evals = $this->preciseSeries(20, 0, 3, 15.0);
        foreach ($this->preciseSeries(20, 10, 14, 15.0) as $j => $e) {
            if ($j >= 10 && $j <= 14) {
                $evals[$j] = $e;
            }
        }

        $this->assertCount(2, $this->divergencesOf($evals, 'bdtopo'));
    }

    /** n échantillons : la source rattachée à la ligne s'écarte de $gap sur la plage [a, b], sinon elle colle. */
    private function lineSeries(int $n, int $a, int $b, float $gap, string $family = 'archeoferroviaire'): array
    {
        $evals = [];
        for ($j = 0; $j < $n; $j++) {
            $evals[] = $this->eval($j, [], [$family => $j >= $a && $j <= $b ? $gap : 2.0]);
        }

        return $evals;
    }

    public function test_an_attached_source_that_leaves_and_comes_back_is_a_divergence(): void
    {
        $out = $this->divergencesOf($this->lineSeries(12, 3, 7, 40.0), 'bdtopo');

        $this->assertCount(1, $out);
        $this->assertSame('line', $out[0]['type']);
        $this->assertSame('archeoferroviaire', $out[0]['other']);
        $this->assertSame(40.0, $out[0]['gap_median_m']);
    }

    public function test_a_source_that_runs_to_the_end_of_the_part_is_a_divergence(): void
    {
        // La portion court jusqu'au bout : la source ne revient pas, mais le tracé s'arrête.
        $this->assertCount(1, $this->divergencesOf($this->lineSeries(12, 6, 11, 40.0), 'bdtopo'));
        $this->assertCount(1, $this->divergencesOf($this->lineSeries(12, 0, 5, 40.0), 'bdtopo'));
    }

    public function test_a_branch_that_goes_away_without_coming_back_is_not_a_divergence(): void
    {
        // Après l'écart, plus aucune trace de la source à moins de 500 m : une branche qui s'en va.
        $evals = [];
        for ($j = 0; $j < 12; $j++) {
            $mine = $j < 3 ? ['archeoferroviaire' => 2.0] : ($j < 8 ? ['archeoferroviaire' => 40.0] : []);
            $evals[] = $this->eval($j, [], $mine);
        }

        $this->assertSame([], $this->divergencesOf($evals, 'bdtopo'));
    }

    public function test_a_short_part_entirely_far_away_is_not_a_divergence(): void
    {
        $this->assertSame([], $this->divergencesOf($this->lineSeries(10, 0, 9, 40.0), 'bdtopo'));
    }

    public function test_a_long_part_entirely_far_away_is_a_divergence(): void
    {
        $this->assertCount(1, $this->divergencesOf($this->lineSeries(20, 0, 19, 40.0), 'bdtopo'));
    }

    public function test_the_attached_source_threshold_depends_on_the_family(): void
    {
        // archeoferroviaire : 25 m ; sncf : 150 m.
        $this->assertSame([], $this->divergencesOf($this->lineSeries(12, 3, 7, 25.0), 'bdtopo'));
        $this->assertSame([], $this->divergencesOf($this->lineSeries(12, 3, 7, 140.0, 'sncf'), 'bdtopo'));
        $this->assertCount(1, $this->divergencesOf($this->lineSeries(12, 3, 7, 160.0, 'sncf'), 'bdtopo'));
    }

    public function test_a_more_precise_attached_source_is_not_compared_to_a_less_precise_retained_one(): void
    {
        // Retenue : OSM ; BD TOPO (plus précise) rattachée et écartée : pas de divergence « line »,
        // seul le cas « precise » s'applique à elle.
        $this->assertSame([], $this->divergencesOf($this->lineSeries(12, 3, 7, 40.0, 'bdtopo'), 'osm'));
    }
}
