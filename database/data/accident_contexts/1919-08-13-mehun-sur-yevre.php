<?php

// Contexte sourcé du déraillement du train Nantes - Lyon à Mehun-sur-Yèvre du 13 août 1919 (recherche du 21/11/2026).
return [
    'slug' => '1919-08-13-mehun-sur-yevre',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1919' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1919',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1919&oldid=241133344#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : entre Vierzon et Bourges, en gare de Mehun-sur-Yèvre, train Nantes - Lyon déraillé vers 7 h, quinze voitures couchées, 2 morts dont le maire de Saumur, une dizaine de blessés.',
        ],
        'delpher-1919-08-16' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk.',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1919-08-16',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB04:000191676:mpeg21:a0016',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Tours : l\'express de Nantes à Lyon a déraillé près de Mehun-sur-Yèvre, quatorze voitures jetées hors des rails et barrant la double voie, deux morts et un grand nombre de blessés.',
        ],
        'petit-journal-1919-08-14' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 14 août 1919',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1919-08-14',
            'locator' => 'p. 1 et 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k622366h/f1.image.r=le%20petit%20journal.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 13 août 1919, vers 7 heures, le train de Nantes à Lyon a déraillé à Mehun-sur-Yèvre, sur la ligne entre Vierzon et Bourges. Selon la liste de Wikipédia, l\'accident s\'est produit en gare.',
                'en' => 'On 13 August 1919, at about 7 am, the Nantes to Lyon train derailed at Mehun-sur-Yèvre, on the line between Vierzon and Bourges. According to the Wikipedia list, the accident took place in the station.',
                'de' => 'Am 13. August 1919 gegen 7 Uhr entgleiste der Zug von Nantes nach Lyon in Mehun-sur-Yèvre auf der Strecke zwischen Vierzon und Bourges. Laut der Wikipedia-Liste ereignete sich der Unfall im Bahnhof.',
                'sources' => ['wp-liste-1919', 'delpher-1919-08-16'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les voitures ont été projetées hors des rails ; la presse néerlandaise parle de quatorze voitures qui ont barré la double voie, la liste de Wikipédia de quinze voitures couchées.',
                'en' => 'The carriages were thrown off the rails; the Dutch press speaks of fourteen carriages blocking the double track, the Wikipedia list of fifteen carriages lying on their sides.',
                'de' => 'Die Wagen wurden aus den Gleisen geworfen; die niederländische Presse spricht von vierzehn Wagen, die das zweigleisige Gleis versperrten, die Wikipedia-Liste von fünfzehn umgestürzten Wagen.',
                'sources' => ['wp-liste-1919', 'delpher-1919-08-16'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait deux morts, dont le maire de Saumur, et des blessés : une dizaine selon la liste de Wikipédia, un grand nombre selon la dépêche du 16 août 1919.',
                'en' => 'The accident killed two people, among them the mayor of Saumur, and injured others: about ten according to the Wikipedia list, a large number according to the report of 16 August 1919.',
                'de' => 'Bei dem Unfall starben zwei Menschen, darunter der Bürgermeister von Saumur, und es gab Verletzte: etwa zehn laut Wikipedia-Liste, eine große Zahl laut der Meldung vom 16. August 1919.',
                'sources' => ['wp-liste-1919', 'delpher-1919-08-16'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de voitures déraillées (14 ou 15) et celui des blessés (une dizaine ou un grand nombre) diffèrent selon les deux sources. Le nombre de morts, deux, est identique.',
                'en' => 'The number of derailed carriages (14 or 15) and of injured (about ten or a large number) differ between the two sources. The number of dead, two, is the same.',
                'de' => 'Die Zahl der entgleisten Wagen (14 oder 15) und der Verletzten (etwa zehn oder eine große Zahl) unterscheidet sich in den beiden Quellen. Die Zahl der Toten, zwei, stimmt überein.',
                'sources' => ['wp-liste-1919', 'delpher-1919-08-16'],
            ],
        ],
    ],
];
