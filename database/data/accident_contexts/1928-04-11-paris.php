<?php

// Contexte sourcé de l'accident de Paris (gare du Nord) du 11 avril 1928 (recherche du 21/11/2026).
return [
    'slug' => '1928-04-11-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1928' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1928',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1928&oldid=233501561#Avril',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 11 avril 1928 ; elle renvoie à Le Matin du 12 avril 1928, pp. 1 et 2.',
        ],
        'matin-1928' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin, 12 avril 1928',
            'publisher' => 'Gallica (BnF)',
            'author' => null,
            'published' => '1928-04-12',
            'locator' => 'pp. 1 et 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k576126m/f1.image.r=d%C3%A9raille.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article de presse cité par Wikipédia ; non lisible depuis la machine de recherche, à consulter.',
        ],
        'commons-rol-1928' => [
            'provider' => 'commons',
            'kind' => 'archive',
            'reliability' => 'press',
            'title' => 'Accident à la gare du Nord, 11 avril 1928 (photographie de presse, agence Rol)',
            'publisher' => 'Wikimedia Commons',
            'author' => null,
            'published' => '1928-04-11',
            'locator' => null,
            'url' => 'https://commons.wikimedia.org/wiki/File:Accident_%C3%A0_la_gare_du_Nord,_11_avril_1928.jpg',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Photographie de l\'agence Rol, domaine public ; confirme la date et le lieu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 11 avril 1928, à environ 800 mètres de la gare de Paris-Nord, sous le pont de la rue Doudeauville, un express à destination d\'Amiens et un train de banlieue arrivant de Gonesse entrent en collision frontale.',
                'en' => 'On 11 April 1928, about 800 metres from Paris-Nord station, under the rue Doudeauville bridge, an express bound for Amiens and a suburban train arriving from Gonesse collided head-on.',
                'de' => 'Am 11. April 1928 stießen etwa 800 Meter vom Pariser Nordbahnhof entfernt, unter der Brücke der Rue Doudeauville, ein Schnellzug nach Amiens und ein aus Gonesse kommender Vorortzug frontal zusammen.',
                'sources' => ['wp-liste-1928', 'commons-rol-1928'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Derrière les locomotives, les voitures des deux trains se télescopent et se disloquent.',
                'en' => 'Behind the locomotives, the coaches of both trains telescoped and broke apart.',
                'de' => 'Hinter den Lokomotiven schoben sich die Wagen beider Züge ineinander und brachen auseinander.',
                'sources' => ['wp-liste-1928'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait quinze morts et trente-quatre blessés.',
                'en' => 'The accident killed fifteen people and injured thirty-four.',
                'de' => 'Bei dem Unfall starben fünfzehn Menschen, vierunddreißig wurden verletzt.',
                'sources' => ['wp-liste-1928'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste consultée, le mécanicien de l\'express, récemment promu et peu familier des lieux, a franchi un signal d\'arrêt fermé.',
                'en' => 'According to the list consulted, the driver of the express, recently promoted and unfamiliar with the site, ran past a closed stop signal.',
                'de' => 'Nach der eingesehenen Liste überfuhr der erst kürzlich beförderte und mit der Örtlichkeit wenig vertraute Lokführer des Schnellzugs ein geschlossenes Haltsignal.',
                'sources' => ['wp-liste-1928'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La carte place l\'accident à la gare de Paris-Nord, position de la gare citée par la liste de Wikipédia. D\'après cette liste, le choc a eu lieu à environ 800 mètres de la gare, sous le pont de la rue Doudeauville : la gare du Nord est la gare de rattachement, pas le lieu du choc.',
                'en' => 'The map places the accident at Paris-Nord station, the position of the station named by the Wikipedia list. According to that list the collision took place about 800 metres from the station, under the rue Doudeauville bridge: Paris-Nord is the station it is linked to, not the place of the impact.',
                'de' => 'Die Karte setzt den Unfall an den Bahnhof Paris-Nord, die Position des in der Wikipedia-Liste genannten Bahnhofs. Laut dieser Liste ereignete sich der Zusammenstoß etwa 800 Meter vom Bahnhof entfernt, unter der Brücke der Rue Doudeauville: Paris-Nord ist der zugeordnete Bahnhof, nicht der Ort des Zusammenstoßes.',
                'sources' => ['wp-liste-1928'],
            ],
        ],
    ],
    'images' => [
        [
            'file' => 'File:Accident à la gare du Nord, 11 avril 1928.jpg',
            'caption' => ['fr' => 'Les suites de la collision à proximité de la gare du Nord, le 11 avril 1928 (photographie de l\'agence Rol).', 'en' => 'The aftermath of the collision near the Gare du Nord on 11 April 1928 (Agence Rol press photograph).', 'de' => 'Die Folgen des Zusammenstoßes in der Nähe des Gare du Nord am 11. April 1928 (Pressefoto der Agentur Rol).'],
        ],
    ],
];
