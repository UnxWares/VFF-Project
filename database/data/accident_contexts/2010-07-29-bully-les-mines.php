<?php

// Contexte sourcé de du déraillement d'un train de charbon à Bully-Grenay (Pas-de-Calais) du 29 juillet 2010 (recherche du 23/11/2026).
return [
    'slug' => '2010-07-29-bully-les-mines',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2010-07-29-bully-les-mines' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un train de charbon à Bully-Grenay (62)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/bully-grenay-62-29-07-2010-deraillement-d-un-train-r137.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 29 juillet 2010, les 19 wagons de tête d\'un train de charbon de la SNCF ont déraillé et se sont couchés, obstruant les deux voies principales. Il n\'y a pas eu de victimes. L\'infrastructure est détériorée sur environ 600 mètres et le matériel roulant est très endommagé.',
                'en' => 'On 29 July 2010 the 19 leading wagons of an SNCF coal train derailed and fell over, blocking both main tracks. There were no casualties. The infrastructure is damaged over about 600 metres and the rolling stock is badly damaged.',
                'de' => 'Am 29. Juli 2010 entgleisten die 19 vordersten Wagen eines Kohlezugs der SNCF und kippten um, wodurch beide Hauptgleise blockiert wurden. Es gab keine Opfer. Die Infrastruktur ist auf etwa 600 Meter beschädigt, und das rollende Material ist stark beschädigt.',
                'sources' => ['bea-tt-2010-07-29-bully-les-mines'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 18 octobre 2011 (thèmes : déraillement, freins, train de fret), puis les recommandations et les réponses de RFF, de la DGITM et de LORMAFER le 5 mars 2012.',
                'en' => 'The BEA-TT published its final report on 18 October 2011 (themes: derailment, brakes, freight train), then the recommendations and the replies of RFF, the DGITM and LORMAFER on 5 March 2012.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 18. Oktober 2011 (Themen: Entgleisung, Bremsen, Güterzug), dann am 5. März 2012 die Empfehlungen und die Antworten von RFF, DGITM und LORMAFER.',
                'sources' => ['bea-tt-2010-07-29-bully-les-mines'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2010-07-29-bully-les-mines'],
            'fr' => 'Le BEA-TT indique que l\'accident ne fait pas de victimes.',
            'en' => 'The BEA-TT states that the accident caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass der Unfall keine Opfer forderte.',
        ],
    ],
];
