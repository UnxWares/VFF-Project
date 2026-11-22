<?php

// Contexte sourcé de la collision du rapide Toulouse - Paris avec un train de marchandises en gare de Dieupentale, d'octobre 1905 (recherche du 21/11/2026).
return [
    'slug' => '1905-10-03-dieupentale',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1905' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1905',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1905&oldid=233590727#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 3 octobre 1905, d\'après Le Temps du 5 octobre : au petit matin, à Dieupentale, près de Montauban, sur la ligne Paris - Toulouse, le rapide Toulouse - Paris prend en écharpe la locomotive d\'un train de marchandises qui quitte sa voie de garage et tue son mécanicien ; deux cheminots du rapide et un agent des Postes sont blessés, les voyageurs indemnes.',
        ],
        'algemeen-handelsblad-1905-10-05' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenlandsche Kroniek',
            'publisher' => 'Algemeen Handelsblad',
            'author' => null,
            'published' => '1905-10-05',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010649680:mpeg21:a0075',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Dans la nuit de mardi, un rapide de Toulouse à Paris a heurté un train de marchandises en gare de Dieupentale ; de nombreux blessés, dont beaucoup en danger de mort ; circulation interrompue.',
        ],
        'noord-brabanter-1905-10-06' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk',
            'publisher' => 'De Noord Brabanter',
            'author' => null,
            'published' => '1905-10-06',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMUBTB04:210836084:mpeg21:a00003',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Le rapide Toulouse - Paris a heurté un train de marchandises à Dieupentale ; il y aurait beaucoup de blessés, dont quelques-uns grièvement ; un train de secours avec des médecins est parti de Toulouse.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Au petit matin du 3 octobre 1905, en gare de Dieupentale, près de Montauban, sur la ligne de Paris à Toulouse, le rapide Toulouse - Paris a pris en écharpe la locomotive d\'un train de marchandises qui sortait de sa voie de garage. La circulation a été interrompue et un train de secours avec des médecins est parti de Toulouse.',
                'en' => 'Early on 3 October 1905, at Dieupentale station near Montauban on the Paris to Toulouse line, the Toulouse to Paris express struck the engine of a goods train that was pulling out of its siding. Traffic was halted and a relief train with doctors left Toulouse.',
                'de' => 'Am frühen Morgen des 3. Oktober 1905 rammte im Bahnhof Dieupentale bei Montauban an der Strecke Paris-Toulouse der Schnellzug Toulouse-Paris die Lokomotive eines Güterzugs, der aus seinem Abstellgleis ausfuhr. Der Verkehr wurde unterbrochen, aus Toulouse fuhr ein Hilfszug mit Ärzten ab.',
                'sources' => ['wp-liste-1905', 'algemeen-handelsblad-1905-10-05', 'noord-brabanter-1905-10-06'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le mécanicien du train de marchandises a été tué, deux cheminots du rapide et un agent des Postes ont été blessés, et aucun voyageur n\'a été atteint.',
                'en' => 'According to the Wikipedia list, the driver of the goods train was killed, two railwaymen on the express and a postal worker were injured, and no passenger was hurt.',
                'de' => 'Nach der Wikipedia-Liste wurde der Lokomotivführer des Güterzugs getötet, zwei Eisenbahner des Schnellzugs und ein Postbeamter wurden verletzt, kein Reisender kam zu Schaden.',
                'sources' => ['wp-liste-1905'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les premières dépêches parues aux Pays-Bas ne parlent d\'aucun mort mais de nombreux blessés, dont certains ou beaucoup en danger de mort, là où la liste de Wikipédia compte un mort, trois blessés et des voyageurs indemnes.',
                'en' => 'The first dispatches printed in the Netherlands mention no deaths but many injured, some or many of them in danger of their lives, where the Wikipedia list counts one dead, three injured and no passenger hurt.',
                'de' => 'Die ersten in den Niederlanden gedruckten Meldungen nennen keine Toten, aber viele Verletzte, einige oder viele davon in Lebensgefahr; die Wikipedia-Liste zählt einen Toten, drei Verletzte und unverletzte Reisende.',
                'sources' => ['algemeen-handelsblad-1905-10-05', 'noord-brabanter-1905-10-06', 'wp-liste-1905'],
            ],
        ],
    ],
];
