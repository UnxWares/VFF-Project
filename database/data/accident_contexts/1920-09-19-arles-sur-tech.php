<?php

// Contexte sourcé du déraillement près d'Amélie-les-Bains, ligne d'Elne à Arles-sur-Tech, septembre 1920 (recherche du 22/11/2026).
return [
    'slug' => '1920-09-19-arles-sur-tech',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1920' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1920',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1920&oldid=236476563#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 19 septembre 1920, d\'après Le Temps du 20 septembre : sur la ligne de Perpignan à Arles-sur-Tech, à 6 h 15, les trois dernières voitures du train venant d\'Arles se renversent près d\'Amélie-les-Bains ; un mort et trois blessés.',
        ],
        'ddb-1920-09-20-bergedorf' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugentgleisung',
            'publisher' => 'Bergedorfer Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1920-09-20',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/FXNEXNBTDH4MOU5MYYU2ANSGC5OAY3GO?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'D\'après Le Temps, de Perpignan : près de la gare d\'Amélie-les-Bains, les trois dernières voitures d\'un train de voyageurs ont déraillé ; une personne tuée, trois blessées.',
        ],
        'ddb-1920-09-21-dresden' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunfälle',
            'publisher' => 'Dresdner Nachrichten (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1920-09-21',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/UUHRJJYWGXBQEZALTEDRG3ALVUYBVQPD?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Même brève, d\'après Le Temps : trois dernières voitures d\'un train de voyageurs déraillées près de la gare d\'Amélie-les-Bains ; un mort et trois blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 19 septembre 1920 vers 6 h 15, sur la ligne de Perpignan à Arles-sur-Tech, les trois dernières voitures d\'un train de voyageurs venant d\'Arles ont déraillé et se sont renversées près de la gare d\'Amélie-les-Bains.',
                'en' => 'On 19 September 1920 at about 6.15 a.m., on the Perpignan to Arles-sur-Tech line, the last three carriages of a passenger train from Arles derailed and overturned near Amélie-les-Bains station.',
                'de' => 'Am 19. September 1920 gegen 6.15 Uhr entgleisten auf der Strecke Perpignan-Arles-sur-Tech die drei letzten Wagen eines aus Arles kommenden Personenzugs nahe dem Bahnhof Amélie-les-Bains und stürzten um.',
                'sources' => ['wp-liste-1920', 'ddb-1920-09-20-bergedorf', 'ddb-1920-09-21-dresden'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une personne a été tuée et trois blessées.',
                'en' => 'One person was killed and three were injured.',
                'de' => 'Eine Person wurde getötet, drei wurden verletzt.',
                'sources' => ['wp-liste-1920', 'ddb-1920-09-20-bergedorf'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '66003',
            'sources' => ['wp-liste-1920', 'ddb-1920-09-20-bergedorf'],
            'fr' => 'Déraillement près de la gare d\'Amélie-les-Bains selon la liste de Wikipédia et la brève tirée du Temps ; Arles-sur-Tech est le terminus de la ligne.',
            'en' => 'Derailment near Amélie-les-Bains station according to the Wikipedia list and the brief taken from Le Temps; Arles-sur-Tech is the terminus of the line.',
            'de' => 'Entgleisung nahe dem Bahnhof Amélie-les-Bains nach der Wikipedia-Liste und der Kurzmeldung aus Le Temps; Arles-sur-Tech ist der Endpunkt der Strecke.',
        ],
    ],
];
