<?php

/*
| API publique (REST /api/v1, GraphQL /graphql, serveur MCP /mcp) : jetons personnels et quotas.
|
| L'API reste ouverte sans jeton. Un jeton personnel (Sanctum, lecture seule) relève la limite
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

    'tokens' => [
        // Jetons actifs par compte au plus.
        'max_per_account' => (int) env('API_TOKENS_MAX_PER_ACCOUNT', 10),
        // Seule capacité accordée : lecture de l'API publique.
        'abilities' => ['read'],
        // Date de dernière utilisation enregistrée au plus une fois par période (secondes) :
        // pas d'écriture en base à chaque requête.
        'last_used_resolution' => 60,
    ],
];
