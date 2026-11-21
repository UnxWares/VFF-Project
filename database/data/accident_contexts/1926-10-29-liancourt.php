<?php

// Contexte sourcé de la collision de Liancourt du 29 octobre 1926 (recherche du 21/11/2026).
return [
    'slug' => '1926-10-29-liancourt',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1926' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1926',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1926&oldid=233501524#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 29 octobre vers 20 h 15, à Liancourt, sur voie unique temporaire, la locomotive du train Boulogne-Paris renverse le wagon-restaurant du train Paris-Lille, 1 mort et 12 blessés.',
        ],
        'delpher-1926-10-30' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Spoorwegongeluk. Een doode',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1926-10-30',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010659836:mpeg21:a0159',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêches du 29 et du 30 octobre : train de Boulogne pris en écharpe par un train Paris-Lille près de Creil, 1 mort et 12 blessés, puis 2 morts, 14 blessés graves et une trentaine de légers, voie unique, cause inconnue.',
        ],
        'delpher-1926-11-02' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Het spoorwegongeluk bij Creil. Twee dooden, 44 gewonden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1926-11-02',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMGAVL01:000014084:mpeg21:a0004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : accident de Liancourt sur voie unique, 2 morts, 14 blessés graves et 30 légers.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le vendredi 29 octobre 1926 dans la soirée, à Liancourt, près de Creil, sur une ligne exploitée à voie unique (voie temporaire selon la liste de Wikipédia), un train de voyageurs venant de Boulogne a été heurté sur le flanc par un train allant de Paris à Lille.',
                'en' => 'On the evening of Friday 29 October 1926, at Liancourt, near Creil, on a line worked as single track (a temporary track according to the Wikipedia list), a passenger train from Boulogne was struck on its side by a train running from Paris to Lille.',
                'de' => 'Am Abend des Freitags, 29. Oktober 1926, wurde in Liancourt bei Creil auf einer eingleisig betriebenen Strecke (laut Wikipedia-Liste ein provisorisches Gleis) ein Reisezug aus Boulogne seitlich von einem Zug von Paris nach Lille gerammt.',
                'sources' => ['delpher-1926-10-30', 'delpher-1926-11-02', 'wp-liste-1926'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, la voie unique avait été établie à la suite du déraillement d\'un wagon, et la locomotive du train Boulogne-Paris a pris en écharpe et renversé le wagon-restaurant du train Paris-Lille.',
                'en' => 'According to the Wikipedia list, the single track had been set up after a wagon derailment, and the locomotive of the Boulogne-Paris train sideswiped and overturned the dining car of the Paris-Lille train.',
                'de' => 'Laut Wikipedia-Liste war das eingleisige Gleis nach der Entgleisung eines Wagens eingerichtet worden, und die Lokomotive des Zuges Boulogne-Paris streifte den Speisewagen des Zuges Paris-Lille und warf ihn um.',
                'sources' => ['wp-liste-1926'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause était inconnue selon la dépêche du 29 octobre.',
                'en' => 'The cause was unknown according to the dispatch of 29 October.',
                'de' => 'Die Ursache war laut Meldung vom 29. Oktober unbekannt.',
                'sources' => ['delpher-1926-10-30'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les premières informations donnent un mort et douze blessés, comme la liste de Wikipédia. Les dépêches du 30 octobre et du 2 novembre donnent ensuite deux morts, quatorze blessés graves et une trentaine de blessés légers, soit quarante-quatre blessés.',
                'en' => 'The first reports give one dead and twelve injured, like the Wikipedia list. The dispatches of 30 October and 2 November then give two dead, fourteen seriously injured and about thirty slightly injured, forty-four injured in all.',
                'de' => 'Die ersten Meldungen nennen einen Toten und zwölf Verletzte, wie die Wikipedia-Liste. Die Meldungen vom 30. Oktober und 2. November nennen dann zwei Tote, vierzehn Schwerverletzte und etwa dreißig Leichtverletzte, insgesamt vierundvierzig Verletzte.',
                'sources' => ['delpher-1926-10-30', 'delpher-1926-11-02', 'wp-liste-1926'],
            ],
        ],
    ],
    'corrections' => [],
];
