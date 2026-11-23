<?php

// Contexte sourcé de du déraillement d'un train de travaux à Saint-Florentin-Vergigny (Yonne) du 17 janvier 2024 (recherche du 23/11/2026).
return [
    'slug' => '2024-01-18-saint-florentin',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2024-01-18-saint-florentin' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un train de travaux à Saint-Florentin-Vergigny (89)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-florentin-89-17-01-2024-deraillement-d-un-r477.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 17 janvier 2024, un train de travaux a manoeuvré sur une voie en impasse et heurté deux véhicules stationnés. Malgré les ordres d\'arrêt répétés du chef de la manoeuvre, l\'ensemble a poussé le butoir puis heurté des bungalows au bout de la voie. Il n\'y a pas eu de victimes, mais les dégâts sont importants.',
                'en' => 'On 17 January 2024 a works train shunting on a dead-end track hit two parked vehicles. Despite repeated stop orders from the shunting leader, the whole set pushed the buffer stop and then hit site huts at the end of the track. There were no casualties, but the damage is considerable.',
                'de' => 'Am 17. Januar 2024 rangierte ein Bauzug auf einem Stumpfgleis und prallte auf zwei abgestellte Fahrzeuge. Trotz wiederholter Haltebefehle des Rangierleiters schob die Einheit den Prellbock und stieß dann gegen Baucontainer am Gleisende. Es gab keine Opfer, aber der Schaden ist erheblich.',
                'sources' => ['bea-tt-2024-01-18-saint-florentin'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Sans attendre son rapport final, le BEA-TT a émis une recommandation à SNCF Réseau le 22 février 2024. Il a publié son rapport le 20 décembre 2024, avec des recommandations à SNCF Réseau et à ETF S.',
                'en' => 'Without waiting for its final report, the BEA-TT issued a recommendation to SNCF Réseau on 22 February 2024. It published its report on 20 December 2024, with recommendations to SNCF Réseau and ETF S.',
                'de' => 'Ohne den Abschlussbericht abzuwarten, sprach die BEA-TT am 22. Februar 2024 eine Empfehlung an SNCF Réseau aus. Sie veröffentlichte ihren Bericht am 20. Dezember 2024, mit Empfehlungen an SNCF Réseau und ETF S.',
                'sources' => ['bea-tt-2024-01-18-saint-florentin'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2024-01-18-saint-florentin'],
            'fr' => 'Le BEA-TT indique que l\'accident n\'a pas fait de victimes.',
            'en' => 'The BEA-TT states that the accident caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass der Unfall keine Opfer forderte.',
        ],
    ],
];
