<?php

// Contexte sourcé de la collision du 10 juillet 1937 à la sortie de la gare du Mans, d'un train pour Rennes
// (recherche du 21/11/2026).
return [
    'slug' => '1937-07-10-rennes',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1937' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1937',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juillet, 10 juillet 1937',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1937&oldid=235357736#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de juillet 1937 (Gallica).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 10 juillet 1937 vers 18 h 45, à la sortie de la gare du Mans, un train de voyageurs à destination de Caen était arrêté devant un carré fermé.',
                'en' => 'On 10 July 1937 at about 6.45 pm, just outside Le Mans station, a passenger train bound for Caen was standing at a stop signal.',
                'de' => 'Am 10. Juli 1937 gegen 18.45 Uhr stand kurz hinter dem Bahnhof Le Mans ein Personenzug nach Caen vor einem Halt zeigenden Signal.',
                'sources' => ['wp-liste-1937'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un train pour Rennes, dont le mécanicien roulait trop vite malgré un signal d\'avertissement, l\'a percuté par l\'arrière. Le fourgon et les trois dernières voitures du train tamponné ont été détruits.',
                'en' => 'A train for Rennes, whose driver was going too fast despite a warning signal, ran into its rear. The van and the last three coaches of the stationary train were destroyed.',
                'de' => 'Ein Zug nach Rennes, dessen Lokführer trotz eines Vorsignals zu schnell fuhr, prallte auf sein Ende. Der Packwagen und die letzten drei Wagen des gerammten Zuges wurden zerstört.',
                'sources' => ['wp-liste-1937'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait neuf morts et 35 blessés.',
                'en' => 'The accident killed nine people and injured 35.',
                'de' => 'Der Unfall forderte neun Tote und 35 Verletzte.',
                'sources' => ['wp-liste-1937'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '72181',
            'sources' => ['wp-liste-1937'],
            'fr' => 'Rennes était la destination du train tamponneur ; la collision a eu lieu à la sortie de la gare du Mans.',
            'en' => 'Rennes was the destination of the colliding train; the collision happened just outside Le Mans station.',
            'de' => 'Rennes war das Ziel des auffahrenden Zuges; der Zusammenstoß ereignete sich kurz hinter dem Bahnhof Le Mans.',
        ],
    ],
];
