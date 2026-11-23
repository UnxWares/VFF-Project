<?php

namespace App\Services\Timetable;

use App\Services\StationService\FeedDefinition;
use App\Services\StationService\GtfsArchive;
use App\Services\StationService\GtfsStop;
use App\Services\StationService\Ymd;

/**
 * Lit un flux GTFS en flux (stop_times.txt fait plusieurs dizaines de Mo) et n'en garde que les courses ferroviaires
 * d'un jour : choix du mardi type, services actifs ce jour-là (calendar.txt et calendar_dates.txt), puis arrêts
 * des seules courses actives.
 *
 * Un arrêt sans montée ni descente (pickup_type = drop_off_type = 1) ne compte pas. Les cars et les produits
 * inconnus ne sont pas des trains : écartés (compte dans `skippedRuns`).
 */
final class TypicalDayReader
{
    /** Mardis candidats : de dans une semaine à dans douze semaines. */
    private const FIRST_WEEKS = 1;

    private const LAST_WEEKS = 12;

    /**
     * Mardi type : parmi les mardis des semaines sans jour férié, celui dont le nombre de trains est la médiane
     * (les semaines de travaux ou de vacances, plus pauvres ou plus riches, sont écartées).
     * Rend aussi le nombre de mardis comparés. `$bounds` : dates (début, fin) à ne pas dépasser, par exemple la validité
     * commune de plusieurs flux lus le même jour.
     *
     * @return array{int, int}
     */
    public function chooseDate(GtfsArchive $gtfs, TimetableProfile $profile, int $today, ?array $bounds = null): array
    {
        $routes = $this->routes($gtfs, $profile);
        [$trips, $services] = $this->trips($gtfs, $routes);
        $horizon = $this->horizon($gtfs);
        if ($bounds !== null) {
            $horizon = [max($horizon[0], $bounds[0]), min($horizon[1], $bounds[1])];
        }
        $from = max(Ymd::addDays($today, self::FIRST_WEEKS * 7), $horizon[0]);
        $to = min(Ymd::addDays($today, self::LAST_WEEKS * 7), $horizon[1]);
        $candidates = FrenchHolidays::cleanTuesdays($from, $to);
        if ($candidates === []) {
            // Flux court ou périmé : mardis propres de tout l'horizon, à défaut le premier mardi.
            $candidates = FrenchHolidays::cleanTuesdays($horizon[0], $horizon[1]);
        }
        $perService = array_count_values($trips);
        $counts = [];
        foreach ($this->activeServices($gtfs, $services, $candidates) as $date => $active) {
            $counts[$date] = array_sum(array_map(fn (int $s): int => $perService[$s] ?? 0, array_keys($active)));
        }
        $counts = array_filter($counts);
        if ($counts === []) {
            throw new \RuntimeException("Flux {$profile->key} : aucun train un mardi type entre {$from} et {$to}");
        }
        asort($counts);
        $dates = array_keys($counts);

        return [$dates[intdiv(count($dates) - 1, 2)], count($dates)];
    }

    public function read(GtfsArchive $gtfs, TimetableProfile $profile, FeedDefinition $definition, int $date): TypicalDay
    {
        $routes = $this->routes($gtfs, $profile);
        [$trips, $services, $tripIds] = $this->trips($gtfs, $routes);
        $active = $this->activeServices($gtfs, $services, [$date])[$date] ?? [];
        [$stopZone, $zones] = $this->stops($gtfs, $definition);

        $raw = [];
        $types = [];
        $skipped = [];
        foreach ($gtfs->rows('stop_times.txt') as $r) {
            $trip = $tripIds[$r['trip_id']] ?? null;
            if ($trip === null || ! isset($active[$trips[$trip]]) || isset($skipped[$trip])) {
                continue;
            }
            if (! isset($types[$trip])) {
                $type = $profile->typeOfStop($r['stop_id']);
                if ($type === null) {
                    $skipped[$trip] = true;

                    continue;
                }
                $types[$trip] = $type;
            }
            $zone = $stopZone[$r['stop_id']] ?? null;
            if ($zone === null || (($r['pickup_type'] ?? '') === '1' && ($r['drop_off_type'] ?? '') === '1')) {
                continue;
            }
            $raw[$trip][(int) $r['stop_sequence']] = [$zone, self::seconds($r['arrival_time'] ?? ''), self::seconds($r['departure_time'] ?? '')];
        }

        $runs = [];
        foreach ($raw as $trip => $stops) {
            ksort($stops);
            $stops = array_values($stops);
            if (count($stops) >= 2) {
                $runs[] = ['type' => $types[$trip], 'stops' => $stops];
            }
        }

        $version = null;
        foreach ($gtfs->rows('feed_info.txt') as $r) {
            $version = ($r['feed_version'] ?? '') !== '' ? $r['feed_version'] : null;
        }
        $horizon = $this->horizon($gtfs);

        return new TypicalDay($profile->key, $version, $date, [Ymd::iso($horizon[0]), Ymd::iso($horizon[1])], $zones, $runs, count($skipped));
    }

    /** @return array<string, true> route_id gardés */
    private function routes(GtfsArchive $gtfs, TimetableProfile $profile): array
    {
        $routes = [];
        foreach ($gtfs->rows('routes.txt') as $r) {
            if (in_array((int) ($r['route_type'] ?? -1), $profile->routeTypes, true)
                && ($profile->agencies === null || in_array($r['agency_id'] ?? '', $profile->agencies, true))) {
                $routes[$r['route_id']] = true;
            }
        }

        return $routes;
    }

    /**
     * @param  array<string, true>  $routes
     * @return array{list<int>, array<string, int>, array<string, int>} service de chaque course (par indice de course),
     *                                                                  service_id => indice, trip_id => indice de course
     */
    private function trips(GtfsArchive $gtfs, array $routes): array
    {
        $trips = [];
        $services = [];
        $ids = [];
        foreach ($gtfs->rows('trips.txt') as $r) {
            if (! isset($routes[$r['route_id']])) {
                continue;
            }
            $service = $services[$r['service_id']] ??= count($services);
            $ids[$r['trip_id']] = count($trips);
            $trips[] = $service;
        }

        return [$trips, $services, $ids];
    }

    /**
     * Services actifs à chacune des dates demandées.
     *
     * @param  array<string, int>  $services  service_id => indice
     * @param  list<int>  $dates
     * @return array<int, array<int, true>> date => (indice de service => true)
     */
    private function activeServices(GtfsArchive $gtfs, array $services, array $dates): array
    {
        $weekdays = [1 => 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $wanted = array_fill_keys($dates, []);
        $day = [];
        foreach ($dates as $d) {
            $day[$d] = $weekdays[(int) Ymd::toDate($d)->format('N')];
        }
        foreach ($gtfs->rows('calendar.txt') as $r) {
            $service = $services[$r['service_id']] ?? null;
            if ($service === null) {
                continue;
            }
            $start = Ymd::parse($r['start_date']);
            $end = Ymd::parse($r['end_date']);
            foreach ($dates as $d) {
                if ($d >= $start && $d <= $end && ($r[$day[$d]] ?? '0') === '1') {
                    $wanted[$d][$service] = true;
                }
            }
        }
        foreach ($gtfs->rows('calendar_dates.txt') as $r) {
            $d = (int) $r['date'];
            $service = $services[$r['service_id']] ?? null;
            if ($service === null || ! isset($wanted[$d])) {
                continue;
            }
            if ($r['exception_type'] === '1') {
                $wanted[$d][$service] = true;
            } elseif ($r['exception_type'] === '2') {
                unset($wanted[$d][$service]);
            }
        }

        return $wanted;
    }

    /** @return array{int, int} validité du flux (feed_info.txt, à défaut calendriers) */
    public function horizon(GtfsArchive $gtfs): array
    {
        foreach ($gtfs->rows('feed_info.txt') as $r) {
            if (($r['feed_start_date'] ?? '') !== '' && ($r['feed_end_date'] ?? '') !== '') {
                return [Ymd::parse($r['feed_start_date']), Ymd::parse($r['feed_end_date'])];
            }
        }
        $min = PHP_INT_MAX;
        $max = 0;
        foreach ($gtfs->rows('calendar.txt') as $r) {
            [$min, $max] = [min($min, Ymd::parse($r['start_date'])), max($max, Ymd::parse($r['end_date']))];
        }
        foreach ($gtfs->rows('calendar_dates.txt') as $r) {
            [$min, $max] = [min($min, (int) $r['date']), max($max, (int) $r['date'])];
        }

        return [$min === PHP_INT_MAX ? 0 : $min, $max];
    }

    /**
     * Zones d'arrêt (parent_station, à défaut l'arrêt) avec nom, position et code UIC lu selon la définition du flux.
     *
     * @return array{array<string, int>, list<GtfsStop>}
     */
    private function stops(GtfsArchive $gtfs, FeedDefinition $feed): array
    {
        $stopZone = [];
        $index = [];
        $zones = [];
        foreach ($gtfs->rows('stops.txt') as $r) {
            $id = $r['stop_id'];
            $key = ($r['parent_station'] ?? '') !== '' ? $r['parent_station'] : $id;
            $zone = $index[$key] ??= count($index);
            $stopZone[$id] = $zone;
            $lat = (float) ($r['stop_lat'] ?? 0);
            $lon = (float) ($r['stop_lon'] ?? 0);
            $located = abs($lat) > 0.0001 || abs($lon) > 0.0001;
            $current = $zones[$zone] ?? null;
            if ($current === null || ($id === $key && $located)) {
                $zones[$zone] = ['key' => $key, 'name' => $r['stop_name'] ?? '', 'lat' => $located ? $lat : ($current['lat'] ?? null),
                    'lon' => $located ? $lon : ($current['lon'] ?? null), 'uic' => $current['uic'] ?? null];
            }
            $zones[$zone]['uic'] ??= $this->uic($r, $feed);
        }
        ksort($zones);

        return [$stopZone, array_map(fn (array $z): GtfsStop => new GtfsStop($z['key'], $z['name'], $z['lat'], $z['lon'], $z['uic'], []), array_values($zones))];
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

    /** « 25:10:00 » => secondes ; null si vide. */
    private static function seconds(string $time): ?int
    {
        if (preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/', $time, $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) ($m[3] ?? 0);
    }
}
