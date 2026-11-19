<?php

namespace App\Enums;

/**
 * Ce que la source dit d'une date : jour, mois, année, ou seulement une période
 * (« entre 1966 et 1971 »). Une date moins précise n'est jamais complétée : l'année
 * seule reste une année (event_date = 1er janvier, à lire avec la précision).
 */
enum FactDatePrecision: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';
    case Range = 'range';

    /** Précision acceptée par line_events.date_precision (une période n'y entre pas telle quelle). */
    public function lineEventPrecision(): ?string
    {
        return match ($this) {
            self::Day, self::Month, self::Year => $this->value,
            self::Range => null,
        };
    }
}
