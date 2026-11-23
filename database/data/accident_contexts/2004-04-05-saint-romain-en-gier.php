<?php

// Contexte sourcé de de la collision frontale entre un TGV et un train de travaux à Saint-Romain-en-Gier (Rhône) du 5 avril 2004 (recherche du 23/11/2026).
return [
    'slug' => '2004-04-05-saint-romain-en-gier',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2004-04-05-saint-romain-en-gier' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision ferroviaire à Saint-Romain-en-Gier',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-romain-en-gier-69-5-04-2004-collision-r21.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 5 avril 2004, un TGV de la ligne de Lyon à Saint-Étienne a heurté un train de travaux qui roulait sur la même voie en sens inverse. Les deux conducteurs sont blessés et les dégâts matériels sont importants.',
                'en' => 'On 5 April 2004 a TGV on the Lyon to Saint-Étienne line hit a works train running on the same track in the opposite direction. Both drivers are injured and the material damage is heavy.',
                'de' => 'Am 5. April 2004 prallte ein TGV auf der Strecke Lyon - Saint-Étienne auf einen Bauzug, der auf demselben Gleis in Gegenrichtung fuhr. Beide Lokführer wurden verletzt, und die Sachschäden sind erheblich.',
                'sources' => ['bea-tt-2004-04-05-saint-romain-en-gier'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 25 août 2005, puis les recommandations et les réponses de la SNCF et de Réseau ferré de France le 16 septembre 2005.',
                'en' => 'The BEA-TT published its final report on 25 August 2005, then the recommendations and the replies of the SNCF and Réseau ferré de France on 16 September 2005.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 25. August 2005, dann am 16. September 2005 die Empfehlungen und die Antworten von SNCF und Réseau ferré de France.',
                'sources' => ['bea-tt-2004-04-05-saint-romain-en-gier'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 2,
            'sources' => ['bea-tt-2004-04-05-saint-romain-en-gier'],
            'fr' => 'Le BEA-TT indique que les deux conducteurs sont blessés.',
            'en' => 'The BEA-TT states that both drivers were injured.',
            'de' => 'Die BEA-TT gibt an, dass beide Lokführer verletzt wurden.',
        ],
    ],
];
