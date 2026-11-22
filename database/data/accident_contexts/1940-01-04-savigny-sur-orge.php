<?php

// Contexte sourcé de la collision d'une machine de secours et d'un train Paris - Orléans en panne près de la gare de Savigny-sur-Orge, janvier 1940 (recherche du 22/11/2026).
return [
    'slug' => '1940-01-04-savigny-sur-orge',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1940',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1940',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 4 janvier 1940, d\'après Le Petit Parisien du 5 janvier : à 200 m de la gare de Savigny-sur-Orge, un train Paris - Orléans en détresse est heurté par la machine envoyée pour le dépanner ; le fourgon de queue et les trois dernières voitures se télescopent ; un mort et sept blessés.',
        ],
        'elux-1940-01-06-luxemburger-zeitung' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 5. Jan. (Havas)',
            'publisher' => 'Luxemburger Zeitung (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1940-01-06',
            'locator' => 'p. 3',
            'url' => 'https://persist.lu/ark:70795/776hg15dwq/pages/3/articles/DIVL299',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche Havas de Paris du 5 janvier : la veille au soir, un train de voyageurs pour Orléans est entré en collision avec une locomotive près de la gare de Savigny-sur-Orge ; un voyageur tué, sept blessés.',
        ],
        'elux-1940-01-08-landwirth' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Lokomotive rennt in einen Personenzug',
            'publisher' => 'Luxemburger Landwirth (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1940-01-08',
            'locator' => 'p. 6',
            'url' => 'https://persist.lu/ark:70795/hk2z9ms6vq/pages/6/articles/DIVL515',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Le train de voyageurs parti à 17 h de la gare d\'Austerlitz s\'est arrêté avant la gare de Savigny-sur-Orge sur une avarie de machine ; une locomotive l\'a heurté, endommageant le fourgon de queue et trois voitures ; sept voyageurs blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 4 janvier 1940, un train de voyageurs de Paris à Orléans, parti à 17 h de la gare d\'Austerlitz, s\'est arrêté sur une avarie de sa machine à environ 200 m de la gare de Savigny-sur-Orge. La locomotive envoyée pour le dépanner l\'a heurté par l\'arrière : le fourgon de queue et les trois dernières voitures se sont télescopés.',
                'en' => 'On 4 January 1940, a passenger train from Paris to Orléans, which had left Austerlitz station at 5 pm, came to a halt with an engine failure about 200 m from Savigny-sur-Orge station. The engine sent to rescue it ran into its rear: the rear van and the last three carriages telescoped.',
                'de' => 'Am 4. Januar 1940 blieb ein Personenzug Paris-Orléans, der um 17 Uhr den Bahnhof Austerlitz verlassen hatte, wegen eines Maschinenschadens etwa 200 m vor dem Bahnhof Savigny-sur-Orge liegen. Die zur Hilfe geschickte Lokomotive fuhr auf ihn auf: Der hintere Packwagen und die drei letzten Wagen schoben sich ineinander.',
                'sources' => ['wp-liste-1940', 'elux-1940-01-06-luxemburger-zeitung', 'elux-1940-01-08-landwirth'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur a été tué et sept blessés.',
                'en' => 'One passenger was killed and seven were injured.',
                'de' => 'Ein Reisender wurde getötet, sieben wurden verletzt.',
                'sources' => ['wp-liste-1940', 'elux-1940-01-06-luxemburger-zeitung', 'elux-1940-01-08-landwirth'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1940', 'elux-1940-01-06-luxemburger-zeitung', 'elux-1940-01-08-landwirth'],
            'fr' => 'Collision d\'une locomotive de secours avec le train arrêté, selon la liste de Wikipédia et deux journaux luxembourgeois ; la fiche indiquait un incident.',
            'en' => 'Collision between a relief engine and the stopped train, according to the Wikipedia list and two Luxembourg newspapers; the record gave an incident.',
            'de' => 'Zusammenstoß einer Hilfslokomotive mit dem stehenden Zug nach der Wikipedia-Liste und zwei luxemburgischen Zeitungen; der Eintrag nannte einen Zwischenfall.',
        ],
    ],
];
