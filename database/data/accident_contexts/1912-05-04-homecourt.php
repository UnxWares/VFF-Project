<?php

// Contexte sourcé de l'explosion d'Homécourt du 4 mai 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-05-04-homecourt',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1912' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1912',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Mai',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : vers 18 h, réseau de la mine de fer du Fond de La Noue à Homécourt, un wagonnet chargé d\'explosifs poussé par des ouvriers est percuté par un train de minerai, explosion, quatre morts et quatre blessés graves dont l\'un meurt peu après.',
        ],
        'gallica-petit-parisien-1912-05-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 6 mai 1912',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1912-05-06',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5643286.r=tamponne.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Numéro cité par la liste de Wikipédia à l\'appui de l\'entrée ; page 1.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 4 mai 1912, vers 18 h, sur le réseau de la mine de fer du Fond de La Noue à Homécourt, des ouvriers poussaient un wagonnet chargé d\'explosifs de mine lorsqu\'un train de minerai l\'a percuté et renversé.',
                'en' => 'On 4 May 1912, at about 6 pm, on the network of the Fond de La Noue iron mine at Homécourt, workers were pushing a tub loaded with mining explosives when an ore train struck it and overturned it.',
                'de' => 'Am 4. Mai 1912 gegen 18 Uhr schoben auf dem Netz der Eisenerzgrube Fond de La Noue in Homécourt Arbeiter einen mit Sprengstoff beladenen Förderwagen, als ein Erzzug ihn rammte und umwarf.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'explosion des explosifs a suivi le choc.',
                'en' => 'The explosives detonated after the impact.',
                'de' => 'Nach dem Aufprall explodierte der Sprengstoff.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte quatre morts et quatre blessés graves, dont l\'un est décédé peu après.',
                'en' => 'The Wikipedia list counts four dead and four seriously injured, one of whom died shortly afterwards.',
                'de' => 'Die Wikipedia-Liste zählt vier Tote und vier Schwerverletzte, von denen einer kurz danach starb.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le décès d\'un blessé grave peu après porterait le nombre de morts à cinq ; l\'entrée de la liste ne précise pas si ce décès est compté dans les quatre morts.',
                'en' => 'The death of a seriously injured person shortly afterwards would bring the number of dead to five; the list entry does not say whether this death is included in the four dead.',
                'de' => 'Der Tod eines Schwerverletzten kurz danach würde die Zahl der Toten auf fünf erhöhen; der Listeneintrag sagt nicht, ob dieser Tod in den vier Toten enthalten ist.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
    ],
];
