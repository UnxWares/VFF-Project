<?php

namespace App\Services;

use App\Models\Era;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Export du réseau VFF pour une ère donnée au format GTFS (zip).
 *
 * Spec GTFS Static : https://gtfs.org/schedule/reference/
 *
 * VFF étant un référentiel d'infrastructure (pas un planificateur d'horaires),
 * `trips.txt`, `stop_times.txt` et `calendar.txt` sont synthétiques :
 *   - 1 trip par ligne (sens unique de la position 0 vers N-1)
 *   - 1 service par défaut (calendrier monday→sunday, range = year)
 *   - pas d'arrival/departure_time (champs vides admis en GTFS-Light pour
 *     un référentiel statique, ou on met 00:00 + minutes croissantes)
 */
class GtfsExporter
{
    /**
     * Mapping VFF.line.type → GTFS route_type (selon spec GTFS étendue).
     */
    private const ROUTE_TYPE_MAP = [
        'passenger' => 2,  // Rail
        'freight' => 100,   // Railway Service (étendu)
        'mixed' => 2,       // Rail
    ];

    /**
     * Génère un zip GTFS pour une ère et retourne son chemin sur disque.
     *
     * Stratégie mémoire : les fichiers volumineux (`shapes.txt`,
     * `stop_times.txt`) sont écrits dans des fichiers temporaires en
     * streaming, puis ajoutés au zip via `addFile`.
     */
    public function exportEra(int $year, string $outputPath): string
    {
        $era = Era::where('year', $year)->firstOrFail();
        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vff-gtfs-'.uniqid();
        @mkdir($tmpDir);

        $stopTimesPath = $tmpDir.'/stop_times.txt';
        $shapesPath = $tmpDir.'/shapes.txt';
        $this->streamStopTimes($era, $stopTimesPath);
        // Tracés d'abord : un trip ne cite un shape_id que s'il a des points.
        $shaped = $this->streamShapes($era, $shapesPath);

        $smallFiles = [
            'agency.txt' => $this->buildAgency(),
            'routes.txt' => $this->buildRoutes($era),
            'stops.txt' => $this->buildStops($era),
            'trips.txt' => $this->buildTrips($era, $shaped),
            'calendar.txt' => $this->buildCalendar($era),
            'feed_info.txt' => $this->buildFeedInfo($era),
        ];

        $zip = new ZipArchive;
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create zip at {$outputPath}");
        }
        foreach ($smallFiles as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->addFile($stopTimesPath, 'stop_times.txt');
        $zip->addFile($shapesPath, 'shapes.txt');
        $zip->close();

        @unlink($stopTimesPath);
        @unlink($shapesPath);
        @rmdir($tmpDir);

        return $outputPath;
    }

    private function buildAgency(): string
    {
        $rows = [['agency_id', 'agency_name', 'agency_url', 'agency_timezone', 'agency_lang']];
        $rows[] = ['vff', 'VFF - Voies Ferrées de France', 'https://vff-project.org', 'Europe/Paris', 'fr'];

        return $this->toCsv($rows);
    }

    private function buildRoutes(Era $era): string
    {
        $rows = [['route_id', 'agency_id', 'route_short_name', 'route_long_name', 'route_type']];
        $lines = DB::select(
            'SELECT DISTINCT l.id, l.code, l.name, l.type
             FROM lines l
             JOIN line_stops ls ON ls.line_id = l.id
             WHERE ls.era_id = ? AND ls.effective_status IN (\'active\', \'tourist\', \'no_commercial\')',
            [$era->id],
        );
        foreach ($lines as $l) {
            $rows[] = [
                "L{$l->id}",
                'vff',
                $l->code ?? '',
                $l->name,
                self::ROUTE_TYPE_MAP[$l->type] ?? 2,
            ];
        }

        return $this->toCsv($rows);
    }

    private function buildStops(Era $era): string
    {
        $rows = [['stop_id', 'stop_name', 'stop_lat', 'stop_lon', 'location_type']];
        $stops = DB::select(
            'SELECT DISTINCT s.id, s.name, ST_X(s.geom::geometry) AS lng, ST_Y(s.geom::geometry) AS lat
             FROM stations s
             JOIN line_stops ls ON ls.station_id = s.id
             WHERE ls.era_id = ? AND ls.effective_status IN (\'active\', \'tourist\', \'no_commercial\')',
            [$era->id],
        );
        foreach ($stops as $s) {
            $rows[] = ["S{$s->id}", $s->name, $s->lat, $s->lng, '0'];
        }

        return $this->toCsv($rows);
    }

    /**
     * Un trip par ligne ayant au moins un arrêt desservi (mêmes lignes que
     * routes.txt : sinon route_id inconnue, flux GTFS invalide).
     *
     * @param  array<int, true>  $shaped  lignes ayant des points dans shapes.txt
     */
    private function buildTrips(Era $era, array $shaped): string
    {
        $rows = [['route_id', 'service_id', 'trip_id', 'shape_id', 'trip_headsign']];
        $lines = DB::select(
            'SELECT DISTINCT ls.line_id, l.name,
                (SELECT s.name FROM stations s JOIN line_stops ls2 ON ls2.station_id=s.id
                 WHERE ls2.line_id=ls.line_id AND ls2.era_id=ls.era_id
                   AND ls2.effective_status IN (\'active\', \'tourist\', \'no_commercial\')
                 ORDER BY ls2.position DESC LIMIT 1) AS terminus
             FROM line_stops ls
             JOIN lines l ON l.id = ls.line_id
             WHERE ls.era_id = ? AND ls.effective_status IN (\'active\', \'tourist\', \'no_commercial\')',
            [$era->id],
        );
        foreach ($lines as $l) {
            $rows[] = [
                "L{$l->line_id}",
                "service-{$era->year}",
                "T{$l->line_id}",
                // shape_id facultatif en GTFS, mais s'il est donné il doit exister.
                isset($shaped[(int) $l->line_id]) ? "shape-{$l->line_id}" : '',
                $l->terminus ?? $l->name,
            ];
        }

        return $this->toCsv($rows);
    }

    private function streamStopTimes(Era $era, string $path): void
    {
        $fp = fopen($path, 'w');
        fputcsv($fp, ['trip_id', 'arrival_time', 'departure_time', 'stop_id', 'stop_sequence', 'shape_dist_traveled'], ',', '"', '\\');

        DB::table('line_stops')
            ->where('era_id', $era->id)
            ->whereIn('effective_status', ['active', 'tourist', 'no_commercial'])
            ->orderBy('line_id')
            ->orderBy('position')
            ->select(['line_id', 'station_id', 'position', 'pk_m'])
            ->chunk(5000, function ($stops) use ($fp) {
                foreach ($stops as $s) {
                    $minutes = $s->position;
                    $t = sprintf('%02d:%02d:00', intdiv($minutes, 60) + 8, $minutes % 60);
                    fputcsv($fp, ["T{$s->line_id}", $t, $t, "S{$s->station_id}", $s->position + 1, $s->pk_m], ',', '"', '\\');
                }
            });
        fclose($fp);
    }

    /**
     * Stream shapes : 1 SQL par ligne avec ST_X/ST_Y + cumDist en SQL.
     *
     * @return array<int, true> lignes dont le tracé a au moins un point
     */
    private function streamShapes(Era $era, string $path): array
    {
        $fp = fopen($path, 'w');
        fputcsv($fp, ['shape_id', 'shape_pt_lat', 'shape_pt_lon', 'shape_pt_sequence', 'shape_dist_traveled'], ',', '"', '\\');

        // Seulement les lignes qui auront un trip (au moins un arrêt desservi).
        $lineIds = DB::table('line_stops')
            ->where('era_id', $era->id)
            ->whereIn('effective_status', ['active', 'tourist', 'no_commercial'])
            ->distinct()
            ->pluck('line_id');
        $shaped = [];

        foreach ($lineIds as $lineId) {
            $pts = DB::select(<<<'SQL'
                WITH dumped AS (
                    SELECT (ST_DumpPoints(ST_LineMerge(ST_Collect(geom))::geometry)).geom AS g
                    FROM line_segments
                    WHERE line_id = ? AND era_id = ?
                      AND status IN ('in_service','single_track')
                ),
                indexed AS (
                    SELECT ST_X(g) AS lng, ST_Y(g) AS lat, g,
                           ROW_NUMBER() OVER () AS seq
                    FROM dumped
                ),
                with_dist AS (
                    SELECT seq, lng, lat,
                           COALESCE(ST_Distance(g::geography, LAG(g) OVER (ORDER BY seq)::geography), 0) AS step_m
                    FROM indexed
                )
                SELECT seq, lng, lat,
                       SUM(step_m) OVER (ORDER BY seq ROWS UNBOUNDED PRECEDING)::int AS cum_m
                FROM with_dist
                ORDER BY seq
                SQL, [$lineId, $era->id]);

            foreach ($pts as $p) {
                fputcsv($fp, ["shape-{$lineId}", $p->lat, $p->lng, $p->seq, $p->cum_m], ',', '"', '\\');
            }
            if ($pts) {
                $shaped[(int) $lineId] = true;
            }
        }
        fclose($fp);

        return $shaped;
    }

    private function buildCalendar(Era $era): string
    {
        $rows = [
            ['service_id', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'start_date', 'end_date'],
            ["service-{$era->year}", '1', '1', '1', '1', '1', '1', '1', "{$era->year}0101", "{$era->year}1231"],
        ];

        return $this->toCsv($rows);
    }

    private function buildFeedInfo(Era $era): string
    {
        return $this->toCsv([
            ['feed_publisher_name', 'feed_publisher_url', 'feed_lang', 'feed_start_date', 'feed_end_date', 'feed_version'],
            ['VFF - Voies Ferrées de France', 'https://vff-project.org', 'fr', "{$era->year}0101", "{$era->year}1231", 'vff-'.now()->format('Y-m-d')],
        ]);
    }

    private function toCsv(array $rows): string
    {
        $out = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '\\');
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
    }
}
