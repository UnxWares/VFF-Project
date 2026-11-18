<?php

namespace App\Enums;

enum LineEventType: string
{
    // Trafic : `opening` / `closure` / `reopening` = TOUT trafic ; les variantes
    // _passenger / _freight ne concernent qu'un service (ex. fermeture aux
    // voyageurs d'une ligne qui reste ouverte au fret → fret seul).
    case Opening = 'opening';
    case OpeningPassenger = 'opening_passenger';
    case OpeningFreight = 'opening_freight';
    case ClosurePassenger = 'closure_passenger';
    case ReopeningPassenger = 'reopening_passenger';
    case ReopeningFreight = 'reopening_freight';
    case Electrification = 'electrification';
    case Closure = 'closure';
    case ClosureFreight = 'closure_freight';
    case Dismantling = 'dismantling';
    // Reconversion de l'emprise (voie verte, route…) : voie déposée, cf. line_events.reuse.
    case Conversion = 'conversion';
    // Exploitation associative (train touristique, vélorail, musée), cf. line_events.heritage_kind.
    case HeritageStart = 'heritage_start';
    case HeritageEnd = 'heritage_end';
    case Reopening = 'reopening';
    case Modernization = 'modernization';
    case Accident = 'accident';
    case Concession = 'concession';
    case ConcessionEnd = 'concession_end';
    case Declassification = 'declassification';
    case Other = 'other';
}
