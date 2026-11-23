<?php

// Contexte sourcé de la collision du 23 mars 1924 près de la gare de Bénestroff (recherche du 21/11/2026).
return [
    'slug' => '1924-03-23-benestroff',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1924' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1924',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Mars, 23 mars 1924',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1924&oldid=233501493#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de mars 1924 (Gallica).',
        ],
        'delpher-tilburgsche-1924-03-26' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het ongeluk bij Benestroff. FRANKRIJK.',
            'publisher' => 'Nieuwe Tilburgsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1924-03-26',
            'locator' => 'p. 6',
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010234144:mpeg21:a0117',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'L\'express Ostende - Bâle, à pleine vitesse, a heurté un train de marchandises à la station de Bénestroff ; huit corps retirés dans ce bilan provisoire.',
        ],
        'delpher-leeuwarder-1924-03-24' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk.',
            'publisher' => 'Leeuwarder Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1924-03-24',
            'locator' => 'p. 3',
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010603049:mpeg21:a0043',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche Havas : l\'express heurte un train de marchandises à la station de Benestroff vers 2 h, huit morts.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 23 mars 1924 vers 2 h, sur la ligne de Metz à Strasbourg, près de la gare de Bénestroff, l\'aiguilleur de service s\'était endormi. Le rapide Calais - Bâle a été dirigé par erreur sur la voie où un train de charbon avait été garé pour le laisser passer.',
                'en' => 'On 23 March 1924 at about 2 am, on the Metz to Strasbourg line near Bénestroff station, the signalman on duty had fallen asleep. The Calais to Basel express was wrongly routed onto the track where a coal train had been put aside to let it pass.',
                'de' => 'Am 23. März 1924 gegen 2 Uhr war auf der Strecke Metz-Straßburg nahe dem Bahnhof Bénestroff der diensthabende Weichenwärter eingeschlafen. Der Schnellzug Calais-Basel wurde irrtümlich auf das Gleis geleitet, auf dem ein Kohlezug abgestellt war, um ihn vorbeizulassen.',
                'sources' => ['wp-liste-1924'],
            ],
            [
                'fr' => 'La locomotive du rapide s\'est couchée, et deux fourgons et trois voitures de troisième classe se sont disloqués contre elle. L\'accident a fait neuf morts et quinze blessés, dont treize graves.',
                'en' => 'The express locomotive fell on its side, and two vans and three third-class coaches broke up against it. The accident killed nine people and injured fifteen, thirteen of them seriously.',
                'de' => 'Die Lokomotive des Schnellzugs kippte um, und zwei Packwagen sowie drei Wagen dritter Klasse zerbrachen an ihr. Der Unfall forderte neun Tote und fünfzehn Verletzte, davon dreizehn schwer.',
                'sources' => ['wp-liste-1924'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1924', 'delpher-tilburgsche-1924-03-26', 'delpher-leeuwarder-1924-03-24'],
            'fr' => 'Le rapide, mal aiguillé, a heurté un train de charbon garé : c\'est une collision.',
            'en' => 'The express, wrongly routed, ran into a coal train standing in a siding: this is a collision.',
            'de' => 'Der falsch geleitete Schnellzug fuhr auf einen abgestellten Kohlezug auf: Es handelt sich um einen Zusammenstoß.',
        ],
    ],
];
