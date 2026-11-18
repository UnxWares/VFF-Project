<?php

namespace App\Services\StationService;

use App\Enums\TransitMode;

/**
 * Desserte de chaque zone d'arrêt d'un flux GTFS : nombre d'arrêts par mode
 * (train, tramway, car) et par jour, sur une fenêtre de dates.
 *
 * Lecture en flux, dans l'ordre routes → trips → calendriers → stops → stop_times :
 * seules les courses des modes gardés par le flux restent en mémoire (un entier
 * par course), stop_times est agrégé par (zone, service, ligne) puis développé
 * jour par jour à la fin.
 *
 * Règles :
 *   - un arrêt sans montée ni descente (pickup_type = drop_off_type = 1) ne compte pas ;
 *   - zone d'arrêt = parent_station, à défaut l'arrêt lui-même ;
 *   - tramway compté comme train pour les flux de tram-trains (SNCF : Nantes - Châteaubriant,
 *     Mulhouse - Thann, Sarreguemines - Sarrebruck) ;
 *   - lignes filtrées par leur nom court quand le flux le demande (tram-trains d'Île-de-France) ;
 *   - un arrêt de car dans une ligne de train (SNCF : « StopPoint:OCECar TER-… ») compte comme car ;
 *   - fenêtre : de la date demandée (au plus tôt le début de l'horizon) à la fin de l'horizon,
 *     ou tout l'horizon s'il est déjà fini à la date demandée (flux périmé).
 */
final class GtfsFeedReader
{
    private const CODES = [1 => TransitMode::Rail, 2 => TransitMode::Tram, 3 => TransitMode::Bus];

    private const BUS_CODE = 3;

    private const MODE_MASK = 3;

    private const ROUTE_MASK = 0x3FFFFF;

    private const SERVICE_SHIFT = 24;

    public function read(GtfsArchive $gtfs, FeedDefinition $feed, ?int $from = null, ?int $to = null): FeedSnapshot
    {
        [$routeModes, $routeNames] = $this->routes($gtfs, $feed);
        [$trips, $serviceIds] = $this->trips($gtfs, $feed, $routeModes);
        [$dates, $horizonStart, $horizonEnd] = $this->calendar($gtfs, $serviceIds);
        [$horizonStart, $horizonEnd] = $this->feedInfo($gtfs) ?? [$horizonStart, $horizonEnd];
        [$stopKeys, $busStops, $zones] = $this->stops($gtfs, $feed);
        $calls = $this->calls($gtfs, $feed, $trips, $stopKeys, $busStops);
        unset($trips, $stopKeys, $busStops);

        // Fenêtre : de $from (au plus tôt le début de l'horizon) à $to ; tout l'horizon s'il est fini avant $from.
        $from = $from === null || $from > $horizonEnd ? $horizonStart : max($from, $horizonStart);
        $to = min($to ?? $horizonEnd, $horizonEnd);
        $stops = [];
        foreach ($calls as $zone => $combos) {
            $services = $this->services($combos, $dates, $routeNames, $from, $to);
            if ($services !== []) {
                $z = $zones[$zone];
                $stops[] = new GtfsStop($z['key'], $z['name'], $z['lat'], $z['lon'], $z['uic'], $services);
            }
        }

        return new FeedSnapshot($feed, $horizonStart, $horizonEnd, $from, $to, $stops);
    }

    /** @return array{array<string, int>, list<string>} route_id => code de mode, noms des lignes */
    private function routes(GtfsArchive $gtfs, FeedDefinition $feed): array
    {
        $modes = [];
        $names = [];
        foreach ($gtfs->rows('routes.txt') as $r) {
            $mode = TransitMode::fromRouteType((int) ($r['route_type'] ?? -1));
            if ($mode === TransitMode::Tram && $feed->tramIsRail) {
                $mode = TransitMode::Rail;
            }
            $name = trim(($r['route_short_name'] ?? '').' '.($r['route_long_name'] ?? ''));
            if ($mode === null || ! $feed->keeps($mode)
                || ($feed->routePattern !== null && preg_match($feed->routePattern, $r['route_short_name'] ?? '') !== 1)) {
                continue;
            }
            $modes[$r['route_id']] = (count($names) << 2) | array_search($mode, self::CODES, true);
            $names[] = $name;
        }

        return [$modes, $names];
    }

    /**
     * Course d'une ligne de train dont la destination désigne un car (Chemins de fer de
     * Provence : « DIGNE LES BAINS - GARE CPZou /car ») : car, gardée seulement si le flux garde les cars.
     *
     * @param  array<string, int>  $routeModes
     * @return array{array<string, int>, array<string, int>} trip_id => (service, ligne, mode), service_id => indice
     */
    private function trips(GtfsArchive $gtfs, FeedDefinition $feed, array $routeModes): array
    {
        $trips = [];
        $services = [];
        foreach ($gtfs->rows('trips.txt') as $r) {
            $route = $routeModes[$r['route_id']] ?? null;
            if ($route !== null && $feed->busTripPattern !== null && preg_match($feed->busTripPattern, $r['trip_headsign'] ?? '') === 1) {
                $route = $feed->keeps(TransitMode::Bus) ? ($route & ~self::MODE_MASK) | self::BUS_CODE : null;
            }
            if ($route === null) {
                continue;
            }
            $service = $services[$r['service_id']] ??= count($services);
            $trips[$r['trip_id']] = ($service << self::SERVICE_SHIFT) | $route;
        }

        return [$trips, $services];
    }

    /**
     * Jours de circulation des services gardés (calendar.txt puis exceptions
     * de calendar_dates.txt) et horizon de tout le flux.
     *
     * @param  array<string, int>  $serviceIds
     * @return array{array<int, array<int, true>>, int, int}
     */
    private function calendar(GtfsArchive $gtfs, array $serviceIds): array
    {
        $dates = [];
        $min = PHP_INT_MAX;
        $max = 0;
        $weekdays = [1 => 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($gtfs->rows('calendar.txt') as $r) {
            $start = Ymd::parse($r['start_date']);
            $end = Ymd::parse($r['end_date']);
            [$min, $max] = [min($min, $start), max($max, $end)];
            $service = $serviceIds[$r['service_id']] ?? null;
            if ($service === null) {
                continue;
            }
            $last = Ymd::toDate($end);
            for ($day = Ymd::toDate($start); $day <= $last; $day = $day->modify('+1 day')) {
                if (($r[$weekdays[(int) $day->format('N')]] ?? '0') === '1') {
                    $dates[$service][Ymd::fromDate($day)] = true;
                }
            }
        }
        foreach ($gtfs->rows('calendar_dates.txt') as $r) {
            $day = Ymd::parse($r['date']);
            $service = $serviceIds[$r['service_id']] ?? null;
            if ($r['exception_type'] === '1') {
                [$min, $max] = [min($min, $day), max($max, $day)];
                if ($service !== null) {
                    $dates[$service][$day] = true;
                }
            } elseif ($service !== null) {
                unset($dates[$service][$day]);
            }
        }

        return [$dates, $min === PHP_INT_MAX ? 0 : $min, $max];
    }

    /** @return array{int, int}|null horizon déclaré par feed_info.txt */
    private function feedInfo(GtfsArchive $gtfs): ?array
    {
        foreach ($gtfs->rows('feed_info.txt') as $r) {
            if (($r['feed_start_date'] ?? '') !== '' && ($r['feed_end_date'] ?? '') !== '') {
                return [Ymd::parse($r['feed_start_date']), Ymd::parse($r['feed_end_date'])];
            }
        }

        return null;
    }

    /**
     * Zones d'arrêt : stop_id => indice de zone, arrêts de car, et pour chaque zone
     * nom, position (celle de la zone parente si elle est décrite) et code UIC.
     *
     * @return array{array<string, int>, array<string, true>, list<array{key: string, name: string, lat: ?float, lon: ?float, uic: ?string}>}
     */
    private function stops(GtfsArchive $gtfs, FeedDefinition $feed): array
    {
        $keys = [];
        $index = [];
        $bus = [];
        $zones = [];
        foreach ($gtfs->rows('stops.txt') as $r) {
            $id = $r['stop_id'];
            $key = ($r['parent_station'] ?? '') !== '' ? $r['parent_station'] : $id;
            $zone = $index[$key] ??= count($index);
            $keys[$id] = $zone;
            if ($feed->busStopPattern !== null && preg_match($feed->busStopPattern, $id) === 1) {
                $bus[$id] = true;
            }
            $lat = (float) ($r['stop_lat'] ?? 0);
            $lon = (float) ($r['stop_lon'] ?? 0);
            $located = abs($lat) > 0.0001 || abs($lon) > 0.0001;
            $current = $zones[$zone] ?? null;
            if ($current === null || ($id === $key && $located)) {
                $zones[$zone] = ['key' => $key, 'name' => $r['stop_name'] ?? '', 'lat' => $located ? $lat : null,
                    'lon' => $located ? $lon : null, 'uic' => $current['uic'] ?? null];
            }
            $zones[$zone]['uic'] ??= $this->uic($r, $feed);
        }
        ksort($zones);

        return [$keys, $bus, array_values($zones)];
    }

    /** @param  array<string, string>  $row */
    private function uic(array $row, FeedDefinition $feed): ?string
    {
        foreach ($feed->uicFields as $field) {
            if (($row[$field] ?? '') !== '' && preg_match($feed->uicPattern, $row[$field], $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Arrêts agrégés par zone et par (service, ligne, mode).
     *
     * @param  array<string, int>  $trips
     * @param  array<string, int>  $stopKeys
     * @param  array<string, true>  $busStops
     * @return array<int, array<int, int>>
     */
    private function calls(GtfsArchive $gtfs, FeedDefinition $feed, array $trips, array $stopKeys, array $busStops): array
    {
        $keepsBus = $feed->keeps(TransitMode::Bus);
        $calls = [];
        foreach ($gtfs->rows('stop_times.txt') as $r) {
            $packed = $trips[$r['trip_id']] ?? null;
            $zone = $stopKeys[$r['stop_id']] ?? null;
            if ($packed === null || $zone === null || (($r['pickup_type'] ?? '') === '1' && ($r['drop_off_type'] ?? '') === '1')) {
                continue;
            }
            if (isset($busStops[$r['stop_id']])) {
                if (! $keepsBus) {
                    continue;
                }
                $packed = ($packed & ~self::MODE_MASK) | self::BUS_CODE;
            }
            $calls[$zone][$packed] = ($calls[$zone][$packed] ?? 0) + 1;
        }

        return $calls;
    }

    /**
     * @param  array<int, int>  $combos  (service, ligne, mode) => arrêts par jour de circulation
     * @param  array<int, array<int, true>>  $dates
     * @param  list<string>  $routeNames
     * @return array<string, ModeService>
     */
    private function services(array $combos, array $dates, array $routeNames, int $from, int $to): array
    {
        $days = [];
        $routes = [];
        foreach ($combos as $packed => $n) {
            $mode = $packed & self::MODE_MASK;
            foreach ($dates[$packed >> self::SERVICE_SHIFT] ?? [] as $day => $_) {
                if ($day >= $from && $day <= $to) {
                    $days[$mode][$day] = ($days[$mode][$day] ?? 0) + $n;
                    $routes[$mode][($packed >> 2) & self::ROUTE_MASK] = true;
                }
            }
        }
        $out = [];
        foreach ($days as $mode => $perDay) {
            ksort($perDay);
            $names = array_values(array_unique(array_map(fn (int $r): string => $routeNames[$r], array_keys($routes[$mode]))));
            sort($names);
            $out[self::CODES[$mode]->value] = new ModeService(array_sum($perDay), $perDay, $names);
        }

        return $out;
    }
}
