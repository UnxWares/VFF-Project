<?php

// Contexte sourcé de du déraillement du train à crémaillère du Montenvers à Chamonix (Haute-Savoie) du 11 août 2019 (recherche du 23/11/2026).
return [
    'slug' => '2019-08-11-chamonix',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2019-08-11-chamonix' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement du train à crémaillère du Montenvers à Chamonix (74)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/chamonix-74-11-08-2019-deraillement-du-train-a-r291.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 11 août 2019, le train du Montenvers, composé d\'une automotrice et d\'une remorque, a déraillé à l\'aiguillage aval des Planards, dans le sens de la descente vers Chamonix. Il a glissé sur une trentaine de mètres puis fini sa course incliné dans le ballast. Malgré la panique, aucun des 145 passagers n\'a été blessé.',
                'en' => 'On 11 August 2019 the Montenvers train, made up of a railcar and a trailer, derailed at the Planards lower points, heading downhill towards Chamonix. It slid for about thirty metres and ended up tilted in the ballast. Despite the panic, none of the 145 passengers was injured.',
                'de' => 'Am 11. August 2019 entgleiste der Zug der Montenvers-Bahn, bestehend aus einem Triebwagen und einem Anhänger, an der unteren Weiche von Les Planards in Talfahrt nach Chamonix. Er rutschte etwa dreißig Meter weit und blieb schräg im Schotter stehen. Trotz der Panik wurde keiner der 145 Fahrgäste verletzt.',
                'sources' => ['bea-tt-2019-08-11-chamonix'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 12 mars 2021, avec des recommandations adressées à la Compagnie du Mont-Blanc et à la DGITM.',
                'en' => 'The BEA-TT published its final report on 12 March 2021, with recommendations addressed to the Compagnie du Mont-Blanc and the DGITM.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 12. März 2021, mit Empfehlungen an die Compagnie du Mont-Blanc und die DGITM.',
                'sources' => ['bea-tt-2019-08-11-chamonix'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2019-08-11-chamonix'],
            'fr' => 'Le BEA-TT indique qu\'il n\'y a pas de blessés parmi les 145 passagers.',
            'en' => 'The BEA-TT states that none of the 145 passengers was injured.',
            'de' => 'Die BEA-TT gibt an, dass keiner der 145 Fahrgäste verletzt wurde.',
        ],
    ],
];
