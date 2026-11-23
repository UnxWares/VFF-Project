<?php

namespace App\Services\Timetable;

use App\Services\StationService\StationStopMatcher;
use App\Services\StationService\Ymd;

/**
 * Rattache les arrêts d'un jour type aux gares de VFF (code UIC, puis nom, puis position : StationStopMatcher) et
 * range, gare par gare, les trains du jour : nombre par type et par sens, premières et dernières heures, destinations
 * directes ; ligne par ligne, le nombre de trains compté une fois.
 *
 * Une course qui s'arrête dans deux zones d'une même gare (UIC partagé) compte une fois. Le « sens » est l'axe
 * principal des trains de la gare : on projette la direction de marche de chaque train (de l'arrêt précédent à
 * l'arrêt suivant) sur l'axe moyen des directions ; les deux demi-axes sont les deux sens (sur une impasse : départs
 * et arrivées).
 */
final class TimetableAggregator
{
    private const COMPASS = ['n', 'ne', 'e', 'se', 's', 'sw', 'w', 'nw'];

    /** Fin de la journée de service pour les heures extrêmes : 03:00 le lendemain (secondes). */
    private const DAY_END = 27 * 3600;

    private const MAX_DESTINATIONS = 6;

    private const MAX_DIRECTION_DESTINATIONS = 3;

    /**
     * @param  list<array{TypicalDay, StationStopMatcher}>  $days  jours lus, avec le rapprocheur déjà construit
     * @param  array<int, array{name: string, slug: string}>  $stations  gares de VFF
     * @param  array<int, list<int>>  $stationLines  gare => lignes en service aujourd'hui
     * @param  list<array<string, mixed>>  $feedMeta  métadonnées de chaque flux (même ordre que $days)
     * @param  list<string>  $primary  flux de référence ; les autres (régionaux, qui republient en partie les mêmes TER) ne servent
     *                                 que pour les gares et les lignes que ces flux ne desservent pas : jamais de double compte
     */
    public function aggregate(array $days, array $stations, array $stationLines, array $feedMeta = [], array $primary = ['sncf', 'transilien']): TimetableResult
    {
        /** @var array<int, array<string, array<string, mixed>>> $passages gare => clé de course => passage */
        $passages = [];
        /** @var array<int, array<string, string>> $lineTrips ligne => clé de course => type */
        $lineTrips = [];
        $unmatched = [];
        $feeds = [];
        foreach ($days as $i => [$day, $matcher]) {
            $zoneStations = [];
            $matchedZones = 0;
            foreach ($day->zones as $zone => $stop) {
                $match = $matcher->match($stop, true);
                if ($match !== null && ! in_array('uic_far', $match->flags, true)) {
                    $zoneStations[$zone] = $match->stationIds;
                    $matchedZones++;
                }
            }
            $zoneCalls = [];
            foreach ($day->runs as $n => $run) {
                $key = $day->feed.':'.$n;
                $stops = $run['stops'];
                $last = count($stops) - 1;
                [$firstZone, $lastZone] = [$stops[0][0], $stops[$last][0]];
                foreach ($stops as $j => [$zone]) {
                    $zoneCalls[$zone] = ($zoneCalls[$zone] ?? 0) + 1;
                    foreach ($zoneStations[$zone] ?? [] as $id) {
                        if (isset($passages[$id][$key])) {
                            continue;
                        }
                        $passages[$id][$key] = [
                            'type' => $run['type'],
                            'feed' => $day->feed,
                            'bearing' => $this->bearing($day, $stops[$j - 1][0] ?? $zone, $stops[$j + 1][0] ?? $zone),
                            'time' => $stops[$j][$j === $last ? 1 : 2] ?? $stops[$j][1] ?? $stops[$j][2],
                            'first' => $j === 0,
                            'last' => $j === $last,
                            'dest' => $j === $last ? null : $this->place($day, $lastZone, $zoneStations, $stations),
                            'origin' => $j === 0 ? null : $this->place($day, $firstZone, $zoneStations, $stations),
                        ];
                    }
                }
                $this->addLine($lineTrips, $key, $run['type'], array_map(fn (array $s): array => $zoneStations[$s[0]] ?? [], $stops), $stationLines);
            }
            foreach ($day->zones as $zone => $stop) {
                if (! isset($zoneStations[$zone]) && isset($zoneCalls[$zone])) {
                    $unmatched[] = ['feed' => $day->feed, 'name' => $stop->name, 'uic' => $stop->uic, 'trains' => $zoneCalls[$zone]];
                }
            }
            $feeds[] = ($feedMeta[$i] ?? []) + ['feed' => $day->feed, 'version' => $day->version, 'date' => Ymd::iso($day->date),
                'horizon' => $day->horizon, 'trains' => count($day->runs), 'zones_matched' => $matchedZones, 'skipped_runs' => $day->skippedRuns];
        }

        $out = [];
        foreach ($passages as $id => $byKey) {
            $rows = array_values($byKey);
            $main = array_values(array_filter($rows, fn (array $r): bool => in_array($r['feed'], $primary, true)));
            $out[$id] = $this->summarize($main !== [] ? $main : $rows);
        }
        $lines = [];
        foreach ($lineTrips as $lineId => $trips) {
            $main = array_filter($trips, fn (string $k): bool => in_array(strstr($k, ':', true), $primary, true), ARRAY_FILTER_USE_KEY);
            $trips = $main !== [] ? $main : $trips;
            $types = array_count_values($trips);
            ksort($types);
            $lines[$lineId] = ['trains' => count($trips), 'by_type' => $types];
        }

        return new TimetableResult($out, $lines, $feeds, $unmatched);
    }

    /**
     * Course comptée sur chaque ligne dont deux arrêts consécutifs sont tous deux des gares.
     *
     * @param  array<int, array<string, string>>  $lineTrips
     * @param  list<list<int>>  $stopStations  gares de chaque arrêt de la course
     * @param  array<int, list<int>>  $stationLines
     */
    private function addLine(array &$lineTrips, string $key, string $type, array $stopStations, array $stationLines): void
    {
        for ($i = 1, $n = count($stopStations); $i < $n; $i++) {
            $a = $this->linesOf($stopStations[$i - 1], $stationLines);
            $b = $this->linesOf($stopStations[$i], $stationLines);
            foreach (array_intersect_key($a, $b) as $line => $_) {
                $lineTrips[$line][$key] = $type;
            }
        }
    }

    /**
     * @param  list<int>  $stationIds
     * @param  array<int, list<int>>  $stationLines
     * @return array<int, true>
     */
    private function linesOf(array $stationIds, array $stationLines): array
    {
        $out = [];
        foreach ($stationIds as $id) {
            foreach ($stationLines[$id] ?? [] as $line) {
                $out[$line] = true;
            }
        }

        return $out;
    }

    /**
     * Lieu d'une extrémité de course : la gare de VFF rapprochée, à défaut le nom de l'arrêt.
     *
     * @param  array<int, list<int>>  $zoneStations
     * @param  array<int, array{name: string, slug: string}>  $stations
     * @return array{name: string, slug: ?string, id: ?int}
     */
    private function place(TypicalDay $day, int $zone, array $zoneStations, array $stations): array
    {
        $id = $zoneStations[$zone][0] ?? null;
        if ($id !== null && isset($stations[$id])) {
            return ['id' => $id, 'name' => $stations[$id]['name'], 'slug' => $stations[$id]['slug']];
        }

        return ['id' => null, 'name' => mb_convert_case(mb_strtolower($day->zones[$zone]->name), MB_CASE_TITLE), 'slug' => null];
    }

    /** Direction de marche en degrés depuis le nord (0 à 360), null sans position. */
    private function bearing(TypicalDay $day, int $from, int $to): ?float
    {
        $a = $day->zones[$from];
        $b = $day->zones[$to];
        if ($from === $to || $a->lat === null || $a->lon === null || $b->lat === null || $b->lon === null) {
            return null;
        }
        $dy = $b->lat - $a->lat;
        $dx = ($b->lon - $a->lon) * cos(deg2rad(($a->lat + $b->lat) / 2));
        if (abs($dx) < 1e-9 && abs($dy) < 1e-9) {
            return null;
        }

        return fmod(rad2deg(atan2($dx, $dy)) + 360, 360);
    }

    /**
     * @param  list<array<string, mixed>>  $rows  passages de la gare
     * @return array<string, mixed>
     */
    private function summarize(array $rows): array
    {
        $axis = $this->axis($rows);
        $sides = [0 => [], 1 => []];
        foreach ($rows as $r) {
            $side = $r['bearing'] === null || $axis === null || cos(deg2rad($r['bearing'] - $axis)) >= 0 ? 0 : 1;
            $sides[$side][] = $r;
        }
        $directions = [];
        foreach ($sides as $side => $list) {
            if ($list !== []) {
                $heading = $axis === null ? null : self::COMPASS[(int) round(fmod($axis + 180 * $side + 360, 360) / 45) % 8];
                $directions[] = $this->block($list) + ['heading' => $heading] + $this->ends($list, self::MAX_DIRECTION_DESTINATIONS);
            }
        }
        usort($directions, fn (array $a, array $b): int => $b['trains'] <=> $a['trains']);

        return $this->block($rows) + ['feeds' => array_values(array_unique(array_column($rows, 'feed'))), 'directions' => $directions] + $this->ends($rows, self::MAX_DESTINATIONS);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{trains: int, by_type: array<string, int>, first: ?string, last: ?string}
     */
    private function block(array $rows): array
    {
        $types = array_count_values(array_column($rows, 'type'));
        ksort($types);
        // Premières et dernières heures de la journée de service : de 00:00 à 02:59 le lendemain ; l'arrivée d'un train de nuit
        // au petit matin du jour suivant (jusqu'à 32:30 dans le GTFS) compte dans les trains mais pas parmi les extrêmes.
        $times = array_filter(array_column($rows, 'time'), fn ($t): bool => $t !== null && $t < self::DAY_END);

        return ['trains' => count($rows), 'by_type' => $types,
            'first' => $times === [] ? null : self::clock((int) min($times)), 'last' => $times === [] ? null : self::clock((int) max($times))];
    }

    /**
     * Destinations directes (terminus des trains qui repartent de la gare) ; à défaut, provenances des trains qui y terminent.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{destinations: list<array<string, mixed>>, origins: list<array<string, mixed>>}
     */
    private function ends(array $rows, int $max): array
    {
        $destinations = $this->tally(array_column(array_filter($rows, fn (array $r): bool => $r['dest'] !== null), 'dest'), $max);
        $origins = $destinations === [] ? $this->tally(array_column(array_filter($rows, fn (array $r): bool => $r['origin'] !== null), 'origin'), $max) : [];

        return ['destinations' => $destinations, 'origins' => $origins];
    }

    /**
     * @param  list<array{name: string, slug: ?string, id: ?int}>  $places
     * @return list<array{name: string, slug: ?string, trains: int}>
     */
    private function tally(array $places, int $max): array
    {
        $counts = [];
        foreach ($places as $p) {
            $k = $p['id'] !== null ? 's'.$p['id'] : 'n'.$p['name'];
            $counts[$k] ??= ['name' => $p['name'], 'slug' => $p['slug'], 'trains' => 0];
            $counts[$k]['trains']++;
        }
        usort($counts, fn (array $a, array $b): int => [$b['trains'], $a['name']] <=> [$a['trains'], $b['name']]);

        return array_slice($counts, 0, $max);
    }

    /**
     * Axe moyen des directions de marche (degrés, modulo 180), null sans direction.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function axis(array $rows): ?float
    {
        $sin = $cos = 0.0;
        $n = 0;
        foreach ($rows as $r) {
            if ($r['bearing'] !== null) {
                $sin += sin(2 * deg2rad($r['bearing']));
                $cos += cos(2 * deg2rad($r['bearing']));
                $n++;
            }
        }

        return $n === 0 ? null : fmod(rad2deg(atan2($sin, $cos)) / 2 + 360, 360);
    }

    private static function clock(int $seconds): string
    {
        return sprintf('%02d:%02d', intdiv($seconds % 86400, 3600), intdiv($seconds % 3600, 60));
    }
}
