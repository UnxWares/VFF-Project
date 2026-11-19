<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookAttempt;
use App\Services\Webhooks\WebhookSender;
use App\Services\Webhooks\WebhookSubscriptions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Livraison d'un événement à un abonnement, par la file d'attente (QUEUE_CONNECTION, un
 * `queue:work` en production).
 *
 * Chaque passage est un essai (WebhookSender). Un échec qui vaut d'être retenté remet la livraison
 * dans la file, après l'attente de config/webhooks.php `backoff` (1 min, 5 min, 30 min, 2 h) ; au
 * bout de `attempts` essais, ou après un échec définitif, la livraison est échouée et compte pour
 * la désactivation de l'abonnement (WebhookSubscriptions::recordFailure). Abonnement désactivé ou
 * supprimé, compte suspendu entre-temps : rien n'est envoyé.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    /** Durée maximale d'un passage (s), bien au-delà des délais de l'envoi. */
    public int $timeout = 30;

    public function __construct(public readonly int $deliveryId) {}

    /** Essais permis par la file : ceux de config/webhooks.php. */
    public function tries(): int
    {
        return self::maxAttempts();
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) config('webhooks.attempts', 5));
    }

    /** Attente avant l'essai qui suit l'essai numéro `$attempt` (s). */
    public static function delayAfter(int $attempt): int
    {
        $backoff = array_values(array_map('intval', (array) config('webhooks.backoff', [60])));
        if ($backoff === []) {
            return 60;
        }

        return $backoff[min($attempt, count($backoff)) - 1];
    }

    public function handle(WebhookSender $sender, WebhookSubscriptions $subscriptions): void
    {
        $delivery = WebhookDelivery::query()->with('subscription.user')->find($this->deliveryId);
        if ($delivery === null || $delivery->status !== WebhookDeliveryStatus::Pending) {
            return;
        }

        $subscription = $delivery->subscription;
        if ($subscription === null || ! $subscription->isActive() || $subscription->user === null || $subscription->user->isSuspended()) {
            $delivery->update(['status' => WebhookDeliveryStatus::Skipped]);

            return;
        }

        $attempt = $sender->send($subscription, $delivery);
        $number = $delivery->attempts + 1;
        $delivery->fill([
            'attempts' => $number,
            'response_status' => $attempt->responseStatus,
            'error' => $attempt->error,
            'duration_ms' => $attempt->durationMs,
            'last_attempt_at' => now(),
        ]);

        if ($attempt->succeeded) {
            $delivery->fill(['status' => WebhookDeliveryStatus::Succeeded, 'delivered_at' => now()])->save();
            $subscriptions->recordSuccess($subscription);

            return;
        }

        if ($attempt->retryable && $number < self::maxAttempts()) {
            $delivery->save();
            $this->release(self::delayAfter($number));

            return;
        }

        $delivery->fill(['status' => WebhookDeliveryStatus::Failed])->save();
        $subscriptions->recordFailure($subscription);
    }

    /** Erreur imprévue à chaque essai de la file : la livraison est tenue pour échouée. */
    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->with('subscription')->find($this->deliveryId);
        if ($delivery === null || $delivery->status !== WebhookDeliveryStatus::Pending) {
            return;
        }
        $delivery->update(['status' => WebhookDeliveryStatus::Failed, 'error' => $delivery->error ?? WebhookAttempt::INTERNAL]);
        if ($delivery->subscription !== null) {
            app(WebhookSubscriptions::class)->recordFailure($delivery->subscription);
        }
    }
}
