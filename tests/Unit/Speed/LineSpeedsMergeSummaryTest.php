<?php

namespace Tests\Unit\Speed;

use App\Services\Speed\LineSpeeds;
use PHPUnit\Framework\TestCase;

/**
 * Cas limites de la fusion des tranches de vitesse (écart de 50 m, recouvrements, vitesses différentes) et du résumé
 * (longueur couverte, vitesses extrêmes, tris), sans base de données.
 */
class LineSpeedsMergeSummaryTest extends TestCase
{
    /** @return array{from: int, to: int, kmh: int} */
    private static function r(int $from, int $to, int $kmh): array
    {
        return ['from' => $from, 'to' => $to, 'kmh' => $kmh];
    }

    /** @return array{pk_start_m: int, length_m: int, max_speed_kmh: int} */
    private static function span(int $from, int $length, int $kmh): array
    {
        return ['pk_start_m' => $from, 'length_m' => $length, 'max_speed_kmh' => $kmh];
    }

    public function test_the_merge_gap_is_50_metres_inclusive(): void
    {
        $this->assertSame([self::r(0, 2000, 100)], LineSpeeds::merge([self::r(0, 1000, 100), self::r(1050, 2000, 100)]));
        $this->assertCount(2, LineSpeeds::merge([self::r(0, 1000, 100), self::r(1051, 2000, 100)]));
        $this->assertSame([self::r(0, 2000, 100)], LineSpeeds::merge([self::r(0, 1000, 100), self::r(1000, 2000, 100)]));
        $this->assertSame([self::r(0, 2000, 100)], LineSpeeds::merge([self::r(0, 1000, 100), self::r(900, 2000, 100)]));
        $this->assertSame(50, LineSpeeds::MERGE_GAP_M);
    }

    public function test_different_speeds_are_never_merged(): void
    {
        $this->assertCount(2, LineSpeeds::merge([self::r(0, 1000, 100), self::r(1000, 2000, 101)]));
    }

    public function test_a_nested_range_keeps_the_widest_end(): void
    {
        $this->assertSame([self::r(0, 5000, 100)], LineSpeeds::merge([self::r(0, 5000, 100), self::r(1000, 2000, 100)]));
    }

    public function test_a_range_ending_before_the_previous_start_is_not_merged(): void
    {
        // Entrée mal triée : un tronçon entièrement avant le précédent n'est pas fondu (« to >= from » du précédent).
        $this->assertCount(2, LineSpeeds::merge([self::r(1000, 2000, 100), self::r(0, 900, 100)]));
    }

    public function test_the_merge_compares_with_the_last_merged_range_only(): void
    {
        $merged = LineSpeeds::merge([self::r(0, 1000, 100), self::r(1000, 2000, 80), self::r(2000, 3000, 100)]);

        $this->assertSame([100, 80, 100], array_column($merged, 'kmh'));
    }

    public function test_a_third_range_of_the_speed_of_the_second_joins_it_not_the_first(): void
    {
        $merged = LineSpeeds::merge([self::r(0, 1000, 100), self::r(1000, 2000, 80), self::r(2000, 3000, 80)]);

        $this->assertSame([self::r(0, 1000, 100), self::r(1000, 3000, 80)], $merged);
        $this->assertSame([self::r(100, 200, 50)], LineSpeeds::merge([self::r(100, 200, 50), self::r(100, 100, 50)]));
    }

    public function test_empty_and_single_inputs(): void
    {
        $this->assertSame([], LineSpeeds::merge([]));
        $this->assertSame([self::r(0, 0, 0)], LineSpeeds::merge([self::r(0, 0, 0)]));
    }

    public function test_a_chain_of_small_gaps_is_merged_into_one(): void
    {
        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = self::r($i * 1050, $i * 1050 + 1000, 120);
        }

        $this->assertSame([self::r(0, 99 * 1050 + 1000, 120)], LineSpeeds::merge($rows));
    }

    public function test_the_summary_of_nothing(): void
    {
        $this->assertSame(['max_speed_kmh' => null, 'min_speed_kmh' => null, 'covered_m' => 0, 'by_speed' => []], LineSpeeds::summary([]));
    }

    public function test_the_summary_sums_by_speed_highest_first_and_gives_the_extremes(): void
    {
        $s = LineSpeeds::summary([self::span(0, 1000, 60), self::span(1000, 2000, 160), self::span(3000, 500, 60), self::span(3500, 700, 220)]);

        $this->assertSame([['max_speed_kmh' => 220, 'length_m' => 700], ['max_speed_kmh' => 160, 'length_m' => 2000], ['max_speed_kmh' => 60, 'length_m' => 1500]], $s['by_speed']);
        $this->assertSame([220, 60, 4200], [$s['max_speed_kmh'], $s['min_speed_kmh'], $s['covered_m']]);
    }

    public function test_the_covered_length_is_the_union_with_gaps_nesting_and_touching_ranges(): void
    {
        $this->assertSame(1000, LineSpeeds::summary([self::span(0, 1000, 100), self::span(0, 1000, 80)])['covered_m']);
        $this->assertSame(1000, LineSpeeds::summary([self::span(0, 1000, 100), self::span(200, 300, 80)])['covered_m']);
        $this->assertSame(2000, LineSpeeds::summary([self::span(0, 1000, 100), self::span(1000, 1000, 80)])['covered_m']);
        $this->assertSame(2000, LineSpeeds::summary([self::span(0, 1000, 100), self::span(1001, 1000, 80)])['covered_m']);
        $this->assertSame(1500, LineSpeeds::summary([self::span(0, 1000, 100), self::span(500, 1000, 80)])['covered_m']);
        $this->assertSame(0, LineSpeeds::summary([self::span(500, 0, 80)])['covered_m']);
    }

    public function test_the_covered_length_does_not_depend_on_the_input_order(): void
    {
        $a = [self::span(5000, 1000, 100), self::span(0, 1000, 80), self::span(500, 1000, 60)];

        $this->assertSame(LineSpeeds::summary($a)['covered_m'], LineSpeeds::summary(array_reverse($a))['covered_m']);
        $this->assertSame(2500, LineSpeeds::summary($a)['covered_m']);
    }

    public function test_the_two_dataset_provenances_and_constants(): void
    {
        $p = LineSpeeds::provenance();

        $this->assertSame('ODbL-1.0', $p['ranges']['license']);
        $this->assertSame('CC BY-SA 4.0', $p['history']['license']);
        $this->assertSame(LineSpeeds::DATASET, $p['ranges']['dataset']);
        $this->assertStringContainsString(LineSpeeds::DATASET, LineSpeeds::DATASET_URL);
    }
}
