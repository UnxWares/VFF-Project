<?php

// Contexte sourcé de de la collision entre un TER et un véhicule léger au passage à niveau n° 302 de Saint-Étienne (Loire) du 7 mai 2019 (recherche du 23/11/2026).
return [
    'slug' => '2019-05-07-saint-etienne',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2019-05-07-saint-etienne' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un TER et un VL sur le PN n° 302 à Saint-Etienne (42)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-etienne-42-7-05-2019-collision-entre-un-ter-r281.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 7 mai 2019, un TER de Boën-sur-Lignon à Saint-Étienne-Châteaucreux a percuté à 88 km/h un véhicule léger sur le passage à niveau n° 302. Il n\'y a eu aucun blessé, ni dans la voiture, qui n\'avait qu\'un occupant, ni dans le train, qui transportait 14 personnes et n\'a pas déraillé.',
                'en' => 'On 7 May 2019 a regional train from Boën-sur-Lignon to Saint-Étienne-Châteaucreux hit at 88 km/h a light vehicle on level crossing no. 302. Nobody was injured, either in the car, which had a single occupant, or in the train, which carried 14 people and did not derail.',
                'de' => 'Am 7. Mai 2019 prallte ein Regionalzug von Boën-sur-Lignon nach Saint-Étienne-Châteaucreux mit 88 km/h auf dem Bahnübergang Nr. 302 auf ein leichtes Fahrzeug. Niemand wurde verletzt, weder im Auto, das nur einen Insassen hatte, noch im Zug, der 14 Personen beförderte und nicht entgleiste.',
                'sources' => ['bea-tt-2019-05-07-saint-etienne'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 21 juillet 2020, avec des recommandations adressées à Saint-Étienne Métropole et à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 21 July 2020, with recommendations addressed to Saint-Étienne Métropole and SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 21. Juli 2020, mit Empfehlungen an Saint-Étienne Métropole und SNCF Réseau.',
                'sources' => ['bea-tt-2019-05-07-saint-etienne'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2019-05-07-saint-etienne'],
            'fr' => 'Le BEA-TT indique qu\'aucun blessé n\'est à déplorer.',
            'en' => 'The BEA-TT states that no one was injured.',
            'de' => 'Die BEA-TT gibt an, dass niemand verletzt wurde.',
        ],
    ],
];
