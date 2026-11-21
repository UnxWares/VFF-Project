<?php

// Contexte sourcé du déraillement d'un train de marchandises à l'entrée de la gare du Gâvre, ligne de Sablé à Montoir-de-Bretagne, de mars 1911 (recherche du 21/11/2026).
return [
    'slug' => '1911-03-14-le-gavre',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1911' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1911',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Mars',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1911&oldid=241541118#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 14 mars 1911, d\'après Le Matin du 15 mars : à l\'entrée de la gare du Gâvre, sur la ligne de Sablé à Saint-Nazaire, un train de marchandises pour Sablé déraille vers 15 h 15 sur un aiguillage ; le conducteur du train et la femme du chef de gare sont tués.',
        ],
        'ddb-1911-03-15-harburg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Nantes, 15. März',
            'publisher' => 'Harburger Anzeigen und Nachrichten (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1911-03-15',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/HNCILHA4Z273I4JBKLQTGLUJF6USRT2J?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Nantes du 15 mars : hier après-midi, près de la station du Gâvre, sur le réseau de l\'État, un train de marchandises a déraillé ; deux personnes tuées, une blessée.',
        ],
        'ddb-1911-03-16-bergedorf' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Nantes, 15. März',
            'publisher' => 'Bergedorfer Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1911-03-16',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/32QYXH32W6KKNQRQLEDTIKZH3YJT4QHF?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche de Nantes, reprise aussi par les Hamburger neueste Nachrichten du 16 mars : déraillement d\'un train de marchandises près de la station du Gâvre, deux morts, un blessé.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 14 mars 1911, vers 15 h 15, un train de marchandises allant à Sablé arrivait en gare du Gâvre, sur la ligne de Sablé à Saint-Nazaire du réseau de l\'État.',
                'en' => 'On 14 March 1911, at about 3.15 pm, a goods train bound for Sablé was entering Le Gâvre station on the State railways\' Sablé to Saint-Nazaire line.',
                'de' => 'Am 14. März 1911 gegen 15.15 Uhr fuhr ein Güterzug nach Sablé in den Bahnhof Le Gâvre an der Staatsbahnstrecke Sablé-Saint-Nazaire ein.',
                'sources' => ['wp-liste-1911', 'ddb-1911-03-15-harburg'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train a déraillé sur un aiguillage à l\'entrée de la gare.',
                'en' => 'The train derailed on a set of points at the station entrance.',
                'de' => 'Der Zug entgleiste an einer Weiche in der Bahnhofseinfahrt.',
                'sources' => ['wp-liste-1911', 'ddb-1911-03-15-harburg', 'ddb-1911-03-16-bergedorf'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux personnes ont été tuées, le conducteur du train et la femme du chef de gare selon la liste de Wikipédia. La dépêche de Nantes signale aussi un blessé.',
                'en' => 'Two people were killed, the train guard and the stationmaster\'s wife according to the Wikipedia list. The Nantes dispatch also reports one injured.',
                'de' => 'Zwei Personen wurden getötet, nach der Wikipedia-Liste der Zugschaffner und die Frau des Bahnhofsvorstehers. Die Depesche aus Nantes meldet außerdem einen Verletzten.',
                'sources' => ['ddb-1911-03-15-harburg', 'ddb-1911-03-16-bergedorf', 'wp-liste-1911'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1911', 'ddb-1911-03-15-harburg', 'ddb-1911-03-16-bergedorf'],
            'fr' => 'Deux morts selon la liste de Wikipédia (Le Matin du 15 mars 1911) et la dépêche de Nantes du 15 mars ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two dead according to the Wikipedia list (Le Matin of 15 March 1911) and the Nantes dispatch of 15 March; the record gave no number of deaths.',
            'de' => 'Zwei Tote nach der Wikipedia-Liste (Le Matin vom 15. März 1911) und der Depesche aus Nantes vom 15. März; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
