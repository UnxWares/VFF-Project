<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\PkCalibration;
use PHPUnit\Framework\TestCase;

/**
 * PK des points du profil calé sur les PK officiels des gares : suite croissante gardée,
 * interpolation entre les gares, un mètre par mètre au-delà, rien sans gare.
 */
class PkCalibrationTest extends TestCase
{
    public function test_keeps_the_longest_increasing_run_of_stations(): void
    {
        // La gare à 5 km porte un PK d'une autre origine (90 km) : écartée.
        $anchors = PkCalibration::monotone([[10_000.0, 20_000], [0.0, 10_000], [5_000.0, 90_000], [20_000.0, 31_000]]);

        $this->assertSame([[0.0, 10_000], [10_000.0, 20_000], [20_000.0, 31_000]], $anchors);
    }

    public function test_interpolates_between_stations_and_extrapolates_one_metre_per_metre(): void
    {
        $anchors = [[1_000.0, 10_000], [11_000.0, 30_000]];

        $this->assertSame([9_000, 10_000, 20_000, 30_000, 31_000], PkCalibration::estimate($anchors, [0.0, 1_000.0, 6_000.0, 11_000.0, 12_000.0]));
    }

    public function test_no_station_no_pk(): void
    {
        $this->assertSame([null, null], PkCalibration::estimate([], [0.0, 100.0]));
        $this->assertSame([], PkCalibration::monotone([]));
    }
}
