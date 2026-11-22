<?php

// Contexte sourcé du tamponnement de Pessac du 18 mars 1941 (recherche du 21/11/2026).
return [
    'slug' => '1941-03-18-pessac',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1941',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1941',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : vers 22 heures, omnibus en retard venant de Dax à l\'arrêt en gare de Pessac, train de marchandises à l\'arrière, 4 morts et une vingtaine de blessés.',
        ],
        'delpher-1941-03-20a' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het spoorwegongeluk bij Bordeaux. Vier dooden en dertig gewonden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1941-03-20',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:011062487:mpeg21:a0035',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 20 mars 1941 : train de voyageurs entré à Pessac avec 20 minutes de retard, train de marchandises derrière lui, 4 morts et 30 blessés, premiers secours allemands.',
        ],
        'delpher-1941-03-20b' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk bij Bordeaux. Twee treinen op elkaar gereden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1941-03-20',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010019570:mpeg21:a0012',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche : fourgon et voiture de troisième classe écrasés par la locomotive du train de marchandises, blessés évacués par des voitures de la Croix-Rouge allemande.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 18 mars 1941, vers 22 heures selon la liste de Wikipédia, un train de voyageurs venant de Dax, entré en gare de Pessac avec 20 minutes de retard, était à l\'arrêt quand un train de marchandises l\'a percuté par l\'arrière.',
                'en' => 'On 18 March 1941, at about 10 p.m. according to the Wikipedia list, a passenger train from Dax that had entered Pessac station 20 minutes late was standing when a goods train ran into it from behind.',
                'de' => 'Am 18. März 1941, laut Wikipedia-Liste gegen 22 Uhr, stand ein aus Dax kommender Reisezug, der mit 20 Minuten Verspätung in den Bahnhof Pessac eingefahren war, als ein Güterzug auf ihn auffuhr.',
                'sources' => ['wp-liste-1940', 'delpher-1941-03-20a'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive du train de marchandises a écrasé l\'un contre l\'autre le fourgon et une voiture de troisième classe du train de voyageurs.',
                'en' => 'The locomotive of the goods train crushed the luggage van and a third-class coach of the passenger train into each other.',
                'de' => 'Die Lokomotive des Güterzugs drückte den Gepäckwagen und einen Wagen dritter Klasse des Reisezugs ineinander.',
                'sources' => ['delpher-1941-03-20a', 'delpher-1941-03-20b'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte quatre morts et une vingtaine de blessés ; les dépêches néerlandaises du 20 mars comptent quatre morts et trente blessés.',
                'en' => 'The Wikipedia list counts four dead and about twenty injured; the Dutch reports of 20 March count four dead and thirty injured.',
                'de' => 'Die Wikipedia-Liste zählt vier Tote und etwa zwanzig Verletzte; die niederländischen Meldungen vom 20. März vier Tote und dreißig Verletzte.',
                'sources' => ['wp-liste-1940', 'delpher-1941-03-20a', 'delpher-1941-03-20b'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Des soldats allemands ont apporté les premiers secours, et les blessés ont été conduits dans les hôpitaux de Bordeaux par des voitures de la Croix-Rouge de la Wehrmacht, selon les dépêches néerlandaises.',
                'en' => 'German soldiers gave first aid, and the injured were taken to Bordeaux hospitals by Wehrmacht Red Cross vehicles, according to the Dutch reports.',
                'de' => 'Deutsche Soldaten leisteten Erste Hilfe, und die Verletzten wurden laut den niederländischen Meldungen mit Fahrzeugen des Roten Kreuzes der Wehrmacht in Krankenhäuser von Bordeaux gebracht.',
                'sources' => ['delpher-1941-03-20a', 'delpher-1941-03-20b'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère : une vingtaine selon la liste de Wikipédia, trente selon les dépêches néerlandaises.',
                'en' => 'The number of injured differs: about twenty according to the Wikipedia list, thirty according to the Dutch reports.',
                'de' => 'Die Zahl der Verletzten weicht ab: etwa zwanzig laut Wikipedia-Liste, dreißig laut den niederländischen Meldungen.',
                'sources' => ['wp-liste-1940', 'delpher-1941-03-20a'],
            ],
        ],
    ],
    'corrections' => [],
];
