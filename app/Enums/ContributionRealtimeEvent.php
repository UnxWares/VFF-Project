<?php

namespace App\Enums;

/**
 * Événements de contribution diffusés en temps réel (App\Events\ContributionChanged). La valeur est le
 * nom de l'événement reçu par Laravel Echo (`.contribution.applied`) et le champ `event` de la charge
 * utile : stable, ne pas renommer. `contribution.applied` porte le même nom que le webhook.
 */
enum ContributionRealtimeEvent: string
{
    /** Contribution proposée, en attente d'une première approbation. */
    case Submitted = 'contribution.submitted';
    /** Première approbation reçue, il en manque encore une. */
    case Approved = 'contribution.approved';
    /** Contribution publiée : appliquée à une ligne, une gare ou une série. */
    case Applied = 'contribution.applied';
    /** Contribution refusée par un validateur. */
    case Rejected = 'contribution.rejected';

    /** Événement qui correspond au nouvel état d'une contribution, ou null (retour en attente). */
    public static function forStatus(ContributionStatus $status): ?self
    {
        return match ($status) {
            ContributionStatus::Pending => null,
            ContributionStatus::Approved => self::Approved,
            ContributionStatus::Applied => self::Applied,
            ContributionStatus::Rejected => self::Rejected,
        };
    }

    /** Diffusé aussi sur le canal public `contributions` : seule une publication est publique. */
    public function isPublic(): bool
    {
        return $this === self::Applied;
    }
}
