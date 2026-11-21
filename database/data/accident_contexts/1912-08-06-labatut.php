<?php

// Contexte sourcé de la collision frontale en gare de Labatut, en août 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-08-06-labatut',
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
            'locator' => '6 août 1912, Labatut',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Place la collision vers 20 h en gare de Labatut (Landes), entre l\'express Toulouse-Bayonne et l\'omnibus Bayonne-Pau ; 2 morts et 13 blessés ; cite Le Figaro du 8 août 1912 et Le Temps du 12 août 1912.',
        ],
        'de-tijd-1912-08-08' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De Tijd du 8 août 1912',
            'publisher' => 'De Tijd',
            'author' => null,
            'published' => '1912-08-08',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010549039:mpeg21:a0070',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Dépêche Reuter d\'Orthez datée du 7 août.',
        ],
        'figaro-1912-08-08' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 8 août 1912, page 2',
            'publisher' => 'Le Figaro (numérisé sur Gallica)',
            'author' => null,
            'published' => '1912-08-08',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k289668r',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Selon une dépêche Reuter d\'Orthez publiée par De Tijd, l\'express de Bayonne est entré en collision avec un autre train à Labatut. La liste de Wikipédia précise que c\'est en gare de Labatut (Landes), vers 20 h, sur la ligne à voie unique de Puyoo à Bayonne, entre l\'express Toulouse-Bayonne et l\'omnibus Bayonne-Pau, en retard et qui n\'avait pu être garé à temps.',
                'en' => 'According to a Reuters dispatch from Orthez published by De Tijd, the Bayonne express collided with another train at Labatut. The Wikipedia list specifies that it was at Labatut station (Landes), at about 8 pm, on the single-track Puyoo to Bayonne line, between the Toulouse to Bayonne express and the Bayonne to Pau stopping train, which was late and could not be put into a siding in time.',
                'de' => 'Laut einer Reuter-Depesche aus Orthez in De Tijd stieß der Schnellzug aus Bayonne in Labatut mit einem anderen Zug zusammen. Die Wikipedia-Liste präzisiert, es sei im Bahnhof Labatut (Landes) gegen 20 Uhr auf der eingleisigen Strecke von Puyoo nach Bayonne gewesen, zwischen dem Schnellzug Toulouse-Bayonne und dem verspäteten Personenzug Bayonne-Pau, der nicht rechtzeitig hatte abgestellt werden können.',
                'sources' => ['de-tijd-1912-08-08', 'wp-liste-1912'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La dépêche Reuter compte deux morts et quatre blessés ; la liste de Wikipédia donne deux morts et treize blessés.',
                'en' => 'The Reuters dispatch counts two dead and four injured; the Wikipedia list gives two dead and thirteen injured.',
                'de' => 'Die Reuter-Depesche zählt zwei Tote und vier Verletzte; die Wikipedia-Liste nennt zwei Tote und dreizehn Verletzte.',
                'sources' => ['de-tijd-1912-08-08', 'wp-liste-1912'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le mécanicien de l\'express a été emprisonné pour avoir freiné trop tardivement à l\'approche de la gare.',
                'en' => 'According to the Wikipedia list, the express driver was imprisoned for braking too late on the approach to the station.',
                'de' => 'Laut der Wikipedia-Liste wurde der Lokführer des Schnellzugs inhaftiert, weil er bei der Einfahrt in den Bahnhof zu spät gebremst hatte.',
                'sources' => ['wp-liste-1912'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La dépêche, datée du 7 août, parle d\'un express de Bayonne, la liste d\'un express venant de Toulouse et d\'un omnibus de Bayonne ; le nombre de blessés diffère (quatre ou treize), sans doute parce que la dépêche est un premier bilan.',
                'en' => 'The dispatch, dated 7 August, speaks of an express from Bayonne, the list of an express from Toulouse and a stopping train from Bayonne; the number of injured differs (four or thirteen), probably because the dispatch is a first toll.',
                'de' => 'Die Depesche vom 7. August spricht von einem Schnellzug aus Bayonne, die Liste von einem Schnellzug aus Toulouse und einem Personenzug aus Bayonne; die Zahl der Verletzten weicht ab (vier oder dreizehn), vermutlich weil die Depesche eine erste Bilanz ist.',
                'sources' => ['de-tijd-1912-08-08', 'wp-liste-1912'],
            ],
        ],
    ],
    'corrections' => [],
];
