<?php

// Contexte sourcé de de la déviation inopinée d'une rame du RER A vers des voies de service à Saint-Germain-en-Laye (Yvelines) du 9 décembre 2014 (recherche du 23/11/2026).
return [
    'slug' => '2014-12-09-saint-germain-en-laye',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2014-12-09-saint-germain-en-laye' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déviation d’un train de banlieue survenue près de la gare d’Achères-Grand-Cormier à Saint-Germain-en-Laye (78)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-germain-en-laye-78-9-12-2014-deviation-r219.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 9 décembre 2014, une rame du RER A qui roulait vers Paris a franchi, sans que la signalisation l\'ait annoncé, une aiguille en déviation vers des voies de service, à 87 km/h pour une vitesse autorisée de 30 km/h. Par chance, elle n\'a pas déraillé et n\'a heurté aucun autre matériel sur le faisceau de service. Il n\'y a eu ni victime ni dégât.',
                'en' => 'On 9 December 2014 a RER A train heading for Paris passed, without the signalling having announced it, a set of points diverging into service sidings, at 87 km/h for a permitted speed of 30 km/h. By luck it did not derail and did not hit any other rolling stock in the service sidings. There were no casualties and no damage.',
                'de' => 'Am 9. Dezember 2014 befuhr ein Zug der RER A auf der Fahrt nach Paris, ohne dass die Signalisierung es angekündigt hatte, eine Weiche in Ablenkung zu Nebengleisen, mit 87 km/h bei zulässigen 30 km/h. Zum Glück entgleiste er nicht und stieß auf dem Abstellgleisbündel nicht mit anderen Fahrzeugen zusammen. Es gab weder Opfer noch Schäden.',
                'sources' => ['bea-tt-2014-12-09-saint-germain-en-laye'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 30 août 2016 (thèmes : maintenance, RER, signalisation), avec des recommandations adressées à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 30 August 2016 (themes: maintenance, RER, signalling), with recommendations addressed to SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 30. August 2016 (Themen: Instandhaltung, RER, Signalisierung), mit Empfehlungen an SNCF Réseau.',
                'sources' => ['bea-tt-2014-12-09-saint-germain-en-laye'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2014-12-09-saint-germain-en-laye'],
            'fr' => 'Le BEA-TT indique qu\'aucune victime n\'est à déplorer.',
            'en' => 'The BEA-TT states that there were no casualties.',
            'de' => 'Die BEA-TT gibt an, dass es keine Opfer gab.',
        ],
    ],
];
