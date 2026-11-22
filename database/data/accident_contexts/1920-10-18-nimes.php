<?php

// Contexte sourcé de la collision à la bifurcation de Grézan, près de Nîmes, le 18 octobre 1920 (recherche du 21/11/2026).
return [
    'slug' => '1920-10-18-nimes',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1920' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1920',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '18 octobre 1920, Nîmes',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1920&oldid=236476563#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Collision de nuit à la bifurcation de Grézan entre un train de voyageurs pour Remoulins et un train de marchandises pour Miramas, sept voitures déraillées, un mort et huit blessés.',
        ],
        'telegraaf-1920-10-19' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De Telegraaf du 19 octobre 1920',
            'publisher' => 'De Telegraaf (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1920-10-19',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:110551142:mpeg21:a0044',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Dépêche de Paris du 18 octobre.',
        ],
        'overijsselsch-1920-10-19' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Overijsselsch dagblad du 19 octobre 1920',
            'publisher' => 'Overijsselsch dagblad (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1920-10-19',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB23:001294096:mpeg21:a00007',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Même dépêche.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Selon une dépêche de Paris du 18 octobre 1920, entre Avignon et Nîmes, un train de voyageurs a heurté un train de marchandises, et sept wagons ont déraillé. La liste de Wikipédia précise que c\'est de nuit, à la sortie de Nîmes, à la bifurcation de Grézan, entre un train de voyageurs pour Remoulins et un train de marchandises pour Miramas.',
                'en' => 'According to a dispatch from Paris of 18 October 1920, between Avignon and Nîmes, a passenger train ran into a goods train and seven wagons derailed. The Wikipedia list specifies that it was at night, at the exit from Nîmes, at the Grézan junction, between a passenger train for Remoulins and a goods train for Miramas.',
                'de' => 'Laut einer Depesche aus Paris vom 18. Oktober 1920 fuhr zwischen Avignon und Nîmes ein Reisezug auf einen Güterzug auf, und sieben Wagen entgleisten. Die Wikipedia-Liste präzisiert, es sei nachts am Ortsausgang von Nîmes an der Abzweigung Grézan zwischen einem Reisezug nach Remoulins und einem Güterzug nach Miramas geschehen.',
                'sources' => ['telegraaf-1920-10-19', 'overijsselsch-1920-10-19', 'wp-liste-1920'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une personne a été tuée et huit blessées, dont une grièvement, selon les deux journaux néerlandais ; la liste de Wikipédia donne un mort, un conducteur du train tamponneur, et huit blessés.',
                'en' => 'One person was killed and eight injured, one seriously, according to the two Dutch newspapers; the Wikipedia list gives one dead, a guard of the colliding train, and eight injured.',
                'de' => 'Eine Person wurde getötet und acht verletzt, eine davon schwer, laut den beiden niederländischen Zeitungen; die Wikipedia-Liste nennt einen Toten, einen Zugbegleiter des auffahrenden Zuges, und acht Verletzte.',
                'sources' => ['telegraaf-1920-10-19', 'overijsselsch-1920-10-19', 'wp-liste-1920'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La dépêche situe l\'accident entre Avignon et Nîmes, alors que la liste de Wikipédia le place à la sortie de Nîmes, sur la ligne de Nîmes à Avignon.',
                'en' => 'The dispatch places the accident between Avignon and Nîmes, whereas the Wikipedia list places it at the exit from Nîmes, on the Nîmes to Avignon line.',
                'de' => 'Die Depesche verortet das Unglück zwischen Avignon und Nîmes, während die Wikipedia-Liste es am Ortsausgang von Nîmes auf der Strecke Nîmes-Avignon ansiedelt.',
                'sources' => ['telegraaf-1920-10-19', 'wp-liste-1920'],
            ],
        ],
    ],
    'corrections' => [],
];
