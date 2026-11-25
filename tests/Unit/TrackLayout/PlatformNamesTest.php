<?php

namespace Tests\Unit\TrackLayout;

use App\Services\TrackLayout\PlatformNames;
use App\Services\TrackLayout\TrackLayout;
use PHPUnit\Framework\TestCase;

/**
 * Track numbers written by two sources and platform names of the RINF (references as read on 25/11/2026: RINF © ERA,
 * graph of SNCF Réseau; OpenStreetMap © contributors, ODbL): « VDO de Paris-10 à St-Denis » of the RINF is « DO » of
 * OpenStreetMap at Paris-Nord, « RD de Paris » is « VRD », « Quai VD » of Cognac borders the track « D »; the RINF
 * calls « 3 » and « 4 » the tracks V1BIS and V2BIS of Brétigny, which only the names of their platforms say.
 */
class PlatformNamesTest extends TestCase
{
    public function test_a_letter_code_is_the_same_track_with_or_without_its_v(): void
    {
        // Paris-Nord, line 272000: RINF line tracks and the OpenStreetMap tracks along them.
        foreach ([['VDO de Paris-10 à St-Denis', 'DO'], ['VRO de Paris-10 à St-Denis', 'RO'], ['VDM de Paris-18 à St-Denis', 'DM'],
            ['VLC1 de Paris-18', 'LC1'], ['VVC de Paris-18 à St-Denis', 'VC'], ['RD', 'VRD'], ['VM', 'M']] as [$rinf, $osm]) {
            $this->assertTrue(TrackLayout::sameNumber(TrackLayout::trackNumber($rinf), TrackLayout::trackNumber($osm)), "$rinf / $osm");
            $this->assertTrue(TrackLayout::sameNumber(TrackLayout::trackNumber($osm), TrackLayout::trackNumber($rinf)), "$osm / $rinf");
        }
        // A number keeps its one form (« V3 » is already 3), and two different codes stay apart.
        $this->assertSame(['3'], TrackLayout::numberForms(TrackLayout::trackNumber('V3')));
        $this->assertFalse(TrackLayout::sameNumber('3', '13'));
        $this->assertFalse(TrackLayout::sameNumber('DO', 'RO'));
        $this->assertFalse(TrackLayout::sameNumber('VC', 'VD'));
        $this->assertFalse(TrackLayout::sameNumber('VVC', 'C'));
        $this->assertFalse(TrackLayout::sameNumber('', ''));
        $this->assertTrue(TrackLayout::sameNumber('D', TrackLayout::trackNumber('VD')));
    }

    public function test_platform_names_list_their_tracks(): void
    {
        $this->assertSame(['3', '4'], PlatformNames::names('Quai 3/4'));
        $this->assertSame(['1', '1BIS'], PlatformNames::names('Quai V1/V1BIS'));
        $this->assertSame(['20', '21'], PlatformNames::names('Quai 20/21 - 50000000050'));
        $this->assertSame(['VD'], PlatformNames::names('Quai VD'));
        // The two edges of an island platform share a key, whatever the spaces.
        $this->assertSame(PlatformNames::key('FR0000002153', 'Quai 3/4'), PlatformNames::key('FR0000002153', 'Quai 3 / 4'));
        $this->assertNotSame(PlatformNames::key('FR0000002153', 'Quai 3/4'), PlatformNames::key('FR0000002805', 'Quai 3/4'));
    }

    public function test_the_name_of_an_island_platform_gives_its_other_track_a_number(): void
    {
        // Brétigny (FR0000002724): « Quai V1/V1BIS » borders « Voie V1 » and « 3 », « Quai V2/V2BIS » « Voie V2 » and « 4 ».
        $edge = fn (string $platform, string $track, string $number): array => ['platform' => PlatformNames::key('FR0000002724', $platform),
            'ref' => $platform, 'track' => $track, 'number' => $number];
        $aliases = PlatformNames::aliases([
            $edge('Quai V1/V1BIS', 'track/ac0a010e29', '1'), $edge('Quai V1/V1BIS', 'track/bc31541c57', '3'),
            $edge('Quai V2/V2BIS', 'track/0d88d18b29', '2'), $edge('Quai V2/V2BIS', 'track/2600ffa5c4', '4'),
            // Paris-Nord: both tracks of « Quai 3/4 » bear their names; Paris-Austerlitz Banlieue: « Quai 1 » names no track.
            $edge('Quai 3/4', 'track/ccaecc72a9', '3'), $edge('Quai 3/4', 'track/9364257179', '4'),
            $edge('Quai 1', 'track/4s', '4S'), $edge('Quai 1', 'track/v2s', '2S'),
        ]);

        $this->assertSame(['track/bc31541c57' => ['1BIS'], 'track/2600ffa5c4' => ['2BIS']], $aliases);
    }
}
