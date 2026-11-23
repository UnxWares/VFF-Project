<?php

namespace App\Enums;

use App\Models\Media;

/**
 * Licences proposées dans les formulaires de contribution (liste fermée).
 *
 *   - image déposée : seulement une licence libre compatible avec la diffusion de VFF
 *     (forMedia() : domaine public, CC0 1.0, CC BY et CC BY-SA en versions 2.0, 2.5, 3.0 et 4.0,
 *     Licence Ouverte 2.0), toutes permettant l'usage commercial et la modification ;
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
    case CcBy3 = 'cc-by-3.0';
    case CcBy25 = 'cc-by-2.5';
    case CcBy2 = 'cc-by-2.0';
    case CcBySa4 = 'cc-by-sa-4.0';
    case CcBySa3 = 'cc-by-sa-3.0';
    case CcBySa25 = 'cc-by-sa-2.5';
    case CcBySa2 = 'cc-by-sa-2.0';
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
            self::AllRightsReserved, self::Unknown => false,
            default => true,
        };
    }

    /**
     * Famille de la licence, pour le choix en deux temps du formulaire (famille, puis version) :
     * `public_domain`, `cc0`, `cc-by`, `cc-by-sa`, `etalab`, `all_rights_reserved` ou `unknown`.
     */
    public function family(): string
    {
        return match ($this) {
            self::PublicDomain => 'public_domain',
            self::Cc0 => 'cc0',
            self::CcBy4, self::CcBy3, self::CcBy25, self::CcBy2 => 'cc-by',
            self::CcBySa4, self::CcBySa3, self::CcBySa25, self::CcBySa2 => 'cc-by-sa',
            self::Etalab2 => 'etalab',
            self::AllRightsReserved => 'all_rights_reserved',
            self::Unknown => 'unknown',
        };
    }

    /** Version de la licence (« 4.0 »), null pour le domaine public et les cas sans licence ouverte. */
    public function version(): ?string
    {
        return match ($this) {
            self::PublicDomain, self::AllRightsReserved, self::Unknown => null,
            default => (string) preg_replace('/^.*-/', '', $this->value),
        };
    }

    /** Nom court, tel qu'il est enregistré et affiché avec l'image. */
    public function label(): string
    {
        return match ($this) {
            self::PublicDomain => 'Public domain',
            self::Cc0 => 'CC0',
            self::CcBy4, self::CcBy3, self::CcBy25, self::CcBy2 => 'CC BY '.$this->version(),
            self::CcBySa4, self::CcBySa3, self::CcBySa25, self::CcBySa2 => 'CC BY-SA '.$this->version(),
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
            self::CcBy4, self::CcBy3, self::CcBy25, self::CcBy2 => 'https://creativecommons.org/licenses/by/'.$this->version().'/',
            self::CcBySa4, self::CcBySa3, self::CcBySa25, self::CcBySa2 => 'https://creativecommons.org/licenses/by-sa/'.$this->version().'/',
            self::Etalab2 => 'https://www.etalab.gouv.fr/licence-ouverte-open-licence/',
            self::PublicDomain, self::AllRightsReserved, self::Unknown => null,
        };
    }

    /** Valeur de `media.rights_status` (contrainte media_rights_status_check). */
    public function rightsStatus(): string
    {
        return match ($this) {
            self::PublicDomain, self::Cc0 => Media::RIGHTS_PUBLIC_DOMAIN,
            self::AllRightsReserved, self::Unknown => Media::RIGHTS_UNKNOWN,
            default => Media::RIGHTS_OPEN_LICENSE,
        };
    }

    public function shareAlike(): bool
    {
        return $this->family() === 'cc-by-sa';
    }

    /** L'auteur doit être cité avec l'image (licences avec attribution). */
    public function attributionRequired(): bool
    {
        return in_array($this->family(), ['cc-by', 'cc-by-sa', 'etalab'], true);
    }
}
