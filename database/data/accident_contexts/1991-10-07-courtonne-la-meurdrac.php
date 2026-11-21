<?php

// Contexte sourcé de la collision du turbotrain avec un camion de chantier au passage à niveau de Courtonne-la-Meurdrac du 7 octobre 1991 (recherche du 21/11/2026).
return [
    'slug' => '1991-10-07-courtonne-la-meurdrac',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1990' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1990',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '1991, 7 octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1990&oldid=239974394#1991',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : collision à un passage à niveau entre la rame de turbotrain RTG 2077/2078 (Paris - Caen) et un camion du chantier d\'électrification arrêté sur la voie, 2 morts (mécanicien et contrôleur).',
        ],
        'rasserie-turbotrains' => [
            'provider' => 'ouvrages',
            'kind' => 'book',
            'reliability' => 'local_history',
            'title' => 'Les turbotrains français',
            'publisher' => 'Cabri (Breil-sur-Roya)',
            'author' => null,
            'published' => '2002',
            'locator' => null,
            'url' => 'https://www.worldcat.org/oclc/401488936',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Ouvrage d\'André Rasserie, cité par la liste de Wikipédia pour cette entrée.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 7 octobre 1991, dans le Calvados, au passage à niveau de Courtonne-la-Meurdrac, la rame de turbotrain RTG 2077/2078 assurant la liaison Paris - Caen est entrée en collision avec un camion du chantier d\'électrification de la ligne, arrêté sur la voie.',
                'en' => 'On 7 October 1991, in Calvados, at the level crossing at Courtonne-la-Meurdrac, the RTG 2077/2078 turbotrain set working the Paris to Caen service collided with a lorry from the line\'s electrification works, stopped on the track.',
                'de' => 'Am 7. Oktober 1991 stieß im Département Calvados am Bahnübergang von Courtonne-la-Meurdrac der Turbozug RTG 2077/2078 auf der Verbindung Paris - Caen mit einem auf dem Gleis stehenden Lastwagen der Elektrifizierungsbaustelle der Strecke zusammen.',
                'sources' => ['wp-liste-1990'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait deux morts, le mécanicien et le contrôleur, qui se trouvaient dans la motrice 2077.',
                'en' => 'The accident killed two people, the driver and the ticket inspector, who were in power car 2077.',
                'de' => 'Bei dem Unfall starben zwei Menschen, der Lokführer und der Zugbegleiter, die sich im Triebkopf 2077 befanden.',
                'sources' => ['wp-liste-1990'],
            ],
        ],
    ],
];
