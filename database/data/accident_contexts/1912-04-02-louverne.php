<?php

// Contexte sourcé de la collision d'un train de Paris à Brest et d'un train de marchandises en gare de Louverné, ligne de Paris à Brest, d'avril 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-04-02-louverne',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1912' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1912',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Avril',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Avril',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 2 avril 1912, d\'après Le Matin du 3 avril : sur la ligne Paris - Rennes, peu avant Laval, vers 14 h, en gare de Louverné, le rapide Paris - Rennes heurte un train de marchandises en manœuvre sur la voie principale et broie son fourgon de queue, où le conducteur (agent d\'accompagnement du train) est tué ; le mécanicien du train tamponneur est blessé.',
        ],
        'ddb-1912-04-03-rundschau' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugzusammenstoß',
            'publisher' => 'Tägliche Rundschau (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1912-04-03',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/BQXK24LN6NHPRNM4FTMALWMFP3QF56JS?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : la veille, vers 15 h, le train de voyageurs de Paris à Brest a heurté près de la gare de Louverné un train de marchandises qui en sortait ; le mécanicien et le chauffeur tués sur le coup, le mécanicien et le chauffeur du train de marchandises grièvement blessés, ainsi que trois voyageurs ; tous les wagons du train de marchandises, les deux locomotives et les tenders entièrement brisés ; voyageurs transbordés.',
        ],
        'delpher-1912-04-06-middelburgsche' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Middelburgsche courant du 6 avril 1912',
            'publisher' => 'Middelburgsche Courant (presse néerlandaise numérisée, Delpher)',
            'author' => null,
            'published' => '1912-04-06',
            'locator' => 'p. 3',
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010635623:mpeg21:a0054',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Brève : entre Le Mans et Laval, un express a vu trop tard un signal d\'arrêt et a heurté à pleine vitesse un train de marchandises en manœuvre ; un wagon de ce dernier a été broyé et l\'homme qui s\'y trouvait tué ; le mécanicien de l\'express a été blessé.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 2 avril 1912, dans l\'après-midi, un train de Paris vers l\'ouest, rapide pour Rennes selon la liste de Wikipédia, train de voyageurs pour Brest selon la presse allemande, a heurté un train de marchandises en manœuvre sur la voie principale en gare de Louverné, peu avant Laval. Les wagons du train de marchandises et les deux locomotives ont été brisés et les voyageurs ont été transbordés.',
                'en' => 'On the afternoon of 2 April 1912, a westbound train from Paris, an express for Rennes according to the Wikipedia list, a passenger train for Brest according to the German press, ran into a goods train shunting on the main line at Louverné station, just before Laval. The goods wagons and both engines were wrecked and passengers were transferred.',
                'de' => 'Am Nachmittag des 2. April 1912 prallte ein Zug von Paris nach Westen, nach der Wikipedia-Liste ein Schnellzug nach Rennes, nach der deutschen Presse ein Personenzug nach Brest, im Bahnhof Louverné kurz vor Laval auf einen auf dem Hauptgleis rangierenden Güterzug. Die Güterwagen und beide Lokomotiven wurden zertrümmert, die Reisenden mussten umsteigen.',
                'sources' => ['wp-liste-1912', 'ddb-1912-04-03-rundschau'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère. Selon la liste de Wikipédia et une brève néerlandaise du 6 avril, l\'agent qui se trouvait dans un wagon du train de marchandises a été tué et le mécanicien du train tamponneur blessé. Selon la Tägliche Rundschau du 3 avril, le mécanicien et le chauffeur du train de voyageurs ont été tués sur le coup, ceux du train de marchandises et trois voyageurs grièvement blessés. L\'heure aussi : vers 14 h dans la liste, vers 15 h dans la presse allemande.',
                'en' => 'The toll differs. According to the Wikipedia list and a Dutch note of 6 April, the railwayman in a wagon of the goods train was killed and the driver of the colliding train injured. According to the Tägliche Rundschau of 3 April, the driver and stoker of the passenger train were killed outright, those of the goods train and three passengers badly injured. The time too: about 2 pm in the list, about 3 pm in the German press.',
                'de' => 'Die Opferzahl weicht ab. Nach der Wikipedia-Liste und einer niederländischen Kurzmeldung vom 6. April wurde der Bedienstete in einem Wagen des Güterzuges getötet und der Lokomotivführer des auffahrenden Zuges verletzt. Nach der Täglichen Rundschau vom 3. April wurden Lokomotivführer und Heizer des Personenzuges sofort getötet, die des Güterzuges und drei Reisende schwer verletzt. Auch die Uhrzeit: gegen 14 Uhr in der Liste, gegen 15 Uhr in der deutschen Presse.',
                'sources' => ['wp-liste-1912', 'delpher-1912-04-06-middelburgsche', 'ddb-1912-04-03-rundschau'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1912', 'delpher-1912-04-06-middelburgsche'],
            'fr' => 'Un agent du train de marchandises tué selon la liste de Wikipédia et la brève néerlandaise du 6 avril 1912 ; la Tägliche Rundschau compte deux morts dans l\'équipe du train de voyageurs ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One railwayman of the goods train killed according to the Wikipedia list and the Dutch note of 6 April 1912; the Tägliche Rundschau counts two dead among the passenger train crew; the record gave no number of deaths.',
            'de' => 'Ein Bediensteter des Güterzuges getötet nach der Wikipedia-Liste und der niederländischen Kurzmeldung vom 6. April 1912; die Tägliche Rundschau zählt zwei Tote im Personal des Personenzuges; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
