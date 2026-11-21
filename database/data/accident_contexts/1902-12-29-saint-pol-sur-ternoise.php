<?php

// Contexte sourcé du déraillement d'un train de voyageurs à l'entrée de la gare de Saint-Pol-sur-Ternoise, ligne de Fives à Abbeville, de décembre 1902 (recherche du 21/11/2026).
return [
    'slug' => '1902-12-29-saint-pol-sur-ternoise',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1902' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1902',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1902&oldid=233590581#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 29 décembre 1902 : peu avant l\'entrée en gare de Saint-Pol-sur-Ternoise, sur la ligne de Fives à Abbeville, vers 9 h, la locomotive et les quatre premières voitures d\'un train de voyageurs venant de Bully-Grenay par Brias déraillent ; le mécanicien est tué, le chauffeur et trois voyageurs blessés.',
        ],
        'ddb-1902-12-31-fremdenblatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugentgleisung in Frankreich',
            'publisher' => 'Hamburger Fremdenblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-12-31',
            'locator' => 'p. 19',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/HYEMTCXMMJW3XHUTPHPWWPW7NBIHNFK3?issuepage=19',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche d\'Arras du 29 décembre : près de la gare de Saint-Pol, un train de voyageurs a déraillé ; la machine et quatre voitures ont quitté les rails et se sont renversées ; le mécanicien a été tué, quatre voyageurs grièvement et plusieurs autres légèrement blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 29 décembre 1902, vers 9 h, peu avant l\'entrée en gare de Saint-Pol-sur-Ternoise, sur la ligne de Fives à Abbeville, la locomotive et les quatre premières voitures d\'un train de voyageurs venant de Bully-Grenay par Brias ont déraillé et se sont renversées.',
                'en' => 'On 29 December 1902, at about 9 am, shortly before entering Saint-Pol-sur-Ternoise station on the Fives to Abbeville line, the engine and the first four coaches of a passenger train from Bully-Grenay via Brias derailed and overturned.',
                'de' => 'Am 29. Dezember 1902 gegen 9 Uhr entgleisten kurz vor der Einfahrt in den Bahnhof Saint-Pol-sur-Ternoise an der Strecke Fives-Abbeville die Lokomotive und die vier ersten Wagen eines Personenzugs aus Bully-Grenay über Brias und stürzten um.',
                'sources' => ['wp-liste-1902', 'ddb-1902-12-31-fremdenblatt'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été tué. Le chauffeur et des voyageurs ont été blessés.',
                'en' => 'The driver was killed. The stoker and some passengers were injured.',
                'de' => 'Der Lokomotivführer wurde getötet, der Heizer und Reisende wurden verletzt.',
                'sources' => ['wp-liste-1902', 'ddb-1902-12-31-fremdenblatt'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia compte trois voyageurs blessés ; la dépêche d\'Arras en annonce quatre grièvement et plusieurs autres légèrement.',
                'en' => 'The Wikipedia list counts three passengers injured; the Arras dispatch reports four seriously and several more slightly.',
                'de' => 'Die Wikipedia-Liste zählt drei verletzte Reisende, die Meldung aus Arras vier schwer und mehrere leicht Verletzte.',
                'sources' => ['wp-liste-1902', 'ddb-1902-12-31-fremdenblatt'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1902', 'ddb-1902-12-31-fremdenblatt'],
            'fr' => 'Mécanicien tué selon la liste de Wikipédia et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver killed according to the Wikipedia list and the German press; the record gave no number of deaths.',
            'de' => 'Lokomotivführer getötet nach der Wikipedia-Liste und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
