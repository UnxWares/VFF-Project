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

    public function test_a_platform_of_the_same_name_in_both_sources_gives_the_rinf_track_the_sncf_number(): void
    {
        // Lyon-Part-Dieu (FR0000003887): RINF platform edges and SNCF Réseau's « Liste des quais » (2020-05-26, ODbL).
        $rinf = fn (string $platform, string $track, string $number): array => ['ref' => $platform, 'track' => $track, 'number' => $number];
        $sncf = fn (string $platform, string $code): array => ['ref' => $platform, 'number' => TrackLayout::trackNumber($code)];
        $aliases = PlatformNames::acrossSources([
            $rinf('Quai A/B', 'track/507fbe6191', 'A'), $rinf('Quai A/B', 'track/c4ed9dd537', 'B'),
            $rinf('Quai B/C', 'track/c4ed9dd537', 'B'), $rinf('Quai B/C', 'track/e7603240e5', 'C'),
            $rinf('Quai C/D', 'track/3b93843cd7', 'D'), $rinf('Quai C/D', 'track/e7603240e5', 'C'),
            $rinf('Quai D/E', 'track/3b93843cd7', 'D'), $rinf('Quai D/E', 'track/2ac2ca7218', 'E'),
            $rinf('Quai E/F', 'track/e9025c5db6', 'F'), $rinf('Quai E/F', 'track/2ac2ca7218', 'E'),
            $rinf('Quai F/G', 'track/e9025c5db6', 'F'), $rinf('Quai F/G', 'track/3418973734', 'G'),
            $rinf('Quai J/K', 'track/ffc6f42418', 'K'), $rinf('Quai J/K', 'track/ed800b98c7', 'J'),
        ], [
            $sncf('Quai A/B', 'V2BIS'), $sncf('Quai A/B', 'B'), $sncf('Quai B/C', 'B'), $sncf('Quai B/C', 'C'),
            $sncf('Quai C/D', 'C'), $sncf('Quai C/D', 'V1BIS'), $sncf('Quai D/E', 'V1BIS'), $sncf('Quai D/E', 'E'),
            $sncf('Quai E/F', 'E'), $sncf('Quai E/F', 'V2'),
            // « Quai F/G » lists V2 alone, SNCF Réseau has no « Quai J/K »: no alias from them.
            $sncf('Quai F/G', 'V2'),
        ]);

        $this->assertSame(['track/507fbe6191' => ['2BIS'], 'track/3b93843cd7' => ['1BIS'], 'track/e9025c5db6' => ['2']], $aliases);
    }

    public function test_no_alias_without_one_shared_track_or_with_two_answers(): void
    {
        $rinf = fn (string $platform, string $track, string $number): array => ['ref' => $platform, 'track' => $track, 'number' => $number];
        $sncf = fn (string $platform, string $number): array => ['ref' => $platform, 'number' => $number];
        // Bordeaux-St-Jean « Quai 1 »: three RINF tracks (1 of 570000, 1 of 655000, A), more SNCF Réseau numbers: nothing.
        $this->assertSame([], PlatformNames::acrossSources(
            [$rinf('Quai 1', 't/1a', '1'), $rinf('Quai 1', 't/1b', '1'), $rinf('Quai 1', 't/a', 'A')],
            [$sncf('Quai 1', '1'), $sncf('Quai 1', '2'), $sncf('Quai 1', 'A')],
        ));
        // No track in common (both differ): no guess; both the same: nothing to learn.
        $this->assertSame([], PlatformNames::acrossSources([$rinf('Quai 3/4', 't/3', '3'), $rinf('Quai 3/4', 't/4', '4')],
            [$sncf('Quai 3/4', '13'), $sncf('Quai 3/4', '14')]));
        $this->assertSame([], PlatformNames::acrossSources([$rinf('Quai 3/4', 't/3', '3'), $rinf('Quai 3/4', 't/4', '4')],
            [$sncf('Quai 3/4', '3'), $sncf('Quai 3/4', '4')]));
        // One track given two numbers by two platforms: none.
        $this->assertSame([], PlatformNames::acrossSources(
            [$rinf('Quai A/B', 't/a', 'A'), $rinf('Quai A/B', 't/b', 'B'), $rinf('Quai Z/A', 't/z', 'Z'), $rinf('Quai Z/A', 't/a', 'A')],
            [$sncf('Quai A/B', '2BIS'), $sncf('Quai A/B', 'B'), $sncf('Quai Z/A', 'Z'), $sncf('Quai Z/A', '1')],
        ));
    }
}
