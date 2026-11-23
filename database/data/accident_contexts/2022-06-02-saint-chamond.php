<?php

// Contexte sourcé de de la chute d'un camion sur les voies ferrées depuis un pont-route à Saint-Chamond (Loire) du 2 juin 2022 (recherche du 23/11/2026).
return [
    'slug' => '2022-06-02-saint-chamond',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2022-06-02-saint-chamond' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Chute sur la voie ferrée d’un camion-benne depuis un pont-route à Saint-Chamond (42)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-chamond-42-2-06-2022-chute-d-un-camion-sur-r329.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 2 juin 2022, un camion-benne est tombé du boulevard de Fonsala sur les voies de la SNCF après être sorti de sa voie juste avant le pont-route. Dans sa chute de plus de 9 mètres, il a arraché les caténaires, ce qui a coupé l\'alimentation électrique des deux voies. Son conducteur n\'a été que légèrement blessé. Aucun train ne circulait à proximité, et la chute a par hasard activé le dispositif d\'arrêt des trains.',
                'en' => 'On 2 June 2022 a tipper truck fell from the Boulevard de Fonsala onto the SNCF tracks after leaving its lane just before the road bridge. In its fall of more than 9 metres it tore down the catenary, cutting the power supply on both tracks. Its driver was only slightly injured. No train was running nearby, and the fall by chance triggered the train-stopping system.',
                'de' => 'Am 2. Juni 2022 stürzte ein Kipper vom Boulevard de Fonsala auf die Gleise der SNCF, nachdem er kurz vor der Straßenbrücke von seiner Fahrspur abgekommen war. Bei seinem Sturz aus mehr als 9 Metern riss er die Fahrleitung herunter und unterbrach die Stromversorgung beider Gleise. Sein Fahrer wurde nur leicht verletzt. Es fuhr kein Zug in der Nähe, und der Sturz löste zufällig die Zugstopp-Einrichtung aus.',
                'sources' => ['bea-tt-2022-06-02-saint-chamond'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 11 décembre 2023, sur la conception du pont-route et les poids lourds, avec des recommandations adressées à Saint-Étienne Métropole et à la DGITM.',
                'en' => 'The BEA-TT published its final report on 11 December 2023, on the design of the road bridge and heavy goods vehicles, with recommendations addressed to Saint-Étienne Métropole and the DGITM.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 11. Dezember 2023 zur Konstruktion der Straßenbrücke und zu Lastwagen, mit Empfehlungen an Saint-Étienne Métropole und die DGITM.',
                'sources' => ['bea-tt-2022-06-02-saint-chamond'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['bea-tt-2022-06-02-saint-chamond'],
            'fr' => 'Le BEA-TT indique que le conducteur du camion a été légèrement blessé.',
            'en' => 'The BEA-TT states that the truck driver was slightly injured.',
            'de' => 'Die BEA-TT gibt an, dass der Lkw-Fahrer leicht verletzt wurde.',
        ],
    ],
];
