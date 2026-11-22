<?php

use App\Providers\AppServiceProvider;
use App\Providers\RealtimeServiceProvider;
use App\Providers\WebhookServiceProvider;
use Nuwave\Lighthouse\Subscriptions\SubscriptionServiceProvider;

return [
    AppServiceProvider::class,
    WebhookServiceProvider::class,
    RealtimeServiceProvider::class,
    // Abonnements GraphQL (type Subscription du schéma), refusés tant que le temps réel est désactivé.
    SubscriptionServiceProvider::class,
];
