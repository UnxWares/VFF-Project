<?php

namespace App\Listeners;

use App\Models\User;
use Laravel\Sanctum\Events\TokenAuthenticated;

/**
 * Un appel authentifié par un jeton Sanctum compte comme une visite (users.last_seen_at), comme
 * une page vue connecté sur le site (TrackLastSeen) : un membre qui n'utilise que l'API n'est ni
 * averti ni supprimé pour inactivité. Une seule écriture par jour (User::markSeen). Les appels
 * anonymes ou avec un jeton invalide ne déclenchent pas l'événement.
 */
final class MarkTokenOwnerSeen
{
    public function handle(TokenAuthenticated $event): void
    {
        if ($event->token->tokenable instanceof User) {
            $event->token->tokenable->markSeen();
        }
    }
}
