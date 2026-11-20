<?php

namespace App\Enums;

/** État d'une livraison de webhook (journal du profil). */
enum WebhookDeliveryStatus: string
{
    /** En file d'attente, ou entre deux essais. */
    case Pending = 'pending';
    /** Le destinataire a répondu par un code 2xx. */
    case Succeeded = 'succeeded';
    /** Tous les essais ont échoué, ou l'échec ne vaut pas un nouvel essai (adresse refusée, redirection). */
    case Failed = 'failed';
    /** Non envoyée : abonnement désactivé ou supprimé, ou compte suspendu entre-temps. */
    case Skipped = 'skipped';
}
