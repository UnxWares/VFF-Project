<?php

// Contexte sourcé de la collision d'un train de voyageurs pour Lille avec un train de marchandises en manœuvre près de la gare de Leforest, ligne de Paris à Lille, de décembre 1909 (recherche du 21/11/2026).
return [
    'slug' => '1909-12-22-leforest',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1909' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1909',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1909&oldid=241222447#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 23 décembre 1909, d\'après Le Temps du 24 décembre : sur la ligne Paris - Lille, près de la gare de Leforest, vers 18 h 30, un train de voyageurs pour Lille prend en écharpe un train de marchandises en manœuvre, dont il renverse la machine ; le fourgon et les trois voitures de tête du train tamponneur s\'écrasent contre sa locomotive ; un conducteur est tué, sept voyageurs blessés.',
        ],
        'ddb-1909-12-23-tagwacht' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Der Paris-Liller Personenzug',
            'publisher' => 'Schwäbische Tagwacht (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1909-12-23',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/ALFGCUGKQ6BK3V5EKCA3WDJWX7GET4JT?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : le train de voyageurs Paris - Lille a heurté la veille au soir un train de marchandises près de la station de Douai ; un agent du chemin de fer tué, huit voyageurs blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Vers 18 h 30, sur la ligne de Paris à Lille, près de la gare de Leforest, un train de voyageurs allant à Lille a pris en écharpe un train de marchandises en manœuvre et renversé sa machine. Le fourgon et les trois premières voitures du train de voyageurs se sont écrasés contre leur propre locomotive.',
                'en' => 'At about 6.30 pm on the Paris to Lille line, near Leforest station, a Lille-bound passenger train struck the side of a goods train being shunted and overturned its engine. The van and the first three coaches of the passenger train were crushed against their own engine.',
                'de' => 'Gegen 18.30 Uhr erfasste an der Strecke Paris-Lille beim Bahnhof Leforest ein Personenzug nach Lille einen rangierenden Güterzug von der Seite und warf dessen Lokomotive um. Packwagen und die drei ersten Wagen des Personenzugs wurden gegen die eigene Lokomotive gedrückt.',
                'sources' => ['wp-liste-1909', 'ddb-1909-12-23-tagwacht'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent d\'accompagnement du train a été tué. Sept voyageurs ont été blessés selon la liste de Wikipédia, huit selon la presse allemande.',
                'en' => 'A train crew member was killed. Seven passengers were injured according to the Wikipedia list, eight according to the German press.',
                'de' => 'Ein Zugbegleiter wurde getötet. Sieben Reisende wurden nach der Wikipedia-Liste verletzt, nach der deutschen Presse acht.',
                'sources' => ['wp-liste-1909', 'ddb-1909-12-23-tagwacht'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La presse allemande place la collision près de la station de Douai ; la liste de Wikipédia près de la gare de Leforest, sur la même ligne. La liste la date du 23 décembre vers 18 h 30, d\'après Le Temps du 24 ; or la Schwäbische Tagwacht, parue le 23 décembre, la dit survenue la veille au soir, soit le 22.',
                'en' => 'The German press places the collision near Douai station, the Wikipedia list near Leforest station, on the same line. The list dates it 23 December at about 6.30 pm, after Le Temps of the 24th; but the Schwäbische Tagwacht, printed on 23 December, says it happened the evening before, the 22nd.',
                'de' => 'Die deutsche Presse verlegt den Zusammenstoß in die Nähe der Station Douai, die Wikipedia-Liste in die Nähe des Bahnhofs Leforest an derselben Strecke. Die Liste datiert ihn nach Le Temps vom 24. auf den 23. Dezember gegen 18.30 Uhr; die am 23. Dezember erschienene Schwäbische Tagwacht meldet ihn aber vom Vorabend, also vom 22.',
                'sources' => ['wp-liste-1909', 'ddb-1909-12-23-tagwacht'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_on',
            'value' => '1909-12-22',
            'sources' => ['ddb-1909-12-23-tagwacht', 'wp-liste-1909'],
            'fr' => 'Le 22 décembre 1909 au soir : la Schwäbische Tagwacht, parue le 23 décembre, rapporte la collision de « hier soir » ; la fiche donnait le 23, d\'après la liste de Wikipédia (Le Temps du 24 décembre).',
            'en' => 'Evening of 22 December 1909: the Schwäbische Tagwacht, printed on 23 December, reports the collision as "last night"; the record gave the 23rd, after the Wikipedia list (Le Temps of 24 December).',
            'de' => 'Abend des 22. Dezember 1909: Die am 23. Dezember erschienene Schwäbische Tagwacht meldet den Zusammenstoß von „gestern abend“; der Eintrag nannte nach der Wikipedia-Liste (Le Temps vom 24. Dezember) den 23.',
        ],
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1909', 'ddb-1909-12-23-tagwacht'],
            'fr' => 'Un agent tué selon la liste de Wikipédia (Le Temps du 24 décembre 1909) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One railwayman killed according to the Wikipedia list (Le Temps of 24 December 1909) and the German press; the record gave no number of deaths.',
            'de' => 'Ein Eisenbahner getötet nach der Wikipedia-Liste (Le Temps vom 24. Dezember 1909) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
