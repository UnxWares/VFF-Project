<?php

namespace App\Enums;

/** Rapprochement d'un arrêt GTFS et d'une gare de VFF (App\Services\StationService\StationStopMatcher). */
enum StopMatchMethod: string
{
    /** Même code UIC (7 premiers chiffres, la clé est ignorée). */
    case Uic = 'uic';
    /** Même nom normalisé, ou nom contenu dans l'autre, à proximité. */
    case Name = 'name';
    /** Seule gare de VFF tout près de l'arrêt, noms différents. */
    case Position = 'position';
}
