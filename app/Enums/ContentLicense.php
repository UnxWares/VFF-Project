<?php

namespace App\Enums;

use App\Models\Media;

/**
 * Licences proposées dans les formulaires de contribution (liste fermée).
 *
 *   - image déposée : seulement une licence libre compatible avec la diffusion de VFF
 *     (forMedia() : domaine public, CC0, CC BY 4.0, CC BY-SA 4.0, Licence Ouverte 2.0) ;
 *   - document cité comme source d'un événement : sa licence telle qu'elle est, y compris
 *     « tous droits réservés » ou « inconnue » (VFF n'en reprend que le fait daté, avec le lien).
 *
 * `label()` est le nom court enregistré dans `media.license`, écrit comme ceux de Wikimedia Commons
 * déjà présents en base (« CC BY-SA 4.0 », « Public domain »).
 */
enum ContentLicense: string
{
    case PublicDomain = 'public_domain';
    case Cc0 = 'cc0-1.0';
    case CcBy4 = 'cc-by-4.0';
    case CcBySa4 = 'cc-by-sa-4.0';
    case Etalab2 = 'etalab-2.0';
    case AllRightsReserved = 'all_rights_reserved';
    case Unknown = 'unknown';

    /** @return list<self> licences acceptées pour une image déposée */
    public static function forMedia(): array
    {
        return array_values(array_filter(self::cases(), fn (self $l): bool => $l->allowsMedia()));
    }

    /** @return list<string> valeurs acceptées pour une image déposée */
    public static function mediaValues(): array
    {
        return array_map(fn (self $l): string => $l->value, self::forMedia());
    }

    public function allowsMedia(): bool
    {
        return match ($this) {
            self::PublicDomain, self::Cc0, self::CcBy4, self::CcBySa4, self::Etalab2 => true,
            self::AllRightsReserved, self::Unknown => false,
        };
    }

    /** Nom court, tel qu'il est enregistré et affiché avec l'image. */
    public function label(): string
    {
        return match ($this) {
            self::PublicDomain => 'Public domain',
            self::Cc0 => 'CC0',
            self::CcBy4 => 'CC BY 4.0',
            self::CcBySa4 => 'CC BY-SA 4.0',
            self::Etalab2 => 'Licence Ouverte 2.0',
            self::AllRightsReserved => 'Tous droits réservés',
            self::Unknown => 'Licence inconnue',
        };
    }

    /** Texte officiel de la licence ; null pour le domaine public et les cas sans licence ouverte. */
    public function url(): ?string
    {
        return match ($this) {
            self::Cc0 => 'https://creativecommons.org/publicdomain/zero/1.0/',
            self::CcBy4 => 'https://creativecommons.org/licenses/by/4.0/',
            self::CcBySa4 => 'https://creativecommons.org/licenses/by-sa/4.0/',
            self::Etalab2 => 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/',
            self::PublicDomain, self::AllRightsReserved, self::Unknown => null,
        };
    }

    /** Valeur de `media.rights_status` (contrainte media_rights_status_check). */
    public function rightsStatus(): string
    {
        return match ($this) {
            self::PublicDomain, self::Cc0 => Media::RIGHTS_PUBLIC_DOMAIN,
            self::CcBy4, self::CcBySa4, self::Etalab2 => Media::RIGHTS_OPEN_LICENSE,
            self::AllRightsReserved, self::Unknown => Media::RIGHTS_UNKNOWN,
        };
    }

    public function shareAlike(): bool
    {
        return $this === self::CcBySa4;
    }

    /** L'auteur doit être cité avec l'image (licences avec attribution). */
    public function attributionRequired(): bool
    {
        return match ($this) {
            self::CcBy4, self::CcBySa4, self::Etalab2 => true,
            default => false,
        };
    }
}
