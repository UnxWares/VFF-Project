<?php

namespace App\Services\Realtime;

use App\Models\User;
use App\Support\Realtime;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Throwable;

/**
 * Coupe les connexions WebSocket d'un compte dont les droits changent (suspension, changement de rôle,
 * suppression). Un canal privé n'est autorisé qu'à l'abonnement : sans cette coupure, un validateur
 * suspendu ou rétrogradé resterait abonné à `private-review-queue` tant que sa connexion vit.
 *
 * Le navigateur s'identifie auprès de Reverb (`/broadcasting/user-auth`, resources/js/lib/realtime.js)
 * pour que le serveur sache à qui appartient la connexion ; l'API de Reverb
 * `POST /apps/{id}/users/{userId}/terminate_connections` (Pusher::terminateUserConnections) les ferme.
 * Le navigateur se reconnecte et redemande ses canaux : `ReviewQueueChannel` refuse alors le compte.
 * Rien ne part tant que le temps réel est désactivé ; un serveur injoignable est consigné sans bloquer
 * la suspension.
 */
final readonly class RealtimeRevocation
{
    public function __construct(private BroadcastManager $broadcasts) {}

    /** Champs du compte dont le changement retire ou modifie des droits sur les canaux. */
    public const WATCHED_FIELDS = ['role', 'suspended_at'];

    public function terminateConnections(User $account): bool
    {
        if (! Realtime::enabled()) {
            return false;
        }
        try {
            $broadcaster = $this->broadcasts->connection();
            if (! $broadcaster instanceof PusherBroadcaster) {
                return false;
            }
            $broadcaster->getPusher()->terminateUserConnections((string) $account->getKey());

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** À appeler après l'enregistrement d'un compte : coupe si la suspension ou le rôle a changé. */
    public function afterSave(User $account): void
    {
        if ($account->wasChanged(self::WATCHED_FIELDS)) {
            $this->terminateConnections($account);
        }
    }
}
