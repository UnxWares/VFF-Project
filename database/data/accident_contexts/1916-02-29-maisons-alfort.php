<?php

// Contexte sourcé de l'accident de Maisons-Alfort du 29 février 1916 (recherche du 21/11/2026).
return [
    'slug' => '1916-02-29-maisons-alfort',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1916' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1916',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Février, 29 février 1916',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1916&oldid=241348251#F%C3%A9vrier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de l\'époque (Gallica).',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 29 février 1916 à 18 h 45, à Maisons-Alfort, dans la banlieue sud-est de Paris, un omnibus parti de Montgeron pour Paris a heurté un wagonnet en dérive sur les voies principales et a déraillé.',
                'en' => 'On 29 February 1916 at 6.45 pm, at Maisons-Alfort in the south-eastern suburbs of Paris, a stopping train from Montgeron to Paris hit a runaway trolley on the main line and derailed.',
                'de' => 'Am 29. Februar 1916 um 18.45 Uhr prallte in Maisons-Alfort im südöstlichen Vorortbereich von Paris ein Personenzug von Montgeron nach Paris gegen einen auf den Hauptgleisen abrollenden Rollwagen und entgleiste.',
                'sources' => ['wp-liste-1916'],
            ],
            [
                'fr' => 'Les quatre premières voitures, en bois et éclairées au gaz, se sont écrasées contre les piles d\'un pont et ont été éventrées. On a compté huit morts et trente-six blessés.',
                'en' => 'The first four coaches, wooden and gas-lit, smashed against the piers of a bridge and were torn open. Eight people were killed and thirty-six injured.',
                'de' => 'Die vier vorderen Wagen aus Holz mit Gasbeleuchtung prallten gegen die Pfeiler einer Brücke und wurden aufgerissen. Es gab acht Tote und sechsunddreißig Verletzte.',
                'sources' => ['wp-liste-1916'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Un autre accident a eu lieu le même jour sur la ligne de Paris à Caen, en gare de Serquigny (trois morts) : c\'est un événement distinct.',
                'en' => 'Another accident happened the same day on the Paris to Caen line, at Serquigny station (three dead): it is a separate event.',
                'de' => 'Am selben Tag ereignete sich auf der Strecke Paris-Caen im Bahnhof Serquigny ein weiterer Unfall (drei Tote): Es ist ein eigenes Ereignis.',
                'sources' => ['wp-liste-1916'],
            ],
        ],
    ],
    'corrections' => [],
];
