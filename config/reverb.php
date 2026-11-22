<?php

/*
| Serveur temps réel Laravel Reverb (protocole Pusher, auto-hébergé), lancé en production par
| `php artisan reverb:start` derrière nginx (docs/DEPLOIEMENT-NGINX.md, section « Temps réel »).
| Rien ne tourne tant que REALTIME_ENABLED vaut false (config/realtime.php) : l'application ne
| diffuse alors aucun événement.
|
| Deux adresses distinctes :
|   - REVERB_SERVER_HOST / REVERB_SERVER_PORT : où le processus Reverb écoute (127.0.0.1:8080 derrière
|     nginx) ;
|   - REVERB_HOST / REVERB_PORT / REVERB_SCHEME : où l'application publie les événements (API HTTP de
|     Reverb, même machine en général : 127.0.0.1, 8080, http). Le navigateur, lui, passe par nginx
|     (REALTIME_CLIENT_* de config/realtime.php).
*/
return [

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [

        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '127.0.0.1'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            // Plusieurs processus Reverb (plusieurs serveurs) : ils se relaient les messages par la
            // publication de Valkey (connexion REDIS_*, le conteneur cible est Valkey, protocole RESP).
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', '6379'),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', '0'),
                    'timeout' => env('REDIS_TIMEOUT', 60),
                ],
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],

    ],

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'options' => [
                    'host' => env('REVERB_HOST'),
                    'port' => env('REVERB_PORT', 443),
                    'scheme' => env('REVERB_SCHEME', 'https'),
                    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
                ],
                // Noms d'hôte des pages autorisées à ouvrir une connexion (en-tête Origin), séparés par
                // des virgules ; par défaut celui d'APP_URL seulement.
                'allowed_origins' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) (env('REVERB_ALLOWED_ORIGINS') ?: parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST))),
                ))),
                'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_connections' => env('REVERB_APP_MAX_CONNECTIONS'),
                'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
                'max_channels_per_connection' => env('REVERB_APP_MAX_CHANNELS_PER_CONNECTION', 100),
                // Le site ne fait qu'émettre : aucun message d'un navigateur à un autre (`none`).
                'accept_client_events_from' => env('REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'none'),
                'rate_limiting' => [
                    'enabled' => env('REVERB_APP_RATE_LIMITING_ENABLED', true),
                    'max_attempts' => env('REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS', 60),
                    'decay_seconds' => env('REVERB_APP_RATE_LIMIT_DECAY_SECONDS', 60),
                    'terminate_on_limit' => env('REVERB_APP_RATE_LIMIT_TERMINATE', false),
                ],
            ],
        ],

    ],

];
