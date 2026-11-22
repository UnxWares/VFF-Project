<?php

// Contexte sourcé du déraillement de l'express des Sables-d'Olonne près de Saint-Mesmin du 12 septembre 1923 (recherche du 21/11/2026).
return [
    'slug' => '1923-09-12-saint-mesmin',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1923' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1923',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1923&oldid=233501473#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne des Sables-d\'Olonne à Tours, entre les gares de Pouzauges et de Saint-Mesmin, l\'express Les Sables-d\'Olonne - Paris déraille vers 21 h 45 ; quatre voitures renversées, 3 morts, 26 ou 23 blessés selon les journaux.',
        ],
        'delpher-1923-09-14' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Telegrammen',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1923-09-14',
            'locator' => 'Provinciale Overijsselsche en Zwolsche courant',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMHCO01:000083013:mpeg21:a0010',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 14 septembre : l\'express venant de Vendée a déraillé, 3 morts et 25 blessés.',
        ],
        'le-matin-1923-09-14' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin, 14 septembre 1923, p. 2',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1923-09-14',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5744548/f2.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article cité par la liste de Wikipédia (26 blessés).',
        ],
        'homme-libre-1923-09-14' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'L\'Homme libre, 14 septembre 1923',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1923-09-14',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k75961180',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Numéro cité par la liste de Wikipédia (23 blessés).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 12 septembre 1923, vers 21 h 45, l\'express Les Sables-d\'Olonne - Paris déraille sur la ligne des Sables-d\'Olonne à Tours, entre les gares de Pouzauges et de Saint-Mesmin. Quatre voitures se renversent.',
                'en' => 'On 12 September 1923, at about 9.45 pm, the Les Sables-d\'Olonne to Paris express derailed on the Les Sables-d\'Olonne to Tours line, between Pouzauges and Saint-Mesmin stations. Four coaches overturned.',
                'de' => 'Am 12. September 1923 entgleiste gegen 21.45 Uhr der Schnellzug Les Sables-d\'Olonne - Paris auf der Strecke Les Sables-d\'Olonne - Tours zwischen den Bahnhöfen Pouzauges und Saint-Mesmin. Vier Wagen stürzten um.',
                'sources' => ['wp-liste-1923', 'delpher-1923-09-14'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait trois morts. Une dépêche reprise aux Pays-Bas le 14 septembre compte 25 blessés.',
                'en' => 'The accident killed three people. A report carried in the Netherlands on 14 September counted 25 injured.',
                'de' => 'Bei dem Unglück kamen drei Menschen ums Leben. Eine am 14. September in den Niederlanden abgedruckte Meldung zählte 25 Verletzte.',
                'sources' => ['wp-liste-1923', 'delpher-1923-09-14'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La liste de Wikipédia avance, avec réserve, une dilatation de la voie sous l\'effet de la chaleur.',
                'en' => 'The Wikipedia list suggests, with reservations, that the track had buckled in the heat.',
                'de' => 'Die Wikipedia-Liste nennt mit Vorbehalt eine Verwerfung des Gleises durch die Hitze.',
                'sources' => ['wp-liste-1923'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère selon les journaux : 26 (Le Matin, d\'après la liste), 25 (dépêche du 14 septembre), 23 (L\'Homme libre, d\'après la liste).',
                'en' => 'The number of injured differs between newspapers: 26 (Le Matin, according to the list), 25 (report of 14 September), 23 (L\'Homme libre, according to the list).',
                'de' => 'Die Zahl der Verletzten weicht je nach Zeitung ab: 26 (Le Matin, laut Liste), 25 (Meldung vom 14. September), 23 (L\'Homme libre, laut Liste).',
                'sources' => ['wp-liste-1923', 'delpher-1923-09-14'],
            ],
        ],
    ],
];
