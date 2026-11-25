<?php

namespace Tests\Feature\Api;

use App\Models\Line;
use App\Services\TrackLayout\TrackLayout;
use App\Services\TrackLayout\TrackLayoutPart;
use App\Services\TrackLayout\TrackLayoutSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One track described twice is served once (Paris-Austerlitz, 25/11/2026: "V1BIS" of the RINF and "1bis" of
 * OpenStreetMap side by side, "V1TER V1TER V1TER" for the pieces of one RINF track cut at each section of line):
 * the RINF line track keeps its official identifier and takes the lateral offset of OpenStreetMap, the duplicates
 * (same number, or no number at the same place, or the approximate RINF station track of the same number) are
 * absorbed and what cited them cites the kept track; the pieces of one track share one row; "PN 1" of SNCF Réseau
 * and "1" of OpenStreetMap are one level crossing; a RINF speed says which tracks it applies to.
 */
class TrackLayoutDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function track(string $id, string $source, ?string $ref, int $from, int $to, string $kind = 'running', array $extra = []): array
    {
        return $extra + ['id' => $id, 'ref' => $ref, 'kind' => $kind, 'pk_from_m' => $from, 'pk_to_m' => $to, 'op_id' => null, 'length_m' => $to - $from,
            'source' => $source, 'precision' => $source === 'rinf' ? 'exact' : 'derived'];
    }

    /** @param  array<string, mixed>  $part */
    private static function source(string $key, array $part): TrackLayoutSource
    {
        return new class($key, $part) implements TrackLayoutSource
        {
            /** @param  array<string, mixed>  $part */
            public function __construct(private string $key, private array $part) {}

            public function key(): string
            {
                return $this->key;
            }

            public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart
            {
                return new TrackLayoutPart(...['source' => ['key' => $this->key, 'label' => $this->key, 'licence' => null, 'dataset' => null, 'fetched_at' => null, 'url' => null]] + $this->part);
            }
        };
    }

    /** @return array<string, mixed> */
    private function layout(): array
    {
        $rinf = self::source('rinf', [
            'tracks' => [
                self::track('r:v1', 'rinf', 'V1', 0, 1000),
                self::track('r:v2', 'rinf', 'V2', 0, 1000),
                self::track('r:v1bis-a', 'rinf', 'V1BIS', 0, 500),
                self::track('r:v1bis-b', 'rinf', 'V1BIS', 500, 1000),
                self::track('r:v1ter-a', 'rinf', 'V1TER', 0, 400),
                self::track('r:v1ter-b', 'rinf', 'V1TER', 400, 1000),
                // Track 1 of the station, centred on its PK: the line track 1 itself.
                self::track('r:st-v1', 'rinf', 'V1', -200, 200, 'running', ['precision' => 'approximate', 'op_id' => 'r:op', 'platform' => true]),
            ],
            'speeds' => [['pk_from_m' => 0, 'pk_to_m' => 1000, 'kmh' => 90, 'tracks' => ['V1', 'V2'], 'source' => 'rinf'],
                ['pk_from_m' => 0, 'pk_to_m' => 1000, 'kmh' => 60, 'tracks' => ['V1BIS'], 'source' => 'rinf']],
            'covered' => ['tracks' => [[0, 1000]]],
        ]);
        $osm = self::source('osm', [
            'tracks' => [
                self::track('o:1', 'osm', '1', 0, 1000, 'running', ['offset_m' => 2.0]),
                self::track('o:2', 'osm', '2', 0, 1000, 'running', ['offset_m' => -2.0]),
                self::track('o:1bis', 'osm', '1bis', 100, 900, 'running', ['offset_m' => 6.4]),
                // The same track without a number, a few decimetres away.
                self::track('o:nameless', 'osm', null, 200, 800, 'running', ['offset_m' => 6.0]),
                // A yard track beyond it, kept.
                self::track('o:yard', 'osm', null, 300, 600, 'yard', ['offset_m' => 15.0]),
            ],
            'switches' => [
                ['id' => 'o:sw1', 'ref' => '12', 'pk_m' => 150, 'kind' => 'simple', 'from_track' => 'o:1', 'to_track' => 'o:1bis', 'facing' => null, 'source' => 'osm', 'precision' => 'derived'],
                ['id' => 'o:sw2', 'ref' => '14', 'pk_m' => 300, 'kind' => 'simple', 'from_track' => 'o:nameless', 'to_track' => 'o:yard', 'facing' => null, 'source' => 'osm', 'precision' => 'derived'],
                ['id' => 'o:sw3', 'ref' => '16', 'pk_m' => 700, 'kind' => 'simple', 'from_track' => 'o:1bis', 'to_track' => 'o:nameless', 'facing' => null, 'source' => 'osm', 'precision' => 'derived'],
            ],
            'signals' => [['id' => 'o:sig', 'ref' => 'C1', 'pk_m' => 400, 'kind' => 'main', 'track' => 'o:1bis', 'direction' => 'up', 'source' => 'osm', 'precision' => 'derived']],
            'crossings' => [['id' => 'o:pn1', 'pk_m' => 650, 'kind' => 'level_crossing', 'ref' => '1', 'use' => 'road', 'source' => 'osm', 'precision' => 'derived']],
            'covered' => ['tracks' => [[0, 1000]], 'switches' => [[0, 1000]]],
        ]);
        $vff = self::source('vff', [
            'crossings' => [
                ['id' => 'v:pn1', 'pk_m' => 652, 'kind' => 'level_crossing', 'ref' => 'PN 1', 'source' => 'vff'],
                ['id' => 'v:pn2', 'pk_m' => 950, 'kind' => 'level_crossing', 'ref' => 'PN 2', 'source' => 'vff'],
            ],
        ]);

        return (new TrackLayout([$rinf, $osm, $vff]))->forLine(Line::factory()->create());
    }

    public function test_a_track_described_by_two_sources_is_served_once(): void
    {
        $json = $this->layout();
        $tracks = collect((array) $json['tracks'])->keyBy('id');

        // Only the RINF line tracks and the yard remain.
        $this->assertSame(['o:yard', 'r:v1', 'r:v1bis-a', 'r:v1bis-b', 'r:v1ter-a', 'r:v1ter-b', 'r:v2'], $tracks->keys()->sort()->values()->all());
        // What cited an absorbed track cites the kept one, the piece that carries its PK (V1BIS is cut at 500); a switch
        // whose two tracks became one leads nowhere.
        $switches = collect((array) $json['switches'])->keyBy('id');
        $this->assertSame(['r:v1', 'r:v1bis-a'], [$switches['o:sw1']['from_track'], $switches['o:sw1']['to_track']]);
        $this->assertSame(['r:v1bis-a', 'o:yard'], [$switches['o:sw2']['from_track'], $switches['o:sw2']['to_track']]);
        $this->assertSame(['r:v1bis-b', null], [$switches['o:sw3']['from_track'], $switches['o:sw3']['to_track']]);
        $this->assertSame('r:v1bis-a', $json['signals'][0]['track']);
    }

    public function test_the_pieces_of_one_track_share_a_row_in_their_real_lateral_order(): void
    {
        $lanes = collect((array) $this->layout()['tracks'])->pluck('lane', 'id');

        $this->assertSame([0, 1], [$lanes['r:v1'], $lanes['r:v2']]);
        // V1BIS, placed by the offset of OpenStreetMap, on one row above track 1; V1TER (no geometry) on one row too.
        $this->assertSame($lanes['r:v1bis-a'], $lanes['r:v1bis-b']);
        $this->assertLessThan(0, $lanes['r:v1bis-a']);
        $this->assertSame($lanes['r:v1ter-a'], $lanes['r:v1ter-b']);
        // The yard lies beyond V1BIS.
        $this->assertLessThan($lanes['r:v1bis-a'], $lanes['o:yard']);
    }

    public function test_one_level_crossing_and_the_tracks_of_a_speed(): void
    {
        $json = $this->layout();

        $this->assertSame(['o:pn1', 'v:pn2'], array_column($json['crossings'], 'id'));
        $this->assertSame([['V1', 'V2'], ['V1BIS']], array_column($json['strip']['speeds'], 'tracks'));
        $this->assertTrue(TrackLayout::sameRef('PN 12', '12'));
        $this->assertTrue(TrackLayout::sameRef('pn12', 'PN 12'));
        $this->assertFalse(TrackLayout::sameRef('PN 12', '1'));
        $this->assertFalse(TrackLayout::sameRef('PN', 'N'));
    }
}
