<?php

// Contexte sourcé de de la collision entre un train de fret et un poids lourd au passage à niveau n° 77 de Laluque (Landes) du 25 septembre 2009 (recherche du 23/11/2026).
return [
    'slug' => '2009-09-25-laluque',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2009-09-25-laluque' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision train-PL à Laluque',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/laluque-40-25-09-2009-collision-entre-un-train-de-r122.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 25 septembre 2009, un train de fret de la SNCF en direction de Bayonne a percuté un ensemble routier chargé de traverses de chemin de fer, au passage à niveau n° 77. L\'ensemble manoeuvrait pour accéder à son lieu de livraison, à l\'intérieur de la plate-forme logistique ferroviaire voisine des voies principales. Il n\'y a pas eu de victime. Les installations du passage à niveau sont détériorées, l\'avant de la locomotive est enfoncé, le tracteur est fortement avarié et la semi-remorque détruite.',
                'en' => 'On 25 September 2009 an SNCF freight train heading for Bayonne hit a road combination loaded with railway sleepers at level crossing no. 77. The combination was manoeuvring to reach its delivery point inside the rail logistics platform next to the main tracks. There were no casualties. The crossing installations are damaged, the front of the locomotive is pushed in, the tractor unit is badly damaged and the semi-trailer destroyed.',
                'de' => 'Am 25. September 2009 prallte ein Güterzug der SNCF in Richtung Bayonne am Bahnübergang Nr. 77 auf einen mit Eisenbahnschwellen beladenen Lastzug. Dieser rangierte, um seinen Lieferort innerhalb der neben den Hauptgleisen liegenden Bahnlogistikplattform zu erreichen. Es gab keine Opfer. Die Anlagen des Bahnübergangs sind beschädigt, die Front der Lokomotive eingedrückt, die Zugmaschine stark beschädigt und der Auflieger zerstört.',
                'sources' => ['bea-tt-2009-09-25-laluque'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 7 octobre 2010, avec des recommandations adressées à leurs destinataires.',
                'en' => 'The BEA-TT published its final report on 7 October 2010, with recommendations addressed to their recipients.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 7. Oktober 2010, mit Empfehlungen an die jeweiligen Adressaten.',
                'sources' => ['bea-tt-2009-09-25-laluque'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2009-09-25-laluque'],
            'fr' => 'Le BEA-TT indique qu\'il n\'y a pas eu de victime.',
            'en' => 'The BEA-TT states that there were no casualties.',
            'de' => 'Die BEA-TT gibt an, dass es keine Opfer gab.',
        ],
    ],
];
