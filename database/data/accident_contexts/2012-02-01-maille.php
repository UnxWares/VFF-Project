<?php

// Contexte sourcé de du rattrapage de deux trains de marchandises à Maillé (Indre-et-Loire) du 1er février 2012 (recherche du 23/11/2026).
return [
    'slug' => '2012-02-01-maille',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2012-02-01-maille' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision, par rattrapage, de deux trains de marchandises à Maillé (37)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/maille-37-1-02-2012-rattrapage-de-deux-trains-de-r173.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 1er février 2012, un train de fret d\'Euro Cargo Rail a percuté à 20 km/h un train de marchandises de Colas Rail arrêté en pleine voie à la suite d\'une rupture de rail sur la ligne de Saint-Pierre-des-Corps à Poitiers. Le train percuteur a déraillé et engagé le gabarit de la voie voisine. Il n\'y a aucun blessé.',
                'en' => 'On 1 February 2012 a freight train of Euro Cargo Rail hit at 20 km/h a Colas Rail goods train stopped on the open line after a rail break on the Saint-Pierre-des-Corps to Poitiers line. The striking train derailed and fouled the clearance of the adjacent track. Nobody was injured.',
                'de' => 'Am 1. Februar 2012 prallte ein Güterzug von Euro Cargo Rail mit 20 km/h auf einen auf freier Strecke nach einem Schienenbruch haltenden Güterzug von Colas Rail auf der Strecke Saint-Pierre-des-Corps - Poitiers. Der auffahrende Zug entgleiste und ragte in das Lichtraumprofil des Nachbargleises. Es gab keine Verletzten.',
                'sources' => ['bea-tt-2012-02-01-maille'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 14 novembre 2012 (thèmes : collision, rattrapage, rupture de rail), puis la recommandation et les réponses de la SNCF et de RFF le 3 janvier 2013.',
                'en' => 'The BEA-TT published its final report on 14 November 2012 (themes: collision, catching up, rail break), then the recommendation and the replies of the SNCF and RFF on 3 January 2013.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 14. November 2012 (Themen: Zusammenstoß, Auffahren, Schienenbruch), dann am 3. Januar 2013 die Empfehlung und die Antworten von SNCF und RFF.',
                'sources' => ['bea-tt-2012-02-01-maille'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2012-02-01-maille'],
            'fr' => 'Le BEA-TT indique qu\'aucun blessé n\'est à déplorer.',
            'en' => 'The BEA-TT states that no one was injured.',
            'de' => 'Die BEA-TT gibt an, dass niemand verletzt wurde.',
        ],
    ],
];
