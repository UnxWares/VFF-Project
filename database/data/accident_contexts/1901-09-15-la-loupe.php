<?php

// Contexte sourcé du déraillement d'un train d'Évreux près de Damville, ligne d'Évreux à La Loupe, de septembre 1901 (recherche du 22/11/2026).
return [
    'slug' => '1901-09-15-la-loupe',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1901' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1901',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1901&oldid=241345719#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 15 septembre 1901 : sur la ligne d\'Évreux à La Loupe, vers 13 heures, peu avant la gare de Damville, dans une courbe prononcée, un train pour Évreux déraille ; la locomotive, le tender et le fourgon de tête dévalent le remblai ; le mécanicien est tué, le chauffeur et le conducteur de tête sont blessés, les voyageurs sont indemnes.',
        ],
        'rn-1901-09-17' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengd Nieuws',
            'publisher' => 'Rotterdamsch Nieuwsblad',
            'author' => null,
            'published' => '1901-09-17',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010179083:mpeg21:a0100',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; brève : près de la gare française de Damville, un train a déraillé samedi après-midi ; trois personnes tuées et plusieurs blessées, toutes du personnel du train.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Un train de voyageurs a déraillé dans une courbe prononcée, peu avant la gare de Damville, sur la ligne d\'Évreux à La Loupe. La locomotive, le tender et le fourgon de tête ont dévalé le remblai et le reste du convoi est resté sur le ballast. Selon la liste de Wikipédia, les voyageurs n\'ont pas été blessés.',
                'en' => 'A passenger train derailed on a sharp curve shortly before Damville station on the Évreux to La Loupe line. The locomotive, tender and leading van went down the embankment and the rest of the train stayed on the ballast. According to the Wikipedia list, the passengers were unhurt.',
                'de' => 'Ein Personenzug entgleiste in einer scharfen Kurve kurz vor dem Bahnhof Damville auf der Strecke von Évreux nach La Loupe. Die Lokomotive, der Tender und der vorderste Gepäckwagen stürzten die Böschung hinab, der Rest des Zugs blieb auf dem Schotter. Nach der Wikipedia-Liste blieben die Reisenden unverletzt.',
                'sources' => ['wp-liste-1901', 'rn-1901-09-17'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le mécanicien est tué, le chauffeur et le conducteur de tête sont blessés. La brève du Rotterdamsch Nieuwsblad compte trois morts et plusieurs blessés, tous du personnel du train.',
                'en' => 'According to the Wikipedia list the driver was killed and the fireman and the leading guard were injured. The brief in the Rotterdamsch Nieuwsblad counts three dead and several injured, all of them train staff.',
                'de' => 'Nach der Wikipedia-Liste wurde der Lokführer getötet, der Heizer und der vorderste Zugführer wurden verletzt. Die Meldung im Rotterdamsch Nieuwsblad zählt drei Tote und mehrere Verletzte, alle vom Zugpersonal.',
                'sources' => ['wp-liste-1901', 'rn-1901-09-17'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : un mort selon la liste de Wikipédia, trois morts selon la presse néerlandaise. La date diffère aussi : la liste donne le dimanche 15 septembre, la brève néerlandaise, parue le mardi 17, parle de samedi après-midi. Aucun des deux n\'est corrigé dans la fiche.',
                'en' => 'The toll differs: one dead according to the Wikipedia list, three dead according to the Dutch press. The date differs too: the list gives Sunday 15 September, the Dutch brief, published on Tuesday 17, speaks of Saturday afternoon. Neither is corrected in the record.',
                'de' => 'Die Bilanz weicht ab: ein Toter nach der Wikipedia-Liste, drei Tote nach der niederländischen Presse. Auch das Datum weicht ab: Die Liste nennt Sonntag, den 15. September, die niederländische Meldung vom Dienstag, dem 17., spricht vom Samstagnachmittag. Keines von beiden wird im Eintrag korrigiert.',
                'sources' => ['wp-liste-1901', 'rn-1901-09-17'],
            ],
        ],
    ],
    'corrections' => [],
];
