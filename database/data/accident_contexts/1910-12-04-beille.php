<?php

// Contexte sourcé de l'accident de passage à niveau près de la gare de Connerré-Beillé, ligne de Paris au Mans, de décembre 1910 (recherche du 21/11/2026).
return [
    'slug' => '1910-12-04-beille',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1910' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1910',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1910&oldid=233727870#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 4 décembre 1910, d\'après Le Petit Parisien du 5 décembre : à 0 h 40, peu avant la gare de Connerré-Beillé, les chauffeurs de deux automobiles ouvrent les barrières non cadenassées d\'un passage à niveau fermé de la ligne de Paris au Mans ; la première voiture passe, la seconde est broyée par l\'express du Mans, ses trois occupants sont tués.',
        ],
        'ddb-1910-12-05-hannover' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Schweres Automobilunglück in Frankreich',
            'publisher' => 'Hannoverscher Kurier (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-12-05',
            'locator' => 'p. 6',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/ELDRPVYJNWDWLFBZYGKZ42BUHO3EJL6R?issuepage=6',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 5 décembre : la nuit dernière, près du Mans, une automobile a été happée sur un passage à niveau par l\'express de Paris et détruite, ses trois occupants tués sur le coup ; l\'essence répandue a mis le feu à plusieurs voitures du train, vite éteint ; les corps ont été déposés à la gare de Connerré-Beillé ; le garde-barrière est mis en cause pour s\'être endormi au lieu de fermer les barrières selon le règlement.',
        ],
        'ddb-1910-12-05-morgenpost' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Récit de l\'accident de Connerré-Beillé',
            'publisher' => 'Berliner Morgenpost (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-12-05',
            'locator' => 'p. 1',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/Z33OAS6EVTUT36MJQYM72XR2X4G366JF?issuepage=1',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit détaillé : deux automobiles arrivent devant le passage à niveau fermé près de Connerré-Beillé ; les chauffeurs, ne voulant pas attendre, ouvrent eux-mêmes la barrière, dont le cadenas n\'avait pas été fermé, malgré l\'avertissement d\'un garde forestier ; la première passe, la seconde est broyée par l\'express Paris - Le Mans, en retard et lancé à 80 km/h ; le mécanicien, qui n\'avait pas vu les voitures, n\'arrête le train qu\'environ 400 m plus loin.',
        ],
        'ddb-1910-12-05-tag' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Ein furchtbares Automobilunglück',
            'publisher' => 'Der Tag (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-12-05',
            'locator' => 'p. 1',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/E6BIO27DDRT5OYNS3WIHQYXL2PLC4A2F?issuepage=1',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme du Mans du 4 décembre : le passage à niveau était fermé, mais le garde dormait et n\'a pas entendu les avertisseurs ; le chauffeur de la première voiture a ouvert lui-même les barrières ; le garde, qui n\'avait pas fermé les barrières au cadenas, a été arrêté.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du 3 au 4 décembre 1910, vers 0 h 40, deux automobiles se sont arrêtées devant un passage à niveau fermé de la ligne de Paris au Mans, peu avant la gare de Connerré-Beillé. Le garde-barrière dormait et n\'avait pas fermé les barrières au cadenas.',
                'en' => 'During the night of 3 to 4 December 1910, at about 0.40 am, two motor cars stopped at a closed level crossing on the Paris to Le Mans line, shortly before Connerré-Beillé station. The crossing keeper was asleep and had not padlocked the gates.',
                'de' => 'In der Nacht vom 3. auf den 4. Dezember 1910 gegen 0.40 Uhr hielten zwei Automobile vor einem geschlossenen Bahnübergang der Strecke Paris-Le Mans kurz vor dem Bahnhof Connerré-Beillé. Der Schrankenwärter schlief und hatte die Schranken nicht mit dem Vorhängeschloss gesichert.',
                'sources' => ['wp-liste-1910', 'ddb-1910-12-05-tag', 'ddb-1910-12-05-morgenpost'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les chauffeurs ont ouvert eux-mêmes les barrières. La première voiture a franchi la voie, la seconde a été broyée par l\'express de Paris au Mans, qui roulait en retard à environ 80 km/h. L\'essence répandue a mis le feu à plusieurs voitures du train ; l\'incendie a été vite éteint.',
                'en' => 'The drivers opened the gates themselves. The first car got across, the second was crushed by the Paris to Le Mans express, which was running late at about 80 km/h. Spilt petrol set fire to several coaches of the train; the fire was soon put out.',
                'de' => 'Die Chauffeure öffneten die Schranken selbst. Das erste Auto kam hinüber, das zweite wurde vom verspäteten, etwa 80 km/h schnellen Expresszug Paris-Le Mans zermalmt. Ausgelaufenes Benzin setzte mehrere Wagen des Zuges in Brand; das Feuer war bald gelöscht.',
                'sources' => ['wp-liste-1910', 'ddb-1910-12-05-morgenpost', 'ddb-1910-12-05-hannover'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les trois occupants de la seconde voiture ont été tués sur le coup. Leurs corps ont été déposés dans la salle d\'attente de la gare de Connerré-Beillé.',
                'en' => 'The three occupants of the second car were killed outright. Their bodies were laid out in the waiting room of Connerré-Beillé station.',
                'de' => 'Die drei Insassen des zweiten Autos waren sofort tot. Ihre Leichen wurden im Wartesaal des Bahnhofs Connerré-Beillé aufgebahrt.',
                'sources' => ['wp-liste-1910', 'ddb-1910-12-05-hannover'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Selon un télégramme du Mans, le garde-barrière a été arrêté.',
                'en' => 'According to a telegram from Le Mans the crossing keeper was arrested.',
                'de' => 'Nach einem Telegramm aus Le Mans wurde der Schrankenwärter verhaftet.',
                'sources' => ['ddb-1910-12-05-tag'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 3,
            'sources' => ['wp-liste-1910', 'ddb-1910-12-05-hannover', 'ddb-1910-12-05-morgenpost'],
            'fr' => 'Trois occupants de l\'automobile tués selon la liste de Wikipédia (Le Petit Parisien du 5 décembre 1910) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Three occupants of the car killed according to the Wikipedia list (Le Petit Parisien of 5 December 1910) and the German press; the record gave no number of deaths.',
            'de' => 'Drei Insassen des Automobils getötet nach der Wikipedia-Liste (Le Petit Parisien vom 5. Dezember 1910) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
