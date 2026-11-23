<?php

// Contexte sourcé de de la collision entre un train de marchandises et un convoi exceptionnel au passage à niveau n° 222 de Balbigny (Loire) du 25 janvier 2011 (recherche du 23/11/2026).
return [
    'slug' => '2011-01-25-balbigny',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2011-01-25-balbigny' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un train de marchandises et un convoi exceptionnel à Balbigny (42)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/balbigny-42-25-01-2011-collision-entre-un-train-de-r148.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 25 janvier 2011, un train de marchandises de la ligne de Roanne à Saint-Étienne a percuté, au passage à niveau n° 222, un convoi exceptionnel qui transportait deux poutres en acier. Le conducteur du train a été légèrement blessé et les dégâts matériels sont importants, tant pour le convoi que pour le train et l\'infrastructure.',
                'en' => 'On 25 January 2011 a goods train on the Roanne to Saint-Étienne line hit, at level crossing no. 222, an exceptional convoy carrying two steel beams. The train driver was slightly injured and the material damage is heavy, for the convoy, the train and the infrastructure alike.',
                'de' => 'Am 25. Januar 2011 prallte ein Güterzug auf der Strecke Roanne - Saint-Étienne am Bahnübergang Nr. 222 auf einen Schwertransport mit zwei Stahlträgern. Der Lokführer wurde leicht verletzt, und die Sachschäden an Transport, Zug und Infrastruktur sind erheblich.',
                'sources' => ['bea-tt-2011-01-25-balbigny'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 17 juillet 2012, puis les recommandations et les réponses de la DGITM et de la DSCR le 11 octobre 2012.',
                'en' => 'The BEA-TT published its final report on 17 July 2012, then the recommendations and the replies of the DGITM and the DSCR on 11 October 2012.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 17. Juli 2012, dann am 11. Oktober 2012 die Empfehlungen und die Antworten von DGITM und DSCR.',
                'sources' => ['bea-tt-2011-01-25-balbigny'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['bea-tt-2011-01-25-balbigny'],
            'fr' => 'Le BEA-TT indique que le conducteur du train a été légèrement blessé.',
            'en' => 'The BEA-TT states that the train driver was slightly injured.',
            'de' => 'Die BEA-TT gibt an, dass der Lokführer leicht verletzt wurde.',
        ],
    ],
];
