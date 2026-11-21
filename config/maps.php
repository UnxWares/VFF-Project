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
    ],
];
