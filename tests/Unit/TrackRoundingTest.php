<?php

namespace Tests\Unit;

use App\Support\TrackRounding;
use PHPUnit\Framework\TestCase;

/**
 * Arrondi des sommets d'un tracé (TrackRounding) : arcs tangents, précision gardée, angles et sommets fixes intacts.
 */
class TrackRoundingTest extends TestCase
{
    /** Plus forte cassure (°) entre deux segments consécutifs. */
    private function maxTurn(array $c): float
    {
        $max = 0.0;
        for ($i = 1; $i < count($c) - 1; $i++) {
            $a1 = atan2($c[$i][1] - $c[$i - 1][1], $c[$i][0] - $c[$i - 1][0]);
            $a2 = atan2($c[$i + 1][1] - $c[$i][1], $c[$i + 1][0] - $c[$i][0]);
            $max = max($max, rad2deg(abs(atan2(sin($a2 - $a1), cos($a2 - $a1)))));
        }

        return $max;
    }

    private function distToPoint(array $c, array $p): float
    {
        $best = INF;
        for ($i = 1; $i < count($c); $i++) {
            [$ax, $ay] = $c[$i - 1];
            [$bx, $by] = $c[$i];
            $l2 = ($bx - $ax) ** 2 + ($by - $ay) ** 2;
            $t = $l2 > 0 ? max(0.0, min(1.0, (($p[0] - $ax) * ($bx - $ax) + ($p[1] - $ay) * ($by - $ay)) / $l2)) : 0.0;
            $best = min($best, hypot($ax + $t * ($bx - $ax) - $p[0], $ay + $t * ($by - $ay) - $p[1]));
        }

        return $best;
    }

    /** Courbe échantillonnée tous les 30 m (cassures de 10° aux sommets), comme un tracé OSM de montagne. */
    private function polygonalCurve(): array
    {
        $c = [[0.0, 0.0]];
        $az = 0.0;
        for ($i = 0; $i < 8; $i++) {
            $c[] = [end($c)[0] + 30 * cos($az), end($c)[1] + 30 * sin($az)];
            $az += deg2rad(10);
        }

        return $c;
    }

    public function test_straight_line_is_unchanged(): void
    {
        $c = [[0.0, 0.0], [100.0, 0.0], [250.0, 0.0], [400.0, 0.0]];

        $this->assertSame($c, TrackRounding::round($c));
    }

    public function test_curve_vertices_become_tangent_arcs_without_visible_angle(): void
    {
        $c = $this->polygonalCurve();
        $this->assertEqualsWithDelta(10, $this->maxTurn($c), 0.01);

        $r = TrackRounding::round($c);

        $this->assertSame($c[0], $r[0]);
        $this->assertSame(end($c), end($r));
        $this->assertGreaterThan(count($c), count($r));
        $this->assertLessThanOrEqual(TrackRounding::STEP_DEG + 0.01, $this->maxTurn($r));
        // Précision : chaque sommet d'origine est à moins de MAX_DEV_M de l'arc qui le remplace.
        foreach (array_slice($c, 1, -1) as $p) {
            $this->assertLessThanOrEqual(TrackRounding::MAX_DEV_M + 0.01, $this->distToPoint($r, $p));
        }
        // Et chaque point de l'arc reste près des segments d'origine.
        foreach ($r as $p) {
            $this->assertLessThanOrEqual(TrackRounding::MAX_DEV_M + 0.01, $this->distToPoint($c, $p));
        }
    }

    public function test_arc_starts_and_ends_on_the_segments_tangentially(): void
    {
        $c = [[0.0, 0.0], [100.0, 0.0], [100.0 + 100 * cos(deg2rad(20)), 100 * sin(deg2rad(20))]];

        $r = TrackRounding::round($c);

        // Premier point de l'arc sur le premier segment (y = 0), dernier sur le second ; virage de 20° en 7 pas de 3° au plus.
        $this->assertCount(2 + 8, $r);
        $this->assertEqualsWithDelta(0.0, $r[1][1], 1e-9);
        $this->assertLessThan(100.0, $r[1][0]);
        $this->assertEqualsWithDelta(0.0, $this->distToPoint([$c[1], $c[2]], $r[8]), 1e-6);
        // Flèche au sommet plafonnée : tangente T = 2 / tan(5°) ≈ 22,9 m (moins que les 50 m de mi-segment).
        $this->assertEqualsWithDelta(100 - 2 / tan(deg2rad(5)), $r[1][0], 1e-6);
        $this->assertEqualsWithDelta(TrackRounding::MAX_DEV_M, $this->distToPoint($r, $c[1]), 0.05);
    }

    public function test_right_and_left_turns_bend_the_same_way_as_the_track(): void
    {
        foreach ([1, -1] as $side) {
            $c = [[0.0, 0.0], [100.0, 0.0], [200.0, $side * 30.0]];

            $r = TrackRounding::round($c);

            foreach ($r as $p) {
                $this->assertGreaterThanOrEqual(-1e-9, $side * $p[1]);
            }
        }
    }

    public function test_sharp_corner_and_tiny_turn_are_left_alone(): void
    {
        $sharp = [[0.0, 0.0], [100.0, 0.0], [100.0, 100.0]];
        $tiny = [[0.0, 0.0], [100.0, 0.0], [200.0, 100 * tan(deg2rad(5))]];

        $this->assertSame($sharp, TrackRounding::round($sharp));
        $this->assertSame($tiny, TrackRounding::round($tiny));
        $this->assertSame([], TrackRounding::candidates($sharp));
    }

    public function test_fixed_vertex_where_another_line_joins_does_not_move(): void
    {
        $c = $this->polygonalCurve();

        $r = TrackRounding::round($c, [3 => true]);

        $this->assertContains($c[3], $r);
        $this->assertNotContains($c[4], $r);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], TrackRounding::candidates($c));
    }

    public function test_short_segments_limit_the_arc_to_their_middle(): void
    {
        // Segments de 4 m : la tangente est bornée à 2 m, les arcs voisins se touchent sans se croiser.
        $c = [[0.0, 0.0], [4.0, 0.0], [4 + 4 * cos(deg2rad(20)), 4 * sin(deg2rad(20))], [4 + 4 * cos(deg2rad(20)) + 4 * cos(deg2rad(40)), 4 * sin(deg2rad(20)) + 4 * sin(deg2rad(40))]];

        $r = TrackRounding::round($c);

        $xs = array_column($r, 0);
        $sorted = $xs;
        sort($sorted);
        $this->assertSame($sorted, $xs);
        $this->assertLessThanOrEqual(TrackRounding::STEP_DEG + 0.01, $this->maxTurn($r));
    }
}
