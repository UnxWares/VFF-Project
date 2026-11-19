<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Livraison d'un événement à un abonnement : une ligne du journal du profil. `payload` est la
 * charge utile envoyée (sans aucune donnée personnelle) ; `error` un code court (`timeout`,
 * `connection_failed`, `http_status`, `redirect`, `address_refused`, `dns_failed`).
 *
 * @property int $id
 * @property int $webhook_subscription_id
 * @property string $event_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property WebhookDeliveryStatus $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $error
 * @property int|null $duration_ms
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'last_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Charge utile dans l'ordre documenté (`id`, `type`, `created_at`, `data` ; dans `data` :
     * `resource`, `id`, `url`, puis les autres champs). PostgreSQL range les clés d'un jsonb à sa
     * façon : l'ordre est rétabli ici.
     *
     * @return array<string, mixed>
     */
    public function orderedPayload(): array
    {
        $payload = $this->payload;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $first = array_intersect_key(array_flip(['resource', 'id', 'url']), $data);

        return [
            'id' => $payload['id'] ?? null,
            'type' => $payload['type'] ?? null,
            'created_at' => $payload['created_at'] ?? null,
            'data' => array_replace($first, $data),
        ];
    }

    /** Corps JSON envoyé, celui que couvre la signature. */
    public function body(): string
    {
        return (string) json_encode($this->orderedPayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return BelongsTo<WebhookSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
