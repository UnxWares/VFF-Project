<?php

// Contexte sourcé de l'accident de Colmar du 11 février 1946 (recherche du 21/11/2026).
return [
    'slug' => '1946-02-11-colmar',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1946',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 11 février 1946 ; elle renvoie à l\'Annuaire de la Société d\'Histoire et d\'Archéologie de Colmar 2003-2004, sans page.',
        ],
        'wintzenheim' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1946 : 15 morts dans une collision entre un train et un bus',
            'publisher' => 'Site d\'histoire de Wintzenheim',
            'author' => null,
            'published' => '1946',
            'locator' => null,
            'url' => 'http://wintzenheim.histoire.free.fr/FaitsDivers/1946/Accident.htm',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Page signalée par un résultat de recherche, inaccessible à la lecture (certificat du serveur invalide) ; probablement la source la plus détaillée, à consulter.',
        ],
        'shac' => [
            'provider' => 'ouvrages',
            'kind' => 'book',
            'reliability' => 'scholarly',
            'title' => 'Annuaire de la Société d\'Histoire et d\'Archéologie de Colmar, 2003-2004',
            'publisher' => 'Société d\'histoire et d\'archéologie de Colmar',
            'author' => null,
            'published' => '2004',
            'locator' => null,
            'url' => 'https://colmar-histoire.fr/publications/',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Volume cité par Wikipédia pour cet accident ; non consulté.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 11 février 1946, vers 7 h 30, au passage à niveau de la route d\'Ingersheim à Colmar (Haut-Rhin), l\'express Calais-Bâle entre en collision avec un autocar de la société Salta, qui assurait la liaison Colmar-Kaysersberg.',
                'en' => 'On 11 February 1946, at about 7.30 a.m., at the level crossing on the Ingersheim road in Colmar (Haut-Rhin), the Calais to Basel express collided with a coach of the Salta company, which ran the Colmar to Kaysersberg service.',
                'de' => 'Am 11. Februar 1946 gegen 7.30 Uhr stieß am Bahnübergang der Straße nach Ingersheim in Colmar (Oberelsass) der Schnellzug Calais-Basel mit einem Omnibus der Gesellschaft Salta zusammen, der die Verbindung Colmar-Kaysersberg bediente.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait quinze morts et seize blessés graves.',
                'en' => 'The accident killed fifteen people and seriously injured sixteen.',
                'de' => 'Bei dem Unfall starben fünfzehn Menschen, sechzehn wurden schwer verletzt.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
    ],
];
