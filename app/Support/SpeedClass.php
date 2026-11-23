<?php

namespace App\Support;

/**
 * Classes de vitesse maximale de la coloration « Vitesse maximale » de la carte /maps (tuiles allégées,
 * liste de ce qui est affiché) : jusqu'à 100 km/h, de 101 à 160, de 161 à 220, au-delà de 220.
 * Mêmes bornes que SPEED_STYLES de `resources/js/lib/railStyle.js`.
 */
final class SpeedClass
{
    /** Borne haute (km/h, comprise) de chaque classe, sauf la dernière. */
    public const UPPER_BOUNDS = [1 => 100, 2 => 160, 3 => 220];

    /** Classe la plus rapide (au-delà de la dernière borne). */
    public const FASTEST = 4;

    /** Clés des classes (liste textuelle de la carte), de la plus lente à la plus rapide. */
    public const KEYS = [1 => 'v100', 2 => 'v160', 3 => 'v220', 4 => 'v220_plus'];

    /** Classe d'une vitesse (km/h), ou null sans vitesse connue. */
    public static function of(?int $kmh): ?int
    {
        if ($kmh === null || $kmh <= 0) {
            return null;
        }
        foreach (self::UPPER_BOUNDS as $class => $upper) {
            if ($kmh <= $upper) {
                return $class;
            }
        }

        return self::FASTEST;
    }

    /**
     * Expression SQL de la classe d'une colonne de vitesse (km/h), NULL sans vitesse ou quand `$when` est faux.
     *
     * @param  string  $column  colonne (ou expression) de vitesse en km/h
     * @param  string  $when  condition SQL supplémentaire (par exemple : portion en service)
     */
    public static function sql(string $column, string $when = 'TRUE'): string
    {
        $branches = '';
        foreach (self::UPPER_BOUNDS as $class => $upper) {
            $branches .= " WHEN {$column} <= {$upper} THEN {$class}";
        }

        return "(CASE WHEN ({$when}) AND {$column} > 0 THEN (CASE{$branches} ELSE ".self::FASTEST.' END) END)::smallint';
    }
}
