<?php

// Contexte sourcé de la collision de l'express 108 avec un wagon couché près de Seigny du 1er février 1957 (recherche du 21/11/2026).
return [
    'slug' => '1957-02-01-seigny',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-seigny' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinramp tussen Parijs en Dijon',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1957-02-01',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB19:000390027:mpeg21:a00005',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1950' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1950',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1957',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1950&oldid=235357701#1957',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : express 108 prenant en écharpe un wagon couché après une rupture d\'essieu au niveau de la sous-station de Seigny, un mort (le conducteur) et quatre blessés ; cite Le Bien Public du 2 février 1957.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le vendredi 1er février 1957, sur la ligne de Paris à Dijon, au niveau de la sous-station électrique de Seigny, en Côte-d\'Or, des wagons d\'un train de marchandises étaient sortis des rails, un wagon étant couché sur la voie à la suite d\'une rupture d\'essieu selon la liste de Wikipédia.',
                'en' => 'On Friday 1 February 1957, on the Paris to Dijon line, at the Seigny electrical substation, in the Côte-d\'Or, wagons of a goods train had left the rails, one wagon lying across the track after an axle failure according to the Wikipedia list.',
                'de' => 'Am Freitag, dem 1. Februar 1957, waren auf der Strecke Paris-Dijon auf Höhe des Umspannwerks von Seigny im Département Côte-d\'Or Wagen eines Güterzugs aus dem Gleis gesprungen, wobei nach der Wikipedia-Liste ein Wagen nach einem Achsbruch quer auf dem Gleis lag.',
                'sources' => ['presse-seigny', 'wp-liste-1950'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'express 108, qui roulait à 125 km/h, a pris en écharpe ou heurté les wagons de marchandises sortis des rails, selon la presse néerlandaise et la liste de Wikipédia.',
                'en' => 'Express 108, travelling at 125 km/h, struck the goods wagons that had come off the rails, according to the Dutch press and the Wikipedia list.',
                'de' => 'Der Schnellzug 108, der mit 125 km/h fuhr, prallte nach der niederländischen Presse und der Wikipedia-Liste auf die entgleisten Güterwagen.',
                'sources' => ['presse-seigny', 'wp-liste-1950'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le conducteur de l\'express a été tué et quatre voyageurs ont été légèrement blessés, selon la presse néerlandaise ; la liste de Wikipédia donne le même bilan.',
                'en' => 'The express driver was killed and four passengers slightly injured, according to the Dutch press; the Wikipedia list gives the same toll.',
                'de' => 'Der Lokführer des Schnellzugs wurde getötet und vier Fahrgäste wurden leicht verletzt, nach der niederländischen Presse; die Wikipedia-Liste nennt dieselbe Bilanz.',
                'sources' => ['presse-seigny', 'wp-liste-1950'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
