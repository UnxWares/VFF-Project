<?php

// Contexte sourcé de la collision de deux trains de ceinture à la gare de Paris-Nord, de novembre 1893 (recherche du 21/11/2026).
return [
    'slug' => '1893-11-01-paris',
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
            'locator' => '1893',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1893',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 1er novembre 1893, d\'après Le Figaro du 2 novembre : à 19 h, à la gare de Paris-Nord, un train de ceinture entrant en gare percute un autre train qui attend le départ ; un voyageur est tué, deux autres sont grièvement blessés.',
        ],
        'delpher-1893-11-03-telegraaf' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'FRANKRIJK.',
            'publisher' => 'De Telegraaf (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1893-11-03',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:110538111:mpeg21:a0004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Nouvelles de France : hier soir, collision de deux trains à la gare du Nord à Paris ; un enfant de dix ans tué, deux autres voyageurs grièvement blessés.',
        ],
        'delpher-1893-11-07-noordbrabantsche' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenland. FRANKRIJK.',
            'publisher' => 'Provinciale Noordbrabantsche en \'s Hertogenbossche courant (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1893-11-07',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMSADB01:000019193:mpeg21:a0006',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle, datée « jeudi soir » : collision de deux trains à la gare du Nord à Paris, un enfant de dix ans tué, deux autres voyageurs grièvement blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Vers 19 h, un train de ceinture qui arrivait à la gare de Paris-Nord a heurté un autre train qui attendait le départ.',
                'en' => 'At about 7 pm, at Paris-Nord station, a Ceinture train entering the station ran into another train waiting to leave.',
                'de' => 'Gegen 19 Uhr fuhr im Pariser Nordbahnhof ein einfahrender Zug der Ceinture auf einen anderen Zug auf, der zur Abfahrt bereitstand.',
                'sources' => ['wp-liste-1890', 'delpher-1893-11-03-telegraaf'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur a été tué et deux autres ont été grièvement blessés. Les deux journaux néerlandais précisent que le mort est un enfant de dix ans.',
                'en' => 'One passenger was killed and two others were seriously injured. Both Dutch newspapers state that the dead passenger was a ten-year-old child.',
                'de' => 'Ein Reisender wurde getötet, zwei weitere wurden schwer verletzt. Beide niederländischen Zeitungen geben an, dass der Tote ein zehnjähriges Kind war.',
                'sources' => ['wp-liste-1890', 'delpher-1893-11-03-telegraaf', 'delpher-1893-11-07-noordbrabantsche'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia date la collision du mercredi 1er novembre, d\'après Le Figaro du 2. De Telegraaf du 3 novembre la place « hier soir » et le journal de Bois-le-Duc du 7 « jeudi soir », ce qui donnerait le 2 novembre.',
                'en' => 'The Wikipedia list dates the collision Wednesday 1 November, from Le Figaro of the 2nd. De Telegraaf of 3 November places it "last night" and the \'s-Hertogenbosch paper of the 7th "on Thursday evening", which would mean 2 November.',
                'de' => 'Die Wikipedia-Liste datiert den Zusammenstoß nach Le Figaro vom 2. November auf Mittwoch, den 1. November. De Telegraaf vom 3. November setzt ihn auf „gestern Abend“, die Zeitung aus ’s-Hertogenbosch vom 7. auf „Donnerstagabend“, was den 2. November ergäbe.',
                'sources' => ['wp-liste-1890', 'delpher-1893-11-03-telegraaf', 'delpher-1893-11-07-noordbrabantsche'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'delpher-1893-11-03-telegraaf', 'delpher-1893-11-07-noordbrabantsche'],
            'fr' => 'Un voyageur tué selon la liste de Wikipédia (Le Figaro du 2 novembre 1893) et deux journaux néerlandais ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One passenger killed according to the Wikipedia list (Le Figaro of 2 November 1893) and two Dutch newspapers; the record gave no number of deaths.',
            'de' => 'Ein Reisender getötet nach der Wikipedia-Liste (Le Figaro vom 2. November 1893) und zwei niederländischen Zeitungen; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'delpher-1893-11-03-telegraaf', 'delpher-1893-11-07-noordbrabantsche'],
            'fr' => 'Deux voyageurs grièvement blessés selon la liste de Wikipédia et les deux journaux néerlandais ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Two passengers seriously injured according to the Wikipedia list and both Dutch newspapers; the record gave no number of injured.',
            'de' => 'Zwei Reisende schwer verletzt nach der Wikipedia-Liste und beiden niederländischen Zeitungen; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
