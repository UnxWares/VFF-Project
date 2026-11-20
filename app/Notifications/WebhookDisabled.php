<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Pour le membre : un de ses abonnements webhook vient d'être désactivé après `$failures` livraisons
 * échouées d'affilée (WebhookSubscriptions::recordFailure). Sur le site seulement (canal `database`,
 * page /notifications), dans la langue du membre ; le lien mène à la section « Webhooks » du profil,
 * où il peut le réactiver.
 */
class WebhookDisabled extends Notification
{
    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $url,
        public readonly int $failures,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, subscription_id: int, title: string, body: string, reason: null, url: string}
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'kind' => NotificationKind::WebhookDisabled->value,
            'subscription_id' => $this->subscriptionId,
            'title' => __('webhooks.notification.title'),
            'body' => trans_choice('webhooks.notification.body', $this->failures, [
                'url' => $this->url,
                'count' => $this->failures,
            ]),
            'reason' => null,
            'url' => route('profile.show', absolute: false).'#webhooks-title',
        ];
    }
}
