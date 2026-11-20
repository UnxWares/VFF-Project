<?php

namespace App\Enums;

/**
 * Ce que devient un trajet (gare A → gare B) d'une époque à l'autre (RouteComparison).
 */
enum RouteComparisonOutcome: string
{
    /** Un itinéraire existe aux deux époques. */
    case Both = 'both';

    /** Possible à la première époque, impossible à la seconde (trajet devenu impossible). */
    case Lost = 'lost';

    /** Impossible à la première époque, possible à la seconde. */
    case Gained = 'gained';

    /** Impossible aux deux époques. */
    case Neither = 'neither';

    public static function fromAvailability(bool $earlier, bool $later): self
    {
        return match (true) {
            $earlier && $later => self::Both,
            $earlier => self::Lost,
            $later => self::Gained,
            default => self::Neither,
        };
    }
}
