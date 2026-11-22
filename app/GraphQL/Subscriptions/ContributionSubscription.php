<?php

namespace App\GraphQL\Subscriptions;

use App\GraphQL\Support\SessionCsrfCheck;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use App\Support\Realtime;
use GraphQL\Error\Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
 *
 * Chaque abonnement reste dans Valkey un jour (Reverb ne signale pas les départs) et chaque diffusion
 * passe par tous les abonnés du sujet : un même compte, ou une même adresse IP sans compte, ouvre au
 * plus `realtime.subscriptions.max_per_day` abonnements par jour (erreur `too_many_subscriptions`).
 */
abstract class ContributionSubscription extends GraphQLSubscription
{
    public function __construct(private readonly SessionCsrfCheck $csrf) {}

    /** Code de l'erreur renvoyée quand le quota d'abonnements est atteint. */
    public const QUOTA_ERROR = 'too_many_subscriptions';

    final public function can(Subscriber $subscriber): bool
    {
        if (! Realtime::enabled() || ! $this->permits($subscriber)) {
            return false;
        }
        $this->countAgainstQuota($subscriber);

        return true;
    }

    /** Droit d'ouvrir cet abonnement (le temps réel étant activé) : ouvert à tous par défaut. */
    protected function permits(Subscriber $subscriber): bool
    {
        return true;
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

    /**
     * Compte un abonnement de plus pour le compte (ou l'adresse IP sans compte) sur la durée de vie
     * d'un abonné, et refuse au-delà du quota.
     *
     * @throws Error quota atteint (`extensions.code` = too_many_subscriptions)
     */
    private function countAgainstQuota(Subscriber $subscriber): void
    {
        $account = $this->account($subscriber);
        $key = 'graphql-subscriptions:'.($account !== null
            ? 'user:'.$account->id
            : 'ip:'.($subscriber->context->request()?->ip() ?? 'unknown'));
        $max = max(1, (int) config('realtime.subscriptions.max_per_day', 100));
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new Error(
                'Too many subscriptions opened from this account or address today. Reuse an open subscription.',
                extensions: ['code' => self::QUOTA_ERROR, 'retry_after' => RateLimiter::availableIn($key)],
            );
        }
        RateLimiter::hit($key, 86400);
    }

    /** @return array<string, mixed> charge utile de la racine diffusée (vide si la racine est inattendue) */
    protected static function payload(mixed $root): array
    {
        $payload = is_array($root) ? ($root['payload'] ?? null) : null;

        return is_array($payload) ? $payload : [];
    }
}
