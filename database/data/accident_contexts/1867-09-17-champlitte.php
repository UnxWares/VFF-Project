<?php

// Contexte sourcé du déraillement d'un train de voyageurs entre Maâtz et Champlitte, ligne de Gray à Chalindrey, septembre 1867 (recherche du 22/11/2026).
return [
    'slug' => '1867-09-17-champlitte',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1867',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1867',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 17 septembre 1867, d\'après Le Temps des 18 et 21 septembre : sur la ligne de Chalindrey à Gray, entre Maâtz et Champlitte, un train de voyageurs déraille ; après rupture de l\'attelage, la machine tombe dans un fossé ; mécanicien et chauffeur tués, chef de train blessé, voyageurs contusionnés.',
        ],
        'elux-1867-09-24-union' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Variétés',
            'publisher' => 'L\'Union, Luxembourg (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1867-09-24',
            'locator' => 'p. 3',
            'url' => 'https://persist.lu/ark:70795/67qhxr/pages/3/articles/DTL42',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Récit d\'après un témoin oculaire : sur la ligne de Gray à Chalindrey, de la compagnie de l\'Est, entre Maatz et Champlitte, vers 7 h du matin, la locomotive a déraillé, s\'est séparée du train et a été précipitée dans le fossé ; mécanicien et chauffeur tués, chef de train grièvement blessé, plusieurs voyageurs atteints ; l\'article discute du rôle de la rupture de l\'attelage.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 17 septembre 1867 vers 7 h, sur la ligne de Gray à Chalindrey de la compagnie de l\'Est, la locomotive d\'un train de voyageurs a déraillé entre Maâtz et Champlitte. L\'attelage s\'est rompu, et la machine, séparée du train, est tombée dans le fossé.',
                'en' => 'On 17 September 1867 at about 7 a.m., on the Eastern company\'s Gray to Chalindrey line, the engine of a passenger train derailed between Maâtz and Champlitte. The coupling broke, and the engine, separated from the train, fell into the ditch.',
                'de' => 'Am 17. September 1867 gegen 7 Uhr entgleiste auf der Strecke Gray-Chalindrey der Ostbahn zwischen Maâtz und Champlitte die Lokomotive eines Personenzugs. Die Kupplung riss, und die vom Zug getrennte Maschine stürzte in den Graben.',
                'sources' => ['wp-liste-1860', 'elux-1867-09-24-union'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués, le chef de train grièvement blessé. Plusieurs voyageurs ont été contusionnés.',
                'en' => 'The driver and the fireman were killed and the guard was badly injured. Several passengers were bruised.',
                'de' => 'Lokomotivführer und Heizer wurden getötet, der Zugführer schwer verletzt. Mehrere Reisende erlitten Prellungen.',
                'sources' => ['wp-liste-1860', 'elux-1867-09-24-union'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1860', 'elux-1867-09-24-union'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Temps du 18 septembre 1867) et L\'Union du 24 septembre ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and fireman killed according to the Wikipedia list (Le Temps of 18 September 1867) and L\'Union of 24 September; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste (Le Temps vom 18. September 1867) und L\'Union vom 24. September; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
