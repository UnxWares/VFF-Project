<?php

// Contexte sourcé du déraillement d'un train Le Havre - Amiens entre Clères et Bosc-le-Hard, de juillet 1899 (recherche du 21/11/2026).
return [
    'slug' => '1899-07-06-bosc-le-hard',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1899',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1899',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 6 juillet 1899 : sur la ligne de Clères à Montérolier-Buchy, entre Clères et Bosc-le-Hard, un train de voyageurs Le Havre - Amiens déraille à 8 h 20 ; la machine et le fourgon à bagages se couchent sur le remblai ; le mécanicien est tué, le chauffeur et le chef de train blessés ; les quatre voitures restent sur la voie.',
        ],
        'ddb-1899-07-13-witten' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Der Schnellzug von Le Havre nach Amiens',
            'publisher' => 'Wittener Volks-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1899-07-13',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/SAXE4LFUMKQCNXBZVWMTNRMMX2HOQHNG?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : l\'express du Havre à Amiens a déraillé le jeudi près de la station de Clères ; la machine s\'est renversée ; le mécanicien a été tué, le chauffeur et le chef de train grièvement blessés ; aucun voyageur n\'a été atteint.',
        ],
        'ddb-1899-07-13-wilhelmsburg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Amiens',
            'publisher' => 'Wilhelmsburger Gemeindezeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1899-07-13',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/Y7N37FEQ4CGHTVDJQF66YZCJORMLWQGQ?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle d\'Amiens : express Le Havre - Amiens déraillé le jeudi près de la station de Clères, machine renversée, mécanicien tué, chauffeur et chef de train grièvement blessés, voyageurs indemnes.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le jeudi 6 juillet 1899, vers 8 h 20, un train de voyageurs du Havre à Amiens a déraillé entre Clères et Bosc-le-Hard, sur la ligne de Clères à Montérolier-Buchy. La machine et le fourgon à bagages se sont couchés sur le remblai ; les quatre voitures sont restées sur la voie.',
                'en' => 'On Thursday 6 July 1899, at about 8.20 am, a Le Havre to Amiens passenger train derailed between Clères and Bosc-le-Hard on the Clères to Montérolier-Buchy line. The engine and the luggage van fell on their side on the embankment; the four coaches stayed on the track.',
                'de' => 'Am Donnerstag, dem 6. Juli 1899, gegen 8.20 Uhr entgleiste zwischen Clères und Bosc-le-Hard an der Strecke Clères-Montérolier-Buchy ein Personenzug Le Havre-Amiens. Lokomotive und Gepäckwagen legten sich auf den Damm, die vier Personenwagen blieben im Gleis.',
                'sources' => ['wp-liste-1890', 'ddb-1899-07-13-witten'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été tué, le chauffeur et le chef de train blessés. Aucun voyageur n\'a été atteint.',
                'en' => 'The driver was killed and the stoker and the guard injured. No passenger was hurt.',
                'de' => 'Der Lokomotivführer wurde getötet, Heizer und Zugführer wurden verletzt. Kein Reisender kam zu Schaden.',
                'sources' => ['wp-liste-1890', 'ddb-1899-07-13-witten', 'ddb-1899-07-13-wilhelmsburg'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1899-07-13-witten'],
            'fr' => 'Mécanicien tué selon la liste de Wikipédia et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver killed according to the Wikipedia list and the German press; the record gave no number of deaths.',
            'de' => 'Lokomotivführer getötet nach der Wikipedia-Liste und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
