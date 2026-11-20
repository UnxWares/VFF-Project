<?php

namespace Tests\Unit\Schematic;

use App\Services\Schematic\SchematicGraph;
use App\Services\Schematic\SchematicLayout;
use App\Services\Schematic\SchematicOptions;
use App\Services\Schematic\SchematicPlan;
use PHPUnit\Framework\TestCase;

/**
 * Plan schématique (SchematicLayout) sur de petits graphes écrits à la main, grille de 1 000 m,
 * sans loupe. Positions volontairement hors de la grille, pour vérifier qu'aucune n'est recopiée.
 *
 *                     C (gare SC)
 *                     |  ligne 8, fret
 *   A ── S1 ── S2 ── J ──────────── B
 *   (SA)  ligne 7, mixte            (SB)
 */
class SchematicLayoutTest extends TestCase
{
    private const CELL = 1000.0;

    /** @var array<int, array{0: float, 1: float}> */
    private array $nodes = [];

    /** @var list<array{u: int, v: int, length: float, line: ?int, category: string}> */
    private array $edges = [];

    /** @var array<int, int> */
    private array $stations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->nodes = [
            1 => [130.0, 70.0],        // A
            2 => [5120.0, 160.0],      // S1
            3 => [7480.0, -90.0],      // S2
            4 => [12230.0, 140.0],     // J
            5 => [24310.0, -80.0],     // B
            6 => [12120.0, 11890.0],   // C (au nord)
        ];
        $this->stations = [1 => 101, 2 => 102, 3 => 103, 5 => 105, 6 => 106];
        $this->edge(1, 2, 7, 'mixed');
        $this->edge(2, 3, 7, 'mixed');
        $this->edge(3, 4, 7, 'mixed');
        $this->edge(4, 5, 7, 'mixed');
        $this->edge(4, 6, 8, 'freight');
    }

    private function edge(int $u, int $v, ?int $line, string $category, ?float $length = null): void
    {
        [$ax, $ay] = $this->nodes[$u];
        [$bx, $by] = $this->nodes[$v];
        $this->edges[] = ['u' => $u, 'v' => $v, 'length' => $length ?? hypot($bx - $ax, $by - $ay), 'line' => $line, 'category' => $category];
    }

    private function layout(?SchematicOptions $options = null): SchematicPlan
    {
        $graph = new SchematicGraph($this->nodes, $this->edges, $this->stations, [101 => 3.0, 105 => 5.0, 106 => 2.0]);

        return (new SchematicLayout($options ?? new SchematicOptions(cellM: self::CELL, lenses: [])))->layout($graph);
    }

    /** @return array<int, array{station: int, x: float, y: float, angle: int, kind: string, main: bool, degree: int}> */
    private static function stationsById(SchematicPlan $plan): array
    {
        return array_column($plan->stations, null, 'station');
    }

    private static function onGrid(float $v): bool
    {
        return abs($v / self::CELL - round($v / self::CELL)) < 1e-9;
    }

    public function test_every_segment_is_horizontal_vertical_or_at_45_degrees(): void
    {
        $plan = $this->layout();

        $this->assertNotEmpty($plan->pieces);
        foreach ($plan->pieces as $piece) {
            for ($k = 1; $k < count($piece['coords']); $k++) {
                $dx = abs($piece['coords'][$k][0] - $piece['coords'][$k - 1][0]);
                $dy = abs($piece['coords'][$k][1] - $piece['coords'][$k - 1][1]);
                $this->assertTrue($dx < 1e-6 || $dy < 1e-6 || abs($dx - $dy) < 1e-6, "segment de pente non octilinéaire : {$dx} × {$dy}");
            }
        }
    }

    public function test_nodes_sit_on_the_grid_and_no_input_position_is_copied(): void
    {
        $plan = $this->layout();

        foreach ($plan->stations as $s) {
            if ($s['kind'] !== 'stop') {
                $this->assertTrue(self::onGrid($s['x']) && self::onGrid($s['y']), "nœud hors grille : gare {$s['station']}");
            }
        }
        $inputs = array_map(fn (array $p): string => sprintf('%.3f,%.3f', $p[0], $p[1]), $this->nodes);
        foreach ($plan->pieces as $piece) {
            foreach ($piece['coords'] as $k => [$x, $y]) {
                $this->assertNotContains(sprintf('%.3f,%.3f', $x, $y), $inputs);
                // Les sommets intérieurs d'un morceau sont des coudes, donc des points de la grille.
                if ($k > 0 && $k < count($piece['coords']) - 1) {
                    $this->assertTrue(self::onGrid($x) && self::onGrid($y));
                }
            }
        }
        foreach ($plan->stations as $s) {
            $this->assertNotContains(sprintf('%.3f,%.3f', $s['x'], $s['y']), $inputs);
        }
    }

    public function test_the_junction_joins_three_axes_at_one_point_and_intermediate_stations_are_ticks(): void
    {
        $plan = $this->layout();
        $stations = self::stationsById($plan);

        $this->assertSame(3, $plan->stats['axes']);
        $this->assertSame(4, $plan->stats['places']);
        $this->assertSame('end', $stations[101]['kind']);
        $this->assertSame('end', $stations[105]['kind']);
        $this->assertSame('stop', $stations[102]['kind']);
        $this->assertSame('stop', $stations[103]['kind']);
        // J (12 230 ; 140) est arrondi au point (12 000 ; 0) : les trois axes y finissent.
        $ends = [];
        foreach ($plan->pieces as $piece) {
            foreach ([$piece['coords'][0], $piece['coords'][count($piece['coords']) - 1]] as [$x, $y]) {
                $ends[sprintf('%.0f,%.0f', $x, $y)] = ($ends[sprintf('%.0f,%.0f', $x, $y)] ?? 0) + 1;
            }
        }
        $this->assertGreaterThanOrEqual(3, $ends['12000,0'] ?? 0);
        // Gares de la ligne ouest-est : traits perpendiculaires à un axe horizontal.
        $this->assertContains($stations[102]['angle'], [0, 180]);
        $this->assertEqualsWithDelta(0.0, $stations[102]['y'], 1e-6);
    }

    public function test_intermediate_stations_keep_their_order_along_the_axis(): void
    {
        $stations = self::stationsById($this->layout());

        $this->assertLessThan($stations[102]['x'], $stations[101]['x']);
        $this->assertLessThan($stations[103]['x'], $stations[102]['x']);
        $this->assertLessThan(12000.0, $stations[103]['x']);
    }

    public function test_each_piece_keeps_its_line_and_category(): void
    {
        $plan = $this->layout();
        $byLine = [];
        foreach ($plan->pieces as $piece) {
            $byLine[$piece['line']][] = $piece['category'];
        }

        $this->assertEqualsCanonicalizing([7, 8], array_keys($byLine));
        $this->assertSame(['mixed'], array_values(array_unique($byLine[7])));
        $this->assertSame(['freight'], $byLine[8]);
    }

    public function test_a_short_spur_without_station_is_removed_and_the_line_goes_straight_through(): void
    {
        // Tiroir de 600 m sans gare branché sur S2 : S2 n'est plus une bifurcation.
        $this->nodes[7] = [7600.0, 560.0];
        $this->edge(3, 7, 9, 'mixed');

        $plan = $this->layout();

        $this->assertNotContains(9, array_column($plan->pieces, 'line'));
        $this->assertSame(3, $plan->stats['axes']);
        $this->assertSame('stop', self::stationsById($plan)[103]['kind']);
    }

    public function test_a_short_spur_with_a_station_is_kept(): void
    {
        $this->nodes[7] = [7600.0, 1500.0];
        $this->stations[7] = 107;
        $this->edge(3, 7, 9, 'mixed');

        $plan = $this->layout();

        $this->assertContains(9, array_column($plan->pieces, 'line'));
        $this->assertSame('end', self::stationsById($plan)[107]['kind']);
    }

    public function test_two_lines_traced_side_by_side_are_drawn_once_with_the_most_active_category(): void
    {
        // Seconde voie J - B (LGV, ligne 10), presque de la même longueur que celle de la ligne 7.
        $this->edge(4, 5, 10, 'lgv', 12300.0);

        $plan = $this->layout();
        // Morceaux à l'est de J (x de 12 000 m et plus), dans un sens ou dans l'autre.
        $eastPieces = array_filter($plan->pieces, function (array $p): bool {
            $xs = array_column($p['coords'], 0);

            return min($xs) >= 12000.0 - 1e-6 && max($xs) > 12000.0;
        });

        $this->assertSame(3, $plan->stats['axes']);
        $this->assertSame(['lgv'], array_values(array_unique(array_column($eastPieces, 'category'))));
        $this->assertSame('end', self::stationsById($plan)[105]['kind']);
    }

    public function test_a_track_that_stops_short_of_a_node_is_joined_to_it(): void
    {
        // Ligne isolée dont le bout de voie s'arrête à 330 m de B (moins de 0,45 pas) : lacune.
        $this->nodes[20] = [24310.0, 250.0];
        $this->nodes[21] = [24310.0, 8250.0];
        $this->stations[21] = 121;
        $this->edge(20, 21, 30, 'mixed');

        $plan = $this->layout();
        $stations = self::stationsById($plan);

        $this->assertSame(1, $plan->stats['bridged']);
        $this->assertSame(0, $plan->stats['isolated']);
        // B devient une gare de passage, la ligne 30 finit en terminus à sa gare 121.
        $this->assertSame('stop', $stations[105]['kind']);
        $this->assertSame('end', $stations[121]['kind']);
        $this->assertContains(30, array_column($plan->pieces, 'line'));
        $this->assertSame(3, $plan->stats['axes']);
    }

    public function test_a_short_isolated_piece_without_station_is_dropped(): void
    {
        // 4 km de voie sans gare, loin de tout : plus long qu'une antenne retirée (3 km), moins que 5 km.
        $this->nodes[40] = [60000.0, 40000.0];
        $this->nodes[41] = [64000.0, 40000.0];
        $this->edge(40, 41, 31, 'freight');

        $plan = $this->layout();

        $this->assertNotContains(31, array_column($plan->pieces, 'line'));
        $this->assertSame(1, $plan->stats['dropped']);
        $this->assertSame(0, $plan->stats['isolated']);
    }

    public function test_an_isolated_line_with_a_station_stays_where_it_is(): void
    {
        // Train touristique sans voie en service vers le réseau, à 30 km : gardé, sans raccord.
        $this->nodes[50] = [40000.0, 30000.0];
        $this->nodes[51] = [46000.0, 30000.0];
        $this->stations[51] = 151;
        $this->edge(50, 51, 32, 'heritage');

        $plan = $this->layout();
        $stations = self::stationsById($plan);

        $this->assertContains(32, array_column($plan->pieces, 'line'));
        $this->assertSame(1, $plan->stats['isolated']);
        $this->assertSame(0, $plan->stats['bridged']);
        $this->assertSame('end', $stations[151]['kind']);
        $this->assertEqualsWithDelta(46000.0, $stations[151]['x'], 1000.0);
    }

    public function test_two_lines_that_cross_on_the_ground_cross_on_the_plan(): void
    {
        // Ligne nord-sud qui passe au-dessus de J - B sans jonction (pont).
        $this->nodes[60] = [18100.0, -6100.0];
        $this->nodes[61] = [18100.0, 6100.0];
        $this->stations += [60 => 160, 61 => 161];
        $this->edge(60, 61, 33, 'mixed');

        $plan = $this->layout();
        $vertical = array_values(array_filter($plan->pieces, fn (array $p): bool => $p['line'] === 33));

        $this->assertSame(1, $plan->stats['crossings']);
        $this->assertSame(1, $plan->stats['real_crossings']);
        $this->assertCount(1, $vertical);
        $this->assertCount(2, $vertical[0]['coords'], 'tracé droit, sans détour');
    }

    public function test_the_base_plan_has_no_crossing_and_no_isolated_piece(): void
    {
        $plan = $this->layout();

        foreach (['crossings', 'node_passes', 'isolated', 'bridged', 'dropped', 'overlaps'] as $stat) {
            $this->assertSame(0, $plan->stats[$stat], $stat);
        }
    }

    public function test_the_layout_is_deterministic(): void
    {
        $this->assertEquals($this->layout()->pieces, $this->layout()->pieces);
        $this->assertEquals($this->layout()->stations, $this->layout()->stations);
    }

    public function test_a_lens_enlarges_the_area_around_its_centre(): void
    {
        $plain = self::stationsById($this->layout());
        // Loupe centrée sur S1 (à peu près), rayon de 4 km, grossissement 3.
        [$lng, $lat] = [rad2deg(5120.0 / 6378137.0), rad2deg(2 * atan(exp(160.0 / 6378137.0)) - M_PI / 2)];
        $lensed = self::stationsById($this->layout(new SchematicOptions(cellM: self::CELL, lenses: [[$lng, $lat, 4000.0, 3.0]])));

        $span = fn (array $s): float => abs($s[103]['x'] - $s[101]['x']);
        $this->assertGreaterThan($span($plain), $span($lensed));
    }

    public function test_an_empty_graph_gives_an_empty_plan(): void
    {
        $plan = (new SchematicLayout(new SchematicOptions(cellM: self::CELL, lenses: [])))->layout(new SchematicGraph([], []));

        $this->assertSame([], $plan->pieces);
        $this->assertSame([], $plan->stations);
        $this->assertSame(0, $plan->stats['axes']);
    }
}
