<?php

namespace App\GraphQL\Subscriptions;

use App\GraphQL\Support\SessionCsrfCheck;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Socle des abonnements GraphQL aux contributions (type Subscription du schéma).
 *
 * La racine diffusée par BroadcastContributionChanges est `['payload' => …, 'author_id' => …]` : le
 * client reçoit la charge utile publique (ContributionEvent), jamais l'auteur. Tout abonnement est
 * refusé tant que le temps réel est désactivé (App\Support\Realtime). Le canal WebSocket privé que
 * Lighthouse renvoie dans `extensions.lighthouse_subscriptions.channel` s'autorise ensuite par
 * `POST /graphql/subscriptions/auth` ; son nom aléatoire n'est connu que de l'abonné.
 */
abstract class ContributionSubscription extends GraphQLSubscription
{
    public function __construct(private readonly SessionCsrfCheck $csrf) {}

    public function can(Subscriber $subscriber): bool
    {
        return Realtime::enabled();
    }

    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return Realtime::enabled();
    }

    /** @param  array<string, mixed>  $args */
    public function resolve(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): mixed
    {
        return is_array($root) ? ($root['payload'] ?? null) : null;
    }

    /**
     * Compte de l'abonné : jeton personnel (AuthenticateApiToken, capacité `read` suffisante), ou
     * session du site avec le même contrôle CSRF que les mutations. Null pour un appel anonyme.
     */
    protected function account(Subscriber $subscriber): ?User
    {
        $request = $subscriber->context->request();
        if ($request === null) {
            return null;
        }
        $tokenUser = $request->attributes->get(AuthenticateApiToken::ACCOUNT);
        if ($tokenUser instanceof User) {
            return $tokenUser;
        }
        if (! $request->hasSession() || ! $this->csrf->passes($request)) {
            return null;
        }
        $sessionUser = Auth::guard('web')->user();

        return $sessionUser instanceof User ? $sessionUser : null;
    }

    /** @return array<string, mixed> charge utile de la racine diffusée (vide si la racine est inattendue) */
    protected static function payload(mixed $root): array
    {
        $payload = is_array($root) ? ($root['payload'] ?? null) : null;

        return is_array($payload) ? $payload : [];
    }
}
