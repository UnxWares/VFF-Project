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

    public function test_splits_the_series_into_pieces_at_the_path_gaps(): void
    {
        $points = [[0.0, 1.0], [100.0, 2.0], [2100.0, 3.0], [2200.0, 4.0]];

        $this->assertSame([[0, 1], [2, 3]], ProfileStats::pieces($points, [[100.0, 2100.0]]));
        $this->assertSame([[0, 3]], ProfileStats::pieces($points));
        $this->assertSame([], ProfileStats::pieces([]));
    }

    public function test_spikes_peaks_and_holes_are_judged_within_their_own_piece(): void
    {
        // Plat à 100 m, lacune, court morceau de 200 m à 300 m d'altitude (autre niveau du tracé),
        // lacune, plat à 100 m : rien de faux. Sans les lacunes, la médiane des voisins d'en bas
        // effacerait les deux points du morceau du milieu.
        $low = self::series(1000, fn (): float => 100.0);
        $mid = [[1500.0, 300.0], [1600.0, 300.0]];
        $after = array_map(fn (array $p): array => [$p[0] + 2100.0, 100.0], $low);
        $points = [...$low, ...$mid, ...$after];
        $gaps = [[1000.0, 1500.0], [1600.0, 2100.0]];
        $this->assertNull(ProfileStats::removeSpikes($points)[11][1]);
        $this->assertSame($points, ProfileStats::removeSpikes($points, $gaps));

        // Point isolé juste après la lacune, encadré par un trou : comparé au morceau d'avant, il
        // ferait un pic de plus de 120 ‰ ; dans son morceau, il n'a pas de voisin et reste.
        $edge = [[0.0, 100.0], [100.0, 100.0], [600.0, 300.0], [700.0, null], [800.0, 100.0]];
        $this->assertNull(ProfileStats::removeSpikes($edge)[2][1]);
        $this->assertSame(300.0, ProfileStats::removeSpikes($edge, [[100.0, 600.0]])[2][1]);

        // Tunnel qui touche la lacune : aucune altitude inventée par-dessus.
        $tunnel = [[0.0, 100.0], [100.0, null], [2100.0, null], [2200.0, 300.0]];
        $filled = ProfileStats::fillStructures($tunnel, [false, true, true, false], gaps: [[100.0, 2100.0]]);
        $this->assertEqualsWithDelta(100.0, $filled[1][1], 1e-9);
        $this->assertEqualsWithDelta(300.0, $filled[2][1], 1e-9);
        // Sans la lacune : une rampe de 200 m inventée sur 2,2 km.
        $this->assertEqualsWithDelta(290.9, ProfileStats::fillStructures($tunnel, [false, true, true, false])[2][1], 0.1);
    }

    public function test_downsample_never_exceeds_the_budget_and_keeps_gap_edges_first(): void
    {
        // 2 501 points, quarante trous d'un point (80 bords) et une lacune : bien plus de points
        // obligés que de place pour une courbe régulière.
        $points = [];
        for ($i = 0; $i <= 2500; $i++) {
            $points[] = [$i * 100, $i % 60 === 30 ? null : 100.0 + $i % 7];
        }
        $gaps = [['from_m' => 120_000, 'to_m' => 120_100]];
        foreach ([10, 50, 600] as $max) {
            $small = LineProfiles::downsample($points, $max, $gaps);
            $this->assertLessThanOrEqual($max, count($small), "max {$max}");
            $this->assertSame($points[0], $small[0]);
            $this->assertSame($points[2500], $small[count($small) - 1]);
            $this->assertContains($points[1200], $small, "bord de lacune, max {$max}");
            $this->assertContains($points[1201], $small, "bord de lacune, max {$max}");
            $distances = array_column($small, 0);
            $this->assertSame($distances, array_values(array_unique($distances)));
            $sorted = $distances;
            sort($sorted);
            $this->assertSame($sorted, $distances);
        }
        $this->assertGreaterThan(550, count(LineProfiles::downsample($points, 600, $gaps)));
        $this->assertSame([], LineProfiles::downsample($points, 0));
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

        // Tunnel interpolé à 80 ‰ sur toute la fenêtre : déclivité non publiée, motif `interpolated`.
        $tunnel = self::series(1000, fn (int $d): float => 300.0 + 0.08 * $d);
        $all = array_fill(0, count($tunnel), ['track' => true, 'tunnel' => false, 'interpolated' => true]);
        $stats = ProfileStats::summarize($tunnel, [], $all);
        $this->assertNotNull($stats);
        $this->assertNull($stats['max_grade_permil']);
        $this->assertSame('interpolated', $stats['max_grade_withheld'] ?? null);
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

        $this->assertLessThanOrEqual(50, count($small));
        $this->assertSame($points[0], $small[0]);
        $this->assertSame($points[1000], $small[count($small) - 1]);
        $this->assertContains([43700, 999.0], $small);
        $this->assertContains([61200, -5.0], $small);
        $this->assertContains([70000, null], $small);
        $this->assertContains([69900, 100.0], $small);
        $this->assertSame($points, LineProfiles::downsample($points, 5000));
    }

    public function test_steepest_grade_never_spans_a_gap_or_the_jump_to_a_branch(): void
    {
        // Ligne plate à 100 m, puis, après un trou (saut vers un embranchement), un morceau plat à
        // 300 m : à travers le trou, la pente serait de 200 m sur 500 m (400 ‰).
        $points = [];
        for ($d = 0; $d <= 2000; $d += 100) {
            $points[] = [(float) $d, 100.0];
        }
        for ($d = 2500; $d <= 4500; $d += 100) {
            $points[] = [(float) $d, 300.0];
        }

        $stats = ProfileStats::summarize($points, [[2000.0, 2500.0]]);

        $this->assertNotNull($stats);
        $this->assertSame(0.0, $stats['max_grade_permil']);
    }

    public function test_steepest_grade_skips_windows_mostly_in_a_tunnel(): void
    {
        // 10 ‰ au sol, puis 50 ‰ sur le premier kilomètre d'un tunnel (altitude douteuse près de la tête).
        $points = [];
        $uncertain = [];
        for ($d = 0; $d <= 4000; $d += 250) {
            $z = $d <= 2000 ? 100 + $d * 0.01 : 120 - ($d - 2000) * 0.05;
            $points[] = [(float) $d, $z];
            $uncertain[] = ['track' => true, 'tunnel' => $d > 2000, 'interpolated' => false];
        }

        $stats = ProfileStats::summarize($points, [], $uncertain);

        $this->assertNotNull($stats);
        $this->assertLessThan(15.0, $stats['max_grade_permil']);
    }

    public function test_steepest_grade_ignores_an_implausible_window(): void
    {
        // 20 ‰ régulier, puis une marche de 200 m sur 1 km (200 ‰, artefact).
        $points = [];
        for ($d = 0; $d <= 6000; $d += 100) {
            $z = 100 + $d * 0.02 + ($d >= 4000 && $d <= 5000 ? ($d - 4000) * 0.2 : ($d > 5000 ? 200 : 0));
            $points[] = [(float) $d, $z];
        }

        $stats = ProfileStats::summarize($points);

        $this->assertNotNull($stats);
        $this->assertLessThanOrEqual(120.0, $stats['max_grade_permil']);
    }

    public function test_grade_is_withheld_when_only_the_terrain_is_known(): void
    {
        // Ligne déposée : altitudes du terrain seulement, 60 ‰ apparents.
        $points = self::series(3000, fn (int $d): float => 200.0 + 0.06 * $d);
        $terrain = array_fill(0, count($points), ['track' => false, 'tunnel' => false, 'interpolated' => false]);

        $stats = ProfileStats::summarize($points, [], $terrain);

        $this->assertNotNull($stats);
        $this->assertNull($stats['max_grade_permil']);
        $this->assertNull($stats['max_grade_from_m']);
        $this->assertNull($stats['max_grade_direction']);
        $this->assertSame('terrain_only', $stats['max_grade_withheld']);
        // Les autres chiffres restent publiés.
        $this->assertEqualsWithDelta(380.0, $stats['max_altitude_m'], 1.0);
    }

    public function test_grade_uses_only_windows_measured_on_the_track(): void
    {
        // 30 ‰ mesurés sur la voie, puis 90 ‰ sur le terrain seul : la valeur publiée vient des fenêtres
        // aux trois quarts sur la voie (au plus un quart de terrain au raccord), loin des 90 ‰.
        $points = [];
        $flags = [];
        for ($d = 0; $d <= 4000; $d += 100) {
            $points[] = [(float) $d, $d <= 2000 ? 100 + $d * 0.03 : 160 + ($d - 2000) * 0.09];
            $flags[] = ['track' => $d <= 2000, 'tunnel' => false, 'interpolated' => false];
        }

        $stats = ProfileStats::summarize($points, [], $flags);

        $this->assertGreaterThanOrEqual(29.0, $stats['max_grade_permil']);
        $this->assertLessThan(45.0, $stats['max_grade_permil']);
        $this->assertNull($stats['max_grade_withheld']);
    }

    public function test_a_shortcut_through_a_spiral_tunnel_does_not_set_the_grade(): void
    {
        // 43 ‰ réels, puis 300 m de tunnel hélicoïdal coupé par le tracé : +29 m tous les 100 m.
        $points = [];
        $flags = [];
        $z = 800.0;
        for ($d = 0; $d <= 3000; $d += 100) {
            $inTunnel = $d > 1500 && $d <= 1700;
            $z += $d === 0 ? 0 : ($inTunnel ? 29.0 : 4.3);
            $points[] = [(float) $d, $z];
            $flags[] = ['track' => ! $inTunnel, 'tunnel' => $inTunnel, 'interpolated' => $inTunnel];
        }

        $stats = ProfileStats::summarize($points, [], $flags);

        $this->assertNotNull($stats);
        $this->assertEqualsWithDelta(43.0, $stats['max_grade_permil'], 1.0);
    }

    public function test_a_jump_at_a_tunnel_head_does_not_set_the_grade(): void
    {
        // Sortie de tunnel : 21 m de saut sur 250 m (86 ‰) entre le dernier point en tunnel et la
        // voie, puis 33 ‰ réels.
        $points = [];
        $flags = [];
        $z = 320.0;
        for ($d = 0; $d <= 4000; $d += 250) {
            $inTunnel = $d <= 1000;
            $z -= $d === 0 || $inTunnel ? 0 : ($d === 1250 ? 21.5 : 8.3);
            $points[] = [(float) $d, $z];
            $flags[] = ['track' => true, 'tunnel' => $inTunnel, 'interpolated' => false];
        }

        $stats = ProfileStats::summarize($points, [], $flags);

        $this->assertNotNull($stats);
        $this->assertEqualsWithDelta(33.2, $stats['max_grade_permil'], 1.0);
    }

    public function test_grade_is_withheld_when_the_track_is_known_on_a_small_part_only(): void
    {
        // 1 km de voie en gare, puis 9 km de terrain : la fenêtre de la gare ne dit rien de la ligne.
        $points = self::series(10000, fn (int $d): float => 10.0 + 0.004 * $d);
        $flags = array_map(fn (array $p): array => ['track' => $p[0] <= 1000, 'tunnel' => false, 'interpolated' => false], $points);

        $stats = ProfileStats::summarize($points, [], $flags);

        $this->assertNotNull($stats);
        $this->assertNull($stats['max_grade_permil']);
        $this->assertSame('terrain_only', $stats['max_grade_withheld']);
    }

    public function test_terrain_noise_is_smoothed_before_the_climbs_are_added_up(): void
    {
        // Ligne plate sur le terrain : le modèle de terrain monte et descend de 8 m d'un point à
        // l'autre (talus, versant voisin). Sans lissage, l'hystérésis de 3 m compterait chaque bosse.
        $points = self::series(20000, fn (int $d): float => 100.0 + (intdiv($d, 100) % 2 === 0 ? 0.0 : 8.0));
        $terrain = array_fill(0, count($points), ['track' => false, 'tunnel' => false, 'interpolated' => false]);

        $raw = ProfileStats::summarize($points);
        $smoothed = ProfileStats::summarize($points, [], $terrain);

        $this->assertNotNull($raw);
        $this->assertNotNull($smoothed);
        $this->assertGreaterThan(700.0, $raw['ascent_m']);
        $this->assertLessThanOrEqual(5.0, $smoothed['ascent_m']);
        $this->assertLessThanOrEqual(5.0, $smoothed['descent_m']);
        // Les altitudes extrêmes restent celles des points.
        $this->assertSame(108.0, $smoothed['max_altitude_m']);
    }

    public function test_a_real_climb_on_the_terrain_is_kept_whole(): void
    {
        // Montée régulière de 1 250 m sur 9 km (chemin de fer du Revard) : la moyenne centrée, rétrécie
        // aux deux bouts, garde toute la montée.
        $points = self::series(9000, fn (int $d): float => 260.0 + 0.139 * $d);
        $terrain = array_fill(0, count($points), ['track' => false, 'tunnel' => false, 'interpolated' => false]);

        $stats = ProfileStats::summarize($points, [], $terrain);

        $this->assertNotNull($stats);
        $this->assertEqualsWithDelta(1251.0, $stats['ascent_m'], 3.0);
        $this->assertSame(0.0, $stats['descent_m']);
    }

    public function test_track_heights_are_not_smoothed(): void
    {
        // Même série en dents de scie, mais mesurée sur la voie : les dénivelés ne changent pas.
        $points = self::series(5000, fn (int $d): float => 100.0 + (intdiv($d, 100) % 2 === 0 ? 0.0 : 8.0));
        $track = array_fill(0, count($points), ['track' => true, 'tunnel' => false, 'interpolated' => false]);

        $this->assertSame(ProfileStats::summarize($points)['ascent_m'] ?? null, ProfileStats::summarize($points, [], $track)['ascent_m'] ?? null);
        $this->assertSame($points, ProfileStats::smoothTerrain($points, [], $track));
    }

    public function test_terrain_smoothing_does_not_reach_across_a_gap(): void
    {
        // Deux morceaux à 100 m et 600 m d'altitude, séparés par une lacune : chaque morceau reste à
        // son altitude, la moyenne ne mélange pas les deux côtés.
        $points = array_merge(
            self::series(2000, fn (int $d): float => 100.0),
            array_map(fn (array $p): array => [$p[0] + 5000.0, 600.0], self::series(2000, fn (int $d): float => 600.0)),
        );
        $terrain = array_fill(0, count($points), ['track' => false, 'tunnel' => false, 'interpolated' => false]);

        $smoothed = ProfileStats::smoothTerrain($points, [[2000.0, 5000.0]], $terrain);

        $this->assertSame([100.0, 600.0], array_values(array_unique(array_column($smoothed, 1))));
    }

    public function test_grade_is_never_published_on_a_rack_railway(): void
    {
        // Rampe de 60 ‰ mesurée sur la voie : publiée sur une ligne à adhérence, jamais sur une
        // ligne à crémaillère, où la règle des 120 ‰ ne garde que les pentes les plus douces.
        $points = self::series(3000, fn (int $d): float => 1000.0 + 0.06 * $d);
        $track = array_fill(0, count($points), ['track' => true, 'tunnel' => false, 'interpolated' => false]);

        $adhesion = ProfileStats::summarize($points, [], $track);
        $rack = ProfileStats::summarize($points, [], $track, rack: true);

        $this->assertNotNull($adhesion);
        $this->assertNotNull($rack);
        $this->assertEqualsWithDelta(60.0, $adhesion['max_grade_permil'], 0.5);
        $this->assertNull($rack['max_grade_permil']);
        $this->assertNull($rack['max_grade_from_m']);
        $this->assertNull($rack['max_grade_direction']);
        $this->assertSame('rack', $rack['max_grade_withheld']);
        $this->assertSame($adhesion['ascent_m'], $rack['ascent_m']);
    }
}
