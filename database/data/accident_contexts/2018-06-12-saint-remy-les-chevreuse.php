<?php

// Contexte sourcé de du déraillement d'une rame du RER B à Saint-Rémy-lès-Chevreuse (Yvelines) du 12 juin 2018 (recherche du 23/11/2026).
return [
    'slug' => '2018-06-12-saint-remy-les-chevreuse',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2018-06-12-saint-remy-les-chevreuse' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement d’une rame du RER B à Saint-Rémy-les-Chevreuse (78)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-remy-les-chevreuse-78-12-06-2018-r270.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 12 juin 2018, une rame du RER B qui roulait vers Paris a déraillé et s\'est couchée en partie entre les gares de Saint-Rémy-lès-Chevreuse et de Courcelle-sur-Yvette. L\'accident a été causé par l\'évidement, sur une quinzaine de mètres, du remblai qui soutenait la voie. Sur les sept passagers présents, trois ont été légèrement blessés.',
                'en' => 'On 12 June 2018 a RER B train heading for Paris derailed and partly fell on its side between Saint-Rémy-lès-Chevreuse and Courcelle-sur-Yvette stations. The accident was caused by the hollowing out, over about fifteen metres, of the embankment supporting the track. Of the seven passengers present, three were slightly injured.',
                'de' => 'Am 12. Juni 2018 entgleiste ein Zug der RER B auf der Fahrt nach Paris zwischen den Bahnhöfen Saint-Rémy-lès-Chevreuse und Courcelle-sur-Yvette und kippte teilweise um. Ursache war die Aushöhlung des das Gleis tragenden Damms auf etwa fünfzehn Meter Länge. Von den sieben anwesenden Fahrgästen wurden drei leicht verletzt.',
                'sources' => ['bea-tt-2018-06-12-saint-remy-les-chevreuse'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 23 janvier 2020 (thèmes : conception, déraillement, RER), avec des recommandations adressées à la RATP.',
                'en' => 'The BEA-TT published its final report on 23 January 2020 (themes: design, derailment, RER), with recommendations addressed to the RATP.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 23. Januar 2020 (Themen: Konstruktion, Entgleisung, RER), mit Empfehlungen an die RATP.',
                'sources' => ['bea-tt-2018-06-12-saint-remy-les-chevreuse'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 3,
            'sources' => ['bea-tt-2018-06-12-saint-remy-les-chevreuse'],
            'fr' => 'Le BEA-TT compte trois blessés légers sur les sept passagers présents.',
            'en' => 'The BEA-TT counts three slightly injured among the seven passengers present.',
            'de' => 'Die BEA-TT zählt drei Leichtverletzte unter den sieben anwesenden Fahrgästen.',
        ],
    ],
];
