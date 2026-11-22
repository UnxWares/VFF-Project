<?php

// Contexte sourcé du déraillement de l'express Paris-Bordeaux à l'entrée de la gare des Aubrais, le 3 septembre 1920 (recherche du 21/11/2026).
return [
    'slug' => '1920-09-03-fleury-les-aubrais',
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
            'locator' => '3 septembre 1920, gare des Aubrais',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1920&oldid=236476563#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Express n° 11 Paris-Bordeaux, à 14 h, aiguillage du poste B franchi à 70 km/h ; la locomotive, le fourgon et cinq voitures se renversent : un mort et trente-deux blessés ; cite Le Petit Parisien du 4 septembre 1920 et Le Petit Journal du 5 septembre 1920.',
        ],
        'ah-1920-09-05' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Algemeen Handelsblad du 5 septembre 1920',
            'publisher' => 'Algemeen Handelsblad (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1920-09-05',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010654706:mpeg21:a0044',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Dépêche de Paris du correspondant du journal.',
        ],
        'petit-parisien-1920-09-04' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 4 septembre 1920, page 1',
            'publisher' => 'Le Petit Parisien (numérisé sur Gallica)',
            'author' => null,
            'published' => '1920-09-04',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k604401x',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 septembre 1920 à 14 h, à l\'entrée de la gare des Aubrais, près d\'Orléans, l\'express n° 11 de Paris à Bordeaux a franchi un aiguillage à vitesse excessive, 70 km/h, selon la liste de Wikipédia. Cet aiguillage, qui dépendait du poste B, le dirigeait sur une voie déviée, alors que le mécanicien affirmera lors de l\'enquête qu\'un signal vert lui indiquait la voie directe.',
                'en' => 'On 3 September 1920 at 2 pm, at the entrance to Les Aubrais station, near Orléans, express no. 11 from Paris to Bordeaux ran over a set of points at excessive speed, 70 km/h, according to the Wikipedia list. The points, which depended on signal box B, sent it onto a diverging track, whereas the driver would state during the inquiry that a green signal had shown him the direct track.',
                'de' => 'Am 3. September 1920 um 14 Uhr überfuhr der Schnellzug Nr. 11 von Paris nach Bordeaux bei der Einfahrt in den Bahnhof Les Aubrais bei Orléans laut der Wikipedia-Liste eine Weiche mit überhöhter Geschwindigkeit, 70 km/h. Die Weiche, die zum Stellwerk B gehörte, lenkte ihn auf ein abzweigendes Gleis, während der Lokführer bei der Untersuchung angeben wird, ein grünes Signal habe ihm das gerade Gleis gezeigt.',
                'sources' => ['wp-liste-1920', 'ah-1920-09-05'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive, le fourgon et les cinq voitures de tête se sont renversés, selon la liste de Wikipédia. L\'Algemeen Handelsblad indique seulement que l\'express Paris-Bordeaux a déraillé près des Aubrais.',
                'en' => 'The locomotive, the van and the five leading coaches overturned, according to the Wikipedia list. The Algemeen Handelsblad says only that the Paris to Bordeaux express derailed near Les Aubrais.',
                'de' => 'Die Lokomotive, der Gepäckwagen und die fünf vorderen Wagen kippten laut der Wikipedia-Liste um. Das Algemeen Handelsblad meldet nur, der Schnellzug Paris-Bordeaux sei bei Les Aubrais entgleist.',
                'sources' => ['wp-liste-1920', 'ah-1920-09-05'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une personne a été tuée et trente blessées selon l\'Algemeen Handelsblad du 5 septembre 1920 ; la liste de Wikipédia donne un mort et trente-deux blessés.',
                'en' => 'One person was killed and thirty injured according to the Algemeen Handelsblad of 5 September 1920; the Wikipedia list gives one dead and thirty-two injured.',
                'de' => 'Eine Person wurde getötet und dreißig verletzt laut dem Algemeen Handelsblad vom 5. September 1920; die Wikipedia-Liste nennt einen Toten und zweiunddreißig Verletzte.',
                'sources' => ['ah-1920-09-05', 'wp-liste-1920'],
            ],
        ],
    ],
    'corrections' => [],
];
