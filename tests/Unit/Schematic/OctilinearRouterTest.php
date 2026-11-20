<?php

namespace Tests\Unit\Schematic;

use App\Services\Schematic\OctilinearRouter;
use App\Services\Schematic\SchematicOptions;
use PHPUnit\Framework\TestCase;

/**
 * Routage des axes sur la grille octilinéaire (OctilinearRouter) : chemins droits quand le tracé
 * l'est, pas de superposition ni de passage par un autre nœud quand un autre chemin existe, tracé de
 * secours à un coude quand la recherche est trop longue.
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
