<?php

namespace App\Enums;

enum LineEventType: string
{
    case Opening = 'opening';
    case Electrification = 'electrification';
    case Closure = 'closure';
    case Dismantling = 'dismantling';
    case Reopening = 'reopening';
    case Modernization = 'modernization';
    case Accident = 'accident';
}
