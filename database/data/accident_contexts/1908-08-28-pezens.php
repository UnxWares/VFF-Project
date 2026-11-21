<?php

// Contexte sourcé de la collision du 28 août 1908 à Pezens, près de Carcassonne (recherche du 21/11/2026).
return [
    'slug' => '1908-08-28-pezens',
    'checked_on' => '2026-11-21',
    'sources' => [
        'ddb-1908-08-29-dortmunder' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück (dépêche de Paris du 29 août 1908)',
            'publisher' => 'Dortmunder Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1908-08-29',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/N6GPUM3Z7357CHWTDHQCOVO67KMUHQE2?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève d\'après un télégramme de Carcassonne : la veille au soir, un train rapide venant de Toulouse est entré en collision avec un train de marchandises près de la gare de la localité (nom déformé par l\'OCR) ; un ouvrier tué, 30 blessés dont plusieurs grièvement.',
        ],
        'ddb-1908-08-30-deutsche-zeitung' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Brève de Carcassonne sur une collision de trains',
            'publisher' => 'Deutsche Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1908-08-30',
            'locator' => 'p. 7',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/NSRFW7BH6WLL6XLV7IVNZGYRQ7V3XAWN?issuepage=7',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même information, venue de Carcassonne : un train express de Toulouse a heurté un train de marchandises à l\'entrée de la gare de Pezens ; un ouvrier tué, 12 blessés dont plusieurs grièvement. Le nom de la gare est lu « Penzens » par l\'OCR.',
        ],
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : sur la ligne de Toulouse à Narbonne, vers 18 h 40, un express venant de Bordeaux percute un train de marchandises arrêté en gare de Pezens, à huit kilomètres de Carcassonne ; un mort et douze blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 28 août 1908, vers 18 h 40 selon la liste de Wikipédia, un express a percuté un train de marchandises arrêté en gare de Pezens, à huit kilomètres de Carcassonne, sur la ligne de Toulouse à Narbonne.',
                'en' => 'On 28 August 1908, at about 6:40 pm according to the Wikipedia list, an express struck a goods train standing in Pezens station, eight kilometres from Carcassonne, on the Toulouse to Narbonne line.',
                'de' => 'Am 28. August 1908, nach der Wikipedia-Liste gegen 18.40 Uhr, rammte ein Schnellzug einen im Bahnhof Pezens haltenden Güterzug, acht Kilometer von Carcassonne entfernt, auf der Strecke von Toulouse nach Narbonne.',
                'sources' => ['wp-liste-1908'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Deux journaux allemands des 29 et 30 août 1908, d\'après un télégramme de Carcassonne, rapportent que la veille au soir un train rapide venant de Toulouse est entré en collision avec un train de marchandises à l\'entrée de la gare de Pezens.',
                'en' => 'Two German newspapers of 29 and 30 August 1908, citing a telegram from Carcassonne, report that the previous evening a fast train coming from Toulouse collided with a goods train at the entrance of Pezens station.',
                'de' => 'Zwei deutsche Zeitungen vom 29. und 30. August 1908 melden unter Berufung auf ein Telegramm aus Carcassonne, dass am Vorabend ein aus Toulouse kommender Eilzug am Eingang des Bahnhofs Pezens mit einem Güterzug zusammenstieß.',
                'sources' => ['ddb-1908-08-29-dortmunder', 'ddb-1908-08-30-deutsche-zeitung'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les deux journaux allemands signalent un ouvrier tué et plusieurs blessés graves, avec 30 blessés pour l\'un et 12 pour l\'autre. La liste de Wikipédia donne un mort et douze blessés.',
                'en' => 'Both German newspapers report one worker killed and several seriously injured, with 30 injured in one and 12 in the other. The Wikipedia list gives one dead and twelve injured.',
                'de' => 'Beide deutschen Zeitungen melden einen getöteten Arbeiter und mehrere Schwerverletzte, die eine mit 30, die andere mit 12 Verletzten. Die Wikipedia-Liste nennt einen Toten und zwölf Verletzte.',
                'sources' => ['ddb-1908-08-29-dortmunder', 'ddb-1908-08-30-deutsche-zeitung', 'wp-liste-1908'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère entre les deux dépêches allemandes (30 dans la Dortmunder Zeitung, 12 dans la Deutsche Zeitung) ; la liste de Wikipédia en donne douze. L\'express est dit venir de Toulouse par la presse allemande et de Bordeaux par la liste.',
                'en' => 'The number of injured differs between the two German dispatches (30 in the Dortmunder Zeitung, 12 in the Deutsche Zeitung); the Wikipedia list gives twelve. The express is said to come from Toulouse by the German press and from Bordeaux by the list.',
                'de' => 'Die Zahl der Verletzten weicht zwischen den beiden deutschen Meldungen ab (30 in der Dortmunder Zeitung, 12 in der Deutschen Zeitung); die Wikipedia-Liste nennt zwölf. Der Eilzug kommt laut deutscher Presse aus Toulouse, laut Liste aus Bordeaux.',
                'sources' => ['ddb-1908-08-29-dortmunder', 'ddb-1908-08-30-deutsche-zeitung', 'wp-liste-1908'],
            ],
        ],
    ],
];
