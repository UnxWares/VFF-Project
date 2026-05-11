<?php

namespace App\Enums;

enum MaterialType: string
{
    case LocomotiveElectrique = 'locomotive_electrique';
    case LocomotiveDiesel = 'locomotive_diesel';
    case LocomotiveVapeur = 'locomotive_vapeur';
    case Automotrice = 'automotrice';
    case Autorail = 'autorail';
    case RameTgv = 'rame_tgv';
    case Voiture = 'voiture';
    case Wagon = 'wagon';
}
