<?php

// Contexte sourcé de l'accident de Castelnau-d'Estrétefonds du 4 septembre 1919 (recherche du 21/11/2026).
return [
    'slug' => '1919-09-04-castelnau-destretefonds',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '4 septembre, Castelnau-d\'Estrétefonds / Saint-Jory',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Heure, tronçon, trains, 20 morts et 40 blessés ; renvoie à Le Petit Parisien du 6 septembre 1919.',
        ],
        'pp-1919-09-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien, 6 septembre 1919',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1919-09-06',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k567009s/f3.image.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
        'kb-1919-09-06-expres' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinongeluk en Frankrijk (dépêche de Paris, 5 septembre 1919)',
            'publisher' => 'Journal néerlandais numérisé (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1919-09-06',
            'locator' => 'article « Frankrijk. Treinongeluk », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB04:000191711:mpeg21:a0004:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche : l\'express Bordeaux-Sète et un train venant de Paris se sont heurtés dans la nuit à Saint-Jory près de Toulouse ; premier bilan 9 morts et 30 blessés. Brève de presse étrangère, titre du journal non relevé.',
        ],
        'kb-1919-09-06-bordeaux' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorweg-ongeluk (dépêche de Paris, 5 septembre 1919)',
            'publisher' => 'Journal néerlandais numérisé (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1919-09-06',
            'locator' => 'article « Spoorweg-ongeluk », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010993338:mpeg21:a0099:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle : express de Bordeaux contre le train de Paris près de Saint-Jory, 9 morts et 30 blessés à ce stade. Indique le sens du choc (le train de Bordeaux heurte celui de Paris).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 4 septembre 1919, vers 23 h, sur la ligne de Montauban à Toulouse, entre les gares de Castelnau-d\'Estrétefonds et de Saint-Jory, l\'express Paris-Toulouse roule au pas à cause d\'une panne.',
                'en' => 'On 4 September 1919, at about 11 pm, on the Montauban-Toulouse line between Castelnau-d\'Estrétefonds and Saint-Jory stations, the Paris-Toulouse express was crawling along because of a breakdown.',
                'de' => 'Am 4. September 1919 gegen 23 Uhr fuhr der Schnellzug Paris-Toulouse auf der Strecke Montauban-Toulouse zwischen den Bahnhöfen Castelnau-d\'Estrétefonds und Saint-Jory wegen einer Panne im Schritttempo.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
            [
                'fr' => 'Les dépêches du 5 septembre 1919 publiées par la presse néerlandaise situent la collision dans la nuit, près de Saint-Jory, au nord de Toulouse : l\'express Bordeaux-Sète s\'est heurté à un train venant de Paris. Elles ne mentionnent pas Castelnau-d\'Estrétefonds.',
                'en' => 'Dispatches of 5 September 1919 published by the Dutch press place the collision during the night near Saint-Jory, north of Toulouse: the Bordeaux-Sète express ran into a train coming from Paris. They do not mention Castelnau-d\'Estrétefonds.',
                'de' => 'Depeschen vom 5. September 1919 in der niederländischen Presse verlegen den Zusammenstoß in die Nacht, nahe Saint-Jory nördlich von Toulouse: Der Schnellzug Bordeaux-Sète war auf einen Zug aus Paris aufgefahren. Castelnau-d\'Estrétefonds wird nicht erwähnt.',
                'sources' => ['kb-1919-09-06-expres', 'kb-1919-09-06-bordeaux'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Il est percuté par un express venu de Bordeaux, remorqué en double traction. Ce dernier détruit les trois dernières voitures du train tamponné.',
                'en' => 'It was struck by an express from Bordeaux, double-headed. The latter destroyed the last three coaches of the struck train.',
                'de' => 'Er wurde von einem Schnellzug aus Bordeaux gerammt, der doppelt bespannt war. Dieser zerstörte die letzten drei Wagen des gerammten Zuges.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de 20 morts et 40 blessés.',
                'en' => 'The toll is 20 dead and 40 injured.',
                'de' => 'Die Bilanz beträgt 20 Tote und 40 Verletzte.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
            [
                'fr' => 'Le premier bilan transmis le 5 septembre est de neuf morts et trente blessés ; il est inférieur au bilan final de vingt morts et quarante blessés donné par la liste de Wikipédia.',
                'en' => 'The first toll sent on 5 September is nine dead and thirty injured; it is lower than the toll of twenty dead and forty injured given by the Wikipedia list.',
                'de' => 'Die erste am 5. September gemeldete Bilanz lautet neun Tote und dreißig Verletzte; sie liegt unter der Bilanz von zwanzig Toten und vierzig Verletzten, die die Wikipedia-Liste nennt.',
                'sources' => ['kb-1919-09-06-expres', 'kb-1919-09-06-bordeaux'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Lieu exact : la liste de Wikipédia place le choc entre les gares de Castelnau-d\'Estrétefonds et de Saint-Jory ; la presse de l\'époque lue ne cite que Saint-Jory. Aucun document lu ne permet de dire dans quelle commune se trouve le point de choc. Bilan : 9 morts et 30 blessés dans les premières dépêches, 20 et 40 dans la liste. Aucune des deux sources n\'est un rapport officiel.',
                'en' => 'Exact place: the Wikipedia list puts the crash between Castelnau-d\'Estrétefonds and Saint-Jory stations; the period press read here names only Saint-Jory. No document read shows in which municipality the point of impact lay. Toll: 9 dead and 30 injured in the first dispatches, 20 and 40 in the list. Neither source is an official report.',
                'de' => 'Genauer Ort: Die Wikipedia-Liste verlegt den Zusammenstoß zwischen die Bahnhöfe Castelnau-d\'Estrétefonds und Saint-Jory; die hier gelesene zeitgenössische Presse nennt nur Saint-Jory. Kein gelesenes Dokument zeigt, in welcher Gemeinde die Unfallstelle lag. Bilanz: 9 Tote und 30 Verletzte in den ersten Depeschen, 20 und 40 in der Liste. Keine der beiden Quellen ist ein amtlicher Bericht.',
                'sources' => ['wp-liste-1919', 'kb-1919-09-06-expres', 'kb-1919-09-06-bordeaux'],
            ],
        ],
    ],
];
