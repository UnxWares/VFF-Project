<?php

namespace App\Enums;

/**
 * Mode d'une ligne GTFS (routes.txt, route_type), réduit à ce qui compte pour la
 * desserte des gares : train, tramway (dont tram-train) et car. Les autres modes
 * (métro, bateau, téléphérique, funiculaire…) ne desservent pas une gare de VFF.
 *
 * Types de base (0 à 3) et types étendus (Google Transit, 100-117 train,
 * 200-209 car, 700-717 bus, 900-906 tramway).
 */
enum TransitMode: string
{
    case Rail = 'rail';
    case Tram = 'tram';
    case Bus = 'bus';

    public static function fromRouteType(int $type): ?self
    {
        return match (true) {
            $type === 2, $type >= 100 && $type <= 117 => self::Rail,
            $type === 0, $type >= 900 && $type <= 906 => self::Tram,
            $type === 3, $type >= 200 && $type <= 209, $type >= 700 && $type <= 717 => self::Bus,
            default => null,
        };
    }
}
