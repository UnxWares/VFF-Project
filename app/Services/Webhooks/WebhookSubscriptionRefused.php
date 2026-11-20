<?php

namespace App\Services\Webhooks;

use RuntimeException;

/** Création d'un abonnement webhook refusée (compte suspendu, nombre maximal atteint), message traduit. */
final class WebhookSubscriptionRefused extends RuntimeException
{
    public static function suspended(): self
    {
        return new self(__('webhooks.errors.suspended'));
    }

    public static function tooMany(int $max): self
    {
        return new self(trans_choice('webhooks.errors.too_many', $max, ['max' => $max]));
    }
}
