<?php

namespace App\Enums;

/**
 * Classe de droits d'une photo de la galerie d'Argentan Web Ferroviaire.
 *
 * La galerie affiche la licence CC BY-SA 4.0 en pied de page. Cette licence ne peut
 * couvrir que les œuvres de l'auteur de la galerie : cartes postales anciennes, documents
 * d'archives et photos créditées à d'autres photographes restent aux droits de leurs auteurs.
 */
enum ArgentanPhotoClass: string
{
    /** Photo prise par l'auteur de la galerie : CC BY-SA 4.0. */
    case OwnWork = 'own_work';

    /** Carte postale ancienne (éditeur ou photographe tiers). */
    case Postcard = 'postcard';

    /** Document d'archives (archives départementales, fonds militaires, presse…). */
    case Archive = 'archive';

    /** Photo créditée à un autre photographe (mot-clé, légende). */
    case Credited = 'credited';

    /** Nature non établie : jamais republiée. */
    case Unknown = 'unknown';

    public function republishable(): bool
    {
        return $this === self::OwnWork;
    }

    public function label(): string
    {
        return match ($this) {
            self::OwnWork => 'œuvre propre de l\'auteur (CC BY-SA 4.0)',
            self::Postcard => 'carte postale ancienne (tiers)',
            self::Archive => 'document d\'archives (tiers)',
            self::Credited => 'photo créditée à un autre photographe',
            self::Unknown => 'nature non établie',
        };
    }
}
