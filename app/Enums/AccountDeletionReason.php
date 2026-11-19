<?php

namespace App\Enums;

/** Origine d'une suppression de compte, consignée dans le journal d'audit. */
enum AccountDeletionReason: string
{
    /** Demande du titulaire depuis son profil (mot de passe confirmé). */
    case Owner = 'owner';
    /** Compte inutilisé pendant 3 ans, un mois après l'avertissement par courriel. */
    case Inactivity = 'inactivity';
}
