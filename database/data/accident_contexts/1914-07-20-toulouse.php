<?php

// Contexte sourcé de la collision d'Empalot (Toulouse) du 20 juillet 1914 (recherche du 21/11/2026).
return [
    'slug' => '1914-07-20-toulouse',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1914' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1914',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '20 juillet 1914',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1914&oldid=239623136#1914',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de l\'époque (Gallica).',
        ],
        'kb-1914-07-21-nieuwsblad-van-het-noorden' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorweg-ongeluk.',
            'publisher' => 'Nieuwsblad van het Noorden (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1914-07-21',
            'locator' => 'article « Spoorweg-ongeluk. », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010667627:mpeg21:a0025:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Dépêche Reuter de Toulouse du 20 juillet : le soir même, vers 21 h, un train venant d\'Auch et un train venant de Bayonne se heurtent près du pont d\'Empalot, à 5 km de Toulouse ; six morts et trente blessés.',
            'facts' => ['date' => '1914-07-20', 'commune_insee' => '31555', 'deaths' => 6],
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 20 juillet 1914 vers 20 h, à Empalot, à cinq kilomètres de Toulouse, à la jonction des lignes de Toulouse à Bayonne et de Toulouse à Auch, un train de voyageurs venant d\'Auch était arrêté à un signal, déjà engagé sur la bifurcation.',
                'en' => 'On 20 July 1914 at about 8 pm, at Empalot, five kilometres from Toulouse, where the Toulouse to Bayonne and Toulouse to Auch lines meet, a passenger train from Auch was standing at a signal, already on the junction.',
                'de' => 'Am 20. Juli 1914 gegen 20 Uhr stand in Empalot, fünf Kilometer von Toulouse, am Treffpunkt der Strecken Toulouse-Bayonne und Toulouse-Auch, ein Personenzug aus Auch vor einem Signal, bereits auf der Abzweigung.',
                'sources' => ['wp-liste-1914'],
            ],
            [
                'fr' => 'Un train venant de Bayonne l\'a pris en écharpe à pleine vitesse par l\'arrière. La collision a fait six morts et cinquante blessés.',
                'en' => 'A train from Bayonne struck its rear side-on at full speed. The collision killed six people and injured fifty.',
                'de' => 'Ein Zug aus Bayonne rammte sein Ende mit voller Geschwindigkeit seitlich. Der Zusammenstoß forderte sechs Tote und fünfzig Verletzte.',
                'sources' => ['wp-liste-1914'],
            ],
        ],
    ],
    'corrections' => [],
];
