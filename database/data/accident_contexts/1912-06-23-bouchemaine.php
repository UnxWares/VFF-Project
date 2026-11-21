<?php

// Contexte sourcé de l'explosion de la locomotive d'un train de marchandises près de la gare de La Pointe, ligne d'Angers à Nantes, de juin 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-06-23-bouchemaine',
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
            'locator' => 'Juin',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Juin',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 23 juin 1912 : à quatorze kilomètres d\'Angers, sur la ligne d\'Angers à Nantes, près de la gare de La Pointe, un train de marchandises allant à Nantes déraille en soirée après l\'explosion de sa locomotive, dont le mécanicien et le chauffeur sont tués.',
        ],
        'ddb-1912-06-25-witten' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Wie aus Angers gemeldet wird',
            'publisher' => 'Wittener Volks-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1912-06-25',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/OBUFBQTAWS5VEHW4VLZRNO4QFSCRSGPO?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du 24 juin : selon une nouvelle d\'Angers, la locomotive d\'un train de marchandises a explosé la veille au soir près de Forges ; le mécanicien et le chauffeur ont été tués.',
        ],
        'ddb-1912-06-27-bensberg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 24. Juni. Eisenbahnunfall',
            'publisher' => 'Bensberger Volkszeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1912-06-27',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/3GUI2QLMRW34ZXZXOWXQ6S6YCWMTXRYG?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 24 juin : sur la machine d\'un train venant de Nantes, la chaudière a explosé pour une cause encore inconnue non loin de la gare d\'Angers ; le chauffeur et le mécanicien ont été tués.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le soir du 23 juin 1912, la locomotive d\'un train de marchandises a explosé près de la gare de La Pointe, à quatorze kilomètres d\'Angers, sur la ligne d\'Angers à Nantes, et le train a déraillé.',
                'en' => 'On the evening of 23 June 1912 the engine of a goods train exploded near La Pointe station, fourteen kilometres from Angers on the Angers to Nantes line, and the train derailed.',
                'de' => 'Am Abend des 23. Juni 1912 explodierte nahe dem Bahnhof La Pointe, vierzehn Kilometer von Angers an der Strecke Angers-Nantes, die Lokomotive eines Güterzugs; der Zug entgleiste.',
                'sources' => ['wp-liste-1912', 'ddb-1912-06-25-witten'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués.',
                'en' => 'The driver and the stoker were killed.',
                'de' => 'Lokomotivführer und Heizer wurden getötet.',
                'sources' => ['wp-liste-1912', 'ddb-1912-06-25-witten', 'ddb-1912-06-27-bensberg'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le train allait à Nantes ; selon la dépêche de Paris du 24 juin, il venait de Nantes. Les dépêches placent l\'explosion près de Forges ou non loin de la gare d\'Angers.',
                'en' => 'According to the Wikipedia list the train was bound for Nantes; according to the Paris dispatch of 24 June it came from Nantes. The dispatches place the explosion near Forges or not far from Angers station.',
                'de' => 'Nach der Wikipedia-Liste fuhr der Zug nach Nantes, nach der Pariser Meldung vom 24. Juni kam er aus Nantes. Die Meldungen verorten die Explosion bei Forges oder unweit des Bahnhofs Angers.',
                'sources' => ['wp-liste-1912', 'ddb-1912-06-25-witten', 'ddb-1912-06-27-bensberg'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1912', 'ddb-1912-06-25-witten'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list and the German press; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
