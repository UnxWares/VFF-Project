<?php

namespace App\Enums;

enum StationType: string
{
    case Gare = 'gare';
    case Halte = 'halte';
    case PointArret = 'point_arret';
    case PassageNiveau = 'passage_niveau';
    case Embranchement = 'embranchement';
}
