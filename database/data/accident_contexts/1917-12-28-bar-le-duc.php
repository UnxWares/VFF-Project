<?php

// Contexte sourcé de la collision à l'entrée de la gare de Bar-le-Duc du 28 décembre 1917 (recherche du 21/11/2026).
return [
    'slug' => '1917-12-28-bar-le-duc',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1917' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1917',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1917&oldid=235357718#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : entrée de la gare de Bar-le-Duc, vers 22 h, train arrêté à un signal fermé percuté par le suivant, 2 morts et un nombre indéterminé de blessés.',
        ],
        'figaro-1917-12-29' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 29 décembre 1917',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1917-12-29',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2916360/f3.image.r=collision.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 28 décembre 1917, vers 22 heures, à l\'entrée de la gare de Bar-le-Duc, un train arrêté devant un signal fermé a été percuté par le train suivant.',
                'en' => 'On 28 December 1917, at about 10 pm, at the entrance of Bar-le-Duc station, a train stopped at a closed signal was struck by the train behind it.',
                'de' => 'Am 28. Dezember 1917 gegen 22 Uhr wurde an der Einfahrt des Bahnhofs Bar-le-Duc ein vor einem geschlossenen Signal haltender Zug vom nachfolgenden Zug gerammt.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait deux morts et un nombre indéterminé de blessés.',
                'en' => 'The accident killed two people and injured an unspecified number.',
                'de' => 'Bei dem Unfall starben zwei Menschen, eine unbestimmte Zahl wurde verletzt.',
                'sources' => ['wp-liste-1917'],
            ],
        ],
    ],
];
