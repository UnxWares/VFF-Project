<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileChain;
use App\Services\Profiles\ProfilePath;
use PHPUnit\Framework\TestCase;

/**
 * Longueur conventionnelle du trou avant un embranchement et assemblage des embranchements
 * (ProfileChain::append) : tests ajoutés après une passe de mutation (Infection, tests/Mutation/Profiles).
 */
class ProfileChainAppendTest extends TestCase
{
    public function test_the_jump_before_a_branch_is_one_percent_of_the_main_line_with_a_floor_of_500_m(): void
    {
        $this->assertSame(500.0, ProfileChain::branchJump(10_000.0));
        $this->assertSame(500.0, ProfileChain::branchJump(50_000.0));
        $this->assertSame(1000.0, ProfileChain::branchJump(100_000.0));
        $this->assertSame(1500.0, ProfileChain::branchJump(150_000.0));
        // Arrondi au plus proche : 1 500,4 donne 1 500, 1 500,6 donne 1 501.
        $this->assertSame(1500.0, ProfileChain::branchJump(150_040.0));
        $this->assertSame(1501.0, ProfileChain::branchJump(150_060.0));
    }

    public function test_an_empty_list_of_branches_leaves_the_path_untouched(): void
    {
        $main = new ProfilePath([[2.0, 48.0], [2.01, 48.0]], [[100.0, 200.0]]);

        $result = ProfileChain::append($main, []);

        $this->assertSame($main, $result['path']);
        $this->assertSame([], $result['branches']);
    }

    public function test_a_branch_keeps_its_own_gaps_and_imposed_lengths_and_starts_after_a_gap_of_the_conventional_jump(): void
    {
        $main = new ProfilePath([[2.0, 48.0], [2.01, 48.0], [2.02, 48.0]]);
        $branch = new ProfilePath([[2.021, 48.0], [2.03, 48.0], [2.04, 48.0], [2.05, 48.0]], [[100.0, 300.0]], [2 => 1000.0]);

        $result = ProfileChain::append($main, [$branch]);

        $mainLength = $main->length();
        $start = $mainLength + 500.0;
        $this->assertEqualsWithDelta($start + $branch->length(), $result['path']->length(), 1e-6);
        // Le segment imposé de 1 000 m compte dans la longueur de l'embranchement.
        $this->assertGreaterThan(1000.0, $branch->length());
        $this->assertCount(1, $result['branches']);
        $this->assertEqualsWithDelta($start, $result['branches'][0][0], 1e-6);
        $this->assertEqualsWithDelta($start + $branch->length(), $result['branches'][0][1], 1e-6);
        // Trou avant l'embranchement, puis trou propre de l'embranchement décalé à son début.
        $this->assertCount(2, $result['path']->gaps);
        $this->assertEqualsWithDelta([$mainLength, $start], $result['path']->gaps[0], 1e-6);
        $this->assertEqualsWithDelta([$start + 100.0, $start + 300.0], $result['path']->gaps[1], 1e-6);
    }

    public function test_a_branch_is_turned_to_start_from_the_end_nearest_to_the_last_vertex_of_the_path(): void
    {
        // Les deux bouts de l'embranchement : l'un tout près du sommet précédent, l'autre plus près du dernier sommet.
        $main = new ProfilePath([[2.0, 48.0], [2.01, 48.0], [2.02, 48.0]]);
        $branch = new ProfilePath([[2.01, 48.0003], [2.0175, 48.0003], [2.025, 48.0003]]);

        $result = ProfileChain::append($main, [$branch]);

        $this->assertSame([2.025, 48.0003], $result['path']->coords[3]);
        $this->assertSame([2.01, 48.0003], $result['path']->coords[5]);
    }
}
