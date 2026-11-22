<?php

namespace App\Services;

use App\Models\Era;
use App\Services\Gtfs\SharedTrackRouter;
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

    /*
     * Prolongement de la forme sur une voie partagée (sharedTrackExtension) : le tracé d'une ligne
     * s'arrête au début du tronc commun qu'elle partage avec une autre ligne, ses trains vont
     * jusqu'à la gare.
     */

    /** Gare terminus à plus de ça (m) de la forme : on cherche un prolongement. */
    private const SHARED_MIN_GAP_M = 30.0;

    /** La gare est au-delà du bout : sa distance au bout dépasse d'au plus ça (m) sa distance à la forme. */
    private const SHARED_ATTACH_M = 1.0;

    /**
     * Écart (m) toléré entre le bout de la forme et la voie qui la prolonge : le seul raccord droit
     * du prolongement (bifurcations dont les deux tracés ne se touchent pas tout à fait).
     */
    private const SHARED_JOIN_M = 10.0;

    /** Le prolongement doit finir à au plus ça (m) de la gare. */
    private const SHARED_REACH_M = 50.0;

    /** Longueur (m) maximale du prolongement. */
    private const SHARED_MAX_M = 2_500.0;

    /** Le prolongement mesure au plus ce multiple de la distance à vol d'oiseau du bout à la gare (plus SHARED_REACH_M). */
    private const SHARED_DETOUR = 2.0;

    /** Changement de cap (°) maximal au départ du prolongement et à chacun de ses sommets. */
    private const SHARED_TURN_DEG = 60.0;

    /** Longueur (m) de la fin de la forme sur laquelle se lit son cap d'arrivée. */
    private const SHARED_HEADING_M = 30.0;

    /** Marge (degrés) de la recherche des voies autour du segment bout - gare (environ 1 km). */
    private const SHARED_SEARCH_DEG = 0.01;

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
                self::ROUTE_TYPE_MAP[$l->type ?? ''] ?? 2,
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
     * Formes des trajets, une par ligne, écrites point par point.
     *
     * Règle des droits (LineTraceExport) : une forme est un tracé continu, elle n'est donc écrite
     * que si TOUS les tronçons exploités de la ligne à l'ère viennent d'une source qui autorise la
     * redistribution. Sinon la ligne n'a pas de forme (son trajet garde ses arrêts, sans
     * `shape_id`) : une forme amputée de ses parties retenues serait fausse.
     *
     * La forme suit ce que parcourt le train (tripShape) : le tracé de la ligne, prolongé au besoin
     * sur la voie d'une autre ligne jusqu'à la gare terminus.
     *
     * Remplit aussi `$stopDistances` : pour chaque ligne dont la forme est une seule polyligne, la
     * distance de chaque arrêt le long de la forme, dans le sens des positions. Si cet ordre n'est
     * pas strictement croissant (tracé replié, gare hors tracé), la ligne n'y figure pas : GTFS
     * exige des distances croissantes et, pour un trajet, toutes ou aucune.
     *
     * @param  array<int, array<int, float>>  $stopDistances
     * @return array<int, true> lignes dont le tracé a au moins un point
     */
    private function streamShapes(Era $era, string $path, array &$stopDistances): array
    {
        $fp = fopen($path, 'w');
        fputcsv($fp, ['shape_id', 'shape_pt_lat', 'shape_pt_lon', 'shape_pt_sequence', 'shape_dist_traveled'], ',', '"', '\\');

        // Seulement les lignes qui auront un trip (au moins deux arrêts desservis).
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
            $shape = $this->tripShape((int) $lineId, $era);
            if ($shape === null) {
                continue;
            }
            [$geometry, $distances] = $shape;
            $pts = DB::select(<<<'SQL'
                WITH dumped AS (
                    SELECT (ST_DumpPoints(?::geometry)).geom AS g
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
                SQL, [$geometry]);

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
     * Forme du trajet d'une ligne (géométrie en EWKB hexadécimal) et distance de chaque arrêt le long
     * d'elle, par position ; null quand la ligne n'a aucun tronçon exploité.
     *
     * La forme part du tracé de la ligne (fusion de ses tronçons exploités), orienté dans le sens des
     * positions des arrêts (la fusion n'en impose aucun). Quand ce tracé s'arrête avant la gare
     * terminus parce que les trains finissent le trajet sur la voie d'une autre ligne (tronc commun :
     * Gannat, Neuf-Brisach), il est prolongé le long de cette voie jusqu'à la gare, à chaque bout
     * (sharedTrackExtension). Le prolongement ne rapproche que des arrêts de la forme : chaque arrêt
     * reste à la même distance de la forme, ou s'en rapproche.
     *
     * @return array{0: string, 1: array<int, float>}|null
     */
    private function tripShape(int $lineId, Era $era): ?array
    {
        $own = DB::selectOne(<<<'SQL'
            SELECT ST_LineMerge(ST_Collect(geom))::geometry AS g,
                   GeometryType(ST_LineMerge(ST_Collect(geom))) AS kind
            FROM line_segments
            WHERE line_id = ? AND era_id = ? AND status IN ('in_service','single_track')
            SQL, [$lineId, $era->id]);
        if ($own === null || $own->g === null) {
            return null;
        }
        $geometry = (string) $own->g;
        [$distances, $reversed] = $this->stopDistancesAlongShape($geometry, $lineId, $era);
        if ($reversed) {
            $geometry = (string) DB::selectOne('SELECT ST_Reverse(?::geometry) AS g', [$geometry])->g;
        }
        if ($own->kind !== 'LINESTRING') {
            return [$geometry, $distances];
        }

        $terminals = DB::select(<<<'SQL'
            (SELECT ls.station_id FROM line_stops ls JOIN stations s ON s.id = ls.station_id AND s.geom IS NOT NULL
             WHERE ls.line_id = ? AND ls.era_id = ? AND ls.effective_status IN ('active', 'tourist', 'no_commercial')
             ORDER BY ls.position LIMIT 1)
            UNION ALL
            (SELECT ls.station_id FROM line_stops ls JOIN stations s ON s.id = ls.station_id AND s.geom IS NOT NULL
             WHERE ls.line_id = ? AND ls.era_id = ? AND ls.effective_status IN ('active', 'tourist', 'no_commercial')
             ORDER BY ls.position DESC LIMIT 1)
            SQL, [$lineId, $era->id, $lineId, $era->id]);
        if (count($terminals) !== 2) {
            return [$geometry, $distances];
        }

        $extended = false;
        // Bout d'arrivée, puis bout de départ (traité sur la forme retournée, puis remise à l'endroit).
        $tail = $this->sharedTrackExtension($geometry, (int) $terminals[1]->station_id, $lineId, $era);
        if ($tail !== null) {
            $geometry = (string) DB::selectOne('SELECT ST_MakeLine(?::geometry, ST_GeomFromEWKT(?)) AS g', [$geometry, $tail])->g;
            $extended = true;
        }
        $reverse = (string) DB::selectOne('SELECT ST_Reverse(?::geometry) AS g', [$geometry])->g;
        $head = $this->sharedTrackExtension($reverse, (int) $terminals[0]->station_id, $lineId, $era);
        if ($head !== null) {
            $geometry = (string) DB::selectOne('SELECT ST_Reverse(ST_MakeLine(?::geometry, ST_GeomFromEWKT(?))) AS g', [$reverse, $head])->g;
            $extended = true;
        }
        if ($extended) {
            [$distances] = $this->stopDistancesAlongShape($geometry, $lineId, $era, false);
        }

        return [$geometry, $distances];
    }

    /**
     * Prolongement de la forme au-delà de son bout d'arrivée, le long des voies d'AUTRES lignes,
     * jusqu'à la gare `$stationId` (EWKT, orienté depuis le bout), ou null.
     *
     * Le prolongement n'est cherché que si la gare est au-delà du bout (le point de la forme le plus
     * proche d'elle est le bout, à SHARED_ATTACH_M près) et à plus de SHARED_MIN_GAP_M de la forme.
     * Il suit, sommet par sommet (SharedTrackRouter), les tronçons exploités à l'ère des autres
     * lignes autour du bout et de la gare, et seulement ceux dont la source autorise la
     * redistribution : une forme ne contient jamais de géométrie retenue, et sans chemin libre la
     * forme reste celle de la ligne. Le chemin part à au plus SHARED_JOIN_M du bout, dans son
     * prolongement, ne rebrousse jamais (SHARED_TURN_DEG à chaque sommet), mesure au plus
     * SHARED_MAX_M et au plus SHARED_DETOUR fois la distance à vol d'oiseau du bout à la gare, et
     * finit à au plus SHARED_REACH_M de la gare. Aucune droite n'est tirée vers la gare.
     */
    private function sharedTrackExtension(string $geometry, int $stationId, int $lineId, Era $era): ?string
    {
        $tip = DB::selectOne(<<<'SQL'
            WITH k AS (
                SELECT ?::geometry AS own, s.geom::geometry AS st
                FROM stations s
                WHERE s.id = ? AND s.geom IS NOT NULL
            )
            SELECT ST_X(ST_EndPoint(own)) AS ex, ST_Y(ST_EndPoint(own)) AS ey,
                   ST_X(b) AS bx, ST_Y(b) AS by, ST_X(st) AS sx, ST_Y(st) AS sy,
                   ST_Distance(ST_EndPoint(own)::geography, st::geography) AS crow,
                   ST_AsEWKT(ST_MakeLine(ST_EndPoint(own), st)) AS span
            FROM k, LATERAL (SELECT ST_LineInterpolatePoint(own, GREATEST(0, 1 - ? / ST_Length(own::geography))) AS b) h
            WHERE ST_Length(own::geography) > 0
              AND ST_Distance(own::geography, st::geography) > ?
              AND ST_Distance(ST_EndPoint(own)::geography, st::geography) - ST_Distance(own::geography, st::geography) <= ?
            SQL, [$geometry, $stationId, self::SHARED_HEADING_M, self::SHARED_MIN_GAP_M, self::SHARED_ATTACH_M]);
        if ($tip === null || (float) $tip->crow > self::SHARED_MAX_M) {
            return null;
        }

        $redistributable = ($this->traceRights ?? app(LineTraceExport::class))->sqlRedistributable('source');
        $tracks = array_map(
            fn (object $r): array => array_map(fn (array $c): array => [(float) $c[0], (float) $c[1]], json_decode($r->g, true)['coordinates']),
            DB::select(<<<SQL
                SELECT ST_AsGeoJSON(geom) AS g
                FROM line_segments
                WHERE era_id = ? AND line_id <> ? AND status IN ('in_service','single_track')
                  AND geom && ST_Expand(ST_GeomFromEWKT(?), ?)
                  AND COALESCE({$redistributable}, FALSE)
                SQL, [$era->id, $lineId, $tip->span, self::SHARED_SEARCH_DEG]),
        );
        if ($tracks === []) {
            return null;
        }

        $router = new SharedTrackRouter(
            joinM: self::SHARED_JOIN_M,
            reachM: self::SHARED_REACH_M,
            maxM: min(self::SHARED_MAX_M, self::SHARED_DETOUR * (float) $tip->crow + self::SHARED_REACH_M),
            turnDeg: self::SHARED_TURN_DEG,
        );
        $path = $router->route($tracks, [(float) $tip->ex, (float) $tip->ey], [(float) $tip->bx, (float) $tip->by], [(float) $tip->sx, (float) $tip->sy]);
        if ($path === null) {
            return null;
        }

        return 'SRID=4326;LINESTRING('.implode(',', array_map(fn (array $c): string => sprintf('%.9F %.9F', $c[0], $c[1]), $path)).')';
    }

    /**
     * Distance (m) de chaque arrêt desservi le long de la forme `$geometry` (EWKB hexadécimal), par
     * position, et si la forme doit être inversée pour suivre le sens des positions (toujours faux
     * quand `$orient` est faux : la forme est déjà orientée). Distances `[]` quand elles ne peuvent
     * pas être données proprement (forme en plusieurs morceaux, ordre non strictement croissant).
     *
     * @return array{0: array<int, float>, 1: bool}
     */
    private function stopDistancesAlongShape(string $geometry, int $lineId, Era $era, bool $orient = true): array
    {
        $rows = DB::select(<<<'SQL'
            WITH merged AS (
                SELECT ?::geometry AS g
            )
            SELECT ls.position,
                   ST_Length(ST_LineSubstring(m.g, 0, ST_LineLocatePoint(m.g, s.geom::geometry))::geography) AS dist_m,
                   ST_Length(m.g::geography) AS length_m
            FROM line_stops ls
            JOIN stations s ON s.id = ls.station_id
            CROSS JOIN merged m
            WHERE ls.line_id = ? AND ls.era_id = ?
              AND ls.effective_status IN ('active', 'tourist', 'no_commercial')
              AND s.geom IS NOT NULL
              AND GeometryType(m.g) = 'LINESTRING'
            ORDER BY ls.position
            SQL, [$geometry, $lineId, $era->id]);
        if ($rows === []) {
            return [[], false];
        }

        $values = array_map(fn ($r) => (float) $r->dist_m, $rows);
        $reversed = $orient && count($values) > 1 && $values[0] > $values[count($values) - 1];
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
