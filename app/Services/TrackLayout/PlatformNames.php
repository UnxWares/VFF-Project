<?php

declare(strict_types=1);

namespace App\Services\TrackLayout;

/**
 * Platform names of the RINF (`rinf_platforms.ref`) read for the track layout.
 *
 * - key(): one key per platform of an operational point (« FR0000002153|quai3/4 »): the platform edges of one island
 *   platform share it, so the tracks that border it are neighbours in the layout (LaneAssigner).
 * - names(): the tracks a platform name lists (« Quai V1/V1BIS » lists V1 and V1BIS, « Quai 20/21 - 50000000050 »
 *   lists 20 and 21, « Quai VD » lists VD), as track numbers (TrackLayout::trackNumber).
 * - aliases(): a track of an island platform that bears another number than the one the platform name gives it. At
 *   Brétigny the RINF calls the two tracks of « Quai V1/V1BIS » « Voie V1 » and « 3 »: the track « 3 » is V1BIS
 *   (likewise « 4 » of « Quai V2/V2BIS »). Only an island platform whose name lists two tracks, one of which is
 *   the other edge's track, gives an alias: « Quai 1 » of Paris-Austerlitz Banlieue (tracks 4S and V2S) gives none.
 */
final class PlatformNames
{
    /** Key of a platform of an operational point: point, then the name in lower case without spaces. */
    public static function key(string $uopid, string $ref): string
    {
        return $uopid.'|'.mb_strtolower((string) preg_replace('/\s+/u', '', $ref));
    }

    /**
     * Track numbers a platform name lists: « Quai 3/4 » gives 3 and 4, « Quai V1/V1BIS » gives 1 and 1BIS (track
     * numbers drop the V before a digit), « Quai VD » gives VD; a name that lists no track (« Quai A », « Quai 1 »
     * of a single track named otherwise) gives its own word, which matches nothing.
     *
     * @return list<string>
     */
    public static function names(string $ref): array
    {
        $ref = trim((string) preg_replace(['/^\s*quais?\s+/iu', '/\s+-\s+\d+\s*$/u'], '', $ref));
        $names = [];
        foreach (explode('/', $ref) as $part) {
            $number = TrackLayout::trackNumber(trim($part));
            if ($number !== '') {
                $names[] = $number;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Other numbers of the tracks of island platforms (see the class). Each edge: platform key and name, track key
     * and number.
     *
     * @param  list<array{platform: string, ref: string, track: string, number: string}>  $edges
     * @return array<string, list<string>> track key => its other numbers
     */
    public static function aliases(array $edges): array
    {
        $byPlatform = [];
        $refs = [];
        foreach ($edges as $e) {
            $byPlatform[$e['platform']][$e['track']] = $e['number'];
            $refs[$e['platform']] = $e['ref'];
        }
        $aliases = [];
        foreach ($byPlatform as $platform => $tracks) {
            $names = self::names($refs[$platform]);
            if (count($names) !== 2 || count($tracks) !== 2) {
                continue;
            }
            $keys = array_keys($tracks);
            foreach ([[$keys[0], $keys[1]], [$keys[1], $keys[0]]] as [$self, $other]) {
                $mine = array_values(array_filter($names, fn (string $n): bool => TrackLayout::sameNumber($tracks[$self], $n)));
                $theirs = array_values(array_filter($names, fn (string $n): bool => TrackLayout::sameNumber($tracks[$other], $n)));
                if ($mine === [] && count($theirs) === 1) {
                    $rest = array_values(array_diff($names, $theirs));
                    if (count($rest) === 1) {
                        $aliases[(string) $self][] = $rest[0];
                    }
                }
            }
        }

        return array_map(fn (array $list): array => array_values(array_unique($list)), $aliases);
    }
}
