<?php

declare(strict_types=1);

namespace App\Services\TrackLayout;

use App\Models\Line;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source `osm` of the track layout: the OpenStreetMap tracks and track nodes attached to the line by
 * vff:import-osm-track-detail (tables osm_tracks and osm_track_nodes, ODbL).
 *
 * - Tracks: every attached way (or stretch of way) with a PK range: running tracks with their tagged number,
 *   loops, sidings, yards, crossovers and spurs; position `derived` (PK computed by VFF on the line chainage) or
 *   `approximate` (long gap between sourced PKs, PK inherited from a neighbouring part of the trace). The lateral
 *   offset (`offset_m`) and the tracks a crossover connects (`connects`) feed LaneAssigner and are removed from
 *   the response. `station_slug`: the station of the line the track serves (within 600 m).
 * - Switches: tagged ones (type from `railway:switch`) and untagged nodes where three track ends or more meet
 *   (type null): the track the switch stands on (toe side) and the track it leads to when it belongs to this line,
 *   the facing direction (up: trains running towards increasing PKs meet the switch points first).
 * - Signals (category `kind`, form `form`, number, track, direction), level crossings (road or pedestrian),
 *   diamond crossings (`railway_crossing`), buffer stops (track) and milestones (`label` as written on the post).
 *   PK `exact` when the node carries the PK of this line in OSM (`railway:position:exact`, a milestone whose
 *   number agrees with the chainage).
 * - Coverage: `tracks` and `switches` over the PK ranges of the running tracks (gaps under COVER_GAP_M bridged):
 *   where OSM draws the tracks it also draws where they split. Signals, level crossings, buffer stops and
 *   milestones are given without a coverage claim: OSM maps them unevenly.
 */
final class OsmTrackLayoutSource implements TrackLayoutSource
{
    public const LICENCE = 'ODbL 1.0';

    public const URL = 'https://www.openstreetmap.org/copyright';

    public const ATTRIBUTION = '© OpenStreetMap contributors';

    /** A track whose PK span exceeds SPAN_RATIO times its length plus SPAN_SLACK_M has a broken chainage: left out. */
    public const SPAN_RATIO = 2;

    public const SPAN_SLACK_M = 2000;

    /** Gaps between running tracks bridged by the coverage (switch zones, unmapped short ways). */
    public const COVER_GAP_M = 200;

    public function key(): string
    {
        return 'osm';
    }

    public function part(Line $line, ?int $fromM = null, ?int $toM = null): TrackLayoutPart
    {
        // Code deployed before its migration: no OSM layout yet, never an error.
        if (! Schema::hasTable('osm_tracks') || ! Schema::hasTable('osm_track_nodes') || ! Schema::hasTable('osm_track_imports')) {
            return TrackLayoutPart::empty();
        }
        $rows = DB::table('osm_tracks as t')->leftJoin('stations as s', 's.id', '=', 't.station_id')
            ->where('t.line_id', $line->id)->whereNotNull('t.pk_from_m')
            // Broken chainage (496 tracks on 40 lines at the import of 24/11/2026: a 602 m yard track placed from PK 1+516
            // to PK 523+044 of Paris - Marseille): left out rather than stretching the layout over the whole line.
            ->whereRaw('(t.length_m IS NULL OR t.pk_to_m - t.pk_from_m <= GREATEST(? * t.length_m, t.length_m + ?))', [self::SPAN_RATIO, self::SPAN_SLACK_M])
            ->when($fromM !== null, fn ($q) => $q->where('t.pk_to_m', '>=', $fromM))
            ->when($toM !== null, fn ($q) => $q->where('t.pk_from_m', '<=', $toM))
            ->orderBy('t.pk_from_m')->orderBy('t.osm_way_id')->orderBy('t.part')
            ->get(['t.osm_way_id', 't.part', 't.kind', 't.ref', 't.pk_from_m', 't.pk_to_m', 't.pk_precision', 't.length_m', 't.offset_m',
                't.electrified', 't.maxspeed_kmh', 't.start_node_id', 't.end_node_id', 's.slug as station_slug']);
        if ($rows->isEmpty()) {
            return TrackLayoutPart::empty();
        }
        $nodes = DB::table('osm_track_nodes')->where('line_id', $line->id)->whereNotNull('pk_m')
            ->whereIn('kind', ['switch', 'railway_crossing', 'level_crossing', 'crossing', 'signal', 'buffer_stop', 'milestone'])
            ->when($fromM !== null, fn ($q) => $q->where('pk_m', '>=', $fromM))
            ->when($toM !== null, fn ($q) => $q->where('pk_m', '<=', $toM))
            ->orderBy('pk_m')->orderBy('osm_node_id')
            ->get(['osm_node_id', 'kind', 'subtype', 'detail', 'ref', 'pk_m', 'pk_precision', 'way_id', 'to_way_id', 'direction']);

        /** @var array<int, list<array{0: string, 1: int, 2: int}>> $pieces way id => [track id, from, to] */
        $pieces = [];
        foreach ($rows as $r) {
            $pieces[(int) $r->osm_way_id][] = [self::trackId((int) $r->osm_way_id, (int) $r->part), (int) $r->pk_from_m, (int) $r->pk_to_m];
        }
        $track = function (?int $wayId, ?int $pk) use ($pieces): ?string {
            if ($wayId === null || ! isset($pieces[$wayId])) {
                return null;
            }
            foreach ($pieces[$wayId] as [$id, $a, $b]) {
                if ($pk !== null && $pk >= $a - 5 && $pk <= $b + 5) {
                    return $id;
                }
            }

            return $pieces[$wayId][0][0];
        };

        $switches = [];
        $signals = [];
        $crossings = [];
        $bufferStops = [];
        $milestones = [];
        $switchWays = [];
        foreach ($nodes as $n) {
            $id = 'osm:node:'.$n->osm_node_id;
            $pk = (int) $n->pk_m;
            $precision = (string) $n->pk_precision;
            $way = $n->way_id === null ? null : (int) $n->way_id;
            $toWay = $n->to_way_id === null ? null : (int) $n->to_way_id;
            switch ($n->kind) {
                case 'switch':
                    $switches[] = ['id' => $id, 'ref' => $n->ref, 'pk_m' => $pk, 'kind' => $n->subtype, 'from_track' => $track($way, $pk),
                        'to_track' => $track($toWay, $pk), 'facing' => $n->direction, 'source' => 'osm', 'precision' => $precision];
                    $switchWays[(int) $n->osm_node_id] = array_values(array_filter([$way, $toWay]));
                    break;
                case 'signal':
                    $signals[] = ['id' => $id, 'ref' => $n->ref, 'pk_m' => $pk, 'kind' => $n->subtype, 'form' => $n->detail,
                        'track' => $track($way, $pk), 'direction' => $n->direction, 'source' => 'osm', 'precision' => $precision];
                    break;
                case 'buffer_stop':
                    $bufferStops[] = ['id' => $id, 'pk_m' => $pk, 'track' => $track($way, $pk), 'source' => 'osm', 'precision' => $precision];
                    break;
                case 'milestone':
                    $milestones[] = ['id' => $id, 'pk_m' => $pk, 'label' => $n->detail, 'source' => 'osm', 'precision' => $precision];
                    break;
                case 'railway_crossing':
                    $crossings[] = ['id' => $id, 'pk_m' => $pk, 'kind' => 'railway_crossing', 'ref' => $n->ref, 'tracks' => array_values(array_filter([$track($way, $pk), $track($toWay, $pk)])),
                        'source' => 'osm', 'precision' => $precision];
                    break;
                default:
                    $crossings[] = ['id' => $id, 'pk_m' => $pk, 'kind' => 'level_crossing', 'ref' => $n->ref,
                        'use' => $n->kind === 'crossing' ? 'pedestrian' : 'road', 'source' => 'osm', 'precision' => $precision];
            }
        }

        $tracks = [];
        $running = [];
        foreach ($rows as $r) {
            $wayId = (int) $r->osm_way_id;
            $from = (int) $r->pk_from_m;
            $to = (int) $r->pk_to_m;
            $connects = [];
            if ($r->kind === 'crossover') {
                foreach ([$r->start_node_id, $r->end_node_id] as $node) {
                    foreach ($node === null ? [] : $switchWays[(int) $node] ?? [] as $other) {
                        if ($other !== $wayId && ($id = $track($other, null)) !== null) {
                            $connects[] = $id;
                        }
                    }
                }
            }
            $tracks[] = [
                'id' => self::trackId($wayId, (int) $r->part), 'ref' => $r->ref, 'kind' => $r->kind, 'pk_from_m' => $from, 'pk_to_m' => $to,
                'op_id' => null, 'station_slug' => $r->station_slug, 'length_m' => (int) $r->length_m,
                'electrified' => $r->electrified === null ? null : (bool) $r->electrified, 'platform' => null,
                'max_speed_kmh' => $r->maxspeed_kmh === null ? null : (int) $r->maxspeed_kmh,
                'source' => 'osm', 'precision' => (string) $r->pk_precision,
                'offset_m' => $r->offset_m === null ? null : (float) $r->offset_m, 'connects' => array_values(array_unique($connects)),
            ];
            if ($r->kind === 'running') {
                $running[] = [$from, $to];
            }
        }
        $covered = self::bridge(TrackLayout::union($running), self::COVER_GAP_M);

        return new TrackLayoutPart(
            source: self::sourceMeta(),
            tracks: $tracks,
            switches: $switches,
            signals: $signals,
            crossings: $crossings,
            covered: ['tracks' => $covered, 'switches' => $covered],
            bufferStops: $bufferStops,
            milestones: $milestones,
        );
    }

    /** Track id of a way (or of its stretch on the line). */
    public static function trackId(int $wayId, int $part): string
    {
        return 'osm:way:'.$wayId.($part > 0 ? ':'.$part : '');
    }

    /** @return array{key: string, label: string, licence: string, dataset: ?string, fetched_at: ?string, url: ?string, attribution: string, licence_url: string} */
    public static function sourceMeta(): array
    {
        $import = DB::table('osm_track_imports')->orderByDesc('id')->first(['file', 'data_at']);

        return ['key' => 'osm', 'label' => 'OpenStreetMap', 'licence' => self::LICENCE,
            'dataset' => $import === null ? null : 'Geofabrik France extract ('.$import->file.')',
            'fetched_at' => $import?->data_at === null ? null : substr((string) $import->data_at, 0, 10),
            'url' => self::URL, 'attribution' => self::ATTRIBUTION, 'licence_url' => 'https://opendatacommons.org/licenses/odbl/1-0/'];
    }

    /**
     * Ranges joined across gaps shorter than $gap.
     *
     * @param  list<array{0: int, 1: int}>  $ranges  sorted, disjoint
     * @return list<array{0: int, 1: int}>
     */
    private static function bridge(array $ranges, int $gap): array
    {
        $out = [];
        foreach ($ranges as $r) {
            $last = count($out) - 1;
            if ($last >= 0 && $gap >= $r[0] - $out[$last][1]) {
                $out[$last][1] = max($out[$last][1], $r[1]);
            } else {
                $out[] = $r;
            }
        }

        return $out;
    }
}
