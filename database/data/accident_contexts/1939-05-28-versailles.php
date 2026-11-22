<?php

// Contexte sourcé de la collision à la sortie de la gare Montparnasse du 28 mai 1939 (recherche du 21/11/2026).
return [
    'slug' => '1939-05-28-versailles',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-versailles' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinbotsing te Parijs. Een doode, 14 gewonden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1939-05-30',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB04:000149797:mpeg21:a0012',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1939' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1939',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Mai',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1939&oldid=233504457#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : train pour Versailles prenant en écharpe un train de Rambouillet à la sortie de la gare Montparnasse, un mort et treize blessés ; cite Le Petit Parisien du 29 mai 1939.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 28 mai 1939, vers 22 h, à la sortie de la gare Montparnasse, à Paris, dans la gare annexe de l\'avenue du Maine, un train en partance pour Versailles a pris en écharpe un train venant de Rambouillet. Les deux trains se sont heurtés à un point où deux voies se croisent, selon la presse néerlandaise.',
                'en' => 'On Sunday 28 May 1939, at about 10 pm, at the exit of Montparnasse station in Paris, in the annexe station on Avenue du Maine, a train leaving for Versailles hit a train from Rambouillet side-on. The two trains collided at a point where two tracks cross, according to the Dutch press.',
                'de' => 'Am Sonntag, dem 28. Mai 1939, gegen 22 Uhr, erfasste am Ausgang des Bahnhofs Montparnasse in Paris, im Nebenbahnhof an der Avenue du Maine, ein nach Versailles abfahrender Zug seitlich einen aus Rambouillet kommenden Zug. Die beiden Züge stießen nach der niederländischen Presse an einer Stelle zusammen, wo sich zwei Gleise kreuzen.',
                'sources' => ['wp-liste-1939', 'presse-versailles'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive d\'un des trains a heurté à assez grande vitesse les voitures du milieu de l\'autre train, d\'après la presse néerlandaise.',
                'en' => 'The locomotive of one train struck the middle carriages of the other train at fairly high speed, according to the Dutch press.',
                'de' => 'Die Lokomotive eines der Züge prallte nach der niederländischen Presse mit ziemlicher Geschwindigkeit gegen die mittleren Wagen des anderen Zuges.',
                'sources' => ['presse-versailles'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une femme a été tuée et quatorze autres voyageurs blessés selon la presse néerlandaise ; la liste de Wikipédia compte un mort et treize blessés dans le train de Rambouillet.',
                'en' => 'A woman was killed and fourteen other passengers injured according to the Dutch press; the Wikipedia list counts one dead and thirteen injured in the Rambouillet train.',
                'de' => 'Eine Frau wurde getötet und vierzehn weitere Fahrgäste wurden verletzt, nach der niederländischen Presse; die Wikipedia-Liste zählt einen Toten und dreizehn Verletzte im Zug aus Rambouillet.',
                'sources' => ['presse-versailles', 'wp-liste-1939'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
