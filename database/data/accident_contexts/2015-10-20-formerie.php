<?php

// Contexte sourcé de de la dérive d'un TER entre Formerie (Oise) et Sommery (Seine-Maritime) du 20 octobre 2015 (recherche du 23/11/2026).
return [
    'slug' => '2015-10-20-formerie',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2015-10-20-formerie' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Dérive d’un TER suite à collision avec des bovidés à Formerie (60)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/formerie-60-serqueux-et-sommery-76-20-10-2015-r234.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 20 octobre 2015, un TER d\'Abancourt à Rouen a percuté des bovins près de Formerie. Après le choc, le conducteur n\'a pas pu arrêter son train, qui est parti en dérive à environ 100 km/h vers Serqueux. Après près de 20 km et la traversée de la gare de Serqueux à 80 km/h, le train a ralenti dans la rampe vers Sommery jusqu\'à la vitesse d\'un homme au pas. Le conducteur est alors descendu en marche avec deux cales anti-dérive et a immobilisé le train une fois arrêté. L\'événement n\'a fait aucune victime.',
                'en' => 'On 20 October 2015 a regional train from Abancourt to Rouen hit cattle near Formerie. After the impact the driver could not stop his train, which ran away at about 100 km/h towards Serqueux. After nearly 20 km and passing through Serqueux station at 80 km/h, the train slowed on the gradient towards Sommery down to walking pace. The driver then got off while it was moving with two anti-runaway chocks and secured the train once it had stopped. The event caused no casualties.',
                'de' => 'Am 20. Oktober 2015 erfasste ein Regionalzug von Abancourt nach Rouen bei Formerie Rinder. Nach dem Aufprall konnte der Lokführer seinen Zug nicht mehr anhalten, der mit etwa 100 km/h in Richtung Serqueux davonrollte. Nach fast 20 km und der Durchfahrt des Bahnhofs Serqueux mit 80 km/h wurde der Zug in der Steigung nach Sommery bis auf Schrittgeschwindigkeit langsamer. Der Lokführer stieg dann in der Fahrt mit zwei Hemmschuhen ab und sicherte den Zug nach dem Halt. Das Ereignis forderte keine Opfer.',
                'sources' => ['bea-tt-2015-10-20-formerie'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 25 novembre 2016 (thèmes : dérive, freins), avec des recommandations adressées à SNCF Mobilités et à l\'EPSF.',
                'en' => 'The BEA-TT published its final report on 25 November 2016 (themes: runaway, brakes), with recommendations addressed to SNCF Mobilités and the EPSF.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 25. November 2016 (Themen: Abrollen, Bremsen), mit Empfehlungen an SNCF Mobilités und die EPSF.',
                'sources' => ['bea-tt-2015-10-20-formerie'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2015-10-20-formerie'],
            'fr' => 'Le BEA-TT indique que l\'événement n\'a fait aucune victime.',
            'en' => 'The BEA-TT states that the event caused no casualties.',
            'de' => 'Die BEA-TT gibt an, dass das Ereignis keine Opfer forderte.',
        ],
    ],
];
