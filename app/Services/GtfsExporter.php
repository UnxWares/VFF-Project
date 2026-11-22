<?php

namespace App\Services;

use App\Models\Era;
use App\Services\TraceExport\LineTraceExport;
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
     * Lignes qui font un trajet : au moins deux arrêts desservis à l'ère (un trajet d'un seul arrêt
     * est inutilisable en GTFS). Un seul paramètre : l'identifiant de l'ère.
     */
    private const TRIP_LINES_SQL = "SELECT x.line_id FROM line_stops x JOIN stations xs ON xs.id = x.station_id AND xs.geom IS NOT NULL
        WHERE x.era_id = ? AND x.effective_status IN ('active', 'tourist', 'no_commercial')
        GROUP BY x.line_id HAVING COUNT(*) >= 2";

    /** Vitesse des horaires synthétiques (m/h), pour que les durées entre arrêts restent plausibles. */
    private const SYNTHETIC_SPEED_M_H = 60_000;

    /** Règle des droits des tracés ; résolue à l'usage quand l'exporteur est construit sans conteneur. */
    public function __construct(private readonly ?LineTraceExport $traceRights = null) {}

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
        // Tracés d'abord : un trip ne cite un shape_id que s'il a des points, et les distances des
        // arrêts sont mesurées le long de ce tracé.
        $stopDistances = [];
        $shaped = $this->streamShapes($era, $shapesPath, $stopDistances);
        $this->streamStopTimes($era, $stopTimesPath, $stopDistances);

        $smallFiles = [
            'agency.txt' => $this->buildAgency(),
            'routes.txt' => $this->buildRoutes($era),
            'stops.txt' => $this->buildStops($era),
            'trips.txt' => $this->buildTrips($era, $shaped),
            'calendar.txt' => $this->buildCalendar($era),
            'feed_info.txt' => $this->buildFeedInfo($era),
            'attributions.txt' => $this->buildAttributions(),
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
             JOIN stations s ON s.id = ls.station_id AND s.geom IS NOT NULL
             WHERE ls.era_id = ? AND ls.effective_status IN (\'active\', \'tourist\', \'no_commercial\')
               AND ls.line_id IN ('.self::TRIP_LINES_SQL.')',
            [$era->id, $era->id],
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
             WHERE ls.era_id = ? AND ls.effective_status IN (\'active\', \'tourist\', \'no_commercial\')
               AND ls.line_id IN ('.self::TRIP_LINES_SQL.')
               AND s.geom IS NOT NULL',
            [$era->id, $era->id],
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
             JOIN stations sg ON sg.id = ls.station_id AND sg.geom IS NOT NULL
             WHERE ls.era_id = ? AND ls.effective_status IN (\'active\', \'tourist\', \'no_commercial\')
               AND ls.line_id IN ('.self::TRIP_LINES_SQL.')',
            [$era->id, $era->id],
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

    /** Distance à vol d'oiseau en mètres entre deux points (haversine). */
    private function crowFlies(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6_371_000 * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param  array<int, array<int, float>>  $stopDistances  ligne => [position => distance en mètres le long de la forme]
     */
    private function streamStopTimes(Era $era, string $path, array $stopDistances): void
    {
        $fp = fopen($path, 'w');
        fputcsv($fp, ['trip_id', 'arrival_time', 'departure_time', 'stop_id', 'stop_sequence', 'shape_dist_traveled'], ',', '"', '\\');

        $lastLine = null;
        $lastPoint = null;
        $crowSeconds = 0;
        DB::table('line_stops')
            ->join('stations', 'stations.id', '=', 'line_stops.station_id')
            ->where('line_stops.era_id', $era->id)
            ->whereIn('line_stops.effective_status', ['active', 'tourist', 'no_commercial'])
            ->whereNotNull('stations.geom')
            ->whereRaw('line_stops.line_id IN ('.self::TRIP_LINES_SQL.')', [$era->id])
            ->orderBy('line_stops.line_id')
            ->orderBy('line_stops.position')
            ->selectRaw('line_stops.line_id, line_stops.station_id, line_stops.position, ST_X(stations.geom::geometry) AS lng, ST_Y(stations.geom::geometry) AS lat')
            ->chunk(5000, function ($stops) use ($fp, $stopDistances, &$lastLine, &$lastPoint, &$crowSeconds) {
                foreach ($stops as $s) {
                    // Distance le long de la forme du trajet, dans la même unité que shapes.txt ; vide
                    // pour tout le trajet quand elle n'est pas fiable (pas de forme, ordre incohérent).
                    $dist = $stopDistances[(int) $s->line_id][(int) $s->position] ?? null;
                    // Horaire synthétique : 60 km/h le long de la forme, sinon une minute par position.
                    if ($lastLine !== (int) $s->line_id) {
                        $lastLine = (int) $s->line_id;
                        $lastPoint = null;
                        $crowSeconds = 0;
                    }
                    if ($lastPoint !== null) {
                        $crowSeconds += max(60, (int) round($this->crowFlies($lastPoint[0], $lastPoint[1], (float) $s->lat, (float) $s->lng) * 3600 / self::SYNTHETIC_SPEED_M_H));
                    }
                    $lastPoint = [(float) $s->lat, (float) $s->lng];
                    $seconds = $dist === null ? $crowSeconds : (int) round($dist * 3600 / self::SYNTHETIC_SPEED_M_H);
                    $t = sprintf('%02d:%02d:%02d', intdiv($seconds, 3600) + 8, intdiv($seconds % 3600, 60), $seconds % 60);
                    fputcsv($fp, ["T{$s->line_id}", $t, $t, "S{$s->station_id}", $s->position + 1, $dist === null ? '' : $dist], ',', '"', '\\');
                }
            });
        fclose($fp);
    }

    /**
     * Stream shapes : 1 SQL par ligne avec ST_X/ST_Y + cumDist en SQL.
     *
     * Règle des droits (LineTraceExport) : une forme est un tracé continu, elle n'est donc écrite
     * que si TOUS les tronçons exploités de la ligne à l'ère viennent d'une source qui autorise la
     * redistribution. Sinon la ligne n'a pas de forme (son trajet garde ses arrêts, sans
     * `shape_id`) : une forme amputée de ses parties retenues serait fausse.
     *
     * Remplit aussi `$stopDistances` : pour chaque ligne dont la forme est une seule polyligne, la
     * distance de chaque arrêt le long de la forme, orientée dans le sens des positions. Si cet ordre
     * n'est pas croissant (tracé replié, gare hors tracé), la ligne n'y figure pas : GTFS exige des
     * distances croissantes et, pour un trajet, toutes ou aucune.
     *
     * @param  array<int, array<int, float>>  $stopDistances
     * @return array<int, true> lignes dont le tracé a au moins un point
     */
    private function streamShapes(Era $era, string $path, array &$stopDistances): array
    {
        $fp = fopen($path, 'w');
        fputcsv($fp, ['shape_id', 'shape_pt_lat', 'shape_pt_lon', 'shape_pt_sequence', 'shape_dist_traveled'], ',', '"', '\\');

        // Seulement les lignes qui auront un trip (au moins un arrêt desservi).
        $lineIds = DB::table('line_stops')
            ->join('stations', 'stations.id', '=', 'line_stops.station_id')
            ->where('line_stops.era_id', $era->id)
            ->whereIn('line_stops.effective_status', ['active', 'tourist', 'no_commercial'])
            ->whereNotNull('stations.geom')
            ->whereRaw('line_stops.line_id IN ('.self::TRIP_LINES_SQL.')', [$era->id])
            ->distinct()
            ->pluck('line_stops.line_id');
        $shaped = [];
        $withheld = array_flip($this->withheldShapeLines($era));

        foreach ($lineIds as $lineId) {
            if (isset($withheld[(int) $lineId])) {
                continue;
            }
            // Sens de la forme : celui des positions des arrêts (la fusion des tronçons n'en impose aucun).
            [$distances, $reversed] = $this->stopDistancesAlongShape((int) $lineId, $era);
            $pts = DB::select(<<<'SQL'
                WITH dumped AS (
                    SELECT (ST_DumpPoints(CASE WHEN ?::boolean THEN ST_Reverse(ST_LineMerge(ST_Collect(geom))::geometry)
                                               ELSE ST_LineMerge(ST_Collect(geom))::geometry END)).geom AS g
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
                ),
                cumulated AS (
                    SELECT seq, lng, lat, step_m,
                           ROUND(SUM(step_m) OVER (ORDER BY seq ROWS UNBOUNDED PRECEDING)::numeric, 2)::float8 AS cum_m
                    FROM with_dist
                )
                SELECT lng, lat, cum_m
                FROM cumulated
                WHERE seq = 1 OR step_m >= 0.1
                ORDER BY seq
                SQL, [$reversed ? 'true' : 'false', $lineId, $era->id]);

            // Points à moins de 10 cm du précédent écartés : GTFS veut des distances strictement croissantes.
            foreach ($pts as $i => $p) {
                fputcsv($fp, ["shape-{$lineId}", $p->lat, $p->lng, $i + 1, $p->cum_m], ',', '"', '\\');
            }
            if ($pts) {
                $shaped[(int) $lineId] = true;
                if ($distances !== []) {
                    $stopDistances[(int) $lineId] = $distances;
                }
            }
        }
        fclose($fp);

        return $shaped;
    }

    /**
     * Distance (m) de chaque arrêt desservi le long de la forme de la ligne, par position, et si la
     * forme doit être inversée pour suivre le sens des positions. Distances `[]` quand elles ne
     * peuvent pas être données proprement (forme en plusieurs morceaux, ordre non strictement
     * croissant).
     *
     * @return array{0: array<int, float>, 1: bool}
     */
    private function stopDistancesAlongShape(int $lineId, Era $era): array
    {
        $rows = DB::select(<<<'SQL'
            WITH merged AS (
                SELECT ST_LineMerge(ST_Collect(geom))::geometry AS g
                FROM line_segments
                WHERE line_id = ? AND era_id = ? AND status IN ('in_service','single_track')
            )
            SELECT ls.position,
                   ST_LineLocatePoint(m.g, s.geom::geometry) * ST_Length(m.g::geography) AS dist_m,
                   ST_Length(m.g::geography) AS length_m
            FROM line_stops ls
            JOIN stations s ON s.id = ls.station_id
            CROSS JOIN merged m
            WHERE ls.line_id = ? AND ls.era_id = ?
              AND ls.effective_status IN ('active', 'tourist', 'no_commercial')
              AND s.geom IS NOT NULL
              AND GeometryType(m.g) = 'LINESTRING'
            ORDER BY ls.position
            SQL, [$lineId, $era->id, $lineId, $era->id]);
        if ($rows === []) {
            return [[], false];
        }

        $values = array_map(fn ($r) => (float) $r->dist_m, $rows);
        $reversed = count($values) > 1 && $values[0] > $values[count($values) - 1];
        if ($reversed) {
            $length = (float) $rows[0]->length_m;
            $values = array_map(fn (float $v) => $length - $v, $values);
        }
        $distances = [];
        $previous = -1.0;
        foreach ($rows as $i => $r) {
            $v = round($values[$i], 2);
            if ($v <= $previous) {
                return [[], $reversed];
            }
            $previous = $v;
            $distances[(int) $r->position] = $v;
        }

        return [$distances, $reversed];
    }

    /**
     * Lignes dont la forme n'est pas exportée à cette ère : au moins un tronçon exploité vient
     * d'une source dont la licence n'autorise pas la redistribution (ou non déclarée).
     *
     * @return list<int>
     */
    public function withheldShapeLines(Era $era): array
    {
        $withheld = ($this->traceRights ?? app(LineTraceExport::class))->sqlWithheld('source');

        return array_map('intval', DB::table('line_segments')
            ->where('era_id', $era->id)
            ->whereIn('status', ['in_service', 'single_track'])
            ->whereRaw($withheld)
            ->distinct()
            ->orderBy('line_id')
            ->pluck('line_id')
            ->all());
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
            ['feed_publisher_name', 'feed_publisher_url', 'feed_lang', 'feed_start_date', 'feed_end_date', 'feed_version', 'feed_contact_email', 'feed_contact_url'],
            ['VFF - Voies Ferrées de France', 'https://vff-project.org', 'fr', "{$era->year}0101", "{$era->year}1231", 'vff-'.now()->format('Y-m-d'), 'contact@vff-project.org', 'https://vff-project.org/legal'],
        ]);
    }

    /**
     * Attributions et licence : VFF produit le flux, dont les données géographiques viennent
     * d'OpenStreetMap et de la SNCF, toutes deux sous licence ODbL 1.0.
     */
    private function buildAttributions(): string
    {
        return $this->toCsv([
            ['attribution_id', 'organization_name', 'is_producer', 'is_operator', 'is_authority', 'attribution_url'],
            ['vff', 'VFF - Voies Ferrées de France (UnxWares), données sous licence ODbL 1.0', '1', '0', '0', 'https://vff-project.org/legal'],
            ['osm', 'Contributeurs OpenStreetMap, licence ODbL 1.0', '1', '0', '0', 'https://www.openstreetmap.org/copyright'],
            ['sncf', 'SNCF, données ouvertes sous licence ODbL 1.0', '1', '0', '0', 'https://ressources.data.sncf.com'],
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
