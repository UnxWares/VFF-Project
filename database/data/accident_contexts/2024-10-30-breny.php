<?php

// Contexte sourcé de du déraillement d'un train de fret à Oulchy-Breny (Aisne) du 30 octobre 2024 (recherche du 23/11/2026).
return [
    'slug' => '2024-10-30-breny',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2024-10-30-breny' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un train de fret à Oulchy-Breny (02)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/oulchy-breny-02-30-10-2024-deraillement-d-un-train-r504.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 30 octobre 2024, le train de fret n° 416 822 de Captrain, qui allait de Châlons-en-Champagne à Oulchy-Breny desservir un site industriel avec 23 wagons vides (387 m, 652 t), a déraillé à 7 h 57 sur une zone de brèches sous la voie. L\'engin moteur et le premier wagon se sont couchés sur le côté gauche et le deuxième wagon a aussi déraillé. Le conducteur n\'a pas été blessé et les deux voies sont très abîmées sur plus de 200 m.',
                'en' => 'On 30 October 2024, Captrain freight train no. 416 822, running from Châlons-en-Champagne to Oulchy-Breny to serve an industrial site with 23 empty wagons (387 m, 652 t), derailed at 7.57 am on a stretch where the ground under the track had been breached. The locomotive and the first wagon fell onto their left side and the second wagon also derailed. The driver was not injured and both tracks are badly damaged over more than 200 m.',
                'de' => 'Am 30. Oktober 2024 entgleiste der Güterzug Nr. 416 822 von Captrain, der mit 23 leeren Wagen (387 m, 652 t) von Châlons-en-Champagne nach Oulchy-Breny fuhr, um ein Industriegelände zu bedienen, um 7.57 Uhr auf einem Abschnitt, in dem der Untergrund unter dem Gleis durchbrochen war. Die Lokomotive und der erste Wagen kippten auf die linke Seite, und auch der zweite Wagen entgleiste. Der Lokführer blieb unverletzt, und beide Gleise sind auf mehr als 200 m stark beschädigt.',
                'sources' => ['bea-tt-2024-10-30-breny'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 7 août 2026, classé sous les thèmes déraillement, destruction de plateforme et inondation, avec des recommandations adressées à SNCF Réseau, à la DGPR et à la DGSCGC.',
                'en' => 'The BEA-TT published its final report on 7 August 2026, filed under derailment, destruction of the trackbed and flooding, with recommendations addressed to SNCF Réseau, the DGPR and the DGSCGC.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 7. August 2026, verschlagwortet mit Entgleisung, Zerstörung des Bahnkörpers und Überschwemmung, mit Empfehlungen an SNCF Réseau, die DGPR und die DGSCGC.',
                'sources' => ['bea-tt-2024-10-30-breny'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2024-10-30-breny'],
            'fr' => 'Le BEA-TT précise que le conducteur n\'a pas été blessé.',
            'en' => 'The BEA-TT states that the driver was not injured.',
            'de' => 'Die BEA-TT gibt an, dass der Lokführer nicht verletzt wurde.',
        ],
    ],
];
