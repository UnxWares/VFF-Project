<?php

/*
|--------------------------------------------------------------------------
| Suivi de la disponibilité et des erreurs (docs/SUIVI-ERREURS.md)
|--------------------------------------------------------------------------
*/
return [
    // Version déployée (étiquette ou court identifiant de commit, posée au déploiement).
    'release' => env('APP_VERSION', 'inconnue'),

    // Jeton de /up/details (en-tête Authorization: Bearer ou paramètre `token`). Vide : la page ne
    // donne que « ok », « degraded » ou « down », sans détail.
    'health_token' => env('HEALTH_TOKEN') ?: null,

    // Demandes par minute et par adresse IP sur /up/details.
    'health_per_minute' => (int) env('HEALTH_PER_MINUTE', 60),

    // Seuils : file de travaux en attente, travaux échoués, espace libre (Mo), âge du dernier calcul
    // de l'ère courante (heures). Au-delà, la réponse reste en 200 mais passe à « degraded ».
    'queue_max_waiting' => (int) env('HEALTH_QUEUE_MAX_WAITING', 1000),
    'failed_jobs_max' => (int) env('HEALTH_FAILED_JOBS_MAX', 50),
    'disk_min_free_mb' => (int) env('HEALTH_DISK_MIN_FREE_MB', 1024),
    'era_max_age_hours' => (int) env('HEALTH_ERA_MAX_AGE_HOURS', 48),

    // Erreurs JavaScript du navigateur (POST /api/client-errors) : journal `client-errors`.
    'client_errors_per_minute' => (int) env('CLIENT_ERRORS_PER_MINUTE', 10),
    'client_errors_max_bytes' => 8192,
    // Part des erreurs que le navigateur envoie (0 à 1), tirée côté navigateur.
    'client_errors_sample_rate' => (float) env('CLIENT_ERRORS_SAMPLE_RATE', 1.0),
];
