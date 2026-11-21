<?php

// Contexte sourcé de la collision en gare de Banassac du 13 octobre 1913 (recherche du 21/11/2026).
return [
    'slug' => '1913-10-13-marvejols',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1913' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1913',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1913&oldid=233727923#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Ligne de Béziers à Neussargues, train de voyageurs contre la machine d\'un train de marchandises en manoeuvre en gare de Banassac, trois morts et une dizaine de blessés ; jugement du tribunal de Marvejols le 5 février 1914 ; renvoie à Le Temps des 15 octobre 1913 et 7 février 1914 (non lus).',
        ],
        'wp-gare-banassac' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Gare de Banassac - La Canourgue',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'introduction et section Histoire',
            'url' => 'https://fr.wikipedia.org/wiki/Gare_de_Banassac_-_La_Canourgue',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Situe la gare sur la commune de Banassac-Canilhac (Lozère), sur la ligne de Béziers à Neussargues, mise en service le 14 août 1883 par la Compagnie du Midi ; ne parle pas de l\'accident.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'La gare de Banassac - La Canourgue se trouve sur la commune de Banassac-Canilhac, en Lozère, sur la ligne de Béziers à Neussargues, dite ligne des Causses. Elle a été mise en service le 14 août 1883 par la Compagnie du Midi, avec la section de Sévérac-le-Château à Banassac.',
                'en' => 'Banassac - La Canourgue station lies in the commune of Banassac-Canilhac, in Lozère, on the Béziers to Neussargues line, also called the Causses line. It was opened on 14 August 1883 by the Compagnie du Midi, together with the section from Sévérac-le-Château to Banassac.',
                'de' => 'Der Bahnhof Banassac - La Canourgue liegt in der Gemeinde Banassac-Canilhac in der Lozère an der Strecke von Béziers nach Neussargues, auch Ligne des Causses genannt. Er wurde am 14. August 1883 von der Compagnie du Midi zusammen mit dem Abschnitt von Sévérac-le-Château nach Banassac eröffnet.',
                'sources' => ['wp-gare-banassac'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le 13 octobre 1913, vers 13 h 50, un train de voyageurs venant de Béziers a heurté en gare de Banassac la machine d\'un train de marchandises qui manoeuvrait, selon la liste de Wikipédia.',
                'en' => 'On 13 October 1913, at about 1.50 pm, a passenger train from Béziers struck the engine of a goods train that was shunting at Banassac station, according to the Wikipedia list.',
                'de' => 'Am 13. Oktober 1913 stieß gegen 13.50 Uhr im Bahnhof Banassac ein Reisezug aus Béziers nach der Wikipedia-Liste auf die Lokomotive eines rangierenden Güterzugs.',
                'sources' => ['wp-liste-1913'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait trois morts et une dizaine de blessés, selon la liste de Wikipédia.',
                'en' => 'The accident killed three and injured about ten, according to the Wikipedia list.',
                'de' => 'Der Unfall forderte nach der Wikipedia-Liste drei Tote und etwa zehn Verletzte.',
                'sources' => ['wp-liste-1913'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le 5 février 1914, le tribunal correctionnel de Marvejols a déclaré responsables de l\'accident le chef de gare intérimaire et le comptable de la gare : un mois de prison et 16 francs d\'amende pour le premier, deux mois de prison avec sursis et 16 francs d\'amende pour le second, selon la liste de Wikipédia.',
                'en' => 'On 5 February 1914, the criminal court of Marvejols held the acting station master and the station accountant responsible for the accident: one month in prison and a 16-franc fine for the first, two months suspended and a 16-franc fine for the second, according to the Wikipedia list.',
                'de' => 'Am 5. Februar 1914 erklärte das Strafgericht von Marvejols den stellvertretenden Bahnhofsvorsteher und den Buchhalter des Bahnhofs für den Unfall verantwortlich: ein Monat Haft und 16 Francs Geldstrafe für den ersten, zwei Monate auf Bewährung und 16 Francs Geldstrafe für den zweiten, nach der Wikipedia-Liste.',
                'sources' => ['wp-liste-1913'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '48017',
            'sources' => ['wp-liste-1913', 'wp-gare-banassac'],
            'fr' => 'La liste de Wikipédia situe l\'accident en gare de Banassac, qui est sur la commune de Banassac-Canilhac (48017) selon l\'article sur la gare ; Marvejols n\'est que le siège du tribunal qui a jugé l\'affaire.',
            'en' => 'The Wikipedia list places the accident at Banassac station, which is in the commune of Banassac-Canilhac (48017) according to the article on the station; Marvejols is only the seat of the court that tried the case.',
            'de' => 'Die Wikipedia-Liste verortet den Unfall im Bahnhof Banassac, der nach dem Bahnhofsartikel in der Gemeinde Banassac-Canilhac (48017) liegt; Marvejols ist nur der Sitz des Gerichts, das den Fall verhandelte.',
        ],
    ],
];
