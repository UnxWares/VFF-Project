<?php

// Contexte sourcé du déraillement d'un train des Invalides à Suresnes près de la gare des Moulineaux-Billancourt, ligne des Moulineaux, de mai 1910 (recherche du 21/11/2026).
return [
    'slug' => '1910-05-23-suresnes',
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
            'locator' => 'Mai',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1910&oldid=233727870#Mai',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 23 mai 1910, d\'après Le Figaro du 24 mai : vers 19 h 30, sur la ligne des Moulineaux, près de la gare des Moulineaux-Billancourt, la locomotive d\'un train des Invalides à Suresnes quitte les rails et se renverse, tuant le mécanicien et le chauffeur ; dans le reste du convoi, écrasé contre la machine, deux voyageurs et le conducteur sont blessés.',
        ],
        'ddb-1910-05-24-koeln' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 24. Mai (Telegr.)',
            'publisher' => 'Kölnische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-05-24',
            'locator' => 'p. 14',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/FMEX74KSFI5PDM3UOEECY66CBX2F75HF?issuepage=14',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme de Paris du 24 mai : le train Paris - Suresnes a déraillé en gare des Moulineaux ; le mécanicien et le chauffeur ont été tués, deux voyageurs contusionnés (même texte dans le Schwäbischer Merkur du 24 mai et la Lüdenscheider Zeitung du 25).',
        ],
        'ddb-1910-05-25-dresden' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris (Priv.-Tel.)',
            'publisher' => 'Dresdner Nachrichten (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-05-25',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/NMYEKSHZBZ34UNUTGTULHWMZ4IO4BECF?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme privé : la veille au soir, le train des Invalides à Suresnes a déraillé entre Javel et les Moulineaux ; le chef de train et le chauffeur ont péri dans les flammes sorties de la locomotive renversée ; seize personnes et quelques conducteurs plus ou moins grièvement blessés ; cause encore inconnue.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 23 mai 1910, vers 19 h 30, la locomotive d\'un train des Invalides à Suresnes a quitté les rails et s\'est renversée sur la ligne des Moulineaux, entre Javel et la gare des Moulineaux-Billancourt. Le reste du train s\'est écrasé contre la machine.',
                'en' => 'On 23 May 1910, at about 7.30 pm, the engine of a train from Les Invalides to Suresnes left the rails and overturned on the Moulineaux line, between Javel and Les Moulineaux-Billancourt station. The rest of the train piled into the engine.',
                'de' => 'Am 23. Mai 1910 gegen 19.30 Uhr sprang die Lokomotive eines Zuges vom Invalidenbahnhof nach Suresnes auf der Moulineaux-Linie zwischen Javel und dem Bahnhof Les Moulineaux-Billancourt aus den Schienen und stürzte um. Der übrige Zug fuhr gegen die Maschine.',
                'sources' => ['wp-liste-1910', 'ddb-1910-05-24-koeln', 'ddb-1910-05-25-dresden'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués. Deux voyageurs et le conducteur du train ont été blessés selon la liste de Wikipédia ; la plupart des dépêches allemandes parlent de deux voyageurs contusionnés.',
                'en' => 'The driver and the stoker were killed. Two passengers and the train\'s guard were injured according to the Wikipedia list; most German dispatches speak of two passengers with bruises.',
                'de' => 'Lokomotivführer und Heizer wurden getötet. Nach der Wikipedia-Liste wurden zwei Reisende und der Zugbegleiter verletzt; die meisten deutschen Meldungen sprechen von zwei Reisenden mit Quetschungen.',
                'sources' => ['wp-liste-1910', 'ddb-1910-05-24-koeln'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Selon les Dresdner Nachrichten, les deux agents tués sont le chef de train et le chauffeur, morts dans les flammes sorties de la locomotive renversée, et seize personnes et quelques conducteurs ont été blessés.',
                'en' => 'According to the Dresdner Nachrichten, the two men killed were the guard and the stoker, who died in flames from the overturned engine, and sixteen people and some guards were injured.',
                'de' => 'Nach den Dresdner Nachrichten waren die beiden Toten der Zugführer und der Heizer, die in den Flammen der umgestürzten Lokomotive umkamen; sechzehn Personen und einige Kondukteure wurden verletzt.',
                'sources' => ['ddb-1910-05-25-dresden'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1910', 'ddb-1910-05-24-koeln', 'ddb-1910-05-25-dresden'],
            'fr' => 'Deux agents tués selon la liste de Wikipédia (Le Figaro du 24 mai 1910) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two railwaymen killed according to the Wikipedia list (Le Figaro of 24 May 1910) and the German press; the record gave no number of deaths.',
            'de' => 'Zwei Bedienstete getötet nach der Wikipedia-Liste (Le Figaro vom 24. Mai 1910) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
