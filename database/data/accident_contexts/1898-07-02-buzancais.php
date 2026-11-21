<?php

// Contexte sourcé du déraillement de Chambon, entre Villedieu-sur-Indre et Buzançais, du 2 juillet 1898 (recherche du 21/11/2026).
return [
    'slug' => '1898-07-02-buzancais',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890s' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1898',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1898',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après Le Figaro du 4 juillet 1898 : à Chambon, entre Villedieu-sur-Indre et Buzançais, vers 19 h 30, la locomotive du train Châteauroux - Tours se couche sur la voie après une rupture d\'essieu ; tender, fourgon et première voiture la télescopent ; un mort et une dizaine de blessés.',
        ],
        'ddb-1898-07-04-koelnische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Chateauroux, 3. Juli (Telegr.)',
            'publisher' => 'Kölnische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1898-07-04',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/S7QI2HGXEXOQD43ENU4QFC56GLE4MNRB?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme de Châteauroux du 3 juillet : le train de voyageurs Châteauroux - Tours a déraillé la veille au soir, un voyageur tué, quatre blessés. Même dépêche dans la Königsberger Hartungsche Zeitung du 4 juillet et le Reichsbote du 6 juillet.',
        ],
        'ddb-1898-07-04-dresdner' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Chateauroux',
            'publisher' => 'Dresdner Journal (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1898-07-04',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/AT45JGY2MHSTHATXZLQP4H6I6JBOIVLN?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève reprenant la même dépêche : le train Châteauroux - Tours a déraillé le samedi soir, un voyageur tué, quatre blessés.',
        ],
        'figaro-1898-07-04' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 4 juillet 1898',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1898-07-04',
            'locator' => 'p. 4',
            'url' => 'https://gallica.bnf.fr/ark:/12148/cb34355551z/date18980704',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de l\'accident, cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 2 juillet 1898, vers 19 h 30, le train de voyageurs de Châteauroux à Tours roulait entre Villedieu-sur-Indre et Buzançais, à hauteur de Chambon.',
                'en' => 'On Saturday 2 July 1898, at about 7.30 pm, the passenger train from Châteauroux to Tours was running between Villedieu-sur-Indre and Buzançais, near Chambon.',
                'de' => 'Am Samstag, dem 2. Juli 1898, gegen 19.30 Uhr fuhr der Personenzug von Châteauroux nach Tours zwischen Villedieu-sur-Indre und Buzançais auf der Höhe von Chambon.',
                'sources' => ['wp-liste-1890s', 'ddb-1898-07-04-koelnische', 'ddb-1898-07-04-dresdner'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un essieu de la locomotive s\'est rompu et la machine s\'est couchée sur la voie ; le tender, le fourgon et la première voiture l\'ont télescopée. La presse allemande parle d\'un déraillement.',
                'en' => 'An axle of the locomotive broke and the engine fell on its side across the track; the tender, the van and the first coach ran into it. The German press reports a derailment.',
                'de' => 'Eine Achse der Lokomotive brach, die Maschine legte sich auf das Gleis, Tender, Packwagen und erster Wagen schoben sich in sie hinein. Die deutsche Presse meldet eine Entgleisung.',
                'sources' => ['wp-liste-1890s', 'ddb-1898-07-04-koelnische'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur a été tué. Le télégramme de Châteauroux compte quatre blessés, la liste de Wikipédia une dizaine.',
                'en' => 'One passenger was killed. The telegram from Châteauroux counts four injured, the Wikipedia list about ten.',
                'de' => 'Ein Reisender wurde getötet. Das Telegramm aus Châteauroux zählt vier Verletzte, die Wikipedia-Liste etwa zehn.',
                'sources' => ['ddb-1898-07-04-koelnische', 'ddb-1898-07-04-dresdner', 'wp-liste-1890s'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère : quatre selon la dépêche de Châteauroux du 3 juillet, une dizaine selon la liste de Wikipédia.',
                'en' => 'The number of injured differs: four according to the Châteauroux dispatch of 3 July, about ten according to the Wikipedia list.',
                'de' => 'Die Zahl der Verletzten weicht ab: vier nach der Meldung aus Châteauroux vom 3. Juli, etwa zehn nach der Wikipedia-Liste.',
                'sources' => ['ddb-1898-07-04-koelnische', 'wp-liste-1890s'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'derailment',
            'sources' => ['wp-liste-1890s', 'ddb-1898-07-04-koelnische'],
            'fr' => 'La liste de Wikipédia décrit une rupture d\'essieu de la locomotive et la presse allemande du 4 juillet 1898 un déraillement ; la fiche indiquait une rupture d\'ouvrage.',
            'en' => 'The Wikipedia list describes a broken locomotive axle and the German press of 4 July 1898 a derailment; the record said structural failure.',
            'de' => 'Die Wikipedia-Liste beschreibt einen Achsbruch der Lokomotive und die deutsche Presse vom 4. Juli 1898 eine Entgleisung; der Eintrag nannte ein Bauwerksversagen.',
        ],
    ],
];
