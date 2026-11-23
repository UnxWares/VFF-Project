<?php

// Contexte sourcé de de la collision entre un TER et un convoi exceptionnel au passage à niveau n° 51 du Clerjus (Vosges) du 19 juin 2023 (recherche du 23/11/2026).
return [
    'slug' => '2023-06-19-le-clerjus',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2023-06-19-le-clerjus' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un train et un convoi exceptionnel sur le PN n° 51 au Clerjus (88)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/le-clerjus-88-19-06-2023-collision-entre-un-ter-et-r350.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 19 juin 2023, un TER d\'Épinal à Belfort a percuté un convoi exceptionnel qui transportait une section de mât d\'éolienne, immobilisé sur le passage à niveau n° 51. Neuf voyageurs ont été légèrement blessés. Les dégâts matériels sont importants, tant sur les véhicules que sur l\'infrastructure.',
                'en' => 'On 19 June 2023 a regional train from Épinal to Belfort hit an exceptional road convoy carrying a section of wind turbine mast, stuck on level crossing no. 51. Nine passengers were slightly injured. The material damage is considerable, to both vehicles and infrastructure.',
                'de' => 'Am 19. Juni 2023 prallte ein Regionalzug von Épinal nach Belfort auf einen Schwertransport mit einem Windradmastsegment, der auf dem Bahnübergang Nr. 51 festsaß. Neun Fahrgäste wurden leicht verletzt. Der Sachschaden an Fahrzeugen und Infrastruktur ist erheblich.',
                'sources' => ['bea-tt-2023-06-19-le-clerjus'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a émis une recommandation en cours d\'enquête le 18 août 2023, puis publié son rapport final le 24 décembre 2024, avec des recommandations à la DSR, SNCF Voyageurs, SNCF Réseau et la société LASO France.',
                'en' => 'The BEA-TT issued a recommendation during the investigation on 18 August 2023, then published its final report on 24 December 2024, with recommendations to the DSR, SNCF Voyageurs, SNCF Réseau and the company LASO France.',
                'de' => 'Die BEA-TT sprach am 18. August 2023 während der Untersuchung eine Empfehlung aus und veröffentlichte ihren Abschlussbericht am 24. Dezember 2024, mit Empfehlungen an die DSR, SNCF Voyageurs, SNCF Réseau und die Firma LASO France.',
                'sources' => ['bea-tt-2023-06-19-le-clerjus'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 9,
            'sources' => ['bea-tt-2023-06-19-le-clerjus'],
            'fr' => 'Le BEA-TT compte neuf blessés légers dans le train.',
            'en' => 'The BEA-TT counts nine slightly injured people in the train.',
            'de' => 'Die BEA-TT zählt neun Leichtverletzte im Zug.',
        ],
    ],
];
