<?php

namespace App\Providers;

use App\GraphQL\Support\SubscriptionContextSerializer;
use App\Listeners\BroadcastContributionChanges;
use App\Models\Contribution;
use App\Models\User;
use App\Services\Realtime\RealtimeRevocation;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\SerializesContext;

/**
 * Temps réel (config/realtime.php) : branche les changements d'état des contributions sur
 * BroadcastContributionChanges, qui ne diffuse rien tant que le temps réel est désactivé, et garde
 * le contexte des abonnés GraphQL sans cookie, adresse IP ni colonne du compte
 * (SubscriptionContextSerializer). Une suspension, un changement de rôle ou la suppression d'un compte
 * coupe ses connexions WebSocket (RealtimeRevocation).
 */
final class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SerializesContext::class, SubscriptionContextSerializer::class);
    }

    public function boot(): void
    {
        Contribution::created(fn (Contribution $c) => app(BroadcastContributionChanges::class)->created($c));
        Contribution::updated(fn (Contribution $c) => app(BroadcastContributionChanges::class)->updated($c));
        User::saved(fn (User $u) => app(RealtimeRevocation::class)->afterSave($u));
        User::deleted(fn (User $u) => app(RealtimeRevocation::class)->terminateConnections($u));
    }
}
