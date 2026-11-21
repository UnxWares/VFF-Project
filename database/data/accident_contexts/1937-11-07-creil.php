<?php

// Contexte sourcé du double accident à la sortie sud de la gare de Creil du 7 novembre 1937 (recherche du 21/11/2026).
return [
    'slug' => '1937-11-07-creil',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-creil' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Een doode en vijf gewonden zijn te betreuren bij de botsing, welke te Creil (Fr.) plaats had tusschen twee treinen',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1937-11-09',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB15:000880033:mpeg21:a00060',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1937' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1937',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1937&oldid=235357736#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : collision de trains de marchandises dans le brouillard à la sortie sud de la gare de Creil, un mort et six blessés ; cite Le Matin du 8 novembre 1937.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 7 novembre 1937, vers 4 h, à la sortie sud de la gare de Creil, un train de marchandises, malgré un avis de marche prudente, en a percuté un autre arrêté dans le brouillard.',
                'en' => 'On Sunday 7 November 1937, at about 4 am, at the southern exit of Creil station, a goods train, despite a caution notice, ran into another stopped in the fog.',
                'de' => 'Am Sonntag, dem 7. November 1937, gegen 4 Uhr fuhr am Südausgang des Bahnhofs Creil ein Güterzug trotz einer Meldung zur vorsichtigen Fahrt auf einen anderen im Nebel stehenden auf.',
                'sources' => ['wp-liste-1937'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les wagons renversés sur les voies lors de la collision ont ensuite été percutés par un troisième train venant de Chantilly, ce qui a fait un double accident, d\'après la liste de Wikipédia.',
                'en' => 'The wagons overturned across the tracks in the collision were then hit by a third train coming from Chantilly, making a double accident, according to the Wikipedia list.',
                'de' => 'Die beim Zusammenstoß auf die Gleise gekippten Wagen wurden anschließend von einem dritten, aus Chantilly kommenden Zug gerammt, so dass ein Doppelunfall entstand, nach der Wikipedia-Liste.',
                'sources' => ['wp-liste-1937'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une personne a été tuée et cinq blessées, selon la légende d\'une photographie de la presse néerlandaise du 9 novembre 1937 ; la liste de Wikipédia compte six blessés.',
                'en' => 'One person was killed and five injured, according to a photo caption in the Dutch press of 9 November 1937; the Wikipedia list counts six injured.',
                'de' => 'Eine Person wurde getötet und fünf wurden verletzt, nach einer Bildunterschrift der niederländischen Presse vom 9. November 1937; die Wikipedia-Liste zählt sechs Verletzte.',
                'sources' => ['presse-creil', 'wp-liste-1937'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
