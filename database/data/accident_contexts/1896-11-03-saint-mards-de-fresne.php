<?php

// Contexte sourcé de la collision d'un train de marchandises et d'une locomotive haut-le-pied en gare de Saint-Mards-de-Fresne, ligne de Paris à Caen, de novembre 1896 (recherche du 21/11/2026).
return [
    'slug' => '1896-11-03-saint-mards-de-fresne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1896',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1896',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 3 novembre 1896, d\'après La Presse du 4 novembre : vers 20 h, en gare de Saint-Mards-de-Fresne, sur la ligne de Paris à Caen, collision entre un train de marchandises et une locomotive haut-le-pied ; un conducteur-chef est tué, un mécanicien blessé.',
        ],
        'ddb-1896-11-07-badische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris.',
            'publisher' => 'Badische Landes-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1896-11-07',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/H4DGRFWACOKHNWMAIFACG4TEEWNR76AA?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève de Paris : dans la gare de « Saint-Marie » près de Caen (OCR), un train de marchandises a heurté une locomotive ; le chef de train a été tué et le mécanicien blessé.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Vers 20 h, en gare de Saint-Mards-de-Fresne, sur la ligne de Paris à Caen, un train de marchandises et une locomotive haut-le-pied sont entrés en collision.',
                'en' => 'At about 8 pm, at Saint-Mards-de-Fresne station on the Paris to Caen line, a goods train and a light engine collided.',
                'de' => 'Gegen 20 Uhr stießen im Bahnhof Saint-Mards-de-Fresne an der Strecke Paris-Caen ein Güterzug und eine Lokomotive auf Leerfahrt zusammen.',
                'sources' => ['wp-liste-1890', 'ddb-1896-11-07-badische'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chef du train a été tué et un mécanicien blessé.',
                'en' => 'The guard in charge of the train was killed and a driver was injured.',
                'de' => 'Der Zugführer wurde getötet, ein Lokomotivführer verletzt.',
                'sources' => ['wp-liste-1890', 'ddb-1896-11-07-badische'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La brève allemande place la gare « près de Caen » et la nomme « Saint-Marie » dans le texte numérisé ; la liste de Wikipédia la situe à Saint-Mards-de-Fresne, dans l\'Eure, sur la même ligne.',
                'en' => 'The German brief places the station "near Caen" and names it "Saint-Marie" in the digitised text; the Wikipedia list puts it at Saint-Mards-de-Fresne, in the Eure, on the same line.',
                'de' => 'Die deutsche Kurzmeldung verlegt den Bahnhof „bei Caen“ und nennt ihn im digitalisierten Text „Saint-Marie“; die Wikipedia-Liste setzt ihn nach Saint-Mards-de-Fresne im Département Eure an derselben Strecke.',
                'sources' => ['ddb-1896-11-07-badische', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1896-11-07-badische'],
            'fr' => 'Un agent tué, le chef du train, selon la liste de Wikipédia et la brève de la Badische Landes-Zeitung du 7 novembre 1896 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One railwayman killed, the guard in charge of the train, according to the Wikipedia list and the brief in the Badische Landes-Zeitung of 7 November 1896; the record gave no number of deaths.',
            'de' => 'Ein Bediensteter getötet, der Zugführer, nach der Wikipedia-Liste und der Kurzmeldung der Badischen Landes-Zeitung vom 7. November 1896; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1896-11-07-badische'],
            'fr' => 'Un mécanicien blessé selon la liste de Wikipédia et la brève de la Badische Landes-Zeitung ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'One driver injured according to the Wikipedia list and the brief in the Badische Landes-Zeitung; the record gave no number of injured.',
            'de' => 'Ein Lokomotivführer verletzt nach der Wikipedia-Liste und der Kurzmeldung der Badischen Landes-Zeitung; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
