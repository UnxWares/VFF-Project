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

    /**
     * Paris-Austerlitz, line 570000 (OpenStreetMap ways and their offsets on the reference trace, RINF platforms of the
     * point FR0000004909, read on 25/11/2026): the offsets give 14, 18, 15, 19, 20, 17, 21 (the underground RER tracks
     * lie under the surface tracks); the tracks of one platform are neighbours and the platforms follow their numbers.
     */
    public function test_the_tracks_of_one_platform_are_neighbours_in_the_order_of_their_numbers(): void
    {
        $key = fn (string $platform): array => ['platform_keys' => ['FR0000004909|'.$platform]];
        $lanes = self::lanes([
            self::track('v1', 'running', 'V1', 0, 860, 101.4),
            self::track('v2', 'running', 'V2', 0, 860, 91.8),
            self::track('osm:way:24156181', 'running', '14', 130, 661, 12.6, $key('quai14/15')),
            self::track('osm:way:256454048', 'running', '15', 131, 601, 1.6, $key('quai14/15')),
            self::track('osm:way:152337196', 'running', '17', 131, 640, -12.9, $key('quai16/17')),
            self::track('osm:way:24156364', 'running', '18', 131, 680, 12.3, $key('quai18/19')),
            self::track('osm:way:256454080', 'running', '19', 131, 641, 1.4, $key('quai18/19')),
            self::track('osm:way:152337201', 'running', '20', 131, 641, -2.5, $key('quai20/21')),
            self::track('osm:way:152337203', 'running', '21', 132, 684, -13.3, $key('quai20/21')),
            // Underground track 3S of the RER, without a platform here.
            self::track('osm:way:638180762', 'running', '3S', 45, 458, 9.2),
        ]);
        $order = ['osm:way:24156181', 'osm:way:256454048', 'osm:way:152337196', 'osm:way:24156364', 'osm:way:256454080', 'osm:way:152337201', 'osm:way:152337203'];
        $platform = array_map(fn (string $id): int => $lanes[$id], $order);

        // 14 15 | 17 | 18 19 | 20 21, on consecutive rows below track 2.
        $this->assertSame(range($platform[0], $platform[0] + 6), $platform);
        $this->assertGreaterThan(1, $platform[0]);
        $this->assertNotContains($lanes['osm:way:638180762'], $platform);
    }

    /**
     * Poitiers, line 570000 (OpenStreetMap ways and offsets, RINF platforms of the point FR0000004178, 25/11/2026): Z
     * lies between tracks 1 and 2 by its offset, the long loop 88745606 between 6 and 8 by its mean offset; quai 2
     * borders Z and track 1, quai 3 track 2 and 4, quai 4 tracks 6 and 8. Track 1 and 2 keep their rows: Z goes just
     * beyond track 1, 4 just beyond track 2, and nothing runs between 6 and 8.
     */
    public function test_a_platform_shared_with_a_main_track_and_a_platform_never_runs_along_a_track_through_another(): void
    {
        $key = fn (string $platform): array => ['platform_keys' => ['FR0000004178|'.$platform]];
        $lanes = self::lanes([
            self::track('v1', 'running', 'V1', 336100, 336830, -1.1, $key('quai2')),
            self::track('v2', 'running', 'V2', 336196, 336561, -15.0, $key('quai3')),
            self::track('osm:way:336888126', 'loop', null, 335985, 336815, 4.0),
            self::track('osm:way:399330597', 'loop', 'Z', 336100, 336814, -9.7, $key('quai2')),
            self::track('osm:way:304521483', 'loop', '4', 336181, 336784, -21.9, $key('quai3')),
            self::track('osm:way:88745529', 'loop', '6', 336197, 336746, -28.6, $key('quai4')),
            self::track('osm:way:88745606', 'loop', null, 335983, 337162, -34.1),
            self::track('osm:way:88745585', 'loop', '8', 336126, 336704, -35.5, $key('quai4')),
        ]);

        $this->assertSame([-1, 0, 1, 2], [$lanes['osm:way:399330597'], $lanes['v1'], $lanes['v2'], $lanes['osm:way:304521483']]);
        $this->assertSame(1, abs($lanes['osm:way:88745529'] - $lanes['osm:way:88745585']));
        $this->assertNotContains($lanes['osm:way:88745606'], range(min($lanes['osm:way:88745529'], $lanes['osm:way:88745585']), max($lanes['osm:way:88745529'], $lanes['osm:way:88745585'])));
        $this->assertArrayNotHasKey('platform_keys', LaneAssigner::assign([self::track('a', 'loop', '3', 0, 10, null, $key('quai3'))])[0]);
    }

    /**
     * Chirac, line 722000 (RINF point FR0000004201, 25/11/2026): the only platform track is the single track itself
     * (« Voie UNIQUE de Béziers à Neussargues-Moissac », « Quai » of 80 m), with nothing beyond it to place. The
     * layout used to divide by zero (the mean row of an empty run of tracks): seven stations answered 500.
     */
    public function test_a_platform_along_the_single_track_alone_keeps_the_rows(): void
    {
        $lanes = self::lanes([
            self::track('rinf:t:vu', 'running', 'U', 0, 9_000, null, ['platform_keys' => ['FR0000004201|quai']]),
            self::track('loop', 'loop', null, 4_000, 4_500, -4.5),
        ]);

        $this->assertSame(['rinf:t:vu' => 0, 'loop' => 1], $lanes);
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
