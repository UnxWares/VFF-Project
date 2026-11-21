<?php

// Contexte sourcé du tamponnement du train-poste Paris - Brest en gare de La Ferté-Bernard, ligne de Paris au Mans, de novembre 1883 (recherche du 21/11/2026).
return [
    'slug' => '1883-11-01-la-ferte-bernard',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1883',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1883',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 1er novembre 1883, d\'après La Presse du 4 novembre : sur la ligne de Paris au Mans, vers minuit, une machine haut-le-pied rentrant au dépôt percute le train-poste Paris - Brest à l\'arrêt en gare de La Ferté-Bernard et écrase le fourgon de queue et deux voitures ; un voyageur tué, dix-neuf blessés.',
        ],
        'ddb-1883-11-06-bergische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück. Paris, 2. Nov.',
            'publisher' => 'Bergische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1883-11-06',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/MTVIYCKJMZXJ4OYLI6CCSJVMV462NGSL?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 2 novembre : cette nuit, sur la ligne de l\'Ouest de Paris à Brest, le train parti de Paris à 8 h du soir a heurté vers minuit une machine de manœuvre en gare de La Ferté-Bernard ; le fourgon et deux voitures de deuxième et troisième classe ont été écrasés ; un voyageur tué, 18 blessés plus ou moins grièvement.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Dans la nuit du 1er au 2 novembre 1883, vers minuit, le train-poste parti de Paris pour Brest à 8 h du soir était arrêté en gare de La Ferté-Bernard, sur la ligne de Paris au Mans. Une machine haut-le-pied qui rentrait au dépôt l\'a heurté et a écrasé le fourgon de queue et deux voitures de deuxième et troisième classe.',
                'en' => 'During the night of 1 to 2 November 1883, at about midnight, the Paris to Brest mail train, which had left Paris at 8 pm, was standing at La Ferté-Bernard station on the Paris to Le Mans line. A light engine returning to the shed ran into it and crushed the rear van and two second and third class coaches.',
                'de' => 'In der Nacht vom 1. auf den 2. November 1883 gegen Mitternacht stand der um 20 Uhr in Paris abgefahrene Postzug nach Brest im Bahnhof La Ferté-Bernard an der Strecke Paris-Le Mans. Eine leer zum Depot zurückfahrende Lokomotive prallte auf ihn und zertrümmerte den Schlusspackwagen und zwei Wagen zweiter und dritter Klasse.',
                'sources' => ['wp-liste-1880', 'ddb-1883-11-06-bergische'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur a été tué. La liste de Wikipédia compte dix-neuf blessés, la dépêche de Paris dix-huit.',
                'en' => 'One passenger was killed. The Wikipedia list counts nineteen injured, the Paris dispatch eighteen.',
                'de' => 'Ein Reisender wurde getötet. Die Wikipedia-Liste zählt neunzehn Verletzte, die Pariser Depesche achtzehn.',
                'sources' => ['wp-liste-1880', 'ddb-1883-11-06-bergische'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La dépêche parle d\'une machine de manœuvre, la liste de Wikipédia d\'une machine haut-le-pied rentrant au dépôt. Le nombre de blessés varie de 18 à 19.',
                'en' => 'The dispatch speaks of a shunting engine, the Wikipedia list of a light engine returning to the shed. The number of injured varies between 18 and 19.',
                'de' => 'Die Depesche spricht von einer Rangierlokomotive, die Wikipedia-Liste von einer leer zum Depot fahrenden Lokomotive. Die Zahl der Verletzten schwankt zwischen 18 und 19.',
                'sources' => ['ddb-1883-11-06-bergische', 'wp-liste-1880'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1880', 'ddb-1883-11-06-bergische'],
            'fr' => 'Un voyageur tué selon la liste de Wikipédia (La Presse du 4 novembre 1883) et la dépêche de Paris du 2 novembre ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One passenger killed according to the Wikipedia list (La Presse of 4 November 1883) and the Paris dispatch of 2 November; the record gave no number of deaths.',
            'de' => 'Ein Reisender getötet nach der Wikipedia-Liste (La Presse vom 4. November 1883) und der Pariser Depesche vom 2. November; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
