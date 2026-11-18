<?php

namespace Tests\Unit;

use App\Services\EraContinuity;
use PHPUnit\Framework\TestCase;

/**
 * Continuité des portions d'une ère à l'autre (EraContinuity) : cas limites
 * en complément de EraContinuityTest (boucles, morceaux isolés, bords de
 * chaîne, égalités de distance, listes vides).
 */
class EraContinuityEdgeCasesTest extends TestCase
{
    private const ACTIVE = 'in_service/mixed//;in_service/mixed//';

    private const CLOSED = 'abandoned/unknown//;abandoned/unknown//';

    // ─── chains ──────────────────────────────────────────────────────────

    public function test_no_piece_gives_no_chain_and_a_lone_piece_is_its_own_chain(): void
    {
        $this->assertSame([], EraContinuity::chains([], 30));
        $this->assertSame([[[0, true]]], EraContinuity::chains([[0, 0, 1000, 0]], 30));
    }

    public function test_closed_loop_is_walked_once(): void
    {
        // Triangle : chaque extrémité touche la suivante, aucune extrémité libre.
        $chains = EraContinuity::chains([
            [0, 0, 1000, 0],
            [1000, 5, 500, 800],
            [500, 805, 0, 3],
        ], 30);

        $this->assertSame([[[0, true], [1, true], [2, true]]], $chains);
    }

    public function test_first_piece_drawn_backwards_is_walked_backwards(): void
    {
        // A est dessiné de 1000 vers 0 : son DÉBUT touche B, la chaîne part de sa fin libre.
        $chains = EraContinuity::chains([
            [1000, 0, 0, 0],
            [1010, 0, 2000, 0],
        ], 30);

        $this->assertSame([[[0, false], [1, true]]], $chains);
    }

    public function test_pieces_further_apart_than_the_tolerance_stay_separate(): void
    {
        $chains = EraContinuity::chains([
            [0, 0, 1000, 0],
            [1031, 0, 2000, 0],
        ], 30);

        $this->assertSame([[[0, true]], [[1, true]]], $chains);
    }

    public function test_ends_of_the_same_piece_are_never_linked(): void
    {
        // Morceau presque fermé sur lui-même (boucle de retournement de 20 m d'ouverture).
        $this->assertSame([[[0, true]]], EraContinuity::chains([[0, 0, 0, 20]], 30));
    }

    // ─── smooth ──────────────────────────────────────────────────────────

    public function test_smoothing_nothing_or_a_single_state_changes_nothing(): void
    {
        $this->assertSame([], EraContinuity::smooth([], [], 200, 500));
        $this->assertSame([0, 1, 2], EraContinuity::smooth([50, 50, 50], [self::ACTIVE, self::ACTIVE, self::ACTIVE], 200, 500));
    }

    public function test_short_piece_at_a_chain_end_goes_to_its_only_neighbour(): void
    {
        $this->assertSame([1, 1], EraContinuity::smooth([100, 800], [self::CLOSED, self::ACTIVE], 200, 500));
        $this->assertSame([0, 0], EraContinuity::smooth([800, 100], [self::ACTIVE, self::CLOSED], 200, 500));
    }

    public function test_short_piece_between_two_different_states_goes_to_the_longer_one(): void
    {
        $absent = '-;-';

        $this->assertSame([0, 0, 2], EraContinuity::smooth([900, 100, 300], [self::ACTIVE, $absent, self::CLOSED], 200, 500));
    }

    public function test_justified_pieces_are_never_absorbed(): void
    {
        $src = EraContinuity::smooth([500, 100, 500, 50, 500], [self::ACTIVE, self::CLOSED, self::ACTIVE, self::CLOSED, self::ACTIVE], 200, 500,
            fn () => true);

        $this->assertSame([0, 1, 2, 3, 4], $src);
    }

    public function test_justification_receives_original_positions_of_the_run_and_its_surroundings(): void
    {
        $calls = [];
        EraContinuity::smooth([500, 500, 100, 500, 500], [self::ACTIVE, self::ACTIVE, self::CLOSED, self::ACTIVE, self::ACTIVE], 200, 500,
            function (array $run, array $around) use (&$calls) {
                $calls[] = [$run, $around];

                return true;
            });

        $this->assertSame([[[2], [0, 1, 3, 4]]], $calls);
    }

    // ─── isHole ──────────────────────────────────────────────────────────

    public function test_dismantled_counts_like_closed_when_looking_for_holes(): void
    {
        // Déposée sous des voisines fermées : même rang, pas un trou.
        $this->assertFalse(EraContinuity::isHole('dismantled/unknown//', 'abandoned/unknown//'));
        // Absente sous des voisines déposées : trou.
        $this->assertTrue(EraContinuity::isHole('-', 'dismantled/unknown//'));
        // Fermée sous des voisines en service : trou.
        $this->assertTrue(EraContinuity::isHole('abandoned/unknown//', 'in_service/freight//'));
    }

    // ─── nearest ─────────────────────────────────────────────────────────

    public function test_nearest_point_tie_goes_to_the_oldest_date(): void
    {
        $cell = 2000.0;
        $grid = [];
        foreach ([[1000, 0, '1900-01-01'], [-1000, 0, '1880-01-01']] as [$x, $y, $d]) {
            $grid[(int) floor($x / $cell)][(int) floor($y / $cell)][] = [$x, $y, ['date' => $d]];
        }

        $this->assertSame('1880-01-01', EraContinuity::nearest($grid, 0, 0, $cell, 5000)['date']);
    }

    public function test_nearest_in_an_empty_grid_is_null(): void
    {
        $this->assertNull(EraContinuity::nearest([], 0, 0, 2000, 100_000));
    }

    public function test_nearest_point_must_be_strictly_within_the_limit(): void
    {
        // « À moins de $maxD mètres » : un point pile à la limite n'est pas retenu.
        $grid = [0 => [0 => [[3000, 0, ['date' => '1910-01-01']]]]];

        $this->assertNull(EraContinuity::nearest($grid, 0, 0, 5000, 3000));
        $this->assertSame('1910-01-01', EraContinuity::nearest($grid, 0, 0, 5000, 3001)['date']);
    }

    // ─── dates voisines ─────────────────────────────────────────────────

    public function test_neighbour_opening_before_the_first_whole_line_opening_is_raised_to_it(): void
    {
        $this->assertSame('1856-01-01', EraContinuity::neighbourOpening('1850-01-01', '1856-01-01', null, '1900-01-01'));
        // Exactement à la date de la première fermeture : admis.
        $this->assertSame('1870-01-01', EraContinuity::neighbourOpening('1870-01-01', '1856-01-01', null, '1870-01-01'));
    }

    public function test_junction_opening_of_lines_touched_several_times_uses_their_oldest_date(): void
    {
        // Les deux extrémités touchent la même LGV : sa date la plus ancienne.
        $this->assertSame('1981-09-27', EraContinuity::junctionOpening([[831, '1992-12-13', true], [831, '1981-09-27', true]]));
        // LGV de 1989 et ligne classique de 1846 : pas plus ancien que la plus récente.
        $this->assertSame('1989-09-24', EraContinuity::junctionOpening([[30, '1846-06-20', false], [900, '1989-09-24', true]]));
    }

    public function test_neighbour_closure_without_own_event_takes_the_neighbour(): void
    {
        $neighbour = ['date' => '1970-01-01'];

        $this->assertSame($neighbour, EraContinuity::neighbourClosure($neighbour, [['date' => '1960-01-01']], null));
        $this->assertSame(['date' => '1960-01-01'], EraContinuity::neighbourClosure(null, [['date' => '1960-01-01']], null));
        $this->assertNull(EraContinuity::neighbourClosure(null, [], null));
        // Même date que le dernier événement de la portion : ne la suit pas.
        $this->assertNull(EraContinuity::neighbourClosure($neighbour, [], '1970-01-01'));
    }
}
