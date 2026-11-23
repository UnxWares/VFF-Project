<?php

/*
| Politique de sécurité du contenu (Content-Security-Policy) du site, posée par SecurityHeaders à
| partir de App\Support\Security\ContentSecurityPolicy.
|
| `enforce` à true (valeur de production, décision du 22/11/2026) : l'en-tête est `Content-Security-Policy`
| et le navigateur refuse ce que la politique n'autorise pas. À false (défaut, développement) : en-tête
| `Content-Security-Policy-Report-Only`, le navigateur ne bloque rien. Dans les deux cas il signale chaque écart
| à /api/csp-report (CspReportController, journal `csp`). Sources manquantes : les ajouter ci-dessous
| (marche à suivre : docs/SECURITE-CSP.md).
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
            // Photos de rue Panoramax des fiches de gare (vff:import-panoramax-media) : instances IGN et
            // OpenStreetMap France, et le stockage de l'instance IGN.
            'https://panoramax.ign.fr',
            'https://panoramax-storage-public-fast.s3.gra.perf.cloud.ovh.net',
            'https://panoramax.openstreetmap.fr',
            // Images de fonds ouverts (vff:import-open-data-media, agent medias-3) affichées en lien direct par le navigateur : École des
            // ponts et chaussées et Numistral (IIIF), Getty (IIIF), archives de Saône-et-Loire.
            'https://heritage.ecoledesponts.fr',
            'https://numistral.bnf.fr',
            'https://media.getty.edu',
            'https://saone-et-loire71.fr',
            // Rosalis (Toulouse) et Pireneas (Pau), images IIIF de marque blanche Gallica (agent medias-4).
            'https://rosalis.bibliotheque.toulouse.fr',
            'https://www.pireneas.fr',
            // Cartes postales et autochromes (agent medias-2) : Archives des Hauts-de-Seine (Albert-Kahn, 9 Fi,
            // Suresnes), Évry-Courcouronnes, Sèvres, musée Gadagne (Grand Lyon).
            'https://opendata.hauts-de-seine.fr',
            'https://data.evrycourcouronnes.fr',
            'https://data.sevres.fr',
            'https://sevres-seineouest.opendatasoft.com',
            'https://download.data.grandlyon.com',
        ],
    ],
];
