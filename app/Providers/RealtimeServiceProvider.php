<?php

namespace App\Providers;

use App\Listeners\BroadcastContributionChanges;
use App\Models\Contribution;
use Illuminate\Support\ServiceProvider;

/**
 * Temps réel (config/realtime.php) : branche les changements d'état des contributions sur
 * BroadcastContributionChanges, qui ne diffuse rien tant que le temps réel est désactivé.
 */
final class RealtimeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Contribution::created(fn (Contribution $c) => app(BroadcastContributionChanges::class)->created($c));
        Contribution::updated(fn (Contribution $c) => app(BroadcastContributionChanges::class)->updated($c));
    }
}
