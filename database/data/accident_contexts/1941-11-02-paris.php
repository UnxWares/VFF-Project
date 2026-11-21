<?php

// Contexte sourcé de l'accident de Paris-Austerlitz du 2 novembre 1941 (recherche du 21/11/2026).
return [
    'slug' => '1941-11-02-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '2 novembre 1941, Paris-Austerlitz',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1941',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Heure, lieu précis (entrée de la gare d\'Austerlitz), trains, 20 morts et une centaine de blessés ; renvoie à Le Figaro du 6 novembre 1941.',
        ],
        'wp-en-1940' => [
            'provider' => 'wikipedia-en',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'List of rail accidents (1940-1949)',
            'publisher' => 'Wikipedia',
            'author' => null,
            'published' => null,
            'locator' => '2 novembre 1941, Paris Austerlitz',
            'url' => 'https://en.wikipedia.org/wiki/List_of_rail_accidents_(1940%E2%80%931949)',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Donne 21 morts et 7 blessés ; cite Semmens 1994, p. 10.',
        ],
        'figaro-1941-11-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro, 6 novembre 1941',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1941-11-06',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k410903z.r=tamponne.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
        'semmens-1994' => [
            'provider' => 'ouvrages',
            'kind' => 'book',
            'reliability' => 'scholarly',
            'title' => 'Semmens 1994 (ouvrage cité par la liste anglaise de Wikipédia, notice complète non lue)',
            'publisher' => null,
            'author' => 'Semmens',
            'published' => '1994',
            'locator' => 'p. 10',
            'url' => 'https://en.wikipedia.org/wiki/List_of_rail_accidents_(1940%E2%80%931949)',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Livre cité pour 21 morts et 7 blessés ; référence complète à vérifier.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 2 novembre 1941, vers 20 h, à l\'entrée de la gare de Paris-Austerlitz, une rame vide en manœuvre est percutée par un train bondé venu d\'Orléans.',
                'en' => 'On 2 November 1941, at about 8 pm, at the entrance to Paris-Austerlitz station, an empty set of coaches being shunted was struck by a crowded train from Orléans.',
                'de' => 'Am 2. November 1941 gegen 20 Uhr wurde an der Einfahrt zum Bahnhof Paris-Austerlitz eine leere, rangierende Wagengarnitur von einem überfüllten Zug aus Orléans gerammt.',
                'sources' => [
                    'wp-liste-1940',
                    'wp-en-1940',
                ],
            ],
        ],
        'course' => [
            [
                'fr' => 'Cinq voitures du train venu d\'Orléans sortent des voies et sont brisées.',
                'en' => 'Five coaches of the train from Orléans left the rails and were smashed.',
                'de' => 'Fünf Wagen des Zuges aus Orléans verließen die Gleise und wurden zertrümmert.',
                'sources' => [
                    'wp-liste-1940',
                ],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste française de Wikipédia donne vingt morts et une centaine de blessés.',
                'en' => 'Wikipedia\'s French list gives twenty dead and about a hundred injured.',
                'de' => 'Die französische Wikipedia-Liste nennt zwanzig Tote und rund hundert Verletzte.',
                'sources' => [
                    'wp-liste-1940',
                ],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : 20 morts et une centaine de blessés pour la liste française de Wikipédia, 21 morts et 7 blessés pour la liste anglaise, qui cite un ouvrage de 1994. La cause n\'est précisée dans aucune des deux pages lues.',
                'en' => 'The toll differs: 20 dead and about a hundred injured in Wikipedia\'s French list, 21 dead and 7 injured in the English list, which cites a 1994 book. Neither page read states a cause.',
                'de' => 'Die Bilanz weicht ab: 20 Tote und rund hundert Verletzte in der französischen Wikipedia-Liste, 21 Tote und 7 Verletzte in der englischen Liste, die ein Buch von 1994 zitiert. Keine der beiden gelesenen Seiten nennt eine Ursache.',
                'sources' => [
                    'wp-liste-1940',
                    'wp-en-1940',
                ],
            ],
        ],
    ],
];
