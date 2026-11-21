<?php

// Contexte sourcé de l'accident de Perrigny-lès-Dijon du 4 février 1920 (recherche du 21/11/2026).
return [
    'slug' => '1920-02-04-perrigny-les-dijon',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1920' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1920',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '4 février, Perrigny',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1920&oldid=236476563#F%C3%A9vrier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Heure, lieu, trains, cause attribuée au mécanicien de l\'express, 18 morts et une soixantaine de blessés.',
        ],
        'pp-1920-02-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien, 6 février 1920',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1920-02-06',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k604193v.image.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
        'pp-1920-02-07' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien, 7 février 1920',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1920-02-07',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k6041947/f2.image.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; Gallica non consultable depuis cette machine.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 4 février 1920, vers minuit, un express Lyon-Paris percute un train de marchandises dans la gare de triage de Perrigny, près de Dijon, sur la ligne de Lyon à Paris.',
                'en' => 'On 4 February 1920, at about midnight, a Lyon-Paris express struck a goods train in the Perrigny marshalling yard, near Dijon, on the Lyon-Paris line.',
                'de' => 'Am 4. Februar 1920 gegen Mitternacht fuhr ein Schnellzug Lyon-Paris im Rangierbahnhof Perrigny bei Dijon auf der Strecke Lyon-Paris auf einen Güterzug auf.',
                'sources' => [
                    'wp-liste-1920',
                ],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les trois premières voitures de l\'express s\'écrasent contre la locomotive du train de marchandises, dont la machine et le tender sont renversés.',
                'en' => 'The first three coaches of the express were crushed against the locomotive of the goods train, whose engine and tender were overturned.',
                'de' => 'Die ersten drei Wagen des Schnellzugs wurden gegen die Lokomotive des Güterzugs gedrückt, deren Maschine und Tender umgeworfen wurden.',
                'sources' => [
                    'wp-liste-1920',
                ],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de 18 morts et d\'une soixantaine de blessés.',
                'en' => 'The toll is 18 dead and about sixty injured.',
                'de' => 'Die Bilanz beträgt 18 Tote und etwa sechzig Verletzte.',
                'sources' => [
                    'wp-liste-1920',
                ],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La responsabilité est attribuée au mécanicien de l\'express, qui aurait ignoré un signal d\'arrêt. La page lue ne mentionne pas de rapport d\'enquête.',
                'en' => 'Responsibility was attributed to the driver of the express, who is said to have ignored a stop signal. The page read mentions no inquiry report.',
                'de' => 'Die Verantwortung wurde dem Lokführer des Schnellzugs zugeschrieben, der ein Haltsignal missachtet haben soll. Die gelesene Seite erwähnt keinen Untersuchungsbericht.',
                'sources' => [
                    'wp-liste-1920',
                ],
            ],
        ],
    ],
];
