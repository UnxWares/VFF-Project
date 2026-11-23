<?php

namespace App\Services;

use App\Enums\AccidentReviewStatus;
use App\Models\Accident;
use Illuminate\Support\Str;

/**
 * Chronologie de la vie d'une gare (section « Chronologie » de /stations/{slug}, champ `chronology` de
 * `GET /api/v1/stations/{station}` et de GraphQL `StationSheet`) : une liste datée et triée qui réunit
 *
 * - les événements de la gare (station_events) : ouverture, fin des voyageurs, fin du fret, fermeture,
 *   réouverture, démolition. Les dates d'un même événement qui se recouvrent (Wikidata « 1934 », Wikipédia
 *   « 23 juillet 1934 ») ne font qu'une entrée : la plus précise, les autres sources dans `also`. Une date
 *   d'ouverture ou de fermeture qui contredit la date retenue par la fiche reste visible, marquée
 *   `retained: false` ;
 * - le début et la fin de la desserte par chaque ligne (station_lines, StationServiceDates), joints à
 *   l'ouverture ou à la fermeture quand ils tombent à la même date ;
 * - les noms portés, quand une source les date (alt_names avec `from`) ;
 * - les accidents dont la gare est la gare la plus proche du lieu, à 3 km au plus (accidents.nearest_station_id).
 *
 * Chaque entrée porte sa date, sa précision (`day`, `month`, `year`, `decade`, `after`, `before`, `range` :
 * mêmes valeurs que les dates de la fiche) et sa source. Lecture seule ; build() ne lit pas la base.
 */
final class StationChronology
{
    /** Distance maximale entre le lieu d'un accident et la gare (mètres). */
    public const ACCIDENT_RADIUS_M = 3000;

    /** Au plus autant d'accidents dans la chronologie (la liste complète reste `links.accidents`). */
    public const ACCIDENT_LIMIT = 30;

    /** Ordre d'entrées de même date : la vie de la gare se lit de l'ouverture à la démolition. */
    private const ORDER = [
        'opening' => 0, 'reopening' => 1, 'line_start' => 2, 'name' => 3, 'accident' => 4,
        'closure_passenger' => 5, 'closure_freight' => 6, 'closure' => 7, 'line_end' => 8, 'demolition' => 9,
    ];

    /** Précisions de la plus fine à la plus large (choix de l'entrée principale d'un groupe). */
    private const RANK = ['day' => 0, 'month' => 1, 'year' => 2, 'decade' => 3];

    /**
     * Accidents survenus à la gare ou tout près (fiches publiques, alias exclus par la portée du modèle),
     * dans la langue demandée.
     *
     * @return list<array{slug: string, name: string, date: string, date_precision: string, kind: string, deaths: ?int,
     *     injuries: ?int, distance_m: ?int, location_precision: ?string, to_review: bool}>
     */
    public function accidents(int $stationId, string $locale): array
    {
        return array_values(Accident::query()
            ->where('nearest_station_id', $stationId)
            // Un lieu connu à la commune près n'est « à X m » d'aucune gare (même si l'ancien rattachement reste en base).
            ->whereIn('location_precision', ['exact', 'level_crossing', 'station'])
            ->where(fn ($q) => $q->whereNull('nearest_station_distance_m')->orWhere('nearest_station_distance_m', '<=', self::ACCIDENT_RADIUS_M))
            ->orderBy('occurred_on')->orderBy('id')
            ->limit(self::ACCIDENT_LIMIT)
            ->get(['slug', 'name', 'occurred_on', 'date_precision', 'kind', 'deaths', 'injuries',
                'nearest_station_distance_m', 'location_precision', 'review_status'])
            ->map(fn (Accident $a): array => [
                'slug' => $a->slug,
                'name' => $a->nameIn($locale),
                'date' => $a->occurred_on->toDateString(),
                'date_precision' => $a->date_precision->value,
                'kind' => $a->kind->value,
                'deaths' => $a->deaths,
                'injuries' => $a->injuries,
                'distance_m' => $a->nearest_station_distance_m,
                'location_precision' => $a->location_precision?->value,
                'to_review' => $a->review_status === AccidentReviewStatus::ToReview,
            ])
            ->all());
    }

    /**
     * Chronologie triée à partir de la fiche (StationSheet::page : `station`, `events`, `lines`) et des accidents.
     *
     * @param  array<string, mixed>  $station  bloc `station` de la fiche
     * @param  iterable<array<string, mixed>>  $events  événements de la fiche
     * @param  iterable<array<string, mixed>>  $lines  lignes desservies de la fiche
     * @param  list<array<string, mixed>>  $accidents  accidents (accidents())
     * @return list<array<string, mixed>>
     */
    public static function build(array $station, iterable $events, iterable $lines, array $accidents = []): array
    {
        $entries = self::eventEntries($station, $events);
        $entries = self::withLines($entries, $lines);
        foreach (self::nameEntries($station) as $e) {
            $entries[] = $e;
        }
        foreach ($accidents as $a) {
            $entries[] = self::entry('accident', null, $a['date'], $a['date_precision'], 'vff_accidents', '/accidents/'.$a['slug'])
                + ['accident' => $a];
        }

        usort($entries, self::compare(...));

        return array_map(fn (array $e): array => $e + ['accident' => null, 'name' => null], $entries);
    }

    /**
     * Événements de la gare regroupés : un groupe par événement, la date la plus précise en tête.
     *
     * @param  array<string, mixed>  $station
     * @param  iterable<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private static function eventEntries(array $station, iterable $events): array
    {
        $byType = [];
        foreach ($events as $e) {
            if (! is_string($e['date'] ?? null) || $e['date'] === '') {
                continue;
            }
            $byType[(string) $e['type']][] = $e;
        }

        $out = [];
        foreach ($byType as $type => $list) {
            // La plus précise d'abord ; à précision égale, une source qui parle de la gare avant une date déduite.
            usort($list, fn ($a, $b) => [self::RANK[$a['precision']] ?? 9, (int) (bool) $a['inferred'], $a['date']]
                <=> [self::RANK[$b['precision']] ?? 9, (int) (bool) $b['inferred'], $b['date']]);
            $groups = [];
            foreach ($list as $e) {
                $span = self::span($e['date'], (string) $e['precision']);
                foreach ($groups as &$g) {
                    if ($span !== null && $g['_span'] !== null && $span[0] <= $g['_span'][0] && $span[1] >= $g['_span'][1]) {
                        if ($e['source'] !== $g['source'] && ! in_array($e['source'], array_column($g['also'], 'source'), true)) {
                            $g['also'][] = ['source' => $e['source'], 'source_url' => $e['source_url'] ?? null, 'date' => $e['date'],
                                'precision' => $e['precision'], 'estimated' => (bool) $e['inferred'],
                                'note' => $e['note'] ?? null, 'note_lang' => $e['note_lang'] ?? null];
                        }

                        continue 2;
                    }
                }
                unset($g);
                $groups[] = self::entry('event', $type, $e['date'], (string) $e['precision'], (string) $e['source'], $e['source_url'] ?? null,
                    (bool) $e['inferred'], $e['note'] ?? null, $e['note_lang'] ?? null) + ['_span' => $span];
            }
            foreach ($groups as $g) {
                $out[] = $g;
            }
        }

        foreach (['opening' => 'opened', 'closure' => 'closed'] as $type => $prefix) {
            $out = self::retain($out, $station, $type, $prefix);
        }

        return array_map(fn (array $e): array => array_diff_key($e, ['_span' => true]), $out);
    }

    /**
     * Date retenue par la fiche (opened_at, closed_at) : le groupe qui la recouvre est retenu, les autres groupes du
     * même événement non. Une ouverture ou une fermeture encadrée (précision `range`) prend l'intervalle de la fiche.
     * Sans groupe qui la recouvre, la date retenue est ajoutée, avec la source de la fiche.
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, mixed>  $station
     * @return list<array<string, mixed>>
     */
    private static function retain(array $entries, array $station, string $type, string $prefix): array
    {
        $date = $station["{$prefix}_at"] ?? null;
        if (! is_string($date) || $date === '') {
            return $entries;
        }
        $precision = (string) ($station["{$prefix}_precision"] ?? 'day');
        $after = is_int($station["{$prefix}_after"] ?? null) ? $station["{$prefix}_after"] : null;
        $span = $precision === 'range'
            ? [($after !== null ? ($after + 1).'-01-01' : '0000-01-01'), substr($date, 0, 4).'-12-31']
            : self::span($date, $precision);

        $found = false;
        foreach ($entries as $i => $e) {
            if ($e['type'] !== $type) {
                continue;
            }
            $overlaps = $span !== null && $e['_span'] !== null && $e['_span'][0] <= $span[1] && $e['_span'][1] >= $span[0];
            // Une borne (avant, après) ne contredit rien : retenue.
            $entries[$i]['retained'] = $overlaps || $span === null || $e['_span'] === null;
            if ($overlaps && ! $found) {
                $found = true;
                if ($precision === 'range' && $e['estimated']) {
                    $entries[$i]['date'] = $date;
                    $entries[$i]['precision'] = 'range';
                    $entries[$i]['after'] = $after;
                }
            }
        }
        if (! $found) {
            $source = (string) (($station['provenance'] ?? [])["{$prefix}_at"] ?? 'vff');
            $entries[] = array_replace(self::entry('event', $type, $date, $precision, $source, null, str_starts_with($source, 'inferred')),
                ['after' => $precision === 'range' ? $after : null, '_span' => $span]);
        }

        return $entries;
    }

    /**
     * Début et fin de desserte de chaque ligne, joints à l'ouverture (ou la réouverture) et à la fermeture de même
     * date, sinon entrées à part. Années tirées des dates de la gare (`years_source` = station) : rien de plus que
     * l'ouverture et la fermeture de la gare, pas d'entrée.
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  iterable<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private static function withLines(array $entries, iterable $lines): array
    {
        foreach ($lines as $l) {
            if (($l['never_served'] ?? null) !== null || ($l['years_source'] ?? null) === 'station') {
                continue;
            }
            $ref = ['slug' => $l['slug'], 'code' => $l['code'] ?? null, 'name' => $l['name']];
            $bounds = [
                'line_start' => self::lineBound($l['service_start'] ?? null, $l['first_year'] ?? null, 'year'),
                'line_end' => self::lineBound($l['service_end'] ?? null, $l['last_year'] ?? null, $l['last_year_kind'] ?? null),
            ];
            foreach ($bounds as $kind => $bound) {
                if ($bound === null) {
                    continue;
                }
                $joins = $kind === 'line_start' ? ['opening', 'reopening'] : ['closure', 'closure_passenger'];
                $span = self::span($bound['date'], $bound['precision']);
                $target = null;
                foreach ($entries as $i => $e) {
                    $eSpan = self::span($e['date'], $e['precision'] === 'range' ? 'year' : $e['precision']);
                    if ($e['kind'] === 'event' && in_array($e['type'], $joins, true) && ($e['retained'] ?? true)
                        && $span !== null && $eSpan !== null && $eSpan[0] <= $span[1] && $eSpan[1] >= $span[0]) {
                        $target = $i;
                        break;
                    }
                }
                if ($target !== null) {
                    $entries[$target]['lines'][] = $ref + ['role' => $kind === 'line_start' ? 'start' : 'end'];

                    continue;
                }
                $entries[] = array_replace(self::entry($kind, null, $bound['date'], $bound['precision'], 'line_events', null),
                    ['lines' => [$ref + ['role' => $kind === 'line_start' ? 'start' : 'end']], 'event_id' => $bound['event_id']]);
            }
        }

        return $entries;
    }

    /**
     * Borne datée d'une desserte : date au jour ou au mois de l'événement de ligne, sinon l'année (fin « au plus
     * tard » : intervalle ouvert, fin non datée : rien).
     *
     * @param  ?array<string, mixed>  $dated  service_start ou service_end de la ligne (StationServiceDates::bounds)
     * @return ?array{date: string, precision: string, event_id: ?int}
     */
    private static function lineBound(?array $dated, mixed $year, ?string $kind): ?array
    {
        if (is_array($dated) && is_string($dated['date'] ?? null)) {
            return ['date' => $dated['date'], 'precision' => (string) ($dated['precision'] ?? 'day'),
                'event_id' => is_int($dated['event_id'] ?? null) ? $dated['event_id'] : null];
        }
        if (! is_numeric($year)) {
            return null;
        }

        return match ($kind) {
            'year' => ['date' => ((int) $year).'-01-01', 'precision' => 'year', 'event_id' => null],
            // « au plus tard en 1989 » (formatStationDate : intervalle sans borne basse).
            'upper_bound' => ['date' => ((int) $year).'-12-31', 'precision' => 'range', 'event_id' => null],
            default => null,
        };
    }

    /**
     * Noms portés et datés par une source (alt_names avec `from`), sauf une simple variante d'écriture du nom
     * de la fiche (« Point du Jour » pour « Le Point-du-Jour », « Carency Station » pour « Carency »).
     *
     * @param  array<string, mixed>  $station
     * @return list<array<string, mixed>>
     */
    private static function nameEntries(array $station): array
    {
        $key = fn (string $n): string => (string) preg_replace(['/\b(gare|station|halte|de|du|des|la|le|les|l|d)\b/u', '/[^a-z0-9]+/'], ['', ''],
            Str::ascii(mb_strtolower($n)));
        $own = $key((string) $station['name']);
        $out = [];
        foreach ((array) ($station['alt_names'] ?? []) as $a) {
            if (! is_array($a) || ! is_string($a['name'] ?? null) || ($key($a['name']) === $own && ! ($a['renamed'] ?? false))) {
                continue;
            }
            $from = is_numeric($a['from'] ?? null) ? (int) $a['from'] : null;
            $to = is_numeric($a['to'] ?? null) ? (int) $a['to'] : null;
            if ($from === null && $to === null) {
                continue;
            }
            // Un nom dont on ne connaît que la fin (« Orléans-Ceinture » jusqu'en 1970) est placé à sa fin.
            [$date, $precision] = $from !== null
                ? self::partial($a['from_date'] ?? null, $a['from_precision'] ?? null, $from)
                : self::partial($a['to_date'] ?? null, $a['to_precision'] ?? null, (int) $to);
            $out[] = self::entry('name', null, $date, $precision, (string) ($a['source'] ?? 'vff'), is_string($a['source_url'] ?? null) && $a['source_url'] !== '' ? $a['source_url'] : null)
                + ['name' => ['name' => $a['name'], 'from' => $from, 'to' => $to]];
        }

        return $out;
    }

    /**
     * Entrée de la chronologie, champs communs à tous les genres.
     *
     * @return array<string, mixed>
     */
    private static function entry(string $kind, ?string $type, string $date, string $precision, string $source, ?string $url,
        bool $estimated = false, ?string $note = null, ?string $noteLang = null): array
    {
        return [
            'kind' => $kind, 'type' => $type, 'date' => $date, 'precision' => $precision, 'after' => null,
            'estimated' => $estimated, 'retained' => true, 'source' => $source, 'source_url' => $url, 'also' => [],
            'note' => $note, 'note_lang' => $noteLang, 'lines' => [], 'event_id' => null,
        ];
    }

    /**
     * Date ISO complète et précision d'une date écrite à l'année, au mois ou au jour (`2024`, `1940-07`, `1940-07-01`).
     *
     * @return array{0: string, 1: string}
     */
    private static function partial(mixed $date, mixed $precision, int $year): array
    {
        $d = is_string($date) ? $date : '';
        if (in_array($precision, ['day', 'month', 'decade'], true) && preg_match('/^\d{4}(-\d\d){0,2}$/', $d) === 1 && (int) substr($d, 0, 4) === $year) {
            return [str_pad($d, 10, strlen($d) === 4 ? '-01-01' : '-01'), (string) $precision];
        }

        return [sprintf('%04d-01-01', $year), 'year'];
    }

    /**
     * Intervalle de dates que couvre une date à sa précision (jour, mois, année, décennie), null pour une borne
     * (avant, après, intervalle) qu'on ne fusionne pas.
     *
     * @return ?array{0: string, 1: string}
     */
    public static function span(string $date, string $precision): ?array
    {
        $y = substr($date, 0, 4);

        return match ($precision) {
            'day' => [substr($date, 0, 10), substr($date, 0, 10)],
            'month' => [substr($date, 0, 7).'-01', substr($date, 0, 7).'-31'],
            'year' => ["{$y}-01-01", "{$y}-12-31"],
            'decade' => [substr($y, 0, 3).'0-01-01', substr($y, 0, 3).'9-12-31'],
            default => null,
        };
    }

    /**
     * Ordre de deux entrées : par date ; la même année, quand l'une n'est datée qu'à l'année (ou moins
     * précisément), par l'ordre de la vie d'une gare (ORDER) : une démolition « 1949 » après la fermeture du
     * « 31 décembre 1949 », un nom porté « dès 1869 » après l'ouverture du « 29 octobre 1869 ».
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function compare(array $a, array $b): int
    {
        [$da, $db] = [self::sortDate($a), self::sortDate($b)];
        [$oa, $ob] = [self::ORDER[$a['type'] ?? $a['kind']] ?? 99, self::ORDER[$b['type'] ?? $b['kind']] ?? 99];
        $coarse = fn (array $e): bool => ! in_array($e['precision'], ['day', 'month'], true);
        if (substr($da, 0, 4) === substr($db, 0, 4) && ($coarse($a) || $coarse($b))) {
            return [$oa, $da] <=> [$ob, $db];
        }

        return [$da, $oa] <=> [$db, $ob];
    }

    /**
     * Clé de tri : un intervalle se range à sa borne basse (« entre 1847 et 1860 » avec 1847).
     *
     * @param  array<string, mixed>  $e
     */
    private static function sortDate(array $e): string
    {
        if ($e['precision'] === 'range' && is_int($e['after'] ?? null)) {
            return ($e['after'] + 1).'-01-01';
        }

        return (string) $e['date'];
    }
}
