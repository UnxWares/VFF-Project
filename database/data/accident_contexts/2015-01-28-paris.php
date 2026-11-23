<?php

// Contexte sourcé de du déraillement d'un TGV en gare de Paris-Gare de Lyon du 28 janvier 2015 (recherche du 23/11/2026).
return [
    'slug' => '2015-01-28-paris',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2015-01-28-paris' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’une rame de TGV en Gare de Lyon à Paris (75)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/paris-gare-de-lyon-28-01-2015-deraillement-d-un-r222.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 28 janvier 2015, un train de deux rames TGV vides a déraillé à 29 km/h juste après l\'aiguille 22L à Paris-Gare de Lyon. Il venait du chantier de lavage pour rejoindre la voie 15 à quai, d\'où il devait partir pour Zurich, et s\'est arrêté environ 240 mètres plus loin. Il n\'y a eu aucune victime, mais le matériel roulant et les installations de voie sont très endommagés.',
                'en' => 'On 28 January 2015 a train of two empty TGV sets derailed at 29 km/h just past points 22L at Paris-Gare de Lyon. It was coming from the washing area to reach platform track 15, from where it was to leave for Zurich, and stopped about 240 metres further on. There were no casualties, but the rolling stock and the track installations are very badly damaged.',
                'de' => 'Am 28. Januar 2015 entgleiste ein Zug aus zwei leeren TGV-Einheiten mit 29 km/h kurz hinter der Weiche 22L in Paris-Gare de Lyon. Er kam aus der Waschanlage und sollte zum Bahnsteiggleis 15, von wo er nach Zürich abfahren sollte, und hielt etwa 240 Meter weiter. Es gab keine Opfer, aber das rollende Material und die Gleisanlagen sind sehr stark beschädigt.',
                'sources' => ['bea-tt-2015-01-28-paris'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 25 novembre 2016 (thèmes : déraillement, maintenance, TGV), avec des recommandations adressées à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 25 November 2016 (themes: derailment, maintenance, TGV), with recommendations addressed to SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 25. November 2016 (Themen: Entgleisung, Instandhaltung, TGV), mit Empfehlungen an SNCF Réseau.',
                'sources' => ['bea-tt-2015-01-28-paris'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2015-01-28-paris'],
            'fr' => 'Le BEA-TT indique qu\'aucune victime n\'est à déplorer.',
            'en' => 'The BEA-TT states that there were no casualties.',
            'de' => 'Die BEA-TT gibt an, dass es keine Opfer gab.',
        ],
    ],
];
