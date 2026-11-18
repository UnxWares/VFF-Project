<?php

return [
    'meta_title' => "Accès à l'API",
    'meta_description' => 'Documentation API publique de VFF (à venir).',

    'hero' => [
        'eyebrow' => 'API publique',
        'title' => 'Construisez vos propres outils sur nos données',
        'subtitle' => 'Une API REST + GeoJSON ouverte, documentée, gratuite, pour bâtir applications, visualisations et recherches autour du rail français.',
        'status' => 'Phase 6 - Spécifications en cours',
    ],

    'endpoints' => [
        'title' => 'Endpoints prévus',
        'lines' => 'Liste et détails des lignes, par époque, statut, région.',
        'segments' => 'Tronçons en GeoJSON ou MVT, pour intégrer dans une carte tierce.',
        'stations' => 'Gares actuelles et historiques, avec dates ouverture/fermeture.',
        'materials' => 'Matériel roulant : fiches, périodes d\'exploitation, lignes parcourues.',
        'events' => 'Événements historiques : inaugurations, fermetures, démantèlements.',
        'media' => 'Médias : photos d\'archive géolocalisées et datées.',
        'items' => [
            'lines_geojson' => "FeatureCollection GeoJSON de tous les tronçons d'une ère donnée.",
            'stations_geojson' => 'FeatureCollection des gares actives à cette ère (filtrées par statut).',
            'tiles' => 'Tuile vectorielle Mapbox (binaire) pour un rendu MapLibre. Propriétés : status, traffic_type, electrified, is_heritage, voltage_v, max_speed_kmh, line_slug, line_name, line_code.',
            'search' => 'Recherche avec autocomplétion dans les lignes, les gares et les matériels. 2 caractères au minimum.',
            'history' => 'Historique complet : tous les événements chronologiques et le statut à chaque ère.',
            'pk' => 'Convertit (lat, lng) en point kilométrique sur la ligne avec PostGIS ST_LineLocatePoint. Précision inférieure au mètre.',
            'stops' => 'Séquence ordonnée des gares de la ligne (équivalent GTFS stop_times) : position, PK, statut effectif à l’ère, is_terminal.',
            'route' => 'Itinéraire ferroviaire entre 2 gares (Dijkstra sur le graphe matérialisé) : distance, durée estimée, étapes, polyligne.',
            'gtfs' => 'Export GTFS complet du réseau d’une ère (agency, routes, stops, trips, stop_times, shapes, calendar), compatible avec tout outil GTFS standard.',
        ],
    ],

    'format' => [
        'title' => 'Formats supportés',
        'json' => 'JSON (REST classique)',
        'geojson' => 'GeoJSON (couches cartographiques)',
        'mvt' => 'MVT : Mapbox Vector Tiles (pour MapLibre / Leaflet)',
    ],

    'example' => [
        'title' => 'Exemple anticipé',
        'description' => 'Récupérer toutes les lignes en service en 2000 (pour avoir une idée du format) :',
        'response' => 'Réponse attendue :',
        'curl_lines' => 'Récupérer toutes les lignes en service en 2030 (GeoJSON)',
        'curl_pk' => 'Trouver le PK d’un point sur la ligne Paris - Brest',
        'curl_search' => 'Recherche avec autocomplétion (lignes, gares et matériels)',
    ],

    'graphql' => [
        'title' => 'API GraphQL (Lighthouse)',
        'text' => 'En plus de l’API REST, VFF expose un point d’accès GraphQL unique pour les requêtes complexes : une ligne avec ses tronçons, ses événements, ses matériels et ses gares en une seule requête.',
        'explorer_desc' => 'Explorer le schéma, autocomplétion, exécution de requêtes',
        'endpoint_desc' => 'Point d’accès unique pour toutes les requêtes',
        'example_comment' => 'Exemple : ligne Argentan - Granville avec ses gares et ses événements',
    ],

    'openapi' => [
        'title' => 'Documentation interactive OpenAPI 3',
        'text_before' => 'La spécification OpenAPI 3.1 est générée automatiquement à partir des contrôleurs Laravel (avec',
        'text_middle' => ') et affichée avec',
        'text_after' => '.',
        'docs' => 'Documentation interactive',
        'docs_desc' => 'Interface Scalar avec essai en direct',
        'spec' => 'Spécification OpenAPI 3.1 (JSON)',
        'spec_desc' => 'À importer dans Postman, Insomnia…',
    ],

    'cta' => [
        'title' => 'Tenez-vous au courant',
        'text' => "L'API arrive en phase 6 de la roadmap. Suivez l'avancée sur Discord ou GitHub.",
        'discord' => 'Discord',
        'github' => 'Voir le code',
    ],
];
