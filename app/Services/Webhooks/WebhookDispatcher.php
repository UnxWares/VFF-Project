<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Annonce un événement aux abonnements qui le suivent : une livraison par abonnement actif d'un
 * compte non suspendu, confiée à la file d'attente (DeliverWebhook).
 *
 * Charge utile, sobre et sans aucune donnée personnelle (ni membre, ni numéro de contribution) :
 *
 *     {"id": "<uuid>", "type": "contribution.applied", "created_at": "2026-11-19T14:02:11+00:00",
 *      "data": {"resource": "line", "id": "rfn-420000", "url": "https://…/api/v1/lines/rfn-420000"}}
 *
 * L'annonce attend la validation de la transaction en cours (rien n'est annoncé si elle est
 * annulée) et ne la fait jamais échouer : une erreur est signalée (report) sans interrompre
 * l'import ou la décision qui l'a déclenchée.
 */
final readonly class WebhookDispatcher
{
    public function __construct(private WebhookSubscriptions $subscriptions) {}

    /**
     * @param  string  $resource  `line`, `station`, `era`, `accident` ou `material`
     * @param  string|int  $id  slug de la ressource, année pour une ère
     * @param  string  $url  adresse de la ressource dans l'API REST
     * @param  array<string, scalar|null>  $extra  champs de plus dans `data` (ex. `as_of` pour une ère)
     */
    public function dispatch(WebhookEvent $event, string $resource, string|int $id, string $url, array $extra = []): void
    {
        DB::afterCommit(function () use ($event, $resource, $id, $url, $extra): void {
            try {
                $this->deliver($event, [
                    'resource' => $resource,
                    'id' => $id,
                    'url' => $url,
                    ...$extra,
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /** @param  array<string, mixed>  $data */
    private function deliver(WebhookEvent $event, array $data): void
    {
        $subscriptions = WebhookSubscription::query()->deliverable($event)->orderBy('id')->get(['id']);
        if ($subscriptions->isEmpty()) {
            return;
        }

        $eventId = (string) Str::uuid();
        $payload = [
            'id' => $eventId,
            'type' => $event->value,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        foreach ($subscriptions as $subscription) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_subscription_id' => $subscription->id,
                'event_id' => $eventId,
                'event_type' => $event->value,
                'payload' => $payload,
                'status' => WebhookDeliveryStatus::Pending,
            ]);
            $this->subscriptions->pruneLog($subscription->id);
            DeliverWebhook::dispatch($delivery->id);
        }
    }
}
