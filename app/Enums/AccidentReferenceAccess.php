<?php

namespace App\Enums;

/**
 * Accès à un document cité par le contexte d'un accident (accident_references.access) :
 *
 *   - open : lu en ligne ; seul un document lu peut appuyer un énoncé ;
 *   - paywall : accès payant (RetroNews, presse) : lien seulement, rien n'en est tiré ;
 *   - link_only : document repéré mais non lu (Gallica, INA, archives non numérisées) : à consulter.
 */
enum AccidentReferenceAccess: string
{
    case Open = 'open';
    case Paywall = 'paywall';
    case LinkOnly = 'link_only';
}
