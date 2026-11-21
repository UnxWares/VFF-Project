<?php

// Contexte sourcé de la collision de Bon-Encontre du 22 septembre 1918 (recherche du 21/11/2026).
return [
    'slug' => '1918-09-22-bon-encontre',
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
            'locator' => 'section Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1918&oldid=241528217#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne Bordeaux - Auch, peu après Agen, en gare de Bon-Encontre, un train de voyageurs pour Auch est percuté par une machine haut-le-pied, quatre morts et trente-huit blessés. Cite Le Matin du 23 septembre 1918, page 2.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 22 septembre 1918, sur la ligne de Bordeaux à Auch, peu après Agen, en gare de Bon-Encontre, un train de voyageurs à destination d\'Auch a été percuté par une machine haut-le-pied (locomotive circulant seule).',
                'en' => 'On 22 September 1918, on the Bordeaux to Auch line, shortly after Agen, in Bon-Encontre station, a passenger train bound for Auch was struck by a light engine (a locomotive running alone).',
                'de' => 'Am 22. September 1918 wurde auf der Strecke Bordeaux - Auch kurz hinter Agen im Bahnhof Bon-Encontre ein Reisezug nach Auch von einer Lokomotive ohne Zug (Leerfahrt) gerammt.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia donne quatre morts et trente-huit blessés.',
                'en' => 'The Wikipedia list gives four dead and thirty-eight injured.',
                'de' => 'Die Wikipedia-Liste nennt vier Tote und achtunddreißig Verletzte.',
                'sources' => ['wp-liste-1918'],
            ],
        ],
    ],
];
