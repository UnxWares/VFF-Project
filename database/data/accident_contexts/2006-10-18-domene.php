<?php

// Contexte sourcé de de la collision entre un TER et un convoi exceptionnel au passage à niveau n° 18 de Domène (Isère) du 18 octobre 2006 (recherche du 23/11/2026).
return [
    'slug' => '2006-10-18-domene',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2006-10-18-domene' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Passage à niveau à Domène',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/domene-38-18-10-2006-collision-entre-un-ter-et-un-r71.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 18 octobre 2006, un TER de la ligne de Grenoble à Montmélian a percuté un convoi exceptionnel qui manoeuvrait pour franchir le passage à niveau n° 18. L\'accident n\'a pas fait de victime mais a causé des dégâts matériels importants.',
                'en' => 'On 18 October 2006 a regional train on the Grenoble to Montmélian line hit an exceptional convoy that was manoeuvring to cross level crossing no. 18. The accident caused no casualties but considerable material damage.',
                'de' => 'Am 18. Oktober 2006 prallte ein Regionalzug der Strecke Grenoble - Montmélian auf einen Schwertransport, der rangierte, um den Bahnübergang Nr. 18 zu queren. Der Unfall forderte keine Opfer, verursachte aber erhebliche Sachschäden.',
                'sources' => ['bea-tt-2006-10-18-domene'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 21 février 2008, puis les recommandations et les réponses de la DSCR et de la DDE de l\'Isère le 31 mars 2008.',
                'en' => 'The BEA-TT published its final report on 21 February 2008, then the recommendations and the replies of the DSCR and the Isère DDE on 31 March 2008.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 21. Februar 2008, dann am 31. März 2008 die Empfehlungen und die Antworten der DSCR und der DDE Isère.',
                'sources' => ['bea-tt-2006-10-18-domene'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2006-10-18-domene'],
            'fr' => 'Le BEA-TT indique que l\'accident n\'a pas fait de victime.',
            'en' => 'The BEA-TT states that the accident caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass der Unfall keine Opfer forderte.',
        ],
    ],
];
