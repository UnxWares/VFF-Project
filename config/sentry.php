<?php

use App\Support\Monitoring\SentryScrubber;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Suivi des erreurs du serveur (GlitchTip, Bugsink ou tout outil qui parle le protocole Sentry)
|--------------------------------------------------------------------------
|
| Inactif tant que SENTRY_LARAVEL_DSN est vide : rien ne sort du serveur. L'adresse DSN doit
| viser un outil auto-hébergé (docs/SUIVI-ERREURS.md). Aucune donnée personnelle : ni adresse IP,
| ni cookie, ni en-tête, ni corps de requête, ni compte (SentryScrubber retire tout cela
| avant l'envoi, en plus de `send_default_pii` à false). Pas de traces de performance, pas de
| journaux, pas de métriques : uniquement les exceptions, échantillonnées.
*/
return [
    'dsn' => env('SENTRY_LARAVEL_DSN') ?: null,
    'release' => env('SENTRY_RELEASE', env('APP_VERSION')),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    // Part des erreurs envoyées (0 à 1).
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 0.5),
    'traces_sample_rate' => null,
    'profiles_sample_rate' => null,
    'enable_logs' => false,
    'enable_metrics' => false,
    'send_default_pii' => false,
    'before_send' => [SentryScrubber::class, 'scrub'],
    'ignore_exceptions' => [
        AuthenticationException::class,
        ValidationException::class,
        NotFoundHttpException::class,
        MethodNotAllowedHttpException::class,
        ThrottleRequestsException::class,
    ],
    'ignore_transactions' => ['/up', '/up/details'],
    'breadcrumbs' => [
        'logs' => false,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => false,
        'command_info' => false,
        'http_client_requests' => false,
        'notifications' => false,
    ],
    'tracing' => [
        'queue_job_transactions' => false,
        'queue_jobs' => false,
        'sql_queries' => false,
        'views' => false,
        'livewire' => false,
        'http_client_requests' => false,
        'cache' => false,
        'redis_commands' => false,
        'notifications' => false,
        'missing_routes' => false,
        'default_integrations' => false,
    ],
];
