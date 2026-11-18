<?php

namespace App\Enums;

enum MediaType: string
{
    case Photo = 'photo';
    case Plan = 'plan';
    case Document = 'document';
    case Map = 'map';
    // Carte postale ancienne (CPA/CPSM) : source majeure d'iconographie ferroviaire.
    case Postcard = 'postcard';
}
