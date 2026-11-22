<?php

// Contexte sourcé du déraillement de l'express Paris-Toulouse du 14 mai 1921 (recherche du 21/11/2026).
return [
    'slug' => '1921-05-14-uzerche',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1921' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1921',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '14 mai 1921, Uzerche',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1921&oldid=233501430#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Place le déraillement au lieudit « le Gour noir », entre Uzerche et Vigeois, à 18 h 15 : huit voitures de l\'express Paris-Toulouse, 5 morts et une vingtaine de blessés ; mentionne aussi l\'accident du lendemain à La Bachellerie.',
        ],
        'volkskrant-1921-05-18' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De Volkskrant du 18 mai 1921',
            'publisher' => 'De Volkskrant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1921-05-18',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB12:000129057:mpeg21:a00015',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Brève « Van hier en daar » ; l\'OCR est lisible.',
        ],
        'petit-parisien-1921-05-16' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 16 mai 1921',
            'publisher' => 'Le Petit Parisien (numérisé sur Gallica)',
            'author' => null,
            'published' => '1921-05-16',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k604655s',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 14 mai 1921 au soir, l\'express de Paris à Toulouse a déraillé en Limousin, entre Uzerche et Vigeois selon la liste de Wikipédia. Le journal néerlandais du 18 mai situe le déraillement à Limoges, ce qui est probablement une erreur de la dépêche.',
                'en' => 'On the evening of Saturday 14 May 1921, the Paris to Toulouse express derailed in the Limousin, between Uzerche and Vigeois according to the Wikipedia list. The Dutch newspaper of 18 May places the derailment at Limoges, which is probably an error in the dispatch.',
                'de' => 'Am Abend des Samstags, 14. Mai 1921, entgleiste der Schnellzug Paris-Toulouse im Limousin, laut der Wikipedia-Liste zwischen Uzerche und Vigeois. Die niederländische Zeitung vom 18. Mai verlegt die Entgleisung nach Limoges, was wahrscheinlich ein Fehler der Depesche ist.',
                'sources' => ['volkskrant-1921-05-18', 'wp-liste-1921'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le journal néerlandais compte cinq morts et quinze blessés pour ce déraillement ; la liste de Wikipédia donne cinq morts et une vingtaine de blessés, surtout dans une voiture totalement détruite.',
                'en' => 'The Dutch newspaper counts five dead and fifteen injured for this derailment; the Wikipedia list gives five dead and about twenty injured, mainly in one completely destroyed carriage.',
                'de' => 'Die niederländische Zeitung zählt für diese Entgleisung fünf Tote und fünfzehn Verletzte; die Wikipedia-Liste nennt fünf Tote und etwa zwanzig Verletzte, vor allem in einem völlig zerstörten Wagen.',
                'sources' => ['volkskrant-1921-05-18', 'wp-liste-1921'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le journal néerlandais signale un second accident plus tard sur le même tronçon, avec huit morts et une cinquantaine de blessés ; la liste de Wikipédia le place le lendemain à La Bachellerie (Dordogne), l\'express ayant été détourné à la suite du déraillement de la veille.',
                'en' => 'The Dutch newspaper reports a second accident later on the same stretch, with eight dead and about fifty injured; the Wikipedia list places it the next day at La Bachellerie (Dordogne), the express having been diverted following the previous day\'s derailment.',
                'de' => 'Die niederländische Zeitung meldet einen zweiten Unfall später auf demselben Abschnitt mit acht Toten und etwa fünfzig Verletzten; die Wikipedia-Liste verlegt ihn auf den folgenden Tag nach La Bachellerie (Dordogne), da der Schnellzug nach der Entgleisung des Vortags umgeleitet worden war.',
                'sources' => ['volkskrant-1921-05-18', 'wp-liste-1921'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu est Uzerche - Vigeois dans la liste et Limoges dans la dépêche ; le nombre de blessés est de quinze ou d\'une vingtaine.',
                'en' => 'The place is Uzerche - Vigeois in the list and Limoges in the dispatch; the number of injured is fifteen or about twenty.',
                'de' => 'Der Ort ist in der Liste Uzerche - Vigeois und in der Depesche Limoges; die Zahl der Verletzten beträgt fünfzehn oder etwa zwanzig.',
                'sources' => ['volkskrant-1921-05-18', 'wp-liste-1921'],
            ],
        ],
    ],
    'corrections' => [],
];
