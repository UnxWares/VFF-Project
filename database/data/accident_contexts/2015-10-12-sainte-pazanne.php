<?php

// Contexte sourcé de du déraillement par bivoie d'un TER à Sainte-Pazanne (Loire-Atlantique) du 12 octobre 2015 (recherche du 23/11/2026).
return [
    'slug' => '2015-10-12-sainte-pazanne',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2015-10-12-sainte-pazanne' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’un TER à l’entrée de la gare de Sainte-Pazanne (44)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/sainte-pazanne-44-12-10-2015-deraillement-par-r233.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 12 octobre 2015, un TER de Saint-Gilles-Croix-de-Vie à Nantes a déraillé par bivoie, à faible vitesse, sur une aiguille à l\'entrée de la gare de Sainte-Pazanne. Il s\'est arrêté 30 m plus loin, son bogie avant sur les rails de la voie 2 et son bogie arrière déraillé sur la voie 1. L\'événement n\'a fait aucune victime et les dégâts matériels sont limités.',
                'en' => 'On 12 October 2015 a regional train from Saint-Gilles-Croix-de-Vie to Nantes derailed across two tracks, at low speed, on a set of points at the entrance of Sainte-Pazanne station. It stopped 30 m further on, its front bogie on the rails of track 2 and its rear bogie derailed on track 1. The event caused no casualties and the material damage is limited.',
                'de' => 'Am 12. Oktober 2015 entgleiste ein Regionalzug von Saint-Gilles-Croix-de-Vie nach Nantes mit niedriger Geschwindigkeit über zwei Gleise an einer Weiche am Eingang des Bahnhofs Sainte-Pazanne. Er hielt 30 m weiter, sein vorderes Drehgestell auf den Schienen von Gleis 2 und sein hinteres entgleist auf Gleis 1. Das Ereignis forderte keine Opfer, der Sachschaden ist begrenzt.',
                'sources' => ['bea-tt-2015-10-12-sainte-pazanne'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 13 février 2017, avec des recommandations adressées à l\'EPSF, à SNCF Réseau et à SNCF Mobilités.',
                'en' => 'The BEA-TT published its final report on 13 February 2017, with recommendations addressed to the EPSF, SNCF Réseau and SNCF Mobilités.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 13. Februar 2017, mit Empfehlungen an die EPSF, SNCF Réseau und SNCF Mobilités.',
                'sources' => ['bea-tt-2015-10-12-sainte-pazanne'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2015-10-12-sainte-pazanne'],
            'fr' => 'Le BEA-TT indique que l\'événement n\'a fait aucune victime.',
            'en' => 'The BEA-TT states that the event caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass das Ereignis keine Opfer forderte.',
        ],
    ],
];
