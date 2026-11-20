<?php

namespace App\Enums;

use App\Services\StationTimeline;

/**
 * Nature de la dernière année de desserte d'une gare par une ligne (station_lines.last_year), la même
 * partout : fiche gare, fiche ligne, REST, GraphQL et MCP.
 *
 * vff:build-station-eras (reconcileServiceYears) borne la desserte par la fermeture retenue de la gare.
 * Quand cette fermeture n'est connue que par un intervalle (stations.closed_precision = range, « entre
 * 1871 et 2026 »), last_year prend l'année de la borne haute : c'est une limite, pas une date.
 */
enum ServiceEnd: string
{
    /** Année de fin tirée d'une date : événement de la ligne, tracé daté ou fermeture datée de la gare. */
    case Year = 'year';

    /** Borne haute : la desserte a cessé au plus tard cette année, date inconnue. */
    case UpperBound = 'upper_bound';

    /**
     * Fin non datée : la gare est fermée aujourd'hui, aucune source ne date sa fermeture, et la borne
     * n'est que l'année du calcul.
     */
    case Undated = 'undated';

    /**
     * Nature de last_year (null si la desserte n'a pas de fin connue).
     *
     * @param  ?string  $closedAt  fermeture retenue de la gare (stations.closed_at, AAAA-MM-JJ)
     * @param  int  $currentYear  année en cours : une borne de cette année ou après est « fin non datée »
     */
    public static function of(?int $lastYear, ?string $closedAt, ?string $closedPrecision, int $currentYear): ?self
    {
        if ($lastYear === null) {
            return null;
        }
        if ($closedPrecision !== StationTimeline::RANGE || $closedAt === null || (int) substr($closedAt, 0, 4) !== $lastYear) {
            return self::Year;
        }

        return $lastYear >= $currentYear ? self::Undated : self::UpperBound;
    }
}
