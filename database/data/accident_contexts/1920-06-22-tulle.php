<?php

// Contexte sourcé du déraillement du train de marchandises en gare de Tulle du 22 juin 1920 (recherche du 21/11/2026).
return [
    'slug' => '1920-06-22-tulle',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1920' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1920',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Juin',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1920&oldid=236476563#Juin',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : en gare de Tulle, sur la ligne de Tulle à Meymac, la machine et les seize wagons d\'un train de marchandises venant d\'Ussel, dont les freins n\'ont pu ralentir la descente de Gimel-les-Cascades, déraillent et se télescopent, 3 morts et 1 blessé parmi le personnel du train.',
        ],
        'delpher-1920-06-24' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1920-06-24',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMRAZ02:000409146:mpeg21:a00023',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : au déraillement d\'un train de marchandises à Tulle (écrit Tule), la locomotive et 16 wagons ont déraillé, 3 morts et 1 blessé.',
        ],
        'petit-parisien-1920-06-23' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 23 juin 1920',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1920-06-23',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k604328z/f3.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de l\'accident, cité par Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 22 juin 1920, un train de marchandises venant d\'Ussel est arrivé en gare de Tulle sur la ligne de Tulle à Meymac sans que ses freins, défaillants dans la descente de Gimel-les-Cascades, aient pu le ralentir.',
                'en' => 'On 22 June 1920, a goods train coming from Ussel reached Tulle station on the Tulle to Meymac line without its brakes, which failed on the descent from Gimel-les-Cascades, having been able to slow it.',
                'de' => 'Am 22. Juni 1920 erreichte ein Güterzug aus Ussel den Bahnhof Tulle auf der Strecke von Tulle nach Meymac, ohne dass seine Bremsen, die im Gefälle von Gimel-les-Cascades versagten, ihn hatten verlangsamen können.',
                'sources' => ['wp-liste-1920'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive et les seize wagons du train ont déraillé en gare de Tulle et se sont télescopés.',
                'en' => 'The locomotive and the sixteen wagons of the train derailed in Tulle station and telescoped.',
                'de' => 'Die Lokomotive und die sechzehn Wagen des Zuges entgleisten im Bahnhof Tulle und schoben sich ineinander.',
                'sources' => ['wp-liste-1920', 'delpher-1920-06-24'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La brève néerlandaise du 24 juin et la liste de Wikipédia comptent trois morts et un blessé ; selon Wikipédia, ce sont des membres du personnel du train.',
                'en' => 'The Dutch item of 24 June and the Wikipedia list count three dead and one injured; according to Wikipedia, they were members of the train crew.',
                'de' => 'Die niederländische Meldung vom 24. Juni und die Wikipedia-Liste zählen drei Tote und einen Verletzten; laut Wikipedia gehörten sie zum Zugpersonal.',
                'sources' => ['delpher-1920-06-24', 'wp-liste-1920'],
            ],
        ],
    ],
];
