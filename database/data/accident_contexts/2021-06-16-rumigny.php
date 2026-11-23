<?php

// Contexte sourcé de de la collision entre un train de fret et un convoi exceptionnel au passage à niveau n° 17 de Rumigny (Ardennes) du 16 juin 2021 (recherche du 23/11/2026).
return [
    'slug' => '2021-06-16-rumigny',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2021-06-16-rumigny' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un train MD et un TE sur le PN n° 17 à Rumigny (08)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/rumigny-08-16-06-2021-collision-entre-un-train-de-r304.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 16 juin 2021, un convoi exceptionnel s\'est immobilisé sur le passage à niveau n° 17, bloqué par sa semi-remorque surbaissée qui portait un bateau. Un train de transport de matières dangereuses l\'a percuté à 92 km/h, puis a déraillé et basculé. Certains wagons ont été éventrés, la semi-remorque et le bateau ont été détruits par un incendie et l\'infrastructure est fortement endommagée. Deux personnes ont été blessées, dont le conducteur du train.',
                'en' => 'On 16 June 2021 an exceptional road convoy came to a halt on level crossing no. 17, stuck by its low-loader semi-trailer carrying a boat. A train carrying dangerous goods hit it at 92 km/h, then derailed and overturned. Some wagons were ripped open, the semi-trailer and the boat were destroyed by fire and the infrastructure is badly damaged. Two people were injured, including the train driver.',
                'de' => 'Am 16. Juni 2021 blieb ein Schwertransport auf dem Bahnübergang Nr. 17 stehen, festgesetzt durch seinen Tieflader mit einem Boot. Ein Gefahrgutzug prallte mit 92 km/h auf ihn, entgleiste und kippte um. Einige Wagen wurden aufgerissen, der Auflieger und das Boot wurden durch einen Brand zerstört, und die Infrastruktur ist stark beschädigt. Zwei Personen wurden verletzt, darunter der Lokführer.',
                'sources' => ['bea-tt-2021-06-16-rumigny'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 9 août 2023, avec des recommandations adressées à la DSR, aux gestionnaires d\'infrastructures ferroviaires, à l\'EPSF et à la DGITM.',
                'en' => 'The BEA-TT published its final report on 9 August 2023, with recommendations addressed to the DSR, railway infrastructure managers, the EPSF and the DGITM.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 9. August 2023, mit Empfehlungen an die DSR, die Eisenbahninfrastrukturbetreiber, die EPSF und die DGITM.',
                'sources' => ['bea-tt-2021-06-16-rumigny'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 2,
            'sources' => ['bea-tt-2021-06-16-rumigny'],
            'fr' => 'Le BEA-TT compte deux blessés, dont le conducteur du train.',
            'en' => 'The BEA-TT counts two injured people, including the train driver.',
            'de' => 'Die BEA-TT zählt zwei Verletzte, darunter den Lokführer.',
        ],
    ],
];
