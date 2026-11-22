<?php

// Contexte sourcé du déraillement de Marcheprime du 14 avril 1935 (recherche du 21/11/2026).
return [
    'slug' => '1935-04-14-marcheprime',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1935' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1935',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Avril',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1935&oldid=236841665#Avril',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 14 avril à 2 h 30, double rupture d\'un bandage et de l\'attelage de la quatrième voiture du rapide Pyrénées-Côte d\'Argent, deux voitures contre un pilier de béton, 4 morts et 10 blessés.',
        ],
        'delpher-1935-04-15' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Trein ontspoord bij Bordeaux. Drie dooden, tien zwaar gewonden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1935-04-15',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010540261:mpeg21:a0145',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du 15 avril : express des Pyrénées, 7 voitures sur 13 déraillées, 3 morts et 10 blessés, roue de la quatrième voiture.',
        ],
        'delpher-1935-04-16' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het spoorwegongeluk bij Marcheprime',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1935-04-16',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010015924:mpeg21:a0118',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève du 16 avril : une quatrième victime retrouvée dans une voiture écrasée (texte OCR dégradé).',
        ],
        'delpher-1935-04-17' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De nachtsneltrein van Hendaye aan de Spaansche grens naar Parijs is nabij Marcheprime',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1935-04-17',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB23:001225090:mpeg21:a00128',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Légende du 17 avril : l\'express de nuit Hendaye-Paris a déraillé près de Marcheprime après le détachement d\'un amortisseur de la première voiture ; version isolée.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du samedi 13 au dimanche 14 avril 1935, vers 2 h 30 selon la liste de Wikipédia, l\'express de nuit Hendaye-Paris (le Pyrénées-Côte d\'Argent) devait arriver à Bordeaux vers 2 heures ; il a déraillé près de la gare de Marcheprime, à environ 30 km de Bordeaux.',
                'en' => 'In the night of Saturday 13 to Sunday 14 April 1935, at about 2.30 am according to the Wikipedia list, the Hendaye-Paris night express (the Pyrénées-Côte d\'Argent) was due in Bordeaux at about 2 am; it derailed near Marcheprime station, about 30 km from Bordeaux.',
                'de' => 'In der Nacht von Samstag, dem 13., auf Sonntag, den 14. April 1935, laut Wikipedia-Liste gegen 2.30 Uhr, entgleiste der Nachtschnellzug Hendaye-Paris (der Pyrénées-Côte d\'Argent), der gegen 2 Uhr in Bordeaux erwartet wurde, in der Nähe des Bahnhofs Marcheprime, etwa 30 km von Bordeaux entfernt.',
                'sources' => ['delpher-1935-04-15', 'wp-liste-1935'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Sept des treize voitures du train ont déraillé et ont été renversées ou écrasées les unes contre les autres. La liste de Wikipédia précise que deux d\'entre elles se sont écrasées contre un pilier de béton.',
                'en' => 'Seven of the train\'s thirteen coaches derailed and were overturned or crushed into one another. The Wikipedia list adds that two of them were crushed against a concrete pillar.',
                'de' => 'Sieben der dreizehn Wagen des Zuges entgleisten und wurden umgeworfen oder ineinandergedrückt. Die Wikipedia-Liste ergänzt, dass zwei davon gegen einen Betonpfeiler gedrückt wurden.',
                'sources' => ['delpher-1935-04-15', 'wp-liste-1935'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte quatre morts et dix blessés. Les premières dépêches du 15 avril donnent trois morts et dix blessés ; une brève du 16 avril signale une quatrième victime retrouvée dans une voiture écrasée.',
                'en' => 'The Wikipedia list counts four dead and ten injured. The first reports of 15 April give three dead and ten injured; an item of 16 April reports a fourth victim found in a crushed coach.',
                'de' => 'Die Wikipedia-Liste zählt vier Tote und zehn Verletzte. Die ersten Meldungen vom 15. April nennen drei Tote und zehn Verletzte; eine Notiz vom 16. April meldet ein viertes Opfer, das in einem zerstörten Wagen gefunden wurde.',
                'sources' => ['wp-liste-1935', 'delpher-1935-04-15', 'delpher-1935-04-16'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les dépêches du 15 avril attribuent l\'accident à une roue de la quatrième voiture qui s\'est détachée ; la liste de Wikipédia parle d\'une double rupture d\'un bandage et de l\'attelage de cette même voiture.',
                'en' => 'The reports of 15 April attribute the accident to a wheel of the fourth coach coming off; the Wikipedia list speaks of a double failure of a tyre and of the coupling of that same coach.',
                'de' => 'Die Meldungen vom 15. April führen den Unfall auf ein sich lösendes Rad des vierten Wagens zurück; die Wikipedia-Liste spricht von einem doppelten Bruch eines Radreifens und der Kupplung desselben Wagens.',
                'sources' => ['delpher-1935-04-15', 'wp-liste-1935'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Une légende de photographie du 17 avril donne une autre cause, le détachement d\'un amortisseur de la première voiture. Le nombre de morts passe de trois (premières dépêches) à quatre (liste de Wikipédia et brève du 16 avril).',
                'en' => 'A photo caption of 17 April gives another cause, a shock absorber coming loose on the first coach. The number of dead rises from three (first reports) to four (Wikipedia list and item of 16 April).',
                'de' => 'Eine Bildunterschrift vom 17. April nennt eine andere Ursache, einen sich lösenden Stoßdämpfer am ersten Wagen. Die Zahl der Toten steigt von drei (erste Meldungen) auf vier (Wikipedia-Liste und Notiz vom 16. April).',
                'sources' => ['delpher-1935-04-17', 'delpher-1935-04-15', 'wp-liste-1935', 'delpher-1935-04-16'],
            ],
        ],
    ],
    'corrections' => [],
];
