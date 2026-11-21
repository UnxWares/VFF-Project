<?php

// Contexte sourcé de la collision du 3 juin 1927 près de la gare de Bessay-sur-Allier, au sud de Moulins
// (recherche du 21/11/2026).
return [
    'slug' => '1927-06-03-moulins',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1927' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1927',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juin, 3 juin 1927',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1927&oldid=233501538#Juin',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de juin 1927 (Gallica).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 juin 1927 vers 2 h, sur la ligne de Paris à Clermont-Ferrand, peu après Moulins, un train de marchandises roulait depuis quelques kilomètres avec un wagon déraillé qui empiétait sur la voie opposée.',
                'en' => 'On 3 June 1927 at about 2 am, on the Paris to Clermont-Ferrand line just beyond Moulins, a freight train had been running for a few kilometres with a derailed wagon fouling the opposite track.',
                'de' => 'Am 3. Juni 1927 gegen 2 Uhr fuhr auf der Strecke Paris-Clermont-Ferrand kurz hinter Moulins ein Güterzug seit einigen Kilometern mit einem entgleisten Wagen, der in das Gegengleis ragte.',
                'sources' => ['wp-liste-1927'],
            ],
        ],
        'course' => [
            [
                'fr' => 'À 500 m de la gare de Bessay-sur-Allier, l\'express Paris - Clermont - Nîmes l\'a croisé et a heurté ce wagon. Sa locomotive s\'est couchée, et le fourgon et les premières voitures sont venus s\'y écraser. On a compté neuf morts et 23 blessés.',
                'en' => '500 m from Bessay-sur-Allier station, the Paris to Clermont and Nîmes express passed it and hit the wagon. Its locomotive fell on its side, and the van and the leading coaches smashed into it. Nine people were killed and 23 injured.',
                'de' => '500 m vor dem Bahnhof Bessay-sur-Allier begegnete ihm der Schnellzug Paris-Clermont-Nîmes und prallte gegen diesen Wagen. Seine Lokomotive kippte um, und der Packwagen und die vorderen Wagen fuhren auf sie auf. Es gab neun Tote und 23 Verletzte.',
                'sources' => ['wp-liste-1927'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '03025',
            'sources' => ['wp-liste-1927'],
            'fr' => 'L\'accident a eu lieu à 500 m de la gare de Bessay-sur-Allier, au sud de Moulins.',
            'en' => 'The accident happened 500 m from Bessay-sur-Allier station, south of Moulins.',
            'de' => 'Der Unfall ereignete sich 500 m vor dem Bahnhof Bessay-sur-Allier südlich von Moulins.',
        ],
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1927'],
            'fr' => 'L\'express a heurté un wagon déraillé d\'un train de marchandises qu\'il croisait : c\'est une collision, le déraillement du wagon en est la cause.',
            'en' => 'The express hit a derailed wagon of a freight train it was passing: this is a collision, caused by the wagon\'s derailment.',
            'de' => 'Der Schnellzug prallte gegen einen entgleisten Wagen eines entgegenkommenden Güterzugs: Es handelt sich um einen Zusammenstoß, verursacht durch die Entgleisung des Wagens.',
        ],
    ],
];
