<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\PkCalibration;
use ErrorException;
use PHPUnit\Framework\TestCase;

/**
 * Ordre, égalités et arrondis de PkCalibration : tests ajoutés après une passe de mutation
 * (Infection, tests/Mutation/Profiles). Les avertissements PHP (indice absent) font échouer le test.
 */
class PkCalibrationBoundariesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            throw new ErrorException($str, 0, $no, $file, $line);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    public function test_anchors_are_sorted_by_distance_not_by_pk(): void
    {
        $kept = PkCalibration::monotone([[300.0, 50], [100.0, 10], [200.0, 60]]);

        $this->assertSame([[100.0, 10], [200.0, 60]], $kept);
    }

    public function test_anchors_at_the_same_distance_are_ordered_by_pk(): void
    {
        $this->assertSame([[100.0, 300], [200.0, 600]], PkCalibration::monotone([[200.0, 600], [100.0, 500], [100.0, 300]]));
    }

    public function test_a_longer_chain_is_kept_whole(): void
    {
        $anchors = [[0.0, 0], [1000.0, 1000], [2000.0, 2000], [3000.0, 3000]];

        $this->assertSame($anchors, PkCalibration::monotone($anchors));
    }

    public function test_equal_distances_or_equal_pk_are_not_a_chain(): void
    {
        $this->assertSame([[100.0, 500]], PkCalibration::monotone([[100.0, 500], [200.0, 500]]));
        $this->assertSame([[100.0, 300]], PkCalibration::monotone([[100.0, 300], [100.0, 500]]));
    }

    public function test_on_equal_chains_the_first_predecessor_found_is_kept(): void
    {
        $this->assertSame([[100.0, 30], [200.0, 40]], PkCalibration::monotone([[100.0, 30], [150.0, 20], [200.0, 40]]));
    }

    public function test_a_single_anchor_and_no_anchor_are_returned_as_they_are(): void
    {
        $this->assertSame([[100.0, 5]], PkCalibration::monotone([[100.0, 5]]));
        $this->assertSame([], PkCalibration::monotone([]));
    }

    public function test_the_pk_is_interpolated_between_the_right_pair_of_anchors(): void
    {
        $anchors = [[0.0, 0], [1000.0, 3000], [2000.0, 5000]];

        $this->assertSame([0, 1500, 3000, 4000, 5000], PkCalibration::estimate($anchors, [0, 500, 1000, 1500, 2000]));
    }

    public function test_the_pk_is_rounded_to_the_nearest_metre(): void
    {
        $anchors = [[0.0, 0], [1000.0, 1000]];

        $this->assertSame([0, 1, 1, 0], PkCalibration::estimate($anchors, [0.4, 0.6, 1.4, 0.2]));
    }

    public function test_before_the_first_and_after_the_last_anchor_the_pk_moves_one_metre_per_metre(): void
    {
        $anchors = [[1000.0, 2000], [3000.0, 4000]];

        $this->assertSame([1500, 2000, 4000, 4500], PkCalibration::estimate($anchors, [500, 1000, 3000, 3500]));
        $this->assertSame([null, null], PkCalibration::estimate([], [1, 2]));
    }
}
