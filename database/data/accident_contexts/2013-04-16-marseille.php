<?php

// Contexte sourcé de de la collision entre un TER et une grue mobile au passage à niveau n° 1 de Marseille-L'Estaque du 16 avril 2013 (recherche du 23/11/2026).
return [
    'slug' => '2013-04-16-marseille',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2013-04-16-marseille' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un TER et une grue mobile sur le PN n° 1 à Marseille (13)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/marseille-l-estaque-13-16-04-2013-collision-entre-r190.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 16 avril 2013, un TER de Miramas à Marseille-Saint-Charles a percuté à 22 km/h une grue mobile qui manoeuvrait aux abords du passage à niveau n° 1 et engageait l\'emprise ferroviaire. La collision a blessé 32 personnes, dont deux ont été hospitalisées : le conducteur de la grue et celui du train.',
                'en' => 'On 16 April 2013 a regional train from Miramas to Marseille-Saint-Charles hit at 22 km/h a mobile crane that was manoeuvring near level crossing no. 1 and fouling the railway boundary. The collision injured 32 people, two of whom were hospitalised: the crane driver and the train driver.',
                'de' => 'Am 16. April 2013 prallte ein Regionalzug von Miramas nach Marseille-Saint-Charles mit 22 km/h auf einen Mobilkran, der in der Nähe des Bahnübergangs Nr. 1 rangierte und in den Gleisbereich ragte. Bei dem Zusammenstoß wurden 32 Personen verletzt, zwei davon kamen ins Krankenhaus: der Kranführer und der Lokführer.',
                'sources' => ['bea-tt-2013-04-16-marseille'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 13 mai 2014, avec une recommandation adressée à la préfecture des Bouches-du-Rhône et à la ville de Marseille.',
                'en' => 'The BEA-TT published its final report on 13 May 2014, with a recommendation addressed to the Bouches-du-Rhône prefecture and the city of Marseille.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 13. Mai 2014, mit einer Empfehlung an die Präfektur der Bouches-du-Rhône und die Stadt Marseille.',
                'sources' => ['bea-tt-2013-04-16-marseille'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 32,
            'sources' => ['bea-tt-2013-04-16-marseille'],
            'fr' => 'Le BEA-TT compte 32 blessés, dont deux hospitalisés.',
            'en' => 'The BEA-TT counts 32 injured, two of them hospitalised.',
            'de' => 'Die BEA-TT zählt 32 Verletzte, zwei davon im Krankenhaus.',
        ],
    ],
];
