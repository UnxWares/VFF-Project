<?php

namespace Tests\Unit\Tracks;

use App\Services\Tracks\Osm\OsmTrackExtract;
use App\Services\Tracks\Osm\OsmTrackTags;
use PHPUnit\Framework\TestCase;

/**
 * Reading of the OPL file of bin/osm-track-detail (lines taken from the real France extract of 23/11/2026,
 * © OpenStreetMap contributors, ODbL) and of the tags of the track layout: escapes, node locations written in
 * the ways, line codes of the ways and of their relations, bounding box, date of the data, track numbers never
 * guessed, PKs, signal categories.
 */
class OsmTrackExtractTest extends TestCase
{
    private const LINES = [
        'n135381 v8 dV c0 t2024-08-17T14:43:56Z i0 u Trailway=switch x5.7594187 y45.1920285',
        'n4290857038 v3 dV c0 t2025-01-01T00:00:00Z i0 u Tname=Luzenac-Garanou,railway=stop,railway:position:exact=114.998 x1.7530809 y42.7643',
        'n1 v1 dV c0 t2020-01-01T00:00:00Z i0 u Tamenity=bench x1.0 y42.0',
        'w2560235 v41 dV c0 t2026-11-16T13:58:56Z i0 u Tname=Raccordement%20%de%20%Lyon-Saint-Clair,railway=rail,railway:track_ref=2,ref=752330,ref:FR:SNCF_Reseau=752330%2c%1%2c%V2%2c%380.540%2c%394.719,usage=main Nn11319019x4.8601y45.7901,n1038665849x4.8605y45.7905,n135381x4.861y45.791',
        'w2 v1 dV c0 t2020-01-01T00:00:00Z i0 u Trailway=abandoned Nn5x4.0y45.0,n6x4.1y45.1',
        'w3 v1 dV c0 t2020-01-01T00:00:00Z i0 u Trailway=rail,service=siding Nn7x4.0y45.0,n8,n9x4.2y45.2',
        'r38759 v62 dV c0 t2025-09-15T15:51:32Z i0 u Tref=676%20%000,route=railway,type=route Mw3@,w2560235@,n135381@',
    ];

    public function test_ways_keep_their_locations_and_the_tags_of_the_layout(): void
    {
        $osm = OsmTrackExtract::fromLines(self::LINES);

        $way = $osm->ways[2560235];
        $this->assertSame([11319019, 1038665849, 135381], $way['nodes']);
        $this->assertSame([4.8601, 45.7901, 4.8605, 45.7905, 4.861, 45.791], $way['coords']);
        $this->assertSame('Raccordement de Lyon-Saint-Clair', $way['tags']['name']);
        $this->assertSame('752330,1,V2,380.540,394.719', $way['tags']['ref:FR:SNCF_Reseau']);
        // Abandoned ways are not tracks; a node without location is left out of its way.
        $this->assertArrayNotHasKey(2, $osm->ways);
        $this->assertSame([7, 9], $osm->ways[3]['nodes']);
        // Tagged nodes of the layout and nodes carrying a PK; not the bench.
        $this->assertSame([135381, 4290857038], array_keys($osm->nodes));
        $this->assertSame('2026-11-16T13:58:56Z', $osm->latest);
    }

    public function test_line_codes_of_ways_and_relations(): void
    {
        $osm = OsmTrackExtract::fromLines(self::LINES);

        $this->assertSame(['752330', '676000'], $osm->wayCodes(2560235));
        $this->assertSame(['676000'], $osm->wayCodes(3));
        $this->assertSame('001000', OsmTrackExtract::lineCode('1000'));
        $this->assertSame('090000', OsmTrackExtract::lineCode('90000'));
        $this->assertNull(OsmTrackExtract::lineCode('L96'));
        $this->assertNull(OsmTrackExtract::lineCode('12'));
    }

    public function test_bounding_box_keeps_the_ways_that_enter_it(): void
    {
        $osm = OsmTrackExtract::fromLines(self::LINES, [4.15, 45.15, 4.5, 45.5]);

        $this->assertSame([3], array_keys($osm->ways));
        $this->assertSame([], $osm->nodes);
    }

    public function test_track_numbers_are_normalised_never_guessed(): void
    {
        $this->assertSame('1', OsmTrackTags::trackRef(['railway:track_ref' => 'V1']));
        $this->assertSame('U', OsmTrackTags::trackRef(['railway:track_ref' => 'UNIQUE']));
        $this->assertSame('2bis', OsmTrackTags::trackRef(['railway:track_ref' => '2BIS']));
        // « 2 bis » (11 tracks of the import of 24/11/2026) is the same number written with a space.
        $this->assertSame('2bis', OsmTrackTags::trackRef(['railway:track_ref' => '2 bis']));
        $this->assertSame('7ter', OsmTrackTags::trackRef(['railway:track_ref' => '7 ter']));
        $this->assertSame('A', OsmTrackTags::trackRef(['railway:track_ref' => 'A']));
        // A line code put in the track number by mistake, or no number: null.
        $this->assertNull(OsmTrackTags::trackRef(['railway:track_ref' => '570000']));
        $this->assertNull(OsmTrackTags::trackRef([]));
    }

    public function test_kinds_positions_and_signals(): void
    {
        $this->assertSame('running', OsmTrackTags::kind(['railway' => 'rail', 'usage' => 'main']));
        $this->assertSame('crossover', OsmTrackTags::kind(['service' => 'crossover']));
        $this->assertSame('siding', OsmTrackTags::kind(['service' => 'siding']));
        $this->assertSame(546_707, OsmTrackTags::position('546.707'));
        $this->assertSame(22_000, OsmTrackTags::position('22'));
        $this->assertSame(12_300, OsmTrackTags::position('12,3'));
        $this->assertSame(22_500, OsmTrackTags::position('22+500'));
        $this->assertNull(OsmTrackTags::position('mi:12'));
        $this->assertSame(['main', 'FR:C', '118098'], OsmTrackTags::signal([
            'railway' => 'signal', 'railway:signal:main' => 'FR:C', 'railway:signal:main:ref' => '118098',
            'railway:signal:speed_limit' => 'FR:TIV-D', 'railway:signal:direction' => 'forward',
        ]));
        $this->assertSame([null, null, null], OsmTrackTags::signal(['railway' => 'signal', 'source' => 'BDOrtho IGN']));
        $this->assertSame('double_slip', OsmTrackTags::switchKind(['railway:switch' => 'double_slip']));
        $this->assertSame('simple', OsmTrackTags::switchKind(['railway:switch' => 'wye']));
        $this->assertTrue(OsmTrackTags::electrified(['electrified' => 'contact_line']));
        $this->assertNull(OsmTrackTags::electrified([]));
        $this->assertSame(1500, OsmTrackTags::integer(['voltage' => '1500;25000'], 'voltage', 50_000));
        $this->assertNull(OsmTrackTags::integer(['maxspeed' => '90 mph'], 'maxspeed', 400));
    }
}
