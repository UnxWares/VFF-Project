<?php

// Contexte sourcé de du heurt d'un TER par un train de surveillance de l'infrastructure à Saint-Germain-des-Fossés (Allier) du 15 décembre 2014 (recherche du 23/11/2026).
return [
    'slug' => '2014-12-15-saint-germain-des-fosses',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2014-12-15-saint-germain-des-fosses' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un TER et un train de travaux à Saint-Germain-des-Fossés (03)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-germain-des-fosses-03-15-12-2014-heurt-d-un-r220.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 15 décembre 2014, un TER de Moulins à Clermont-Ferrand stationnait à quai en gare de Saint-Germain-des-Fossés quand il a été percuté par un train de surveillance de l\'infrastructure qui effectuait une manoeuvre de refoulement depuis une voie de service. Quatre voyageurs du TER et trois agents de SNCF Réseau du train de surveillance ont été blessés.',
                'en' => 'On 15 December 2014 a regional train from Moulins to Clermont-Ferrand stood at the platform at Saint-Germain-des-Fossés station when it was hit by an infrastructure inspection train performing a reversing move from a siding. Four passengers of the regional train and three SNCF Réseau staff on the inspection train were injured.',
                'de' => 'Am 15. Dezember 2014 stand ein Regionalzug von Moulins nach Clermont-Ferrand am Bahnsteig des Bahnhofs Saint-Germain-des-Fossés, als er von einem Infrastruktur-Überwachungszug gerammt wurde, der aus einem Nebengleis zurückschob. Vier Fahrgäste des Regionalzugs und drei Mitarbeiter von SNCF Réseau im Überwachungszug wurden verletzt.',
                'sources' => ['bea-tt-2014-12-15-saint-germain-des-fosses'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 29 juin 2016 (thèmes : collision, procédures), avec une recommandation adressée à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 29 June 2016 (themes: collision, procedures), with a recommendation addressed to SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 29. Juni 2016 (Themen: Zusammenstoß, Verfahren), mit einer Empfehlung an SNCF Réseau.',
                'sources' => ['bea-tt-2014-12-15-saint-germain-des-fosses'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 7,
            'sources' => ['bea-tt-2014-12-15-saint-germain-des-fosses'],
            'fr' => 'Le BEA-TT compte quatre voyageurs du TER et trois agents de SNCF Réseau blessés.',
            'en' => 'The BEA-TT counts four injured regional train passengers and three injured SNCF Réseau staff.',
            'de' => 'Die BEA-TT zählt vier verletzte Fahrgäste des Regionalzugs und drei verletzte Mitarbeiter von SNCF Réseau.',
        ],
    ],
];
