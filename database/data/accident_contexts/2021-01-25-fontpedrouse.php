<?php

// Contexte sourcé de du déraillement d'un TER dans le tunnel de La Castagna à Fontpédrouse (Pyrénées-Orientales) du 25 janvier 2021 (recherche du 23/11/2026).
return [
    'slug' => '2021-01-25-fontpedrouse',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2021-01-25-fontpedrouse' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement du train jaune à Fontpédrouse (66)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/fontpedrouse-66-25-01-2021-deraillement-d-un-train-r318.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 25 janvier 2021, un TER sans voyageur de la ligne du train jaune (Villefranche à Latour-de-Carol) a déraillé dans le tunnel de La Castagna après plusieurs enrayages et un dépassement de la vitesse limite autorisée. Le conducteur et le contrôleur à bord sont indemnes. Le côté gauche du train a heurté la paroi du tunnel et plus de 200 m de voie sont abîmés.',
                'en' => 'On 25 January 2021 an empty regional train on the Train Jaune line (Villefranche to Latour-de-Carol) derailed in the La Castagna tunnel after several wheel locks and an overshoot of the permitted speed limit. The driver and the ticket inspector on board were unhurt. The left side of the train struck the tunnel wall and more than 200 m of track are damaged.',
                'de' => 'Am 25. Januar 2021 entgleiste ein Regionalzug ohne Fahrgäste auf der Linie des Train Jaune (Villefranche - Latour-de-Carol) im Tunnel von La Castagna nach mehrfachem Blockieren der Räder und Überschreiten der zulässigen Geschwindigkeit. Der Lokführer und der Zugbegleiter an Bord blieben unverletzt. Die linke Zugseite prallte gegen die Tunnelwand, und mehr als 200 m Gleis sind beschädigt.',
                'sources' => ['bea-tt-2021-01-25-fontpedrouse'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 30 décembre 2022 (thèmes : chemin de fer métrique, déraillement), avec des recommandations adressées à SNCF Voyageurs et à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 30 December 2022 (themes: metre-gauge railway, derailment), with recommendations addressed to SNCF Voyageurs and SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 30. Dezember 2022 (Themen: Meterspurbahn, Entgleisung), mit Empfehlungen an SNCF Voyageurs und SNCF Réseau.',
                'sources' => ['bea-tt-2021-01-25-fontpedrouse'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2021-01-25-fontpedrouse'],
            'fr' => 'Le BEA-TT précise que le conducteur et le contrôleur sont indemnes.',
            'en' => 'The BEA-TT states that the driver and the inspector were unhurt.',
            'de' => 'Die BEA-TT gibt an, dass Lokführer und Zugbegleiter unverletzt blieben.',
        ],
    ],
];
