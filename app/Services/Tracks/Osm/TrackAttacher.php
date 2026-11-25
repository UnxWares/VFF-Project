<?php

declare(strict_types=1);

namespace App\Services\Tracks\Osm;

use App\Services\Tracks\TrackGeometry;

/**
 * Attaches the OpenStreetMap tracks and track nodes to the VFF lines in service, with their PK and topology.
 *
 * A way carrying the code of known lines (`ref`, `ref:FR:SNCF_Reseau`, route=railway relation) belongs to those
 * lines only, whole, when the reference trace of one of them passes within CODED_M of it; a way coded for a
 * known line that is not in service here is never given to another line. Any other way is cut into the
 * stretches that are:
 *   - within CORRIDOR_M of the trace of a line (the nearest line, and every line within TIE_M of it: shared
 *     tracks), running tracks only when they run along it (angle under PARALLEL_DEG, never a line crossing it);
 *   - or within STATION_M of a station of a line: always for service tracks (yards, sidings, spurs,
 *     crossovers of the station), for running tracks only when no line runs within CORRIDOR_M (platform tracks
 *     of the large stations).
 * Stretches shorter than MIN_PIECE_M are dropped.
 *
 * Each piece is placed as a whole on the chainage of the line (TrackChainage::chain: the portion of the trace that
 * keeps the PK continuous along the track, not the nearest one sample by sample) and cut where the PK still jumps
 * (a change of numbering under the track); it gets its PK range and its median lateral offset, the station of the
 * line it serves (nearest within STATION_M), and a kind (OsmTrackTags::kind; a siding connected at both ends,
 * through other sidings joined end to end, is a loop). Nodes on the pieces become switches (the track they sit
 * on and the track they lead to, facing up or down), diamond crossings, buffer stops, level crossings,
 * signals (direction up or down) and milestones; a node that carries the PK of a way of this line keeps it when
 * it agrees with the chainage within EXACT_AGREE_M (`exact`).
 */
final class TrackAttacher
{
    public const CODED_M = 200.0;

    public const CORRIDOR_M = 30.0;

    public const TIE_M = 8.0;

    public const PARALLEL_DEG = 35.0;

    public const STATION_M = 600.0;

    public const MIN_PIECE_M = 15.0;

    public const SAMPLE_M = 25.0;

    public const EXACT_AGREE_M = 300.0;

    /** A milestone off the tracks is read on the nearest way within this distance. */
    public const MILESTONE_SNAP_M = 15.0;

    /** A signal mapped beside the track (not as a node of the way) is read on the nearest way within this distance. */
    public const SIGNAL_SNAP_M = 8.0;

    /** Beyond the length of track between two samples, a PK jump larger than this cuts the piece (change of numbering). */
    public const JUMP_SLACK_M = 200.0;

    /** Length along a way at which a branch of a switch is measured. */
    private const BRANCH_M = 15.0;

    private const CELL_DEG = 0.01;

    /** @var array<string, list<int>> line code => indexes in */
    private array $byCode = [];

    /** @var array<int, array<int, true>> grid cell => line indexes passing within STATION_M */
    private array $lineGrid = [];

    /** @var array<int, list<array{0: int, 1: int}>> grid cell => [line index, station index] */
    private array $stationGrid = [];

    /** @var array<int, int> node id => number of way ends and passing ways (2 each) */
    private array $degree = [];

    /** @var array<int, list<int>> node id => ways holding it */
    private array $nodeWays = [];

    /**
     * @param  list<TrackLine>  $lines
     * @param  array<string, true>  $knownCodes  codes of every VFF line, in service or not
     */
    public function __construct(private readonly OsmTrackExtract $osm, private readonly array $lines, private readonly array $knownCodes)
    {
        foreach ($lines as $li => $line) {
            if ($line->code !== null) {
                $this->byCode[$line->code][] = $li;
            }
            $pad = self::STATION_M / TrackGeometry::M_PER_DEG * 1.5;
            foreach ($line->parts as $c) {
                for ($i = 2, $n = count($c); $i + 1 < $n; $i += 2) {
                    $box = [min($c[$i - 2], $c[$i]) - $pad, min($c[$i - 1], $c[$i + 1]) - $pad, max($c[$i - 2], $c[$i]) + $pad, max($c[$i - 1], $c[$i + 1]) + $pad];
                    foreach (self::cells(...$box) as $key) {
                        $this->lineGrid[$key][$li] = true;
                    }
                }
            }
            foreach ($line->stations as $si => $s) {
                $this->stationGrid[self::cell($s['lon'], $s['lat'])][] = [$li, $si];
            }
        }
        $this->topology();
    }

    /**
     * Anchors of known PK carried by OpenStreetMap, by line code: nodes with `railway:position:exact` (or a
     * milestone `railway:position`) standing on ways of a single line code; a milestone beside the track is read
     * on the nearest way within MILESTONE_SNAP_M.
     *
     * @return array<string, list<array{lon: float, lat: float, pk_m: int, max_m: float, strong: bool}>>
     */
    public static function osmAnchors(OsmTrackExtract $osm): array
    {
        $nodeCodes = [];
        foreach ($osm->ways as $wayId => $way) {
            $codes = $osm->wayCodes($wayId);
            foreach ($way['nodes'] as $nodeId) {
                if (isset($osm->nodes[$nodeId])) {
                    $nodeCodes[$nodeId] = array_values(array_unique([...($nodeCodes[$nodeId] ?? []), ...$codes]));
                }
            }
        }
        $grid = null;
        $anchors = [];
        foreach ($osm->nodes as $nodeId => $node) {
            $tags = $node['tags'];
            $milestone = ($tags['railway'] ?? null) === 'milestone';
            $pk = OsmTrackTags::position($tags['railway:position:exact'] ?? ($milestone ? $tags['railway:position'] ?? null : null));
            if ($pk === null) {
                continue;
            }
            $codes = $nodeCodes[$nodeId] ?? null;
            if ($codes === null && $milestone) {
                $grid ??= self::wayGrid($osm);
                $snap = self::nearestWay($osm, $grid, $node['lon'], $node['lat'], self::MILESTONE_SNAP_M);
                $codes = $snap === null ? null : $osm->wayCodes($snap[0]);
            }
            if ($codes !== null && count($codes) === 1) {
                $anchors[$codes[0]][] = ['lon' => $node['lon'], 'lat' => $node['lat'], 'pk_m' => $pk, 'max_m' => 30.0, 'strong' => true];
            }
        }

        return $anchors;
    }

    /**
     * @param  list<int>|null  $onlyLineIds  lines whose tracks are returned (null: all)
     * @return array{tracks: list<array<string, mixed>>, nodes: list<array<string, mixed>>}
     */
    public function attach(?array $onlyLineIds = null): array
    {
        $wanted = $onlyLineIds === null ? null : array_flip($onlyLineIds);
        $pieces = [];
        foreach ($this->osm->ways as $wayId => $way) {
            foreach ($this->stretches($wayId, $way) as [$li, $from, $to, $how, $dense]) {
                if ($wanted !== null && ! isset($wanted[$this->lines[$li]->id])) {
                    continue;
                }
                foreach ($this->placed($li, $dense, $from, $to) as [$a, $b, $places]) {
                    $piece = $this->piece($li, $wayId, $way, $dense, $a, $b, $how, $places);
                    if ($piece !== null) {
                        $pieces[] = $piece;
                    }
                }
            }
        }
        $this->number($pieces);
        $this->loops($pieces);

        $nodes = [];
        foreach ($pieces as $piece) {
            foreach ($piece['node_ids'] as $nodeId => [$lon, $lat]) {
                $key = $piece['line_id'].':'.$nodeId;
                if (isset($nodes[$key])) {
                    continue;
                }
                $node = isset($this->osm->nodes[$nodeId])
                    ? $this->node($piece, $nodeId, $this->osm->nodes[$nodeId]['tags'], $lon, $lat)
                    : (($this->degree[$nodeId] ?? 0) >= 3 ? $this->node($piece, $nodeId, [], $lon, $lat) : null);
                if ($node !== null) {
                    $nodes[$key] = $node;
                }
            }
        }
        $this->snapped($pieces, $nodes);
        foreach ($pieces as &$piece) {
            unset($piece['node_ids'], $piece['node_pks'], $piece['line_index']);
        }

        return ['tracks' => $pieces, 'nodes' => array_values($nodes)];
    }

    /**
     * Milestones and signals mapped beside the track (not nodes of a way): read on the nearest way within
     * MILESTONE_SNAP_M or SIGNAL_SNAP_M, for each line that way is attached to.
     *
     * @param  list<array<string, mixed>>  $pieces
     * @param  array<string, array<string, mixed>>  $nodes
     */
    private function snapped(array $pieces, array &$nodes): void
    {
        $byWay = [];
        foreach ($pieces as $i => $p) {
            $byWay[$p['osm_way_id']][] = $i;
        }
        $grid = null;
        foreach ($this->osm->nodes as $nodeId => $n) {
            $kind = $n['tags']['railway'] ?? null;
            if (($kind !== 'milestone' && $kind !== 'signal') || isset($this->nodeWays[$nodeId])) {
                continue;
            }
            $grid ??= self::wayGrid($this->osm);
            $snap = self::nearestWay($this->osm, $grid, $n['lon'], $n['lat'], $kind === 'milestone' ? self::MILESTONE_SNAP_M : self::SIGNAL_SNAP_M);
            if ($snap === null) {
                continue;
            }
            foreach ($byWay[$snap[0]] ?? [] as $i) {
                $key = $pieces[$i]['line_id'].':'.$nodeId;
                if (! isset($nodes[$key])) {
                    $node = $this->node($pieces[$i], $nodeId, $n['tags'], $n['lon'], $n['lat'], $snap[1]);
                    if ($node !== null) {
                        $nodes[$key] = $node;
                    }
                }
            }
        }
    }

    /**
     * Stretches of a way to attach: [line index, first sample, last sample, how, densified coords].
     *
     * @param  array{tags: array<string, string>, nodes: list<int>, coords: list<float>}  $way
     * @return list<array{0: int, 1: int, 2: int, 3: string, 4: array{0: list<float>, 1: list<int>}}>
     */
    private function stretches(int $wayId, array $way): array
    {
        $dense = TrackGeometry::densify($way['coords'], self::SAMPLE_M);
        [$c] = $dense;
        $last = intdiv(count($c), 2) - 1;
        $codes = $this->osm->wayCodes($wayId);
        $known = array_values(array_filter($codes, fn (string $code): bool => isset($this->knownCodes[$code]) || isset($this->byCode[$code])));
        if ($known !== []) {
            $out = [];
            foreach ($known as $code) {
                foreach ($this->byCode[$code] ?? [] as $li) {
                    if ($this->near($li, $c, self::CODED_M)) {
                        $out[] = [$li, 0, $last, 'code', $dense];
                    }
                }
            }

            return $out;
        }

        $service = OsmTrackTags::kind($way['tags']) !== 'running';
        $cosMax = cos(deg2rad(self::PARALLEL_DEG));
        $member = [];
        for ($i = 0; $i <= $last; $i++) {
            $lon = $c[2 * $i];
            $lat = $c[2 * $i + 1];
            $dir = $i < $last
                ? TrackGeometry::direction($lon, $lat, $c[2 * $i + 2], $c[2 * $i + 3])
                : TrackGeometry::direction($c[2 * $i - 2], $c[2 * $i - 1], $lon, $lat);
            $near = [];
            foreach (array_keys($this->lineGrid[self::cell($lon, $lat)] ?? []) as $li) {
                $at = $this->lines[$li]->chainage->locate($lon, $lat, self::CORRIDOR_M);
                if ($at === null) {
                    continue;
                }
                $cos = abs($dir[0] * $at['direction'][0] + $dir[1] * $at['direction'][1]);
                if ($service || $cos >= $cosMax) {
                    $near[$li] = $at['distance_m'];
                }
            }
            if ($near !== []) {
                $min = min($near);
                $near = array_filter($near, fn (float $d): bool => $d <= $min + self::TIE_M);
            }
            $inStation = $service || $near === [] ? $this->stationLines($lon, $lat) : [];
            foreach (array_keys($near) as $li) {
                $member[$li][$i] = 'corridor';
            }
            foreach ($inStation as $li) {
                $member[$li][$i] ??= 'station';
            }
        }

        $out = [];
        foreach ($member as $li => $samples) {
            $start = $prev = (int) array_key_first($samples);
            $hows = [];
            foreach ($samples as $i => $how) {
                if ($i > $prev + 1) {
                    $out[] = [$li, $start, $prev, self::majority($hows), $dense];
                    $start = $i;
                    $hows = [];
                }
                $hows[] = $how;
                $prev = $i;
            }
            $out[] = [$li, $start, $prev, self::majority($hows), $dense];
        }

        return $out;
    }

    /** @param  list<string>  $hows */
    private static function majority(array $hows): string
    {
        $counts = array_count_values($hows);

        return ($counts['corridor'] ?? 0) >= ($counts['station'] ?? 0) ? 'corridor' : 'station';
    }

    /**
     * Lines of the stations within STATION_M.
     *
     * @return list<int>
     */
    private function stationLines(float $lon, float $lat): array
    {
        $found = [];
        $x = (int) floor($lon / self::CELL_DEG);
        $y = (int) floor($lat / self::CELL_DEG);
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                foreach ($this->stationGrid[self::key($x + $dx, $y + $dy)] ?? [] as [$li, $si]) {
                    $s = $this->lines[$li]->stations[$si];
                    if (TrackGeometry::metres($lon, $lat, $s['lon'], $s['lat']) <= self::STATION_M) {
                        $found[$li] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Whether the trace of the line passes within $max metres of the polyline (tested every few samples).
     *
     * @param  list<float>  $c
     */
    private function near(int $li, array $c, float $max): bool
    {
        $count = intdiv(count($c), 2);
        $step = max(1, intdiv($count, 8));
        for ($i = 0; $i < $count; $i += $step) {
            if ($this->lines[$li]->chainage->locate($c[2 * $i], $c[2 * $i + 1], $max) !== null) {
                return true;
            }
        }

        return $this->lines[$li]->chainage->locate($c[2 * $count - 2], $c[2 * $count - 1], $max) !== null;
    }

    /**
     * Places of the samples of a stretch on the chainage of the line, the track followed as a whole (TrackChainage::chain),
     * and the stretch cut where the PK still jumps between two samples (a change of the PK numbering under the
     * track): [first sample, last sample, places by sample].
     *
     * @param  array{0: list<float>, 1: list<int>}  $dense
     * @return list<array{0: int, 1: int, 2: array<int, array{pk_m: ?int, precision: ?string, offset_m: float}|null>}>
     */
    private function placed(int $li, array $dense, int $from, int $to): array
    {
        [$c] = $dense;
        $chain = $this->lines[$li]->chainage->chain(array_slice($c, 2 * $from, 2 * ($to - $from + 1)), self::STATION_M + self::CODED_M);
        $places = [];
        foreach ($chain as $k => $at) {
            $places[$from + $k] = $at;
        }
        $out = [];
        $start = $from;
        $prev = null;
        for ($i = $from; $i <= $to; $i++) {
            $pk = $places[$i]['pk_m'] ?? null;
            if ($pk === null) {
                continue;
            }
            if ($prev !== null) {
                $step = TrackGeometry::metres($c[2 * $prev], $c[2 * $prev + 1], $c[2 * $i], $c[2 * $i + 1]);
                if (abs($pk - (int) $places[$prev]['pk_m']) > 1.5 * $step + self::JUMP_SLACK_M) {
                    $out[] = [$start, $i - 1, $places];
                    $start = $i;
                }
            }
            $prev = $i;
        }
        $out[] = [$start, $to, $places];

        return $out;
    }

    /**
     * One attached piece of a way: geometry, PK range, offset, kind and the nodes it holds.
     *
     * @param  array{tags: array<string, string>, nodes: list<int>, coords: list<float>}  $way
     * @param  array{0: list<float>, 1: list<int>}  $dense
     * @param  array<int, array{pk_m: ?int, precision: ?string, offset_m: float}|null>  $places  by sample
     * @return array<string, mixed>|null
     */
    private function piece(int $li, int $wayId, array $way, array $dense, int $from, int $to, string $how, array $places): ?array
    {
        [$c, $index] = $dense;
        // Geometry: the vertices of the way inside the stretch, between its two cut points.
        $coords = [$c[2 * $from], $c[2 * $from + 1]];
        $nodeIds = [];
        $nodePks = [];
        foreach ($index as $k => $di) {
            if ($di >= $from && $di <= $to) {
                $nodeIds[$way['nodes'][$k]] = [$c[2 * $di], $c[2 * $di + 1]];
                $nodePks[$way['nodes'][$k]] = isset($places[$di]['pk_m']) ? [$places[$di]['pk_m'], $places[$di]['precision']] : null;
                if ($di > $from && $di < $to) {
                    $coords[] = $c[2 * $di];
                    $coords[] = $c[2 * $di + 1];
                }
            }
        }
        $coords[] = $c[2 * $to];
        $coords[] = $c[2 * $to + 1];
        $whole = $from === 0 && $to === intdiv(count($c), 2) - 1;
        $length = TrackGeometry::length($coords);
        if (! $whole && $length < self::MIN_PIECE_M) {
            return null;
        }
        $line = $this->lines[$li];
        $pks = [];
        $offsets = [];
        $approximate = false;
        for ($i = $from; $i <= $to; $i++) {
            $at = $places[$i] ?? null;
            if ($at === null || $at['pk_m'] === null) {
                $approximate = true;

                continue;
            }
            $pks[$i] = $at['pk_m'];
            $offsets[] = $at['offset_m'];
            $approximate = $approximate || $at['precision'] !== 'derived';
        }
        sort($offsets);
        $tags = $way['tags'];
        $stationId = null;
        $best = self::STATION_M;
        $mid = intdiv($from + $to, 2);
        foreach ($line->stations as $s) {
            $d = TrackGeometry::metres($c[2 * $mid], $c[2 * $mid + 1], $s['lon'], $s['lat']);
            if ($d < $best) {
                $best = $d;
                $stationId = $s['id'];
            }
        }

        return [
            'line_id' => $line->id,
            'line_index' => $li,
            'osm_way_id' => $wayId,
            'part' => 0,
            'kind' => OsmTrackTags::kind($tags),
            'ref' => OsmTrackTags::trackRef($tags),
            'service' => isset($tags['service']) ? mb_substr($tags['service'], 0, 20) : null,
            'usage' => isset($tags['usage']) ? mb_substr($tags['usage'], 0, 20) : null,
            'pk_from_m' => $pks === [] ? null : min($pks),
            'pk_to_m' => $pks === [] ? null : max($pks),
            'pk_up' => $pks === [] ? null : $pks[array_key_last($pks)] >= $pks[array_key_first($pks)],
            'length_m' => (int) round($length),
            'offset_m' => $offsets === [] ? null : round($offsets[intdiv(count($offsets), 2)], 1),
            'electrified' => OsmTrackTags::electrified($tags),
            'voltage_v' => OsmTrackTags::integer($tags, 'voltage', 50_000),
            'maxspeed_kmh' => OsmTrackTags::integer($tags, 'maxspeed', 400),
            'gauge_mm' => OsmTrackTags::integer($tags, 'gauge', 2000),
            'station_id' => $stationId,
            'attach' => $how,
            'pk_precision' => $pks === [] ? null : ($approximate ? 'approximate' : 'derived'),
            'start_node_id' => $whole || $from === 0 ? $way['nodes'][0] : null,
            'end_node_id' => $whole || $to === intdiv(count($c), 2) - 1 ? $way['nodes'][count($way['nodes']) - 1] : null,
            'coords' => $coords,
            'tags' => $tags,
            'node_ids' => $nodeIds,
            'node_pks' => $nodePks,
        ];
    }

    /**
     * Numbers the pieces of a same way on a same line (0, 1, ...) in the order of the way.
     *
     * @param  list<array<string, mixed>>  $pieces
     */
    private function number(array &$pieces): void
    {
        $seen = [];
        foreach ($pieces as &$p) {
            $key = $p['line_id'].':'.$p['osm_way_id'];
            $p['part'] = $seen[$key] ?? 0;
            $seen[$key] = $p['part'] + 1;
        }
    }

    /**
     * Sidings joined end to end form one track; it is a loop when each of its free ends meets another track and
     * none ends on a buffer stop.
     *
     * @param  list<array<string, mixed>>  $pieces
     */
    private function loops(array &$pieces): void
    {
        $sidings = [];
        foreach ($this->osm->ways as $wayId => $way) {
            if (OsmTrackTags::kind($way['tags']) === 'siding') {
                $sidings[$wayId] = true;
            }
        }
        // Union of sidings meeting end to end where nothing else meets.
        $parent = [];
        $find = function (int $w) use (&$parent, &$find): int {
            $parent[$w] ??= $w;

            return $parent[$w] === $w ? $w : ($parent[$w] = $find($parent[$w]));
        };
        $ends = [];
        foreach (array_keys($sidings) as $wayId) {
            $n = $this->osm->ways[$wayId]['nodes'];
            $ends[$n[0]][] = $wayId;
            $ends[$n[count($n) - 1]][] = $wayId;
        }
        foreach ($ends as $nodeId => $ways) {
            if (count($ways) === 2 && ($this->degree[$nodeId] ?? 0) === 2) {
                $parent[$find($ways[0])] = $find($ways[1]);
            }
        }
        $free = [];
        foreach ($ends as $nodeId => $ways) {
            $joined = count($ways) === 2 && ($this->degree[$nodeId] ?? 0) === 2;
            if ($joined) {
                continue;
            }
            foreach ($ways as $wayId) {
                $root = $find($wayId);
                $free[$root] ??= true;
                $buffer = ($this->osm->nodes[$nodeId]['tags']['railway'] ?? null) === 'buffer_stop';
                if (($this->degree[$nodeId] ?? 0) < 2 || $buffer) {
                    $free[$root] = false;
                }
            }
        }
        foreach ($pieces as &$p) {
            if ($p['kind'] === 'siding' && ($free[$find($p['osm_way_id'])] ?? false)) {
                $p['kind'] = 'loop';
            }
        }
    }

    /**
     * A tagged node of an attached piece, with its PK on the line and its topology.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>|null
     */
    private function node(array $piece, int $nodeId, array $tags, float $lon, float $lat, ?int $segment = null): ?array
    {
        // An untagged node where three track ends or more meet is a switch all the same (type unknown).
        $kind = $tags === [] ? 'switch' : ($tags['railway'] ?? null);
        if (! in_array($kind, OsmTrackExtract::NODE_KINDS, true)) {
            return null;
        }
        $line = $this->lines[$piece['line_index']];
        if (array_key_exists($nodeId, $piece['node_pks']) && $segment === null) {
            // A node of the track: the PK the track gives it there.
            [$pk, $precision] = $piece['node_pks'][$nodeId] ?? [null, null];
        } else {
            $near = $piece['pk_from_m'] === null ? null : intdiv($piece['pk_from_m'] + $piece['pk_to_m'], 2);
            $at = $line->chainage->locate($lon, $lat, self::STATION_M + self::CODED_M, $near);
            $pk = $at['pk_m'] ?? null;
            $precision = $at['precision'] ?? null;
        }
        $osmPk = OsmTrackTags::position($tags['railway:position:exact'] ?? ($kind === 'milestone' ? $tags['railway:position'] ?? null : null));
        $codes = $segment === null ? $this->nodeCodes($nodeId) : $this->osm->wayCodes($piece['osm_way_id']);
        $onLine = $line->code !== null && in_array($line->code, $codes, true);
        // Without a PK of its own to compare, the PK carried by the node is taken only on ways of this line alone
        // (on a way shared by two lines, it may be the PK of the other one).
        if ($osmPk !== null && $onLine && ($pk === null ? $codes === [$line->code] : abs($osmPk - $pk) <= self::EXACT_AGREE_M)) {
            $pk = $osmPk;
            $precision = 'exact';
        }

        $subtype = null;
        $detail = null;
        $ref = isset($tags['ref']) ? mb_substr($tags['ref'], 0, 40) : null;
        $from = $piece['osm_way_id'];
        $to = null;
        $direction = null;
        if ($kind === 'switch') {
            $subtype = $tags === [] ? null : OsmTrackTags::switchKind($tags);
            [$from, $to, $direction] = $this->switchTopology($nodeId, $lon, $lat, $line, $pk);
        } elseif ($kind === 'railway_crossing') {
            [$from, $to] = $this->crossingWays($nodeId, $lon, $lat) + [$from, null];
        } elseif ($kind === 'signal') {
            [$subtype, $detail, $ref] = OsmTrackTags::signal($tags);
            $direction = $this->signalDirection($piece, $nodeId, $tags['railway:signal:direction'] ?? null, $line, $segment, $pk);
        } elseif ($kind === 'level_crossing' || $kind === 'crossing') {
            $subtype = $kind === 'crossing' ? 'pedestrian' : 'road';
        } elseif ($kind === 'milestone') {
            // Label as written on the post (« 22 », « 121,5 »), with a decimal comma.
            $label = trim((string) ($tags['railway:position'] ?? $tags['railway:position:exact'] ?? ''));
            $detail = $label === '' ? null : mb_substr(str_replace('.', ',', $label), 0, 60);
        }

        return [
            'line_id' => $piece['line_id'],
            'osm_node_id' => $nodeId,
            'kind' => $kind,
            'subtype' => $subtype,
            'detail' => $detail,
            'ref' => $ref,
            'pk_m' => $pk,
            'pk_precision' => $pk === null ? null : $precision,
            'osm_pk_m' => $osmPk,
            'way_id' => $from,
            'to_way_id' => $to,
            'direction' => $direction,
            'lon' => $lon,
            'lat' => $lat,
            'tags' => $tags,
        ];
    }

    /** @return list<string> */
    private function nodeCodes(int $nodeId): array
    {
        $codes = [];
        foreach ($this->nodeWays[$nodeId] ?? [] as $wayId) {
            array_push($codes, ...$this->osm->wayCodes($wayId));
        }

        return array_values(array_unique($codes));
    }

    /**
     * Branches of the tracks meeting at a node: [way id, unit direction leaving the node, point at BRANCH_M].
     *
     * @return list<array{0: int, 1: array{0: float, 1: float}, 2: array{0: float, 1: float}}>
     */
    private function branches(int $nodeId, float $lon, float $lat): array
    {
        $out = [];
        foreach ($this->nodeWays[$nodeId] ?? [] as $wayId) {
            $way = $this->osm->ways[$wayId];
            $k = array_search($nodeId, $way['nodes'], true);
            if ($k === false) {
                continue;
            }
            foreach ([-1, 1] as $step) {
                if ($k + $step < 0 || $k + $step >= count($way['nodes'])) {
                    continue;
                }
                $p = TrackGeometry::along($way['coords'], $k, $step, self::BRANCH_M);
                $out[] = [$wayId, TrackGeometry::direction($lon, $lat, $p[0], $p[1]), $p];
            }
        }

        return $out;
    }

    /**
     * Track a switch stands on (its toe side), track it leads to, and the direction of the trains that face
     * it (up: towards increasing PKs). Not a three-branch switch: the two tracks that cross, no facing.
     *
     * @return array{0: ?int, 1: ?int, 2: ?string}
     */
    private function switchTopology(int $nodeId, float $lon, float $lat, TrackLine $line, ?int $pk): array
    {
        $b = $this->branches($nodeId, $lon, $lat);
        if (count($b) !== 3) {
            $ways = array_values(array_unique(array_column($b, 0)));

            return [$ways[0] ?? null, $ways[1] ?? null, null];
        }
        // The two heel branches leave in nearly the same direction; the third is the toe.
        $best = null;
        foreach ([[0, 1, 2], [0, 2, 1], [1, 2, 0]] as [$i, $j, $k]) {
            $dot = $b[$i][1][0] * $b[$j][1][0] + $b[$i][1][1] * $b[$j][1][1];
            if ($best === null || $dot > $best[0]) {
                $best = [$dot, $i, $j, $k];
            }
        }
        [, $h1, $h2, $toe] = $best;
        $straightness = fn (int $h): float => $b[$h][1][0] * $b[$toe][1][0] + $b[$h][1][1] * $b[$toe][1][1];
        // The through heel is the most opposite to the toe; the other one diverges.
        [$through, $diverging] = $straightness($h1) <= $straightness($h2) ? [$h1, $h2] : [$h2, $h1];
        $from = $b[$toe][0];
        $to = $b[$diverging][0] !== $from ? $b[$diverging][0] : ($b[$through][0] !== $from ? $b[$through][0] : null);
        $facing = null;
        if ($pk !== null) {
            $toePk = $line->chainage->locate($b[$toe][2][0], $b[$toe][2][1], self::STATION_M + self::CODED_M, $pk)['pk_m'] ?? null;
            if ($toePk !== null && abs($toePk - $pk) >= 3) {
                $facing = $toePk < $pk ? 'up' : 'down';
            }
        }

        return [$from, $to, $facing];
    }

    /** @return array{0?: int, 1?: int} */
    private function crossingWays(int $nodeId, float $lon, float $lat): array
    {
        return array_slice(array_values(array_unique(array_column($this->branches($nodeId, $lon, $lat), 0))), 0, 2);
    }

    /**
     * Direction of the trains a signal is for (up: increasing PKs), from `railway:signal:direction` read along
     * the way it stands on.
     *
     * @param  array<string, mixed>  $piece
     */
    private function signalDirection(array $piece, int $nodeId, ?string $tag, TrackLine $line, ?int $segment, ?int $pk): ?string
    {
        if ($tag !== 'forward' && $tag !== 'backward') {
            return null;
        }
        $way = $this->osm->ways[$piece['osm_way_id']];
        $c = $way['coords'];
        if ($segment !== null) {
            // Beside the track: the direction of the way along its nearest segment.
            $behind = [$c[2 * $segment], $c[2 * $segment + 1]];
            $ahead = [$c[2 * $segment + 2], $c[2 * $segment + 3]];
        } else {
            $k = array_search($nodeId, $way['nodes'], true);
            if ($k === false) {
                return null;
            }
            $behind = TrackGeometry::along($c, $k, -1, self::BRANCH_M);
            $ahead = TrackGeometry::along($c, $k, 1, self::BRANCH_M);
        }
        $max = self::STATION_M + self::CODED_M;
        $a = $line->chainage->locate($behind[0], $behind[1], $max, $pk)['pk_m'] ?? null;
        $b = $line->chainage->locate($ahead[0], $ahead[1], $max, $pk)['pk_m'] ?? null;
        if ($a === null || $b === null || $a === $b) {
            return null;
        }
        $wayUp = $b > $a;

        return ($tag === 'forward') === $wayUp ? 'up' : 'down';
    }

    /**
     * Degrees of the nodes where ways end (each way end counts 1, a way passing through counts 2: a siding meeting
     * a main track in the middle of its way makes 3) and the ways holding each tagged node or junction.
     */
    private function topology(): void
    {
        foreach ($this->osm->ways as $way) {
            $nodes = $way['nodes'];
            $last = count($nodes) - 1;
            $this->degree[$nodes[0]] = ($this->degree[$nodes[0]] ?? 0) + 1;
            $this->degree[$nodes[$last]] = ($this->degree[$nodes[$last]] ?? 0) + 1;
        }
        foreach ($this->osm->ways as $way) {
            $nodes = $way['nodes'];
            for ($i = 1, $last = count($nodes) - 1; $i < $last; $i++) {
                if (isset($this->degree[$nodes[$i]])) {
                    $this->degree[$nodes[$i]] += 2;
                }
            }
        }
        foreach ($this->osm->ways as $wayId => $way) {
            foreach ($way['nodes'] as $nodeId) {
                if (isset($this->osm->nodes[$nodeId]) || ($this->degree[$nodeId] ?? 0) >= 3) {
                    $this->nodeWays[$nodeId][] = $wayId;
                }
            }
        }
    }

    /**
     * Grid of the ways by cell (every cell their segments cross).
     *
     * @return array<int, array<int, true>>
     */
    private static function wayGrid(OsmTrackExtract $osm): array
    {
        $grid = [];
        foreach ($osm->ways as $wayId => $way) {
            $c = $way['coords'];
            for ($i = 2, $n = count($c); $i + 1 < $n; $i += 2) {
                foreach (self::cells(min($c[$i - 2], $c[$i]), min($c[$i - 1], $c[$i + 1]), max($c[$i - 2], $c[$i]), max($c[$i - 1], $c[$i + 1])) as $key) {
                    $grid[$key][$wayId] = true;
                }
            }
        }

        return $grid;
    }

    /**
     * Nearest way within $max metres: [way id, segment index], or null.
     *
     * @param  array<int, array<int, true>>  $grid
     * @return array{0: int, 1: int}|null
     */
    private static function nearestWay(OsmTrackExtract $osm, array $grid, float $lon, float $lat, float $max): ?array
    {
        $best = null;
        foreach (array_keys($grid[self::cell($lon, $lat)] ?? []) as $wayId) {
            $c = $osm->ways[$wayId]['coords'];
            for ($i = 2, $n = count($c); $i + 1 < $n; $i += 2) {
                [$d] = TrackGeometry::onSegment($lon, $lat, $c[$i - 2], $c[$i - 1], $c[$i], $c[$i + 1]);
                if ($d <= $max && ($best === null || $d < $best[0])) {
                    $best = [$d, $wayId, intdiv($i, 2) - 1];
                }
            }
        }

        return $best === null ? null : [$best[1], $best[2]];
    }

    private static function cell(float $lon, float $lat): int
    {
        return self::key((int) floor($lon / self::CELL_DEG), (int) floor($lat / self::CELL_DEG));
    }

    private static function key(int $x, int $y): int
    {
        return ($x + 100_000) * 1_000_000 + ($y + 100_000);
    }

    /** @return list<int> */
    private static function cells(float $minLon, float $minLat, float $maxLon, float $maxLat): array
    {
        $keys = [];
        for ($x = (int) floor($minLon / self::CELL_DEG), $x1 = (int) floor($maxLon / self::CELL_DEG); $x <= $x1; $x++) {
            for ($y = (int) floor($minLat / self::CELL_DEG), $y1 = (int) floor($maxLat / self::CELL_DEG); $y <= $y1; $y++) {
                $keys[] = self::key($x, $y);
            }
        }

        return $keys;
    }
}
