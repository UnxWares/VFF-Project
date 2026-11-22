<?php

// Contexte sourcé du déraillement de la gare Saint-Lazare du 2 mars 1924 (recherche du 21/11/2026).
return [
    'slug' => '1924-03-02-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1924' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1924',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Mars',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1924&oldid=233501493#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 2 mars, vers minuit, train pour Rueil quittant la gare Saint-Lazare, aiguillage manoeuvré par erreur, avant-dernière voiture à impériale déraillée, 2 morts et une quinzaine de blessés.',
        ],
        'delpher-1924-03-03a' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenland. Frankrijk. Spoorwegongeluk te Parijs. Twee dooden en vijftien gewonden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1924-03-03',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010234124:mpeg21:a0069',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article du 3 mars : train de banlieue déraillé au départ de la gare Saint-Lazare, erreur d\'accouplement du avant-dernier wagon, 2 morts et 15 blessés dont 3 graves, un aiguilleur arrêté.',
        ],
        'delpher-1924-03-03b' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Spoorwegongeluk met een buurttrein bij Parijs. Twee dooden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1924-03-03',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010657435:mpeg21:a0167',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche datée du 1er mars : mauvaise position d\'un aiguillage, dernière voiture déraillée, 2 morts et 15 blessés dont 2 graves.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Vers minuit, dans la nuit du 1er au 2 mars 1924, un train de banlieue pour Rueil quittait la gare Saint-Lazare, à Paris, quand une voiture a déraillé. La dépêche néerlandaise est datée du 1er mars, la liste de Wikipédia donne le 2.',
                'en' => 'At about midnight, in the night of 1 to 2 March 1924, a suburban train for Rueil was leaving the Gare Saint-Lazare in Paris when a coach derailed. The Dutch dispatch is dated 1 March, the Wikipedia list gives the 2nd.',
                'de' => 'Gegen Mitternacht, in der Nacht vom 1. auf den 2. März 1924, verließ ein Vorortzug nach Rueil den Pariser Gare Saint-Lazare, als ein Wagen entgleiste. Die niederländische Meldung ist auf den 1. März datiert, die Wikipedia-Liste nennt den 2.',
                'sources' => ['delpher-1924-03-03b', 'delpher-1924-03-03a', 'wp-liste-1924'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Une voiture d\'un modèle ancien à impériale, l\'avant-dernière du train, a déraillé et s\'est couchée en se disloquant, selon la liste de Wikipédia ; la dépêche parle de la dernière voiture.',
                'en' => 'An old-type double-deck coach, the second to last of the train, derailed and fell over, breaking up, according to the Wikipedia list; the dispatch speaks of the last coach.',
                'de' => 'Ein altes Doppelstockwagenmodell, der vorletzte Wagen des Zuges, entgleiste laut Wikipedia-Liste und kippte, wobei er auseinanderbrach; die Meldung spricht vom letzten Wagen.',
                'sources' => ['wp-liste-1924', 'delpher-1924-03-03b'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les dépêches mentionnent une mauvaise position d\'aiguillage ou une erreur d\'accouplement de l\'avant-dernière voiture ; la liste de Wikipédia parle de la manoeuvre intempestive d\'un aiguillage. Un aiguilleur soupçonné de faute a été arrêté par la justice, arrivée aussitôt sur place.',
                'en' => 'The dispatches mention a wrongly set set of points or a coupling error on the second-to-last coach; the Wikipedia list speaks of an ill-timed operation of a set of points. A signalman suspected of fault was arrested by the judicial authorities, who arrived at once.',
                'de' => 'Die Meldungen nennen eine falsche Weichenstellung oder einen Kupplungsfehler am vorletzten Wagen; die Wikipedia-Liste spricht von einer unzeitigen Weichenbedienung. Ein der Schuld verdächtigter Stellwerkswärter wurde von der sofort eingetroffenen Justiz festgenommen.',
                'sources' => ['delpher-1924-03-03a', 'delpher-1924-03-03b', 'wp-liste-1924'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux personnes sont mortes et une quinzaine ont été blessées, dont deux ou trois grièvement.',
                'en' => 'Two people died and about fifteen were injured, two or three of them seriously.',
                'de' => 'Zwei Menschen starben, und etwa fünfzehn wurden verletzt, darunter zwei oder drei schwer.',
                'sources' => ['delpher-1924-03-03a', 'delpher-1924-03-03b', 'wp-liste-1924'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '75056',
            'sources' => ['wp-liste-1924', 'delpher-1924-03-03a'],
            'fr' => 'L\'accident s\'est produit au départ de la gare Saint-Lazare, à Paris, et non à Rueil-Malmaison, destination du train.',
            'en' => 'The accident happened on departure from the Gare Saint-Lazare in Paris, not at Rueil-Malmaison, the train\'s destination.',
            'de' => 'Der Unfall ereignete sich bei der Abfahrt aus dem Gare Saint-Lazare in Paris, nicht in Rueil-Malmaison, dem Ziel des Zuges.',
        ],
        [
            'field' => 'injuries',
            'value' => 15,
            'sources' => ['wp-liste-1924', 'delpher-1924-03-03a', 'delpher-1924-03-03b'],
            'fr' => 'Quinze blessés selon les deux dépêches et une quinzaine selon la liste de Wikipédia.',
            'en' => 'Fifteen injured according to both dispatches and about fifteen according to the Wikipedia list.',
            'de' => 'Fünfzehn Verletzte laut beiden Meldungen und etwa fünfzehn laut Wikipedia-Liste.',
        ],
    ],
];
