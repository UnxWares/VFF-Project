<?php

// Contexte sourcé du déraillement d'un express près de la gare de Montrabé, ligne de Toulouse à Capdenac, de juillet 1899 (recherche du 21/11/2026).
return [
    'slug' => '1899-07-29-montrabe',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1899',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1899',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 29 juillet 1899, d\'après Le Temps du 30 juillet : sur la ligne Paris - Toulouse par Capdenac, près de la gare de Montrabé, un express pour Paris déraille vers 13 h 30 ; la locomotive et le tender se couchent, le chauffeur est tué, un conducteur et trois postiers ambulants sont blessés.',
        ],
        'haagsche-courant-1899-08-01' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengd nieuws',
            'publisher' => 'Haagsche courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1899-08-01',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB04:000124351:mpeg21:a0041',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. L\'express de Paris à Capdenac a déraillé la veille après-midi près de la gare de Montrabé, non loin de Toulouse ; la machine, sortie des rails, est tombée dans un champ en écrasant le mécanicien ; six blessés, dont un très grièvement.',
        ],
        'rotterdamsch-nieuwsblad-1899-07-31' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk',
            'publisher' => 'Rotterdamsch nieuwsblad (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1899-07-31',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010177982:mpeg21:a0128',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Brève : près de la gare de Montrabé, à 12 kilomètres de Toulouse, un train a déraillé ; sept personnes grièvement blessées.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 29 juillet 1899 au début de l\'après-midi, un express a déraillé près de la gare de Montrabé, à une douzaine de kilomètres de Toulouse, sur la ligne de Toulouse à Capdenac. La locomotive et son tender, sortis des rails, se sont couchés dans un champ.',
                'en' => 'Early in the afternoon of 29 July 1899, an express derailed near Montrabé station, about twelve kilometres from Toulouse, on the Toulouse to Capdenac line. The engine and its tender left the rails and overturned into a field.',
                'de' => 'Am frühen Nachmittag des 29. Juli 1899 entgleiste nahe dem Bahnhof Montrabé, etwa zwölf Kilometer von Toulouse, an der Strecke Toulouse-Capdenac ein Schnellzug. Lokomotive und Tender sprangen aus den Schienen und stürzten auf ein Feld.',
                'sources' => ['wp-liste-1890', 'haagsche-courant-1899-08-01', 'rotterdamsch-nieuwsblad-1899-07-31'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent de la machine a été tué. La liste de Wikipédia compte quatre blessés, un conducteur et trois postiers ambulants ; la presse néerlandaise en compte six ou sept.',
                'en' => 'One of the engine crew was killed. The Wikipedia list counts four injured, a guard and three travelling post office clerks; the Dutch press counts six or seven.',
                'de' => 'Ein Mann des Lokomotivpersonals wurde getötet. Die Wikipedia-Liste zählt vier Verletzte, einen Zugbegleiter und drei Bahnpostbeamte, die niederländische Presse sechs oder sieben.',
                'sources' => ['wp-liste-1890', 'haagsche-courant-1899-08-01', 'rotterdamsch-nieuwsblad-1899-07-31'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le mort est le chauffeur selon la liste de Wikipédia, le mécanicien selon la Haagsche courant.',
                'en' => 'The man killed was the stoker according to the Wikipedia list, the driver according to the Haagsche courant.',
                'de' => 'Der Tote war nach der Wikipedia-Liste der Heizer, nach der Haagsche courant der Lokomotivführer.',
                'sources' => ['wp-liste-1890', 'haagsche-courant-1899-08-01'],
            ],
            [
                'fr' => 'Sens du train : un express pour Paris selon la liste, l\'express de Paris à Capdenac selon la Haagsche courant. Date et heure : vers 13 h 30 le 29 juillet selon la liste ; « hier après-midi » dans la Haagsche courant du 1er août, « cette nuit » dans le Rotterdamsch nieuwsblad du 31 juillet.',
                'en' => 'Direction: an express for Paris according to the list, the Paris to Capdenac express according to the Haagsche courant. Date and time: about 1.30 pm on 29 July according to the list; "yesterday afternoon" in the Haagsche courant of 1 August, "last night" in the Rotterdamsch nieuwsblad of 31 July.',
                'de' => 'Fahrtrichtung: ein Schnellzug nach Paris nach der Liste, der Schnellzug Paris-Capdenac nach der Haagsche courant. Datum und Uhrzeit: nach der Liste der 29. Juli gegen 13.30 Uhr; „gestern Nachmittag“ in der Haagsche courant vom 1. August, „in der Nacht“ im Rotterdamsch nieuwsblad vom 31. Juli.',
                'sources' => ['wp-liste-1890', 'haagsche-courant-1899-08-01', 'rotterdamsch-nieuwsblad-1899-07-31'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'haagsche-courant-1899-08-01'],
            'fr' => 'Un agent de la machine tué selon la liste de Wikipédia (Le Temps du 30 juillet 1899) et la presse néerlandaise du 1er août ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One of the engine crew killed according to the Wikipedia list (Le Temps of 30 July 1899) and the Dutch press of 1 August; the record gave no number of deaths.',
            'de' => 'Ein Mann des Lokomotivpersonals getötet nach der Wikipedia-Liste (Le Temps vom 30. Juli 1899) und der niederländischen Presse vom 1. August; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
