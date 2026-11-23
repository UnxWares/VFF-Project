<?php

// Contexte sourcé de de la collision entre un TER et un autocar scolaire au passage à niveau n° 42 de Cevins (Savoie) du 24 janvier 2023 (recherche du 23/11/2026).
return [
    'slug' => '2023-01-24-cevins',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2023-01-24-cevins' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un TER et un autocar sur le PN n° 42 à Cevins (73)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/cevins-73-24-01-2023-collision-entre-un-ter-et-un-r336.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 24 janvier 2023, peu après 7 h, le TER de Bourg-Saint-Maurice à Aix-les-Bains, qui avait une cinquantaine de passagers, a percuté un autocar de transport scolaire immobilisé sur le passage à niveau n° 42, avec son seul conducteur à bord. L\'autocar s\'est disloqué en deux parties et son conducteur a été grièvement blessé. Trois voyageurs du train ont été blessés par le heurt latéral d\'éléments du car. L\'infrastructure ferroviaire est fortement endommagée.',
                'en' => 'On 24 January 2023, shortly after 7 am, the regional train from Bourg-Saint-Maurice to Aix-les-Bains, with about fifty passengers, hit a school coach standing on level crossing no. 42 with only its driver aboard. The coach broke into two parts and its driver was gravely injured. Three train passengers were injured when parts of the coach struck the train sideways. The railway infrastructure is badly damaged.',
                'de' => 'Am 24. Januar 2023 kurz nach 7 Uhr prallte der Regionalzug von Bourg-Saint-Maurice nach Aix-les-Bains mit etwa fünfzig Fahrgästen auf einen Schulbus, der nur mit seinem Fahrer besetzt auf dem Bahnübergang Nr. 42 stand. Der Bus brach in zwei Teile auseinander, und sein Fahrer wurde schwer verletzt. Drei Fahrgäste des Zuges wurden durch den seitlichen Aufprall von Busteilen verletzt. Die Eisenbahninfrastruktur ist stark beschädigt.',
                'sources' => ['bea-tt-2023-01-24-cevins'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 22 août 2025, avec des recommandations à la DSR, la DGITM, la région Auvergne-Rhône-Alpes, Transdev Savoie et SNCF Réseau, puis un dossier pour approfondir le sujet le 12 novembre 2025.',
                'en' => 'The BEA-TT published its final report on 22 August 2025, with recommendations to the DSR, the DGITM, the Auvergne-Rhône-Alpes region, Transdev Savoie and SNCF Réseau, then a resource file to explore the subject further on 12 November 2025.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 22. August 2025, mit Empfehlungen an die DSR, die DGITM, die Region Auvergne-Rhône-Alpes, Transdev Savoie und SNCF Réseau, am 12. November 2025 dann ein Vertiefungsdossier.',
                'sources' => ['bea-tt-2023-01-24-cevins'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 4,
            'sources' => ['bea-tt-2023-01-24-cevins'],
            'fr' => 'Le BEA-TT compte le conducteur de l\'autocar, grièvement blessé, et trois voyageurs du train blessés.',
            'en' => 'The BEA-TT counts the coach driver, gravely injured, and three injured train passengers.',
            'de' => 'Die BEA-TT zählt den schwer verletzten Busfahrer und drei verletzte Fahrgäste des Zuges.',
        ],
    ],
];
