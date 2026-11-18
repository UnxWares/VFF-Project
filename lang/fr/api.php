<?php

// Page « Accès à l'API » (/api). Vocabulaire : « point d'accès » (pas « endpoint »), « ère »,
// « référence » pour les documentations générées.
return [
    'meta_title' => "Accès à l'API",
    'meta_description' => "Accès à l'API de VFF : points d'accès REST, API GraphQL, spécification OpenAPI et exemples de requêtes. Lecture seule, sans clé ni compte.",

    'hero' => [
        'eyebrow' => 'API publique',
        'title' => 'Les données de VFF pour vos propres outils',
        'subtitle' => "Lignes, gares, chronologies et tracés du réseau ferré, de 1830 à aujourd'hui, en JSON, GeoJSON, tuiles vectorielles et GTFS. Lecture seule, sans clé ni compte.",
    ],

    'docs' => [
        'title' => 'Documentations',
        'intro' => 'Toutes décrivent les mêmes données : choisissez l’interface qui vous convient.',
        'items' => [
            'scalar' => ['title' => 'Référence REST (Scalar)', 'text' => 'La référence principale, avec essais en direct et exemples de code.'],
            'swagger' => ['title' => 'Référence REST (Swagger UI)', 'text' => 'La même spécification dans une interface plus classique.'],
            'openapi' => ['title' => 'Spécification OpenAPI 3.1', 'text' => 'Fichier JSON à importer dans Postman, Insomnia ou un générateur de client.'],
            'graphql' => ['title' => 'Référence GraphQL', 'text' => 'Types, requêtes et exemples du schéma.'],
            'graphiql' => ['title' => 'GraphiQL', 'text' => 'Console pour écrire et exécuter des requêtes GraphQL.'],
            'schema' => ['title' => 'Schéma GraphQL (SDL)', 'text' => 'Le schéma complet, au format texte.'],
        ],
    ],

    'access' => [
        'title' => 'Accès et limites',
        'readonly' => ['title' => 'Lecture seule', 'text' => 'Les requêtes REST sont toutes des GET ; GraphQL ne propose que des requêtes de lecture.'],
        'no_key' => ['title' => 'Sans clé ni compte', 'text' => 'Aucune inscription : appelez directement les adresses.'],
        'limit' => ['title' => '300 requêtes par minute', 'text' => 'Limite par adresse IP, pour REST comme pour GraphQL. Au-delà, le serveur répond HTTP 429.'],
        'licence' => ['title' => 'Licences des données', 'text' => 'Citez « VFF - Voies Ferrées de France » avec un lien vers vff-project.org. Chaque source garde sa licence : le détail figure dans les références.'],
    ],

    'endpoints' => [
        'title' => 'Points d’accès REST',
        'intro' => 'Toutes les adresses commencent par /api/v1 et répondent en GET. Dans les chemins, {year} désigne une ère décennale (de 1830 à 2030) et {line} l’identifiant d’une ligne, par exemple rfn-420000.',
        'label' => 'Points d’accès REST de l’API VFF',
        'items' => [
            'lines_geojson' => 'Tracés du réseau à une ère, en GeoJSON (FeatureCollection de tronçons).',
            'stations_geojson' => 'Gares à une ère, en GeoJSON. Les gares fermées y figurent tant qu’une voie existe à proximité, avec leur statut.',
            'tiles' => 'Tuile vectorielle (MVT) d’une ère, pour une source MapLibre ou OpenLayers.',
            'gtfs' => 'Réseau d’une ère au format GTFS Static (archive ZIP).',
            'search' => 'Recherche par nom dans les lignes, les gares et le matériel. 2 caractères au minimum.',
            'history' => 'Chronologie d’une ligne : événements datés et état à chaque ère.',
            'pk' => 'Point kilométrique d’un lieu sur une ligne, à partir de sa latitude et de sa longitude.',
            'stops' => 'Gares d’une ligne dans l’ordre des points kilométriques, à une ère.',
            'signalling' => 'Tranches de points kilométriques homogènes en signalisation.',
            'media' => 'Photos, cartes postales et plans liés à une ligne, avec leurs droits.',
            'route' => 'Itinéraire ferroviaire entre deux gares sur le réseau d’une ère : distance, étapes et tracé.',
        ],
    ],

    'example' => [
        'title' => 'Exemples REST',
        'intro' => 'Trois requêtes à copier dans un terminal, puis la réponse de la deuxième, telle que le serveur la renvoie.',
        'curl_lines' => 'Tracés du réseau d’aujourd’hui (ère 2030), en GeoJSON',
        'curl_pk' => 'Point kilométrique d’un lieu sur la ligne Paris-Montparnasse - Brest',
        'curl_search' => 'Recherche par nom',
        'response' => 'Réponse à la deuxième requête : le point est ramené sur le tracé le plus proche (snap), à 395 m ici.',
        'code_label' => 'Exemple de code',
    ],

    'graphql' => [
        'title' => 'API GraphQL',
        'text' => 'Pour les requêtes plus riches, un seul point d’accès GraphQL, POST /graphql, renvoie en une fois une ligne avec ses tronçons et ses événements. Lisez le schéma dans la référence, ou essayez vos requêtes dans GraphiQL.',
        'links' => 'Documentations GraphQL',
        'example_comment' => 'Une ligne avec ses tronçons et ses événements',
    ],

    'cta' => [
        'title' => 'Une question, une idée ?',
        'text' => 'Dites-nous ce que vous construisez ou ce qui manque, sur Discord, ou signalez un problème sur GitHub.',
        'discord' => 'Discuter sur Discord',
        'github' => 'Voir le code sur GitHub',
    ],
];
