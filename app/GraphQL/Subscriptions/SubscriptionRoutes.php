<?php

namespace App\GraphQL\Subscriptions;

use App\Http\Middleware\EnsureRealtimeEnabled;
use App\Services\Api\PublicApiRateLimit;
use Illuminate\Contracts\Routing\Registrar;
use Nuwave\Lighthouse\Subscriptions\SubscriptionController;

/**
 * Routes des abonnements GraphQL (config/lighthouse.php, `subscriptions.broadcasters.*.routes`), à la
 * place de celles de Lighthouse :
 *
 *   - `POST /graphql/subscriptions/auth` (autorisation du canal privé d'un abonné) passe par
 *     EnsureRealtimeEnabled (404 tant que le temps réel est désactivé, comme /broadcasting/auth) et
 *     par le limiteur commun de l'API publique (une recherche dans Valkey par appel) ;
 *   - pas de `/graphql/subscriptions/webhook` : Lighthouse n'y vérifie pas la signature du serveur,
 *     n'importe qui pourrait y effacer des abonnés, et Reverb n'envoie pas de webhook.
 */
final class SubscriptionRoutes
{
    public function reverb(Registrar $router): void
    {
        $router->post('graphql/subscriptions/auth', [SubscriptionController::class, 'authorize'])
            ->middleware([EnsureRealtimeEnabled::class, 'api.token', 'throttle:'.PublicApiRateLimit::NAME])
            ->name('lighthouse.subscriptions.auth');
    }

    /** Serveur compatible Pusher auto-hébergé : mêmes routes que pour Reverb. */
    public function pusher(Registrar $router): void
    {
        $this->reverb($router);
    }
}
