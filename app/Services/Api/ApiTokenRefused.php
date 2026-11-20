<?php

namespace App\Services\Api;

use RuntimeException;

/** Création d'un jeton d'API refusée : message traduit, prêt à afficher sous le formulaire du profil. */
final class ApiTokenRefused extends RuntimeException
{
    public static function suspended(): self
    {
        return new self(__('api_tokens.errors.suspended'));
    }

    public static function tooMany(int $max): self
    {
        return new self(trans_choice('api_tokens.errors.too_many', $max, ['max' => $max]));
    }
}
