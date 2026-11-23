<?php

namespace App\Services\Timetable;

use App\Services\StationService\FeedDefinition;
use App\Services\StationService\GtfsArchive;
use App\Services\StationService\StationServiceImporter;
use App\Services\StationService\StationStopMatcher;
use App\Services\StationService\Ymd;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Desserte d'un jour type des gares et des lignes (vff:import-station-timetables) : lit les flux GTFS SNCF et Transilien
 * un même mardi type, rattache les arrêts aux gares de VFF, agrège (TimetableAggregator) et range le tout dans
 * station_timetables et line_timetables. Écrit seulement ces deux tables.
 */
final readonly class TimetableImporter
{
    /** Flux qui décident du mardi type ; les autres (régionaux) sont lus ce jour-là s'il est dans leur validité. */
    private const PRIMARY = ['sncf', 'transilien'];

    public function __construct(
        private TypicalDayReader $reader,
        private TimetableAggregator $aggregator,
        private StationServiceImporter $stationService,
    ) {}

    /**
     * @param  list<array{string, TimetableProfile, FeedDefinition}>  $feeds  chemin du flux, profil de lecture, définition
     * @param  Closure(string): void|null  $progress
     */
    public function analyse(array $feeds, int $today, ?int $forcedDate = null, ?Closure $progress = null): TimetableResult
    {
        $say = $progress ?? static fn (string $line) => null;
        $stations = [];
        $candidates = [];
        foreach ($this->stationService->stations() as $id => $s) {
            $candidates[] = $s['candidate'];
        }
        foreach (DB::table('stations')->get(['id', 'name', 'slug']) as $r) {
            $stations[(int) $r->id] = ['name' => (string) $r->name, 'slug' => (string) $r->slug];
        }
        $matcher = new StationStopMatcher($candidates);

        $days = [];
        $meta = [];
        $date = $forcedDate;
        // Un même mardi pour tous les flux : il doit tomber dans la validité de chacun (Transilien ne publie que quelques semaines).
        $bounds = [0, PHP_INT_MAX];
        foreach ($feeds as [$path, $profile]) {
            if (! in_array($profile->key, self::PRIMARY, true)) {
                continue;
            }
            $horizon = $this->reader->horizon(new GtfsArchive($path));
            $bounds = [max($bounds[0], $horizon[0]), min($bounds[1], $horizon[1])];
        }
        foreach ($feeds as [$path, $profile, $definition]) {
            $gtfs = new GtfsArchive($path);
            if ($date === null) {
                [$date, $compared] = $this->reader->chooseDate($gtfs, $profile, $today, $bounds);
                $say(sprintf('Mardi type : %s (médiane de %d mardis de semaines sans jour férié)', Ymd::iso($date), $compared));
            }
            $horizon = $this->reader->horizon($gtfs);
            if ($date < $horizon[0] || $date > $horizon[1]) {
                $say(sprintf('%s : le %s est hors de la validité du flux (%s à %s), flux écarté', $profile->key, Ymd::iso($date), Ymd::iso($horizon[0]), Ymd::iso($horizon[1])));

                continue;
            }
            $day = $this->reader->read($gtfs, $profile, $definition, $date);
            $say(sprintf('%s : %d trains le %s, %d zones d\'arrêt (version %s)', $profile->key, count($day->runs), Ymd::iso($date), count($day->zones), $day->version ?? '?'));
            $days[] = [$day, $matcher];
            $meta[] = ['name' => $definition->name, 'provider' => $definition->provider, 'licence' => $definition->licence, 'page' => $definition->page];
        }

        return $this->aggregator->aggregate($days, $stations, $this->stationLines(), $meta);
    }

    /** Range le résultat (tables entièrement remplacées). */
    public function store(TimetableResult $result, DateTimeInterface $now): array
    {
        $byFeed = [];
        foreach ($result->feeds as $f) {
            $byFeed[$f['feed']] = ['feed' => $f['feed'], 'version' => $f['version'], 'date' => $f['date'], 'valid_to' => $f['horizon'][1],
                'name' => $f['name'] ?? null, 'licence' => $f['licence'] ?? null, 'page' => $f['page'] ?? null];
        }
        $date = (string) ($result->feeds[0]['date'] ?? $now->format('Y-m-d'));
        $stations = [];
        foreach ($result->stations as $id => $s) {
            $stations[] = [
                'station_id' => $id, 'service_date' => $date, 'trains' => $s['trains'],
                'by_type' => json_encode((object) $s['by_type']),
                'first_time' => $s['first'], 'last_time' => $s['last'],
                'directions' => json_encode($s['directions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'destinations' => json_encode($s['destinations'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'origins' => json_encode($s['origins'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'feeds' => json_encode(array_values(array_map(fn (string $k): array => $byFeed[$k], $s['feeds'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source' => 'sncf', 'computed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $lines = [];
        foreach ($result->lines as $id => $l) {
            $lines[] = ['line_id' => $id, 'service_date' => $date, 'trains' => $l['trains'], 'by_type' => json_encode((object) $l['by_type']),
                'source' => 'sncf', 'computed_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::transaction(function () use ($stations, $lines): void {
            DB::table('station_timetables')->delete();
            DB::table('line_timetables')->delete();
            foreach (array_chunk($stations, 500) as $chunk) {
                DB::table('station_timetables')->insert($chunk);
            }
            foreach (array_chunk($lines, 500) as $chunk) {
                DB::table('line_timetables')->insert($chunk);
            }
        });

        return ['stations' => count($stations), 'lines' => count($lines)];
    }

    /**
     * Lignes en service aujourd'hui (au moins un tronçon de l'ère courante en service) de chaque gare.
     *
     * @return array<int, list<int>>
     */
    private function stationLines(): array
    {
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT sl.station_id, sl.line_id
            FROM station_lines sl
            WHERE sl.line_id IN (
                SELECT ls.line_id FROM line_segments ls JOIN eras e ON e.id = ls.era_id AND e.is_current
                WHERE ls.status IN ('in_service', 'single_track')
            )
        SQL) as $r) {
            $out[(int) $r->station_id][] = (int) $r->line_id;
        }

        return $out;
    }
}
