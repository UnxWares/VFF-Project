<?php

// Contexte sourcé de de la collision entre un TER et un poids lourd au passage à niveau n° 76 de Gimont (Gers) du 27 septembre 2010 (recherche du 23/11/2026).
return [
    'slug' => '2010-09-27-gimont',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2010-09-27-gimont' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un TER et un PL sur le PN n° 76 à Gimont (32)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/gimont-32-27-09-2010-collision-entre-un-ter-et-un-r123.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 27 septembre 2010, un TER d\'Auch à Toulouse a percuté un camion bloqué sur le passage à niveau n° 76, non gardé et à croix de Saint-André. Le bilan est de 11 blessés, dont un grave.',
                'en' => 'On 27 September 2010 a regional train from Auch to Toulouse hit a lorry stuck on level crossing no. 76, an unguarded crossing with St Andrew\'s crosses. The toll is 11 injured, one of them seriously.',
                'de' => 'Am 27. September 2010 prallte ein Regionalzug von Auch nach Toulouse auf einen Lkw, der auf dem unbeschrankten, mit Andreaskreuzen gesicherten Bahnübergang Nr. 76 festsaß. Die Bilanz lautet 11 Verletzte, einer davon schwer.',
                'sources' => ['bea-tt-2010-09-27-gimont'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 5 décembre 2011, puis les recommandations et les réponses de la DGITM, de RFF et de la commune de Gimont le 2 février 2012.',
                'en' => 'The BEA-TT published its final report on 5 December 2011, then the recommendations and the replies of the DGITM, RFF and the commune of Gimont on 2 February 2012.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 5. Dezember 2011, dann am 2. Februar 2012 die Empfehlungen und die Antworten von DGITM, RFF und der Gemeinde Gimont.',
                'sources' => ['bea-tt-2010-09-27-gimont'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 11,
            'sources' => ['bea-tt-2010-09-27-gimont'],
            'fr' => 'Le BEA-TT compte 11 blessés, dont un grave.',
            'en' => 'The BEA-TT counts 11 injured, one of them seriously.',
            'de' => 'Die BEA-TT zählt 11 Verletzte, einen davon schwer.',
        ],
    ],
];
