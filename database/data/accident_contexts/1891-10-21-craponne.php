<?php

// Contexte sourcé de l'effondrement du sol sous un train de Mornant à Lyon près de Craponne, ligne à voie métrique de l'Ouest-Lyonnais, d'octobre 1891 (recherche du 21/11/2026).
return [
    'slug' => '1891-10-21-craponne',
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
            'locator' => '1891',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1891',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 21 octobre 1891, d\'après La Presse du 23 octobre : sur la ligne à voie métrique de Lyon à Mornant de la compagnie Fourvière Ouest-Lyonnais, près de Craponne, le sol s\'effondre au passage d\'un train ; la locomotive et la première voiture tombent dans une cavité d\'une dizaine de mètres ; le mécanicien et le chauffeur sont écrasés, l\'un des cinq voyageurs est blessé.',
        ],
        'ddb-1891-10-25-aachen' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 22. Oktober. (Eisenbahnunglück.)',
            'publisher' => 'Aachener Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1891-10-25',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/FJ5V6GPKFDDA6YUEHMBVW42B46ZEQTFM?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 22 octobre : hier, sous un train de voyageurs de trois voitures allant de Mornant à Lyon, le sol s\'est soudain affaissé entre Craponne et un lieu que la dépêche nomme « Petardière » ; la locomotive est tombée dans le trou, la première voiture a été brisée ; le mécanicien et le chauffeur écrasés, un voyageur blessé ; il n\'y avait que cinq voyageurs, aucun dans la première voiture.',
        ],
        'ddb-1891-10-24-koelnische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 22. Oct.',
            'publisher' => 'Kölnische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1891-10-24',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/N34HPFTI3YIWR2VPXWGSRFMEBNEUQMOQ?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle : effondrement du sol sous un train près de Craponne, mécanicien et chauffeur tués.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 21 octobre 1891, sur la ligne à voie métrique de Lyon à Mornant de la compagnie Fourvière Ouest-Lyonnais, le sol s\'est effondré près de Craponne sous un train de voyageurs de trois voitures qui allait de Mornant à Lyon. La locomotive et la première voiture sont tombées dans une cavité d\'une dizaine de mètres.',
                'en' => 'On 21 October 1891, on the metre-gauge Lyon to Mornant line of the Fourvière Ouest-Lyonnais company, the ground gave way near Craponne under a three-coach passenger train running from Mornant to Lyon. The engine and the first coach fell into a cavity about ten metres deep.',
                'de' => 'Am 21. Oktober 1891 brach auf der Meterspurstrecke Lyon-Mornant der Gesellschaft Fourvière Ouest-Lyonnais bei Craponne der Boden unter einem Personenzug aus drei Wagen ein, der von Mornant nach Lyon fuhr. Lokomotive und erster Wagen stürzten in eine etwa zehn Meter tiefe Höhlung.',
                'sources' => ['wp-liste-1890', 'ddb-1891-10-25-aachen'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été écrasés. Le train ne transportait que cinq voyageurs, aucun dans la première voiture ; l\'un d\'eux a été blessé.',
                'en' => 'The driver and the stoker were crushed. The train carried only five passengers, none in the first coach; one of them was injured.',
                'de' => 'Lokführer und Heizer wurden zermalmt. Der Zug beförderte nur fünf Reisende, keinen im ersten Wagen; einer von ihnen wurde verletzt.',
                'sources' => ['wp-liste-1890', 'ddb-1891-10-25-aachen', 'ddb-1891-10-24-koelnische'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'ddb-1891-10-25-aachen', 'ddb-1891-10-24-koelnische'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (La Presse du 23 octobre 1891) et la dépêche de Paris du 22 octobre ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (La Presse of 23 October 1891) and the Paris dispatch of 22 October; the record gave no number of deaths.',
            'de' => 'Lokführer und Heizer getötet nach der Wikipedia-Liste (La Presse vom 23. Oktober 1891) und der Pariser Depesche vom 22. Oktober; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1891-10-25-aachen'],
            'fr' => 'Un voyageur blessé selon la liste de Wikipédia et la dépêche de Paris du 22 octobre ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'One passenger injured according to the Wikipedia list and the Paris dispatch of 22 October; the record gave no number of injured.',
            'de' => 'Ein Reisender verletzt nach der Wikipedia-Liste und der Pariser Depesche vom 22. Oktober; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
