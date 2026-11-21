<?php

/*
| Politique de sécurité du contenu (Content-Security-Policy) du site, posée par SecurityHeaders à
| partir de App\Support\Security\ContentSecurityPolicy.
|
| `enforce` à false : l'en-tête est `Content-Security-Policy-Report-Only`. Le navigateur ne bloque
| rien, il signale chaque écart à /api/csp-report (CspReportController, journal `csp`). Une fois les
| rapports relus et les sources manquantes ajoutées ci-dessous, mettre CSP_ENFORCE=true rend la
| politique bloquante (marche à suivre : docs/SECURITE-CSP.md).
*/
return [
    'enforce' => (bool) env('CSP_ENFORCE', false),

    // Envoi des rapports de violation (report-uri et Reporting-Endpoints).
    'report' => (bool) env('CSP_REPORT', true),

    // Fichier « hot » de Vite (adresse du serveur de développement) ; null : public/hot. Lu seulement
    // dans l'environnement local.
    'hot_file' => env('CSP_VITE_HOT_FILE'),

    // Rapports reçus par minute et par adresse IP au plus (le reste est ignoré, réponse 429).
    'reports_per_minute' => (int) env('CSP_REPORTS_PER_MINUTE', 30),

    /*
    | Origines externes admises, par directive. Tout le reste vient du site lui-même ('self').
    */
    'sources' => [
        // Fond de carte (tuiles raster OpenStreetMap, style MapTiler si une clé est configurée,
        // glyphes OpenFreeMap, cartes historiques de l'IGN), lus par MapLibre en fetch et en image.
        'connect-src' => [
            'https://tile.openstreetmap.org',
            'https://api.maptiler.com',
            'https://tiles.openfreemap.org',
            'https://data.geopf.fr',
        ],
        // Images : tuiles de carte, Wikimedia Commons (galeries, séries de matériel), Gallica.
        'img-src' => [
            'https://tile.openstreetmap.org',
            'https://api.maptiler.com',
            'https://data.geopf.fr',
            'https://upload.wikimedia.org',
            // Vignettes de Commons servies par le nouveau domaine des miniatures (accidents, matériel, histoires).
            'https://thumb.wikimedia.org',
            'https://commons.wikimedia.org',
            'https://gallica.bnf.fr',
        ],
    ],
];
