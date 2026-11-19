<?php

/*
 * Serveur MCP de VFF (paquet laravel/mcp, cf. docs/MCP.md). Ce fichier est fusionné avec la
 * configuration du paquet (vendor/laravel/mcp/config/mcp.php) clé par clé de premier niveau :
 * les clés absentes ici (OAuth, clients MCP sortants) gardent leur valeur par défaut, inutilisée
 * par VFF (serveur public en lecture seule, sans authentification ni appel sortant).
 */
return [

    /*
     * Origines navigateur admises sur POST /mcp, en plus de APP_URL et de localhost (protection du
     * paquet contre le « DNS rebinding »). Les clients MCP hors navigateur n'envoient pas d'Origin.
     */
    'allowed_origins' => array_values(array_filter(explode(',', (string) env('MCP_ALLOWED_ORIGINS', '')))),

    'vff' => [
        // Adresse publique des fiches citées dans chaque réponse (lien de la fiche VFF), quel que
        // soit APP_URL (le serveur stdio tourne en local mais renvoie vers le site public).
        'public_url' => rtrim((string) env('VFF_PUBLIC_URL', 'https://vff-project.org'), '/'),

        // Taille maximale d'une réponse d'outil ou de ressource (octets du JSON ou du Markdown) :
        // au-delà, les listes les plus longues sont raccourcies et la réponse le dit (`truncated`).
        'max_response_bytes' => (int) env('MCP_MAX_RESPONSE_BYTES', 48_000),

        // Requêtes par minute et par adresse IP sur POST /mcp : la même limite que l'API publique.
        'rate_limit_per_minute' => (int) env('MCP_RATE_LIMIT', 300),
    ],

];
