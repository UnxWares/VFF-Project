<?php

// Contexte sourcé de du franchissement d'un signal fermé et du talonnage d'une aiguille par un TER à Antibes (Alpes-Maritimes) du 26 décembre 2016 (recherche du 23/11/2026).
return [
    'slug' => '2016-12-26-antibes',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2016-12-26-antibes' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Talonnage d’une aiguille par un TER à Antibes (06)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/antibes-06-26-12-2016-franchissement-d-un-signal-r253.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 26 décembre 2016, un TER de Vintimille à Cannes a talonné une aiguille en voie principale après avoir franchi un carré fermé. Le conducteur n\'a été relevé que le lendemain. Il n\'y a eu aucune victime ni dégât au matériel roulant ; seule l\'aiguille talonnée a été détériorée, ce qui a rendu impossible son franchissement et donc la circulation sur la voie principale.',
                'en' => 'On 26 December 2016 a regional train from Ventimiglia to Cannes ran through a set of points on the main line after passing a closed signal. The driver was relieved only the next day. There were no casualties and no damage to the rolling stock; only the points were damaged, making them impassable and so stopping traffic on the main line.',
                'de' => 'Am 26. Dezember 2016 fuhr ein Regionalzug von Ventimiglia nach Cannes, nachdem er ein Halt zeigendes Signal überfahren hatte, eine Weiche im Hauptgleis auf. Der Lokführer wurde erst am nächsten Tag abgelöst. Es gab keine Opfer und keinen Schaden am rollenden Material; nur die aufgefahrene Weiche wurde beschädigt, was ihre Befahrung und damit den Verkehr auf dem Hauptgleis unmöglich machte.',
                'sources' => ['bea-tt-2016-12-26-antibes'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 2 juillet 2018 (thèmes : signalisation, talonnage d\'aiguille), avec des recommandations adressées à SNCF Mobilités et à SNCF Réseau.',
                'en' => 'The BEA-TT published its final report on 2 July 2018 (themes: signalling, running through points), with recommendations addressed to SNCF Mobilités and SNCF Réseau.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 2. Juli 2018 (Themen: Signalisierung, Auffahren auf Weichen), mit Empfehlungen an SNCF Mobilités und SNCF Réseau.',
                'sources' => ['bea-tt-2016-12-26-antibes'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2016-12-26-antibes'],
            'fr' => 'Le BEA-TT indique qu\'il n\'y a eu aucune victime.',
            'en' => 'The BEA-TT states that there were no casualties.',
            'de' => 'Die BEA-TT gibt an, dass es keine Opfer gab.',
        ],
    ],
];
