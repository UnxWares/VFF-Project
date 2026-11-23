<?php

// Contexte sourcé de la collision de la gare de Lyon à Paris du 15 août 1926 (recherche du 21/11/2026).
return [
    'slug' => '1926-08-15-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1926' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1926',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1926&oldid=233501524#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 15 août, vers 23 h 40, en gare de Paris-Lyon, avarie d\'un aiguillage, train de banlieue de Villeneuve-Saint-Georges pris en écharpe par une rame vide, 2 morts et une vingtaine de blessés.',
        ],
        'delpher-1926-08-16a' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk in Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1926-08-16',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010603774:mpeg21:a0048',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêches Havas et B.T.A. : collision à 400 m de la gare de Lyon entre un train de voyageurs et une locomotive de manoeuvre, 2 morts et une quarantaine de blessés, puis 1 mort et une trentaine de blessés.',
        ],
        'delpher-1926-08-16b' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengd nieuws. Ernstige treinbotsing te Parijs',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1926-08-16',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:011083772:mpeg21:a0068',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article : train de banlieue contre un train de voyageurs en manoeuvre, 11 h 40 du soir, deux voitures renversées, 1 mort et 28 blessés dont 6 graves (3 morts et 40 blessés dans la première version).',
        ],
        'delpher-1926-08-16c' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinbotsing. 4 dooden, 40 gewonden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1926-08-16',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010932234:mpeg21:a0078',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche : train de voyageurs et locomotive en collision à l\'entrée de la gare de Lyon, 3 morts et une quarantaine de blessés (4 morts dans le titre).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 15 août 1926 à 23 h 40, à l\'entrée de la gare de Lyon, à Paris (à 400 m de la gare selon une dépêche), un train de banlieue est entré en collision avec une rame ou une locomotive qui manoeuvrait. La liste de Wikipédia précise que le train venait de Villeneuve-Saint-Georges et qu\'il a été pris en écharpe, à la suite de l\'avarie d\'un aiguillage, par une rame de matériel vide remontant vers le chantier de Conflans.',
                'en' => 'On Saturday 15 August 1926 at 11.40 p.m., at the entrance to the Gare de Lyon in Paris (400 m from the station according to one dispatch), a suburban train collided with a train set or a locomotive that was shunting. The Wikipedia list specifies that the train came from Villeneuve-Saint-Georges and was sideswiped, after a points failure, by a rake of empty stock heading up to the Conflans yard.',
                'de' => 'Am Samstag, dem 15. August 1926, um 23.40 Uhr stieß am Einfahrtsbereich des Pariser Gare de Lyon (laut einer Meldung 400 m vom Bahnhof entfernt) ein Vorortzug mit einer rangierenden Zuggarnitur oder Lokomotive zusammen. Die Wikipedia-Liste präzisiert, der Zug sei aus Villeneuve-Saint-Georges gekommen und nach dem Ausfall einer Weiche von einer leeren Wagengarnitur auf dem Weg zum Bahnhof Conflans gestreift worden.',
                'sources' => ['delpher-1926-08-16a', 'delpher-1926-08-16b', 'delpher-1926-08-16c', 'wp-liste-1926'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Deux voitures ont été renversées.',
                'en' => 'Two coaches were overturned.',
                'de' => 'Zwei Wagen wurden umgeworfen.',
                'sources' => ['delpher-1926-08-16b'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les premières dépêches comptent deux, trois ou quatre morts et une quarantaine de blessés ; le récit détaillé de l\'article donne un mort et vingt-huit blessés dont six graves, et une dépêche de l\'agence B.T.A. un mort et une trentaine de blessés dont six graves. La liste de Wikipédia compte deux morts et une vingtaine de blessés.',
                'en' => 'The first dispatches count two, three or four dead and about forty injured; the detailed account in the article gives one dead and twenty-eight injured, six of them seriously, and a B.T.A. agency dispatch one dead and about thirty injured, six of them seriously. The Wikipedia list counts two dead and about twenty injured.',
                'de' => 'Die ersten Meldungen zählen zwei, drei oder vier Tote und etwa vierzig Verletzte; der ausführliche Bericht im Artikel nennt einen Toten und achtundzwanzig Verletzte, davon sechs schwer, eine Meldung der Agentur B.T.A. einen Toten und etwa dreißig Verletzte, davon sechs schwer. Die Wikipedia-Liste zählt zwei Tote und etwa zwanzig Verletzte.',
                'sources' => ['delpher-1926-08-16a', 'delpher-1926-08-16b', 'delpher-1926-08-16c', 'wp-liste-1926'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de morts varie de un à quatre selon les dépêches du 16 août, et celui des blessés de vingt-huit à quarante. La liste de Wikipédia donne 2 morts et une vingtaine de blessés. Le lieu est la gare de Lyon, à Paris, et non Villeneuve-Saint-Georges, d\'où venait le train.',
                'en' => 'The number of dead ranges from one to four according to the dispatches of 16 August, and that of injured from twenty-eight to forty. The Wikipedia list gives 2 dead and about twenty injured. The place is the Gare de Lyon in Paris, not Villeneuve-Saint-Georges, where the train came from.',
                'de' => 'Die Zahl der Toten reicht laut den Meldungen vom 16. August von eins bis vier, die der Verletzten von achtundzwanzig bis vierzig. Die Wikipedia-Liste nennt 2 Tote und etwa zwanzig Verletzte. Der Ort ist der Pariser Gare de Lyon, nicht Villeneuve-Saint-Georges, der Herkunftsort des Zuges.',
                'sources' => ['delpher-1926-08-16a', 'delpher-1926-08-16b', 'delpher-1926-08-16c', 'wp-liste-1926'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '75056',
            'sources' => ['wp-liste-1926', 'delpher-1926-08-16a', 'delpher-1926-08-16b'],
            'fr' => 'La collision a eu lieu à l\'entrée de la gare de Lyon, à Paris, et non à Villeneuve-Saint-Georges, d\'où venait le train de banlieue.',
            'en' => 'The collision took place at the entrance to the Gare de Lyon in Paris, not at Villeneuve-Saint-Georges, where the suburban train came from.',
            'de' => 'Die Kollision ereignete sich am Einfahrtsbereich des Pariser Gare de Lyon, nicht in Villeneuve-Saint-Georges, dem Herkunftsort des Vorortzugs.',
        ],
    ],
];
