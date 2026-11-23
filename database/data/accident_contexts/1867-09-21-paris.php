<?php

// Contexte sourcé du déraillement de la ligne de Vincennes à l'approche de la station de Bel-Air, à Paris, du 21 septembre 1867 (recherche du 23/11/2026).
return [
    'slug' => '1867-09-21-paris',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1867, 21 septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1867',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Entrée de la liste : déraillement dans une courbe, à l\'approche de la station de Bel-Air, d\'un train pour la Bastille ; une dizaine de blessés légers et un blessé grave mort à l\'hôpital. Cite Le Figaro du 23 septembre 1867.',
        ],
        'figaro-1867-09-23' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 23 septembre 1867',
            'publisher' => 'Le Figaro (numérisé sur Gallica)',
            'author' => null,
            'published' => '1867-09-23',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2708767/f3.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-23',
            'note' => 'Page 3, citée par la liste de Wikipédia ; non lue.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 21 septembre 1867 vers 17 heures, sur la ligne de Vincennes, les véhicules de tête d\'un train pour la Bastille, qui circulait tender en avant, ont déraillé dans une courbe et se sont couchés à l\'approche de la station de Bel-Air, d\'après la liste de Wikipédia.',
                'en' => 'On 21 September 1867 at about 5 p.m., on the Vincennes line, the leading vehicles of a train for the Bastille, running tender first, derailed in a curve and overturned as they approached Bel-Air station, according to the Wikipedia list.',
                'de' => 'Am 21. September 1867 gegen 17 Uhr entgleisten auf der Strecke von Vincennes die vorderen Fahrzeuge eines Zuges zur Bastille, der mit dem Tender voraus fuhr, in einer Kurve und kippten bei der Annäherung an die Station Bel-Air um, so die Wikipedia-Liste.',
                'sources' => ['wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une dizaine de blessés légers et un blessé grave, mort peu après à l\'hôpital, selon la liste de Wikipédia.',
                'en' => 'About ten people were lightly injured and one seriously, who died soon afterwards in hospital, according to the Wikipedia list.',
                'de' => 'Etwa zehn Personen wurden leicht und eine schwer verletzt, die kurz darauf im Krankenhaus starb, so die Wikipedia-Liste.',
                'sources' => ['wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La carte place l\'accident à la gare de la Bastille, terminus du train, position de la gare citée par la liste de Wikipédia. Le texte de la liste le situe à l\'approche de la station de Bel-Air, donc plus loin sur la ligne : la Bastille est la gare de rattachement, pas le lieu du déraillement.',
                'en' => 'The map places the accident at the Bastille station, the terminus of the train, the position of the station named by the Wikipedia list. The list\'s text puts it on the approach to Bel-Air station, further along the line: the Bastille is the station it is linked to, not the place of the derailment.',
                'de' => 'Die Karte setzt den Unfall an die Gare de la Bastille, den Endpunkt des Zuges, die Position des in der Wikipedia-Liste genannten Bahnhofs. Der Text der Liste verortet ihn bei der Annäherung an die Station Bel-Air, also weiter an der Strecke: Die Bastille ist der zugeordnete Bahnhof, nicht der Ort der Entgleisung.',
                'sources' => ['wp-liste-1860'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
