<?php

// Contexte sourcé de la collision du 18 mai 1912 à la sortie de la gare du Nord, à Paris, d'un train pour Creil
// (recherche du 21/11/2026).
return [
    'slug' => '1912-05-18-creil',
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
            'locator' => 'Mai, 18 mai 1912',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après Le Petit Parisien du 19 mai, Le Temps du 20 mai et Paris-Midi du 22 mai 1912.',
        ],
        'petit-parisien-1912-05-19' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 19 mai 1912',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1912-05-19',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5643417.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Cité par Wikipédia ; non lu.',
        ],
        'le-temps-1912-05-20' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 20 mai 1912',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1912-05-20',
            'locator' => 'p. 3-4',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k240860w/f3.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 18 mai 1912 vers 21 h 30, un train en partance pour Creil quittait la gare du Nord, à Paris. Sous le pont de la rue Doudeauville, une commande d\'aiguillage s\'est rompue.',
                'en' => 'On 18 May 1912 at about 9.30 pm, a train bound for Creil was leaving the Gare du Nord in Paris. Under the rue Doudeauville bridge, a points control rod broke.',
                'de' => 'Am 18. Mai 1912 gegen 21.30 Uhr verließ ein Zug nach Creil den Pariser Nordbahnhof. Unter der Brücke der Rue Doudeauville brach ein Weichenantrieb.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les dernières voitures du train pour Creil ont été prises en écharpe par un train venant en sens inverse de Montsoult-Maffliers.',
                'en' => 'The rear coaches of the Creil train were struck side-on by a train coming the other way from Montsoult-Maffliers.',
                'de' => 'Die letzten Wagen des Zuges nach Creil wurden von einem entgegenkommenden Zug aus Montsoult-Maffliers seitlich gerammt.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Neuf morts et 39 blessés ont été retirés des voitures disloquées. Six des blessés sont morts par la suite, d\'après la liste de Wikipédia, qui s\'appuie sur la presse de mai 1912.',
                'en' => 'Nine dead and 39 injured were taken from the wrecked coaches. Six of the injured died later, according to the Wikipedia list, which relies on the press of May 1912.',
                'de' => 'Aus den zertrümmerten Wagen wurden neun Tote und 39 Verletzte geborgen. Sechs der Verletzten starben später, so die Wikipedia-Liste, die sich auf die Presse vom Mai 1912 stützt.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '75056',
            'sources' => ['wp-liste-1912'],
            'fr' => 'Creil était la destination du train ; la collision a eu lieu à Paris, à la sortie de la gare du Nord, sous le pont de la rue Doudeauville.',
            'en' => 'Creil was the train\'s destination; the collision happened in Paris, just outside the Gare du Nord, under the rue Doudeauville bridge.',
            'de' => 'Creil war das Ziel des Zuges; der Zusammenstoß ereignete sich in Paris, kurz hinter dem Nordbahnhof unter der Brücke der Rue Doudeauville.',
        ],
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1912'],
            'fr' => 'La rupture d\'une commande d\'aiguillage a provoqué une collision entre deux trains ; aucun ouvrage ne s\'est effondré.',
            'en' => 'A broken points control caused a collision between two trains; no structure collapsed.',
            'de' => 'Ein gebrochener Weichenantrieb führte zu einem Zusammenstoß zweier Züge; kein Bauwerk stürzte ein.',
        ],
    ],
];
