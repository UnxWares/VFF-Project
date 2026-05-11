<?php

namespace App\Enums;

enum LineType: string
{
    case Passengers = 'passengers';
    case Freight = 'freight';
    case Mixed = 'mixed';
}
