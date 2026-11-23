<?php

// Contexte sourcé de de la collision puis du déraillement d'un train de fret à Metz-Sablon (Moselle) du 11 février 2024 (recherche du 23/11/2026).
return [
    'slug' => '2024-02-11-metz',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2024-02-11-metz' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un train de fret suite à rattrapage à Metz-Sablon (57)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/metz-sablon-57-11-02-2024-collision-puis-r493.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 11 février 2024, un train de marchandises de Fret SNCF a rattrapé puis percuté la queue d\'un autre train de Fret SNCF arrêté sur une voie principale à Metz-Sablon, avant de dérailler. Les installations de traction électrique et les voies ont subi des dégâts importants. Les deux conducteurs n\'ont pas été blessés mais sont restés choqués.',
                'en' => 'On 11 February 2024 a Fret SNCF goods train caught up with and struck the rear of another Fret SNCF train standing on a main line at Metz-Sablon, and then derailed. The electric traction installations and the tracks suffered heavy damage. Neither driver was injured but both were shaken.',
                'de' => 'Am 11. Februar 2024 fuhr ein Güterzug von Fret SNCF in Metz-Sablon auf das Ende eines anderen, auf einem Hauptgleis stehenden Fret-SNCF-Zuges auf und entgleiste danach. Die Fahrleitungsanlagen und die Gleise wurden stark beschädigt. Beide Lokführer blieben unverletzt, standen aber unter Schock.',
                'sources' => ['bea-tt-2024-02-11-metz'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 11 avril 2025, avec des recommandations adressées à SNCF Réseau et à Hexafret.',
                'en' => 'The BEA-TT published its final report on 11 April 2025, with recommendations addressed to SNCF Réseau and Hexafret.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 11. April 2025, mit Empfehlungen an SNCF Réseau und Hexafret.',
                'sources' => ['bea-tt-2024-02-11-metz'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2024-02-11-metz'],
            'fr' => 'Le BEA-TT précise que les deux conducteurs ne sont pas blessés mais choqués.',
            'en' => 'The BEA-TT states that both drivers were not injured but shaken.',
            'de' => 'Die BEA-TT gibt an, dass beide Lokführer nicht verletzt, aber unter Schock waren.',
        ],
    ],
];
