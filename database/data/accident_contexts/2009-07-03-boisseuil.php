<?php

// Contexte sourcé de de la collision entre un train de voyageurs et une remorque agricole à Boisseuil (Haute-Vienne) du 3 juillet 2009 (recherche du 23/11/2026).
return [
    'slug' => '2009-07-03-boisseuil',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2009-07-03-boisseuil' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision train-remorque agricole à Boisseuil',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/boisseuil-87-3-07-2009-collision-entre-un-train-de-r133.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 3 juillet 2009, un agriculteur chargeait des ballots de foin sur une remorque quand elle a écrasé les cales posées sous ses roues, dévalé la pente de la prairie et fini par tomber sur les voies ferrées en contrebas. Un train de voyageurs a percuté la remorque tombée sur la voie 1 et a déraillé. Le bilan est de 13 blessés, dont un grave.',
                'en' => 'On 3 July 2009 a farmer was loading hay bales onto a trailer when it crushed the chocks placed under its wheels, ran down the slope of the meadow and ended up falling onto the railway tracks below. A passenger train hit the trailer lying on track 1 and derailed. The toll is 13 injured, one of them seriously.',
                'de' => 'Am 3. Juli 2009 lud ein Landwirt Heuballen auf einen Anhänger, als dieser die unter seine Räder gelegten Keile zerdrückte, den Hang der Wiese hinabrollte und schließlich auf die tiefer liegenden Gleise stürzte. Ein Personenzug prallte auf den auf Gleis 1 liegenden Anhänger und entgleiste. Die Bilanz lautet 13 Verletzte, einer davon schwer.',
                'sources' => ['bea-tt-2009-07-03-boisseuil'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 4 février 2011, puis les recommandations et les réponses de la SNCF, de RFF, d\'AXEMA et de FG Industries le 6 mai 2011.',
                'en' => 'The BEA-TT published its final report on 4 February 2011, then the recommendations and the replies of the SNCF, RFF, AXEMA and FG Industries on 6 May 2011.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 4. Februar 2011, dann am 6. Mai 2011 die Empfehlungen und die Antworten von SNCF, RFF, AXEMA und FG Industries.',
                'sources' => ['bea-tt-2009-07-03-boisseuil'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 13,
            'sources' => ['bea-tt-2009-07-03-boisseuil'],
            'fr' => 'Le BEA-TT compte 13 blessés, dont un grave.',
            'en' => 'The BEA-TT counts 13 injured, one of them seriously.',
            'de' => 'Die BEA-TT zählt 13 Verletzte, einen davon schwer.',
        ],
    ],
];
