<?php

// Contexte sourcé de de la dérive d'un train de fret en gare de Montauban (Tarn-et-Garonne) du 26 avril 2008 (recherche du 23/11/2026).
return [
    'slug' => '2008-04-26-montauban',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2008-04-26-montauban' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Défaillance de freinage sur le train de fret à Montauban',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/montauban-82-26-04-2008-derive-d-un-train-de-fret-r102.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 26 avril 2008, un train de fret de Veolia Cargo France, de Bordeaux vers Boussens, s\'est arrêté en urgence dans la gare de Montauban sans avoir pu, malgré le freinage, respecter la signalisation qui protégeait le point de convergence de plusieurs lignes. Il lui a fallu 3 300 mètres pour s\'arrêter. Il n\'y a eu ni victime ni dégât matériel, grâce à la réaction rapide de l\'aiguilleur de Montauban.',
                'en' => 'On 26 April 2008 a Veolia Cargo France freight train from Bordeaux to Boussens made an emergency stop in Montauban station without having been able, despite braking, to obey the signalling that protected the convergence point of several lines. It took 3,300 metres to stop. There were no casualties and no material damage, thanks to the quick reaction of the Montauban signalman.',
                'de' => 'Am 26. April 2008 hielt ein Güterzug von Veolia Cargo France von Bordeaux nach Boussens im Bahnhof Montauban notfallmäßig an, ohne trotz Bremsung die Signalisierung beachten zu können, die den Vereinigungspunkt mehrerer Strecken schützte. Bis zum Halt brauchte er 3 300 Meter. Dank der schnellen Reaktion des Weichenwärters von Montauban gab es weder Opfer noch Sachschaden.',
                'sources' => ['bea-tt-2008-04-26-montauban'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 26 mars 2009 (thèmes : dérive, freins, procédures), avec des recommandations adressées à Veolia.',
                'en' => 'The BEA-TT published its final report on 26 March 2009 (themes: runaway, brakes, procedures), with recommendations addressed to Veolia.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 26. März 2009 (Themen: Abrollen, Bremsen, Verfahren), mit Empfehlungen an Veolia.',
                'sources' => ['bea-tt-2008-04-26-montauban'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2008-04-26-montauban'],
            'fr' => 'Le BEA-TT indique qu\'aucune victime humaine n\'est à déplorer.',
            'en' => 'The BEA-TT states that there were no human casualties.',
            'de' => 'Die BEA-TT gibt an, dass es keine Personenopfer gab.',
        ],
    ],
];
