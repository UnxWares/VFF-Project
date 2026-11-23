<?php

// Contexte sourcé de de la dérive d'une rame du tramway T4 à Clichy-sous-Bois (Seine-Saint-Denis) du 8 novembre 2020 (recherche du 23/11/2026).
return [
    'slug' => '2020-11-08-clichy-sous-bois',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2020-11-08-clichy-sous-bois' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Dérive d’un tram-train à Clichy-sous-Bois (93)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/clichy-sous-bois-93-8-11-2020-derive-d-une-rame-de-r317.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 8 novembre 2020, une rame du tramway T4, avec une quarantaine de voyageurs, s\'est arrêtée au pied d\'un feu en haut d\'une rampe à 7 %, puis est partie en dérive dans le sens de la descente jusqu\'à 63 km/h. Le conducteur l\'a stoppée par un freinage d\'urgence après 430 mètres. Il n\'y a eu ni blessé ni dégât matériel.',
                'en' => 'On 8 November 2020 a tram of line T4, with about forty passengers, stopped at a signal at the top of a 7% gradient, then ran away down the slope up to 63 km/h. The driver stopped it with an emergency brake application after 430 metres. There were no injuries and no material damage.',
                'de' => 'Am 8. November 2020 hielt eine Straßenbahn der Linie T4 mit etwa vierzig Fahrgästen an einem Signal oben auf einer Steigung von 7 %, rollte dann in Gefällerichtung mit bis zu 63 km/h ab. Der Fahrer brachte sie nach 430 Metern mit einer Schnellbremsung zum Stehen. Es gab weder Verletzte noch Sachschaden.',
                'sources' => ['bea-tt-2020-11-08-clichy-sous-bois'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 2 février 2022 (thèmes : conception, dérive, tramway), avec des recommandations adressées à SNCF Voyageurs, à la DGITM et à la DLPAJ.',
                'en' => 'The BEA-TT published its final report on 2 February 2022 (themes: design, runaway, tram), with recommendations addressed to SNCF Voyageurs, the DGITM and the DLPAJ.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 2. Februar 2022 (Themen: Konstruktion, Abrollen, Straßenbahn), mit Empfehlungen an SNCF Voyageurs, die DGITM und die DLPAJ.',
                'sources' => ['bea-tt-2020-11-08-clichy-sous-bois'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2020-11-08-clichy-sous-bois'],
            'fr' => 'Le BEA-TT indique qu\'il n\'y a pas eu de blessé.',
            'en' => 'The BEA-TT states that nobody was injured.',
            'de' => 'Die BEA-TT gibt an, dass niemand verletzt wurde.',
        ],
    ],
];
