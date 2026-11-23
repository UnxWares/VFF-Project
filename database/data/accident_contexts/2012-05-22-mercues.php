<?php

// Contexte sourcé de du déraillement d'un TER à la suite d'une chute de rochers à Mercuès (Lot) du 22 mai 2012 (recherche du 23/11/2026).
return [
    'slug' => '2012-05-22-mercues',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2012-05-22-mercues' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un TER consécutif à l’éboulement d’un mur de parement à Mercuès (46)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/mercues-46-22-05-2012-deraillement-d-un-ter-r180.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 22 mai 2012, un TER de Paris à Brive-la-Gaillarde a heurté à 88 km/h un amas rocheux qui obstruait les voies. La locomotive a déraillé et poursuivi sa course sur environ 300 mètres, maintenue par le rail de sécurité de la voie qui surplombe à cet endroit le Lot et une route départementale. Il n\'y a eu aucune victime.',
                'en' => 'On 22 May 2012 a regional train from Paris to Brive-la-Gaillarde hit at 88 km/h a pile of rocks blocking the tracks. The locomotive derailed and carried on for about 300 metres, held by the guard rail of the track which at that point overlooks the Lot and a departmental road. There were no casualties.',
                'de' => 'Am 22. Mai 2012 prallte ein Regionalzug von Paris nach Brive-la-Gaillarde mit 88 km/h auf einen die Gleise versperrenden Felshaufen. Die Lokomotive entgleiste und fuhr noch etwa 300 Meter weiter, gehalten durch die Radlenker des Gleises, das dort über dem Lot und einer Départementsstraße verläuft. Es gab keine Opfer.',
                'sources' => ['bea-tt-2012-05-22-mercues'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 4 septembre 2013, puis les recommandations et les réponses de RFF et de la SNCF le 26 novembre 2013.',
                'en' => 'The BEA-TT published its final report on 4 September 2013, then the recommendations and the replies of RFF and the SNCF on 26 November 2013.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 4. September 2013, dann am 26. November 2013 die Empfehlungen und die Antworten von RFF und SNCF.',
                'sources' => ['bea-tt-2012-05-22-mercues'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2012-05-22-mercues'],
            'fr' => 'Le BEA-TT indique qu\'aucune victime n\'est à déplorer.',
            'en' => 'The BEA-TT states that there were no casualties.',
            'de' => 'Die BEA-TT gibt an, dass es keine Opfer gab.',
        ],
    ],
];
