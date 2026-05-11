<?php

return [
    'meta_title' => "Accès à l'API",
    'meta_description' => 'Documentation API publique de VFF (à venir).',

    'hero' => [
        'eyebrow' => 'API publique',
        'title' => "Construisez vos propres outils sur nos données",
        'subtitle' => "Une API REST + GeoJSON ouverte, documentée, gratuite — pour bâtir applications, visualisations et recherches autour du rail français.",
        'status' => 'Phase 6 — Spécifications en cours',
    ],

    'endpoints' => [
        'title' => 'Endpoints prévus',
        'lines' => 'Liste et détails des lignes — par époque, statut, région.',
        'segments' => "Tronçons en GeoJSON ou MVT — pour intégrer dans une carte tierce.",
        'stations' => 'Gares — actuelles et historiques, avec dates ouverture/fermeture.',
        'materials' => 'Matériel roulant — fiches, périodes d\'exploitation, lignes parcourues.',
        'events' => 'Événements historiques — inaugurations, fermetures, démantèlements.',
        'media' => 'Médias — photos d\'archive géolocalisées et datées.',
    ],

    'format' => [
        'title' => 'Formats supportés',
        'json' => 'JSON (REST classique)',
        'geojson' => 'GeoJSON (couches cartographiques)',
        'mvt' => 'MVT — Mapbox Vector Tiles (pour MapLibre / Leaflet)',
    ],

    'example' => [
        'title' => 'Exemple anticipé',
        'description' => 'Récupérer toutes les lignes en service en 2000 (pour avoir une idée du format) :',
    ],

    'cta' => [
        'title' => 'Tenez-vous au courant',
        'text' => "L'API arrive en phase 6 de la roadmap. Suivez l'avancée sur Discord ou GitHub.",
        'discord' => 'Discord',
        'github' => 'Voir le code',
    ],
];
