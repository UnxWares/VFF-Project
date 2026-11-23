<?php

// Contexte sourcé de du déraillement en ligne de deux wagons à Artenay (Loiret) du 9 mars 2011 (recherche du 23/11/2026).
return [
    'slug' => '2011-03-09-artenay',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2011-03-09-artenay' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement en ligne de deux wagons d’un train de fret à Artenay (45)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/artenay-45-9-3-2011-deraillement-en-ligne-de-deux-r155.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 9 mars 2011, les 17e et 19e wagons d\'un train d\'Euro Cargo Rail entre Riom (Puy-de-Dôme) et Forbach (Moselle) ont déraillé en pleine voie. Les wagons déraillés ne se sont pas couchés et n\'ont pas engagé le gabarit de la voie voisine. Il n\'y a eu aucune victime, mais la voie est fortement détériorée sur quelque 500 mètres.',
                'en' => 'On 9 March 2011 the 17th and 19th wagons of a Euro Cargo Rail train between Riom (Puy-de-Dôme) and Forbach (Moselle) derailed on the open line. The derailed wagons did not fall over and did not foul the clearance of the adjacent track. There were no casualties, but the track is badly damaged over some 500 metres.',
                'de' => 'Am 9. März 2011 entgleisten der 17. und der 19. Wagen eines Zugs von Euro Cargo Rail zwischen Riom (Puy-de-Dôme) und Forbach (Moselle) auf freier Strecke. Die entgleisten Wagen kippten nicht um und ragten nicht in das Lichtraumprofil des Nachbargleises. Es gab keine Opfer, aber das Gleis ist auf etwa 500 Meter stark beschädigt.',
                'sources' => ['bea-tt-2011-03-09-artenay'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 17 juillet 2012 (thèmes : déraillement, rupture de roue, d\'essieu ou d\'attelage), avec des recommandations adressées à NACCO et à l\'autorité de sécurité ferroviaire suédoise.',
                'en' => 'The BEA-TT published its final report on 17 July 2012 (themes: derailment, failure of a wheel, axle or coupling), with recommendations addressed to NACCO and the Swedish railway safety authority.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 17. Juli 2012 (Themen: Entgleisung, Bruch von Rad, Achse oder Kupplung), mit Empfehlungen an NACCO und die schwedische Eisenbahnsicherheitsbehörde.',
                'sources' => ['bea-tt-2011-03-09-artenay'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2011-03-09-artenay'],
            'fr' => 'Le BEA-TT indique que l\'accident ne fait aucune victime.',
            'en' => 'The BEA-TT states that the accident caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass der Unfall keine Opfer forderte.',
        ],
    ],
];
