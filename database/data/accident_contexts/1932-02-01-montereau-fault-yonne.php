<?php

// Contexte sourcé de l'accident de l'express Paris-Milan à Montereau du 1er février 1932 (recherche du 21/11/2026).
return [
    'slug' => '1932-02-01-montereau-fault-yonne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1932' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1932',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Février',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1932&oldid=236417113#F%C3%A9vrier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 1er février, vers 9 h 15, le train Paris-Milan heurte une machine de manoeuvre à Montereau, locomotive couchée et trois voitures de tête télescopées, 2 morts (le chauffeur et un employé des Postes) et 14 blessés.',
        ],
        'delpher-1932-02-01' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Trein Parijs-Milaan ontspoord. Bij Montereau. Een doode, drie gewonden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1932-02-01',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010662298:mpeg21:a0148',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche Reuter : sneltrein Paris-Milan déraillé près de la gare de Montereau, un chauffeur tué, trois blessés dont un postier ; rappel d\'un accident dû à un sabotage au même endroit deux ans plus tôt.',
        ],
        'delpher-1932-02-02a' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Expressetrein gederailleerd',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1932-02-02',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:011023856:mpeg21:a0046',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche Aneta-Havas : express déraillé en gare de Montereau, le mécanicien tué, trois blessés, un wagon détruit.',
        ],
        'delpher-1932-02-02b' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegramp',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1932-02-02',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010283836:mpeg21:a0137',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche : le mécanicien tué, trois blessés, un wagon détruit.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le lundi 1er février 1932 au matin (vers 9 h 15 selon la liste de Wikipédia), l\'express Paris-Milan a déraillé à la gare de Montereau.',
                'en' => 'On the morning of Monday 1 February 1932 (at about 9.15 a.m. according to the Wikipedia list), the Paris-Milan express derailed at Montereau station.',
                'de' => 'Am Morgen des Montags, 1. Februar 1932 (laut Wikipedia-Liste gegen 9.15 Uhr), entgleiste der Schnellzug Paris-Mailand im Bahnhof Montereau.',
                'sources' => ['delpher-1932-02-01', 'delpher-1932-02-02a', 'delpher-1932-02-02b', 'wp-liste-1932'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le train a heurté une machine de manoeuvre : la locomotive s\'est couchée et les trois voitures de tête l\'ont télescopée. Les dépêches mentionnent un wagon détruit.',
                'en' => 'According to the Wikipedia list, the train struck a shunting engine: the locomotive overturned and the three leading coaches telescoped it. The dispatches mention one wagon destroyed.',
                'de' => 'Laut Wikipedia-Liste stieß der Zug auf eine Rangierlokomotive: Die Lokomotive kippte, und die drei vorderen Wagen schoben sich auf sie. Die Meldungen erwähnen einen zerstörten Wagen.',
                'sources' => ['wp-liste-1932', 'delpher-1932-02-02a', 'delpher-1932-02-02b'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les dépêches néerlandaises comptent un mort, le chauffeur ou le mécanicien selon les versions, et trois blessés dont un employé des Postes. La liste de Wikipédia compte deux morts, le chauffeur du rapide et un employé des Postes, et quatorze blessés.',
                'en' => 'The Dutch dispatches count one dead, the fireman or the driver depending on the version, and three injured, one of them a postal employee. The Wikipedia list counts two dead, the express fireman and a postal employee, and fourteen injured.',
                'de' => 'Die niederländischen Meldungen zählen einen Toten, je nach Fassung den Heizer oder den Lokführer, und drei Verletzte, darunter einen Postbediensteten. Die Wikipedia-Liste zählt zwei Tote, den Heizer des Schnellzugs und einen Postbediensteten, und vierzehn Verletzte.',
                'sources' => ['delpher-1932-02-01', 'delpher-1932-02-02a', 'delpher-1932-02-02b', 'wp-liste-1932'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'La dépêche du 1er février 1932 rappelle qu\'un accident dû à un sabotage avait eu lieu au même endroit deux ans plus tôt.',
                'en' => 'The dispatch of 1 February 1932 recalls that an accident caused by sabotage had occurred at the same place two years earlier.',
                'de' => 'Die Meldung vom 1. Februar 1932 erinnert daran, dass sich zwei Jahre zuvor an derselben Stelle ein durch Sabotage verursachter Unfall ereignet hatte.',
                'sources' => ['delpher-1932-02-01'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : un mort et trois blessés (dépêches), deux morts et quatorze blessés (liste de Wikipédia). Les dépêches parlent d\'un déraillement, la liste d\'un heurt contre une machine de manoeuvre.',
                'en' => 'The toll differs: one dead and three injured (dispatches), two dead and fourteen injured (Wikipedia list). The dispatches speak of a derailment, the list of a collision with a shunting engine.',
                'de' => 'Die Bilanz weicht ab: ein Toter und drei Verletzte (Meldungen), zwei Tote und vierzehn Verletzte (Wikipedia-Liste). Die Meldungen sprechen von einer Entgleisung, die Liste von einem Zusammenstoß mit einer Rangierlokomotive.',
                'sources' => ['delpher-1932-02-01', 'delpher-1932-02-02a', 'wp-liste-1932'],
            ],
        ],
    ],
    'corrections' => [],
];
