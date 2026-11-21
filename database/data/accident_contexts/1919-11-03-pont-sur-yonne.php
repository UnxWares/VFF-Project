<?php

// Contexte sourcé de l'accident de Pont-sur-Yonne du 3 novembre 1919 (recherche du 21/11/2026).
return [
    'slug' => '1919-11-03-pont-sur-yonne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '3 novembre, Pont-sur-Yonne',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Heure, lieu, trains, cause attribuée à une fuite de vapeur, 20 morts et une soixantaine de blessés ; renvoie à quatre articles de presse.',
        ],
        'pp-1919-11-05' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien, 5 novembre 1919',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1919-11-05',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k567069v.r=tamponne.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
        'pp-1919-11-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien, 6 novembre 1919',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1919-11-06',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k567070s.image.langFR.r=tamponne',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
        'temps-1919-11-07' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps, 7 novembre 1919',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1919-11-07',
            'locator' => 'p. 4',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k243820b/f4.image.r=tamponnement.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 novembre 1919, vers 22 h 30, sur la ligne de Paris à Dijon, peu après la gare de Pont-sur-Yonne, le Simplon-Express est à l\'arrêt.',
                'en' => 'On 3 November 1919, at about 10.30 pm, on the Paris-Dijon line, shortly after Pont-sur-Yonne station, the Simplon Express was standing still.',
                'de' => 'Am 3. November 1919 gegen 22.30 Uhr stand der Simplon-Express auf der Strecke Paris-Dijon kurz hinter dem Bahnhof Pont-sur-Yonne.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
        ],
        'course' => [
            [
                'fr' => 'Il est percuté par le rapide Paris-Genève. Les dernières voitures du train tamponné sont les plus touchées.',
                'en' => 'It was struck by the Paris-Geneva fast train. The last coaches of the struck train suffered the most.',
                'de' => 'Er wurde vom Schnellzug Paris-Genf gerammt. Die letzten Wagen des gerammten Zuges wurden am stärksten getroffen.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de 20 morts et d\'une soixantaine de blessés.',
                'en' => 'The toll is 20 dead and about sixty injured.',
                'de' => 'Die Bilanz beträgt 20 Tote und etwa sechzig Verletzte.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le mécanicien du rapide n\'a pas vu les signaux du block-système, gêné par une fuite de vapeur sur sa machine, qui n\'avait pas été réparée malgré ses demandes. Cette explication provient d\'une seule page, appuyée sur des articles de presse de l\'époque non consultés.',
                'en' => 'According to Wikipedia\'s list, the driver of the fast train did not see the block-system signals, hampered by a steam leak on his engine that had not been repaired despite his requests. This explanation comes from a single page, based on contemporary press articles that were not consulted.',
                'de' => 'Laut der Wikipedia-Liste sah der Lokführer des Schnellzugs die Signale des Blocksystems nicht, da ihn ein Dampfaustritt an seiner Lokomotive behinderte, der trotz seiner Bitten nicht repariert worden war. Diese Erklärung stammt von einer einzigen Seite, die sich auf nicht eingesehene zeitgenössische Presseartikel stützt.',
                'sources' => [
                    'wp-liste-1919',
                ],
            ],
        ],
    ],
];
