<?php

// Contexte sourcé de la collision de Saint-Jean-de-Maurienne du 13 mars 1918 (recherche du 21/11/2026).
return [
    'slug' => '1918-03-13-saint-jean-de-maurienne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1918' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1918',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Mars',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1918&oldid=241528217#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne Chambéry-Modane, en gare de Saint-Jean-de-Maurienne, par suite d\'une erreur d\'aiguillage un train de voyageurs en percute un autre, 3 morts et 3 blessés ; référence Le Figaro du 14 mars 1918, p. 2.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 13 mars 1918, sur la ligne de Chambéry à Modane, en gare de Saint-Jean-de-Maurienne, un train de voyageurs en a percuté un autre.',
                'en' => 'On 13 March 1918, on the Chambéry to Modane line, in Saint-Jean-de-Maurienne station, one passenger train ran into another.',
                'de' => 'Am 13. März 1918 fuhr auf der Strecke von Chambéry nach Modane im Bahnhof Saint-Jean-de-Maurienne ein Personenzug auf einen anderen auf.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La liste de Wikipédia attribue la collision à une erreur d\'aiguillage.',
                'en' => 'The Wikipedia list attributes the collision to a pointing error.',
                'de' => 'Die Wikipedia-Liste führt die Kollision auf einen Weichenfehler zurück.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte trois morts et trois blessés.',
                'en' => 'The Wikipedia list counts three dead and three injured.',
                'de' => 'Die Wikipedia-Liste zählt drei Tote und drei Verletzte.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
    ],
];
