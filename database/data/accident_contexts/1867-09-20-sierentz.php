<?php

// Contexte sourcé du déraillement de Sierentz du 20 septembre 1867 (recherche du 21/11/2026).
return [
    'slug' => '1867-09-20-sierentz',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1867',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1867',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après Le Petit Journal du 23 septembre 1867 : vers 9 h 30, sur la ligne de Mulhouse à Bâle, peu avant la gare de Sierentz, un train semi-direct pour Bâle déraille et dévale le remblai ; le chauffeur est tué, une vingtaine de voyageurs sont blessés.',
        ],
        'ddb-1867-09-26-koelnische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Der am 20. Sept. um 9 Uhr Vormittags von Basel abgegangene Schnellzug der französischen Ostbahn ist 200 Schritte unterhalb Sierenz entgleist',
            'publisher' => 'Kölnische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1867-09-26',
            'locator' => 'p. 7',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/ET6YUN633P45BDW35HQYPRODASS4FQEY?issuepage=7',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Nouvelle de Bâle : l\'express de l\'Est parti de Bâle le 20 septembre à 9 h du matin déraille 200 pas en aval de Sierentz ; la machine et les voitures sont projetées dans un champ ; le chauffeur est tué, le chef de train et un voyageur anglais grièvement blessés, 13 voyageurs blessés ; la cause donnée est le mauvais état des traverses, qui devaient être remplacées les jours suivants.',
        ],
        'ddb-1867-09-27-nuernberger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Der Schnellzug von Basel nach Paris entgleiste bei Sierenz',
            'publisher' => 'Nürnberger Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1867-09-27',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/MGLUH4KS7F5I7TCKHACTACVCDCCMS7I7?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : l\'express de Bâle à Paris a déraillé près de Sierentz ; plusieurs personnes sont tuées ou blessées.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 20 septembre 1867 au matin, un train rapide déraille peu avant la gare de Sierentz (Haut-Rhin), sur la ligne de Mulhouse à Bâle de la Compagnie de l\'Est.',
                'en' => 'On the morning of 20 September 1867, a fast train derailed shortly before Sierentz station (Haut-Rhin), on the Eastern Railway\'s Mulhouse to Basel line.',
                'de' => 'Am Morgen des 20. September 1867 entgleiste kurz vor dem Bahnhof Sierentz (Haut-Rhin) auf der Strecke Mülhausen-Basel der Ostbahn ein Schnellzug.',
                'sources' => ['wp-liste-1860', 'ddb-1867-09-26-koelnische', 'ddb-1867-09-27-nuernberger'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La machine et les voitures quittent la voie et finissent dans un champ, au pied du remblai. La Kölnische Zeitung attribue le déraillement au mauvais état des traverses, qui devaient être remplacées dans les jours suivants.',
                'en' => 'The engine and carriages left the track and ended up in a field at the foot of the embankment. The Kölnische Zeitung puts the derailment down to the poor state of the sleepers, which were due to be replaced in the following days.',
                'de' => 'Lokomotive und Wagen verließen das Gleis und landeten am Fuß des Bahndamms in einem Feld. Die Kölnische Zeitung führt die Entgleisung auf den schadhaften Zustand der Schwellen zurück, die in den folgenden Tagen ersetzt werden sollten.',
                'sources' => ['ddb-1867-09-26-koelnische', 'wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur est tué. La Kölnische Zeitung compte le chef de train et un voyageur grièvement blessés et 13 voyageurs blessés ; la liste de Wikipédia parle d\'une vingtaine de blessés.',
                'en' => 'The stoker was killed. The Kölnische Zeitung counts the guard and one passenger seriously injured and 13 passengers hurt; the Wikipedia list speaks of about twenty injured.',
                'de' => 'Der Heizer wurde getötet. Die Kölnische Zeitung zählt den Zugführer und einen Reisenden schwer verletzt sowie 13 verletzte Reisende; die Wikipedia-Liste spricht von rund zwanzig Verletzten.',
                'sources' => ['wp-liste-1860', 'ddb-1867-09-26-koelnische'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le sens du train diffère : train pour Bâle selon la liste de Wikipédia, express parti de Bâle vers Paris selon la presse allemande. L\'heure aussi : vers 9 h 30 dans la liste, départ de Bâle à 9 h dans la presse. Le nombre de blessés va d\'une quinzaine (presse) à une vingtaine (liste).',
                'en' => 'The direction of the train differs: a train to Basel according to the Wikipedia list, an express from Basel to Paris according to the German press. So does the time: about 9.30 am in the list, departure from Basel at 9 am in the press. The number of injured ranges from about fifteen (press) to about twenty (list).',
                'de' => 'Die Fahrtrichtung weicht ab: ein Zug nach Basel nach der Wikipedia-Liste, ein Schnellzug von Basel nach Paris nach der deutschen Presse. Ebenso die Uhrzeit: gegen 9.30 Uhr in der Liste, Abfahrt in Basel um 9 Uhr in der Presse. Die Zahl der Verletzten reicht von etwa fünfzehn (Presse) bis etwa zwanzig (Liste).',
                'sources' => ['wp-liste-1860', 'ddb-1867-09-26-koelnische', 'ddb-1867-09-27-nuernberger'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['ddb-1867-09-26-koelnische', 'wp-liste-1860'],
            'fr' => 'Un mort, le chauffeur, selon la liste de Wikipédia et la Kölnische Zeitung du 26 septembre 1867 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One dead, the stoker, according to the Wikipedia list and the Kölnische Zeitung of 26 September 1867; the record gave no number of deaths.',
            'de' => 'Ein Toter, der Heizer, nach der Wikipedia-Liste und der Kölnischen Zeitung vom 26. September 1867; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
