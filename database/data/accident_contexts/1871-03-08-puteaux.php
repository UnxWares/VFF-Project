<?php

// Contexte sourcé de la collision de Puteaux du 8 mars 1871 (recherche du 21/11/2026).
return [
    'slug' => '1871-03-08-puteaux',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1871',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1871',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Seule source lue sur cet accident ; s\'appuie sur Le Constitutionnel des 11 et 13 mars 1871 et sur Grison (1882).',
        ],
        'constitutionnel-1871-03-11' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Constitutionnel, 11 mars 1871, page 2',
            'publisher' => 'Gallica (BnF)',
            'author' => null,
            'published' => '1871-03-11',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k676006t/f2.item.r=Puteaux.zoom',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de presse et communiqué administratif cités par Wikipédia ; Gallica inaccessible, non lu.',
        ],
        'grison-accidents' => [
            'provider' => 'gallica',
            'kind' => 'book',
            'reliability' => 'scholarly',
            'title' => 'Accidents de chemins de fer, grandes catastrophes, page 45',
            'publisher' => 'Gallica (BnF)',
            'author' => 'Georges Grison',
            'published' => '1882',
            'locator' => 'p. 45',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k6260485t/f69.vertical.r=%22Georges%20Grison%22accidents%20de%20chemin%20de%20fer',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Ouvrage du XIXe siècle cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 8 mars 1871 vers 19 heures, sur le réseau de la Compagnie de l\'Ouest, un train rapatriant en Allemagne des soldats prussiens malades ou blessés s\'est arrêté juste avant l\'entrée de la gare de Puteaux (Seine).',
                'en' => 'On 8 March 1871 at about 7 p.m., on the Compagnie de l\'Ouest network, a train repatriating sick or wounded Prussian soldiers to Germany stopped just before the entrance to Puteaux station (Seine).',
                'de' => 'Am 8. März 1871 gegen 19 Uhr hielt im Netz der Compagnie de l\'Ouest ein Zug, der kranke oder verwundete preußische Soldaten nach Deutschland zurückbrachte, kurz vor der Einfahrt in den Bahnhof Puteaux (Seine).',
                'sources' => ['wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un train de marchandises qui le suivait l\'a percuté et a écrasé dix-neuf de ses trente-deux wagons.',
                'en' => 'A goods train following it ran into it and crushed nineteen of its thirty-two wagons.',
                'de' => 'Ein nachfolgender Güterzug fuhr auf ihn auf und zerdrückte neunzehn seiner zweiunddreißig Wagen.',
                'sources' => ['wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La collision a fait de nombreuses victimes. Des bilans d\'environ vingt morts et de plus de cent blessés ont été avancés par la suite ; aucun bilan officiel n\'a été trouvé.',
                'en' => 'The collision caused many casualties. Figures of about twenty dead and more than a hundred injured were put forward afterwards; no official toll has been found.',
                'de' => 'Der Zusammenstoß forderte viele Opfer. Später wurden Zahlen von etwa zwanzig Toten und mehr als hundert Verletzten genannt; eine amtliche Bilanz wurde nicht gefunden.',
                'sources' => ['wp-liste-1870'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Dans le contexte de la guerre, l\'accident n\'a donné lieu qu\'à un bref communiqué administratif, selon lequel « il y a eu à regretter de graves accidents ».',
                'en' => 'In the wartime context, the accident gave rise only to a brief administrative statement, according to which there had been "graves accidents" to regret.',
                'de' => 'Im Kontext des Krieges gab es zu dem Unfall nur eine kurze Verwaltungsmitteilung, wonach «es schwere Unfälle zu bedauern gab» («il y a eu à regretter de graves accidents»).',
                'sources' => ['wp-liste-1870'],
            ],
        ],
    ],
];
