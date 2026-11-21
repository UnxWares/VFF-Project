<?php

// Contexte sourcé de la collision de deux trains de marchandises en gare de Novion-Porcien, ligne d'Hirson à Amagne-Lucquy, de novembre 1906 (recherche du 21/11/2026).
return [
    'slug' => '1906-11-09-novion-porcien',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1906' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1906',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1906&oldid=233590740#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 9 novembre 1906, d\'après Le Petit Parisien du 10 novembre : sur la ligne d\'Hirson à Amagne-Lucquy, à 6 h 15, un train de marchandises venant d\'Hirson prend en écharpe un autre train de marchandises qui manœuvrait en gare de Novion-Porcien ; la locomotive du train tamponneur déraille et son chauffeur est tué.',
        ],
        'ddb-1906-11-15-beobachter' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 10. Nov.',
            'publisher' => 'Badischer Beobachter (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1906-11-15',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/A6W2A4ZOKGRNAYHJP7ZAC7L6LCBGELWU?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 10 novembre : hier, en gare de Novion, un train de marchandises est entré en collision avec un autre ; un chauffeur tué, dégâts importants.',
        ],
        'ddb-1906-11-13-volksblatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugzusammenstoß',
            'publisher' => 'Volksblatt, Halle (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1906-11-13',
            'locator' => 'p. 7',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/23D6HYRQ6EYISISHBM6YC2ITLQZJAE7B?issuepage=7',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : en gare de Novion, un train de marchandises a heurté un autre train ; le chauffeur de l\'un des trains tué, dégâts importants.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 9 novembre 1906, à 6 h 15, un train de marchandises venant d\'Hirson a pris en écharpe un autre train de marchandises qui manœuvrait en gare de Novion-Porcien, sur la ligne d\'Hirson à Amagne-Lucquy. La locomotive du train tamponneur a déraillé. Les dégâts ont été importants.',
                'en' => 'On 9 November 1906, at 6.15 am, a goods train from Hirson struck another goods train side-on while it was shunting at Novion-Porcien station on the Hirson to Amagne-Lucquy line. The engine of the colliding train derailed. The damage was heavy.',
                'de' => 'Am 9. November 1906 um 6.15 Uhr rammte ein Güterzug aus Hirson im Bahnhof Novion-Porcien an der Strecke Hirson-Amagne-Lucquy seitlich einen anderen, rangierenden Güterzug. Die Lokomotive des auffahrenden Zuges entgleiste. Der Sachschaden war bedeutend.',
                'sources' => ['wp-liste-1906', 'ddb-1906-11-15-beobachter'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur de la locomotive du train tamponneur a été tué.',
                'en' => 'The stoker of the colliding train\'s engine was killed.',
                'de' => 'Der Heizer der Lokomotive des auffahrenden Zuges wurde getötet.',
                'sources' => ['wp-liste-1906', 'ddb-1906-11-15-beobachter', 'ddb-1906-11-13-volksblatt'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1906', 'ddb-1906-11-15-beobachter', 'ddb-1906-11-13-volksblatt'],
            'fr' => 'Un chauffeur tué selon la liste de Wikipédia (Le Petit Parisien du 10 novembre 1906) et la dépêche de Paris du 10 novembre ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One stoker killed according to the Wikipedia list (Le Petit Parisien of 10 November 1906) and the Paris dispatch of 10 November; the record gave no number of deaths.',
            'de' => 'Ein Heizer getötet nach der Wikipedia-Liste (Le Petit Parisien vom 10. November 1906) und der Pariser Depesche vom 10. November; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
