<?php

// Contexte sourcé de la collision de deux convois près de la gare de Saint-Michel-sur-Orge, chemin de fer de Paris à Orléans, du 13 décembre 1845 (recherche du 22/11/2026).
return [
    'slug' => '1845-12-13-saint-michel-sur-orge',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1840' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1840',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1845',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1840&oldid=235357682#1845',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 13 décembre 1845 : sur le chemin de fer de Paris à Orléans, en gare de Saint-Michel-sur-Orge, un convoi de marchandises à l\'arrêt pour prendre de l\'eau est rattrapé par le train qui le dédoublait, conduit par un mécanicien novice ; un inspecteur chargé de superviser ce mécanicien est tué.',
        ],
        'limbourg-1845-12-18' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Journal du Limbourg du 18 décembre 1845',
            'publisher' => 'Journal du Limbourg',
            'author' => null,
            'published' => '1845-12-18',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMCC01:048196294:mpeg21:a00005',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; journal en français. Brève sur le chemin de fer d\'Orléans : collision de deux convois près de Saint-Michel, le samedi soir.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le samedi soir 13 décembre 1845, sur le chemin de fer d\'Orléans, un convoi spécial a heurté près de Saint-Michel un autre convoi parti deux heures plus tôt. La liste de Wikipédia précise que le second convoi, un train de marchandises, était arrêté en gare de Saint-Michel-sur-Orge pour prendre de l\'eau et que le train qui le suivait était conduit par un mécanicien novice.',
                'en' => 'On the evening of Saturday 13 December 1845, on the Orléans railway, a special train ran into another train that had left two hours earlier, near Saint-Michel. The Wikipedia list adds that the second train, a goods train, was stopped at Saint-Michel-sur-Orge station to take on water and that the train behind it was driven by an inexperienced driver.',
                'de' => 'Am Samstagabend, dem 13. Dezember 1845, fuhr auf der Orléans-Bahn ein Sonderzug nahe Saint-Michel auf einen anderen Zug auf, der zwei Stunden früher abgefahren war. Die Wikipedia-Liste ergänzt, der zweite Zug, ein Güterzug, habe im Bahnhof Saint-Michel-sur-Orge zum Wasserfassen gehalten und der folgende Zug sei von einem unerfahrenen Lokführer gefahren worden.',
                'sources' => ['limbourg-1845-12-18', 'wp-liste-1840'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'inspecteur qui avait la direction du convoi tamponneur a été écrasé sous le tender. La brève du Journal du Limbourg et la liste de Wikipédia ne comptent que ce mort.',
                'en' => 'The inspector in charge of the train that ran into the other was crushed under the tender. The brief in the Journal du Limbourg and the Wikipedia list count only this death.',
                'de' => 'Der Inspektor, der den auffahrenden Zug leitete, wurde unter dem Tender zerquetscht. Die Meldung im Journal du Limbourg und die Wikipedia-Liste zählen nur diesen Toten.',
                'sources' => ['limbourg-1845-12-18', 'wp-liste-1840'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['limbourg-1845-12-18', 'wp-liste-1840'],
            'fr' => 'Un inspecteur tué selon la liste de Wikipédia et le Journal du Limbourg du 18 décembre 1845 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One inspector killed according to the Wikipedia list and the Journal du Limbourg of 18 December 1845; the record gave no number of deaths.',
            'de' => 'Ein Inspektor getötet nach der Wikipedia-Liste und dem Journal du Limbourg vom 18. Dezember 1845; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
