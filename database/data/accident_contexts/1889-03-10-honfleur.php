<?php

// Contexte sourcé du déraillement du train-poste peu avant Honfleur, ligne de Pont-l'Évêque à Honfleur, de mars 1889 (recherche du 21/11/2026).
return [
    'slug' => '1889-03-10-honfleur',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1889',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1889',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 10 mars 1889, d\'après Le Matin du 11 mars : sur la ligne de Pont-l\'Évêque à Honfleur, peu avant Honfleur, vers 14 h, un train-poste déraille ; le mécanicien et le chauffeur sont tués, le chef de train et le conducteur blessés, les voyageurs indemnes.',
        ],
        'delpher-1889-03-13-haagsche' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengd nieuws',
            'publisher' => 'Haagsche courant (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1889-03-13',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB04:000118354:mpeg21:a0012',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève (même texte dans Recht voor allen du 13 mars) : le train-poste qui devait arriver à Honfleur dimanche à 14 h 15 a déraillé à environ deux milles de la gare ; le train a été entièrement renversé, le mécanicien et le chauffeur tués sur le coup, un conducteur grièvement blessé, plusieurs voyageurs légèrement.',
        ],
        'delpher-1889-03-13-nieuws' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengd Nieuws',
            'publisher' => 'Het nieuws van den dag (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1889-03-13',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010137580:mpeg21:a0075',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : dimanche, un train a déraillé près de Honfleur ; le mécanicien et le chauffeur tués ; le reste du personnel et les voyageurs indemnes, ces derniers ont gagné à pied la gare voisine.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le dimanche 10 mars 1889, le train-poste qui devait arriver à Honfleur à 14 h 15 a déraillé sur la ligne de Pont-l\'Évêque à Honfleur, peu avant l\'arrivée. Le train a été renversé.',
                'en' => 'On Sunday 10 March 1889 the mail train due at Honfleur at 2.15 pm derailed on the Pont-l\'Évêque to Honfleur line shortly before arriving. The train overturned.',
                'de' => 'Am Sonntag, dem 10. März 1889, entgleiste der um 14.15 Uhr in Honfleur erwartete Postzug an der Strecke Pont-l\'Évêque-Honfleur kurz vor der Ankunft. Der Zug stürzte um.',
                'sources' => ['wp-liste-1880', 'delpher-1889-03-13-haagsche'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués sur le coup. Le chef de train et le conducteur ont été blessés selon la liste de Wikipédia ; les voyageurs ont gagné à pied la gare voisine.',
                'en' => 'The driver and the stoker were killed outright. The guard and the conductor were injured according to the Wikipedia list; the passengers walked to the nearby station.',
                'de' => 'Lokführer und Heizer waren sofort tot. Zugführer und Schaffner wurden nach der Wikipedia-Liste verletzt; die Reisenden gingen zu Fuß zum nahen Bahnhof.',
                'sources' => ['wp-liste-1880', 'delpher-1889-03-13-haagsche', 'delpher-1889-03-13-nieuws'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les sources divergent sur les blessés : chef de train et conducteur, voyageurs indemnes (liste de Wikipédia) ; un conducteur grièvement blessé et plusieurs voyageurs légèrement (Haagsche courant) ; personnel et voyageurs indemnes (Het nieuws van den dag).',
                'en' => 'The sources differ on the injured: guard and conductor, passengers unhurt (Wikipedia list); one conductor seriously and several passengers slightly injured (Haagsche courant); crew and passengers unhurt (Het nieuws van den dag).',
                'de' => 'Die Quellen weichen bei den Verletzten ab: Zugführer und Schaffner, Reisende unverletzt (Wikipedia-Liste); ein Schaffner schwer und mehrere Reisende leicht verletzt (Haagsche courant); Personal und Reisende unverletzt (Het nieuws van den dag).',
                'sources' => ['wp-liste-1880', 'delpher-1889-03-13-haagsche', 'delpher-1889-03-13-nieuws'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1880', 'delpher-1889-03-13-haagsche', 'delpher-1889-03-13-nieuws'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Matin du 11 mars 1889) et la presse néerlandaise du 13 mars ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Matin of 11 March 1889) and the Dutch press of 13 March; the record gave no number of deaths.',
            'de' => 'Lokführer und Heizer getötet nach der Wikipedia-Liste (Le Matin vom 11. März 1889) und der niederländischen Presse vom 13. März; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
