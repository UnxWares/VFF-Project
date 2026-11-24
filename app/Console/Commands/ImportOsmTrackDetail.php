<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tracks\Osm\OsmTrackExtract;
use App\Services\Tracks\Osm\TrackAttacher;
use App\Services\Tracks\Osm\TrackNetwork;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Imports the detailed track layout of OpenStreetMap (ODbL) for the track layout diagram of the lines
 * (`GET /api/v1/lines/{line}/track-layout`, source `osm`): every track of a line in service today (running tracks,
 * loops, sidings, yards, crossovers, spurs) with its PK range, and its switches, diamond crossings, buffer stops,
 * level crossings, signals and milestones, attached and placed by TrackAttacher on the chainage of each line
 * (TrackChainage). Reads the OPL file written by `bin/osm-track-detail` from the Geofabrik France extract; reads
 * VFF without writing (TrackNetwork); writes only `osm_tracks`, `osm_track_nodes` and `osm_track_imports`, line
 * by line (the rows of an imported line are replaced, a full run also drops the lines no longer attached).
 *
 *   bin/osm-track-detail france-latest.osm.pbf storage/app/sources/osm/track-detail
 *   php artisan vff:import-osm-track-detail --file=storage/app/sources/osm/track-detail/track-detail.opl.gz --dry-run
 *   php artisan vff:import-osm-track-detail --file=... --line=570000 --line=rfn-830000
 */
class ImportOsmTrackDetail extends Command
{
    protected $signature = 'vff:import-osm-track-detail
        {--file= : OPL file written by bin/osm-track-detail (plain or .gz)}
        {--line=* : line codes or slugs (default: every line in service)}
        {--dry-run : compute and report without writing}';

    protected $description = 'Imports the OpenStreetMap tracks, switches, crossings and signals of the lines in service (track layout).';

    /** Margin (degrees) around the lines asked for: neighbouring lines decide the shared and parallel tracks. */
    private const BBOX_MARGIN_DEG = 0.02;

    private const CHUNK = 1000;

    public function handle(TrackNetwork $network): int
    {
        ini_set('memory_limit', '3G');
        DB::statement("SET statement_timeout = '30s'");
        $file = (string) $this->option('file');
        if ($file === '' || ! is_file($file) || ! is_readable($file)) {
            $this->error('Unreadable file: '.($file === '' ? '(--file missing)' : $file));

            return self::FAILURE;
        }
        $eraId = $network->currentEraId();
        if ($eraId === null) {
            $this->error('No current era.');

            return self::FAILURE;
        }

        $targets = null;
        $bbox = null;
        if ($asked = $this->option('line')) {
            $targets = DB::table('lines')->whereIn('code', $asked)->orWhereIn('slug', $asked)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if ($targets === []) {
                $this->error('Unknown lines: '.implode(', ', $asked));

                return self::FAILURE;
            }
            $bbox = $network->bbox($eraId, $targets, self::BBOX_MARGIN_DEG);
            if ($bbox === null) {
                $this->warn('No trace in service for these lines: nothing to attach.');

                return self::SUCCESS;
            }
        }

        $started = microtime(true);
        $osm = OsmTrackExtract::fromFile($file, $bbox);
        $this->line(sprintf('OSM: %d ways, %d nodes, data up to %s (%.0f s)', count($osm->ways), count($osm->nodes), $osm->latest ?? '?', microtime(true) - $started));
        $anchors = TrackAttacher::osmAnchors($osm);
        $lines = $network->load($eraId, $network->lineIds($eraId, $bbox), $anchors);
        $this->line(sprintf('VFF: %d lines in service read (%.0f s)', count($lines), microtime(true) - $started));
        $result = (new TrackAttacher($osm, $lines, $network->knownCodes()))->attach($targets);
        $this->line(sprintf('Attached: %d tracks, %d nodes (%.0f s, %d MB)', count($result['tracks']), count($result['nodes']), microtime(true) - $started, intdiv(memory_get_peak_usage(true), 1 << 20)));

        $this->report($result, $targets !== null);
        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing written.');

            return self::SUCCESS;
        }
        $this->write($result, $targets, $file, $osm->latest);
        $this->info('Written.');

        return self::SUCCESS;
    }

    /**
     * Kilometres of track by kind and nodes by kind, per line (with --line) and in total.
     *
     * @param  array{tracks: list<array<string, mixed>>, nodes: list<array<string, mixed>>}  $result
     */
    private function report(array $result, bool $perLine): void
    {
        $empty = ['km' => [], 'nodes' => [], 'no_pk' => 0];
        /** @var array<int|string, array{km: array<string, float>, nodes: array<string, int>, no_pk: int}> $stats */
        $stats = ['total' => $empty];
        foreach ($result['tracks'] as $t) {
            foreach (array_unique([(int) $t['line_id'], 'total']) as $key) {
                $stats[$key] ??= $empty;
                $stats[$key]['km'][(string) $t['kind']] = ($stats[$key]['km'][(string) $t['kind']] ?? 0.0) + $t['length_m'] / 1000;
                $stats[$key]['no_pk'] += $t['pk_from_m'] === null ? 1 : 0;
            }
        }
        foreach ($result['nodes'] as $n) {
            $kind = $n['kind'] === 'switch' && $n['subtype'] === null ? 'junction' : (string) $n['kind'];
            foreach ([(int) $n['line_id'], 'total'] as $key) {
                $stats[$key] ??= $empty;
                $stats[$key]['nodes'][$kind] = ($stats[$key]['nodes'][$kind] ?? 0) + 1;
            }
        }
        $codes = DB::table('lines')->whereIn('id', array_filter(array_keys($stats), 'is_int'))->pluck('code', 'id');
        $km = fn (array $s, string ...$kinds): string => number_format(array_sum(array_map(fn (string $k): float => $s['km'][$k] ?? 0.0, $kinds)), 1, '.', '');
        $row = fn (string $name, array $s): array => [
            $name,
            number_format(array_sum($s['km']), 1, '.', ''),
            $km($s, 'running'),
            $km($s, 'loop', 'siding'),
            $km($s, 'yard', 'spur', 'crossover'),
            ($s['nodes']['switch'] ?? 0).' + '.($s['nodes']['junction'] ?? 0),
            $s['nodes']['railway_crossing'] ?? 0,
            ($s['nodes']['level_crossing'] ?? 0).' + '.($s['nodes']['crossing'] ?? 0),
            $s['nodes']['signal'] ?? 0,
            $s['nodes']['buffer_stop'] ?? 0,
            $s['nodes']['milestone'] ?? 0,
            $s['no_pk'],
        ];
        $rows = [];
        foreach ($stats as $key => $s) {
            if ($perLine && $key !== 'total') {
                $rows[] = $row((string) ($codes[$key] ?? $key), $s);
            }
        }
        $rows[] = $row((count($stats) - 1).' lines', $stats['total']);
        $this->table(['Line', 'km', 'running', 'loops+sidings', 'yards+spurs+xovers', 'switches (tagged + untagged)', 'diamonds',
            'level xings (road + foot)', 'signals', 'buffer stops', 'milestones', 'tracks w/o PK'], $rows);
    }

    /**
     * @param  array{tracks: list<array<string, mixed>>, nodes: list<array<string, mixed>>}  $result
     * @param  list<int>|null  $targets
     */
    private function write(array $result, ?array $targets, string $file, ?string $latest): void
    {
        DB::transaction(function () use ($result, $targets, $file, $latest): void {
            DB::statement("SET LOCAL statement_timeout = '600s'");
            if ($targets === null) {
                DB::table('osm_track_nodes')->delete();
                DB::table('osm_tracks')->delete();
            } else {
                DB::table('osm_track_nodes')->whereIn('line_id', $targets)->delete();
                DB::table('osm_tracks')->whereIn('line_id', $targets)->delete();
            }
            foreach (array_chunk($result['tracks'], self::CHUNK) as $chunk) {
                DB::statement(<<<'SQL'
                    INSERT INTO osm_tracks (line_id, osm_way_id, part, kind, ref, service, usage, pk_from_m, pk_to_m, pk_up,
                        pk_precision, length_m, offset_m, electrified, voltage_v, maxspeed_kmh, gauge_mm, station_id, attach,
                        start_node_id, end_node_id, tags, geom, created_at, updated_at)
                    SELECT (r->>'line_id')::bigint, (r->>'osm_way_id')::bigint, (r->>'part')::smallint, r->>'kind', r->>'ref',
                        r->>'service', r->>'usage', (r->>'pk_from_m')::int, (r->>'pk_to_m')::int, (r->>'pk_up')::boolean,
                        r->>'pk_precision', (r->>'length_m')::int, (r->>'offset_m')::real, (r->>'electrified')::boolean,
                        (r->>'voltage_v')::int, (r->>'maxspeed_kmh')::smallint, (r->>'gauge_mm')::smallint,
                        (r->>'station_id')::bigint, r->>'attach', (r->>'start_node_id')::bigint, (r->>'end_node_id')::bigint,
                        COALESCE(r->'tags', '{}'::jsonb), ST_SetSRID(ST_GeomFromGeoJSON(r->>'geom'), 4326), NOW(), NOW()
                    FROM jsonb_array_elements(CAST(? AS jsonb)) r
                    SQL, [json_encode(array_map(fn (array $t): array => self::trackRow($t), $chunk), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
            }
            foreach (array_chunk($result['nodes'], self::CHUNK) as $chunk) {
                DB::statement(<<<'SQL'
                    INSERT INTO osm_track_nodes (line_id, osm_node_id, kind, subtype, detail, ref, pk_m, pk_precision, osm_pk_m,
                        way_id, to_way_id, direction, tags, geom, created_at, updated_at)
                    SELECT (r->>'line_id')::bigint, (r->>'osm_node_id')::bigint, r->>'kind', r->>'subtype', r->>'detail', r->>'ref',
                        (r->>'pk_m')::int, r->>'pk_precision', (r->>'osm_pk_m')::int, (r->>'way_id')::bigint, (r->>'to_way_id')::bigint,
                        r->>'direction', COALESCE(r->'tags', '{}'::jsonb),
                        ST_SetSRID(ST_MakePoint((r->>'lon')::float8, (r->>'lat')::float8), 4326), NOW(), NOW()
                    FROM jsonb_array_elements(CAST(? AS jsonb)) r
                    SQL, [json_encode(array_map(fn (array $n): array => ['tags' => $n['tags'] === [] ? new \stdClass : $n['tags']] + $n, $chunk), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
            }
            DB::table('osm_track_imports')->insert([
                'file' => mb_substr(basename($file), 0, 255),
                'data_at' => $latest === null ? null : Carbon::parse($latest)->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s'),
                'lines' => count(array_unique(array_column($result['tracks'], 'line_id'))),
                'tracks' => count($result['tracks']),
                'nodes' => count($result['nodes']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    private static function trackRow(array $t): array
    {
        $coords = [];
        for ($i = 0, $n = count($t['coords']); $i + 1 < $n; $i += 2) {
            $coords[] = [round($t['coords'][$i], 7), round($t['coords'][$i + 1], 7)];
        }
        $row = $t;
        unset($row['coords']);
        $row['tags'] = $t['tags'] === [] ? new \stdClass : $t['tags'];
        $row['geom'] = json_encode(['type' => 'LineString', 'coordinates' => $coords], JSON_THROW_ON_ERROR);

        return $row;
    }
}
