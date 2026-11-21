<?php

// Contexte sourcé du déraillement d'un train de marchandises à l'approche de la gare de Roanne, de mai 1898 (recherche du 21/11/2026).
return [
    'slug' => '1898-05-23-roanne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1898',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1898',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 23 mai 1898, d\'après Le Figaro du 24 mai : avant d\'arriver en gare de Roanne, un train de marchandises déraille par suite d\'une erreur d\'aiguillage ; le chauffeur est tué.',
        ],
        'ddb-1898-05-25-muelheim' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Roanne, 23. Mai',
            'publisher' => 'Mülheimer Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1898-05-25',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/W632JPZ4AANY6YMEJFKZYNCBGAXLRSQF?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Roanne du 23 mai : à la suite d\'une erreur d\'aiguillage, un train de marchandises a déraillé près de la gare ; le chauffeur a été tué ; quatre wagons, la machine et un tender ont été brisés.',
        ],
        'ddb-1898-05-26-fremdenblatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugentgleisung',
            'publisher' => 'Hamburger Fremdenblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1898-05-26',
            'locator' => 'p. 13',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/SM6M7KNQ7D6YJ5FBBSF2VCKWOIMQXEHJ?issuepage=13',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche de Roanne du 23 mai : train de marchandises déraillé près de la gare après une erreur d\'aiguillage, chauffeur tué, quatre wagons, la machine et un tender brisés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 23 mai 1898, un train de marchandises a déraillé peu avant la gare de Roanne, à la suite d\'une erreur d\'aiguillage. Quatre wagons, la machine et un tender ont été brisés.',
                'en' => 'On 23 May 1898 a goods train derailed shortly before Roanne station after a points error. Four wagons, the engine and a tender were wrecked.',
                'de' => 'Am 23. Mai 1898 entgleiste kurz vor dem Bahnhof Roanne ein Güterzug infolge eines Fehlers beim Stellen der Weiche. Vier Wagen, die Lokomotive und ein Tender wurden zertrümmert.',
                'sources' => ['wp-liste-1890', 'ddb-1898-05-25-muelheim', 'ddb-1898-05-26-fremdenblatt'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur a été tué.',
                'en' => 'The stoker was killed.',
                'de' => 'Der Heizer wurde getötet.',
                'sources' => ['wp-liste-1890', 'ddb-1898-05-25-muelheim', 'ddb-1898-05-26-fremdenblatt'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1898-05-25-muelheim'],
            'fr' => 'Chauffeur tué selon la liste de Wikipédia (Le Figaro du 24 mai 1898) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Stoker killed according to the Wikipedia list (Le Figaro of 24 May 1898) and the German press; the record gave no number of deaths.',
            'de' => 'Heizer getötet nach der Wikipedia-Liste (Le Figaro vom 24. Mai 1898) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
