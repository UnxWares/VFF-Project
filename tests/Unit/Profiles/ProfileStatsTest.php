<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\LineProfiles;
use App\Services\Profiles\ProfileStats;
use PHPUnit\Framework\TestCase;

/**
 * Calculs du profil en long : ouvrages interpolés, aberrations effacées, dénivelés avec seuil,
 * déclivité la plus forte par moindres carrés sur 1 km, allègement des points.
 */
class ProfileStatsTest extends TestCase
{
    /**
     * Points tous les 100 m d'altitude donnée par une fonction de la distance.
     *
     * @return list<array{0: float, 1: float|null}>
     */
    private static function series(int $lengthM, callable $z, int $step = 100): array
    {
        $out = [];
        for ($d = 0; $d <= $lengthM; $d += $step) {
            $out[] = [(float) $d, $z($d)];
        }

        return $out;
    }

    public function test_interpolates_a_tunnel_between_its_two_portals(): void
    {
        $points = [[0.0, 100.0], [100.0, 900.0], [200.0, 950.0], [300.0, 130.0]];
        $filled = ProfileStats::fillStructures($points, [false, true, true, false]);

        $this->assertEqualsWithDelta(110.0, $filled[1][1], 1e-9);
        $this->assertEqualsWithDelta(120.0, $filled[2][1], 1e-9);
    }

    public function test_leaves_a_long_open_ended_tunnel_unknown_but_fills_a_short_hole(): void
    {
        // Tunnel qui passe la frontière : aucune tête connue de l'autre côté, 2 km sans altitude.
        $points = self::series(3000, fn (int $d): ?float => $d <= 500 ? 1000.0 + $d / 100 : null);
        $flags = array_map(fn (array $p): bool => $p[0] > 500, $points);
        $filled = ProfileStats::fillStructures($points, $flags);
        $this->assertNull($filled[20][1]);

        $hole = [[0.0, 10.0], [100.0, null], [200.0, 30.0]];
        $this->assertEqualsWithDelta(20.0, ProfileStats::fillStructures($hole, [false, false, false])[1][1], 1e-9);
    }

    public function test_removes_a_spike_and_an_isolated_peak_but_keeps_a_steady_climb(): void
    {
        $climb = self::series(2000, fn (int $d): float => 500.0 + 0.09 * $d);
        $this->assertSame($climb, ProfileStats::removeSpikes($climb));

        $spiky = $climb;
        $spiky[10][1] -= 77.0;
        $this->assertNull(ProfileStats::removeSpikes($spiky)[10][1]);

        // Point à l'air libre entre deux tunnels sans altitude, 200 m au-dessus de ses voisins.
        $peak = [[0.0, 300.0], [100.0, null], [200.0, null], [300.0, 500.0], [400.0, null], [500.0, null], [600.0, 290.0]];
        $this->assertNull(ProfileStats::removeSpikes($peak)[3][1]);
    }

    public function test_counts_climbs_above_the_three_metre_threshold(): void
    {
        // Bruit de ±1 m sur du plat, puis montée de 50 m et descente de 20 m.
        $points = [[0.0, 100.0], [100.0, 101.0], [200.0, 99.0], [300.0, 100.0], [400.0, 150.0], [500.0, 130.0]];
        $stats = ProfileStats::summarize($points);

        $this->assertNotNull($stats);
        $this->assertSame(50.0, $stats['ascent_m']);
        $this->assertSame(20.0, $stats['descent_m']);
        $this->assertSame(99.0, $stats['min_altitude_m']);
        $this->assertSame(150.0, $stats['max_altitude_m']);
    }

    public function test_finds_the_steepest_kilometre_by_least_squares_and_its_direction(): void
    {
        // Plat, puis 2 km de pente à 25 ‰ en descendant, avec un bruit de ±4 m alterné.
        $points = self::series(5000, function (int $d): float {
            $z = $d < 2000 ? 600.0 : 600.0 - 0.025 * min($d - 2000, 2000);

            return $z + (($d / 100) % 2 === 0 ? 4.0 : -4.0);
        });
        $stats = ProfileStats::summarize($points);

        $this->assertNotNull($stats);
        $this->assertSame('down', $stats['max_grade_direction']);
        // L'écart entre les bouts d'une fenêtre donnerait 25 ± 8 ‰ ; la droite des moindres carrés reste proche de 25.
        $this->assertEqualsWithDelta(25.0, $stats['max_grade_permil'], 2.5);
        $this->assertGreaterThanOrEqual(1900, $stats['max_grade_from_m']);
        $this->assertLessThanOrEqual(4100, $stats['max_grade_to_m']);
    }

    public function test_ignores_windows_across_gaps_or_mostly_interpolated(): void
    {
        // Saut de 100 m à travers une lacune : pas une déclivité.
        $points = [...self::series(1000, fn (): float => 100.0), ...array_map(
            fn (array $p): array => [$p[0] + 1500, 200.0],
            self::series(1000, fn (): float => 200.0),
        )];
        $stats = ProfileStats::summarize($points, [[1000.0, 1500.0]]);
        $this->assertNotNull($stats);
        $this->assertSame(0.0, $stats['max_grade_permil']);

        // Tunnel interpolé à 80 ‰ sur toute la fenêtre : écarté de la déclivité maximale.
        $tunnel = self::series(1000, fn (int $d): float => 300.0 + 0.08 * $d);
        $all = array_fill(0, count($tunnel), true);
        $this->assertSame(0.0, ProfileStats::summarize($tunnel, [], $all)['max_grade_permil'] ?? null);
    }

    public function test_slope_needs_three_known_points(): void
    {
        $this->assertNull(ProfileStats::slope([[0.0, 1.0], [100.0, 2.0]]));
        $this->assertNull(ProfileStats::slope([[0.0, 1.0], [100.0, null], [200.0, 3.0]]));
        $this->assertEqualsWithDelta(10.0, ProfileStats::slope([[0.0, 0.0], [100.0, 1.0], [200.0, 2.0]]), 1e-9);
    }

    public function test_altitude_at_interpolates_between_known_points(): void
    {
        $points = [[0.0, 100.0], [100.0, 200.0], [200.0, null]];

        $this->assertEqualsWithDelta(150.0, ProfileStats::altitudeAt($points, 50), 1e-9);
        $this->assertNull(ProfileStats::altitudeAt($points, 150));
        $this->assertNull(ProfileStats::altitudeAt($points, 500));
    }

    public function test_downsample_keeps_ends_extremes_and_hole_edges(): void
    {
        $points = [];
        for ($i = 0; $i <= 1000; $i++) {
            $points[] = [$i * 100, $i === 437 ? 999.0 : ($i === 612 ? -5.0 : (in_array($i, [700, 701], true) ? null : 100.0))];
        }
        $small = LineProfiles::downsample($points, 50);

        $this->assertLessThan(70, count($small));
        $this->assertSame($points[0], $small[0]);
        $this->assertSame($points[1000], $small[count($small) - 1]);
        $this->assertContains([43700, 999.0], $small);
        $this->assertContains([61200, -5.0], $small);
        $this->assertContains([70000, null], $small);
        $this->assertContains([69900, 100.0], $small);
        $this->assertSame($points, LineProfiles::downsample($points, 5000));
    }
}
