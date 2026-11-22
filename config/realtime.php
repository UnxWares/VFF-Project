<?php

/*
| Suivi des contributions en temps réel (App\Support\Realtime, docs/API.md, section « Temps réel »).
|
| Désactivé tant que la production n'a pas de serveur temps réel : aucun événement diffusé, aucun
| abonnement GraphQL accepté, aucune connexion WebSocket ouverte par le site. Pour l'activer :
| REALTIME_ENABLED=true, BROADCAST_CONNECTION=reverb (ou `pusher` vers un serveur compatible
| auto-hébergé), les clés REVERB_APP_* et l'adresse publique ci-dessous.
*/
return [
    'enabled' => (bool) env('REALTIME_ENABLED', false),

    /*
    | Adresse publique du serveur, celle qu'ouvre le navigateur (Laravel Echo) : en production, le
    | site lui-même derrière nginx (wss://vff-project.org/app/…). Par défaut, l'hôte d'APP_URL en
    | HTTPS sur le port 443.
    */
    'client' => [
        'key' => env('REVERB_APP_KEY') ?: env('PUSHER_APP_KEY'),
        'host' => env('REALTIME_CLIENT_HOST') ?: parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),
        'port' => (int) env('REALTIME_CLIENT_PORT', 443),
        'scheme' => env('REALTIME_CLIENT_SCHEME', 'https'),
    ],

    /*
    | Abonnements GraphQL : chacun reste un jour dans Valkey et chaque diffusion passe par tous les
    | abonnés du sujet. Un compte (ou une adresse IP sans compte) en ouvre au plus ce nombre par jour
    | (App\GraphQL\Subscriptions\ContributionSubscription).
    */
    'subscriptions' => [
        'max_per_day' => (int) env('REALTIME_SUBSCRIPTIONS_PER_DAY', 100),
    ],
];
