<?php

namespace App\Services\StationService;

use App\Enums\StationServiceClass;
use App\Enums\StopMatchMethod;
use App\Enums\TransitMode;
use App\Services\StationLineCoverage;
use App\Support\StationNames;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Desserte voyageurs des gares de VFF aujourd'hui (vff:import-station-service) :
 * lit les flux GTFS, rapproche leurs arrêts des gares, réunit les indices sans
 * horaires (lignes touristiques et vélorails de VFF, OSM, fréquentation SNCF),
 * classe chaque gare (StationServiceClassifier) et range le tout dans station_services.
 * Ne modifie jamais le statut des gares.
 */
final readonly class StationServiceImporter
{
    /** Distance maximale entre une gare et le tronçon touristique ou le vélorail de l'une de ses lignes. */
    public const HERITAGE_RADIUS_M = 300;

    public function __construct(
        private GtfsFeedReader $reader,
        private ServicePatternDetector $detector,
        private StationServiceClassifier $classifier,
        private StationLineCoverage $coverage,
    ) {}

    /**
     * @param  list<array{string, FeedDefinition}>  $feeds  chemin du flux et sa définition
     * @param  list<string>  $restrictedDepartments  départements couverts seulement par certains flux (FeedCatalog)
     * @param  array<string, array{year: int, travellers: int}>  $frequentation  UIC à 7 chiffres => fréquentation
     * @param  Closure(string): void|null  $progress
     */
    public function analyse(array $feeds, int $today, array $restrictedDepartments = [], array $frequentation = [], ?Closure $progress = null): StationServiceResult
    {
        $stations = $this->stations();
        $matcher = new StationStopMatcher(array_column($stations, 'candidate'));
        $collector = new EvidenceCollector($this->detector);
        $unmatched = [];
        $summaries = [];
        $coveredDepartments = [];
        foreach ($feeds as [$path, $feed]) {
            $snapshot = $this->reader->read(new GtfsArchive($path), $feed, ...$feed->readWindow($today));
            $role = FeedRole::of($snapshot, $today);
            if ($role === FeedRole::Current) {
                $coveredDepartments = [...$coveredDepartments, ...$feed->departments];
            }
            $matched = 0;
            foreach ($snapshot->stops as $stop) {
                $trains = $stop->service(TransitMode::Rail) !== null || $stop->service(TransitMode::Tram) !== null;
                // Rapprochement par la seule position : trains seulement (pas les arrêts de tramway ou de car).
                $match = $matcher->match($stop, $stop->service(TransitMode::Rail) !== null);
                if ($match !== null && ($trains || $match->method === StopMatchMethod::Uic)) {
                    $collector->add($match, $stop, $snapshot, $role);
                    $matched++;
                } elseif ($trains && ($role === FeedRole::Current || $role === FeedRole::Tourist)) {
                    $unmatched[] = $this->unmatched($snapshot, $stop);
                }
            }
            $summaries[] = ['feed' => $feed->key, 'role' => $role->value, 'horizon' => [Ymd::iso($snapshot->horizonStart), Ymd::iso($snapshot->horizonEnd)],
                'window' => [Ymd::iso($snapshot->windowStart), Ymd::iso($snapshot->windowEnd)], 'stops' => count($snapshot->stops), 'matched' => $matched];
            if ($progress !== null) {
                $progress(sprintf('%s : %d arrêts desservis, %d rapprochés (%s)', $feed->key, count($snapshot->stops), $matched, $role->value));
            }
        }

        $heritage = $this->heritageNearStations();
        $touristOsm = $this->coverage->touristStations();
        $veloOsm = $this->velorailOsmStations();
        $rows = [];
        foreach ($stations as $id => $s) {
            $uncovered = in_array($s['department'], $restrictedDepartments, true) && ! in_array($s['department'], $coveredDepartments, true);
            $evidence = $collector->evidence($id, new HeritageHints(
                touristLine: $heritage[$id]['tourist'] ?? false, touristStation: isset($touristOsm[$id]),
                velorailLine: $heritage[$id]['velorail'] ?? false, velorailStation: isset($veloOsm[$id]),
            ), ! $uncovered);
            $verdict = $this->classifier->classify($evidence, $today);
            $indicators = array_filter([
                'tourist_line' => $heritage[$id]['tourist'] ?? false, 'tourist_osm' => isset($touristOsm[$id]),
                'velorail_line' => $heritage[$id]['velorail'] ?? false, 'velorail_osm' => isset($veloOsm[$id]),
                'frequentation' => $this->frequentation($s['candidate'], $frequentation),
            ]);
            $rows[$id] = new StationServiceRow($id, $verdict, $this->primary($verdict, $evidence), $collector->trace($id), $indicators);
        }

        return new StationServiceResult($rows, $unmatched, $summaries);
    }

    /** Range le résultat dans station_services (table entière remplacée). */
    public function store(StationServiceResult $result, DateTimeInterface $now): int
    {
        $rows = array_map(fn (StationServiceRow $r): array => [
            'station_id' => $r->stationId,
            'service_class' => $r->verdict->class->value,
            'pattern' => $r->verdict->pattern?->value,
            'calls' => $r->profile?->calls,
            'served_days' => $r->profile?->servedDays,
            'window_start' => $r->profile === null ? null : Ymd::iso($r->profile->from),
            'window_end' => $r->profile === null ? null : Ymd::iso($r->profile->to),
            'first_service' => $r->profile === null ? null : Ymd::iso($r->profile->first),
            'last_service' => $r->profile === null ? null : Ymd::iso($r->profile->last),
            'evidence' => json_encode($r->evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'indicators' => json_encode((object) $r->indicators, JSON_UNESCAPED_UNICODE),
            'note' => $r->verdict->note,
            'computed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($result->rows));
        DB::transaction(function () use ($rows): void {
            DB::table('station_services')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('station_services')->insert($chunk);
            }
        });

        return count($rows);
    }

    /**
     * Fréquentation SNCF (frequentation-gares, une colonne total_voyageurs_AAAA par année) par UIC à 7 chiffres :
     * dernière année publiée.
     *
     * @return array<string, array{year: int, travellers: int}>
     */
    public static function readFrequentation(string $csv): array
    {
        $handle = fopen($csv, 'rb');
        if ($handle === false) {
            return [];
        }
        $header = array_map(fn (?string $h): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), fgetcsv($handle, null, ';', '"', '') ?: []);
        $years = [];
        foreach ($header as $i => $column) {
            if (preg_match('/^total_voyageurs_(\d{4})$/', $column, $m) === 1) {
                $years[(int) $m[1]] = $i;
            }
        }
        krsort($years);
        $uicColumn = array_search('code_uic_complet', $header, true);
        $out = [];
        while ($years !== [] && $uicColumn !== false && ($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
            $year = array_key_first($years);
            $uic = substr(trim((string) ($row[$uicColumn] ?? '')), 0, 7);
            if (strlen($uic) === 7) {
                $out[$uic] = ['year' => $year, 'travellers' => (int) round((float) ($row[$years[$year]] ?? 0))];
            }
        }
        fclose($handle);

        return $out;
    }

    /**
     * Gares de VFF (hors points techniques et gares projetées) et leur département.
     *
     * @return array<int, array{candidate: StationCandidate, department: ?string}>
     */
    private function stations(): array
    {
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT s.id, s.name, s.department_code, ST_Y(s.geom) AS lat, ST_X(s.geom) AS lon, s.uic_code,
                   COALESCE(json_agg(DISTINCT ss.uic_code) FILTER (WHERE ss.uic_code IS NOT NULL), '[]') AS uics,
                   COALESCE(json_agg(DISTINCT ss.name) FILTER (WHERE ss.name IS NOT NULL), '[]') AS names
            FROM stations s LEFT JOIN station_sources ss ON ss.station_id = s.id
            WHERE s.provenance->>'kind' IS NULL
            GROUP BY s.id
            ORDER BY s.id
        SQL) as $r) {
            $uics = array_filter([$r->uic_code, ...json_decode($r->uics, true)], fn (?string $u): bool => $u !== null && preg_match('/^\d{7,8}$/', $u) === 1);
            $names = array_filter(array_map(fn (string $n): ?string => StationNames::normalize($n), [$r->name, ...json_decode($r->names, true)]));
            $out[(int) $r->id] = [
                'candidate' => new StationCandidate((int) $r->id, (float) $r->lat, (float) $r->lon,
                    array_values(array_unique($names)), array_values(array_unique(array_map(fn (string $u): string => substr($u, 0, 7), $uics)))),
                'department' => $r->department_code,
            ];
        }

        return $out;
    }

    /**
     * Gares rattachées à une ligne dont un tronçon touristique en service ou un vélorail
     * de l'ère courante passe à moins de 300 m.
     *
     * @return array<int, array{tourist: bool, velorail: bool}>
     */
    private function heritageNearStations(): array
    {
        $out = [];
        foreach (DB::select(<<<'SQL'
            SELECT s.id,
                   BOOL_OR(ls.heritage_kind = 'tourist_train' AND ls.status IN ('in_service', 'single_track')) AS tourist,
                   BOOL_OR(ls.heritage_kind = 'velorail') AS velorail
            FROM line_segments ls
            JOIN eras e ON e.id = ls.era_id AND e.is_current
            JOIN station_lines sl ON sl.line_id = ls.line_id
            JOIN stations s ON s.id = sl.station_id
                AND ST_DWithin(s.geom, ls.geom, 0.005) AND ST_DWithin(s.geom::geography, ls.geom::geography, ?)
            WHERE ls.heritage_kind IS NOT NULL
            GROUP BY s.id
        SQL, [self::HERITAGE_RADIUS_M]) as $r) {
            $out[(int) $r->id] = ['tourist' => (bool) $r->tourist, 'velorail' => (bool) $r->velorail];
        }

        return $out;
    }

    /** @return array<int, true> gares dont le nom ou l'exploitant OSM désigne un vélorail */
    private function velorailOsmStations(): array
    {
        return array_fill_keys(array_map('intval', DB::table('station_sources')
            ->where('source', 'osm')->whereNotNull('station_id')
            ->where(fn ($q) => $q->whereRaw("COALESCE(properties->>'name', name) ~* 'v[ée]lo-?rail'")
                ->orWhereRaw("properties->>'operator' ~* 'v[ée]lo-?rail'"))
            ->pluck('station_id')->all()), true);
    }

    /**
     * @param  array<string, array{year: int, travellers: int}>  $frequentation
     * @return array{year: int, travellers: int}|null
     */
    private function frequentation(StationCandidate $station, array $frequentation): ?array
    {
        foreach ($station->uics as $uic) {
            if (isset($frequentation[$uic])) {
                return $frequentation[$uic];
            }
        }

        return null;
    }

    /** Desserte sur laquelle repose le classement (rangée dans les colonnes de station_services). */
    private function primary(StationServiceVerdict $verdict, StationEvidence $e): ?ServiceProfile
    {
        return match ($verdict->class) {
            StationServiceClass::Regular, StationServiceClass::Periodic => $e->rail ?? $e->tram ?? $e->summer ?? $e->spring,
            StationServiceClass::Tourist => $e->touristTrains,
            StationServiceClass::BusOnly => $e->bus,
            StationServiceClass::Unknown => $e->stale,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function unmatched(FeedSnapshot $snapshot, GtfsStop $stop): array
    {
        $trains = $stop->service(TransitMode::Rail) ?? $stop->service(TransitMode::Tram);

        $country = $stop->uic === null ? '?' : (str_starts_with($stop->uic, '87') ? 'FR' : 'étranger');

        return ['feed' => $snapshot->feed->key, 'stop' => $stop->key, 'name' => $stop->name, 'uic' => $stop->uic, 'country' => $country,
            'lat' => $stop->lat, 'lon' => $stop->lon, 'calls' => $trains?->calls, 'days' => $trains === null ? 0 : count($trains->days),
            'routes' => implode(' | ', array_slice($trains->routes ?? [], 0, 4))];
    }
}
