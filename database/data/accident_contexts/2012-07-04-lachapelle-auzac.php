<?php

// Contexte sourcé de du heurt d'un engin de travaux par un train Intercités à Lachapelle-Auzac (Lot) du 4 juillet 2012 (recherche du 23/11/2026).
return [
    'slug' => '2012-07-04-lachapelle-auzac',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2012-07-04-lachapelle-auzac' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Heurt d’un engin de travaux sur caténaires par un train de voyageurs à Lachapelle-Auzac (46)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/lachapelle-auzac-46-4-07-2012-heurt-d-un-engin-de-r181.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 4 juillet 2012, un train Intercités de Toulouse à Paris a heurté à 30 km/h un engin de travaux sur caténaires qui traversait la voie 2 pour rejoindre un chantier de maintenance sur la voie 1, fermée à la circulation. L\'engin s\'est encastré sous la locomotive, qui n\'a pas déraillé. Il n\'y a pas eu de blessés.',
                'en' => 'On 4 July 2012 an Intercités train from Toulouse to Paris hit at 30 km/h a catenary works vehicle that was crossing track 2 to reach a maintenance worksite on track 1, closed to traffic. The vehicle became wedged under the locomotive, which did not derail. Nobody was injured.',
                'de' => 'Am 4. Juli 2012 prallte ein Intercités-Zug von Toulouse nach Paris mit 30 km/h auf ein Fahrleitungs-Arbeitsfahrzeug, das Gleis 2 querte, um zu einer Wartungsbaustelle auf dem für den Verkehr gesperrten Gleis 1 zu gelangen. Das Fahrzeug verkeilte sich unter der Lokomotive, die nicht entgleiste. Es gab keine Verletzten.',
                'sources' => ['bea-tt-2012-07-04-lachapelle-auzac'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 4 septembre 2013 (avec une animation reconstituant le déroulement de l\'accident), puis les recommandations et les réponses de RFF et de la SNCF le 26 novembre 2013.',
                'en' => 'The BEA-TT published its final report on 4 September 2013 (with an animation reconstructing the sequence of the accident), then the recommendations and the replies of RFF and the SNCF on 26 November 2013.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 4. September 2013 (mit einer Animation zum Unfallhergang), dann am 26. November 2013 die Empfehlungen und die Antworten von RFF und SNCF.',
                'sources' => ['bea-tt-2012-07-04-lachapelle-auzac'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2012-07-04-lachapelle-auzac'],
            'fr' => 'Le BEA-TT indique qu\'il n\'y a pas eu de blessés.',
            'en' => 'The BEA-TT states that nobody was injured.',
            'de' => 'Die BEA-TT gibt an, dass es keine Verletzten gab.',
        ],
    ],
];
