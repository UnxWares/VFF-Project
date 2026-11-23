<?php

// Contexte sourcé de de la collision entre un TER et un véhicule léger au passage à niveau n° 48 de Pavilly (Seine-Maritime) du 11 avril 2019 (recherche du 23/11/2026).
return [
    'slug' => '2019-04-11-pavilly',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2019-04-11-pavilly' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un train intercités et un VL sur le PN 48 à Pavilly (76)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/pavilly-76-11-04-2019-collision-entre-un-ter-et-un-r283.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 11 avril 2019, un TER de Paris au Havre a percuté à 122 km/h un véhicule léger sur le passage à niveau n° 48, inscrit au programme national de sécurisation et équipé de quatre demi-barrières. Les deux occupantes de la voiture ont été gravement blessées et une seule personne, légèrement, sur les 152 présentes dans le train, qui n\'a pas déraillé.',
                'en' => 'On 11 April 2019 a regional train from Paris to Le Havre hit at 122 km/h a light vehicle on level crossing no. 48, which is in the national safety programme and has four half-barriers. The two occupants of the car were gravely injured and only one person, slightly, of the 152 in the train, which did not derail.',
                'de' => 'Am 11. April 2019 prallte ein Regionalzug von Paris nach Le Havre mit 122 km/h auf dem Bahnübergang Nr. 48, der im nationalen Sicherungsprogramm steht und vier Halbschranken hat, auf ein leichtes Fahrzeug. Die beiden Insassinnen des Autos wurden schwer verletzt, und von den 152 Personen im Zug, der nicht entgleiste, nur eine leicht.',
                'sources' => ['bea-tt-2019-04-11-pavilly'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 10 août 2021 ; l\'analyse des témoignages et des circonstances ne lui a pas permis d\'émettre de recommandation spécifique.',
                'en' => 'The BEA-TT published its final report on 10 August 2021; its analysis of the testimony and circumstances did not allow it to make a specific recommendation.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 10. August 2021; die Auswertung der Zeugenaussagen und Umstände erlaubte ihr keine spezifische Empfehlung.',
                'sources' => ['bea-tt-2019-04-11-pavilly'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 3,
            'sources' => ['bea-tt-2019-04-11-pavilly'],
            'fr' => 'Le BEA-TT compte les deux occupantes de la voiture, gravement blessées, et une personne légèrement blessée dans le train.',
            'en' => 'The BEA-TT counts the car\'s two occupants, gravely injured, and one person slightly injured in the train.',
            'de' => 'Die BEA-TT zählt die beiden schwer verletzten Autoinsassinnen und eine leicht verletzte Person im Zug.',
        ],
    ],
];
