<?php

// Contexte sourcé de la collision en gare de Villars (Loire) du 28 mai 1917 (recherche du 21/11/2026).
return [
    'slug' => '1917-05-28-villars',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1917' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1917',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Mai',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1917&oldid=235357718#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : en gare de Villars, ligne Saint-Étienne - Paris, vers 22 h 30, l\'express pour Paris percute un train de marchandises dont trois wagons sont disloqués et prennent feu ; 2 morts et 3 blessés.',
        ],
        'le-temps-1917-05-30' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps, 30 mai 1917, p. 3',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1917-05-30',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2429258/f3.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 28 mai 1917, vers 22 h 30, en gare de Villars, sur la ligne de Saint-Étienne vers Paris, l\'express pour Paris percute un train de marchandises.',
                'en' => 'On 28 May 1917, at about 10.30 pm, at Villars station on the line from Saint-Étienne towards Paris, the express for Paris ran into a goods train.',
                'de' => 'Am 28. Mai 1917 fuhr gegen 22.30 Uhr im Bahnhof Villars an der Strecke von Saint-Étienne nach Paris der Schnellzug nach Paris auf einen Güterzug auf.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Trois wagons du train de marchandises sont disloqués et prennent feu.',
                'en' => 'Three wagons of the goods train were smashed and caught fire.',
                'de' => 'Drei Wagen des Güterzugs wurden zertrümmert und gerieten in Brand.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait deux morts et trois blessés.',
                'en' => 'The accident killed two people and injured three.',
                'de' => 'Das Unglück forderte zwei Tote und drei Verletzte.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
    ],
];
