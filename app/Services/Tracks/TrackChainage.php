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
 * trace is left out (a station building is not where its PK is read). Each part is calibrated on its own:
 * the longest run of anchors whose PK grows along the part, in whichever direction keeps more of them, then a
 * spike filter drops an anchor whose two neighbouring intervals both stretch or squeeze the PK beyond
 * SCALE_MIN..SCALE_MAX (one wrong anchor), never a genuine jump of the PK numbering (one interval only).
 * A part left with fewer than two anchors takes the PK of the calibrated parts it touches (within JOIN_M of
 * one of its ends), its direction given by a second anchor or else by the direction of the part it leaves.
 *
 * Precision: `derived` between two anchors less than DERIVED_GAP_M apart and up to EXTRAPOLATE_M beyond the
 * outer ones, `approximate` elsewhere (long gap, extrapolation, PK inherited from a neighbouring part). A part
 * without any PK gives none. Pure (tests: tests/Unit/Tracks/TrackChainageTest.php).
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

    /** Grid cell (degrees) of the segment index. */
    private const CELL_DEG = 0.005;

    /** @var list<list<float>> */
    private array $parts;

    /** @var list<list<float>> cumulative length at each vertex, per part */
    private array $cumulative;

    /** @var array<int, list<int>> cell key => part * SEG_BASE + segment */
    private array $grid = [];

    /** @var list<array{reversed: bool, anchors: list<array{0: float, 1: int}>, inherited: bool}|null> calibration per part */
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
     *
     * @return array{pk_m: ?int, precision: ?string, distance_m: float, offset_m: float, along_m: float, part: int, direction: array{0: float, 1: float}}|null
     */
    public function locate(float $lon, float $lat, float $maxDistance = 30.0): ?array
    {
        $best = $this->nearest($lon, $lat, $maxDistance);
        if ($best === null) {
            return null;
        }
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
            [$pi, $seg, $t] = $best;
            $cum = $this->cumulative[$pi];
            $located[$pi][] = [$cum[$seg] + ($cum[$seg + 1] - $cum[$seg]) * $t, $a['pk_m'], $a['strong'] ?? false];
        }
        $perPart = [];
        foreach ($located as $pi => $list) {
            $strong = array_values(array_filter(array_map(fn (array $a): ?float => $a[2] ? $a[0] : null, $list), fn (?float $d): bool => $d !== null));
            sort($strong);
            foreach ($list as [$along, $pk, $isStrong]) {
                if ($isStrong || ! self::within($strong, $along, self::SHADOW_M)) {
                    $perPart[$pi][] = [$along, $pk];
                }
            }
        }
        foreach ($this->parts as $pi => $c) {
            $this->calibration[$pi] = self::fit($perPart[$pi] ?? [], $this->cumulative[$pi][count($this->cumulative[$pi]) - 1]);
        }
        $this->inherit($perPart);
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
        $forward = self::despike(PkCalibration::monotone($anchors));
        $backward = self::despike(PkCalibration::monotone(array_map(fn (array $a): array => [$length - $a[0], $a[1]], $anchors)));
        $reversed = count($backward) > count($forward);
        $kept = $reversed ? $backward : $forward;

        return count($kept) >= 2 ? ['reversed' => $reversed, 'anchors' => $kept, 'inherited' => false] : null;
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
     * PK and precision at a distance along a calibrated part.
     *
     * @param  array{reversed: bool, anchors: list<array{0: float, 1: int}>, inherited: bool}  $cal
     * @return array{0: int, 1: string}
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
            $pk = $p0 + ($p1 - $p0) * ($d - $d0) / ($d1 - $d0);
            $precise = $d1 - $d0 <= self::DERIVED_GAP_M;
        }

        return [(int) round($pk), $precise && ! $cal['inherited'] ? 'derived' : 'approximate'];
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
                    $grows = TrackGeometry::direction($hc[2 * $hs], $hc[2 * $hs + 1], $hc[2 * $hs + 2], $hc[2 * $hs + 3]);
                    $inward = TrackGeometry::direction($lon, $lat, $nextLon, $nextLat);
                    $dot = ($grows[0] * $inward[0] + $grows[1] * $inward[1]) * ($hcal['reversed'] ? -1 : 1);
                    $touches[] = [$along, $pk, $dot >= 0.0];
                }
                if ($touches === []) {
                    continue;
                }
                $fit = self::fit([...$own, ...array_map(fn (array $t): array => [$t[0], $t[1]], $touches)], $length);
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
