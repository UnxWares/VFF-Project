<?php

// Contexte sourcé de de la collision entre un TGV et un convoi routier au passage à niveau n° 128 de Saint-Rémy-de-Sillé (Sarthe) du 15 octobre 2013 (recherche du 23/11/2026).
return [
    'slug' => '2013-10-15-saint-remy-de-sille',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2013-10-15-saint-remy-de-sille' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un TGV et un poids lourd à Saint-Rémy-de-Sillé (72)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/saint-remy-de-sille-72-15-10-2013-collision-entre-r199.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 15 octobre 2013, un TGV de Paris à Saint-Brieuc a percuté à 155 km/h un ensemble routier surbaissé chargé d\'engins agricoles, bloqué sur le passage à niveau n° 128. Deux personnes ont été légèrement blessées, le conducteur du train et l\'un de ses passagers. Les dégâts subis par l\'ensemble routier, le train et l\'infrastructure sont très importants.',
                'en' => 'On 15 October 2013 a TGV from Paris to Saint-Brieuc hit at 155 km/h a low-loader road combination carrying farm machinery, stuck on level crossing no. 128. Two people were slightly injured, the train driver and one of his passengers. The damage to the road combination, the train and the infrastructure is very heavy.',
                'de' => 'Am 15. Oktober 2013 prallte ein TGV von Paris nach Saint-Brieuc mit 155 km/h auf einen mit Landmaschinen beladenen Tiefladerzug, der auf dem Bahnübergang Nr. 128 festsaß. Zwei Personen wurden leicht verletzt, der Lokführer und einer seiner Fahrgäste. Die Schäden am Straßenfahrzeug, am Zug und an der Infrastruktur sind sehr groß.',
                'sources' => ['bea-tt-2013-10-15-saint-remy-de-sille'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 7 juillet 2015, avec une recommandation adressée à SNCF Réseau et à la commune de Saint-Rémy-de-Sillé.',
                'en' => 'The BEA-TT published its final report on 7 July 2015, with a recommendation addressed to SNCF Réseau and the commune of Saint-Rémy-de-Sillé.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 7. Juli 2015, mit einer Empfehlung an SNCF Réseau und die Gemeinde Saint-Rémy-de-Sillé.',
                'sources' => ['bea-tt-2013-10-15-saint-remy-de-sille'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 2,
            'sources' => ['bea-tt-2013-10-15-saint-remy-de-sille'],
            'fr' => 'Le BEA-TT compte deux blessés légers, le conducteur du train et un passager.',
            'en' => 'The BEA-TT counts two slightly injured people, the train driver and a passenger.',
            'de' => 'Die BEA-TT zählt zwei Leichtverletzte, den Lokführer und einen Fahrgast.',
        ],
    ],
];
