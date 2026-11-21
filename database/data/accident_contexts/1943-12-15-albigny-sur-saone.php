<?php

// Contexte sourcé du sabotage du Vintimille - Paris près de la gare d'Albigny - Neuville le 15 décembre 1943 (recherche du 21/11/2026).
return [
    'slug' => '1943-12-15-albigny-sur-saone',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1943, 15 décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1943',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de l\'époque (Gallica).',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 15 décembre 1943 à 3 h, près de la gare d\'Albigny - Neuville, à 18 km de Lyon, le train Vintimille - Paris a déraillé à la suite d\'un sabotage : un rail avait été déboulonné.',
                'en' => 'On 15 December 1943 at 3 am, near Albigny - Neuville station, 18 km from Lyon, the Ventimiglia to Paris train derailed after sabotage: a rail had been unbolted.',
                'de' => 'Am 15. Dezember 1943 um 3 Uhr entgleiste nahe dem Bahnhof Albigny-Neuville, 18 km von Lyon, der Zug Ventimiglia-Paris infolge eines Sabotageakts: Eine Schiene war gelöst worden.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait huit morts, dont le gangster marseillais Paul Carbone, et quarante blessés.',
                'en' => 'The accident killed eight people, including the Marseille gangster Paul Carbone, and injured forty.',
                'de' => 'Der Unfall forderte acht Tote, darunter den Marseiller Gangster Paul Carbone, und vierzig Verletzte.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'attack',
            'sources' => ['wp-liste-1940'],
            'fr' => 'Le déraillement a été provoqué par un sabotage, un rail déboulonné : la fiche est rangée avec les attentats.',
            'en' => 'The derailment was caused by sabotage, an unbolted rail: the record is classed with attacks.',
            'de' => 'Die Entgleisung wurde durch Sabotage, eine gelöste Schiene, verursacht: Der Eintrag wird bei den Anschlägen eingeordnet.',
        ],
    ],
];
