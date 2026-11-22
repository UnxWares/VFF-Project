<?php

// Contexte sourcé du déraillement d'un train entre Ychoux et Labouheyre, ligne de Bordeaux à Irun, de mars 1909 (recherche du 21/11/2026).
return [
    'slug' => '1909-03-31-ychoux',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1909' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1909',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Mars',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1909&oldid=241222447#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 31 mars 1909, d\'après Le Figaro du 1er avril : sur la ligne Bordeaux - Bayonne, entre les gares d\'Ychoux et de Labouheyre, un train de marchandises déraille ; le chef de train est tué.',
        ],
        'arnhemsche-courant-1909-04-02' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Arnhemsche courant du 2 avril 1909',
            'publisher' => 'Arnhemsche courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1909-04-02',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB08:000098144:mpeg21:a0010',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Brève : sur la ligne de Bordeaux à Bayonne, un train a déraillé entre Ychoux et Labouheyre ; le mécanicien et un voyageur auraient été tués et plusieurs personnes blessées ; quelques voitures endommagées.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 31 mars 1909, un train a déraillé entre les gares d\'Ychoux et de Labouheyre, sur la ligne de Bordeaux à Bayonne.',
                'en' => 'On 31 March 1909, a train derailed between Ychoux and Labouheyre stations, on the Bordeaux to Bayonne line.',
                'de' => 'Am 31. März 1909 entgleiste zwischen den Bahnhöfen Ychoux und Labouheyre an der Strecke Bordeaux-Bayonne ein Zug.',
                'sources' => ['wp-liste-1909', 'arnhemsche-courant-1909-04-02'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Selon la liste de Wikipédia, c\'était un train de marchandises et seul le chef de train a été tué. La brève de l\'Arnhemsche courant parle de voitures endommagées, de deux morts probables, le mécanicien et un voyageur, et de plusieurs blessés.',
                'en' => 'According to the Wikipedia list, it was a goods train and only the guard was killed. The Arnhemsche courant item speaks of damaged carriages, of two reported dead, the driver and a passenger, and of several injured.',
                'de' => 'Nach der Wikipedia-Liste war es ein Güterzug, und nur der Zugführer wurde getötet. Die Kurzmeldung der Arnhemsche courant spricht von beschädigten Wagen, von zwei mutmaßlichen Toten, dem Lokomotivführer und einem Reisenden, und von mehreren Verletzten.',
                'sources' => ['wp-liste-1909', 'arnhemsche-courant-1909-04-02'],
            ],
        ],
    ],
];
