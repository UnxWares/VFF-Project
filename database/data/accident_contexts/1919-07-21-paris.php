<?php

// Contexte sourcé du déraillement de la gare de Versailles-Rive-Gauche du 21 juillet 1919, classé à Paris (recherche du 23/11/2026).
return [
    'slug' => '1919-07-21-paris',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1919, 21 juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Entrée de la liste : en gare de Versailles-Rive-Gauche, déraillement des dernières voitures d\'un train arrivant de Paris-Montparnasse, un mort et dix-sept blessés. Cite Le Petit Parisien du 22 juillet 1919.',
        ],
        'petit-parisien-1919-07-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 22 juillet 1919',
            'publisher' => 'Le Petit Parisien (numérisé sur Gallica)',
            'author' => null,
            'published' => '1919-07-22',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5669633/f2.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-23',
            'note' => 'Page 2, citée par la liste de Wikipédia ; non lue (accès refusé aux accès automatiques).',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 21 juillet 1919 vers 7 heures, en gare de Versailles-Rive-Gauche, une erreur dans la manœuvre d\'un aiguillage a fait dérailler les dernières voitures d\'un train arrivant de Paris-Montparnasse ; elles ont heurté une machine en stationnement et se sont renversées, d\'après la liste de Wikipédia.',
                'en' => 'On 21 July 1919 at about 7 a.m., at Versailles-Rive-Gauche station, a mistake in operating a set of points derailed the last carriages of a train arriving from Paris-Montparnasse; they struck a stationary engine and overturned, according to the Wikipedia list.',
                'de' => 'Am 21. Juli 1919 gegen 7 Uhr entgleisten im Bahnhof Versailles-Rive-Gauche durch einen Fehler beim Stellen einer Weiche die letzten Wagen eines aus Paris-Montparnasse einlaufenden Zuges; sie stießen gegen eine abgestellte Lokomotive und kippten um, so die Wikipedia-Liste.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un mort et dix-sept blessés selon la liste de Wikipédia.',
                'en' => 'One dead and seventeen injured according to the Wikipedia list.',
                'de' => 'Ein Toter und siebzehn Verletzte nach der Wikipedia-Liste.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste de Wikipédia, une erreur dans la manœuvre d\'un aiguillage.',
                'en' => 'According to the Wikipedia list, a mistake in operating a set of points.',
                'de' => 'Laut der Wikipedia-Liste ein Fehler beim Stellen einer Weiche.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '78646',
            'sources' => ['wp-liste-1919'],
            'fr' => 'La liste de Wikipédia situe le déraillement en gare de Versailles-Rive-Gauche ; Paris-Montparnasse n\'est que la gare de départ du train. La fiche le plaçait à Paris, à la gare Montparnasse, à 0 m.',
            'en' => 'The Wikipedia list places the derailment at Versailles-Rive-Gauche station; Paris-Montparnasse is only the departure station of the train. The record placed it in Paris, at Montparnasse station, at 0 m.',
            'de' => 'Die Wikipedia-Liste verortet die Entgleisung im Bahnhof Versailles-Rive-Gauche; Paris-Montparnasse ist nur der Ausgangsbahnhof des Zuges. Der Eintrag setzte sie nach Paris an den Bahnhof Montparnasse in 0 m.',
            'single_source' => true,
        ],
    ],
];
