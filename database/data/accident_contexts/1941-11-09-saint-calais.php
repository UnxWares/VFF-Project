<?php

// Contexte sourcé de la dérive de wagons en gare de Saint-Calais du 9 novembre 1941 (recherche du 21/11/2026).
return [
    'slug' => '1941-11-09-saint-calais',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940s' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1941',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1941',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Mamers à Saint-Calais, en gare de Saint-Calais, vers 15 heures, une rame de wagons en dérive dans une pente percute un train de voyageurs bondé en partance pour Connerré ; 3 morts et 13 blessés.',
        ],
        'petit-parisien-1941-11-11' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien, 11 novembre 1941, p. 3',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1941-11-11',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k684523m/f3.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 9 novembre 1941, vers 15 heures, en gare de Saint-Calais, sur la ligne de Mamers à Saint-Calais, une rame de wagons part à la dérive dans une pente et vient percuter un train de voyageurs bondé qui s\'apprête à partir pour Connerré.',
                'en' => 'On 9 November 1941, at about 3 pm, at Saint-Calais station on the Mamers to Saint-Calais line, a rake of wagons ran away down a gradient and struck a crowded passenger train about to leave for Connerré.',
                'de' => 'Am 9. November 1941 rollte gegen 15 Uhr im Bahnhof Saint-Calais an der Strecke Mamers - Saint-Calais eine Wagengruppe auf einem Gefälle unkontrolliert davon und prallte auf einen voll besetzten Personenzug, der nach Connerré abfahren sollte.',
                'sources' => ['wp-liste-1940s'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La collision fait trois morts et treize blessés.',
                'en' => 'The collision killed three people and injured thirteen.',
                'de' => 'Der Zusammenstoß forderte drei Tote und dreizehn Verletzte.',
                'sources' => ['wp-liste-1940s'],
            ],
        ],
    ],
];
