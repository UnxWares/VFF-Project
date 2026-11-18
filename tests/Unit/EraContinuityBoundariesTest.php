<?php

namespace Tests\Unit;

use App\Services\EraContinuity as E;
use PHPUnit\Framework\TestCase;

/**
 * Continuité des portions d'une ligne (EraContinuity) : seuils exacts et
 * ordre de traitement, en complément d'EraContinuityTest / EdgeCasesTest.
 *   - chaînage : extrémités à exactement la tolérance reliées, cases voisines
 *     en diagonale, longueurs déduites des extrémités, égalité départagée par
 *     l'indice ;
 *   - lissage : seuils minPiece / maxGap inclus ou exclus, suite la plus courte
 *     traitée d'abord, voisine de gauche à longueur égale, positions transmises
 *     au callback de justification ;
 *   - ouvertures « en sandwich » : bornes de longueur et d'années, voisine
 *     retenue, plusieurs suites ;
 *   - trous, point daté le plus proche, ouvertures et fermetures déduites.
 */
class EraContinuityBoundariesTest extends TestCase
{
    private const SERVICE = 'in_service/mixed';

    public function test_ends_exactly_at_the_tolerance_are_linked(): void
    {
        $this->assertSame([[[0, true], [1, true]]], E::chains([[0, 0, 100, 0], [105, 0, 200, 0]], 5.0));
        $this->assertSame([[[0, true]], [[1, true]]], E::chains([[0, 0, 100, 0], [105.01, 0, 200, 0]], 5.0));
    }

    public function test_ends_in_diagonal_grid_cells_are_linked(): void
    {
        // (4,9 ; 4,9) et (5,1 ; 5,1) : cases (0, 0) et (1, 1) de la grille de 5 m.
        $this->assertSame([[[0, true], [1, true]]], E::chains([[0, 0, 4.9, 4.9], [5.1, 5.1, 20, 20]], 5.0));
    }

    public function test_lengths_default_to_the_distance_between_the_ends(): void
    {
        // Nœud (5000, 5000) : A (1 000 m) et C (500 m) sont reliés, B (10 m) reste libre.
        $chains = E::chains([[4000, 5000, 5000, 5000], [5000, 5000, 5000, 5010], [5000, 5000, 5500, 5000]], 5.0);

        $this->assertSame([[[0, true], [2, true]], [[1, true]]], $chains);
    }

    public function test_equal_lengths_at_a_node_link_the_lowest_indices(): void
    {
        $ends = [[0, 0, 100, 0], [100, 0, 200, 0], [100, 0, 100, 100]];

        $this->assertSame([[[0, true], [1, true]], [[2, true]]], E::chains($ends, 5.0));
        $this->assertSame([[[0, true], [1, true]], [[2, true]]], E::chains($ends, 5.0, [100.0, 100.0, 100.0]));
        $this->assertSame([[[0, true], [1, false]]], E::chains([[200, 0, 100, 0], [0, 0, 100, 0]], 5.0), 'second morceau parcouru à rebours');
    }

    public function test_min_piece_threshold_is_inclusive(): void
    {
        $this->assertSame([0, 1, 2], E::smooth([100, 100, 100], [self::SERVICE, 'closed', self::SERVICE], 100, 0));
        $this->assertSame([0, 0, 2], E::smooth([100, 99.9, 100], [self::SERVICE, 'closed', self::SERVICE], 100, 0));
    }

    public function test_max_gap_threshold_is_exclusive(): void
    {
        $this->assertSame([0, 1, 2], E::smooth([100, 200, 100], [self::SERVICE, '-', self::SERVICE], 50, 200));
        $this->assertSame([0, 0, 2], E::smooth([100, 199, 100], [self::SERVICE, '-', self::SERVICE], 50, 200));
    }

    public function test_short_piece_between_equal_neighbours_goes_left(): void
    {
        $this->assertSame([0, 0, 2], E::smooth([200, 10, 200], [self::SERVICE, '-', 'closed/x'], 50, 0));
    }

    public function test_shortest_run_is_absorbed_first(): void
    {
        // B (30 m) d'abord, vers la gauche ; puis C (2 × 20 m) entre deux A, vers la gauche aussi.
        $sig = [self::SERVICE, self::SERVICE, self::SERVICE, 'closed', '-', '-', self::SERVICE, self::SERVICE, self::SERVICE];

        $this->assertSame([0, 1, 2, 2, 2, 2, 6, 7, 8], E::smooth([300, 300, 300, 30, 20, 20, 300, 300, 300], $sig, 100, 0));
    }

    public function test_justification_receives_current_sources_of_the_surroundings(): void
    {
        $sig = [self::SERVICE, self::SERVICE, self::SERVICE, self::SERVICE, 'closed', self::SERVICE, '-',
            self::SERVICE, self::SERVICE, self::SERVICE, self::SERVICE];
        $calls = [];

        $res = E::smooth(array_fill(0, 11, 100), $sig, 150, 0, function (array $own, array $around) use (&$calls) {
            $calls[] = [$own, $around];

            return false;
        });

        // Position 4 absorbée d'abord (elle prend la source 3) ; la position 6 voit alors 3, 3, 5 à sa gauche.
        $this->assertSame([[[4], [1, 2, 3, 5]], [[6], [3, 3, 5, 7, 8, 9]]], $calls);
        $this->assertSame([0, 1, 2, 3, 3, 5, 5, 7, 8, 9, 10], $res);
    }

    public function test_a_justified_run_does_not_stop_the_search(): void
    {
        $sig = [self::SERVICE, self::SERVICE, self::SERVICE, 'closed', self::SERVICE, self::SERVICE, '-', self::SERVICE, self::SERVICE, self::SERVICE];

        $res = E::smooth([100, 100, 100, 10, 100, 100, 20, 100, 100, 100], $sig, 50, 0, fn (array $own) => $own === [3]);

        $this->assertSame([0, 1, 2, 3, 4, 5, 5, 7, 8, 9], $res, 'la position 3 justifiée reste, la 6 est absorbée');
    }

    public function test_sandwiched_openings_take_the_later_neighbour(): void
    {
        $this->assertSame([2 => ['date' => '1860-01-01', 'from' => 3]],
            E::sandwichedOpenings([100, 100, 100, 100, 100], ['1857-01-01', '1857-01-01', '1965-05-01', '1860-01-01', '1860-01-01'], 500, 20));
        $this->assertSame([1 => ['date' => '1857-01-01', 'from' => 0]], E::sandwichedOpenings([100, 100, 100], ['1857-01-01', null, '1857-01-01'], 500, 20),
            'voisines de même date : celle de gauche');
    }

    public function test_sandwiched_openings_bounds(): void
    {
        $this->assertSame([], E::sandwichedOpenings([100, 100, 100], ['1857-01-01', '1877-01-01', '1857-01-01'], 500, 20), 'exactement 20 ans après');
        $this->assertSame([1 => ['date' => '1857-01-01', 'from' => 0]], E::sandwichedOpenings([100, 100, 100], ['1857-01-01', '1878-01-01', '1857-01-01'], 500, 20));
        $this->assertSame([], E::sandwichedOpenings([100, 500, 100], ['1857-01-01', null, '1857-01-01'], 500, 20), 'longueur maximale exclue');
        $this->assertSame([1 => ['date' => '1857-01-01', 'from' => 0]], E::sandwichedOpenings([100, 499, 100], ['1857-01-01', null, '1857-01-01'], 500, 20));
        $this->assertSame([], E::sandwichedOpenings([100, 100, 100], [null, null, '1857-01-01'], 500, 20), 'une seule voisine datée');
        $this->assertSame([], E::sandwichedOpenings([100, 100, 100], ['1857-01-01', null, null], 500, 20));
    }

    public function test_every_sandwiched_run_is_examined(): void
    {
        $out = E::sandwichedOpenings([100, 100, 100, 100, 100], ['1900-01-01', '1901-01-01', '1900-01-01', null, '1900-01-01'], 500, 20);
        $this->assertSame([3 => ['date' => '1900-01-01', 'from' => 2]], $out, '1901 n\'est pas tardive ; la suite non datée suivante l\'est');

        $two = E::sandwichedOpenings([100, 100, 100, 100, 100], ['1857-01-01', null, '1857-01-01', null, '1857-01-01'], 500, 20);
        $this->assertSame([1 => ['date' => '1857-01-01', 'from' => 0], 3 => ['date' => '1857-01-01', 'from' => 2]], $two);
    }

    public function test_holes(): void
    {
        $this->assertTrue(E::isHole('-;-', 'closed/x'), 'signatures de longueurs différentes');
        $this->assertTrue(E::isHole('in_service/a;-', 'in_service/b;closed'), 'égale puis moindre');
        $this->assertFalse(E::isHole('in_service/a', 'in_service/b'), 'jamais moindre');
        $this->assertTrue(E::isHole('closed;-', 'in_service/a;closed'));
        $this->assertFalse(E::isHole('in_service/a;-', 'closed;closed'), 'plus présente à une ère');
    }

    public function test_nearest_dated_point(): void
    {
        $grid = [];
        foreach ([[0, 0, ['date' => '1900']], [10, 0, ['date' => '1880']], [-10, 0, ['date' => '1870']], [300, 300, ['date' => '1800']]] as $p) {
            $grid[(int) floor($p[0] / 100)][(int) floor($p[1] / 100)][] = $p;
        }

        $this->assertSame(['date' => '1900'], E::nearest($grid, 0, 0, 100, 50));
        $this->assertSame(['date' => '1880'], E::nearest($grid, 5, 0, 100, 50), 'égalité : la plus ancienne des deux plus proches');
        $this->assertSame(['date' => '1900'], E::nearest($grid, 0, 0, 100, 5));
        $this->assertSame(['date' => '1800'], E::nearest($grid, 290, 290, 100, 50));
        $this->assertNull(E::nearest($grid, 0, 0, 100, 0.0));
    }

    public function test_deduced_openings_and_closures(): void
    {
        $this->assertSame('1860-01-01', E::neighbourOpening('1860-01-01', '1857-01-01', null, null));
        $this->assertSame('1857-01-01', E::neighbourOpening('1850-01-01', '1857-01-01', '1859-01-01', null));
        $this->assertSame('1865-01-01', E::neighbourOpening('1870-01-01', '1857-01-01', '1865-01-01', null), 'pas après l\'achèvement');
        $this->assertNull(E::neighbourOpening('1870-01-01', '1857-01-01', null, '1869-12-31'), 'pas après la première fermeture');
        $this->assertSame('1870-01-01', E::neighbourOpening('1870-01-01', '1857-01-01', null, '1870-01-01'), 'fermeture le même jour : admise');

        $this->assertSame('1981-09-27', E::junctionOpening([[1, '1981-09-27', true], [2, '1850-01-01']]));
        $this->assertNull(E::junctionOpening([[1, '1981-09-27', false], [2, '1850-01-01']]), 'sans LGV');
        $this->assertNull(E::junctionOpening([[1, '1981-09-27', true]]), 'une seule extrémité');
        $this->assertNull(E::junctionOpening([[1, '1981-09-27', true], [null, '1850-01-01']]), 'extrémité libre non rattachée');
        $this->assertSame('1985-01-01', E::junctionOpening([[1, '1990-01-01', true], [1, '1981-01-01', true], [2, '1985-01-01']]));

        $this->assertSame(['date' => '1950-01-01'], E::neighbourClosure(['date' => '1950-01-01'], [], null));
        $this->assertSame(['date' => '1960-01-01'], E::neighbourClosure(['date' => '1950-01-01'], [['date' => '1960-01-01']], '1955-01-01'));
        $this->assertSame(['date' => '1960-01-01'], E::neighbourClosure(null, [['date' => '1940-01-01'], ['date' => '1960-01-01']], '1950-01-01'));
        $this->assertNull(E::neighbourClosure(['date' => '1940-01-01'], [], '1950-01-01'));

        $this->assertTrue(E::wholeLineClosureContradicted(true, false));
        $this->assertFalse(E::wholeLineClosureContradicted(true, true));
        $this->assertFalse(E::wholeLineClosureContradicted(false, false));
    }
}
