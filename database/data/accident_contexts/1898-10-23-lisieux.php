<?php

// Contexte sourcé du tamponnement de deux trains de marchandises entre Lisieux et Mesnil-Mauger, ligne de Paris à Cherbourg, octobre 1898 (recherche du 22/11/2026).
return [
    'slug' => '1898-10-23-lisieux',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1898',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1898',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 23 octobre 1898, d\'après Le Petit Parisien du 25 octobre : vers 11 h, sur la ligne de Paris à Cherbourg, entre Lisieux et Mesnil-Mauger, un train de marchandises en tamponne un autre ; le conducteur d\'arrière du train tamponné est tué.',
        ],
        'elux-1898-10-25-independance' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Lisieux (France), 24 oct.',
            'publisher' => 'L\'Indépendance luxembourgeoise (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1898-10-25',
            'locator' => 'p. 3',
            'url' => 'https://persist.lu/ark:70795/9pxbvx/pages/3/articles/DTL109',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche de Lisieux du 24 octobre : la veille vers 11 h, au kilomètre 204 de la ligne de Paris à Cherbourg, entre Lisieux et Mesnil-Mauger, le train de marchandises n° 2409 a tamponné le train n° 5919 ; le conducteur d\'arrière du 5919 tué, six wagons brisés ; voies dégagées dans la soirée.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 23 octobre 1898 vers 11 h, au kilomètre 204 de la ligne de Paris à Cherbourg, entre Lisieux et Mesnil-Mauger, le train de marchandises n° 2409 a tamponné le train de marchandises n° 5919. Six wagons ont été brisés. Les voies ont été dégagées dans la soirée.',
                'en' => 'On 23 October 1898 at about 11 am, at kilometre 204 of the Paris to Cherbourg line, between Lisieux and Mesnil-Mauger, goods train no. 2409 ran into goods train no. 5919. Six wagons were wrecked. The tracks were cleared in the evening.',
                'de' => 'Am 23. Oktober 1898 gegen 11 Uhr fuhr bei Kilometer 204 der Strecke Paris-Cherbourg, zwischen Lisieux und Mesnil-Mauger, der Güterzug Nr. 2409 auf den Güterzug Nr. 5919 auf. Sechs Wagen wurden zertrümmert. Am Abend waren die Gleise wieder frei.',
                'sources' => ['wp-liste-1890', 'elux-1898-10-25-independance'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le conducteur d\'arrière du train tamponné a été tué.',
                'en' => 'The rear guard of the train that was struck was killed.',
                'de' => 'Der Schlussschaffner des getroffenen Zuges wurde getötet.',
                'sources' => ['wp-liste-1890', 'elux-1898-10-25-independance'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'elux-1898-10-25-independance'],
            'fr' => 'Conducteur d\'arrière tué selon la liste de Wikipédia (Le Petit Parisien du 25 octobre 1898) et la dépêche de Lisieux parue dans L\'Indépendance luxembourgeoise ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Rear guard killed according to the Wikipedia list (Le Petit Parisien of 25 October 1898) and the Lisieux dispatch printed in L\'Indépendance luxembourgeoise; the record gave no number of deaths.',
            'de' => 'Schlussschaffner getötet nach der Wikipedia-Liste (Le Petit Parisien vom 25. Oktober 1898) und der in L\'Indépendance luxembourgeoise gedruckten Meldung aus Lisieux; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
