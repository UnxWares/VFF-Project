<?php

// Contexte sourcé du déraillement des ateliers de La Chapelle, à Paris, du 14 septembre 1931 (recherche du 23/11/2026).
return [
    'slug' => '1931-09-14-paris',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-1931' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1931',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1931, 14 septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1931&oldid=235357732#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Entrée de la liste : vers 16 h 30, au niveau des ateliers de La Chapelle, erreur d\'aiguillage, déraillement d\'un train de voyageurs vide refoulant vers la gare du Nord, le conducteur est tué dans son fourgon. Cite Le Matin du 15 septembre 1931.',
        ],
        'matin-1931-09-15' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 15 septembre 1931',
            'publisher' => 'Le Matin (numérisé sur Gallica)',
            'author' => null,
            'published' => '1931-09-15',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5773767/f4.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-23',
            'note' => 'Page 4, citée par la liste de Wikipédia ; non lue.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 14 septembre 1931 vers 16 h 30, au niveau des ateliers de La Chapelle, une erreur d\'aiguillage a fait dérailler un train de voyageurs vide qui refoulait vers la gare du Nord pour être mis à quai, d\'après la liste de Wikipédia.',
                'en' => 'On 14 September 1931 at about 4.30 p.m., level with the La Chapelle workshops, a points error derailed an empty passenger train that was backing towards the Gare du Nord to be placed at a platform, according to the Wikipedia list.',
                'de' => 'Am 14. September 1931 gegen 16.30 Uhr entgleiste auf Höhe der Werkstätten von La Chapelle durch einen Weichenfehler ein leerer Reisezug, der zur Bereitstellung am Bahnsteig in Richtung Gare du Nord zurückgeschoben wurde, so die Wikipedia-Liste.',
                'sources' => ['wp-liste-1931'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le conducteur, l\'agent de sécurité du train, a été tué dans son fourgon renversé, selon la liste de Wikipédia.',
                'en' => 'The conducteur, the train\'s safety agent, was killed in his overturned van, according to the Wikipedia list.',
                'de' => 'Der conducteur, der Sicherheitsbeamte des Zuges, wurde in seinem umgekippten Packwagen getötet, so die Wikipedia-Liste.',
                'sources' => ['wp-liste-1931'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La carte place l\'accident à la gare de Paris-Nord, position de la gare citée par la liste de Wikipédia. Le texte de la liste le situe au niveau des ateliers de La Chapelle, sur les voies d\'accès à la gare : la gare du Nord est la gare de rattachement, pas le lieu du déraillement.',
                'en' => 'The map places the accident at Paris-Nord station, the position of the station named by the Wikipedia list. The list\'s text puts it level with the La Chapelle workshops, on the access tracks to the station: Paris-Nord is the station it is linked to, not the place of the derailment.',
                'de' => 'Die Karte setzt den Unfall an den Bahnhof Paris-Nord, die Position des in der Wikipedia-Liste genannten Bahnhofs. Der Text der Liste verortet ihn auf Höhe der Werkstätten von La Chapelle, auf den Zufahrtsgleisen zum Bahnhof: Paris-Nord ist der zugeordnete Bahnhof, nicht der Ort der Entgleisung.',
                'sources' => ['wp-liste-1931'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1931'],
            'fr' => 'Un mort, le conducteur du train, selon la liste de Wikipédia ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One dead, the train\'s conducteur, according to the Wikipedia list; the record gave no number of deaths.',
            'de' => 'Ein Toter, der conducteur des Zuges, nach der Wikipedia-Liste; der Eintrag nannte keine Zahl von Toten.',
            'single_source' => true,
        ],
    ],
];
