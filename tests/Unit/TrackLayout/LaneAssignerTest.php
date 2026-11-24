<?php

namespace Tests\Unit\TrackLayout;

use App\Services\TrackLayout\LaneAssigner;
use PHPUnit\Framework\TestCase;

/**
 * Rows of the track layout: with the lateral offsets of a geometric source (OpenStreetMap), the real lateral order
 * decides (track 1 side from the offsets of tracks 1 and 2, right-hand running lines included, unnamed main tracks,
 * loops beyond their neighbours, pieces of one track sharing a row, crossovers on the rows they join); without
 * offsets, the track numbers decide as before.
 */
class LaneAssignerTest extends TestCase
{
    private static function track(string $id, string $kind, ?string $ref, int $from, int $to, ?float $offset = null, array $extra = []): array
    {
        return ['id' => $id, 'kind' => $kind, 'ref' => $ref, 'pk_from_m' => $from, 'pk_to_m' => $to, 'op_id' => null] + $extra
            + ($offset === null ? [] : ['offset_m' => $offset]);
    }

    /** @return array<string, int> */
    private static function lanes(array $tracks): array
    {
        return array_column(LaneAssigner::assign($tracks), 'lane', 'id');
    }

    public function test_offsets_order_the_tracks_of_a_double_track_station(): void
    {
        $lanes = self::lanes([
            self::track('v1', 'running', '1', 0, 5_000, 2.0),
            self::track('v2', 'running', '2', 0, 5_000, -2.0),
            // Platform loop beyond track 2, a yard further out, a siding beyond track 1.
            self::track('loop', 'loop', null, 1_000, 1_600, -7.0),
            self::track('yard', 'yard', null, 1_100, 1_500, -15.0),
            self::track('siding', 'siding', null, 1_200, 1_400, 6.5),
        ]);

        $this->assertSame(['v1' => 0, 'v2' => 1, 'loop' => 2, 'yard' => 3, 'siding' => -1], $lanes);
    }

    public function test_track_one_on_the_right_on_right_hand_running_lines(): void
    {
        // Alsace: track 1 lies on the right facing increasing PKs; a siding next to it stays on its side.
        $lanes = self::lanes([
            self::track('v1', 'running', '1', 0, 5_000, -2.0),
            self::track('v2', 'running', '2', 0, 5_000, 2.0),
            self::track('siding', 'siding', null, 1_000, 1_500, -6.0),
        ]);

        $this->assertSame(-1, $lanes['siding']);
        $this->assertSame(1, LaneAssigner::trackOneSide([self::track('a', 'running', '1', 0, 10, 2.0), self::track('b', 'running', '2', 0, 10, -2.0)]));
    }

    public function test_unnamed_main_tracks_and_touching_pieces(): void
    {
        $lanes = self::lanes([
            // An unnamed double track split into pieces at a switch: each piece keeps its row.
            self::track('left-a', 'running', null, 0, 2_000, 2.1),
            self::track('left-b', 'running', null, 2_000, 4_000, 2.0),
            self::track('right-a', 'running', null, 0, 4_000, -2.0),
            // A crossover between them sits on the lower row it joins.
            self::track('x', 'crossover', null, 1_990, 2_050, 0.0, ['connects' => ['left-a', 'right-a']]),
        ]);

        $this->assertSame(['left-a' => 0, 'left-b' => 0, 'right-a' => 1, 'x' => 0], $lanes);
        $out = LaneAssigner::assign([self::track('a', 'loop', null, 0, 10, 3.0, ['connects' => []])]);
        $this->assertArrayNotHasKey('offset_m', $out[0]);
        $this->assertArrayNotHasKey('connects', $out[0]);
    }

    public function test_single_track_loops_take_both_sides(): void
    {
        $lanes = self::lanes([
            self::track('vu', 'running', 'U', 0, 10_000, 0.0),
            self::track('north', 'loop', null, 4_000, 4_500, 4.5),
            self::track('south', 'loop', null, 4_000, 4_500, -4.5),
        ]);

        $this->assertSame(['vu' => 0, 'north' => -1, 'south' => 1], $lanes);
    }

    public function test_without_offsets_track_numbers_decide(): void
    {
        $lanes = self::lanes([
            self::track('v1', 'running', 'V1', 0, 5_000),
            self::track('v2', 'running', 'V2', 0, 5_000),
            self::track('3', 'loop', '3', 1_000, 1_300),
            self::track('5', 'siding', '5', 1_000, 1_300),
            self::track('4', 'loop', '4', 1_000, 1_300),
        ]);

        $this->assertSame(['v1' => 0, 'v2' => 1, '3' => -1, '5' => -2, '4' => 2], $lanes);
    }
}
