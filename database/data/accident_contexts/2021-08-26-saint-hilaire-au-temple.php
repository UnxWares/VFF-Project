<?php

// Contexte sourcé du déraillement de Saint-Hilaire-au-Temple du 26 août 2021 (recherche du 22/11/2026).
// Fusion du 22/11/2026 : la fiche « déraillement à Reims » issue de la liste de Wikipédia décrit le même accident.
return [
    'slug' => '2021-08-26-saint-hilaire-au-temple',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-2020' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 2020',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '26 août 2021',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_2020&oldid=241585500',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée appuyée sur L\'Union du 28 août 2021 (p. 1 à 4), journal non consulté.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le jeudi 26 août 2021, un train de marchandises chargé de céréales a déraillé entre Châlons-en-Champagne et Reims, à la hauteur de la LGV : treize wagons sont sortis des rails, dont trois se sont couchés sur la voie, d\'après la liste de Wikipédia.',
                'en' => 'On Thursday 26 August 2021, a freight train loaded with grain derailed between Châlons-en-Champagne and Reims, near the high-speed line: thirteen wagons left the rails, three of which overturned on the track, according to the Wikipedia list.',
                'de' => 'Am Donnerstag, dem 26. August 2021, entgleiste zwischen Châlons-en-Champagne und Reims auf Höhe der Schnellfahrstrecke ein mit Getreide beladener Güterzug: Dreizehn Wagen sprangen aus den Schienen, drei davon kippten auf das Gleis, so die Wikipedia-Liste.',
                'sources' => ['wp-liste-2020'],
            ],
        ],
    ],
    'corrections' => [],
    'aliases' => [
        [
            'slug' => '2021-08-26-reims',
            'sources' => ['wp-liste-2020'],
            'fr' => 'La fiche « déraillement à Reims » du 26 août 2021 venait de la liste de Wikipédia, qui situe le train de céréales entre Châlons-en-Champagne et Reims : c\'est le déraillement du train de fret de Saint-Hilaire-au-Temple, près de Châlons, sur lequel le BEA-TT a enquêté. Reims n\'était qu\'un bout du trajet.',
            'en' => 'The "derailment at Reims" record of 26 August 2021 came from the Wikipedia list, which places the grain train between Châlons-en-Champagne and Reims: this is the freight train derailment at Saint-Hilaire-au-Temple, near Châlons, investigated by the BEA-TT. Reims was only one end of the journey.',
            'de' => 'Der Datensatz „Entgleisung in Reims“ vom 26. August 2021 stammte aus der Wikipedia-Liste, die den Getreidezug zwischen Châlons-en-Champagne und Reims verortet: Es ist die Entgleisung des Güterzugs bei Saint-Hilaire-au-Temple nahe Châlons, die die BEA-TT untersucht hat. Reims war nur ein Ende der Strecke.',
        ],
    ],
];
