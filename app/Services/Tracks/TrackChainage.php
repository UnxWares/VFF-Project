<?php

declare(strict_types=1);

namespace App\Services\Tracks;

use App\Services\Profiles\PkCalibration;

/**
 * Linear referencing of the detailed track layout: kilometre point (PK, metres) of any place near a line, on
 * the reference trace of the line in the current era (`line_segments` in service, merged into parts by
 * ST_LineMerge), calibrated on sourced PKs like elsewhere in VFF (PkCalibration).
 *
 * Anchors are places of known PK projected on the trace: stations whose PK is sourced (`station_lines`, never
 * the raw abscissa of a trace) and remarkable points of the line (`line_landmarks.php`), both weak, SNCF Reseau
 * level crossings of the line and OpenStreetMap objects that carry the PK of the line they stand on
 * (`railway:position:exact`, milestones), both strong: a weak anchor within SHADOW_M of a strong one along the
 * trace is left out (a station building is not where its PK is read), unless every strong one near it is then left out
 * by the calibration (foreign run, spike): the parts are then fitted again with it. Each part is calibrated on its own:
 * the longest run of anchors whose PK grows along the part, in whichever direction keeps more of them, then a
 * spike filter drops an anchor whose two neighbouring intervals both stretch or squeeze the PK beyond
 * SCALE_MIN..SCALE_MAX (one wrong anchor), never a genuine jump of the PK numbering (one interval only).
 * A part left with fewer than two anchors takes the PK of the calibrated parts it touches (within JOIN_M of
 * one of its ends), its direction given by a second anchor or else by the direction of the part it leaves; a
 * part with one anchor whose touching PK breaks with it keeps its anchor and takes only the direction; a part
 * closed on itself (a turn of a helix) takes the two PKs met at its node that differ by about its length, or none.
 *
 * Two kept anchors whose PK gap differs from their distance along the trace by more than JUMP_M, with a scale
 * outside SCALE_MIN..SCALE_MAX, are a break: either a run of anchors of another numbering (OpenStreetMap switches of
 * a yard numbered from its own origin, a station of a neighbouring line) or a genuine change of the PK numbering.
 * A run of anchors is dropped when the PK on both sides of it (the neighbouring anchors, or the PK of the parts of
 * the trace touching that end of the part within CONTEXT_M) agree with each other across it; a run at an end of
 * the part with nothing beyond is dropped when its PKs lie far outside those of the rest of the line
 * (FOREIGN_MARGIN_M) or when it is a small minority of the part (two anchors at most against MINORITY times as
 * many). A break that stays is a genuine change of numbering: the PK is never interpolated across it, a place
 * gets the PK of the nearer side within a third of the gap (and EXTRAPOLATE_M), none in between.
 *
 * Precision: `derived` between two anchors less than DERIVED_GAP_M apart and up to EXTRAPOLATE_M beyond the
 * outer ones, `approximate` elsewhere (long gap, extrapolation, PK inherited from a neighbouring part, near a
 * break). A part without any PK gives none.
 *
 * `chain()` places a whole track at once: each sample may project on several portions of the trace (parts of
 * the line, the two branches of a trace that folds back on itself, the turns of a spiral), and the portion kept
 * for each sample is the one that keeps the PK continuous along the track at the least distance (dynamic
 * programming), instead of the nearest portion sample by sample. `locate()` with a PK hint picks, among the
 * portions within reach, the one whose PK is the nearest to the hint (nodes of a track, branches of a switch).
 * Pure (tests: tests/Unit/Tracks/TrackChainageTest.php).
 */
final class TrackChainage
{
    public const DERIVED_GAP_M = 10000.0;

    public const EXTRAPOLATE_M = 1000.0;

    /** An end of an uncalibrated part closer than this to a calibrated part takes its PK. */
    public const JOIN_M = 30.0;

    /** Accepted PK stretch between two consecutive anchors (PK metres per trace metre) before a spike is dropped. */
    public const SCALE_MIN = 0.7;

    public const SCALE_MAX = 1.4;

    /** A weak anchor (station, landmark) is left out when a strong one (level crossing, OSM PK) lies this close along the trace. */
    public const SHADOW_M = 1000.0;

    /** A PK gap that differs from the distance along the trace by more than this (with a scale out of range) is a break. */
    public const JUMP_M = 1000.0;

    /** An anchor whose PK is out of step by more than this with both its neighbours, which agree, is dropped. */
    public const SPIKE_M = 150.0;

    /** Parts of the trace whose end lies this close to an end of a part give it its PK context. */
    public const CONTEXT_M = 300.0;

    /** A run of anchors longer than this along the trace is never taken for a foreign island. */
    public const ISLAND_M = 10000.0;

    /** A run of anchors at an end of a part whose PKs lie this far outside those of the rest of the line is foreign. */
    public const FOREIGN_MARGIN_M = 20000.0;

    /** A run of two anchors at most at an end of a part is foreign against this many times as many anchors. */
    public const MINORITY = 5;

    /** Cost (metres) of leaving a portion of the trace for another one without PK to compare (chain()). */
    public const SWITCH_COST_M = 200.0;

    /** Rounds of the calibration (fit, then drop what the context gives away). */
    private const ROUNDS = 10;

    /** Grid cell (degrees) of the segment index. */
    private const CELL_DEG = 0.005;

    /** @var list<list<float>> */
    private array $parts;

    /** @var list<list<float>> cumulative length at each vertex, per part */
    private array $cumulative;

    /** @var array<int, list<int>> cell key => part * SEG_BASE + segment */
    private array $grid = [];

    /** @var array<int, array{reversed: bool, anchors: list<array{0: float, 1: int}>, inherited: bool}|null> calibration per part */
    private array $calibration = [];

    private const SEG_BASE = 10_000_000;

    /**
     * @param  list<list<float>>  $parts  flat polylines [lon, lat, ...] of the reference trace
     * @param  list<array{lon: float, lat: float, pk_m: int, max_m: float, strong?: bool}>  $anchors  places of known PK, how far from the trace they may lie, strong or weak
     */
    public function __construct(array $parts, array $anchors)
    {
        $this->parts = array_values(array_filter($parts, fn (array $p): bool => count($p) >= 4));
        foreach ($this->parts as $pi => $c) {
            $this->cumulative[$pi] = TrackGeometry::cumulative($c);
            for ($i = 2, $n = count($c); $i + 1 < $n; $i += 2) {
                foreach ($this->cells(min($c[$i - 2], $c[$i]), min($c[$i - 1], $c[$i + 1]), max($c[$i - 2], $c[$i]), max($c[$i - 1], $c[$i + 1])) as $key) {
                    $this->grid[$key][] = $pi * self::SEG_BASE + intdiv($i, 2) - 1;
                }
            }
        }
        $this->calibrate($anchors);
    }

    /** Whether at least one part has a PK. */
    public function calibrated(): bool
    {
        return array_filter($this->calibration) !== [];
    }

    /** Anchors kept by the calibration, all parts together. */
    public function anchorCount(): int
    {
        return array_sum(array_map(fn (?array $c): int => $c === null || $c['inherited'] ? 0 : count($c['anchors']), $this->calibration));
    }

    /**
     * The place projected on the nearest part of the trace within $maxDistance metres, or null.
     * `offset_m` is positive on the left when facing increasing PKs (on the left of the part direction when the
     * part has no PK); `direction` is the unit vector of the trace towards increasing PKs (local metres).
     * With $nearPk, the portion of the trace within reach whose PK is the nearest to it wins over the nearest one
     * (a portion without PK only when none has one).
     *
     * @return array{pk_m: ?int, precision: ?string, distance_m: float, offset_m: float, along_m: float, part: int, direction: array{0: float, 1: float}}|null
     */
    public function locate(float $lon, float $lat, float $maxDistance = 30.0, ?int $nearPk = null): ?array
    {
        if ($nearPk === null) {
            $best = $this->nearest($lon, $lat, $maxDistance);

            return $best === null ? null : $this->place($best);
        }
        $found = null;
        $cost = INF;
        foreach ($this->candidates($lon, $lat, $maxDistance) as $candidate) {
            $at = $this->place($candidate);
            $c = $at['distance_m'] + ($at['pk_m'] === null ? 1.0e9 : abs($at['pk_m'] - $nearPk));
            if ($c < $cost) {
                $cost = $c;
                $found = $at;
            }
        }

        return $found;
    }

    /**
     * Places of the points of a track (flat polyline [lon, lat, ...], points a few tens of metres apart), each on
     * the portion of the trace within $maxDistance that keeps the PK continuous along the track at the least total
     * distance; null for a point with no portion within reach (the chain starts again after it).
     *
     * @param  list<float>  $points
     * @return list<array{pk_m: ?int, precision: ?string, distance_m: float, offset_m: float, along_m: float, part: int, direction: array{0: float, 1: float}}|null>
     */
    public function chain(array $points, float $maxDistance): array
    {
        $count = intdiv(count($points), 2);
        /** @var list<list<array{pk_m: ?int, precision: ?string, distance_m: float, offset_m: float, along_m: float, part: int, direction: array{0: float, 1: float}}>> $states */
        $states = [];
        $cost = [];
        $back = [];
        for ($i = 0; $i < $count; $i++) {
            $states[$i] = array_map(fn (array $c): array => $this->place($c), $this->candidates($points[2 * $i], $points[2 * $i + 1], $maxDistance));
            $cost[$i] = [];
            $back[$i] = [];
            $step = $i === 0 ? 0.0 : TrackGeometry::metres($points[2 * $i - 2], $points[2 * $i - 1], $points[2 * $i], $points[2 * $i + 1]);
            foreach ($states[$i] as $k => $at) {
                $best = 0.0;
                $from = null;
                if ($i > 0 && $states[$i - 1] !== []) {
                    $best = INF;
                    foreach ($states[$i - 1] as $j => $prev) {
                        $c = $cost[$i - 1][$j] + self::transition($prev, $at, $step);
                        if ($c < $best) {
                            $best = $c;
                            $from = $j;
                        }
                    }
                }
                $cost[$i][$k] = $best + $at['distance_m'];
                $back[$i][$k] = $from;
            }
        }
        $out = array_fill(0, $count, null);
        $k = null;
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($states[$i] === []) {
                $k = null;

                continue;
            }
            if ($k === null) {
                $k = (int) array_search(min($cost[$i]), $cost[$i], true);
            }
            $out[$i] = $states[$i][$k];
            $k = $back[$i][$k];
        }

        return $out;
    }

    /**
     * Cost of going from one place to the next along a track $step metres further: the PK (or, without PK, the
     * distance along the same part) should not move more than the track does.
     *
     * @param  array{pk_m: ?int, along_m: float, part: int}  $a
     * @param  array{pk_m: ?int, along_m: float, part: int}  $b
     */
    private static function transition(array $a, array $b, float $step): float
    {
        $slack = 1.5 * $step + 10.0;
        if ($a['pk_m'] !== null && $b['pk_m'] !== null) {
            return max(0.0, abs($b['pk_m'] - $a['pk_m']) - $slack) + ($a['part'] === $b['part'] ? 0.0 : 5.0);
        }
        if ($a['part'] === $b['part'] && $a['pk_m'] === null && $b['pk_m'] === null) {
            return max(0.0, abs($b['along_m'] - $a['along_m']) - $slack);
        }

        return self::SWITCH_COST_M;
    }

    /**
     * Locate result of a projection [part, segment, t, distance, signed offset, fraction along the infinite segment].
     *
     * @param  array{0: int, 1: int, 2: float, 3: float, 4: float, 5: float}  $best
     * @return array{pk_m: ?int, precision: ?string, distance_m: float, offset_m: float, along_m: float, part: int, direction: array{0: float, 1: float}}
     */
    private function place(array $best): array
    {
        [$pi, $seg, $t, $dist, $offset, $raw] = $best;
        $c = $this->parts[$pi];
        $cum = $this->cumulative[$pi];
        // Beyond an end of the part (tracks of a terminus, of a junction station), the PK runs on along the
        // last segment instead of stopping at the end.
        $beyond = ($seg === 0 && $raw < 0.0) || ($seg === count($cum) - 2 && $raw > 1.0);
        $along = $cum[$seg] + ($cum[$seg + 1] - $cum[$seg]) * ($beyond ? $raw : $t);
        $direction = TrackGeometry::direction($c[2 * $seg], $c[2 * $seg + 1], $c[2 * $seg + 2], $c[2 * $seg + 3]);
        $cal = $this->calibration[$pi] ?? null;
        [$pk, $precision] = $cal === null ? [null, null] : self::estimate($cal, $along, $cum[count($cum) - 1]);
        if ($cal !== null && $cal['reversed']) {
            $offset = -$offset;
            $direction = [-$direction[0], -$direction[1]];
        }

        return [
            'pk_m' => $pk,
            'precision' => $precision,
            'distance_m' => $dist,
            'offset_m' => $offset,
            'along_m' => $along,
            'part' => $pi,
            'direction' => $direction,
        ];
    }

    /**
     * Every portion of the trace within $maxDistance: the nearest projection on each run of consecutive segments
     * of a part that pass within reach (a part folding back on itself gives one per branch).
     *
     * @return list<array{0: int, 1: int, 2: float, 3: float, 4: float, 5: float}>
     */
    private function candidates(float $lon, float $lat, float $maxDistance): array
    {
        $dLat = $maxDistance / TrackGeometry::M_PER_DEG;
        $dLon = $dLat / max(0.1, cos(deg2rad($lat)));
        $hits = [];
        $seen = [];
        foreach ($this->cells($lon - $dLon, $lat - $dLat, $lon + $dLon, $lat + $dLat) as $key) {
            foreach ($this->grid[$key] ?? [] as $ref) {
                if (isset($seen[$ref])) {
                    continue;
                }
                $seen[$ref] = true;
                $pi = intdiv($ref, self::SEG_BASE);
                $seg = $ref % self::SEG_BASE;
                $c = $this->parts[$pi];
                [$d, $t, $offset, $raw] = TrackGeometry::onSegment($lon, $lat, $c[2 * $seg], $c[2 * $seg + 1], $c[2 * $seg + 2], $c[2 * $seg + 3]);
                if ($d <= $maxDistance) {
                    $hits[$pi][$seg] = [$pi, $seg, $t, $d, $offset, $raw];
                }
            }
        }
        $out = [];
        foreach ($hits as $segments) {
            ksort($segments);
            $run = null;
            $last = -2;
            foreach ($segments as $seg => $hit) {
                if ($seg !== $last + 1 && $run !== null) {
                    $out[] = $run;
                    $run = null;
                }
                if ($run === null || $hit[3] < $run[3]) {
                    $run = $hit;
                }
                $last = $seg;
            }
            // $hits only holds parts with at least one segment within reach: the last run is never empty.
            $out[] = $run;
        }

        return $out;
    }

    /**
     * Nearest segment: [part, segment, t, distance, signed offset, fraction along the infinite segment].
     *
     * @return array{0: int, 1: int, 2: float, 3: float, 4: float, 5: float}|null
     */
    private function nearest(float $lon, float $lat, float $maxDistance, ?int $skipPart = null, bool $calibratedOnly = false): ?array
    {
        $dLat = $maxDistance / TrackGeometry::M_PER_DEG;
        $dLon = $dLat / max(0.1, cos(deg2rad($lat)));
        $best = null;
        $seen = [];
        foreach ($this->cells($lon - $dLon, $lat - $dLat, $lon + $dLon, $lat + $dLat) as $key) {
            foreach ($this->grid[$key] ?? [] as $ref) {
                if (isset($seen[$ref])) {
                    continue;
                }
                $seen[$ref] = true;
                $pi = intdiv($ref, self::SEG_BASE);
                if ($pi === $skipPart || ($calibratedOnly && ($this->calibration[$pi] ?? null) === null)) {
                    continue;
                }
                $seg = $ref % self::SEG_BASE;
                $c = $this->parts[$pi];
                [$d, $t, $offset, $raw] = TrackGeometry::onSegment($lon, $lat, $c[2 * $seg], $c[2 * $seg + 1], $c[2 * $seg + 2], $c[2 * $seg + 3]);
                if ($d <= $maxDistance && ($best === null || $d < $best[3])) {
                    $best = [$pi, $seg, $t, $d, $offset, $raw];
                }
            }
        }

        return $best;
    }

    /**
     * Grid keys of the cells covering a bounding box.
     *
     * @return list<int>
     */
    private function cells(float $minLon, float $minLat, float $maxLon, float $maxLat): array
    {
        $keys = [];
        $x0 = (int) floor($minLon / self::CELL_DEG);
        $x1 = (int) floor($maxLon / self::CELL_DEG);
        $y0 = (int) floor($minLat / self::CELL_DEG);
        $y1 = (int) floor($maxLat / self::CELL_DEG);
        for ($x = $x0; $x <= $x1; $x++) {
            for ($y = $y0; $y <= $y1; $y++) {
                $keys[] = ($x + 100_000) * 1_000_000 + ($y + 100_000);
            }
        }

        return $keys;
    }

    /** @param  list<array{lon: float, lat: float, pk_m: int, max_m: float, strong?: bool}>  $anchors */
    private function calibrate(array $anchors): void
    {
        $located = [];
        foreach ($anchors as $a) {
            $best = $this->nearest($a['lon'], $a['lat'], $a['max_m']);
            if ($best === null) {
                continue;
            }
            [$pi, $seg, $t, , , $raw] = $best;
            $cum = $this->cumulative[$pi];
            // Beyond an end of the part, the anchor is placed at that end: its distance along the part is not its own.
            $beyond = ($seg === 0 && $raw < 0.0) || ($seg === count($cum) - 2 && $raw > 1.0);
            $located[$pi][] = [$cum[$seg] + ($cum[$seg + 1] - $cum[$seg]) * $t, $a['pk_m'], $a['strong'] ?? false, $beyond];
        }
        // Every strong anchor shadows the weak ones near it; a weak anchor shadowed only by strong anchors that the
        // calibration then leaves out (a foreign run, the PK of a neighbouring line, a spike) is taken back and the parts
        // are fitted again with the strong anchors kept as the only shadows (Séon-St-Henry on 830000, PK 853+219, hidden
        // by two OpenStreetMap PKs of 710000 that the calibration drops). Not a weak anchor beyond an end of its part
        // (Carcassonne, 272 m past the end of a part of 676000, would pin the PK of the station on that end).
        $all = array_map(fn (array $list): array => array_column(array_filter($list, fn (array $a): bool => $a[2]), 0), $located);
        $first = self::shadowed($located, $all, $all);
        $perPart = $this->fitRounds($first);
        $kept = [];
        foreach ($located as $pi => $list) {
            $kept[$pi] = array_column(array_filter($list, fn (array $a): bool => $a[2] && $this->keeps($pi, $perPart[$pi] ?? [], $a)), 0);
        }
        $second = self::shadowed($located, $all, $kept);
        if ($second !== $first) {
            $perPart = $this->fitRounds($second);
        }
        $this->inherit($perPart);
    }

    /**
     * Anchors [along, PK] of each part, without the weak anchors that lie within SHADOW_M of a shadow: one of $kept for an
     * anchor on the part, one of $all for an anchor beyond an end of it.
     *
     * @param  array<int, list<array{0: float, 1: int, 2: bool, 3: bool}>>  $located  [along, PK, strong, beyond an end] per part
     * @param  array<int, list<float>>  $all  along of every strong anchor, per part
     * @param  array<int, list<float>>  $kept  along of the strong anchors that shadow an anchor on the part, per part
     * @return array<int, list<array{0: float, 1: int}>>
     */
    private static function shadowed(array $located, array $all, array $kept): array
    {
        $perPart = [];
        foreach ($located as $pi => $list) {
            $everyStrong = $all[$pi] ?? [];
            $keptStrong = $kept[$pi] ?? [];
            sort($everyStrong);
            sort($keptStrong);
            foreach ($list as [$along, $pk, $isStrong, $beyond]) {
                if ($isStrong || ! self::within($beyond ? $everyStrong : $keptStrong, $along, self::SHADOW_M)) {
                    $perPart[$pi][] = [$along, $pk];
                }
            }
        }

        return $perPart;
    }

    /**
     * Whether the calibration of a part uses an anchor [along, PK, strong]: among the anchors it keeps, or, for a part
     * left without a calibration, among its own anchors still standing (they still give the PK it inherits).
     *
     * @param  list<array{0: float, 1: int}>  $own
     * @param  array{0: float, 1: int, 2: bool, 3: bool}  $anchor
     */
    private function keeps(int $pi, array $own, array $anchor): bool
    {
        $cal = $this->calibration[$pi] ?? null;
        $length = $this->cumulative[$pi][count($this->cumulative[$pi]) - 1];
        $used = $cal === null ? $own : array_map(fn (array $a): array => [$cal['reversed'] ? $length - $a[0] : $a[0], $a[1]], $cal['anchors']);
        foreach ($used as [$along, $pk]) {
            if ($pk === $anchor[1] && abs($along - $anchor[0]) < 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fit, drop the runs of foreign anchors that the context gives away, fit again until nothing is dropped.
     *
     * @param  array<int, list<array{0: float, 1: int}>>  $perPart
     * @return array<int, list<array{0: float, 1: int}>> the anchors left to each part
     */
    private function fitRounds(array $perPart): array
    {
        $refit = array_keys($this->parts);
        for ($round = 0; $round < self::ROUNDS; $round++) {
            foreach ($refit as $pi) {
                $this->calibration[$pi] = self::fit($perPart[$pi] ?? [], $this->cumulative[$pi][count($this->cumulative[$pi]) - 1]);
            }
            $refit = [];
            $dropped = false;
            foreach ($this->parts as $pi => $c) {
                $foreign = $this->foreignAnchors($pi);
                if ($foreign !== []) {
                    $refit[] = $pi;
                    $perPart[$pi] = array_values(array_filter($perPart[$pi], function (array $a) use ($foreign): bool {
                        foreach ($foreign as [$along, $pk]) {
                            if ($a[1] === $pk && abs($a[0] - $along) < 0.01) {
                                return false;
                            }
                        }

                        return true;
                    }));
                    $dropped = true;
                }
            }
            if (! $dropped) {
                break;
            }
        }

        return $perPart;
    }

    /**
     * Whether two anchors [distance, PK] (increasing distance) are on two sides of a break of the numbering.
     *
     * @param  array{0: float, 1: int}  $a
     * @param  array{0: float, 1: int}  $b
     */
    public static function isBreak(array $a, array $b): bool
    {
        $d = $b[0] - $a[0];
        $pk = $b[1] - $a[1];
        if (abs($pk - $d) <= self::JUMP_M) {
            return false;
        }
        if ($d <= 0.0) {
            return true;
        }
        $scale = $pk / $d;

        return $scale < self::SCALE_MIN || $scale > self::SCALE_MAX;
    }

    /**
     * Whether the PK gap between two anchors (increasing distance) differs from their distance by more than
     * SPIKE_M with a scale out of range: one of them is misplaced.
     *
     * @param  array{0: float, 1: int}  $a
     * @param  array{0: float, 1: int}  $b
     */
    public static function isSpike(array $a, array $b): bool
    {
        $d = $b[0] - $a[0];
        $pk = $b[1] - $a[1];
        if (abs($pk - $d) <= self::SPIKE_M) {
            return false;
        }
        if ($d <= 0.0) {
            return true;
        }
        $scale = $pk / $d;

        return $scale < self::SCALE_MIN || $scale > self::SCALE_MAX;
    }

    /**
     * Whether two anchors on both sides of a run agree across it: their PK gap matches their distance within JUMP_M.
     *
     * @param  array{0: float, 1: int}  $a
     * @param  array{0: float, 1: int}  $b
     */
    private static function agree(array $a, array $b): bool
    {
        return abs(($b[1] - $a[1]) - ($b[0] - $a[0])) <= self::JUMP_M;
    }

    /**
     * Runs of anchors between breaks.
     *
     * @param  list<array{0: float, 1: int}>  $anchors  sorted, increasing
     * @return list<list<array{0: float, 1: int}>>
     */
    public static function runs(array $anchors): array
    {
        $runs = [];
        foreach ($anchors as $i => $a) {
            if ($i === 0 || self::isBreak($anchors[$i - 1], $a)) {
                $runs[] = [];
            }
            $runs[count($runs) - 1][] = $a;
        }

        return $runs;
    }

    /**
     * Anchors of a part ([distance along the part, PK], as given) that belong to a foreign run: see the class
     * description.
     *
     * @return list<array{0: float, 1: int}>
     */
    private function foreignAnchors(int $pi): array
    {
        $cal = $this->calibration[$pi];
        if ($cal === null) {
            return [];
        }
        $cum = $this->cumulative[$pi];
        $length = $cum[count($cum) - 1];
        $runs = self::runs($cal['anchors']);
        // PK context of each end of the part, in the distance of the fit (from the low-PK end).
        [$atStart, $atEnd] = [$this->context($pi, 0), $this->context($pi, count($this->parts[$pi]) - 2)];
        $before = array_map(fn (int $pk): array => [0.0, $pk], $cal['reversed'] ? $atEnd : $atStart);
        $after = array_map(fn (int $pk): array => [$length, $pk], $cal['reversed'] ? $atStart : $atEnd);
        $total = count($cal['anchors']);
        $drop = null;
        foreach ($runs as $r => $run) {
            $prev = $r > 0 ? [$runs[$r - 1][count($runs[$r - 1]) - 1]] : $before;
            $next = $r < count($runs) - 1 ? [$runs[$r + 1][0]] : $after;
            $first = $run[0];
            $last = $run[count($run) - 1];
            // The anchors of the part outside the run, and each context used, outweigh the run.
            $others = $total - count($run);
            $weight = $others + ($r === 0 ? 1 : 0) + ($r === count($runs) - 1 ? 1 : 0);
            $foreign = false;
            if (count($run) < $weight && $last[0] - $first[0] <= self::ISLAND_M) {
                foreach ($prev as $p) {
                    foreach ($next as $n) {
                        if (self::agree($p, $n) && (self::isBreak($p, $first) || self::isBreak($last, $n))) {
                            $foreign = true;
                        }
                    }
                }
            }
            if (! $foreign && count($runs) > 1 && ($r === 0 || $r === count($runs) - 1)) {
                // A run at an end of the part with nothing on its other side to compare.
                $foreign = (count($run) <= 2 && $others >= self::MINORITY * count($run)) || $this->outsideLine($pi, $run);
            }
            if ($foreign && ($drop === null || count($run) < count($drop))) {
                $drop = $run;
            }
        }
        if ($drop === null) {
            // One anchor out of step with both its neighbours (contexts of the ends included) while they agree.
            $anchors = $cal['anchors'];
            $n = count($anchors);
            for ($i = 0; $i < $n && $drop === null; $i++) {
                $prev = $i > 0 ? [$anchors[$i - 1]] : $before;
                $next = $i < $n - 1 ? [$anchors[$i + 1]] : $after;
                foreach ($prev as $p) {
                    foreach ($next as $q) {
                        if ($drop === null && self::isSpike($p, $anchors[$i]) && self::isSpike($anchors[$i], $q) && ! self::isSpike($p, $q)) {
                            $drop = [$anchors[$i]];
                        }
                    }
                }
            }
        }
        if ($drop !== null) {

            return array_map(fn (array $a): array => [$cal['reversed'] ? $length - $a[0] : $a[0], $a[1]], $drop);
        }

        return [];
    }

    /**
     * Whether the PKs of a run lie more than FOREIGN_MARGIN_M outside those of every other anchor of the line, these
     * being more numerous than the run.
     *
     * @param  list<array{0: float, 1: int}>  $run
     */
    private function outsideLine(int $pi, array $run): bool
    {
        $min = PHP_INT_MAX;
        $max = PHP_INT_MIN;
        $count = 0;
        foreach ($this->calibration as $pj => $cal) {
            if ($cal === null || $cal['inherited']) {
                continue;
            }
            foreach ($cal['anchors'] as $a) {
                if ($pj === $pi && in_array($a, $run, true)) {
                    continue;
                }
                $min = min($min, $a[1]);
                $max = max($max, $a[1]);
                $count++;
            }
        }
        if ($min > $max || $count <= count($run)) {
            return false;
        }
        $lo = min(array_column($run, 1));
        $hi = max(array_column($run, 1));

        return $hi < $min - self::FOREIGN_MARGIN_M || $lo > $max + self::FOREIGN_MARGIN_M;
    }

    /**
     * PKs given by the other calibrated parts (own anchors, not inherited) touching the end of a part at vertex
     * index $vertex (0 or the last one) within CONTEXT_M.
     *
     * @return list<int>
     */
    private function context(int $pi, int $vertex): array
    {
        $c = $this->parts[$pi];
        $lon = $c[$vertex];
        $lat = $c[$vertex + 1];
        $out = [];
        foreach ($this->candidates($lon, $lat, self::CONTEXT_M) as $hit) {
            $hp = $hit[0];
            $hcal = $this->calibration[$hp] ?? null;
            if ($hp === $pi || $hcal === null || $hcal['inherited']) {
                continue;
            }
            $hcum = $this->cumulative[$hp];
            [$pk] = self::estimate($hcal, $hcum[$hit[1]] + ($hcum[$hit[1] + 1] - $hcum[$hit[1]]) * $hit[2], $hcum[count($hcum) - 1]);
            if ($pk !== null) {
                $out[] = $pk;
            }
        }

        return $out;
    }

    /**
     * Whether a sorted list holds a value within $radius of $x.
     *
     * @param  list<float>  $sorted
     */
    private static function within(array $sorted, float $x, float $radius): bool
    {
        $lo = 0;
        $hi = count($sorted) - 1;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if (abs($sorted[$mid] - $x) <= $radius) {
                return true;
            }
            if ($sorted[$mid] < $x) {
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return false;
    }

    /**
     * Calibration of one part from its anchors [along, pk], or null with fewer than two kept anchors.
     *
     * @param  list<array{0: float, 1: int}>  $anchors
     * @return array{reversed: bool, anchors: list<array{0: float, 1: int}>, inherited: bool}|null
     */
    public static function fit(array $anchors, float $length): ?array
    {
        $forward = self::chainOf($anchors);
        $backward = self::chainOf(array_map(fn (array $a): array => [$length - $a[0], $a[1]], $anchors));
        $reversed = count($backward) > count($forward);
        $kept = $reversed ? $backward : $forward;

        return count($kept) >= 2 ? ['reversed' => $reversed, 'anchors' => $kept, 'inherited' => false] : null;
    }

    /**
     * The anchors kept along the part: the longest chain whose PK grows with the distance (PkCalibration::monotone),
     * spikes dropped; then a run of that chain between two breaks, ISLAND_M long at most, is left out when, without
     * it, the chain takes back anchors of the line on both sides of it whose PK gap matches their distance within
     * JUMP_M (a run of foreign anchors that pushed aside the anchors of the line in its place).
     *
     * @param  list<array{0: float, 1: int}>  $anchors
     * @return list<array{0: float, 1: int}>
     */
    public static function chainOf(array $anchors): array
    {
        $chain = self::despike(PkCalibration::monotone($anchors));
        for ($round = 0; $round < 3; $round++) {
            $runs = self::runs($chain);
            if (count($runs) < 2) {
                break;
            }
            usort($runs, fn (array $x, array $y): int => count($x) <=> count($y));
            $changed = false;
            foreach ($runs as $run) {
                $rest = array_values(array_filter($anchors, fn (array $a): bool => ! in_array($a, $run, true)));
                $other = self::despike(PkCalibration::monotone($rest));
                $before = array_values(array_filter($other, fn (array $a): bool => $a[0] < $run[0][0]));
                $after = array_values(array_filter($other, fn (array $a): bool => $a[0] > $run[count($run) - 1][0]));
                if ($before !== [] && $after !== [] && self::agree($before[count($before) - 1], $after[0]) && $run[count($run) - 1][0] - $run[0][0] <= self::ISLAND_M
                    && count($other) >= count($chain) - count($run) + 1) {
                    $anchors = $rest;
                    $chain = $other;
                    $changed = true;

                    break;
                }
            }
            if (! $changed) {
                break;
            }
        }

        return $chain;
    }

    /**
     * Drops an anchor whose two neighbouring intervals both have a PK scale outside SCALE_MIN..SCALE_MAX.
     *
     * @param  list<array{0: float, 1: int}>  $anchors  sorted, increasing
     * @return list<array{0: float, 1: int}>
     */
    public static function despike(array $anchors): array
    {
        $bad = function (array $a, array $b): bool {
            $d = $b[0] - $a[0];
            if ($d < 200.0) {
                return false;
            }
            $scale = ($b[1] - $a[1]) / $d;

            return $scale < self::SCALE_MIN || $scale > self::SCALE_MAX;
        };
        $n = count($anchors);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i > 0 && $i < $n - 1 && $bad($anchors[$i - 1], $anchors[$i]) && $bad($anchors[$i], $anchors[$i + 1]) && ! $bad($anchors[$i - 1], $anchors[$i + 1])) {
                continue;
            }
            $out[] = $anchors[$i];
        }

        return $out;
    }

    /**
     * PK and precision at a distance along a calibrated part (none in the middle of a break).
     *
     * @param  array{reversed: bool, anchors: list<array{0: float, 1: int}>, inherited: bool}  $cal
     * @return array{0: ?int, 1: ?string}
     */
    private static function estimate(array $cal, float $along, float $length): array
    {
        $d = $cal['reversed'] ? $length - $along : $along;
        $a = $cal['anchors'];
        $n = count($a);
        if ($d <= $a[0][0]) {
            $pk = $a[0][1] - ($a[0][0] - $d);
            $precise = $a[0][0] - $d <= self::EXTRAPOLATE_M;
        } elseif ($d >= $a[$n - 1][0]) {
            $pk = $a[$n - 1][1] + ($d - $a[$n - 1][0]);
            $precise = $d - $a[$n - 1][0] <= self::EXTRAPOLATE_M;
        } else {
            $lo = 0;
            $hi = $n - 1;
            while ($hi - $lo > 1) {
                $mid = intdiv($lo + $hi, 2);
                if ($a[$mid][0] <= $d) {
                    $lo = $mid;
                } else {
                    $hi = $mid;
                }
            }
            [$d0, $p0] = $a[$lo];
            [$d1, $p1] = $a[$hi];
            if (self::isBreak($a[$lo], $a[$hi])) {
                // A change of numbering somewhere between the two: the PK of the nearer side, close to it only.
                $reach = min(self::EXTRAPOLATE_M, ($d1 - $d0) / 3);
                if ($d - $d0 <= $reach) {
                    return [(int) round($p0 + ($d - $d0)), 'approximate'];
                }
                if ($d1 - $d <= $reach) {
                    return [(int) round($p1 - ($d1 - $d)), 'approximate'];
                }

                return [null, null];
            }
            $pk = $p0 + ($p1 - $p0) * ($d - $d0) / ($d1 - $d0);
            $precise = $d1 - $d0 <= self::DERIVED_GAP_M;
        }

        return [(int) round($pk), $precise && ! $cal['inherited'] ? 'derived' : 'approximate'];
    }

    /**
     * Calibration of a part closed on itself (a turn of a helix) without anchor: two calibrated parts meet at its
     * node, one whose PK at the node is lower than the other's by about the length of the turn; the turn starts
     * on the side the lower one runs into. Null when no such pair exists (which end comes first is unknown).
     *
     * @return array{reversed: bool, anchors: list<array{0: float, 1: int}>, inherited: bool}|null
     */
    private function closedLoop(int $pi): ?array
    {
        $c = $this->parts[$pi];
        $cum = $this->cumulative[$pi];
        $length = $cum[count($cum) - 1];
        $around = [];
        foreach ($this->candidates($c[0], $c[1], self::JOIN_M) as [$hp, $hs, $ht]) {
            $hcal = $this->calibration[$hp] ?? null;
            if ($hp === $pi || $hcal === null) {
                continue;
            }
            $hc = $this->parts[$hp];
            $hcum = $this->cumulative[$hp];
            [$pk] = self::estimate($hcal, $hcum[$hs] + ($hcum[$hs + 1] - $hcum[$hs]) * $ht, $hcum[count($hcum) - 1]);
            if ($pk === null) {
                continue;
            }
            $grows = TrackGeometry::direction($hc[2 * $hs], $hc[2 * $hs + 1], $hc[2 * $hs + 2], $hc[2 * $hs + 3]);
            $around[] = [$pk, $hcal['reversed'] ? [-$grows[0], -$grows[1]] : $grows];
        }
        $inward = TrackGeometry::direction($c[0], $c[1], $c[2], $c[3]);
        foreach ($around as [$low, $grows]) {
            foreach ($around as [$high]) {
                $scale = ($high - $low) / max(1.0, $length);
                if ($scale >= self::SCALE_MIN && $scale <= self::SCALE_MAX) {
                    // The lower part runs into the turn: the start of the turn when it leaves the node that way.
                    $forward = $grows[0] * $inward[0] + $grows[1] * $inward[1] >= 0.0;

                    return ['reversed' => ! $forward, 'anchors' => [[0.0, $low], [$length, $high]], 'inherited' => true];
                }
            }
        }

        return null;
    }

    /**
     * Parts without a calibration take the PK of the calibrated parts touching their ends, until nothing changes.
     *
     * @param  array<int, list<array{0: float, 1: int}>>  $perPart  own anchors of each part
     */
    private function inherit(array $perPart): void
    {
        do {
            $changed = false;
            foreach ($this->parts as $pi => $c) {
                if ($this->calibration[$pi] !== null) {
                    continue;
                }
                $cum = $this->cumulative[$pi];
                $length = $cum[count($cum) - 1];
                $n = count($c);
                // Each end: distance along the part, the end, the next vertex towards the inside.
                $ends = [[0.0, $c[0], $c[1], $c[2], $c[3]], [$length, $c[$n - 2], $c[$n - 1], $c[$n - 4], $c[$n - 3]]];
                $own = $perPart[$pi] ?? [];
                $touches = [];
                foreach ($ends as [$along, $lon, $lat, $nextLon, $nextLat]) {
                    $hit = $this->nearest($lon, $lat, self::JOIN_M, $pi, true);
                    if ($hit === null) {
                        continue;
                    }
                    [$hp, $hs, $ht] = $hit;
                    $hc = $this->parts[$hp];
                    $hcum = $this->cumulative[$hp];
                    $hcal = $this->calibration[$hp];
                    assert($hcal !== null);
                    [$pk] = self::estimate($hcal, $hcum[$hs] + ($hcum[$hs + 1] - $hcum[$hs]) * $ht, $hcum[count($hcum) - 1]);
                    if ($pk === null) {
                        continue;
                    }
                    $grows = TrackGeometry::direction($hc[2 * $hs], $hc[2 * $hs + 1], $hc[2 * $hs + 2], $hc[2 * $hs + 3]);
                    $inward = TrackGeometry::direction($lon, $lat, $nextLon, $nextLat);
                    $dot = ($grows[0] * $inward[0] + $grows[1] * $inward[1]) * ($hcal['reversed'] ? -1 : 1);
                    $touches[] = [$along, $pk, $dot >= 0.0];
                }
                $closed = TrackGeometry::metres($c[0], $c[1], $c[$n - 2], $c[$n - 1]) <= self::JOIN_M;
                if ($closed && $own === []) {
                    $fit = $this->closedLoop($pi);
                    if ($fit !== null) {
                        $this->calibration[$pi] = $fit;
                        $changed = true;
                    }

                    continue;
                }
                if ($touches === []) {
                    continue;
                }
                $fit = self::fit([...$own, ...array_map(fn (array $t): array => [$t[0], $t[1]], $touches)], $length);
                if ($fit !== null && $own !== [] && self::runs($fit['anchors']) !== [$fit['anchors']]) {
                    // The PK of a touching part breaks with the anchor of the part (a helix or a loop between them):
                    // the part keeps its anchor and only takes the direction of the PK from the touching part, the way
                    // its PK grows into the part (the sign of the break says nothing about it).
                    [$a0, $p0] = $own[0];
                    [$ta, , $growsInto] = $touches[0];
                    $up = ($ta === 0.0) === $growsInto;
                    $d0 = $up ? $a0 : $length - $a0;
                    $fit = ['reversed' => ! $up, 'anchors' => [[$d0, $p0], [$d0 + 1000.0, $p0 + 1000]], 'inherited' => true];
                }
                if ($fit !== null && $own === [] && count($touches) === 2) {
                    // Both ends inherited: the PK they span must match the length of the part (a part bridging two
                    // places of the trace that the PK does not join, a spiral, a duplicate, keeps one end only).
                    $scale = abs($touches[1][1] - $touches[0][1]) / max(1.0, $length);
                    if ($scale < self::SCALE_MIN || $scale > self::SCALE_MAX) {
                        $fit = null;
                    }
                }
                if ($fit === null && $own === []) {
                    // A single anchor at one end: the PK grows into the part when the touched part grows that way.
                    [$along, $pk, $growsInto] = $touches[0];
                    $span = (int) round($length);
                    $fit = [
                        'reversed' => $along === 0.0 ? ! $growsInto : $growsInto,
                        'anchors' => $growsInto ? [[0.0, $pk], [$length, $pk + $span]] : [[0.0, $pk - $span], [$length, $pk]],
                        'inherited' => true,
                    ];
                } elseif ($fit !== null) {
                    $fit['inherited'] = true;
                }
                if ($fit !== null) {
                    $this->calibration[$pi] = $fit;
                    $changed = true;
                }
            }
        } while ($changed);
    }
}
