<?php

// Contexte sourcé du déraillement du Sud-Express après une collision avec une machine haut-le-pied à l'entrée de la gare de Saint-Saviol, ligne de Paris à Bordeaux, de juillet 1911 (recherche du 21/11/2026).
return [
    'slug' => '1911-07-09-saint-saviol',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1911' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1911',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1911&oldid=241541118#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 11 juillet 1911, d\'après Le Temps du 11 juillet : peu après Ruffec, vers 18 h 30, à l\'entrée de la gare de Saint-Saviol, la locomotive et les trois premières voitures du Sud-Express, qui remontait en retard vers Paris, déraillent après avoir heurté une machine haut-le-pied manœuvrant sur la voie principale, dont le mécanicien est tué ; quatre voyageurs du rapide sont blessés.',
        ],
        'ddb-1911-07-11-fremdenblatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Das Unheil der französischen Eisenbahnen',
            'publisher' => 'Hamburger Fremdenblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1911-07-11',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/QWBR7REOY4IPHPLNKF3EWB6T4IU3QUVW?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 10 juillet : le Sud-Express a déraillé la veille au soir près de la station de Ruffec ; la locomotive et trois voitures ont quitté les rails ; le mécanicien, resté sous la locomotive, a été tué sur le coup ; le chauffeur et un agent ont été blessés.',
        ],
        'ddb-1911-07-11-reichsbote' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 10. Juli',
            'publisher' => 'Der Reichsbote (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1911-07-11',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/O7PB7A674SIA2S4KUR5BXQI6GVPTT56N?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 10 juillet : la veille, sur le réseau d\'Orléans, le rapide 110 Madrid - Paris a déraillé peu après avoir quitté la station de Ruffec, la locomotive et les trois voitures de tête sortant des rails ; un agent de conduite tué sur le coup, le chauffeur grièvement blessé.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le Sud-Express, qui remontait de Madrid vers Paris avec du retard, a déraillé vers 18 h 30 à l\'entrée de la gare de Saint-Saviol, peu après Ruffec, sur le réseau d\'Orléans. Selon la liste de Wikipédia, il venait de heurter une machine haut-le-pied qui manœuvrait sur la voie principale. La locomotive et les trois premières voitures ont quitté les rails.',
                'en' => 'The Sud Express, running late from Madrid to Paris, derailed at about 6.30 pm on entering Saint-Saviol station, shortly after Ruffec, on the Orléans company\'s network. According to the Wikipedia list it had just struck a light engine shunting on the main line. The engine and the first three coaches left the rails.',
                'de' => 'Der verspätete Südexpress von Madrid nach Paris entgleiste gegen 18.30 Uhr bei der Einfahrt in den Bahnhof Saint-Saviol kurz hinter Ruffec im Netz der Orléans-Bahn. Nach der Wikipedia-Liste war er zuvor mit einer auf dem Hauptgleis rangierenden Leerlokomotive zusammengestoßen. Lokomotive und die drei ersten Wagen sprangen aus den Schienen.',
                'sources' => ['wp-liste-1911', 'ddb-1911-07-11-fremdenblatt', 'ddb-1911-07-11-reichsbote'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un mécanicien a été tué. Le chauffeur a été grièvement blessé et plusieurs voyageurs blessés, quatre selon la liste de Wikipédia.',
                'en' => 'One driver was killed. The stoker was seriously injured and several passengers hurt, four according to the Wikipedia list.',
                'de' => 'Ein Lokomotivführer wurde getötet, der Heizer schwer verletzt, dazu mehrere Reisende, nach der Wikipedia-Liste vier.',
                'sources' => ['wp-liste-1911', 'ddb-1911-07-11-fremdenblatt', 'ddb-1911-07-11-reichsbote'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le mort est le mécanicien de la machine haut-le-pied ; selon les dépêches de Paris, c\'est le mécanicien du Sud-Express, resté sous sa locomotive. La liste date l\'accident du 11 juillet 1911, d\'après Le Temps du 11, mais deux journaux allemands parus le 11 juillet publient deux dépêches de Paris du 10 qui le placent la veille au soir, le dimanche 9 juillet.',
                'en' => 'According to the Wikipedia list, the dead man was the driver of the light engine; according to the Paris dispatches, it was the driver of the Sud-Express, trapped under his engine. The list dates the accident 11 July 1911, after Le Temps of the 11th, but two German papers printed on 11 July carry two Paris dispatches of the 10th that place it the evening before, Sunday 9 July.',
                'de' => 'Nach der Wikipedia-Liste war der Tote der Führer der Leerfahrtlokomotive, nach den Pariser Depeschen der Lokomotivführer des Südexpress, der unter seiner Maschine lag. Die Liste datiert den Unfall nach Le Temps vom 11. auf den 11. Juli 1911, doch zwei deutsche Zeitungen vom 11. Juli bringen zwei Pariser Depeschen vom 10., die ihn auf den Vorabend legen, Sonntag, den 9. Juli.',
                'sources' => ['wp-liste-1911', 'ddb-1911-07-11-fremdenblatt', 'ddb-1911-07-11-reichsbote'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_on',
            'value' => '1911-07-09',
            'sources' => ['ddb-1911-07-11-fremdenblatt', 'ddb-1911-07-11-reichsbote'],
            'fr' => 'Le 9 juillet 1911 au soir : deux dépêches de Paris du 10 juillet, de textes différents et parues le 11 dans deux journaux allemands, placent le déraillement la veille ; la fiche donnait le 11 juillet, date du numéro du Temps cité par la liste de Wikipédia.',
            'en' => 'Evening of 9 July 1911: two Paris dispatches of 10 July, with different wording and printed on the 11th in two German papers, place the derailment the day before; the record gave 11 July, the date of the issue of Le Temps cited by the Wikipedia list.',
            'de' => 'Abend des 9. Juli 1911: Zwei Pariser Depeschen vom 10. Juli mit verschiedenem Wortlaut, am 11. in zwei deutschen Zeitungen erschienen, legen die Entgleisung auf den Vortag; der Eintrag nannte den 11. Juli, das Datum der von der Wikipedia-Liste zitierten Ausgabe von Le Temps.',
        ],
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1911', 'ddb-1911-07-11-fremdenblatt'],
            'fr' => 'Un mécanicien tué selon la liste de Wikipédia (Le Temps du 11 juillet 1911) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One driver killed according to the Wikipedia list (Le Temps of 11 July 1911) and the German press; the record gave no number of deaths.',
            'de' => 'Ein Lokomotivführer getötet nach der Wikipedia-Liste (Le Temps vom 11. Juli 1911) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
