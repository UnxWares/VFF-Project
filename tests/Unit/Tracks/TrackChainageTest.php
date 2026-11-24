<?php

namespace Tests\Unit\Tracks;

use App\Services\Tracks\TrackChainage;
use App\Services\Tracks\TrackGeometry;
use PHPUnit\Framework\TestCase;

/**
 * PK of places near a line, on its reference trace calibrated on sourced PKs: interpolation, direction of the PK
 * given by the anchors, strong anchors over weak ones, spike filter, side of the offset, PK beyond the ends,
 * inheritance between parts of the trace and honest precision.
 */
class TrackChainageTest extends TestCase
{
    /** Longitude step of 100 m at latitude 45 (east-west line). */
    private const STEP = 100 / (TrackGeometry::M_PER_DEG * 0.70710678);

    /** Flat east-west polyline at latitude 45 from $fromM to $toM metres east of longitude 2. */
    private static function line(float $fromM, float $toM): array
    {
        $c = [];
        for ($m = $fromM; $m <= $toM + 0.1; $m += 500) {
            $c[] = 2.0 + $m / 100 * self::STEP;
            $c[] = 45.0;
        }

        return $c;
    }

    private static function at(float $m, float $north = 0.0): array
    {
        return [2.0 + $m / 100 * self::STEP, 45.0 + $north / TrackGeometry::M_PER_DEG];
    }

    private static function anchor(float $m, int $pk, bool $strong = false, float $north = 0.0): array
    {
        [$lon, $lat] = self::at($m, $north);

        return ['lon' => $lon, 'lat' => $lat, 'pk_m' => $pk, 'max_m' => 500.0, 'strong' => $strong];
    }

    public function test_interpolates_between_anchors_and_says_derived(): void
    {
        $chainage = new TrackChainage([self::line(0, 10_000)], [self::anchor(1_000, 51_000), self::anchor(9_000, 59_400)]);
        [$lon, $lat] = self::at(5_000, 12.0);
        $at = $chainage->locate($lon, $lat);

        // 4 km of trace out of 8 km between PK 51,000 and 59,400.
        $this->assertSame(55_200, $at['pk_m']);
        $this->assertSame('derived', $at['precision']);
        $this->assertEqualsWithDelta(12.0, $at['distance_m'], 0.1);
        // North of an eastward line, PKs growing eastward: on the left.
        $this->assertEqualsWithDelta(12.0, $at['offset_m'], 0.1);
        $this->assertNull($chainage->locate($lon, $lat + 100 / TrackGeometry::M_PER_DEG));
    }

    public function test_the_pk_direction_follows_the_anchors_and_flips_the_side(): void
    {
        // PKs grow westward: the part is read backwards, north becomes the right-hand side.
        $chainage = new TrackChainage([self::line(0, 10_000)], [self::anchor(1_000, 20_000), self::anchor(9_000, 12_000)]);
        [$lon, $lat] = self::at(3_000, 5.0);
        $at = $chainage->locate($lon, $lat);

        $this->assertSame(18_000, $at['pk_m']);
        $this->assertEqualsWithDelta(-5.0, $at['offset_m'], 0.1);
        $this->assertEqualsWithDelta(-1.0, $at['direction'][0], 0.01);
    }

    public function test_a_strong_anchor_shadows_a_nearby_station(): void
    {
        // The station building stands 300 m away (along the trace) from where its PK is read; a level crossing
        // 400 m away gives the true chainage.
        $anchors = [self::anchor(0, 10_000, true), self::anchor(5_300, 15_000), self::anchor(5_400, 15_400, true), self::anchor(10_000, 20_000, true)];
        $chainage = new TrackChainage([self::line(0, 10_000)], $anchors);
        [$lon, $lat] = self::at(5_300);

        $this->assertSame(15_300, $chainage->locate($lon, $lat)['pk_m']);
        $this->assertSame(3, $chainage->anchorCount());
    }

    public function test_one_wrong_anchor_is_dropped_but_a_jump_of_the_numbering_is_kept(): void
    {
        $this->assertSame([[0.0, 0], [2_000.0, 2_000], [3_000.0, 3_000]], TrackChainage::despike([[0.0, 0], [1_000.0, 1_900], [2_000.0, 2_000], [3_000.0, 3_000]]));
        // A jump of the PK numbering (one stretched interval) stays.
        $jump = [[0.0, 0], [1_000.0, 1_000], [2_000.0, 9_000], [3_000.0, 10_000]];
        $this->assertSame($jump, TrackChainage::despike($jump));
    }

    public function test_beyond_the_ends_the_pk_runs_on_and_far_extrapolation_is_approximate(): void
    {
        $chainage = new TrackChainage([self::line(0, 10_000)], [self::anchor(0, 100_000), self::anchor(10_000, 110_000)]);
        // A terminus track 200 m beyond the east end of the trace.
        [$lon, $lat] = self::at(10_200, 8.0);
        $at = $chainage->locate($lon, $lat, 300.0);
        $this->assertSame(110_200, $at['pk_m']);
        $this->assertSame('derived', $at['precision']);

        // Anchors in the west only: 6 km beyond the last one is approximate.
        $west = new TrackChainage([self::line(0, 10_000)], [self::anchor(0, 0), self::anchor(2_000, 2_000)]);
        [$lon, $lat] = self::at(9_000);
        $this->assertSame(9_000, $west->locate($lon, $lat)['pk_m']);
        $this->assertSame('approximate', $west->locate($lon, $lat)['precision']);
    }

    public function test_a_part_without_anchor_inherits_the_pk_of_the_part_it_leaves(): void
    {
        // A branch leaving the east end of the main part towards the north-east: no anchor of its own.
        $branch = [];
        for ($m = 0; $m <= 2_000; $m += 500) {
            [$lon, $lat] = self::at(10_000 + $m / 2, $m);
            $branch[] = $lon;
            $branch[] = $lat;
        }
        $chainage = new TrackChainage([self::line(0, 10_000), $branch], [self::anchor(0, 0), self::anchor(10_000, 10_000)]);
        [$lon, $lat] = self::at(10_500, 1_000);
        $at = $chainage->locate($lon, $lat);

        // The PK grows into the branch as on the main part: 10,000 + 1,118 m of branch.
        $this->assertSame(1, $at['part']);
        $this->assertEqualsWithDelta(11_118, $at['pk_m'], 2);
        $this->assertSame('approximate', $at['precision']);
    }

    public function test_no_anchor_no_pk(): void
    {
        $chainage = new TrackChainage([self::line(0, 10_000)], [self::anchor(5_000, 5_000)]);
        [$lon, $lat] = self::at(1_000);

        $this->assertFalse($chainage->calibrated());
        $this->assertNull($chainage->locate($lon, $lat)['pk_m']);
        $this->assertEqualsWithDelta(0.0, $chainage->locate($lon, $lat)['distance_m'], 0.5);
    }
}
