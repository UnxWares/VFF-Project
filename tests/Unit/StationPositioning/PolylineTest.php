<?php

namespace Tests\Unit\StationPositioning;

use App\Services\StationPositioning\Polyline;
use PHPUnit\Framework\TestCase;

/**
 * Axe d'une ligne (Polyline) : abscisse curviligne, point à une abscisse, morceaux recousus.
 */
class PolylineTest extends TestCase
{
    public function test_projection_gives_the_measure_and_the_distance(): void
    {
        $axis = new Polyline([[0, 0], [1000, 0], [1000, 1000]]);

        $this->assertSame(2000.0, $axis->length());
        $this->assertEqualsWithDelta([500, 30], $axis->project(500, 30), 1e-9);
        $this->assertEqualsWithDelta([1500, 20], $axis->project(980, 500), 1e-9);
        $this->assertEqualsWithDelta([0, 100], $axis->project(-100, 0), 1e-9);
    }

    public function test_point_at_a_measure_is_bounded_to_the_axis(): void
    {
        $axis = new Polyline([[0, 0], [1000, 0], [1000, 1000]]);

        $this->assertEqualsWithDelta([1000, 250], $axis->pointAt(1250), 1e-9);
        $this->assertEqualsWithDelta([0, 0], $axis->pointAt(-50), 1e-9);
        $this->assertEqualsWithDelta([1000, 1000], $axis->pointAt(5000), 1e-9);
    }

    public function test_parts_are_chained_end_to_end_reversed_when_needed(): void
    {
        $axis = Polyline::chain([
            [[0, 0], [5000, 0]],
            [[5600, 0], [5100, 0]],          // à l'envers, 100 m de trou
            [[-2000, 0], [-200, 0]],         // en tête
        ]);

        $this->assertEqualsWithDelta(5000 + 100 + 500 + 200 + 1800, $axis->length(), 1e-6);
        $this->assertEqualsWithDelta([-2000, 0], $axis->pointAt(0), 1e-9);
        $this->assertEqualsWithDelta([5600, 0], $axis->pointAt($axis->length()), 1e-9);
    }

    public function test_far_parts_and_parts_folded_along_the_axis_are_left_out(): void
    {
        $axis = Polyline::chain([
            [[0, 0], [10000, 0]],
            [[10000, 30], [6000, 30]],       // doublon parallèle replié le long de l'axe
            [[30000, 0], [31000, 0]],        // à 20 km : trop loin
        ]);

        $this->assertSame(10000.0, $axis->length());
    }

    public function test_no_part_gives_no_axis(): void
    {
        $this->assertNull(Polyline::chain([[[1, 1]]]));
    }
}
