<?php

// Contexte sourcé du déraillement d'un train de marchandises tombé dans le Lot entre Le Monastier et Banassac, ligne de Béziers à Neussargues, d'août 1910 (recherche du 21/11/2026).
return [
    'slug' => '1910-08-09-banassac-canilhac',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1910' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1910',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1910&oldid=233727870#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 9 août 1910, d\'après Le Figaro du 11 août : sur la ligne de Béziers à Neussargues, entre les gares du Monastier et de Banassac-La Canourgue, la locomotive et une dizaine de wagons d\'un train de marchandises déraillent et tombent dans le Lot ; un serre-freins est broyé dans sa guérite.',
        ],
        'ddb-1910-08-11-badische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Mende, 10. August (Tel.)',
            'publisher' => 'Badische Presse, édition de midi (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-08-11',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/XIOEWHHQ4AOI44TBF5NL2E7XFK5RGVU4?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme de Mende du 10 août : hier après-midi, entre les stations du Monastier et de Banassac-La Canourgue, un train de marchandises a déraillé ; la machine et plusieurs wagons sont tombés dans la rivière ; un serre-freins tué, deux autres agents blessés.',
        ],
        'ddb-1910-08-11-ettlingen' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Mende, 10. Aug.',
            'publisher' => 'Mittelbadischer Courier (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-08-11',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/L457RCZNRYUNMDX2YK7MTMERZ6ELBNQR?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même télégramme de Mende : déraillement d\'un train de marchandises entre Le Monastier et Banassac-La Canourgue, un serre-freins tué, deux agents blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 9 août 1910, l\'après-midi, un train de marchandises circulait sur la ligne de Béziers à Neussargues, entre les gares du Monastier et de Banassac-La Canourgue, le long du Lot.',
                'en' => 'On the afternoon of 9 August 1910 a goods train was running on the Béziers to Neussargues line between Le Monastier and Banassac-La Canourgue stations, along the Lot.',
                'de' => 'Am Nachmittag des 9. August 1910 fuhr ein Güterzug auf der Strecke Béziers-Neussargues zwischen den Bahnhöfen Le Monastier und Banassac-La Canourgue am Lot entlang.',
                'sources' => ['wp-liste-1910', 'ddb-1910-08-11-badische'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train a déraillé : la locomotive et une dizaine de wagons sont tombés dans la rivière.',
                'en' => 'The train derailed: the engine and about ten wagons fell into the river.',
                'de' => 'Der Zug entgleiste: Die Lokomotive und etwa zehn Wagen stürzten in den Fluss.',
                'sources' => ['wp-liste-1910', 'ddb-1910-08-11-badische', 'ddb-1910-08-11-ettlingen'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un serre-freins a été tué, broyé dans sa guérite selon la liste de Wikipédia. Le télégramme de Mende signale aussi deux autres agents blessés.',
                'en' => 'A brakeman was killed, crushed in his brake cabin according to the Wikipedia list. The Mende telegram also reports two other railwaymen injured.',
                'de' => 'Ein Bremser wurde getötet, nach der Wikipedia-Liste in seinem Bremserhäuschen zerquetscht. Das Telegramm aus Mende meldet außerdem zwei weitere verletzte Bedienstete.',
                'sources' => ['wp-liste-1910', 'ddb-1910-08-11-badische'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1910', 'ddb-1910-08-11-badische', 'ddb-1910-08-11-ettlingen'],
            'fr' => 'Un serre-freins tué selon la liste de Wikipédia (Le Figaro du 11 août 1910) et le télégramme de Mende du 10 août ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'A brakeman killed according to the Wikipedia list (Le Figaro of 11 August 1910) and the Mende telegram of 10 August; the record gave no number of deaths.',
            'de' => 'Ein Bremser getötet nach der Wikipedia-Liste (Le Figaro vom 11. August 1910) und dem Telegramm aus Mende vom 10. August; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
