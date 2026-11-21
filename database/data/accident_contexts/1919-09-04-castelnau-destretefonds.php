<?php

// Contexte sourcé de l'accident de Castelnau-d'Estrétefonds du 4 septembre 1919 (recherche du 21/11/2026).
return [
    'slug' => '1919-09-04-castelnau-destretefonds',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '4 septembre, Castelnau-d\'Estrétefonds / Saint-Jory',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Heure, tronçon, trains, 20 morts et 40 blessés ; renvoie à Le Petit Parisien du 6 septembre 1919.',
        ],
        'pp-1919-09-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien, 6 septembre 1919',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1919-09-06',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k567009s/f3.image.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 4 septembre 1919, vers 23 h, sur la ligne de Montauban à Toulouse, entre les gares de Castelnau-d\'Estrétefonds et de Saint-Jory, l\'express Paris-Toulouse roule au pas à cause d\'une panne.',
                'en' => 'On 4 September 1919, at about 11 pm, on the Montauban-Toulouse line between Castelnau-d\'Estrétefonds and Saint-Jory stations, the Paris-Toulouse express was crawling along because of a breakdown.',
                'de' => 'Am 4. September 1919 gegen 23 Uhr fuhr der Schnellzug Paris-Toulouse auf der Strecke Montauban-Toulouse zwischen den Bahnhöfen Castelnau-d\'Estrétefonds und Saint-Jory wegen einer Panne im Schritttempo.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
        ],
        'course' => [
            [
                'fr' => 'Il est percuté par un express venu de Bordeaux, remorqué en double traction. Ce dernier détruit les trois dernières voitures du train tamponné.',
                'en' => 'It was struck by an express from Bordeaux, double-headed. The latter destroyed the last three coaches of the struck train.',
                'de' => 'Er wurde von einem Schnellzug aus Bordeaux gerammt, der doppelt bespannt war. Dieser zerstörte die letzten drei Wagen des gerammten Zuges.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de 20 morts et 40 blessés.',
                'en' => 'The toll is 20 dead and 40 injured.',
                'de' => 'Die Bilanz beträgt 20 Tote und 40 Verletzte.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
        ],
    ],
];
