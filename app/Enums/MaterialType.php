<?php

namespace App\Enums;

/**
 * Catégorie d'une série de matériel roulant. `tramway` : motrices et rames de tramway (urbain ou
 * interurbain) ; `engin_service` : engins de l'infrastructure (voitures d'auscultation,
 * chasse-neige, draisines), qui ne transportent ni voyageurs ni marchandises.
 */
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
    case Tramway = 'tramway';
    case EnginService = 'engin_service';
}
