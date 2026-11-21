<?php

// Contexte sourcé du déraillement d'un train de minerai sur un aiguillage saboté, réseau privé des Forges et Aciéries de la Marine à Homécourt, d'octobre 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-10-17-homecourt',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1912' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1912',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 17 octobre 1912, d\'après Le Temps du 18 octobre : à Homécourt, sur le réseau privé de la Compagnie des forges et aciéries de la Marine, un train de minerai déraille à 3 h sur un aiguillage saboté ; un accrocheur est tué.',
        ],
        'ddb-1912-10-18-gladbach' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 17. Okt.',
            'publisher' => 'Westdeutsche Landeszeitung (Gladbacher Volkszeitung) (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1912-10-18',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/NKRQAJ65J4MIKSZ5FMC6B2A7M7VDJ47T?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 17 octobre, d\'après Briey : à la suite d\'un sabotage, un wagon chargé de minerai a déraillé sur le chemin de fer privé d\'une mine à Homécourt ; un agent du chemin de fer a été tué.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 17 octobre 1912, vers 3 h, un train de minerai a déraillé sur un aiguillage saboté à Homécourt, sur le réseau privé de la Compagnie des forges et aciéries de la Marine, qui desservait ses mines.',
                'en' => 'On 17 October 1912, at about 3 am, an ore train derailed on sabotaged points at Homécourt, on the private network serving the mines of the Compagnie des forges et aciéries de la Marine.',
                'de' => 'Am 17. Oktober 1912 gegen 3 Uhr entgleiste in Homécourt auf einer sabotierten Weiche ein Erzzug, auf dem privaten Netz, das die Gruben der Compagnie des forges et aciéries de la Marine bediente.',
                'sources' => ['wp-liste-1912', 'ddb-1912-10-18-gladbach'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent chargé de l\'attelage des wagons a été tué.',
                'en' => 'A worker who coupled the wagons was killed.',
                'de' => 'Ein für das Kuppeln der Wagen zuständiger Arbeiter wurde getötet.',
                'sources' => ['wp-liste-1912', 'ddb-1912-10-18-gladbach'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1912', 'ddb-1912-10-18-gladbach'],
            'fr' => 'Un accrocheur tué selon la liste de Wikipédia (Le Temps du 18 octobre 1912) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One coupler killed according to the Wikipedia list (Le Temps of 18 October 1912) and the German press; the record gave no number of deaths.',
            'de' => 'Ein Kuppler getötet nach der Wikipedia-Liste (Le Temps vom 18. Oktober 1912) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
