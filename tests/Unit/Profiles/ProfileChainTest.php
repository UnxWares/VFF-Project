<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileChain;
use PHPUnit\Framework\TestCase;

/**
 * Assemblage des morceaux du tracé en un parcours de bout en bout : l'artère principale est
 * suivie, les épis courts laissés de côté, les lacunes de moins de 5 km franchies et rendues.
 */
class ProfileChainTest extends TestCase
{
    public function test_follows_the_trunk_and_leaves_a_short_spur_aside(): void
    {
        // Artère nord-sud en deux morceaux qui se touchent, épi de 1 km vers l'est au milieu.
        $north = [[2.0, 45.0], [2.0, 45.1]];
        $south = [[2.0, 45.1], [2.0, 45.2]];
        $spur = [[2.0, 45.1], [2.0127, 45.1]];

        $path = ProfileChain::build([$spur, $north, $south]);

        $this->assertNotNull($path);
        $this->assertEqualsWithDelta(22_239, $path->length(), 30);
        $this->assertSame([], $path->gaps);
        $lats = array_column($path->coords, 1);
        $this->assertEqualsWithDelta(0.2, abs($lats[0] - $lats[count($lats) - 1]), 1e-9);
    }

    public function test_bridges_a_small_gap_and_reports_it(): void
    {
        // Deux morceaux séparés de 0,01° de latitude (≈ 1,1 km).
        $a = [[2.0, 45.0], [2.0, 45.05]];
        $b = [[2.0, 45.06], [2.0, 45.1]];

        $path = ProfileChain::build([$b, $a]);

        $this->assertNotNull($path);
        $this->assertCount(1, $path->gaps);
        [$from, $to] = $path->gaps[0];
        $this->assertEqualsWithDelta(1_112, $to - $from, 5);
        $this->assertEqualsWithDelta(11_120, $path->length(), 20);
    }

    public function test_keeps_only_the_longest_component_beyond_the_gap_limit(): void
    {
        $long = [[2.0, 45.0], [2.0, 45.2]];
        $far = [[2.0, 46.0], [2.0, 46.05]];

        $path = ProfileChain::build([$far, $long]);

        $this->assertNotNull($path);
        $this->assertEqualsWithDelta(22_239, $path->length(), 30);
    }

    public function test_returns_null_without_usable_parts(): void
    {
        $this->assertNull(ProfileChain::build([]));
        $this->assertNull(ProfileChain::build([[[2.0, 45.0]]]));
    }
}
