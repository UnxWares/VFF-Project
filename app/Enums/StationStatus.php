<?php

namespace App\Enums;

enum StationStatus: string
{
    case Active = 'active';                       // Service commercial voyageurs
    case Tourist = 'tourist';                     // Sur ligne touristique / patrimoine
    case NoCommercialService = 'no_commercial';   // Sur ligne fret, plus desservie voyageurs
    case Declassified = 'declassified';           // Plus aucune voie active à proximité
}
