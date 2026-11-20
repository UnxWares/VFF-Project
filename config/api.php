<?php

/*
| API publique (REST /api/v1, GraphQL /graphql, serveur MCP /mcp) : jetons personnels et quotas.
|
| L'API reste ouverte sans jeton. Un jeton personnel (Sanctum, lecture, et contribution s'il en a
| reçu la capacité) relève la limite
| de débit de son compte ; un administrateur peut accorder le profil « partenaire »
| (php artisan vff:api-partner). Le compteur est commun aux trois interfaces : par adresse IP
| sans jeton, par compte avec un jeton (tous ses jetons partagent le même compteur).
*/
return [
    // Requêtes par minute, selon le profil de l'appelant (App\Enums\ApiAccessProfile).
    'rate_limits' => [
        'anonymous' => (int) env('API_RATE_LIMIT_ANONYMOUS', 300),
        'member' => (int) env('API_RATE_LIMIT_MEMBER', 1200),
        'partner' => (int) env('API_RATE_LIMIT_PARTNER', 6000),
    ],

    // Règle des droits des tracés (App\Services\TraceExport\LineTraceExport) : la géométrie d'un tronçon
    // n'est redistribuée par l'API (REST, GraphQL, GTFS, itinéraires) que si sa source a une licence
    // ouverte vérifiée dans le registre des droits. false : retour arrière, toutes les géométries
    // sortent de nouveau. Les tuiles vectorielles de la carte (affichage) ne sont pas concernées.
    'withhold_unlicensed_geometry' => (bool) env('API_WITHHOLD_UNLICENSED_GEOMETRY', true),

    'tokens' => [
        // Jetons actifs par compte au plus.
        'max_per_account' => (int) env('API_TOKENS_MAX_PER_ACCOUNT', 10),
        // Durée de validité d'un jeton (jours), depuis sa création ou son dernier renouvellement ;
        // rappel par courriel ce nombre de jours avant l'échéance (vff:remind-expiring-api-tokens).
        'lifetime_days' => (int) env('API_TOKENS_LIFETIME_DAYS', 365),
        'reminder_days' => (int) env('API_TOKENS_REMINDER_DAYS', 30),
        // Capacité de tout jeton : lecture de l'API publique.
        'abilities' => ['read'],
        // Capacités facultatives, cochées par le membre à la création du jeton (décochées par
        // défaut) : `contribute` ouvre les mutations GraphQL de contribution et de validation
        // (submitContribution, validateContribution), avec les droits du compte sur le site.
        'optional_abilities' => ['contribute'],
        // Date de dernière utilisation enregistrée au plus une fois par période (secondes) :
        // pas d'écriture en base à chaque requête.
        'last_used_resolution' => 60,
    ],
];
