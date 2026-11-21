<?php

// Contexte sourcé de la collision de wagons partis en dérive avec un train de marchandises à Saint-Macaire, ligne de Bordeaux à Sète, de septembre 1905 (recherche du 21/11/2026).
return [
    'slug' => '1905-09-24-saint-macaire',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1905' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1905',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1905&oldid=233590727#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 24 septembre 1905, d\'après Le Figaro du 25 septembre : sur la ligne Bordeaux - Montauban, près de Langon, 32 wagons d\'un train de marchandises allant vers Bordeaux partent en dérive après une rupture d\'attelage et heurtent à Saint-Macaire le train qui suivait à deux minutes d\'intervalle, dont la locomotive tombe au bas d\'un viaduc ; le mécanicien, le chauffeur et un garde-frein sont tués.',
        ],
        'ddb-1905-09-26-dortmund' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnzusammenstöße',
            'publisher' => 'Dortmunder Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1905-09-26',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/JWVWVM3BJYKSSD7UZKFFEQY4GHF5GX3D?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme de Bordeaux du 25 septembre : près de Saint-Macaire, un train de marchandises et un wagon de marchandises vide sont entrés en collision ; le chauffeur et le mécanicien du train ont été tués.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 24 septembre 1905, sur la ligne de Bordeaux à Montauban, près de Langon, l\'attelage d\'un train de marchandises allant vers Bordeaux s\'est rompu. Trente-deux wagons sont partis en dérive et ont heurté à Saint-Macaire le train qui suivait à deux minutes. La locomotive de ce train est tombée au bas d\'un viaduc.',
                'en' => 'On 24 September 1905, on the Bordeaux to Montauban line near Langon, a coupling broke on a Bordeaux-bound goods train. Thirty-two wagons ran away and struck the following train, two minutes behind, at Saint-Macaire. That train\'s engine fell to the foot of a viaduct.',
                'de' => 'Am 24. September 1905 riss an der Strecke Bordeaux-Montauban bei Langon die Kupplung eines Güterzugs in Richtung Bordeaux. Zweiunddreißig Wagen rollten davon und stießen in Saint-Macaire mit dem zwei Minuten später folgenden Zug zusammen. Dessen Lokomotive stürzte am Fuß eines Viadukts hinab.',
                'sources' => ['wp-liste-1905', 'ddb-1905-09-26-dortmund'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur du train heurté ont été tués, ainsi qu\'un garde-frein selon la liste de Wikipédia.',
                'en' => 'The driver and the stoker of the train that was struck were killed, as was a brakesman according to the Wikipedia list.',
                'de' => 'Lokomotivführer und Heizer des getroffenen Zugs wurden getötet, nach der Wikipedia-Liste außerdem ein Bremser.',
                'sources' => ['wp-liste-1905', 'ddb-1905-09-26-dortmund'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La dépêche de Bordeaux du 25 septembre ne compte que deux morts, le chauffeur et le mécanicien, et parle d\'un wagon vide là où la liste de Wikipédia décrit trente-deux wagons partis en dérive.',
                'en' => 'The Bordeaux dispatch of 25 September counts only two dead, the stoker and the driver, and speaks of one empty wagon where the Wikipedia list describes thirty-two runaway wagons.',
                'de' => 'Die Meldung aus Bordeaux vom 25. September zählt nur zwei Tote, Heizer und Lokomotivführer, und spricht von einem leeren Güterwagen, wo die Wikipedia-Liste zweiunddreißig abgerollte Wagen beschreibt.',
                'sources' => ['ddb-1905-09-26-dortmund', 'wp-liste-1905'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 3,
            'sources' => ['wp-liste-1905', 'ddb-1905-09-26-dortmund'],
            'fr' => 'Mécanicien, chauffeur et garde-frein tués selon la liste de Wikipédia (Le Figaro du 25 septembre 1905) ; la dépêche allemande du 25 confirme les deux premiers ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver, stoker and brakesman killed according to the Wikipedia list (Le Figaro of 25 September 1905); the German dispatch of the 25th confirms the first two; the record gave no number of deaths.',
            'de' => 'Lokomotivführer, Heizer und Bremser getötet nach der Wikipedia-Liste (Le Figaro vom 25. September 1905); die deutsche Meldung vom 25. bestätigt die beiden ersten; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
