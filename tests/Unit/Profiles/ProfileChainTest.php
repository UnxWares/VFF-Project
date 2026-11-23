<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileChain;
use PHPUnit\Framework\TestCase;

/**
 * Assemblage des morceaux du tracé de référence en un parcours de bout en bout : l'artère
 * principale est suivie, les trous franchis et rendus quelle que soit leur longueur, les épis mis
 * bout à bout ensuite en embranchements.
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

    public function test_keeps_a_piece_far_beyond_a_long_gap(): void
    {
        // Deux morceaux séparés de 0,8° de latitude (≈ 89 km) : tous deux dans le parcours.
        $long = [[2.0, 45.0], [2.0, 45.2]];
        $far = [[2.0, 46.0], [2.0, 46.05]];

        $path = ProfileChain::build([$far, $long]);

        $this->assertNotNull($path);
        $this->assertCount(1, $path->gaps);
        [$from, $to] = $path->gaps[0];
        $this->assertEqualsWithDelta(88_956, $to - $from, 100);
        $this->assertEqualsWithDelta(22_239 + 88_956 + 5_560, $path->length(), 150);
    }

    public function test_links_three_pieces_in_order_along_the_line(): void
    {
        // Trois morceaux alignés, séparés de 10 km : le parcours les visite dans l'ordre (pas de
        // saut direct du premier au troisième).
        $a = [[2.0, 45.0], [2.0, 45.1]];
        $b = [[2.0, 45.19], [2.0, 45.29]];
        $c = [[2.0, 45.38], [2.0, 45.48]];

        $path = ProfileChain::build([$c, $a, $b]);

        $this->assertNotNull($path);
        $this->assertCount(2, $path->gaps);
        $this->assertEqualsWithDelta(53_375, $path->length(), 100);
    }

    public function test_spur_becomes_a_branch_after_a_conventional_gap(): void
    {
        $north = [[2.0, 45.0], [2.0, 45.1]];
        $south = [[2.0, 45.1], [2.0, 45.2]];
        $spur = [[2.0, 45.1], [2.0127, 45.1]];

        $chain = ProfileChain::assemble([$spur, $north, $south]);
        $this->assertNotNull($chain);
        $this->assertCount(1, $chain['branches']);
        $this->assertEqualsWithDelta(1_000, $chain['branches'][0]->length(), 10);
        $this->assertSame(0.0, $chain['off_path_m']);

        ['path' => $path, 'branches' => $ranges] = ProfileChain::append($chain['path'], $chain['branches']);
        $main = $chain['path']->length();
        $jump = ProfileChain::branchJump($main);
        $this->assertSame(500.0, $jump);
        // Trou de longueur conventionnelle, puis l'épi.
        $this->assertCount(1, $path->gaps);
        $this->assertEqualsWithDelta($main, $path->gaps[0][0], 0.01);
        $this->assertEqualsWithDelta($main + $jump, $path->gaps[0][1], 0.01);
        $this->assertEqualsWithDelta($main + $jump, $ranges[0][0], 0.01);
        $this->assertEqualsWithDelta($main + $jump + 1_000, $ranges[0][1], 10);
        $this->assertEqualsWithDelta($ranges[0][1], $path->length(), 0.01);
    }

    public function test_turns_at_a_tee_on_the_inside_of_a_piece(): void
    {
        // Moret - Lyon (23/11/2026) : la suite de la ligne part du MILIEU d'un long morceau qui continue tout droit
        // (raccord en T, ST_LineMerge ne coupe pas). Sans coupe au raccord, le parcours sautait à vol d'oiseau.
        $trunk = [[2.0, 45.0], [2.0, 45.1], [2.0, 45.2], [2.0, 45.3]];
        $onward = [[2.0001, 45.2], [2.1, 45.2], [2.3, 45.2]];

        $chain = ProfileChain::assemble([$trunk, $onward]);

        $this->assertNotNull($chain);
        $this->assertSame([], $chain['path']->gaps);
        // Parcours : 22,2 km vers le nord puis 23,6 km vers l'est ; le bout nord de 11,1 km reste en embranchement.
        $this->assertEqualsWithDelta(22_239 + 23_580, $chain['path']->length(), 120);
        $this->assertCount(1, $chain['branches']);
    }

    public function test_a_tee_left_a_little_short_is_crossed_by_a_short_gap(): void
    {
        // Bout arrêté à 39 m de l'intérieur du morceau (757000, Les Laumes) : une lacune de 39 m, pas de 11 km.
        $trunk = [[2.0, 45.0], [2.0, 45.2], [2.0, 45.3]];
        $onward = [[2.0005, 45.2], [2.3, 45.2]];

        $path = ProfileChain::build([$trunk, $onward]);

        $this->assertNotNull($path);
        $this->assertCount(1, $path->gaps);
        [$from, $to] = $path->gaps[0];
        $this->assertEqualsWithDelta(39, $to - $from, 2);
    }

    public function test_split_at_tees_cuts_only_inside_and_keeps_every_vertex(): void
    {
        $trunk = [[2.0, 45.0], [2.0, 45.1], [2.0, 45.2]];
        $spur = [[2.0001, 45.05], [2.05, 45.05]];
        // Bout déjà sur l'extrémité du tronc (moins de 30 m) : pas de coupe.
        $end = [[2.0, 45.2001], [2.0, 45.25]];

        $parts = ProfileChain::splitAtTees([$trunk, $spur, $end]);

        $this->assertCount(4, $parts);
        $this->assertSame([2.0, 45.0], $parts[0][0]);
        $this->assertEqualsWithDelta(45.05, $parts[0][1][1], 1e-9);
        $this->assertSame($parts[0][1], $parts[1][0]);
        $this->assertSame([2.0, 45.2], $parts[1][2]);
        $this->assertSame([$spur, $end], [$parts[2], $parts[3]]);
    }

    public function test_tiny_leftovers_are_counted_off_path(): void
    {
        // Bout de 39 m accroché au nœud qui sépare les deux morceaux de la ligne.
        $a = [[2.0, 45.0], [2.0, 45.05]];
        $b = [[2.0, 45.05], [2.0, 45.1]];
        $stub = [[2.0, 45.05], [2.0005, 45.05]];

        $chain = ProfileChain::assemble([$a, $b, $stub]);

        $this->assertNotNull($chain);
        $this->assertSame([], $chain['branches']);
        $this->assertEqualsWithDelta(39, $chain['off_path_m'], 2);
    }

    public function test_returns_null_without_usable_parts(): void
    {
        $this->assertNull(ProfileChain::build([]));
        $this->assertNull(ProfileChain::build([[[2.0, 45.0]]]));
    }
}
