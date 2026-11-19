<?php

namespace App\Services;

use App\Console\Commands\AttachStations;
use App\Enums\ServiceEnd;
use App\Support\EventText;

/**
 * Dates de début et de fin de desserte d'une gare par une ligne, au jour ou au mois quand l'événement
 * de ligne qui les donne l'est, avec cet événement (line_events.id, `GET /api/v1/events/{id}`). Une
 * seule règle pour la fiche gare (lignes desservies), la fiche ligne (gares), REST, GraphQL et MCP.
 *
 * vff:attach-stations garde dans station_lines la date et la précision de l'événement retenu
 * (first_event_date / last_event_date), puis vff:build-station-eras borne les années par les dates de
 * la gare. La date de l'événement ne vaut donc pour la desserte que si :
 *   - son année est encore celle de la période (first_year / last_year) : sinon la gare a ouvert plus
 *     tard, ou fermé plus tôt, et c'est sa propre date qui borne la desserte ;
 *   - la gare n'a pas ouvert (ou fermé) la même année à une date qui pourrait la contredire : la date
 *     de la gare, si elle vient d'une source, doit tomber au plus tard le jour de l'ouverture de la
 *     ligne (au plus tôt le jour de la fin), sinon rien n'est affirmé au jour ;
 *   - la fin est une vraie date (ServiceEnd::Year), jamais une borne ni une fin non datée ;
 *   - la ligne a desservi la gare (years_source différent de never_served).
 * Sinon la borne est nulle et seules les années restent. La précision est celle de l'événement
 * (day, month, year) : une date à l'année n'est jamais écrite au jour.
 *
 * L'identifiant de l'événement est retrouvé à la lecture (columns) : même ligne, même type de
 * fait, même date et même précision que la date gardée, en préférant un événement qui couvre la gare
 * (AttachStations::EVENT_COVERS_STATION) puis, pour la fin, une fermeture plutôt qu'un déclassement.
 */
final class StationServiceDates
{
    /** Types d'événements de ligne qui ouvrent la desserte (AttachStations::computeServiceYears). */
    private const OPENING_TYPES = "('opening', 'opening_passenger', 'opening_freight')";

    /** Types d'événements de ligne qui la terminent ; les deux premiers mettent fin au service. */
    private const END_TYPES = "('closure_passenger', 'closure', 'declassification', 'dismantling')";

    /** Mêmes colonnes, toutes nulles (rattachement de repli sans station_lines). */
    public const NULL_COLUMNS = 'NULL::date AS first_event_date, NULL AS first_event_precision, NULL::date AS last_event_date,
        NULL AS last_event_precision, NULL::bigint AS first_event_id, NULL::bigint AS last_event_id,
        NULL::date AS station_opened_at, NULL AS station_opened_precision, NULL AS station_opened_source,
        NULL::date AS station_closed_at, NULL AS station_closed_precision, NULL AS station_closed_source';

    /**
     * Colonnes à ajouter à une requête sur station_lines (alias sl) jointe à stations (alias s) :
     * dates et précisions des événements, leurs identifiants et les dates de la gare utiles à bounds().
     */
    public static function columns(): string
    {
        // Même règle qu'AttachStations, la distance de 300 m mesurée en degrés (0,004°, 300 à 450 m en
        // France) : elle ne sert qu'à départager deux événements de même date, et la distance géographique
        // sur une section entière coûtait 3 ms par gare.
        $covers = str_replace('ST_DWithin(le.section_geom::geography, s.geom::geography, 300)', 'ST_DWithin(le.section_geom, s.geom, 0.004)',
            AttachStations::EVENT_COVERS_STATION);
        $opening = self::OPENING_TYPES;
        $end = self::END_TYPES;

        return <<<SQL
            sl.first_event_date, sl.first_event_precision, sl.last_event_date, sl.last_event_precision,
            CASE WHEN sl.first_event_date IS NOT NULL THEN (
                SELECT le.id FROM line_events le
                WHERE le.line_id = sl.line_id AND le.type IN {$opening}
                  AND le.event_date = sl.first_event_date AND le.date_precision IS NOT DISTINCT FROM sl.first_event_precision
                ORDER BY {$covers} DESC, le.id LIMIT 1) END AS first_event_id,
            CASE WHEN sl.last_event_date IS NOT NULL THEN (
                SELECT le.id FROM line_events le
                WHERE le.line_id = sl.line_id AND le.type IN {$end}
                  AND le.event_date = sl.last_event_date AND le.date_precision IS NOT DISTINCT FROM sl.last_event_precision
                ORDER BY {$covers} DESC, le.type IN ('closure_passenger', 'closure') DESC, le.id LIMIT 1) END AS last_event_id,
            s.opened_at AS station_opened_at, s.opened_precision AS station_opened_precision,
            s.provenance->>'opened_at' AS station_opened_source,
            s.closed_at AS station_closed_at, s.closed_precision AS station_closed_precision,
            s.provenance->>'closed_at' AS station_closed_source
            SQL;
    }

    /**
     * Début et fin de desserte datés d'une ligne de requête portant columns(), first_year, last_year et
     * years_source.
     *
     * @return array{start: ?array{date: string, precision: string, event_id: ?int}, end: ?array{date: string, precision: string, event_id: ?int}}
     */
    public static function bounds(object $row, ?int $currentYear = null): array
    {
        $currentYear ??= (int) now()->year;
        $firstYear = $row->first_year !== null ? (int) $row->first_year : null;
        $lastYear = $row->last_year !== null ? (int) $row->last_year : null;
        if ($row->years_source === 'never_served') {
            return ['start' => null, 'end' => null];
        }
        $start = self::bound($row->first_event_date ?? null, $row->first_event_precision ?? null, $row->first_event_id ?? null, $firstYear);
        if ($start !== null) {
            $opened = self::stationDate($row->station_opened_at ?? null, $row->station_opened_precision ?? null, $row->station_opened_source ?? null);
            // La gare a ouvert la même année : la ligne ne la dessert pas avant, sauf preuve qu'elle était déjà ouverte.
            if ($opened !== null && (int) substr($opened[0], 0, 4) === $firstYear
                && ! self::notAfter($opened[0], $opened[1], $start['date'], $start['precision'])) {
                $start = null;
            }
        }
        $kind = ServiceEnd::of($lastYear, self::iso($row->station_closed_at ?? null), $row->station_closed_precision ?? null, $currentYear);
        $end = $kind === ServiceEnd::Year
            ? self::bound($row->last_event_date ?? null, $row->last_event_precision ?? null, $row->last_event_id ?? null, $lastYear)
            : null;
        if ($end !== null) {
            $closed = self::stationDate($row->station_closed_at ?? null, $row->station_closed_precision ?? null, $row->station_closed_source ?? null);
            if ($closed !== null && $closed[0] <= now()->toDateString() && (int) substr($closed[0], 0, 4) === $lastYear
                && ! self::notAfter($end['date'], $end['precision'], $closed[0], $closed[1])) {
                $end = null;
            }
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Borne de bounds() rédigée à sa précision pour le MCP (`when`, dans la langue demandée) : jamais un
     * jour pour une date au mois ou à l'année.
     *
     * @param  ?array{date: string, precision: string, event_id: ?int}  $bound
     * @return ?array{date: string, precision: string, when: string, event_id: ?int}
     */
    public static function written(?array $bound, string $lang): ?array
    {
        return $bound === null ? null : ['date' => $bound['date'], 'precision' => $bound['precision'],
            'when' => EventText::isoDate($bound['date'], $bound['precision'], $lang), 'event_id' => $bound['event_id']];
    }

    /**
     * Date d'un événement de ligne, si son année est celle de la période.
     *
     * @return ?array{date: string, precision: string, event_id: ?int}
     */
    private static function bound(mixed $date, ?string $precision, mixed $eventId, ?int $year): ?array
    {
        $iso = self::iso($date);
        if ($iso === null || $year === null || (int) substr($iso, 0, 4) !== $year || ! in_array($precision, ['day', 'month', 'year'], true)) {
            return null;
        }

        return ['date' => $iso, 'precision' => $precision, 'event_id' => $eventId !== null ? (int) $eventId : null];
    }

    /**
     * Date de la gare tirée d'une source (une date déduite des lignes ne contredit rien).
     *
     * @return ?array{0: string, 1: ?string}
     */
    private static function stationDate(mixed $date, ?string $precision, ?string $source): ?array
    {
        $iso = self::iso($date);
        if ($iso === null || str_starts_with((string) $source, 'inferred:')) {
            return null;
        }

        return [$iso, $precision];
    }

    /**
     * La date a tombe sûrement au plus tard le jour de la date b : au jour si les deux sont au jour, au
     * mois (mois strictement antérieur) si l'une n'est qu'au mois ; jamais à l'année.
     */
    public static function notAfter(string $a, ?string $aPrecision, string $b, ?string $bPrecision): bool
    {
        $aPrecision ??= 'day';
        $bPrecision ??= 'day';
        if ($aPrecision === 'day' && $bPrecision === 'day') {
            return $a <= $b;
        }
        if (in_array($aPrecision, ['day', 'month'], true) && in_array($bPrecision, ['day', 'month'], true)) {
            return substr($a, 0, 7) < substr($b, 0, 7);
        }

        return false;
    }

    private static function iso(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
    }
}
