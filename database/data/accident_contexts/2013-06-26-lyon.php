<?php

// Contexte sourcé de du déraillement en ligne d'un bogie d'un TER à Lyon (secteur de Guillotière) du 26 juin 2013 (recherche du 23/11/2026).
return [
    'slug' => '2013-06-26-lyon',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2013-06-26-lyon' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Déraillement en ligne d’un bogie d’un TER à Lyon (69)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/lyon-69-26-06-2013-deraillement-en-ligne-d-un-r196.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 26 juin 2013, un TER de Lyon-Part-Dieu à Chambéry a déraillé d\'un bogie à environ 50 km/h dans le secteur du poste 15 de Lyon-Guillotière. Ressentant des mouvements anormaux, le conducteur a aussitôt arrêté son train. Le véhicule déraillé est resté en ligne et n\'est pas sorti du gabarit de sa voie. Il n\'y a aucun blessé.',
                'en' => 'On 26 June 2013 a regional train from Lyon-Part-Dieu to Chambéry had one bogie derail at about 50 km/h in the area of signal box 15 at Lyon-Guillotière. Feeling abnormal movements, the driver stopped his train at once. The derailed vehicle stayed on the line and did not go outside the clearance of its track. Nobody was injured.',
                'de' => 'Am 26. Juni 2013 entgleiste bei einem Regionalzug von Lyon-Part-Dieu nach Chambéry bei etwa 50 km/h ein Drehgestell im Bereich des Stellwerks 15 in Lyon-Guillotière. Der Lokführer spürte ungewöhnliche Bewegungen und hielt den Zug sofort an. Das entgleiste Fahrzeug blieb im Gleis und ragte nicht aus dem Lichtraumprofil seines Gleises. Es gab keine Verletzten.',
                'sources' => ['bea-tt-2013-06-26-lyon'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 16 décembre 2014 (thèmes : déraillement, rupture de roue, d\'essieu ou d\'attelage), avec une recommandation adressée à la SNCF.',
                'en' => 'The BEA-TT published its final report on 16 December 2014 (themes: derailment, failure of a wheel, axle or coupling), with a recommendation addressed to the SNCF.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 16. Dezember 2014 (Themen: Entgleisung, Bruch von Rad, Achse oder Kupplung), mit einer Empfehlung an die SNCF.',
                'sources' => ['bea-tt-2013-06-26-lyon'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2013-06-26-lyon'],
            'fr' => 'Le BEA-TT indique qu\'aucun blessé n\'est à déplorer.',
            'en' => 'The BEA-TT states that no one was injured.',
            'de' => 'Die BEA-TT gibt an, dass niemand verletzt wurde.',
        ],
    ],
];
