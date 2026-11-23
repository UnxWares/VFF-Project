<?php

// Contexte sourcé de de la collision entre un TER et un poids lourd au passage à niveau n° 19 de La Roche-en-Brenil (Côte-d'Or) du 7 juillet 2008 (recherche du 23/11/2026).
return [
    'slug' => '2008-07-07-la-roche-en-brenil',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2008-07-07-la-roche-en-brenil' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un poids lourd et un train à la Roche-en-Brenil',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/la-roche-en-brenil-21-7-07-2008-collision-entre-un-r106.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 7 juillet 2008, un TER d\'Autun à Avallon a percuté un ensemble routier chargé d\'enrobés sur le passage à niveau n° 19, non gardé. L\'ensemble se rendait sur le chantier d\'une zone d\'activités voisine du passage à niveau. Le bilan est de 6 blessés légers, tous passagers du train.',
                'en' => 'On 7 July 2008 a regional train from Autun to Avallon hit a road combination loaded with asphalt mix on level crossing no. 19, which is unguarded. The combination was on its way to a building site in an industrial estate right next to the crossing. The toll is 6 slightly injured, all passengers of the train.',
                'de' => 'Am 7. Juli 2008 prallte ein Regionalzug von Autun nach Avallon auf dem unbeschrankten Bahnübergang Nr. 19 auf einen mit Asphaltmischgut beladenen Lastzug. Dieser war auf dem Weg zur Baustelle eines unmittelbar neben dem Bahnübergang liegenden Gewerbegebiets. Die Bilanz lautet 6 Leichtverletzte, alle Fahrgäste des Zuges.',
                'sources' => ['bea-tt-2008-07-07-la-roche-en-brenil'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 4 février 2010, avec des recommandations adressées à la SNCF, à RFF et à la DHUP.',
                'en' => 'The BEA-TT published its final report on 4 February 2010, with recommendations addressed to the SNCF, RFF and the DHUP.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 4. Februar 2010, mit Empfehlungen an die SNCF, RFF und die DHUP.',
                'sources' => ['bea-tt-2008-07-07-la-roche-en-brenil'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 6,
            'sources' => ['bea-tt-2008-07-07-la-roche-en-brenil'],
            'fr' => 'Le BEA-TT compte six blessés légers, tous passagers du train.',
            'en' => 'The BEA-TT counts six slightly injured, all passengers of the train.',
            'de' => 'Die BEA-TT zählt sechs Leichtverletzte, alle Fahrgäste des Zuges.',
        ],
    ],
];
