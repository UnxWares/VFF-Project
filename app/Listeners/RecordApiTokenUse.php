<?php

namespace App\Listeners;

use Laravel\Sanctum\Events\TokenAuthenticated;

/**
 * Date de dernière utilisation d'un jeton d'API (affichée dans le profil et exportée avec les
 * données du compte). Écrite au plus une fois par minute (config api.tokens.last_used_resolution)
 * plutôt qu'à chaque requête : le suivi intégré de Sanctum est coupé (config/sanctum.php).
 */
final class RecordApiTokenUse
{
    public function handle(TokenAuthenticated $event): void
    {
        $token = $event->token;
        $resolution = max(1, (int) config('api.tokens.last_used_resolution', 60));

        if ($token->last_used_at === null || $token->last_used_at->lte(now()->subSeconds($resolution))) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }
}
