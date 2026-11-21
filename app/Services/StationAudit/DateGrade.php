<?php

namespace App\Services\StationAudit;

/** Qualité d'une date de gare (ouverture, fermeture, fin d'un service). */
enum DateGrade: string
{
    /** Date relevée dans une source (Wikipédia, Wikidata, OHM, OSM, correction sourcée). */
    case Sourced = 'sourced';
    /** Date déduite par VFF (événements des lignes, état des tracés, intervalle des ères). */
    case Estimated = 'estimated';
    /** Aucune date. */
    case Unknown = 'unknown';
    /** Sans objet : la gare (ou le service) est encore ouverte. */
    case NotApplicable = 'not_applicable';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $g): string => $g->value, self::cases());
    }

    /**
     * Note d'une date d'après sa source et sa précision : une source « inferred:* », l'intervalle
     * des ères (`vff:eras`) ou une précision « range » valent une estimation.
     */
    public static function of(?string $date, ?string $source, ?string $precision): self
    {
        if ($date === null || $date === '') {
            return self::Unknown;
        }
        if ($precision === 'range' || $source === null || $source === '' || $source === 'vff:eras' || str_starts_with($source, 'inferred:')) {
            return self::Estimated;
        }

        return self::Sourced;
    }
}
