<?php

namespace App\Enums;

enum TrafficType: string
{
    case Passenger = 'passenger';
    case Freight = 'freight';
    case Mixed = 'mixed';
    case Unknown = 'unknown';
}
