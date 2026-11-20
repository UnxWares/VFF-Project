<?php

namespace App\Enums;

/**
 * Ce que les sources disent de la date d'un accident. Une date moins précise n'est jamais
 * complétée : l'année seule est enregistrée au 1er janvier et se lit avec cette précision.
 */
enum AccidentDatePrecision: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';

    /**
     * Précision d'une date Wikidata (wikibase:timePrecision) : 11 jour, 10 mois, 9 année. Une
     * précision plus faible (décennie, siècle) ne date pas un accident : null.
     */
    public static function fromWikidata(int $precision): ?self
    {
        return match (true) {
            $precision >= 11 => self::Day,
            $precision === 10 => self::Month,
            $precision === 9 => self::Year,
            default => null,
        };
    }

    /** Rang pour comparer deux précisions (plus grand = plus précis). */
    public function rank(): int
    {
        return match ($this) {
            self::Day => 3,
            self::Month => 2,
            self::Year => 1,
        };
    }
}
