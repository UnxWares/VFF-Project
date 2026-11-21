<?php

// Contexte sourcé du déraillement du train de Paris à Belfort du 3 juillet 1875 (recherche du 21/11/2026).
return [
    'slug' => '1875-07-03-maizieres-sur-amance',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1875',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1875',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après Le Temps du 4 juillet et Le Figaro du 5 juillet 1875 : le train-poste de Paris à Belfort déraille à 3 h 30 en Haute-Marne, près de Maizières-sur-Amance, peu après la gare de Charmoy-Fayl-Billot ; une voiture se couche et est traînée sur 800 m ; deux voyageuses anglaises tuées, un autre voyageur blessé ; la Compagnie de l\'Est indique que les deux voyageuses se sont tuées en sautant en marche.',
        ],
        'ddb-1875-07-07-allgemeine' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Mülhausen, 3. Juli. (Eisenbahn-Unfall.) Der um 9 Uhr Vormittags hier von Paris ankommende Schnellzug',
            'publisher' => 'Allgemeine Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1875-07-07',
            'locator' => 'p. 8',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/KWTT2K227L7WPEKQKQIT55NQQPIBYET7?issuepage=8',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Nouvelle de Mulhouse du 3 juillet : l\'express de Paris attendu à 9 h à Mulhouse a déraillé à 50 m avant la station de Vaivre, après Vesoul ; toutes les voitures sauf la voiture directe de 1re classe des chemins de fer d\'Alsace-Lorraine sont sorties des rails ; le train ne s\'est arrêté qu\'après Vaivre ; deux Anglaises qui voulaient se sauver en sautant de la voiture se sont tuées, plusieurs autres personnes sont plus ou moins gravement blessées ; une voiture de 1re classe est renversée.',
        ],
        'ddb-1875-07-11-erzgebirgischer' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Mülhausen, 3. Juli. Der um 9 Uhr Vormittags hier von Paris ankommende Schnellzug',
            'publisher' => 'Erzgebirgischer Volksfreund (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1875-07-11',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/RSNFNJN2QGODJBJALHRHJC37S3UBLK67?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle de Mulhouse : déraillement à 50 m avant la station de Vaivre, deux Anglaises tuées en sautant de la voiture, plusieurs blessés, un directeur de la Compagnie de l\'Est dans le train.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 juillet 1875, au petit matin, le train de Paris à Belfort et Mulhouse de la Compagnie de l\'Est a déraillé. Un directeur de la compagnie se trouvait à bord.',
                'en' => 'In the early morning of 3 July 1875, the Eastern Railway Company\'s train from Paris to Belfort and Mulhouse derailed. One of the company\'s directors was on board.',
                'de' => 'Am frühen Morgen des 3. Juli 1875 entgleiste der Zug der Ostbahn von Paris nach Belfort und Mülhausen. Ein Direktor der Gesellschaft befand sich im Zug.',
                'sources' => ['wp-liste-1870', 'ddb-1875-07-07-allgemeine'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Presque toutes les voitures sont sorties des rails ; selon la presse allemande, le train n\'a pu s\'arrêter qu\'au-delà de la station de Vaivre. Une voiture de 1re classe s\'est couchée ; la liste de Wikipédia précise qu\'elle a été traînée sur 800 m.',
                'en' => 'Nearly all the carriages left the rails; according to the German press, the train could only be stopped beyond Vaivre station. A first-class carriage overturned; the Wikipedia list says it was dragged for 800 m.',
                'de' => 'Fast alle Wagen sprangen aus den Schienen; nach der deutschen Presse kam der Zug erst hinter der Station Vaivre zum Stehen. Ein Wagen 1. Klasse stürzte um; nach der Wikipedia-Liste wurde er 800 m weit mitgeschleift.',
                'sources' => ['ddb-1875-07-07-allgemeine', 'ddb-1875-07-11-erzgebirgischer', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux voyageuses anglaises ont été tuées en sautant de leur voiture. La presse allemande parle de plusieurs autres blessés, la liste de Wikipédia d\'un seul.',
                'en' => 'Two English women passengers were killed when they jumped from their carriage. The German press speaks of several other injured, the Wikipedia list of one.',
                'de' => 'Zwei englische Reisende kamen ums Leben, als sie aus ihrem Wagen sprangen. Die deutsche Presse spricht von mehreren weiteren Verletzten, die Wikipedia-Liste von einem.',
                'sources' => ['ddb-1875-07-07-allgemeine', 'ddb-1875-07-11-erzgebirgischer', 'wp-liste-1870'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu diffère : près de Maizières-sur-Amance (Haute-Marne), peu après la gare de Charmoy-Fayl-Billot, selon la liste de Wikipédia ; à 50 m avant la station de Vaivre, après Vesoul (Haute-Saône), selon la nouvelle de Mulhouse reprise par la presse allemande. Le nombre de blessés diffère aussi : un selon la liste, plusieurs selon la presse.',
                'en' => 'The location differs: near Maizières-sur-Amance (Haute-Marne), shortly after Charmoy-Fayl-Billot station, according to the Wikipedia list; 50 m before Vaivre station, beyond Vesoul (Haute-Saône), according to the report from Mulhouse carried by the German press. The number of injured differs too: one according to the list, several according to the press.',
                'de' => 'Der Ort weicht ab: bei Maizières-sur-Amance (Haute-Marne), kurz hinter dem Bahnhof Charmoy-Fayl-Billot, nach der Wikipedia-Liste; 50 m vor der Station Vaivre hinter Vesoul (Haute-Saône) nach der von der deutschen Presse übernommenen Meldung aus Mülhausen. Auch die Zahl der Verletzten weicht ab: einer nach der Liste, mehrere nach der Presse.',
                'sources' => ['wp-liste-1870', 'ddb-1875-07-07-allgemeine', 'ddb-1875-07-11-erzgebirgischer'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['ddb-1875-07-07-allgemeine', 'wp-liste-1870'],
            'fr' => 'Deux voyageuses tuées selon la liste de Wikipédia et la presse allemande de juillet 1875 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two women passengers killed according to the Wikipedia list and the German press of July 1875; the record gave no number of deaths.',
            'de' => 'Zwei getötete Reisende nach der Wikipedia-Liste und der deutschen Presse vom Juli 1875; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
