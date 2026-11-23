<?php

// Contexte sourcé de du déraillement d'un train de fret à La Ferté-sur-Chiers (Ardennes) du 13 juin 2006 (recherche du 23/11/2026).
return [
    'slug' => '2006-06-13-la-ferte-sur-chiers',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2006-06-13-la-ferte-sur-chiers' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement train Fret La Ferté-sur-Chiers',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/la-ferte-sur-chiers-08-13-06-2006-deraillement-d-r62.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 13 juin 2006, un train de minerai de Dunkerque à Dieulouard a déraillé de son dernier wagon à la sortie d\'une courbe. L\'alerte a été donnée par des agents d\'entretien de la voie. Un agent de maintenance, atteint par des projections de ballast, a été légèrement blessé. La voie est détériorée sur une dizaine de kilomètres.',
                'en' => 'On 13 June 2006 an ore train from Dunkirk to Dieulouard derailed with its last wagon on leaving a curve. The alarm was raised by track maintenance staff. A maintenance worker hit by flying ballast was slightly injured. The track is damaged over about ten kilometres.',
                'de' => 'Am 13. Juni 2006 entgleiste bei einem Erzzug von Dunkerque nach Dieulouard der letzte Wagen am Ausgang einer Kurve. Der Alarm wurde von Gleisinstandhaltungspersonal gegeben. Ein von Schotter getroffener Instandhaltungsmitarbeiter wurde leicht verletzt. Das Gleis ist auf etwa zehn Kilometer beschädigt.',
                'sources' => ['bea-tt-2006-06-13-la-ferte-sur-chiers'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 11 octobre 2007 (thèmes : déraillement, maintenance, train de fret), avec des recommandations adressées à la SNCF et à RFF.',
                'en' => 'The BEA-TT published its final report on 11 October 2007 (themes: derailment, maintenance, freight train), with recommendations addressed to the SNCF and RFF.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 11. Oktober 2007 (Themen: Entgleisung, Instandhaltung, Güterzug), mit Empfehlungen an die SNCF und RFF.',
                'sources' => ['bea-tt-2006-06-13-la-ferte-sur-chiers'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['bea-tt-2006-06-13-la-ferte-sur-chiers'],
            'fr' => 'Le BEA-TT compte un blessé léger, un agent de maintenance.',
            'en' => 'The BEA-TT counts one slightly injured person, a maintenance worker.',
            'de' => 'Die BEA-TT zählt einen Leichtverletzten, einen Instandhaltungsmitarbeiter.',
        ],
    ],
];
