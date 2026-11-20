<?php

namespace App\Enums;

/**
 * Répartition dans le temps des trains qui desservent une gare, sur l'horizon
 * d'un flux GTFS (App\Services\StationService\ServicePatternDetector).
 */
enum ServicePattern: string
{
    /** Desservie au moins 4 jours sur 5 de l'horizon. */
    case Daily = 'daily';
    /** Desservie presque chaque semaine, du lundi au vendredi seulement. */
    case Weekdays = 'weekdays';
    /** Desservie presque chaque semaine, le samedi et le dimanche seulement. */
    case Weekend = 'weekend';
    /** Desservie presque chaque semaine, quelques jours seulement. */
    case SomeDays = 'some_days';
    /** Desservie au milieu de l'horizon seulement (une saison). */
    case Seasonal = 'seasonal';
    /** Desservie à partir d'une date avancée de l'horizon (reprise, début de saison). */
    case Starts = 'starts';
    /** Desservie jusqu'à une date avancée de l'horizon (fin de saison, travaux, fermeture). */
    case Ends = 'ends';
    /** Desservie certaines semaines, sans régularité. */
    case Intermittent = 'intermittent';
    /** Desservie trois jours au plus. */
    case Sporadic = 'sporadic';

    /** Desserte de toutes (ou presque toutes) les semaines de l'horizon. */
    public function isRegular(): bool
    {
        return match ($this) {
            self::Daily, self::Weekdays, self::Weekend, self::SomeDays => true,
            default => false,
        };
    }
}
