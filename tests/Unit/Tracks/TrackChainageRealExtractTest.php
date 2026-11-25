<?php

namespace Tests\Unit\Tracks;

use App\Services\Tracks\Osm\OsmTrackExtract;
use App\Services\Tracks\Osm\TrackAttacher;
use App\Services\Tracks\Osm\TrackLine;
use App\Services\Tracks\Osm\TrackNetwork;
use App\Services\Tracks\TrackChainage;
use App\Services\Tracks\TrackGeometry;
use PHPUnit\Framework\TestCase;

/**
 * PK of the OpenStreetMap tracks on two real places where the calibration used to break (tests/Fixtures/Osm,
 * © OpenStreetMap contributors, ODbL; reference traces and station PKs from VFF and SNCF Reseau):
 *   - Sibelin yard on Paris - Marseille (830000): the yard switches carry `railway:position:exact` of their own
 *     numbering (1.500, 1.595...) on ways of the line, which made a run of anchors that grew with the line PK
 *     (522 km) and stretched the yard tracks from PK 1+516 to PK 523+044;
 *   - the helix north of Tende (946000): the trace folds back on itself, a track 1.4 km long took the PK of
 *     the turn below or above sample by sample and spanned 3 km.
 * The chainage is built as TrackNetwork::load builds it (stations with a sourced PK, weak; SNCF Reseau level
 * crossings and OpenStreetMap PKs of the line, strong).
 */
class TrackChainageRealExtractTest extends TestCase
{
    /**
     * @return array{0: TrackLine, 1: OsmTrackExtract}
     */
    private static function load(string $name, string $opl): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/Osm/'.$name), true, flags: JSON_THROW_ON_ERROR);
        $osm = OsmTrackExtract::fromFile(__DIR__.'/../../Fixtures/Osm/'.$opl);
        $code = $fixture['line']['code'];
        $anchors = [];
        foreach ($fixture['stations'] as $s) {
            if (in_array($s['pk_source'], TrackNetwork::CALIBRATED, true)) {
                $anchors[] = ['lon' => $s['lon'], 'lat' => $s['lat'], 'pk_m' => $s['pk_m'], 'max_m' => 500.0];
            }
        }
        foreach ($fixture['level_crossings'] as $c) {
            $anchors[] = ['lon' => $c['lon'], 'lat' => $c['lat'], 'pk_m' => $c['pk_m'], 'max_m' => 50.0, 'strong' => true];
        }
        array_push($anchors, ...(TrackAttacher::osmAnchors($osm)[$code] ?? []));
        $chainage = new TrackChainage($fixture['parts'], $anchors);
        $stations = [];
        foreach ($fixture['stations'] as $i => $s) {
            if ($chainage->locate($s['lon'], $s['lat'], 1000.0) !== null) {
                $stations[] = ['id' => $i + 1, 'lon' => $s['lon'], 'lat' => $s['lat']];
            }
        }

        return [new TrackLine($fixture['line']['id'], $code, $fixture['parts'], $chainage, $stations), $osm];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tracks(TrackLine $line, OsmTrackExtract $osm): array
    {
        return (new TrackAttacher($osm, [$line], [$line->code => true]))->attach()['tracks'];
    }

    public function test_the_yard_switches_numbered_from_their_own_origin_do_not_calibrate_the_line(): void
    {
        [$line, $osm] = self::load('chainage-830000-sibelin.json', 'track-detail-sibelin.opl');

        // The OpenStreetMap PKs of the yard (1.500, 1.595...) are among the anchors of the line...
        $own = array_column(TrackAttacher::osmAnchors($osm)[(string) $line->code] ?? [], 'pk_m');
        $this->assertContains(1500, $own);
        $this->assertContains(522761, $own);
        // ...but the PK of Sibelin stays the PK of the line (522,862 at the station, SNCF Reseau).
        $at = $line->chainage->locate(4.8439466, 45.6520029, 500.0);
        $this->assertEqualsWithDelta(522_862, $at['pk_m'], 300);

        $tracks = self::tracks($line, $osm);
        $this->assertGreaterThan(50, count($tracks));
        foreach ($tracks as $t) {
            if ($t['pk_from_m'] === null) {
                continue;
            }
            // Every track lies around Sibelin (Feyzin 521,277 - Serezin 526,418) and spans about its length.
            $this->assertGreaterThan(519_000, $t['pk_from_m'], 'way '.$t['osm_way_id']);
            $this->assertLessThan(528_000, $t['pk_to_m'], 'way '.$t['osm_way_id']);
            $this->assertLessThanOrEqual(1.3 * $t['length_m'] + 250, $t['pk_to_m'] - $t['pk_from_m'], 'way '.$t['osm_way_id']);
        }
        // The yard track that spanned PK 1+516 to 523+044 (602 m long).
        $yard = array_values(array_filter($tracks, fn (array $t): bool => $t['osm_way_id'] === 308534901));
        $this->assertCount(1, $yard);
        $this->assertGreaterThan(522_000, $yard[0]['pk_from_m']);
        $this->assertLessThan(524_000, $yard[0]['pk_to_m']);
    }

    public function test_a_track_on_a_helix_keeps_to_one_turn_of_the_trace(): void
    {
        [$line, $osm] = self::load('chainage-946000-tende.json', 'track-detail-tende.opl');
        $tracks = self::tracks($line, $osm);
        $byWay = [];
        foreach ($tracks as $t) {
            $byWay[$t['osm_way_id']][] = $t;
        }

        // The 1.4 km track of the helix and its neighbour: one piece each, a PK span close to their length,
        // between Tende (50,443) and Vievola (42,262).
        foreach ([88816630, 164520633] as $way) {
            $this->assertArrayHasKey($way, $byWay);
            foreach ($byWay[$way] as $t) {
                $this->assertNotNull($t['pk_from_m']);
                $this->assertLessThanOrEqual(1.3 * $t['length_m'] + 250, $t['pk_to_m'] - $t['pk_from_m'], 'way '.$way);
                $this->assertGreaterThan(42_000, $t['pk_from_m']);
                $this->assertLessThan(51_000, $t['pk_to_m']);
            }
        }
        // Sample by sample, the nearest turn of the trace gives PKs kilometres apart along that track.
        $c = TrackGeometry::densify($osm->ways[88816630]['coords'], 25.0)[0];
        $pks = [];
        for ($i = 0; $i < intdiv(count($c), 2); $i++) {
            $pks[] = $line->chainage->locate($c[2 * $i], $c[2 * $i + 1], 800.0)['pk_m'] ?? null;
        }
        $pks = array_values(array_filter($pks, fn (?int $pk): bool => $pk !== null));
        $this->assertGreaterThan(2_000, max($pks) - min($pks));
    }
}
