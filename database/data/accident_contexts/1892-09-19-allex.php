<?php

// Contexte sourcé du tamponnement d'un train de voyageurs en gare d'Allex-Grane, ligne de Livron à Aspres-sur-Buëch, de septembre 1892 (recherche du 21/11/2026).
return [
    'slug' => '1892-09-19-allex',
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
            'locator' => '1892',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1892',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 19 septembre 1892, d\'après Le Temps du 21 et Le Figaro du 22 septembre : sur la ligne de Livron à Aspres-sur-Buëch, à midi, un train de ballast heurte un train de voyageurs à l\'arrêt en gare d\'Allex-Grane et projette ses trois dernières voitures sur le quai ; un mort et 32 blessés, dont un mourra quelques jours plus tard.',
        ],
        'ddb-1892-09-20-hannover' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 19. Sept.',
            'publisher' => 'Hannoverscher Kurier (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1892-09-20',
            'locator' => 'p. 7',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/XFNKUPL2BA4STAUVOLEAXK4GW7QJH3RC?issuepage=7',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Première dépêche de Paris du 19 septembre, d\'après des nouvelles de Valence : cet après-midi, en gare d\'Allex, un train de marchandises a heurté un train de voyageurs allant à Livron ; une vingtaine de morts, de nombreux blessés.',
        ],
        'ddb-1892-09-20-staatsbuerger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris',
            'publisher' => 'Staatsbürger-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1892-09-20',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/3KQYYG7FDP234DAS3RYKLIE2OFPW5DM6?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche rectificative, d\'après une nouvelle plus tardive de Valence : lors de la collision en gare d\'Allex, une personne tuée, deux grièvement et une trentaine légèrement blessées.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 19 septembre 1892, vers midi, un train de ballast a heurté un train de voyageurs arrêté en gare d\'Allex-Grane, sur la ligne de Livron à Aspres-sur-Buëch. Les trois dernières voitures du train de voyageurs ont été projetées sur le quai.',
                'en' => 'On 19 September 1892, at about noon, a ballast train ran into a passenger train standing at Allex-Grane station on the Livron to Aspres-sur-Buëch line. The last three coaches of the passenger train were thrown onto the platform.',
                'de' => 'Am 19. September 1892 gegen Mittag fuhr ein Schotterzug im Bahnhof Allex-Grane an der Strecke Livron-Aspres-sur-Buëch auf einen haltenden Personenzug auf. Die drei letzten Wagen des Personenzugs wurden auf den Bahnsteig geschleudert.',
                'sources' => ['wp-liste-1890', 'ddb-1892-09-20-staatsbuerger'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une personne a été tuée et une trentaine blessées, deux grièvement selon la dépêche de Valence. Selon la liste de Wikipédia, l\'un des 32 blessés est mort quelques jours plus tard.',
                'en' => 'One person was killed and about thirty injured, two seriously according to the Valence dispatch. According to the Wikipedia list one of the 32 injured died a few days later.',
                'de' => 'Eine Person wurde getötet und etwa dreißig verletzt, nach der Meldung aus Valence zwei schwer. Nach der Wikipedia-Liste starb einer der 32 Verletzten einige Tage später.',
                'sources' => ['ddb-1892-09-20-staatsbuerger', 'wp-liste-1890'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La première dépêche de Paris parlait d\'une vingtaine de morts ; une nouvelle plus tardive de Valence a ramené le bilan à un mort. La presse allemande parle d\'un train de marchandises, la liste de Wikipédia d\'un train de ballast.',
                'en' => 'The first Paris dispatch spoke of about twenty dead; a later report from Valence brought the toll down to one. The German press speaks of a goods train, the Wikipedia list of a ballast train.',
                'de' => 'Die erste Pariser Depesche sprach von etwa zwanzig Toten; eine spätere Meldung aus Valence nannte einen Toten. Die deutsche Presse spricht von einem Güterzug, die Wikipedia-Liste von einem Schotterzug.',
                'sources' => ['ddb-1892-09-20-hannover', 'ddb-1892-09-20-staatsbuerger', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'ddb-1892-09-20-staatsbuerger'],
            'fr' => 'Un mort sur le coup selon la dépêche rectificative de Valence et la liste de Wikipédia, et un blessé mort quelques jours plus tard selon la liste (Le Figaro du 22 septembre 1892) ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One killed outright according to the corrected Valence dispatch and the Wikipedia list, and one injured person who died a few days later according to the list (Le Figaro of 22 September 1892); the record gave no number of deaths.',
            'de' => 'Ein Toter nach der berichtigten Meldung aus Valence und der Wikipedia-Liste, dazu ein Verletzter, der nach der Liste einige Tage später starb (Le Figaro vom 22. September 1892); der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
