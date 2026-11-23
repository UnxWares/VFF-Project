<?php

// Contexte sourcé de de l'incendie à bord d'une navette fret Eurotunnel du 11 septembre 2008 (recherche du 23/11/2026).
return [
    'slug' => '2008-09-11-eurotunnel',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2008-09-11-eurotunnel' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Incendie navettes camions Eurotunnel',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/eurotunnel-11-09-2008-incendie-a-bord-d-une-r117.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 11 septembre 2008, une navette fret Eurotunnel chargée de 25 camions et 2 camionnettes a quitté le terminal de Folkestone vers 15 h 30 et un feu a été détecté à bord. L\'évacuation vers le terminal français s\'est achevée près de 3 heures après le début de l\'événement et la lutte contre l\'incendie le lendemain vers midi. L\'incendie n\'a causé ni décès ni blessure grave. Tous les wagons chargés et les camions ont brûlé, les deux locomotives ont été endommagées et le tunnel Nord, très abîmé, n\'a rouvert qu\'en février 2009.',
                'en' => 'On 11 September 2008 an Eurotunnel freight shuttle loaded with 25 lorries and 2 vans left the Folkestone terminal at about 3.30 pm and a fire was detected on board. The evacuation to the French terminal ended nearly 3 hours after the start of the event and the firefighting the next day at about noon. The fire caused no death or serious injury. All the loaded wagons and the lorries burned, both locomotives were damaged and the North tunnel, very badly damaged, only reopened in February 2009.',
                'de' => 'Am 11. September 2008 verließ ein Eurotunnel-Güterzugshuttle mit 25 Lkw und 2 Transportern gegen 15.30 Uhr das Terminal Folkestone, und an Bord wurde ein Feuer entdeckt. Die Evakuierung zum französischen Terminal endete fast 3 Stunden nach Beginn des Ereignisses und die Brandbekämpfung am nächsten Tag gegen Mittag. Der Brand forderte weder Tote noch Schwerverletzte. Alle beladenen Wagen und die Lkw brannten aus, beide Lokomotiven wurden beschädigt, und der stark beschädigte Nordtunnel wurde erst im Februar 2009 wieder geöffnet.',
                'sources' => ['bea-tt-2008-09-11-eurotunnel'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 22 novembre 2010, avec des recommandations adressées à leurs destinataires.',
                'en' => 'The BEA-TT published its final report on 22 November 2010, with recommendations addressed to their recipients.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 22. November 2010, mit Empfehlungen an die jeweiligen Adressaten.',
                'sources' => ['bea-tt-2008-09-11-eurotunnel'],
            ],
        ],
    ],
];
