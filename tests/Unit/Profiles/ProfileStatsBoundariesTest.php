<?php

namespace Tests\Unit\Profiles;

use App\Services\Profiles\ProfileStats;
use PHPUnit\Framework\TestCase;

/**
 * Bornes, seuils, arrondis et ordre des calculs de ProfileStats : tests ajoutés après une passe de
 * mutation (Infection, tests/Mutation/Profiles).
 */
class ProfileStatsBoundariesTest extends TestCase
{
    /** @return list<array{0: float, 1: float}> */
    private function line(int $count, float $step, float $z0 = 100.0, float $perMetre = 0.0625, float $d0 = 0.0): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $points[] = [$d0 + $i * $step, $z0 + $i * $step * $perMetre];
        }

        return $points;
    }

    /** @return array{track: bool, tunnel: bool, interpolated: bool} */
    private static function flag(bool $track = true, bool $tunnel = false, bool $interpolated = false): array
    {
        return ['track' => $track, 'tunnel' => $tunnel, 'interpolated' => $interpolated];
    }

    public function test_summary_rounds_each_figure_to_its_own_precision(): void
    {
        $s = ProfileStats::summarize([[0.6, 100.06], [500.5, 110.66], [1000.6, 100.26]]);

        $this->assertSame(100.1, $s['min_altitude_m']);
        $this->assertSame(110.7, $s['max_altitude_m']);
        $this->assertSame(11.0, $s['ascent_m']);
        $this->assertSame(10.0, $s['descent_m']);
        $this->assertSame(0.2, $s['max_grade_permil']);
        $this->assertSame(1, $s['max_grade_from_m']);
        $this->assertSame(1001, $s['max_grade_to_m']);
        $this->assertSame('up', $s['max_grade_direction']);
        $this->assertNull($s['max_grade_withheld']);
    }

    public function test_a_flat_parcours_reads_as_an_ascending_grade_of_zero(): void
    {
        $s = ProfileStats::summarize([[0.0, 50.0], [500.0, 50.0], [1000.0, 50.0]]);

        $this->assertSame(0.0, $s['max_grade_permil']);
        $this->assertSame('up', $s['max_grade_direction']);
        $this->assertSame(0.0, $s['ascent_m']);
    }

    public function test_the_direction_of_a_descending_grade_is_down(): void
    {
        $s = ProfileStats::summarize([[0.0, 150.0], [500.0, 120.0], [1000.0, 90.0]]);

        $this->assertSame(60.0, $s['max_grade_permil']);
        $this->assertSame('down', $s['max_grade_direction']);
    }

    public function test_a_rack_railway_never_publishes_a_grade(): void
    {
        $s = ProfileStats::summarize($this->line(5, 250.0, 100.0, 0.05), [], [], true);

        $this->assertSame('rack', $s['max_grade_withheld']);
        $this->assertNull($s['max_grade_permil']);
        $this->assertNull($s['max_grade_from_m']);
        $this->assertNull($s['max_grade_to_m']);
        $this->assertNull($s['max_grade_direction']);
    }

    public function test_two_altitudes_are_enough_for_a_summary_one_is_not(): void
    {
        $this->assertNull(ProfileStats::summarize([[0.0, 10.0], [100.0, null]]));
        $this->assertNotNull(ProfileStats::summarize([[0.0, 10.0], [100.0, 20.0]]));
        $this->assertNotNull(ProfileStats::summarize([[0.0, 10.0], [50.0, null], [100.0, 20.0]]));
    }

    public function test_the_first_best_window_wins_on_equal_grades_and_starts_at_the_first_point(): void
    {
        $s = ProfileStats::summarize($this->line(9, 250.0, 100.0, 0.0625, 5000.0));

        $this->assertSame(62.5, $s['max_grade_permil']);
        $this->assertSame(5000, $s['max_grade_from_m']);
        $this->assertSame(6000, $s['max_grade_to_m']);
    }

    public function test_a_short_parcours_uses_its_own_length_as_window(): void
    {
        $s = ProfileStats::summarize($this->line(4, 200.0, 100.0, 0.0625, 5000.0));

        $this->assertSame(62.5, $s['max_grade_permil']);
        $this->assertSame(5000, $s['max_grade_from_m']);
        $this->assertSame(5600, $s['max_grade_to_m']);
    }

    public function test_a_window_must_reach_three_quarters_of_its_length(): void
    {
        // 0 à 750 m : exactement les trois quarts de 1 000 m, admise.
        $ok = ProfileStats::summarize([[0.0, 100.0], [300.0, 130.0], [750.0, 175.0], [2000.0, 175.0]]);
        $this->assertSame(0, $ok['max_grade_from_m']);
        $this->assertSame(750, $ok['max_grade_to_m']);
        $this->assertGreaterThan(0.0, $ok['max_grade_permil']);

        // 0 à 200 m seulement : trois points mais une fenêtre trop courte, aucune pente publiée
        // (corrigé le 21/11/2026 : null, et non plus 0,0 ‰ « up » qui figeait un défaut).
        $short = ProfileStats::summarize([[0.0, 100.0], [100.0, 105.0], [200.0, 110.0], [5000.0, 110.0]]);
        $this->assertNull($short['max_grade_permil']);
        $this->assertNull($short['max_grade_direction']);
    }

    public function test_a_window_crossing_a_gap_is_ignored_but_the_next_one_is_kept(): void
    {
        $points = $this->line(13, 250.0);
        $s = ProfileStats::summarize($points, [[100.0, 200.0]]);

        $this->assertSame(250, $s['max_grade_from_m']);
        $this->assertSame(62.5, $s['max_grade_permil']);

        $all = ProfileStats::summarize($this->line(5, 250.0), [[400.0, 600.0]]);
        $this->assertNull($all['max_grade_permil']);
    }

    public function test_the_window_includes_a_point_exactly_at_its_end(): void
    {
        $s = ProfileStats::summarize([[0.0, 100.0], [500.0, 130.0], [1000.0, 160.0]]);

        $this->assertSame(60.0, $s['max_grade_permil']);
        $this->assertSame(1000, $s['max_grade_to_m']);
    }

    public function test_a_grade_of_exactly_120_permil_is_kept_and_a_steeper_step_is_not(): void
    {
        $at = ProfileStats::summarize($this->line(5, 250.0, 100.0, 0.12));
        $this->assertSame(120.0, $at['max_grade_permil']);

        $over = ProfileStats::summarize($this->line(5, 250.0, 100.0, 0.13));
        $this->assertNull($over['max_grade_permil']);
        $this->assertSame('interpolated', $over['max_grade_withheld']);
    }

    public function test_a_step_at_a_doubtful_point_is_judged_against_the_tunnel_limit(): void
    {
        // 70 ‰ exactement (17,5 m sur 250 m) ; au-delà, un point en tunnel ou interpolé écarte la fenêtre.
        $flags = array_fill(0, 5, self::flag());
        $flags[2] = self::flag(true, true);
        $ok = ProfileStats::summarize($this->line(5, 250.0, 100.0, 0.07), [], $flags);
        $this->assertSame(70.0, $ok['max_grade_permil']);

        $over = ProfileStats::summarize($this->line(5, 250.0, 100.0, 0.0704), [], $flags);
        $this->assertNull($over['max_grade_permil']);
        $this->assertSame('interpolated', $over['max_grade_withheld']);

        $same = ProfileStats::summarize($this->line(5, 250.0, 100.0, 0.0704), [], array_fill(0, 5, self::flag()));
        $this->assertSame(70.4, $same['max_grade_permil']);

        $flags = array_fill(0, 5, self::flag());
        $flags[3] = self::flag(true, false, true);
        $this->assertSame('interpolated', ProfileStats::summarize($this->line(5, 250.0, 100.0, 0.0704), [], $flags)['max_grade_withheld']);
    }

    public function test_a_window_with_a_quarter_of_terrain_points_is_kept_one_more_is_not(): void
    {
        $line = $this->line(4, 300.0);
        $quarter = [self::flag(), self::flag(), self::flag(), self::flag(false)];
        $this->assertNull(ProfileStats::summarize($line, [], $quarter)['max_grade_withheld']);

        $half = [self::flag(), self::flag(), self::flag(false), self::flag(false)];
        $s = ProfileStats::summarize($line, [], $half);
        $this->assertSame('terrain_only', $s['max_grade_withheld']);
        $this->assertNull($s['max_grade_permil']);
    }

    public function test_a_window_with_a_quarter_of_tunnel_points_is_kept_two_in_seven_are_not(): void
    {
        $one = array_fill(0, 4, self::flag());
        $one[1] = self::flag(true, true);
        $this->assertNull(ProfileStats::summarize($this->line(4, 300.0), [], $one)['max_grade_withheld']);

        $two = array_fill(0, 7, self::flag());
        $two[3] = self::flag(true, true);
        $two[4] = self::flag(true, true);
        $s = ProfileStats::summarize($this->line(7, 150.0), [], $two);
        $this->assertSame('tunnel', $s['max_grade_withheld']);
        $this->assertNull($s['max_grade_permil']);
    }

    public function test_a_window_with_a_quarter_of_interpolated_points_is_kept_two_in_seven_are_not(): void
    {
        $one = array_fill(0, 4, self::flag());
        $one[1] = self::flag(true, false, true);
        $this->assertNull(ProfileStats::summarize($this->line(4, 300.0), [], $one)['max_grade_withheld']);

        $two = array_fill(0, 7, self::flag());
        $two[3] = self::flag(true, false, true);
        $two[4] = self::flag(true, false, true);
        $s = ProfileStats::summarize($this->line(7, 150.0), [], $two);
        $this->assertSame('interpolated', $s['max_grade_withheld']);
        $this->assertNull($s['max_grade_permil']);
    }

    public function test_a_line_measured_on_half_of_its_points_keeps_its_grade_fewer_lose_it(): void
    {
        $line = $this->line(8, 250.0);
        $half = [self::flag(), self::flag(), self::flag(), self::flag(), self::flag(false), self::flag(false), self::flag(false), self::flag(false)];
        $s = ProfileStats::summarize($line, [], $half);
        $this->assertNull($s['max_grade_withheld']);
        $this->assertSame(62.5, $s['max_grade_permil']);

        $line = $this->line(10, 250.0);
        $four = array_merge(array_fill(0, 4, self::flag()), array_fill(0, 6, self::flag(false)));
        $s = ProfileStats::summarize($line, [], $four);
        $this->assertSame('terrain_only', $s['max_grade_withheld']);
        $this->assertNull($s['max_grade_permil']);
    }

    public function test_the_withheld_reason_is_the_most_frequent_one_then_terrain_tunnel_interpolated(): void
    {
        // Aucune fenêtre complète (parcours en trois morceaux) mais des drapeaux : motif d'après l'ensemble.
        $line = $this->line(3, 100.0);
        $s = ProfileStats::summarize($line, [[50.0, 60.0], [150.0, 160.0]], [self::flag(false), self::flag(false), self::flag(false)]);
        $this->assertSame('terrain_only', $s['max_grade_withheld']);

        $s = ProfileStats::summarize($line, [[50.0, 60.0], [150.0, 160.0]], [self::flag(), self::flag(), self::flag()]);
        $this->assertSame('interpolated', $s['max_grade_withheld']);

        // Sans drapeau ni fenêtre refusée : aucun motif.
        $this->assertNull(ProfileStats::summarize($line, [[50.0, 60.0], [150.0, 160.0]])['max_grade_withheld']);
    }

    public function test_one_refused_window_of_each_kind_names_the_first_in_order(): void
    {
        // Un seul motif refusé, d'abord l'interpolé : il ne cède pas la place à un motif jamais compté.
        $flags = array_fill(0, 7, self::flag());
        $flags[3] = self::flag(true, false, true);
        $flags[4] = self::flag(true, false, true);
        $this->assertSame('interpolated', ProfileStats::summarize($this->line(7, 150.0), [], $flags)['max_grade_withheld']);
    }

    public function test_climbs_count_from_exactly_three_metres_and_ignore_smaller_moves(): void
    {
        $this->assertSame(3.0, ProfileStats::summarize([[0.0, 100.0], [100.0, 103.0]])['ascent_m']);
        $this->assertSame(3.0, ProfileStats::summarize([[0.0, 103.0], [100.0, 100.0]])['descent_m']);

        $small = ProfileStats::summarize([[0.0, 100.0], [100.0, 99.0], [200.0, 100.5], [300.0, 101.9]]);
        $this->assertSame(0.0, $small['ascent_m']);
        $this->assertSame(0.0, $small['descent_m']);
    }

    public function test_successive_descents_add_up(): void
    {
        $s = ProfileStats::summarize([[0.0, 100.0], [100.0, 90.0], [200.0, 100.0], [300.0, 90.0]]);

        $this->assertSame(20.0, $s['descent_m']);
        $this->assertSame(10.0, $s['ascent_m']);
    }

    public function test_climbs_restart_after_a_gap_and_keep_going(): void
    {
        $s = ProfileStats::summarize([[0.0, 100.0], [100.0, 101.0], [200.0, 200.0], [300.0, 210.0]], [[150.0, 160.0]]);

        $this->assertSame(10.0, $s['ascent_m']);
        $this->assertSame(0.0, $s['descent_m']);
    }

    public function test_a_point_without_altitude_resets_the_reference(): void
    {
        $s = ProfileStats::summarize([[0.0, 100.0], [100.0, null], [200.0, 150.0], [300.0, 156.0]]);

        $this->assertSame(6.0, $s['ascent_m']);
    }

    public function test_pieces_are_cut_only_when_the_gap_really_lies_between_two_points(): void
    {
        $points = [[0.0, 1.0], [100.0, 1.0], [200.0, 1.0], [300.0, 1.0]];

        $this->assertSame([[0, 1], [2, 3]], ProfileStats::pieces($points, [[100.0, 200.0]]));
        $this->assertSame([[0, 3]], ProfileStats::pieces($points, [[100.0, 100.5]]));
        $this->assertSame([[0, 3]], ProfileStats::pieces($points, [[199.5, 200.4]]));
        $this->assertSame([[0, 1], [2, 3]], ProfileStats::pieces($points, [[100.0, 100.6]]));
        $this->assertSame([[0, 1], [2, 3]], ProfileStats::pieces($points, [[199.4, 200.4]]));
        $this->assertSame([], ProfileStats::pieces([]));
    }

    public function test_altitude_at_the_ends_and_in_the_middle_of_the_parcours(): void
    {
        $points = [[0.0, 10.0], [100.0, 20.0], [200.0, 30.0], [300.0, 40.0]];

        $this->assertSame(10.0, ProfileStats::altitudeAt($points, 0.0));
        $this->assertSame(20.0, ProfileStats::altitudeAt($points, 100.0));
        $this->assertSame(30.0, ProfileStats::altitudeAt($points, 200.0));
        $this->assertSame(40.0, ProfileStats::altitudeAt($points, 300.0));
        $this->assertSame(25.0, ProfileStats::altitudeAt($points, 150.0));
        $this->assertNull(ProfileStats::altitudeAt($points, -0.5));
        $this->assertNull(ProfileStats::altitudeAt($points, 300.5));
        $this->assertNull(ProfileStats::altitudeAt([], 5.0));
        $this->assertSame(15.0, ProfileStats::altitudeAt([[100.0, 10.0], [300.0, 20.0]], 200.0));
    }

    public function test_altitude_at_a_doubled_distance_takes_the_first_altitude(): void
    {
        $this->assertSame(20.0, ProfileStats::altitudeAt([[0.0, 10.0], [100.0, 20.0], [100.0, 30.0]], 100.0));
    }

    public function test_altitude_next_to_a_point_without_altitude_is_known_only_on_a_point_with_one(): void
    {
        $points = [[0.0, 10.0], [100.0, null], [200.0, 30.0]];

        $this->assertSame(10.0, ProfileStats::altitudeAt($points, 0.0));
        $this->assertNull(ProfileStats::altitudeAt($points, 100.0));
        $this->assertSame(30.0, ProfileStats::altitudeAt($points, 200.0));
        $this->assertNull(ProfileStats::altitudeAt($points, 50.0));
        $this->assertNull(ProfileStats::altitudeAt($points, 150.0));
        $this->assertSame(20.0, ProfileStats::altitudeAt([[0.0, null], [100.0, 20.0]], 100.0));
        $this->assertNull(ProfileStats::altitudeAt([[0.0, null], [100.0, 20.0]], 50.0));
    }

    public function test_a_hole_of_exactly_the_maximum_length_is_filled_a_longer_one_is_not(): void
    {
        $none = [false, false, false];

        $filled = ProfileStats::fillStructures([[0.0, 100.0], [500.0, null], [1000.0, 200.0]], $none, 1000.0);
        $this->assertSame(150.0, $filled[1][1]);

        $kept = ProfileStats::fillStructures([[0.0, 100.0], [500.0, null], [1001.0, 200.0]], $none, 1000.0);
        $this->assertNull($kept[1][1]);
    }

    public function test_a_hole_missing_its_flags_is_not_a_structure(): void
    {
        $out = ProfileStats::fillStructures([[0.0, 100.0], [500.0, null], [3000.0, 200.0]], [], 1000.0);

        $this->assertNull($out[1][1]);
    }

    public function test_a_structure_framed_on_both_sides_is_interpolated_whatever_its_length(): void
    {
        $out = ProfileStats::fillStructures([[0.0, 100.0], [500.0, 9999.0], [3000.0, 200.0]], [false, true, false], 1000.0);

        $this->assertEqualsWithDelta(116.6666667, $out[1][1], 1e-6);
    }

    public function test_a_long_structure_open_on_one_side_is_left_empty(): void
    {
        $start = ProfileStats::fillStructures([[0.0, null], [2000.0, 50.0]], [true, false], 1000.0);
        $this->assertNull($start[0][1]);

        $end = ProfileStats::fillStructures([[0.0, 50.0], [1500.0, 9999.0]], [false, true], 1000.0);
        $this->assertSame(9999.0, $end[1][1]);
    }

    public function test_a_short_trailing_hole_takes_the_altitude_of_its_only_neighbour(): void
    {
        $short = ProfileStats::fillStructures([[0.0, 100.0], [400.0, null], [900.0, null]], [false, false, false], 1000.0);
        $this->assertSame(100.0, $short[1][1]);
        $this->assertSame(100.0, $short[2][1]);

        $long = ProfileStats::fillStructures([[0.0, 100.0], [400.0, null], [1200.0, null]], [false, false, false], 1000.0);
        $this->assertNull($long[1][1]);
        $this->assertNull($long[2][1]);
    }

    public function test_a_valid_point_outside_any_structure_is_left_alone(): void
    {
        $points = [[0.0, 100.0], [500.0, 105.0], [1000.0, 110.0]];

        $this->assertSame($points, ProfileStats::fillStructures($points, [false, false, false]));
    }

    public function test_terrain_is_averaged_over_a_kilometre_centred_on_each_point(): void
    {
        $points = [[0.0, 100.0], [500.0, 100.0], [1000.0, 100.0], [1500.0, 200.0], [2000.0, 100.0], [2500.0, 100.0], [3000.0, 130.0]];
        $flags = array_fill(0, 7, self::flag(false));

        $out = ProfileStats::smoothTerrain($points, [], $flags);

        $this->assertEqualsWithDelta(100.0, $out[0][1], 1e-9);
        $this->assertEqualsWithDelta(100.0, $out[1][1], 1e-9);
        $this->assertEqualsWithDelta(133.3333333, $out[2][1], 1e-6);
        $this->assertEqualsWithDelta(133.3333333, $out[3][1], 1e-6);
        $this->assertEqualsWithDelta(133.3333333, $out[4][1], 1e-6);
        $this->assertEqualsWithDelta(110.0, $out[5][1], 1e-9);
        $this->assertEqualsWithDelta(130.0, $out[6][1], 1e-9);
    }

    public function test_terrain_smoothing_shrinks_near_the_start_of_an_offset_parcours(): void
    {
        $points = [[1000.0, 100.0], [1500.0, 200.0], [2000.0, 100.0], [2500.0, 100.0], [3000.0, 100.0]];
        $out = ProfileStats::smoothTerrain($points, [], array_fill(0, 5, self::flag(false)));

        $this->assertEqualsWithDelta(100.0, $out[0][1], 1e-9);
        $this->assertEqualsWithDelta(133.3333333, $out[1][1], 1e-6);
    }

    public function test_terrain_smoothing_skips_track_points_but_keeps_smoothing_the_following_ones(): void
    {
        $points = [[0.0, 100.0], [500.0, 100.0], [1000.0, 200.0], [1500.0, 100.0], [2000.0, 100.0]];
        $flags = [self::flag(), self::flag(), self::flag(false), self::flag(false), self::flag(true, false, true)];

        $out = ProfileStats::smoothTerrain($points, [], $flags);

        $this->assertSame(100.0, $out[0][1]);
        $this->assertSame(100.0, $out[1][1]);
        $this->assertEqualsWithDelta(133.3333333, $out[2][1], 1e-6);
        $this->assertEqualsWithDelta(133.3333333, $out[3][1], 1e-6);
        $this->assertSame(100.0, $out[4][1]);
    }

    public function test_terrain_smoothing_ignores_missing_altitudes_and_missing_flags(): void
    {
        $points = [[0.0, 100.0], [500.0, null], [1000.0, 130.0], [1500.0, 100.0]];

        $out = ProfileStats::smoothTerrain($points, [], [self::flag(false), self::flag(false), self::flag(false)]);

        $this->assertSame(100.0, $out[0][1]);
        $this->assertNull($out[1][1]);
        $this->assertEqualsWithDelta(115.0, $out[2][1], 1e-9);
        $this->assertSame(100.0, $out[3][1]);
    }

    public function test_spikes_further_than_twenty_metres_from_the_local_median_are_erased(): void
    {
        $points = [];
        for ($i = 0; $i < 11; $i++) {
            $points[] = [$i * 1000.0, 100.0];
        }
        $points[5][1] = 120.0;
        $this->assertSame(120.0, ProfileStats::removeSpikes($points)[5][1]);

        $points[5][1] = 120.1;
        $this->assertNull(ProfileStats::removeSpikes($points)[5][1]);
    }
}
