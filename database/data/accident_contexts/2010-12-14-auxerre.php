<?php

// Contexte sourcé de de la collision entre un TER et un autocar au passage à niveau n° 19 d'Auxerre (Yonne) du 14 décembre 2010 (recherche du 23/11/2026).
return [
    'slug' => '2010-12-14-auxerre',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2010-12-14-auxerre' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un TER et un autocar sur le PN n° 19 à Auxerre (89)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/auxerre-89-14-12-2010-collision-entre-un-ter-et-un-r146.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 14 décembre 2010, un autocar qui roulait sur la RN 77 avec une trentaine de passagers s\'est arrêté juste après le passage à niveau n° 19 de la ligne d\'Auxerre à Laroche-Migennes. Il a été percuté par un TER venant de la gare d\'Auxerre-Saint-Gervais. Le bilan est de 17 blessés, dont un grave, tous passagers de l\'autocar.',
                'en' => 'On 14 December 2010 a coach on the RN 77 with about thirty passengers stopped just past level crossing no. 19 on the Auxerre to Laroche-Migennes line. It was hit by a regional train coming from Auxerre-Saint-Gervais station. The toll is 17 injured, one of them seriously, all passengers of the coach.',
                'de' => 'Am 14. Dezember 2010 hielt ein Reisebus mit etwa dreißig Fahrgästen auf der RN 77 unmittelbar hinter dem Bahnübergang Nr. 19 der Strecke Auxerre - Laroche-Migennes. Er wurde von einem Regionalzug aus dem Bahnhof Auxerre-Saint-Gervais gerammt. Die Bilanz lautet 17 Verletzte, einer davon schwer, alle Fahrgäste des Busses.',
                'sources' => ['bea-tt-2010-12-14-auxerre'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 2 janvier 2012, puis les recommandations et les réponses de la DIR Centre-Est et des Rapides de Bourgogne le 1er mars 2012.',
                'en' => 'The BEA-TT published its final report on 2 January 2012, then the recommendations and the replies of the DIR Centre-Est and Les Rapides de Bourgogne on 1 March 2012.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 2. Januar 2012, dann am 1. März 2012 die Empfehlungen und die Antworten der DIR Centre-Est und von Les Rapides de Bourgogne.',
                'sources' => ['bea-tt-2010-12-14-auxerre'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 17,
            'sources' => ['bea-tt-2010-12-14-auxerre'],
            'fr' => 'Le BEA-TT compte 17 blessés, dont un grave, tous passagers de l\'autocar.',
            'en' => 'The BEA-TT counts 17 injured, one of them seriously, all coach passengers.',
            'de' => 'Die BEA-TT zählt 17 Verletzte, einen davon schwer, alle Fahrgäste des Busses.',
        ],
    ],
];
