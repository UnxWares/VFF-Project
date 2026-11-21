<?php

/*
| Carte du réseau (/maps).
*/
return [
    // Vue ferroviaire : plan schématique d'une époque (App\Services\Schematic), choix « Vue ferroviaire » de la
    // carte et point d'accès GET /api/v1/eras/{year}/schematic.geojson. false : le choix n'est pas proposé et
    // le point d'accès répond 404.
    'schematic' => (bool) env('VFF_SCHEMATIC_MAP', true),

    // Fond de carte libre auto-hébergé (App\Support\Basemap\BasemapArchive) : archive PMTiles des données
    // OpenStreetMap au schéma Protomaps (ODbL, « © OpenStreetMap »), préparée par scripts/basemap-pmtiles.sh
    // dans `directory` et servie par VFF à /basemap/{archive} (lecture par plages d'octets). Marche à suivre :
    // docs/FOND-DE-CARTE.md.
    'basemap' => [
        // Nom du fichier (lettres minuscules, chiffres, tirets, suffixe .pmtiles) ; la date des données dans le
        // nom change l'adresse à chaque nouvelle archive. Vide ou fichier absent : aucun fond libre.
        'archive' => env('VFF_BASEMAP_ARCHIVE', 'france-20261120.pmtiles'),
        'directory' => env('VFF_BASEMAP_DIRECTORY', storage_path('app/data/pmtiles')),
        // Fond libre aussi en ligne, à la place des tuiles OpenStreetMap de la carte /maps. false : il sert
        // seulement hors ligne (pages relues sans réseau), sur les zones déjà vues.
        'online' => (bool) env('VFF_BASEMAP_ONLINE', false),
        // Durée de cache HTTP de l'archive (s) : son adresse change avec ses données.
        'max_age' => (int) env('VFF_BASEMAP_MAX_AGE', 2_592_000),
        // Garde-fous de BasemapArchiveController (mesures du 21/11/2026 dans docs/FOND-DE-CARTE.md) : une
        // requête sans plage d'octets, à plusieurs plages ou à une plage plus grande que `max_range_bytes`
        // reçoit 416 (plus grosse tuile de l'archive France : 388 105 octets, plus gros répertoire : 11 088).
        'max_range_bytes' => (int) env('VFF_BASEMAP_MAX_RANGE_BYTES', 1_048_576),
        // Par adresse IP : requêtes par minute et octets par heure (une carte /maps où l'on zoome et se
        // déplace sans arrêt : 57 requêtes en 31 s, 8 par seconde au plus, 2,2 Mo en 55 s). Au-delà : 429.
        'requests_per_minute' => (int) env('VFF_BASEMAP_REQUESTS_PER_MINUTE', 600),
        'bytes_per_hour' => (int) env('VFF_BASEMAP_BYTES_PER_HOUR', 536_870_912),
        // Production derrière nginx : préfixe de la location interne qui sert l'archive (ex. `/_basemap/`).
        // VFF vérifie la requête puis répond X-Accel-Redirect ; vide : PHP envoie les octets lui-même.
        'accel_redirect' => env('VFF_BASEMAP_ACCEL_REDIRECT', ''),
    ],
];
