<?php

// Contexte sourcé de de l'accostage brutal d'un train Transilien à Versailles-Rive-Gauche (Yvelines) du 13 août 2007 (recherche du 23/11/2026).
return [
    'slug' => '2007-08-13-versailles',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2007-08-13-versailles' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Choc d\'un train contre un heurtoir en gare de Versailles rive gauche',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/versailles-rive-gauche-78-13-08-2007-accostage-r77.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 13 août 2007, un train Transilien de Paris-Invalides à Versailles-Rive-Gauche a percuté à 6 km/h le heurtoir de la voie 3 de la gare. Il n\'y a eu aucun blessé, ni parmi les voyageurs, ni pour le conducteur ou d\'autres agents de la SNCF. L\'accident a causé des dégâts aux installations fixes et au matériel roulant.',
                'en' => 'On 13 August 2007 a Transilien train from Paris-Invalides to Versailles-Rive-Gauche hit at 6 km/h the buffer stop of track 3 of the station. Nobody was injured, neither passengers nor the driver or other SNCF staff. The accident caused damage to fixed installations and to the rolling stock.',
                'de' => 'Am 13. August 2007 prallte ein Transilien von Paris-Invalides nach Versailles-Rive-Gauche mit 6 km/h gegen den Prellbock von Gleis 3 des Bahnhofs. Niemand wurde verletzt, weder Fahrgäste noch der Lokführer oder andere SNCF-Mitarbeiter. Der Unfall verursachte Schäden an den festen Anlagen und am rollenden Material.',
                'sources' => ['bea-tt-2007-08-13-versailles'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 28 avril 2008, avec des recommandations adressées à la SNCF et à RFF.',
                'en' => 'The BEA-TT published its final report on 28 April 2008, with recommendations addressed to the SNCF and RFF.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 28. April 2008, mit Empfehlungen an die SNCF und RFF.',
                'sources' => ['bea-tt-2007-08-13-versailles'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2007-08-13-versailles'],
            'fr' => 'Le BEA-TT indique qu\'aucun blessé n\'est à déplorer.',
            'en' => 'The BEA-TT states that no one was injured.',
            'de' => 'Die BEA-TT gibt an, dass niemand verletzt wurde.',
        ],
    ],
];
