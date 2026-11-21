<?php

namespace App\Services\StationAudit;

/**
 * Note des dates et des services d'une gare, et contrôles croisés (fonction pure, testée).
 *
 * Entrée : l'état de la gare tel que VFF le publie (stations.opened_at / closed_at et leur source
 * dans provenance, station_events, années de desserte de station_lines, drapeau fret SNCF, dates
 * candidates comparées). Rien n'est recalculé : l'audit dit seulement d'où vient chaque date.
 *
 * Services (voyageurs, fret) : état du jour (`open`, `closed`, `unknown`) d'après stations.status
 * (`active` et `tourist` : voyageurs ; `no_commercial` : fret seul ; `declassified` : fermée), date de
 * fin (événement `closure_passenger` ou `closure_freight`, sinon la fermeture totale) et sa note.
 *
 * Contrôles croisés (codes de `checks`) :
 *   closure_before_opening   fermeture antérieure à l'ouverture ;
 *   opening_before_lines     ouverture plus d'un an avant la première desserte connue de ses lignes ;
 *   open_after_lines         gare ouverte alors que toutes ses lignes ont fini leur desserte (plus d'un an) ;
 *   freight_closed_before_opening  fin du fret antérieure à l'ouverture ;
 *   passenger_after_closure  fin des voyageurs plus d'un an après la fermeture totale ;
 *   wikidata_differs         une date Wikidata contredit la date de VFF de plus d'un an ;
 *   far_from_track           position à plus de 50 m de la voie de ses lignes ;
 *   opening_before_map       ouverture antérieure d'au moins une ère à la première ère où la carte montre la gare
 *                            ouverte (ère = instantané au 31/12 de l'année ; 1 184 cas relevés par l'agent frises,
 *                            presque tous des ouvertures `inferred:line_event`) ;
 *   era_passenger_mismatch   statut d'une ère contraire à la fin sourcée des voyageurs : « fret seul » avant la
 *                            fin des voyageurs (Messei en 1970, fermée aux voyageurs en 1973), ou ouverte aux
 *                            voyageurs après elle ;
 *   closed_without_date      gare fermée sans date de fermeture, ou avec un intervalle qui s'arrête au jour du
 *                            calcul (« fermée après sa dernière ère ouverte », 8 haltes de 405000) ;
 *   freight_only_undated     « fret seul » à une ère sans aucune fin des voyageurs datée.
 */
final class DateAuditor
{
    private const OPEN_PASSENGER = ['active', 'tourist'];

    private const FREIGHT_ONLY = ['no_commercial'];

    /**
     * @param  array{
     *     status: string,
     *     opened_at: ?string, opened_precision: ?string, opened_source: ?string,
     *     closed_at: ?string, closed_precision: ?string, closed_source: ?string,
     *     events: list<array{type: string, date: string, precision: ?string, source: string, inferred: bool}>,
     *     lines: array{count: int, first_year: ?int, last_year: ?int, open: int},
     *     freight_flag: ?bool,
     *     candidates: list<array{kind: string, comparison: string, delta_days: ?int, date: string, precision: string}>,
     *     track_offset_m: ?int,
     *     today_year: int,
     *     eras?: list<array{year: int, status: string}>,
     * }  $facts
     */
    public static function audit(array $facts): DatesResult
    {
        $status = $facts['status'];
        $closedNow = ! in_array($status, [...self::OPEN_PASSENGER, ...self::FREIGHT_ONLY], true);

        $opening = DateGrade::of($facts['opened_at'], $facts['opened_source'], $facts['opened_precision']);
        $closure = match (true) {
            ! $closedNow => DateGrade::NotApplicable,
            self::undatedClosure($facts) => DateGrade::Unknown,
            default => DateGrade::of($facts['closed_at'], $facts['closed_source'], $facts['closed_precision']),
        };

        $services = [
            'passenger' => self::service(
                open: in_array($status, self::OPEN_PASSENGER, true),
                known: true,
                event: self::firstEvent($facts['events'], 'closure_passenger') ?? self::closureEvent($facts),
            ),
            'freight' => self::service(
                open: in_array($status, self::FREIGHT_ONLY, true) || (! $closedNow && $facts['freight_flag'] === true),
                known: $closedNow || in_array($status, self::FREIGHT_ONLY, true) || $facts['freight_flag'] !== null,
                event: self::firstEvent($facts['events'], 'closure_freight') ?? ($closedNow ? self::closureEvent($facts) : null),
            ),
        ];

        return new DatesResult($opening, $closure, $services, self::checks($facts, $closedNow));
    }

    /**
     * @param  array{type: string, date: string, precision: ?string, source: string, inferred: bool}|null  $event
     * @return array{state: string, closed_on: ?string, closed_precision: ?string, source: ?string, grade: string}
     */
    private static function service(bool $open, bool $known, ?array $event): array
    {
        if ($open) {
            return ['state' => 'open', 'closed_on' => null, 'closed_precision' => null, 'source' => null, 'grade' => DateGrade::NotApplicable->value];
        }
        if (! $known) {
            return ['state' => 'unknown', 'closed_on' => null, 'closed_precision' => null, 'source' => null, 'grade' => DateGrade::Unknown->value];
        }

        return [
            'state' => 'closed',
            'closed_on' => $event['date'] ?? null,
            'closed_precision' => $event['precision'] ?? null,
            'source' => $event['source'] ?? null,
            'grade' => DateGrade::of($event['date'] ?? null, $event === null ? null : ($event['inferred'] ? 'inferred:' : $event['source']), $event['precision'] ?? null)->value,
        ];
    }

    /**
     * Fermeture totale publiée (stations.closed_at), vue comme un événement.
     *
     * @return array{type: string, date: string, precision: ?string, source: string, inferred: bool}|null
     */
    private static function closureEvent(array $facts): ?array
    {
        if ($facts['closed_at'] === null) {
            return null;
        }
        $source = (string) ($facts['closed_source'] ?? '');

        return [
            'type' => 'closure', 'date' => $facts['closed_at'], 'precision' => $facts['closed_precision'],
            'source' => $source, 'inferred' => $source === '' || $source === 'vff:eras' || str_starts_with($source, 'inferred:') || $facts['closed_precision'] === 'range',
        ];
    }

    /**
     * Premier événement d'un type, les événements sourcés avant les déduits.
     *
     * @param  list<array{type: string, date: string, precision: ?string, source: string, inferred: bool}>  $events
     * @return array{type: string, date: string, precision: ?string, source: string, inferred: bool}|null
     */
    private static function firstEvent(array $events, string $type): ?array
    {
        $matching = array_values(array_filter($events, fn (array $e): bool => $e['type'] === $type));
        usort($matching, fn (array $a, array $b): int => [$a['inferred'], $a['date']] <=> [$b['inferred'], $b['date']]);

        return $matching[0] ?? null;
    }

    /** @return list<array{code: string, detail: string}> */
    private static function checks(array $facts, bool $closedNow): array
    {
        $checks = [];
        $openYear = self::year($facts['opened_at']);
        $closeYear = self::year($facts['closed_at']);
        $lines = $facts['lines'];

        if ($facts['opened_at'] !== null && $facts['closed_at'] !== null && $facts['closed_at'] < $facts['opened_at']) {
            $checks[] = ['code' => 'closure_before_opening', 'detail' => "{$facts['closed_at']} < {$facts['opened_at']}"];
        }
        if ($openYear !== null && $lines['first_year'] !== null && $openYear < $lines['first_year'] - 1) {
            $checks[] = ['code' => 'opening_before_lines', 'detail' => "{$openYear} < {$lines['first_year']}"];
        }
        if (! $closedNow && $lines['count'] > 0 && $lines['open'] === 0 && $lines['last_year'] !== null
            && $lines['last_year'] < $facts['today_year'] - 1) {
            $checks[] = ['code' => 'open_after_lines', 'detail' => (string) $lines['last_year']];
        }
        $freight = self::firstEvent($facts['events'], 'closure_freight');
        if ($freight !== null && $facts['opened_at'] !== null && $freight['date'] < $facts['opened_at']) {
            $checks[] = ['code' => 'freight_closed_before_opening', 'detail' => "{$freight['date']} < {$facts['opened_at']}"];
        }
        $passenger = self::firstEvent($facts['events'], 'closure_passenger');
        if ($passenger !== null && $closeYear !== null && (self::year($passenger['date']) ?? 0) > $closeYear + 1) {
            $checks[] = ['code' => 'passenger_after_closure', 'detail' => "{$passenger['date']} > {$facts['closed_at']}"];
        }
        foreach ($facts['candidates'] as $c) {
            if ($c['comparison'] === 'differs' && in_array($c['kind'], ['opening', 'closure'], true) && abs((int) $c['delta_days']) > 366) {
                $checks[] = ['code' => 'wikidata_differs', 'detail' => "{$c['kind']} {$c['date']}"];
            }
        }
        if ($facts['track_offset_m'] !== null && $facts['track_offset_m'] > PlacementGrader::TRACK_FAR_M) {
            $checks[] = ['code' => 'far_from_track', 'detail' => "{$facts['track_offset_m']} m"];
        }
        if ($closedNow && ($facts['closed_at'] === null || self::undatedClosure($facts))) {
            $checks[] = ['code' => 'closed_without_date', 'detail' => (string) $facts['status']];
        }
        array_push($checks, ...self::eraChecks($facts, $openYear));

        return $checks;
    }

    /**
     * Fermeture « non datée » : l'intervalle des ères s'arrête au jour du calcul (stations.closed_at = l'année en
     * cours, précision range). VFF sait seulement que la gare a fermé après sa dernière ère ouverte.
     */
    private static function undatedClosure(array $facts): bool
    {
        return $facts['closed_at'] !== null && $facts['closed_precision'] === 'range'
            && self::year($facts['closed_at']) >= $facts['today_year'];
    }

    /**
     * Contrôles entre les dates et le statut de la gare à chaque ère de la carte.
     *
     * @return list<array{code: string, detail: string}>
     */
    private static function eraChecks(array $facts, ?int $openYear): array
    {
        $eras = $facts['eras'] ?? [];
        $checks = [];
        $open = array_values(array_filter($eras, fn (array $e): bool => in_array($e['status'], [...self::OPEN_PASSENGER, ...self::FREIGHT_ONLY], true)));
        $first = $open === [] ? null : min(array_column($open, 'year'));
        if ($openYear !== null && $first !== null && $openYear <= $first - 10) {
            $checks[] = ['code' => 'opening_before_map', 'detail' => "{$facts['opened_at']} ({$facts['opened_source']}) < {$first}"];
        }
        // Fin des voyageurs sourcée : événement closure_passenger relevé, sinon fermeture totale relevée.
        $end = self::firstEvent(array_values(array_filter($facts['events'], fn (array $e): bool => ! $e['inferred'])), 'closure_passenger');
        $endYear = self::year($end['date'] ?? null);
        if ($endYear === null && DateGrade::of($facts['closed_at'], $facts['closed_source'], $facts['closed_precision']) === DateGrade::Sourced) {
            $endYear = self::year($facts['closed_at']);
        }
        // « Fret seul » sur la carte sans aucune fin des voyageurs datée (Messei en 1970 : Wikipédia la date de 1973).
        $freightEra = array_values(array_filter($eras, fn (array $e): bool => $e['status'] === 'no_commercial'));
        if ($freightEra !== [] && self::firstEvent($facts['events'], 'closure_passenger') === null) {
            $checks[] = ['code' => 'freight_only_undated', 'detail' => (string) $freightEra[0]['year']];
        }
        // Une réouverture après la fin des voyageurs rend la suite des ères légitime : pas de contrôle.
        $reopened = $endYear !== null && array_filter($facts['events'], fn (array $e): bool => $e['type'] === 'reopening' && (self::year($e['date']) ?? 0) >= $endYear) !== [];
        if ($endYear !== null && ! $reopened) {
            foreach ($eras as $e) {
                // Instantané au 31/12 de l'année de l'ère : fin des voyageurs après l'ère, la gare y avait des voyageurs.
                $wrong = ($e['status'] === 'no_commercial' && $endYear > $e['year'])
                    || (in_array($e['status'], self::OPEN_PASSENGER, true) && $endYear <= $e['year'] && $e['year'] < $facts['today_year']);
                if ($wrong) {
                    $checks[] = ['code' => 'era_passenger_mismatch', 'detail' => "{$e['year']} {$e['status']}, voyageurs jusqu'en {$endYear}"];
                    break;
                }
            }
        }

        return $checks;
    }

    private static function year(?string $date): ?int
    {
        return $date !== null && preg_match('/^(-?\d{1,4})/', $date, $m) ? (int) $m[1] : null;
    }
}
