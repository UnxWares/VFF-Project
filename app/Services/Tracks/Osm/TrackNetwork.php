<?php

declare(strict_types=1);

namespace App\Services\Tracks\Osm;

use App\Services\Signalling\LineLandmarksFile;
use App\Services\Tracks\TrackChainage;
use Illuminate\Support\Facades\DB;

/**
 * Reads, line by line and without writing anything, what the OpenStreetMap track import needs from VFF: the lines
 * in service in the current era (`line_segments` in service, merged by ST_LineMerge, simplified to SIMPLIFY_DEG),
 * their stations and the sourced PKs that calibrate their chainage (stations with a sourced PK and remarkable
 * points of the line, weak; SNCF Reseau level crossings of the line and OpenStreetMap PKs of the line, strong).
 */
final class TrackNetwork
{
    /** PK origins accepted as anchors (station_lines.pk_source): never the raw abscissa of a trace. */
    public const CALIBRATED = ['sncf', 'projected', 'lignes-oubliees', 'wikipedia_schema', 'correction'];

    /** Simplification of the reference trace (degrees, about 0.3 m). */
    private const SIMPLIFY_DEG = 0.000003;

    /** A station anchors the PK within this distance of the trace; a level crossing within LEVEL_CROSSING_M. */
    private const STATION_ANCHOR_M = 500.0;

    private const LEVEL_CROSSING_M = 50.0;

    /** A remarkable point of the line (line_landmarks.php) anchors the PK within this distance of the trace. */
    private const LANDMARK_M = 100.0;

    /** @var array<int|string, list<array{name: string, keys: list<string>, kind: string, pk_m: int, lat: float, lon: float, method: string}>>|null */
    private ?array $landmarks = null;

    /** Stations farther than this from the trace are not stations of the line for the track import. */
    private const STATION_TRACE_M = 1000.0;

    public function currentEraId(): ?int
    {
        $id = DB::table('eras')->where('is_current', true)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Lines in service in the era, optionally those whose trace crosses a bounding box.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     * @return list<int>
     */
    public function lineIds(int $eraId, ?array $bbox = null): array
    {
        $q = DB::table('line_segments')->where('era_id', $eraId)->where('status', 'in_service');
        if ($bbox !== null) {
            $q->whereRaw('geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', $bbox);
        }

        return $q->distinct()->orderBy('line_id')->pluck('line_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Bounding box of the traces in service of some lines, widened by $margin degrees, or null without trace.
     *
     * @param  list<int>  $lineIds
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public function bbox(int $eraId, array $lineIds, float $margin): ?array
    {
        if ($lineIds === []) {
            return null;
        }
        $box = DB::table('line_segments')->where('era_id', $eraId)->where('status', 'in_service')->whereIn('line_id', $lineIds)
            ->selectRaw('ST_XMin(ST_Extent(geom)) AS x0, ST_YMin(ST_Extent(geom)) AS y0, ST_XMax(ST_Extent(geom)) AS x1, ST_YMax(ST_Extent(geom)) AS y1')
            ->first();
        if ($box === null || $box->x0 === null) {
            return null;
        }

        return [(float) $box->x0 - $margin, (float) $box->y0 - $margin, (float) $box->x1 + $margin, (float) $box->y1 + $margin];
    }

    /**
     * Codes of every VFF line, in service or not.
     *
     * @return array<string, true>
     */
    public function knownCodes(): array
    {
        return array_fill_keys(DB::table('lines')->whereNotNull('code')->where('code', '<>', '')->distinct()->pluck('code')->map(fn ($c): string => (string) $c)->all(), true);
    }

    /**
     * @param  list<int>  $lineIds
     * @param  array<string, list<array{lon: float, lat: float, pk_m: int, max_m: float}>>  $osmAnchors  by line code
     * @return list<TrackLine>
     */
    public function load(int $eraId, array $lineIds, array $osmAnchors): array
    {
        if ($lineIds === []) {
            return [];
        }
        $codes = DB::table('lines')->whereIn('id', $lineIds)->pluck('code', 'id')->map(fn ($c): ?string => $c === null || $c === '' ? null : (string) $c)->all();
        $stations = [];
        foreach (DB::table('station_lines as sl')->join('stations as s', 's.id', '=', 'sl.station_id')->whereIn('sl.line_id', $lineIds)
            ->get(['sl.line_id', 's.id', DB::raw('ST_X(s.geom) AS lon'), DB::raw('ST_Y(s.geom) AS lat'), 'sl.pk_m', 'sl.pk_source']) as $r) {
            $stations[(int) $r->line_id][] = $r;
        }
        $crossings = [];
        $lineCodes = array_values(array_filter($codes));
        if ($lineCodes !== []) {
            foreach (DB::table('sncf_level_crossings')->whereIn('code_ligne', $lineCodes)->whereNotNull('pk_m')
                ->get(['code_ligne', 'pk_m', 'lon', 'lat']) as $r) {
                $crossings[(string) $r->code_ligne][] = ['lon' => (float) $r->lon, 'lat' => (float) $r->lat, 'pk_m' => (int) $r->pk_m, 'max_m' => self::LEVEL_CROSSING_M, 'strong' => true];
            }
        }

        $lines = [];
        foreach ($lineIds as $lineId) {
            $parts = $this->parts($lineId, $eraId);
            if ($parts === []) {
                continue;
            }
            $code = $codes[$lineId] ?? null;
            $anchors = [];
            foreach ($stations[$lineId] ?? [] as $s) {
                if ($s->pk_m !== null && in_array($s->pk_source, self::CALIBRATED, true)) {
                    $anchors[] = ['lon' => (float) $s->lon, 'lat' => (float) $s->lat, 'pk_m' => (int) $s->pk_m, 'max_m' => self::STATION_ANCHOR_M];
                }
            }
            if ($code !== null) {
                foreach ($this->landmarks()[$code] ?? [] as $p) {
                    $anchors[] = ['lon' => $p['lon'], 'lat' => $p['lat'], 'pk_m' => $p['pk_m'], 'max_m' => self::LANDMARK_M];
                }
                array_push($anchors, ...($crossings[$code] ?? []), ...($osmAnchors[$code] ?? []));
            }
            $chainage = new TrackChainage($parts, $anchors);
            $own = [];
            foreach ($stations[$lineId] ?? [] as $s) {
                if ($chainage->locate((float) $s->lon, (float) $s->lat, self::STATION_TRACE_M) !== null) {
                    $own[] = ['id' => (int) $s->id, 'lon' => (float) $s->lon, 'lat' => (float) $s->lat];
                }
            }
            $lines[] = new TrackLine($lineId, $code, $parts, $chainage, $own);
        }

        return $lines;
    }

    /** @return array<int|string, list<array{name: string, keys: list<string>, kind: string, pk_m: int, lat: float, lon: float, method: string}>> */
    private function landmarks(): array
    {
        return $this->landmarks ??= LineLandmarksFile::load();
    }

    /**
     * Merged parts of the trace in service of a line, as flat polylines.
     *
     * @return list<list<float>>
     */
    public function parts(int $lineId, int $eraId): array
    {
        $rows = DB::select(<<<'SQL'
            WITH merged AS (
                SELECT ST_LineMerge(ST_Collect(ST_Simplify(geom, ?))) AS g
                FROM line_segments WHERE line_id = ? AND era_id = ? AND status = 'in_service'
            )
            SELECT ST_AsGeoJSON((ST_Dump(g)).geom, 7) AS g FROM merged WHERE g IS NOT NULL
            SQL, [self::SIMPLIFY_DEG, $lineId, $eraId]);
        $parts = [];
        foreach ($rows as $r) {
            $geo = json_decode((string) $r->g, true, flags: JSON_THROW_ON_ERROR);
            if (($geo['type'] ?? null) !== 'LineString') {
                continue;
            }
            $flat = [];
            foreach ($geo['coordinates'] as [$lon, $lat]) {
                $flat[] = (float) $lon;
                $flat[] = (float) $lat;
            }
            if (count($flat) >= 4) {
                $parts[] = $flat;
            }
        }

        return $parts;
    }
}
