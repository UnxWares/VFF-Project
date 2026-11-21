<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\TrackIndex;
use PHPUnit\Framework\TestCase;

/**
 * Index des tronçons de la BD TOPO : tronçon le plus proche dans la tolérance, altitude
 * interpolée le long du segment, rien au-delà de la tolérance.
 */
class TrackIndexTest extends TestCase
{
    public function test_finds_the_nearest_track_and_interpolates_its_height(): void
    {
        $index = new TrackIndex([
            ['kind' => 'ground', 'coords' => [[2.0, 45.0], [2.0, 45.01]], 'z' => [100.0, 110.0]],
            // Voie parallèle à 40 m à l'est, en tunnel.
            ['kind' => 'tunnel', 'coords' => [[2.0005, 45.0], [2.0005, 45.01]], 'z' => [null, null]],
        ]);

        $found = $index->nearest([2.0001, 45.005], 20.0);

        $this->assertSame('ground', $found['kind']);
        $this->assertEqualsWithDelta(105.0, $found['z'], 0.01);
        $this->assertEqualsWithDelta(7.9, $found['offset'], 0.2);
    }

    public function test_returns_nothing_beyond_the_tolerance_or_for_an_empty_index(): void
    {
        $index = new TrackIndex([['kind' => 'ground', 'coords' => [[2.0, 45.0], [2.0, 45.01]], 'z' => [100.0, 110.0]]]);

        $this->assertNull($index->nearest([2.001, 45.005], 20.0)['kind']);
        $this->assertTrue((new TrackIndex)->isEmpty());
        $this->assertSame(INF, (new TrackIndex)->nearest([2.0, 45.0], 20.0)['offset']);
    }

    public function test_a_long_segment_is_found_far_from_its_vertices(): void
    {
        // Segment de 5 km sans sommet intermédiaire : rangé dans toutes les cases qu'il couvre.
        $index = new TrackIndex([['kind' => 'bridge', 'coords' => [[2.0, 45.0], [2.0, 45.045]], 'z' => [50.0, 95.0]]]);

        $found = $index->nearest([2.0, 45.0225], 20.0);

        $this->assertSame('bridge', $found['kind']);
        $this->assertEqualsWithDelta(72.5, $found['z'], 0.01);
    }
}
