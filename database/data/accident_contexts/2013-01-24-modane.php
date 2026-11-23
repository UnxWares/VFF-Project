<?php

// Contexte sourcé de de la dérive d'un wagon sur une voie en forte pente à Modane (Savoie) du 24 janvier 2013 (recherche du 23/11/2026).
return [
    'slug' => '2013-01-24-modane',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2013-01-24-modane' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Dérive et collision avec un train en stationnement du wagon de queue d’un train de marchandises à Modane (73)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/modane-73-24-01-2013-derive-d-un-wagon-sur-une-r194.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 24 janvier 2013, sur un train de Fret SNCF entre Modane et Orbassano, en Italie, l\'attelage avant du dernier wagon s\'est rompu alors que le train était sur un tronçon en pente. Le wagon est parti en dérive à contresens et a percuté violemment un train arrêté en gare. Le matériel roulant est fortement endommagé et trois agents ont été blessés.',
                'en' => 'On 24 January 2013, on a Fret SNCF train between Modane and Orbassano in Italy, the coupling in front of the last wagon broke while the train was on a sloping section. The wagon ran away in the opposite direction and violently struck a train standing in a station. The rolling stock is badly damaged and three staff were injured.',
                'de' => 'Am 24. Januar 2013 riss bei einem Zug von Fret SNCF zwischen Modane und Orbassano in Italien die Kupplung vor dem letzten Wagen, als der Zug auf einem Gefällabschnitt stand. Der Wagen rollte in Gegenrichtung ab und prallte heftig auf einen im Bahnhof stehenden Zug. Das rollende Material ist stark beschädigt, und drei Mitarbeiter wurden verletzt.',
                'sources' => ['bea-tt-2013-01-24-modane'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 17 juillet 2015 (thèmes : dérive, freins, rupture d\'attelage), avec des recommandations adressées à la direction du matériel de la SNCF, à ERMEWA et à Faiveley Transport.',
                'en' => 'The BEA-TT published its final report on 17 July 2015 (themes: runaway, brakes, coupling failure), with recommendations addressed to the SNCF rolling stock department, ERMEWA and Faiveley Transport.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 17. Juli 2015 (Themen: Abrollen, Bremsen, Kupplungsbruch), mit Empfehlungen an die Fahrzeugdirektion der SNCF, ERMEWA und Faiveley Transport.',
                'sources' => ['bea-tt-2013-01-24-modane'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 3,
            'sources' => ['bea-tt-2013-01-24-modane'],
            'fr' => 'Le BEA-TT compte trois agents blessés.',
            'en' => 'The BEA-TT counts three injured staff.',
            'de' => 'Die BEA-TT zählt drei verletzte Mitarbeiter.',
        ],
    ],
];
