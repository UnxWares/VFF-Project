<?php

// Contexte sourcé du déraillement d'un train de voyageurs entre Bayon et Charmes, ligne de Blainville - Damelevières à Lure, de février 1903 (recherche du 21/11/2026).
return [
    'slug' => '1903-02-14-virecourt',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1903' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1903',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Février',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1903&oldid=239810319#Février',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 14 février 1903, d\'après Le Matin du 15 février : vers 22 h 45, sur la ligne Nancy - Épinal, dans la forêt entre les gares de Bayon et de Charmes, un train de voyageurs déraille ; le mécanicien est tué, le chauffeur blessé, les trente voyageurs indemnes.',
        ],
        'nieuws-van-den-dag-1903-02-17' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenlandsch nieuws',
            'publisher' => 'Het nieuws van den dag',
            'author' => null,
            'published' => '1903-02-17',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010128505:mpeg21:a0116',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Entre Bayon et Charmes, un train de voyageurs a déraillé ; le mécanicien a été tué, le chauffeur blessé, les trente voyageurs sont indemnes ; l\'accident serait dû à la malveillance, une enquête judiciaire est ouverte.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 14 février 1903 vers 22 h 45, un train de voyageurs a déraillé en forêt entre les gares de Bayon et de Charmes, sur la ligne de Nancy à Épinal.',
                'en' => 'At about 10.45 pm on 14 February 1903, a passenger train derailed in the forest between Bayon and Charmes stations, on the Nancy to Épinal line.',
                'de' => 'Am 14. Februar 1903 gegen 22.45 Uhr entgleiste im Wald zwischen den Bahnhöfen Bayon und Charmes an der Strecke Nancy-Épinal ein Personenzug.',
                'sources' => ['wp-liste-1903', 'nieuws-van-den-dag-1903-02-17'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la presse néerlandaise, l\'accident était probablement dû à la malveillance, et une enquête judiciaire avait été ouverte.',
                'en' => 'According to the Dutch press, the accident was probably due to malicious action, and a judicial inquiry had been opened.',
                'de' => 'Nach der niederländischen Presse ging der Unfall vermutlich auf Böswilligkeit zurück; eine gerichtliche Untersuchung war eingeleitet.',
                'sources' => ['nieuws-van-den-dag-1903-02-17'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été tué et le chauffeur blessé. Les trente voyageurs s\'en sont sortis indemnes.',
                'en' => 'The driver was killed and the stoker injured. The thirty passengers were unhurt.',
                'de' => 'Der Lokomotivführer wurde getötet, der Heizer verletzt. Die dreißig Reisenden blieben unverletzt.',
                'sources' => ['wp-liste-1903', 'nieuws-van-den-dag-1903-02-17'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1903', 'nieuws-van-den-dag-1903-02-17'],
            'fr' => 'Mécanicien tué selon la liste de Wikipédia (Le Matin du 15 février 1903) et la presse néerlandaise du 17 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver killed according to the Wikipedia list (Le Matin of 15 February 1903) and the Dutch press of the 17th; the record gave no number of deaths.',
            'de' => 'Lokomotivführer getötet nach der Wikipedia-Liste (Le Matin vom 15. Februar 1903) und der niederländischen Presse vom 17.; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
