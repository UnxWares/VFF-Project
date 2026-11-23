<?php

// Contexte sourcé du déraillement de Taillebourg du 17 janvier 1923 (recherche du 21/11/2026).
return [
    'slug' => '1923-01-17-taillebourg',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1923' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1923',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1923&oldid=233501473#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 17 janvier, vers 20 h, près de la gare de Taillebourg, rupture d\'un rail et déraillement d\'un omnibus Nantes-Saintes, 2 morts et 5 blessés.',
        ],
        'delpher-1923-01-19' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Weder twee spoorwegongelukken',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1923-01-19',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010656436:mpeg21:a0079',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du 18 janvier : train omnibus Nantes-Bordeaux déraillé à « Aillebourg », 2 morts et 3 blessés.',
        ],
        'delpher-1923-01-22' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengde berichten',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1923-01-22',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010884278:mpeg21:a0015',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit repris du Journal : mercredi soir peu après 20 h, à 300 m de la gare, quelques voitures sorties des rails, 2 morts, essieu probablement rompu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mercredi 17 janvier 1923 peu après 20 heures, un train omnibus Nantes-Bordeaux (Nantes-Saintes selon la liste de Wikipédia), à l\'heure, a déraillé à environ 300 mètres de la gare de Taillebourg, non loin de Saintes.',
                'en' => 'On Wednesday 17 January 1923 shortly after 8 p.m., a Nantes-Bordeaux stopping train (Nantes-Saintes according to the Wikipedia list), running on time, derailed about 300 metres from Taillebourg station, not far from Saintes.',
                'de' => 'Am Mittwoch, dem 17. Januar 1923, kurz nach 20 Uhr, entgleiste ein pünktlicher Personenzug Nantes-Bordeaux (laut Wikipedia-Liste Nantes-Saintes) etwa 300 Meter vom Bahnhof Taillebourg entfernt, unweit von Saintes.',
                'sources' => ['delpher-1923-01-22', 'delpher-1923-01-19', 'wp-liste-1923'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Quelques voitures ont quitté les rails et ont été écrasées par les suivantes. D\'après le récit repris du Journal, le mécanicien n\'a rien remarqué d\'abord, puis a constaté que les freins ne répondaient plus. Selon la liste de Wikipédia, le fourgon de tête et les neuf voitures se sont renversés et télescopés.',
                'en' => 'A few coaches left the rails and were crushed by the following ones. According to the account taken from the Journal, the driver noticed nothing at first, then found that the brakes no longer worked. According to the Wikipedia list, the leading van and the nine coaches overturned and telescoped.',
                'de' => 'Einige Wagen verließen die Schienen und wurden von den folgenden zusammengedrückt. Laut dem aus dem Journal übernommenen Bericht bemerkte der Lokführer zunächst nichts und stellte dann fest, dass die Bremsen nicht mehr wirkten. Laut Wikipedia-Liste kippten der vordere Gepäckwagen und die neun Wagen um und schoben sich ineinander.',
                'sources' => ['delpher-1923-01-22', 'wp-liste-1923'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon le récit, un essieu de l\'une des premières voitures s\'était probablement rompu, ce qui aurait faussé les rails, constatés tordus après l\'accident. La liste de Wikipédia parle de la rupture d\'un rail.',
                'en' => 'According to the account, an axle of one of the first coaches had probably broken, which would have bent the rails, found twisted after the accident. The Wikipedia list speaks of a broken rail.',
                'de' => 'Laut dem Bericht war wahrscheinlich eine Achse eines der ersten Wagen gebrochen, was die Schienen verbogen haben soll, die nach dem Unfall verformt vorgefunden wurden. Die Wikipedia-Liste spricht vom Bruch einer Schiene.',
                'sources' => ['delpher-1923-01-22', 'wp-liste-1923'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux personnes sont mortes. Les blessés sont donnés pour trois (dépêche du 18 janvier), « plusieurs » (récit du 22) ou cinq (liste de Wikipédia).',
                'en' => 'Two people died. The injured are given as three (dispatch of 18 January), "several" (account of 22 January) or five (Wikipedia list).',
                'de' => 'Zwei Menschen starben. Die Verletzten werden mit drei (Meldung vom 18. Januar), „mehreren“ (Bericht vom 22.) oder fünf (Wikipedia-Liste) angegeben.',
                'sources' => ['delpher-1923-01-19', 'delpher-1923-01-22', 'wp-liste-1923'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'derailment',
            'sources' => ['wp-liste-1923', 'delpher-1923-01-19', 'delpher-1923-01-22'],
            'fr' => 'Les trois sources décrivent un déraillement d\'omnibus, dû à un rail ou à un essieu rompu, et non une défaillance d\'ouvrage.',
            'en' => 'All three sources describe a derailment of a stopping train, caused by a broken rail or axle, not a structural failure.',
            'de' => 'Alle drei Quellen beschreiben eine Entgleisung eines Personenzugs infolge einer gebrochenen Schiene oder Achse, kein Bauwerksversagen.',
        ],
    ],
];
