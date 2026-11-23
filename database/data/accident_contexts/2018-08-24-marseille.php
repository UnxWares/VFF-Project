<?php

// Contexte sourcé de du déraillement d'un TGV en gare de Marseille-Saint-Charles du 24 août 2018 (recherche du 23/11/2026).
return [
    'slug' => '2018-08-24-marseille',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2018-08-24-marseille' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’une rame TGV en entrée de gare de Marseille St-Charles (13)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/marseille-13-24-08-2018-deraillement-d-un-tgv-en-r271.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 24 août 2018, un TGV de Paris composé de deux rames couplées arrivait en gare de Marseille-Saint-Charles. Quand la première rame a atteint le quai, la rame de queue a déraillé des sept dernières remorques et de la dernière motrice. Le train a parcouru 155 mètres et abîmé des appareils de voie au point de rendre quatre voies inutilisables. Aucun voyageur n\'a été blessé, mais les dommages au matériel roulant sont conséquents.',
                'en' => 'On 24 August 2018 a TGV from Paris made up of two coupled sets was arriving at Marseille-Saint-Charles station. When the first set reached the platform, the rear set derailed with its last seven trailers and its last power car. The train ran on for 155 metres and damaged points so badly that four tracks were unusable. No passenger was injured, but the damage to the rolling stock is considerable.',
                'de' => 'Am 24. August 2018 fuhr ein TGV aus Paris, bestehend aus zwei gekuppelten Einheiten, in den Bahnhof Marseille-Saint-Charles ein. Als die erste Einheit den Bahnsteig erreichte, entgleiste die hintere Einheit mit ihren letzten sieben Zwischenwagen und dem letzten Triebkopf. Der Zug legte noch 155 Meter zurück und beschädigte Weichen so stark, dass vier Gleise unbenutzbar wurden. Kein Fahrgast wurde verletzt, aber die Schäden am rollenden Material sind beträchtlich.',
                'sources' => ['bea-tt-2018-08-24-marseille'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 11 décembre 2019 (thèmes : déraillement, rupture de rail, TGV), avec une recommandation adressée à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 11 December 2019 (themes: derailment, rail break, TGV), with a recommendation addressed to SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 11. Dezember 2019 (Themen: Entgleisung, Schienenbruch, TGV), mit einer Empfehlung an SNCF Réseau.',
                'sources' => ['bea-tt-2018-08-24-marseille'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2018-08-24-marseille'],
            'fr' => 'Le BEA-TT indique qu\'aucun voyageur n\'a été blessé.',
            'en' => 'The BEA-TT states that no passenger was injured.',
            'de' => 'Die BEA-TT gibt an, dass kein Fahrgast verletzt wurde.',
        ],
    ],
];
