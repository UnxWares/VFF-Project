<?php

// Contexte sourcé de la collision de deux trains de marchandises en gare de Marles-en-Brie, ligne de Paris à Coulommiers, de décembre 1894 (recherche du 21/11/2026).
return [
    'slug' => '1894-12-05-marles-en-brie',
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
            'locator' => '1894',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1894',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 5 décembre 1894, d\'après Le Figaro du 6 décembre : vers 23 h 30, en gare de Marles-en-Brie, sur la ligne de Paris à Coulommiers, deux trains de marchandises se télescopent ; le mécanicien du train tamponneur et deux serre-freins du train tamponné sont tués.',
        ],
        'ddb-1894-12-06-reichsanzeiger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 5. Dezember',
            'publisher' => 'Deutscher Reichsanzeiger und Preußischer Staatsanzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1894-12-06',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/GHOZ7OQO7VF6BLQYUEIP5AOAVST7KCDB?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 5 décembre, d\'après l\'agence Wolff : la nuit dernière, deux trains de marchandises se sont heurtés près de Coulommiers ; trois agents tués.',
        ],
        'ddb-1894-12-07-halle' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 5. December. (Eisenbahnunglück.)',
            'publisher' => 'General-Anzeiger für Halle und den Saalkreis (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1894-12-07',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/JYY6EHUBFDRSGUQBHL22FK3DOKQL52U5?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche de Paris du 5 décembre : la nuit dernière, collision de deux trains de marchandises près de Coulommiers, trois agents tués.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Vers 23 h 30, en gare de Marles-en-Brie, sur la ligne de Paris à Coulommiers, un train de marchandises a tamponné un autre train de marchandises.',
                'en' => 'At about 11.30 pm, at Marles-en-Brie station on the Paris to Coulommiers line, a goods train ran into another goods train.',
                'de' => 'Gegen 23.30 Uhr fuhr im Bahnhof Marles-en-Brie an der Strecke Paris-Coulommiers ein Güterzug auf einen anderen Güterzug auf.',
                'sources' => ['wp-liste-1890', 'ddb-1894-12-06-reichsanzeiger'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois agents ont été tués : le mécanicien du train tamponneur et deux serre-freins du train tamponné selon la liste de Wikipédia.',
                'en' => 'Three railwaymen were killed: the driver of the colliding train and two brakemen of the train it struck, according to the Wikipedia list.',
                'de' => 'Drei Bedienstete wurden getötet: nach der Wikipedia-Liste der Lokführer des auffahrenden Zuges und zwei Bremser des getroffenen Zuges.',
                'sources' => ['wp-liste-1890', 'ddb-1894-12-06-reichsanzeiger', 'ddb-1894-12-07-halle'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La dépêche de Paris datée du 5 décembre place la collision « la nuit dernière », soit dans la nuit du 4 au 5 décembre ; la liste de Wikipédia la date du 5 décembre vers 23 h 30.',
                'en' => 'The Paris dispatch dated 5 December places the collision "last night", that is the night of 4 to 5 December; the Wikipedia list dates it 5 December at about 11.30 pm.',
                'de' => 'Die Pariser Depesche vom 5. Dezember setzt den Zusammenstoß auf „vergangene Nacht“, also die Nacht vom 4. auf den 5. Dezember; die Wikipedia-Liste datiert ihn auf den 5. Dezember gegen 23.30 Uhr.',
                'sources' => ['ddb-1894-12-06-reichsanzeiger', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 3,
            'sources' => ['wp-liste-1890', 'ddb-1894-12-06-reichsanzeiger', 'ddb-1894-12-07-halle'],
            'fr' => 'Trois agents tués selon la liste de Wikipédia (Le Figaro du 6 décembre 1894) et la dépêche de Paris du 5 décembre ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Three railwaymen killed according to the Wikipedia list (Le Figaro of 6 December 1894) and the Paris dispatch of 5 December; the record gave no number of deaths.',
            'de' => 'Drei Bedienstete getötet nach der Wikipedia-Liste (Le Figaro vom 6. Dezember 1894) und der Pariser Depesche vom 5. Dezember; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
