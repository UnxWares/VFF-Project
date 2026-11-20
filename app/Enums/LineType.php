<?php

namespace App\Enums;

enum LineType: string
{
    case Passengers = 'passenger';
    case Freight = 'freight';
    case Mixed = 'mixed';
}
