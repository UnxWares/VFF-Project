<?php

namespace App\Enums;

/**
 * Événements qu'un abonnement webhook peut suivre. La valeur est le champ `type` de la charge utile
 * et de l'en-tête `VFF-Event` : stable, ne pas renommer.
 */
enum WebhookEvent: string
{
    /** Contribution approuvée et appliquée à une ligne ou à une gare. */
    case ContributionApplied = 'contribution.applied';
    /** L'ère courante (réseau d'aujourd'hui) a été recalculée : des lignes ou des gares ont changé (vff:refresh-current-era). */
    case CurrentEraRefreshed = 'current_era.refreshed';
    /** Nouvelle fiche d'accident publiée, annoncée après sa première vérification (vff:verify-accidents), jamais un alias ni une nature masquée. */
    case AccidentPublished = 'accident.published';
    /** Nouvelle fiche de matériel roulant publiée par un import. */
    case MaterialPublished = 'material.published';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $e): string => $e->value, self::cases());
    }
}
