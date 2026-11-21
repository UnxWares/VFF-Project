<?php

// Contexte sourcé du déraillement du 7 décembre 1903 près de Saintes, fiche « Saintes » (recherche du 21/11/2026).
return [
    'slug' => '1903-12-07-saintes',
    'checked_on' => '2026-11-21',
    'sources' => [
        'ddb-1903-12-08-staatsbuerger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunfall (brève sur Saintes)',
            'publisher' => 'Staatsbürger-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1903-12-08',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/YAFU47YCDJ45M5374ETLEY4JW7TK7NF7?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève de quelques lignes : près de Saintes, un train de voyageurs a déraillé ; plusieurs personnes blessées, une tuée.',
        ],
        'ddb-1903-12-09-allgemeine' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugsentgleisung (dépêche de Paris)',
            'publisher' => 'Allgemeine Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1903-12-09',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/P4DUL7KECZ3DSKWAF2SW4DVZFJHJUBW5?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche de Paris : un train de voyageurs déraille près de Saintes, plusieurs voyageurs blessés et un tué. Le Berliner Morgen-Zeitung du 9 décembre 1903 (p. 2) reprend la même brève.',
        ],
        'wp-liste-1903' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1903',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1903&oldid=239810319#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : vers 11 h, sur le réseau à voie métrique des Chemins de fer économiques des Charentes, un train bondé de la ligne de Marennes à Saintes, inaugurée un mois plus tôt, déraille près de Crazannes par suite de l\'affaissement d\'un remblai sous la pluie ; un mort, trois blessés graves et des blessés légers.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Selon la liste de Wikipédia, l\'accident s\'est produit sur le réseau à voie métrique des Chemins de fer économiques des Charentes, sur la ligne de Marennes à Saintes inaugurée un mois plus tôt, et l\'affaissement d\'un remblai sous l\'effet de la pluie est en cause.',
                'en' => 'According to the Wikipedia list, the accident happened on the metre-gauge network of the Chemins de fer économiques des Charentes, on the Marennes to Saintes line opened a month earlier, and a railway embankment giving way under the rain is blamed.',
                'de' => 'Nach der Wikipedia-Liste ereignete sich der Unfall auf dem meterspurigen Netz der Chemins de fer économiques des Charentes, auf der einen Monat zuvor eröffneten Strecke von Marennes nach Saintes; als Ursache wird ein unter dem Regen nachgebender Bahndamm genannt.',
                'sources' => ['wp-liste-1903'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Des journaux allemands des 8 et 9 décembre 1903, d\'après une dépêche de Paris, signalent qu\'un train de voyageurs a déraillé près de Saintes.',
                'en' => 'German newspapers of 8 and 9 December 1903, citing a dispatch from Paris, report that a passenger train derailed near Saintes.',
                'de' => 'Deutsche Zeitungen vom 8. und 9. Dezember 1903 melden unter Berufung auf eine Pariser Depesche, dass bei Saintes ein Personenzug entgleiste.',
                'sources' => ['ddb-1903-12-08-staatsbuerger', 'ddb-1903-12-09-allgemeine'],
            ],
            [
                'fr' => 'La liste de Wikipédia situe le déraillement vers 11 heures, près de Crazannes, dans un train bondé.',
                'en' => 'The Wikipedia list places the derailment at about 11 am, near Crazannes, in a crowded train.',
                'de' => 'Die Wikipedia-Liste verortet die Entgleisung gegen 11 Uhr bei Crazannes, in einem überfüllten Zug.',
                'sources' => ['wp-liste-1903'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La presse allemande indique plusieurs blessés et un mort. La liste de Wikipédia donne un mort, trois blessés graves et des blessés légers.',
                'en' => 'The German press reports several injured and one dead. The Wikipedia list gives one dead, three seriously injured and some slightly injured.',
                'de' => 'Die deutsche Presse meldet mehrere Verletzte und einen Toten. Die Wikipedia-Liste nennt einen Toten, drei Schwerverletzte und einige Leichtverletzte.',
                'sources' => ['ddb-1903-12-08-staatsbuerger', 'ddb-1903-12-09-allgemeine', 'wp-liste-1903'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La presse allemande dit « près de Saintes » ; la liste de Wikipédia situe l\'accident près de Crazannes, sur la ligne de Marennes à Saintes.',
                'en' => 'The German press says “near Saintes”; the Wikipedia list places the accident near Crazannes, on the Marennes to Saintes line.',
                'de' => 'Die deutsche Presse schreibt „bei Saintes“; die Wikipedia-Liste verortet den Unfall bei Crazannes, auf der Strecke von Marennes nach Saintes.',
                'sources' => ['ddb-1903-12-09-allgemeine', 'wp-liste-1903'],
            ],
        ],
    ],
];
