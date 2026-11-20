<?php

namespace Tests\Unit\Schematic;

use App\Services\Schematic\OctilinearRouter;
use App\Services\Schematic\SchematicOptions;
use PHPUnit\Framework\TestCase;

/**
 * Routage des axes sur la grille octilinéaire (OctilinearRouter) : chemins droits quand le tracé
 * l'est, pas de superposition ni de passage par un autre nœud quand un autre chemin existe, tracé de
 * secours à un coude quand la recherche est trop longue, croisements gardés seulement quand ils
 * existent sur le terrain, reprise d'un axe déjà routé.
 */
class OctilinearRouterTest extends TestCase
{
    private function router(?SchematicOptions $options = null): OctilinearRouter
    {
        return new OctilinearRouter($options ?? new SchematicOptions(lenses: []));
    }

    /**
     * @param  list<array{0: int, 1: int}>  $corners
     * @return list<string> pas unitaires du chemin (non orientés)
     */
    private static function steps(array $corners): array
    {
        $steps = [];
        for ($k = 1; $k < count($corners); $k++) {
            [$i, $j] = $corners[$k - 1];
            [$ti, $tj] = $corners[$k];
            $di = $ti <=> $i;
            $dj = $tj <=> $j;
            while ([$i, $j] !== [$ti, $tj]) {
                $next = [$i + $di, $j + $dj];
                $pair = [[$i, $j], $next];
                sort($pair);
                $steps[] = json_encode($pair);
                [$i, $j] = $next;
            }
        }

        return $steps;
    }

    public function test_a_straight_track_gives_a_straight_axis(): void
    {
        $path = $this->router()->route([0, 0], [6, 0], [[0.0, 0.0], [6.0, 0.0]], 0.0, 0.0);

        $this->assertSame([[0, 0], [6, 0]], $path);
    }

    public function test_a_diagonal_track_gives_a_45_degree_axis(): void
    {
        $path = $this->router()->route([0, 0], [5, 5], [[0.0, 0.0], [5.0, 5.0]], 45.0, 45.0);

        $this->assertSame([[0, 0], [5, 5]], $path);
    }

    public function test_a_second_axis_between_the_same_points_does_not_overlap_the_first(): void
    {
        $router = $this->router();
        $first = $router->route([0, 0], [8, 0], [[0.0, 0.0], [8.0, 0.0]], 0.0, 0.0);
        $second = $router->route([0, 0], [8, 0], [[0.0, 0.0], [8.0, 0.0]], 0.0, 0.0);

        $this->assertSame([], array_values(array_intersect(self::steps($first), self::steps($second))));
        $this->assertSame(0, $router->overlaps());
    }

    public function test_an_axis_goes_around_another_node(): void
    {
        $router = $this->router();
        $router->occupy(4, 0, 99);

        $path = $router->route([0, 0], [8, 0], [[0.0, 0.0], [8.0, 0.0]], 0.0, 0.0);

        $this->assertNotContains(json_encode([[3, 0], [4, 0]]), self::steps($path));
        $this->assertNotContains(json_encode([[4, 0], [5, 0]]), self::steps($path));
        $this->assertSame(99, $router->placeAt(4, 0));
    }

    public function test_a_search_that_is_too_long_falls_back_to_a_single_elbow(): void
    {
        $router = $this->router(new SchematicOptions(maxExpansions: 1, lenses: []));

        $path = $router->route([0, 0], [6, 2], [[0.0, 0.0], [6.0, 2.0]], 18.0, 18.0);

        $this->assertSame(1, $router->fallbacks);
        $this->assertCount(3, $path);
        $this->assertSame([0, 0], $path[0]);
        $this->assertSame([6, 2], $path[2]);
    }

    /**
     * Un axe horizontal (0, 0) - (4, 0), extrémités occupées par des nœuds, puis un axe vertical qui
     * doit le traverser ou le contourner.
     *
     * @return array{0: OctilinearRouter, 1: list<array{0: int, 1: int}>}
     */
    private function crossing(bool $real): array
    {
        $router = $this->router();
        $router->occupy(0, 0, 100);
        $router->occupy(4, 0, 101);
        $router->realCrossings($real ? [0 => [1 => true], 1 => [0 => true]] : []);
        $router->route([0, 0], [4, 0], [[0.0, 0.0], [4.0, 0.0]], 0.0, 0.0, 0);

        return [$router, $router->route([2, -3], [2, 3], [[2.0, -3.0], [2.0, 3.0]], 90.0, 90.0, 1)];
    }

    public function test_a_crossing_that_exists_on_the_ground_is_drawn_straight(): void
    {
        [$router, $path] = $this->crossing(true);

        $this->assertSame([[2, -3], [2, 3]], $path);
        $this->assertSame(1, $router->crossingCount());
        $this->assertSame(1, $router->crossingCount(true));
        $this->assertSame(0, $router->crossingCount(false));
    }

    public function test_a_crossing_that_only_the_plan_would_have_is_avoided(): void
    {
        [$router, $path] = $this->crossing(false);

        $this->assertNotContains([2, 0], OctilinearRouter::cells($path));
        $this->assertSame(0, $router->crossingCount());
        $this->assertSame(0, $router->conflicts($path));
        $this->assertSame(0, $router->nodePasses());
    }

    public function test_rerouting_an_axis_frees_its_old_path_and_avoids_an_axis_routed_after_it(): void
    {
        $router = $this->router();
        foreach ([[0, 0], [6, 0], [3, -2], [3, 2]] as $k => [$i, $j]) {
            $router->occupy($i, $j, 100 + $k);
        }
        $guide = [[0.0, 0.0], [6.0, 0.0]];
        $first = $router->route([0, 0], [6, 0], $guide, 0.0, 0.0, 0);
        // Le second axe croise le premier : croisement réel, tracé droit.
        $router->realCrossings([0 => [1 => true], 1 => [0 => true]]);
        $router->route([3, -2], [3, 2], [[3.0, -2.0], [3.0, 2.0]], 90.0, 90.0, 1);
        $this->assertSame([[0, 0], [6, 0]], $first);
        $this->assertSame(1, $router->conflicts($first));

        // Le croisement n'existe plus sur le terrain : repris, le premier axe contourne le second.
        $router->realCrossings([]);
        $second = $router->reroute($first, $guide, 0.0, 0.0, 0);

        $this->assertNotSame($first, $second);
        $this->assertSame([0, 0], $second[0]);
        $this->assertSame([6, 0], $second[count($second) - 1]);
        $this->assertSame(0, $router->conflicts($second));
        $this->assertSame(0, $router->crossingCount());
        $this->assertSame(0, $router->overlaps());
    }

    public function test_rerouting_keeps_the_old_path_when_the_search_gives_up(): void
    {
        $router = $this->router(new SchematicOptions(maxExpansions: 1, lenses: []));
        $router->occupy(3, 0, 99);
        $path = $router->route([0, 0], [6, 0], [[0.0, 0.0], [6.0, 0.0]], 0.0, 0.0, 0);

        // Tracé de secours droit, à travers le nœud : un défaut, que la reprise ne peut pas corriger.
        $this->assertSame([[0, 0], [6, 0]], $path);
        $this->assertSame(1, $router->conflicts($path));
        $this->assertSame(1, $router->nodePasses());
        $this->assertSame($path, $router->reroute($path, [[0.0, 0.0], [6.0, 0.0]], 0.0, 0.0, 0));
        $this->assertSame(1, $router->nodePasses());
        $this->assertSame(0, $router->overlaps());
    }

    public function test_cells_expand_the_corners_of_a_path(): void
    {
        $this->assertSame([[0, 0], [1, 0], [2, 0], [3, 1]], OctilinearRouter::cells([[0, 0], [2, 0], [3, 1]]));
        $this->assertSame([], OctilinearRouter::cells([]));
    }

    public function test_corners_keep_only_the_direction_changes(): void
    {
        $this->assertSame(
            [[0, 0], [2, 0], [4, 2]],
            OctilinearRouter::corners([[0, 0], [1, 0], [2, 0], [3, 1], [4, 2]]),
        );
    }

    public function test_turns_and_distance_helpers(): void
    {
        $this->assertSame(0.0, OctilinearRouter::turns(0.0, 360.0));
        $this->assertSame(1.0, OctilinearRouter::turns(45.0, 90.0));
        $this->assertSame(4.0, OctilinearRouter::turns(0.0, 180.0));
        $this->assertEqualsWithDelta(1.0, OctilinearRouter::distanceToPolyline(1.0, 1.0, [[0.0, 0.0], [4.0, 0.0]]), 1e-9);
        $this->assertEqualsWithDelta(5.0, OctilinearRouter::distanceToPolyline(3.0, 4.0, [[0.0, 0.0]]), 1e-9);
    }
}
