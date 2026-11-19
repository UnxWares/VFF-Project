<?php

/*
 * Webhooks de l'API (App\Services\Webhooks) : un membre déclare une adresse HTTPS depuis son profil
 * et VFF y envoie, par la file d'attente, un court message JSON signé à chaque événement choisi.
 *
 * Toutes les valeurs se règlent par variable d'environnement ; les valeurs par défaut sont prudentes.
 */
return [
    // Abonnements au plus par compte.
    'max_per_account' => (int) env('WEBHOOKS_MAX_PER_ACCOUNT', 5),

    // Délais d'un envoi, en secondes : connexion, puis réponse complète. Le corps de la réponse n'est
    // jamais lu (seul le code HTTP compte).
    'connect_timeout' => (float) env('WEBHOOKS_CONNECT_TIMEOUT', 3),
    'timeout' => (float) env('WEBHOOKS_TIMEOUT', 5),

    // Essais d'une livraison (le premier compris) et attente avant chacun des suivants (s) :
    // 1 min, 5 min, 30 min, 2 h. Au-delà, la livraison est tenue pour échouée.
    'attempts' => (int) env('WEBHOOKS_ATTEMPTS', 5),
    'backoff' => [60, 300, 1800, 7200],

    // Livraisons échouées d'affilée (après tous leurs essais) avant la désactivation de l'abonnement,
    // annoncée au membre sur le site.
    'disable_after' => (int) env('WEBHOOKS_DISABLE_AFTER', 5),

    // Livraisons gardées par abonnement pour le journal du profil (les plus anciennes sont effacées).
    'log_size' => (int) env('WEBHOOKS_LOG_SIZE', 50),

    // Ports acceptés dans l'adresse : HTTPS seulement, sur son port standard.
    'allowed_ports' => [443],

    // Écart toléré par les destinataires entre l'horodatage signé et leur horloge (documentation et
    // exemples de vérification), en secondes.
    'tolerance' => 300,

    'user_agent' => 'VFF-Webhooks/1.0 (+https://vff-project.org/api#webhooks)',
];
