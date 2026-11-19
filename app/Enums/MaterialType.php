<?php

namespace App\Enums;

/**
 * Catégorie d'une série de matériel roulant. `tramway` : motrices et rames de tramway (urbain ou
 * interurbain) ; `engin_service` : engins de l'infrastructure (voitures d'auscultation,
 * chasse-neige, draisines), qui ne transportent ni voyageurs ni marchandises ; `bimode` : engin qui
 * circule sous caténaire et à l'énergie thermique (Régiolis, AGC bimodes), rangé là seulement quand
 * une source le dit (classe « bimode » de Wikidata), sinon il reste `automotrice` ou `autorail`.
 */
enum MaterialType: string
{
    case LocomotiveElectrique = 'locomotive_electrique';
    case LocomotiveDiesel = 'locomotive_diesel';
    case LocomotiveVapeur = 'locomotive_vapeur';
    case Automotrice = 'automotrice';
    case Bimode = 'bimode';
    case Autorail = 'autorail';
    case RameTgv = 'rame_tgv';
    case Voiture = 'voiture';
    case Wagon = 'wagon';
    case Tramway = 'tramway';
    case EnginService = 'engin_service';
}
