<?php

// Contexte sourcé de la collision de wagons de charbon et de locomotives à la fosse 2 des mines de Courrières, Billy-Montigny, de janvier 1900 (recherche du 21/11/2026).
return [
    'slug' => '1900-01-09-billy-montigny',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1900' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1900',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1900&oldid=233589330#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 9 janvier 1900, d\'après Le Petit Parisien du 10 janvier : à Billy-Montigny, sur le carreau de la fosse 2 de la compagnie des mines de Courrières, après une erreur d\'aiguillage, deux wagons chargés de charbon dévalent une voie en pente et percutent un convoi de trois locomotives ; trois des six mécaniciens et chauffeurs sont tués, trois grièvement blessés.',
        ],
        'ddb-1900-01-10-walder' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Bethune, 9. Jan.',
            'publisher' => 'Walder Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1900-01-10',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/R4EHN2ML3L5PLMSYUKFOBS5LNDCZILB3?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Béthune du 9 janvier : à la fosse 2 de Billy-Montigny, deux wagons de chemin de fer ont dévalé le plan incliné à la suite d\'une erreur d\'aiguillage et heurté une locomotive qui venait en sens inverse ; trois chauffeurs tués, trois blessés.',
        ],
        'ddb-1900-01-11-fremdenblatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Bethune, 9. Jan.',
            'publisher' => 'Hamburger Fremdenblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1900-01-11',
            'locator' => 'p. 21',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/B5JWC66CQFTMS2DPOBDW3GOWNOD6RYGM?issuepage=21',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche de Béthune, reprise aussi par les Bautzener Nachrichten, la Saale-Zeitung et la Wattenscheider Zeitung : deux wagons emballés sur le plan incliné après une erreur d\'aiguillage, contre une locomotive, trois chauffeurs tués, trois blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 9 janvier 1900, l\'accident a eu lieu sur les voies de la fosse 2 de la compagnie des mines de Courrières, à Billy-Montigny. Après une erreur d\'aiguillage, deux wagons chargés de charbon ont dévalé une voie en pente.',
                'en' => 'On 9 January 1900 the accident happened on the tracks of pit no. 2 of the Courrières mining company at Billy-Montigny. After a points error, two wagons loaded with coal ran away down a sloping track.',
                'de' => 'Am 9. Januar 1900 ereignete sich der Unfall auf den Gleisen der Schachtanlage Nr. 2 der Bergwerksgesellschaft von Courrières in Billy-Montigny. Nach einer falschen Weichenstellung rollten zwei mit Kohle beladene Wagen ein abschüssiges Gleis hinab.',
                'sources' => ['wp-liste-1900', 'ddb-1900-01-10-walder', 'ddb-1900-01-11-fremdenblatt'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les wagons ont heurté des locomotives qui venaient en sens inverse : une locomotive selon la dépêche de Béthune, un convoi de trois locomotives selon la liste de Wikipédia.',
                'en' => 'The wagons struck locomotives coming the other way: one locomotive according to the Béthune dispatch, a convoy of three locomotives according to the Wikipedia list.',
                'de' => 'Die Wagen prallten auf entgegenkommende Lokomotiven: eine Lokomotive nach der Depesche aus Béthune, ein Zug aus drei Lokomotiven nach der Wikipedia-Liste.',
                'sources' => ['ddb-1900-01-10-walder', 'wp-liste-1900'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois agents des locomotives ont été tués et trois autres grièvement blessés. La dépêche parle de chauffeurs, la liste de Wikipédia de mécaniciens et de chauffeurs.',
                'en' => 'Three locomotive crew members were killed and three others seriously injured. The dispatch speaks of stokers, the Wikipedia list of drivers and stokers.',
                'de' => 'Drei Mann des Lokomotivpersonals wurden getötet und drei weitere schwer verletzt. Die Depesche spricht von Heizern, die Wikipedia-Liste von Lokführern und Heizern.',
                'sources' => ['ddb-1900-01-10-walder', 'ddb-1900-01-11-fremdenblatt', 'wp-liste-1900'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 3,
            'sources' => ['wp-liste-1900', 'ddb-1900-01-10-walder', 'ddb-1900-01-11-fremdenblatt'],
            'fr' => 'Trois tués selon la liste de Wikipédia (Le Petit Parisien du 10 janvier 1900) et la dépêche de Béthune du 9 janvier ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Three killed according to the Wikipedia list (Le Petit Parisien of 10 January 1900) and the Béthune dispatch of 9 January; the record gave no number of deaths.',
            'de' => 'Drei Tote nach der Wikipedia-Liste (Le Petit Parisien vom 10. Januar 1900) und der Depesche aus Béthune vom 9. Januar; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
