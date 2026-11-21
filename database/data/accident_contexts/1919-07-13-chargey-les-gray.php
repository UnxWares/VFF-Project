<?php

// Contexte sourcé de la collision de Chargey-lès-Gray du 13 juillet 1919 (recherche du 21/11/2026).
return [
    'slug' => '1919-07-13-chargey-les-gray',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Culmont - Chalindrey à Gray, vers 14 h entre les gares de Gray et de Chargey-lès-Gray, collision entre un train de Gray vers Chalindrey et un train de permissionnaires, cinq morts et une vingtaine de blessés.',
        ],
        'gallica-temps-1919-07-17' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 17 juillet 1919',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1919-07-17',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k243706m/f3.item.r=Gray.zoom',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Numéro cité par la liste de Wikipédia à l\'appui de l\'entrée ; page 3.',
        ],
        'gallica-bulletin-arc-1919-08' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Bulletin paroissial d\'Arc-lès-Gray, août 1919',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1919-08',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k925017p/f5.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Bulletin cité par la liste de Wikipédia à l\'appui de l\'entrée ; page 5.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 13 juillet 1919, vers 14 h, sur la ligne de Culmont - Chalindrey à Gray, un train allant de Gray à Chalindrey est entré en collision avec un train de permissionnaires, entre la gare de Gray et celle de Chargey-lès-Gray.',
                'en' => 'On 13 July 1919, at about 2 pm, on the Culmont - Chalindrey to Gray line, a train running from Gray to Chalindrey collided with a train carrying soldiers on leave, between Gray station and Chargey-lès-Gray station.',
                'de' => 'Am 13. Juli 1919 gegen 14 Uhr stieß auf der Strecke Culmont - Chalindrey nach Gray ein Zug von Gray nach Chalindrey zwischen dem Bahnhof Gray und dem Bahnhof Chargey-lès-Gray mit einem Urlauberzug zusammen.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia donne cinq morts et une vingtaine de blessés.',
                'en' => 'The Wikipedia list gives five dead and about twenty injured.',
                'de' => 'Die Wikipedia-Liste nennt fünf Tote und etwa zwanzig Verletzte.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le lieu exact n\'est pas établi : l\'entrée de la liste place la collision entre les gares de Gray et de Chargey-lès-Gray, sans préciser la commune de survenue.',
                'en' => 'The exact place is not established: the list entry places the collision between Gray and Chargey-lès-Gray stations, without saying in which commune it happened.',
                'de' => 'Der genaue Ort ist nicht belegt: Der Listeneintrag verortet den Zusammenstoß zwischen den Bahnhöfen Gray und Chargey-lès-Gray, ohne die Gemeinde zu nennen.',
                'sources' => ['wp-liste-1919'],
            ],
        ],
    ],
];
