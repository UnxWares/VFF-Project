<?php

// Contexte sourcé de du déraillement d'un train de voyageurs à Pertuis (Vaucluse) du 9 novembre 2007 (recherche du 23/11/2026).
return [
    'slug' => '2007-11-09-pertuis',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2007-11-09-pertuis' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement du TER à Pertuis',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/pertuis-84-9-11-2007-deraillement-d-un-train-de-r78.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 9 novembre 2007, un TER de Briançon à Aix-en-Provence, avec environ 150 personnes à bord, a vu sa locomotive et ses deux premières voitures dérailler dans une courbe. Les véhicules ne se sont pas renversés et il n\'y a aucun blessé. La voie est endommagée sur 300 mètres et le matériel roulant a subi des dégâts importants.',
                'en' => 'On 9 November 2007 a regional train from Briançon to Aix-en-Provence, with about 150 people on board, had its locomotive and first two cars derail on a curve. The vehicles did not overturn and nobody was injured. The track is damaged over 300 metres and the rolling stock suffered heavy damage.',
                'de' => 'Am 9. November 2007 entgleisten bei einem Regionalzug von Briançon nach Aix-en-Provence mit etwa 150 Personen an Bord die Lokomotive und die ersten beiden Wagen in einer Kurve. Die Fahrzeuge kippten nicht um, und es gab keine Verletzten. Das Gleis ist auf 300 Meter beschädigt, und das rollende Material erlitt erhebliche Schäden.',
                'sources' => ['bea-tt-2007-11-09-pertuis'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 1er août 2008 (thèmes : déraillement, rupture de rail), avec des recommandations adressées à la SNCF et à RFF.',
                'en' => 'The BEA-TT published its final report on 1 August 2008 (themes: derailment, rail break), with recommendations addressed to the SNCF and RFF.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 1. August 2008 (Themen: Entgleisung, Schienenbruch), mit Empfehlungen an die SNCF und RFF.',
                'sources' => ['bea-tt-2007-11-09-pertuis'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2007-11-09-pertuis'],
            'fr' => 'Le BEA-TT indique qu\'aucun blessé n\'est à déplorer.',
            'en' => 'The BEA-TT states that no one was injured.',
            'de' => 'Die BEA-TT gibt an, dass niemand verletzt wurde.',
        ],
    ],
];
