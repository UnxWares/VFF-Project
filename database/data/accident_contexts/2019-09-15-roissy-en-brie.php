<?php

// Contexte sourcé de de la collision entre un train Transilien et un véhicule léger au passage à niveau n° 8 de Roissy-en-Brie (Seine-et-Marne) du 15 septembre 2019 (recherche du 23/11/2026).
return [
    'slug' => '2019-09-15-roissy-en-brie',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2019-09-15-roissy-en-brie' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un train et un VL au PN 8 à Roissy-en-Brie (77)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/roissy-en-brie-77-15-09-2019-collision-entre-un-r285.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 15 septembre 2019, un train Transilien de Paris-Est à Provins a heurté à 100 km/h un véhicule léger arrêté sur le passage à niveau n° 8. Les quatre occupants de la voiture ont pu l\'évacuer juste avant le choc. Neuf des 44 personnes présentes dans le train ont été légèrement blessées.',
                'en' => 'On 15 September 2019 a Transilien train from Paris-Est to Provins hit at 100 km/h a light vehicle stopped on level crossing no. 8. The four occupants of the car managed to get out just before the impact. Nine of the 44 people in the train were slightly injured.',
                'de' => 'Am 15. September 2019 prallte ein Transilien von Paris-Est nach Provins mit 100 km/h auf ein auf dem Bahnübergang Nr. 8 stehendes leichtes Fahrzeug. Die vier Insassen des Autos konnten es kurz vor dem Aufprall verlassen. Neun der 44 Personen im Zug wurden leicht verletzt.',
                'sources' => ['bea-tt-2019-09-15-roissy-en-brie'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 29 mars 2021, avec des recommandations adressées à la commune de Roissy-en-Brie et à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 29 March 2021, with recommendations addressed to the commune of Roissy-en-Brie and SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 29. März 2021, mit Empfehlungen an die Gemeinde Roissy-en-Brie und SNCF Réseau.',
                'sources' => ['bea-tt-2019-09-15-roissy-en-brie'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 9,
            'sources' => ['bea-tt-2019-09-15-roissy-en-brie'],
            'fr' => 'Le BEA-TT compte neuf blessés légers parmi les occupants du train ; les occupants de la voiture ont évacué avant le choc.',
            'en' => 'The BEA-TT counts nine slightly injured among the train\'s occupants; the car\'s occupants got out before the impact.',
            'de' => 'Die BEA-TT zählt neun Leichtverletzte unter den Zuginsassen; die Autoinsassen stiegen vor dem Aufprall aus.',
        ],
    ],
];
