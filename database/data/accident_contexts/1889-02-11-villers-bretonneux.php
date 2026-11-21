<?php

// Contexte sourcé de la collision de l'express Bâle - Calais et d'un train de marchandises en gare de Villers-Bretonneux, ligne d'Amiens à Laon, de février 1889 (recherche du 21/11/2026).
return [
    'slug' => '1889-02-11-villers-bretonneux',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1889',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1889',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 11 février 1889, d\'après La Presse du 14 février : en gare de Villers-Bretonneux, sur la ligne d\'Amiens à Laon, vers 11 h 35, un train de marchandises en manœuvre est pris en écharpe par l\'express Bâle - Calais, dont la locomotive et les trois premières voitures déraillent ; le chauffeur de l\'express est tué, les voyageurs indemnes.',
        ],
        'delpher-1889-02-15-haarlem' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Verschillende Berichten',
            'publisher' => 'Opregte Haarlemsche Courant (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1889-02-15',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB12:000046039:mpeg21:a00018',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : lundi matin, l\'express de Bâle à Calais est entré en collision avec un train de marchandises à la gare de Villers-Bretonneux ; le chauffeur tué, plusieurs voyageurs blessés ; l\'express est arrivé à Amiens avec une heure de retard.',
        ],
        'delpher-1889-02-16-zuidholland' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk',
            'publisher' => 'Dagblad van Zuidholland en \'s Gravenhage (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1889-02-16',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB19:003599081:mpeg21:a00048',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : l\'express de Bâle a heurté un train de marchandises à Villers-Bretonneux ; on parle de deux morts et de plusieurs blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le lundi 11 février 1889, vers 11 h 35, en gare de Villers-Bretonneux, sur la ligne d\'Amiens à Laon, l\'express de Bâle à Calais a pris en écharpe un train de marchandises en manœuvre. La locomotive et les trois premières voitures de l\'express ont déraillé, et l\'express est arrivé à Amiens avec une heure de retard.',
                'en' => 'On Monday 11 February 1889, at about 11.35 am, at Villers-Bretonneux station on the Amiens to Laon line, the Basel to Calais express struck a goods train that was shunting. The engine and the first three coaches of the express derailed, and the express reached Amiens an hour late.',
                'de' => 'Am Montag, dem 11. Februar 1889, gegen 11.35 Uhr rammte im Bahnhof Villers-Bretonneux an der Strecke Amiens-Laon der Expresszug Basel-Calais seitlich einen rangierenden Güterzug. Lokomotive und die ersten drei Wagen des Expresszugs entgleisten; er erreichte Amiens mit einer Stunde Verspätung.',
                'sources' => ['wp-liste-1880', 'delpher-1889-02-15-haarlem'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur de l\'express a été tué.',
                'en' => 'The stoker of the express was killed.',
                'de' => 'Der Heizer des Expresszugs wurde getötet.',
                'sources' => ['wp-liste-1880', 'delpher-1889-02-15-haarlem'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia dit les voyageurs indemnes, l\'Opregte Haarlemsche Courant plusieurs voyageurs blessés. Le Dagblad van Zuidholland rapporte le bruit de deux morts.',
                'en' => 'The Wikipedia list says the passengers were unhurt, the Opregte Haarlemsche Courant that several were injured. The Dagblad van Zuidholland reports talk of two dead.',
                'de' => 'Nach der Wikipedia-Liste blieben die Reisenden unverletzt, nach der Opregte Haarlemsche Courant wurden mehrere verletzt. Das Dagblad van Zuidholland meldet Gerüchte über zwei Tote.',
                'sources' => ['wp-liste-1880', 'delpher-1889-02-15-haarlem', 'delpher-1889-02-16-zuidholland'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1880', 'delpher-1889-02-15-haarlem'],
            'fr' => 'Chauffeur de l\'express tué selon la liste de Wikipédia (La Presse du 14 février 1889) et l\'Opregte Haarlemsche Courant du 15 février ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Stoker of the express killed according to the Wikipedia list (La Presse of 14 February 1889) and the Opregte Haarlemsche Courant of 15 February; the record gave no number of deaths.',
            'de' => 'Heizer des Expresszugs getötet nach der Wikipedia-Liste (La Presse vom 14. Februar 1889) und der Opregte Haarlemsche Courant vom 15. Februar; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1880', 'delpher-1889-02-15-haarlem'],
            'fr' => 'L\'express a heurté un train de marchandises avant de dérailler, selon la liste de Wikipédia et la presse néerlandaise ; la fiche le classait en déraillement.',
            'en' => 'The express struck a goods train before derailing, according to the Wikipedia list and the Dutch press; the record classed it as a derailment.',
            'de' => 'Der Expresszug prallte nach der Wikipedia-Liste und der niederländischen Presse auf einen Güterzug, bevor er entgleiste; der Eintrag führte den Unfall als Entgleisung.',
        ],
    ],
];
