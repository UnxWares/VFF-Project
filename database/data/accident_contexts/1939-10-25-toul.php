<?php

// Contexte sourcé de la collision de la bifurcation de Toul du 25 octobre 1939 (recherche du 21/11/2026).
return [
    'slug' => '1939-10-25-toul',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1939' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1939',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1939&oldid=233504457#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : vers 6 h, bifurcation des lignes de Paris à Strasbourg et de Culmont-Chalindrey, train Toul - Commercy percuté par un convoi venant de Neufchâteau, 2 morts, 3 blessés graves et plusieurs légers.',
        ],
        'matin-1939-10-26' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 26 octobre 1939',
            'publisher' => 'Le Matin',
            'author' => null,
            'published' => '1939-10-26',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5864932/f3.image.r=d%C3%A9raille.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 25 octobre 1939, vers 6 heures, à la bifurcation de la ligne de Paris à Strasbourg et de la ligne de Culmont-Chalindrey à Toul, un train de voyageurs qui quittait Toul pour Commercy a été percuté par un convoi venant de Neufchâteau.',
                'en' => 'On 25 October 1939, at about 6 am, at the junction of the Paris to Strasbourg line and the Culmont-Chalindrey to Toul line, a passenger train leaving Toul for Commercy was struck by a train coming from Neufchâteau.',
                'de' => 'Am 25. Oktober 1939 gegen 6 Uhr wurde an der Abzweigung der Strecke von Paris nach Straßburg und der Strecke von Culmont-Chalindrey nach Toul ein Reisezug, der Toul in Richtung Commercy verließ, von einem aus Neufchâteau kommenden Zug gerammt.',
                'sources' => ['wp-liste-1939'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait deux morts, trois blessés graves et plusieurs blessés légers.',
                'en' => 'The accident killed two people and left three seriously injured and several slightly injured.',
                'de' => 'Bei dem Unfall starben zwei Menschen, drei wurden schwer und mehrere leicht verletzt.',
                'sources' => ['wp-liste-1939'],
            ],
        ],
    ],
];
