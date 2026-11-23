<?php

// Contexte sourcé de l'accident de Meung-sur-Loire du 5 décembre 1918 (recherche du 21/11/2026).
return [
    'slug' => '1918-12-05-meung-sur-loire',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1918' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1918',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '5 décembre, Meung-sur-Loire',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1918&oldid=238612092#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Soir, ligne Paris-Tours, express d\'Orléans contre un train de matériel américain, 20 morts et une trentaine de blessés ; renvoie à Le Temps du 8 décembre 1918, p. 3 (sans adresse).',
        ],
        'kb-1918-12-07-reuter' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Een treinbotsing',
            'publisher' => 'Journal néerlandais numérisé (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1918-12-07',
            'locator' => 'article « Een treinbotsing », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB23:001520117:mpeg21:a00025:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche Reuter du lendemain : express d\'Orléans contre un train chargé de marchandises américaines à environ 300 m de la gare, quatre voitures détruites, 10 morts identifiés, 25 blessés, d\'autres victimes sous les débris. Presse étrangère lointaine, brève, titre du journal non relevé.',
            'facts' => ['date' => '1918-12-05', 'commune_insee' => '45203', 'deaths' => 10, 'deaths_provisional' => true],
        ],
        'gron-1918-12-07-spoorweg' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Laatste berichten',
            'publisher' => 'Journal néerlandais, Groninger Archieven (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1918-12-07',
            'locator' => 'rubrique « Laatste berichten », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMRHCG04:235129071:mpeg21:a00016:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche, avec « vivres américains » au lieu de « marchandises américaines » ; 4 voitures brisées, 25 blessés, 10 morts identifiés. Brève de presse étrangère, sans enquête.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 5 décembre 1918, dans la soirée, sur la ligne de Paris à Tours, un express venant d\'Orléans percute en gare de Meung-sur-Loire un train de matériel américain à l\'arrêt.',
                'en' => 'On the evening of 5 December 1918, on the Paris-Tours line, an express from Orléans ran into a stationary train of American equipment at Meung-sur-Loire station.',
                'de' => 'Am Abend des 5. Dezember 1918 fuhr auf der Strecke Paris-Tours ein Schnellzug aus Orléans im Bahnhof Meung-sur-Loire auf einen haltenden Zug mit amerikanischem Material auf.',
                'sources' => [
                    'wp-liste-1918',
                ],
            ],
            [
                'fr' => 'Une dépêche d\'Orléans du 6 décembre 1918, reprise par la presse néerlandaise, situe la collision la veille au soir, à environ 300 mètres de la gare de Meung-sur-Loire : l\'express d\'Orléans a heurté un train chargé de marchandises américaines.',
                'en' => 'A dispatch from Orléans dated 6 December 1918, picked up by the Dutch press, places the collision on the previous evening, about 300 metres from Meung-sur-Loire station: the Orléans express ran into a train loaded with American goods.',
                'de' => 'Eine Depesche aus Orléans vom 6. Dezember 1918, von der niederländischen Presse übernommen, verlegt den Zusammenstoß auf den Vorabend, etwa 300 Meter vor den Bahnhof Meung-sur-Loire: Der Schnellzug aus Orléans war auf einen Zug mit amerikanischen Gütern aufgefahren.',
                'sources' => ['kb-1918-12-07-reuter', 'gron-1918-12-07-spoorweg'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Quatre voitures de l\'express sont détruites.',
                'en' => 'Four coaches of the express were destroyed.',
                'de' => 'Vier Wagen des Schnellzugs wurden zerstört.',
                'sources' => [
                    'wp-liste-1918',
                ],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait vingt morts et une trentaine de blessés.',
                'en' => 'The accident left twenty dead and about thirty injured.',
                'de' => 'Der Unfall forderte zwanzig Tote und etwa dreißig Verletzte.',
                'sources' => [
                    'wp-liste-1918',
                ],
            ],
            [
                'fr' => 'Les premières dépêches comptent dix morts identifiés et vingt-cinq blessés, et précisent que d\'autres victimes restaient prisonnières des débris. Le bilan de vingt morts de la liste de Wikipédia ne figure pas dans ces textes.',
                'en' => 'The first dispatches count ten dead identified and twenty-five injured, and add that other victims were still trapped in the wreckage. The toll of twenty dead given in the Wikipedia list does not appear in these texts.',
                'de' => 'Die ersten Depeschen nennen zehn identifizierte Tote und fünfundzwanzig Verletzte und fügen hinzu, dass weitere Opfer noch unter den Trümmern eingeklemmt waren. Die Zahl von zwanzig Toten aus der Wikipedia-Liste steht nicht in diesen Texten.',
                'sources' => ['kb-1918-12-07-reuter', 'gron-1918-12-07-spoorweg'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Lieu et trains : la liste de Wikipédia parle d\'un train de matériel américain à l\'arrêt en gare ; les dépêches du 6 décembre situent le choc à 300 mètres de la gare et parlent de marchandises ou de vivres américains. Ces dépêches sont des brèves de presse étrangère, écrites à chaud ; elles n\'ont pas valeur d\'enquête.',
                'en' => 'Place and trains: the Wikipedia list speaks of a train of American equipment standing in the station; the dispatches of 6 December put the crash 300 metres from the station and speak of American goods or foodstuffs. These dispatches are short foreign press items written in the heat of the moment and carry no investigative weight.',
                'de' => 'Ort und Züge: Die Wikipedia-Liste spricht von einem im Bahnhof haltenden Zug mit amerikanischem Material; die Depeschen vom 6. Dezember verlegen den Zusammenstoß 300 Meter vor den Bahnhof und sprechen von amerikanischen Gütern oder Lebensmitteln. Diese Depeschen sind kurze, hastig geschriebene Meldungen der ausländischen Presse ohne Ermittlungswert.',
                'sources' => ['wp-liste-1918', 'kb-1918-12-07-reuter', 'gron-1918-12-07-spoorweg'],
            ],
        ],
    ],
];
