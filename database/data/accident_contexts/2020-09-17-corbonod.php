<?php

// Contexte sourcé de du déraillement d'un train de fret à Corbonod (Ain) du 17 septembre 2020 (recherche du 23/11/2026).
return [
    'slug' => '2020-09-17-corbonod',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2020-09-17-corbonod' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un train de fret RegioRail à Corbonod (01)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/corbonod-01-17-09-2020-deraillement-d-un-train-de-r309.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 17 septembre 2020, un train de marchandises qui transportait de l\'eau minérale de Publier, en Haute-Savoie, vers le Royaume-Uni a vu dérailler le premier essieu de son cinquième wagon, ce qui a provoqué un début d\'incendie. Le bilan matériel est lourd : la voie a été détruite sur presque 3 km.',
                'en' => 'On 17 September 2020 a goods train carrying mineral water from Publier, in Haute-Savoie, to the United Kingdom had the first axle of its fifth wagon derail, which started a fire. The material toll is heavy: the track was destroyed over almost 3 km.',
                'de' => 'Am 17. September 2020 entgleiste bei einem Güterzug, der Mineralwasser von Publier in Haute-Savoie ins Vereinigte Königreich beförderte, die erste Achse des fünften Wagens, was einen Brandbeginn auslöste. Der Sachschaden ist hoch: Das Gleis wurde auf fast 3 km zerstört.',
                'sources' => ['bea-tt-2020-09-17-corbonod'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 23 février 2023 (thèmes : détecteur de boîte chaude, rupture de roue, d\'essieu ou d\'attelage), avec des recommandations adressées à l\'AFWP, à SNCF Voyageurs et à l\'UIP.',
                'en' => 'The BEA-TT published its final report on 23 February 2023 (themes: hot axle box detector, failure of a wheel, axle or coupling), with recommendations addressed to the AFWP, SNCF Voyageurs and the UIP.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 23. Februar 2023 (Themen: Heißläuferortungsanlage, Bruch von Rad, Achse oder Kupplung), mit Empfehlungen an die AFWP, SNCF Voyageurs und die UIP.',
                'sources' => ['bea-tt-2020-09-17-corbonod'],
            ],
        ],
    ],
];
