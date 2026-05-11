<?php

return [
    'meta_title' => 'Nos cartes',
    'meta_description' => "Carte interactive du réseau ferré français, en service et disparu, par époque.",

    'hero' => [
        'eyebrow' => 'Cartographie',
        'title' => 'Le rail français comme vous ne l’avez jamais vu',
        'subtitle' => "Une carte interactive du réseau ferré français, vivant ou disparu, traversant les décennies de 1990 à aujourd'hui.",
        'status' => 'MVP en développement',
    ],

    'features' => [
        'title' => 'Ce que la carte permettra',
        'time_title' => 'Sélecteur temporel',
        'time_text' => 'Un slider pour basculer entre 1990, 2000, 2010, 2020 et 2030. Les tracés disparus apparaissent et disparaissent en temps réel.',
        'lines_title' => 'Tracés détaillés',
        'lines_text' => 'En service, voie unique, abandonnée, déposée, rasée — chaque statut a son style, chaque ligne sa fiche.',
        'materials_title' => 'Matériel roulant',
        'materials_text' => 'Cliquez sur une ligne, découvrez les locomotives, automotrices et rames qui l’ont parcourue, avec photos d’archives.',
        'archives_title' => 'Archives géolocalisées',
        'archives_text' => "Photos, plans, documents, datés et placés sur la carte. Un voyage spatio-temporel à travers le patrimoine.",
    ],

    'tech' => [
        'title' => 'Sous le capot',
        'paragraph' => "La carte est construite avec MapLibre GL JS sur un fond OpenStreetMap. Nos données ferroviaires sont stockées en LINESTRING dans PostgreSQL+PostGIS et servies en tuiles vectorielles MVT pour des performances optimales, même avec des milliers de polylignes empilées sur plusieurs époques. Un overlay OpenRailwayMap optionnel affiche l'infrastructure détaillée (signaux, électrification, vitesses).",
    ],

    'progress' => [
        'title' => 'Avancement',
        'phase' => 'Phase 2 — Carte interactive (MVP)',
        'description' => 'Voir la <a href=":roadmap_url" target="_blank" rel="noopener">roadmap complète</a> pour le détail des fonctionnalités à venir.',
    ],

    'cta' => [
        'title' => 'Soyez prévenu·e au lancement',
        'text' => 'Rejoignez notre Discord pour être informé·e dès l’ouverture de la carte interactive.',
        'discord' => 'Rejoindre Discord',
    ],
];
