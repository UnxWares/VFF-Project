<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Abonnement webhook d'un membre (App\Services\Webhooks\WebhookSubscriptions).
 *
 * Le secret de signature est chiffré par la clé de l'application (cast `encrypted`) et caché de
 * toute sérialisation : il n'est montré qu'une fois, à la création, et jamais exporté.
 *
 * @property int $id
 * @property int $user_id
 * @property string $url
 * @property list<string> $events
 * @property string $secret
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property string|null $disabled_reason `failures` (échecs d'affilée)
 * @property Carbon|null $last_delivery_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookSubscription extends Model
{
    /** Motif de désactivation : trop de livraisons échouées d'affilée. */
    public const DISABLED_FAILURES = 'failures';

    protected $fillable = ['url', 'events', 'secret'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'disabled_at' => 'datetime',
            'last_delivery_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->disabled_at === null;
    }

    public function follows(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events, true);
    }

    /**
     * Abonnements actifs qui suivent `$event`, d'un compte qui n'est pas suspendu.
     *
     * @param  Builder<WebhookSubscription>  $query
     */
    #[Scope]
    protected function deliverable(Builder $query, WebhookEvent $event): void
    {
        $query->whereNull('disabled_at')
            ->whereJsonContains('events', $event->value)
            ->whereHas('user', fn (Builder $user) => $user->notSuspended());
    }
}
