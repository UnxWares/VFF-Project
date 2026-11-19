<?php

namespace App\Services\Webhooks;

use App\Enums\AuditAction;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Notifications\WebhookDisabled;
use App\Services\AuditLog;
use App\Services\Notifications\SiteNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Abonnements webhook d'un compte : création (adresse vérifiée, secret montré une fois),
 * suppression, réactivation, suivi des échecs et liste du profil.
 *
 * Le nombre d'abonnements est limité par compte (config/webhooks.php `max_per_account`), revérifié
 * sous verrou du compte. Un compte suspendu ne crée ni ne réactive rien, et ses abonnements ne
 * reçoivent rien (WebhookSubscription::deliverable, DeliverWebhook). Création et suppression
 * passent au journal d'audit avec le seul numéro de l'abonnement ; une désactivation reste notée
 * dans l'abonnement (disabled_at) et annoncée au membre.
 */
final readonly class WebhookSubscriptions
{
    /** Livraisons montrées par abonnement dans le profil. */
    public const PROFILE_LOG = 10;

    public function __construct(
        private WebhookUrlGuard $guard,
        private AuditLog $audit,
        private SiteNotifier $notifier,
    ) {}

    public static function maxPerAccount(): int
    {
        return max(1, (int) config('webhooks.max_per_account', 5));
    }

    public static function disableAfter(): int
    {
        return max(1, (int) config('webhooks.disable_after', 5));
    }

    /**
     * Crée l'abonnement, avec un nouveau secret de signature (en clair dans le modèle rendu, à
     * montrer une seule fois).
     *
     * @param  list<WebhookEvent>  $events
     *
     * @throws WebhookSubscriptionRefused
     * @throws WebhookUrlRefused
     */
    public function create(User $user, string $url, array $events): WebhookSubscription
    {
        // Adresse vérifiée (et résolue) hors transaction : le DNS peut prendre un moment.
        $target = $this->guard->target($url);

        return DB::transaction(function () use ($user, $target, $events): WebhookSubscription {
            // Suspension relue sur la ligne verrouillée : une suspension décidée pendant la requête compte.
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->isSuspended()) {
                throw WebhookSubscriptionRefused::suspended();
            }
            if ($user->webhookSubscriptions()->count() >= self::maxPerAccount()) {
                throw WebhookSubscriptionRefused::tooMany(self::maxPerAccount());
            }

            $values = array_values(array_unique(array_map(fn (WebhookEvent $e): string => $e->value, $events)));
            $subscription = $user->webhookSubscriptions()->create([
                'url' => $target->url,
                'events' => $values,
                'secret' => WebhookSignature::newSecret(),
            ]);
            $this->audit->record(AuditAction::WebhookCreated, $user, context: ['subscription_id' => $subscription->id]);

            return $subscription;
        });
    }

    /** Supprime un abonnement du compte (et son journal) ; faux s'il n'existe pas ou appartient à un autre compte. */
    public function delete(User $user, int $subscriptionId): bool
    {
        return DB::transaction(function () use ($user, $subscriptionId): bool {
            $deleted = $user->webhookSubscriptions()->whereKey($subscriptionId)->delete() > 0;
            if ($deleted) {
                $this->audit->record(AuditAction::WebhookDeleted, $user, context: ['subscription_id' => $subscriptionId]);
            }

            return $deleted;
        });
    }

    /** Réactive un abonnement désactivé (compteur d'échecs remis à zéro) ; faux s'il n'est pas du compte. */
    public function enable(User $user, int $subscriptionId): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return $user->webhookSubscriptions()->whereKey($subscriptionId)
            ->update(['disabled_at' => null, 'disabled_reason' => null, 'consecutive_failures' => 0, 'updated_at' => now()]) > 0;
    }

    public function recordSuccess(WebhookSubscription $subscription): void
    {
        WebhookSubscription::query()->whereKey($subscription->id)
            ->update(['consecutive_failures' => 0, 'last_delivery_at' => now()]);
    }

    /**
     * Livraison échouée : une de plus d'affilée. Au seuil (`disable_after`), l'abonnement est
     * désactivé, une seule fois, et le membre en est averti sur le site.
     */
    public function recordFailure(WebhookSubscription $subscription): void
    {
        $disabled = DB::transaction(function () use ($subscription): ?WebhookSubscription {
            $locked = WebhookSubscription::query()->with('user')->whereKey($subscription->id)->lockForUpdate()->first();
            if ($locked === null) {
                return null;
            }
            $locked->consecutive_failures++;
            $locked->last_delivery_at = now();
            $reached = $locked->isActive() && $locked->consecutive_failures >= self::disableAfter();
            if ($reached) {
                $locked->disabled_at = now();
                $locked->disabled_reason = WebhookSubscription::DISABLED_FAILURES;
            }
            $locked->save();
            if (! $reached) {
                return null;
            }

            return $locked;
        });

        if ($disabled !== null) {
            $this->notifier->send($disabled->user, new WebhookDisabled($disabled->id, $disabled->url, $disabled->consecutive_failures));
        }
    }

    /**
     * Élague le journal de l'abonnement : garde les `log_size` livraisons terminées les plus
     * récentes. Une livraison en attente n'est jamais supprimée, sinon un import massif (des
     * centaines de fiches créées d'affilée) effacerait des événements avant leur envoi.
     */
    public function pruneLog(int $subscriptionId): void
    {
        $keep = max(1, (int) config('webhooks.log_size', 50));
        $finished = fn () => WebhookDelivery::query()->where('webhook_subscription_id', $subscriptionId)
            ->where('status', '!=', WebhookDeliveryStatus::Pending->value);
        $oldestKept = $finished()->orderByDesc('id')->skip($keep - 1)->value('id');
        if ($oldestKept !== null) {
            $finished()->where('id', '<', $oldestKept)->delete();
        }
    }

    /**
     * Section « Webhooks » du profil : abonnements (sans secret) et leurs dernières livraisons,
     * événements possibles, nombre maximal, et le secret de l'abonnement tout juste créé (une fois).
     *
     * @return array<string, mixed>
     */
    public function forProfile(User $user, mixed $justCreated = null): array
    {
        $subscriptions = $user->webhookSubscriptions()->orderByDesc('id')->get();
        $logs = $this->recentDeliveries($subscriptions->pluck('id')->all(), self::PROFILE_LOG);

        $list = $subscriptions->map(fn (WebhookSubscription $s): array => [
            'id' => $s->id,
            'url' => $s->url,
            'events' => $s->events,
            'active' => $s->isActive(),
            'disabled_at' => $s->disabled_at?->toIso8601String(),
            'disabled_reason' => $s->disabled_reason,
            'consecutive_failures' => $s->consecutive_failures,
            'created_at' => $s->created_at?->toIso8601String(),
            'deliveries' => $logs[$s->id] ?? [],
        ])->values()->all();

        $new = is_array($justCreated) && is_int($justCreated['id'] ?? null) && is_string($justCreated['secret'] ?? null)
            && in_array($justCreated['id'], array_column($list, 'id'), true)
            ? ['id' => $justCreated['id'], 'secret' => $justCreated['secret']]
            : null;

        return [
            'subscriptions' => $list,
            'events' => WebhookEvent::values(),
            'max' => self::maxPerAccount(),
            'disable_after' => self::disableAfter(),
            'attempts' => max(1, (int) config('webhooks.attempts', 5)),
            'can_create' => ! $user->isSuspended() && count($list) < self::maxPerAccount(),
            'suspended' => $user->isSuspended(),
            'new_secret' => $new,
        ];
    }

    /**
     * Abonnements du compte pour l'export de ses données : adresse, événements, état et journal,
     * jamais le secret.
     *
     * @return list<array<string, mixed>>
     */
    public function forExport(User $user): array
    {
        $subscriptions = $user->webhookSubscriptions()->orderBy('id')->get();
        $logs = $this->recentDeliveries($subscriptions->pluck('id')->all(), max(1, (int) config('webhooks.log_size', 50)));

        return $subscriptions->map(fn (WebhookSubscription $s): array => [
            'url' => $s->url,
            'events' => $s->events,
            'created_at' => $s->created_at?->toIso8601String(),
            'disabled_at' => $s->disabled_at?->toIso8601String(),
            'disabled_reason' => $s->disabled_reason,
            'consecutive_failures' => $s->consecutive_failures,
            'last_delivery_at' => $s->last_delivery_at?->toIso8601String(),
            'deliveries' => $logs[$s->id] ?? [],
        ])->values()->all();
    }

    /**
     * Dernières livraisons de chaque abonnement, de la plus récente à la plus ancienne.
     *
     * @param  list<int>  $subscriptionIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function recentDeliveries(array $subscriptionIds, int $limit): array
    {
        if ($subscriptionIds === []) {
            return [];
        }

        $rows = WebhookDelivery::query()
            ->fromSub(
                WebhookDelivery::query()->select('*')
                    ->selectRaw('row_number() over (partition by webhook_subscription_id order by id desc) as rank')
                    ->whereIn('webhook_subscription_id', $subscriptionIds),
                'webhook_deliveries',
            )
            ->where('rank', '<=', $limit)
            ->orderByDesc('id')
            ->get();

        $logs = [];
        foreach ($rows as $d) {
            $logs[$d->webhook_subscription_id][] = [
                'id' => $d->id,
                'event_id' => $d->event_id,
                'event_type' => $d->event_type,
                'status' => $d->status->value,
                'attempts' => $d->attempts,
                'response_status' => $d->response_status,
                'error' => $d->error,
                'created_at' => $d->created_at?->toIso8601String(),
                'last_attempt_at' => $d->last_attempt_at?->toIso8601String(),
            ];
        }

        return $logs;
    }
}
