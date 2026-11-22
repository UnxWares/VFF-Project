<?php

// Contexte sourcé du déraillement d'une voiture d'un train militaire en gare de Labenne, ligne de Bordeaux à Bayonne, d'octobre 1897 (recherche du 21/11/2026).
return [
    'slug' => '1897-10-30-labenne',
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
            'locator' => '1897',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1897',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 30 octobre 1897 : à 17 h 30, sur la ligne de Bordeaux à Bayonne, en gare de Labenne, lors d\'une manœuvre pour remplacer la machine en panne d\'un train militaire, une voiture déraille sur un aiguillage et se renverse ; un réserviste est tué, huit blessés.',
        ],
        'delpher-1897-11-02-haarlem' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Allerlei',
            'publisher' => 'Opregte Haarlemsche Courant (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1897-11-02',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB12:000048107:mpeg21:a00034',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : samedi soir, à Labenne, à une cinquantaine de kilomètres de Bayonne, une partie d\'un train a déraillé ; deux morts et une vingtaine de blessés, dont quelques-uns grièvement, parmi lesquels des réservistes qui rentraient chez eux.',
        ],
        'delpher-1897-11-03-arnhem' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengde berichten',
            'publisher' => 'Arnhemsche courant (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1897-11-03',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB08:000093864:mpeg21:a0028',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même brève : à Labenne, près de Bayonne, une partie d\'un train a déraillé, deux morts et une vingtaine de blessés, dont des réservistes rentrant chez eux.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le samedi 30 octobre 1897, vers 17 h 30, en gare de Labenne, sur la ligne de Bordeaux à Bayonne, on manœuvrait pour remplacer la machine en panne d\'un train militaire qui ramenait des réservistes. Une voiture a déraillé sur un aiguillage et s\'est renversée.',
                'en' => 'On Saturday 30 October 1897, at about 5.30 pm, at Labenne station on the Bordeaux to Bayonne line, shunting was under way to replace the failed engine of a military train carrying reservists home. One coach derailed on a set of points and overturned.',
                'de' => 'Am Samstag, dem 30. Oktober 1897, gegen 17.30 Uhr wurde im Bahnhof Labenne an der Strecke Bordeaux-Bayonne rangiert, um die ausgefallene Lokomotive eines Militärzugs mit heimkehrenden Reservisten zu ersetzen. Ein Wagen entgleiste an einer Weiche und stürzte um.',
                'sources' => ['wp-liste-1890', 'delpher-1897-11-02-haarlem'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte un réserviste tué et huit blessés ; la presse néerlandaise parle de deux morts et d\'une vingtaine de blessés, dont quelques-uns grièvement.',
                'en' => 'The Wikipedia list counts one reservist killed and eight injured; the Dutch press speaks of two dead and about twenty injured, some of them seriously.',
                'de' => 'Die Wikipedia-Liste zählt einen getöteten Reservisten und acht Verletzte; die niederländische Presse spricht von zwei Toten und etwa zwanzig Verletzten, einige davon schwer.',
                'sources' => ['wp-liste-1890', 'delpher-1897-11-02-haarlem', 'delpher-1897-11-03-arnhem'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : un réserviste tué et huit blessés selon la liste de Wikipédia, deux morts et une vingtaine de blessés dans la brève reprise par plusieurs journaux néerlandais. Faute d\'autre source, la fiche suit la liste.',
                'en' => 'The toll differs: one reservist killed and eight injured according to the Wikipedia list, two dead and about twenty injured in the brief note carried by several Dutch papers. With no other source, the record follows the list.',
                'de' => 'Die Opferzahl weicht ab: ein getöteter Reservist und acht Verletzte nach der Wikipedia-Liste, zwei Tote und etwa zwanzig Verletzte in der von mehreren niederländischen Zeitungen übernommenen Kurzmeldung. Mangels weiterer Quelle folgt der Eintrag der Liste.',
                'sources' => ['wp-liste-1890', 'delpher-1897-11-02-haarlem', 'delpher-1897-11-03-arnhem'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890'],
            'fr' => 'Un réserviste tué selon la liste de Wikipédia ; la brève néerlandaise parle de deux morts ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One reservist killed according to the Wikipedia list; the Dutch note speaks of two dead; the record gave no number of deaths.',
            'de' => 'Ein Reservist getötet nach der Wikipedia-Liste; die niederländische Kurzmeldung spricht von zwei Toten; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
