<?php

// Contexte sourcé de l'accident de Chaumont-Porcien du 22 juillet 1908 (recherche du 21/11/2026).
return [
    'slug' => '1908-07-22-chaumont-porcien',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '22 juillet 1908, Chaumont-Porcien',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (5 morts et 4 blessés), d\'après Le Petit Journal du 23 juillet 1908.',
        ],
        'wp-en-wasigny-renneville' => [
            'provider' => 'wikipedia-en',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Wasigny-Renneville/Berlise railway',
            'publisher' => 'Wikipedia (anglais)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://en.wikipedia.org/wiki/Wasigny%E2%80%93Renneville/Berlise_railway',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ligne à voie métrique des Chemins de fer départementaux des Ardennes, avec ses stations et leurs points kilométriques ; ne parle pas de l\'accident.',
        ],
        'le-petit-journal-1908-07-23' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 23 juillet 1908',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1908-07-23',
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Juillet',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia, sans lien de numérisation ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 22 juillet 1908, un train de ballast a déraillé à Chaumont-Porcien (Ardennes), sur la ligne du chemin de fer départemental de Wasigny à Renneville, à la suite de la défaillance d\'un aiguillage.',
                'en' => 'On 22 July 1908, a ballast train derailed at Chaumont-Porcien (Ardennes), on the departmental railway line from Wasigny to Renneville, after a switch failed.',
                'de' => 'Am 22. Juli 1908 entgleiste in Chaumont-Porcien (Ardennen) auf der Departementsbahn von Wasigny nach Renneville ein Schotterzug, nachdem eine Weiche versagt hatte.',
                'sources' => ['wp-liste-1908'],
            ],
            [
                'fr' => 'La ligne de Wasigny à Renneville, à voie métrique, était exploitée par les Chemins de fer départementaux des Ardennes ; Chaumont-Porcien en était une station, au kilomètre 10 de ses 25 km.',
                'en' => 'The Wasigny to Renneville line, of metre gauge, was run by the Ardennes departmental railways; Chaumont-Porcien was a station on it, at kilometre 10 of its 25 km.',
                'de' => 'Die meterspurige Strecke von Wasigny nach Renneville wurde von den Departementsbahnen der Ardennen betrieben; Chaumont-Porcien war eine Station an ihr, bei Kilometer 10 von insgesamt 25 km.',
                'sources' => ['wp-en-wasigny-renneville'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un wagon transportant des ouvriers s\'est renversé et a été écrasé ; on en a retiré cinq morts et quatre blessés, selon la liste de Wikipédia, qui cite Le Petit Journal du 23 juillet 1908.',
                'en' => 'A wagon carrying workers overturned and was crushed; five dead and four injured were taken from it, according to the Wikipedia list, which cites Le Petit Journal of 23 July 1908.',
                'de' => 'Ein Wagen mit Arbeitern kippte um und wurde zerdrückt; aus ihm wurden fünf Tote und vier Verletzte geborgen, laut der Wikipedia-Liste, die Le Petit Journal vom 23. Juli 1908 zitiert.',
                'sources' => ['wp-liste-1908'],
            ],
        ],
    ],
    'corrections' => [],
];
