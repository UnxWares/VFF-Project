<?php

// Contexte sourcé du déraillement entre Anizy-Pinon et Landricourt, ligne d'Anizy-Pinon à Chauny, de février 1895 (recherche du 21/11/2026).
return [
    'slug' => '1895-02-25-landricourt',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1895',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1895',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 25 février 1895, d\'après La Presse du 26 février : sur la ligne de Laon à Chauny, vers 9 h, entre Anizy-Pinon et Landricourt, un train de voyageurs déraille dans une descente à la suite d\'un affaissement du sol ; la locomotive, enlisée dans le remblai, est heurtée et écrasée par le reste du train ; le mécanicien et le chauffeur, mortellement blessés, meurent pendant leur transport à l\'hôpital ; les voyageurs sont seulement contusionnés.',
        ],
        'ddb-1895-02-28-koeln' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 26. Febr.',
            'publisher' => 'Kölner General-Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1895-02-28',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/GKCNHOTVXKBWQJYWXFAS3DABDU7EQZP7?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 26 février (reprise aussi par la Badische Landes-Zeitung et la Karlsruher Zeitung du 1er mars) : sur la ligne du Nord Anzin - Chauny, un train a déraillé près de Landricourt à la suite d\'une rupture de rail ; le mécanicien et un chauffeur tués.',
        ],
        'ddb-1895-02-27-merkur' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 27. Febr.',
            'publisher' => 'Rheinischer Merkur (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1895-02-27',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/WYGIOEYB26B4GWKISAA7SF6342P4KSUU?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche : déraillement près de Landricourt sur la ligne Anzin - Chauny par rupture de rail, mécanicien et chauffeur tués.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 25 février 1895, vers 9 h, un train de voyageurs descendait vers Landricourt sur la ligne d\'Anizy-Pinon à Chauny, entre Anizy-Pinon et Landricourt.',
                'en' => 'On 25 February 1895, at about 9 am, a passenger train was running downhill towards Landricourt on the Anizy-Pinon to Chauny line, between Anizy-Pinon and Landricourt.',
                'de' => 'Am 25. Februar 1895 gegen 9 Uhr fuhr ein Personenzug auf der Strecke Anizy-Pinon-Chauny zwischen Anizy-Pinon und Landricourt bergab in Richtung Landricourt.',
                'sources' => ['wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train a déraillé. La locomotive s\'est enfoncée dans le remblai et le reste du train l\'a heurtée et écrasée.',
                'en' => 'The train derailed. The engine sank into the embankment and the rest of the train ran into it and crushed it.',
                'de' => 'Der Zug entgleiste. Die Lokomotive grub sich in den Damm ein und wurde vom Rest des Zuges gerammt und zerdrückt.',
                'sources' => ['wp-liste-1890', 'ddb-1895-02-28-koeln'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués ; selon la liste de Wikipédia, ils sont morts pendant leur transport à l\'hôpital. Les voyageurs n\'ont eu que des contusions.',
                'en' => 'The driver and the stoker were killed; according to the Wikipedia list they died on the way to hospital. The passengers suffered only bruises.',
                'de' => 'Lokführer und Heizer kamen ums Leben; nach der Wikipedia-Liste starben sie auf dem Weg ins Krankenhaus. Die Reisenden erlitten nur Prellungen.',
                'sources' => ['wp-liste-1890', 'ddb-1895-02-28-koeln', 'ddb-1895-02-27-merkur'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La cause diffère : un affaissement du sol selon la liste de Wikipédia, une rupture de rail selon la dépêche de Paris.',
                'en' => 'The cause differs: subsidence according to the Wikipedia list, a broken rail according to the Paris dispatch.',
                'de' => 'Die Ursache weicht ab: eine Bodensenkung nach der Wikipedia-Liste, ein Schienenbruch nach der Pariser Depesche.',
                'sources' => ['wp-liste-1890', 'ddb-1895-02-28-koeln'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'ddb-1895-02-28-koeln', 'ddb-1895-02-27-merkur'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (La Presse du 26 février 1895) et la dépêche de Paris du 26 février ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (La Presse of 26 February 1895) and the Paris dispatch of 26 February; the record gave no number of deaths.',
            'de' => 'Lokführer und Heizer getötet nach der Wikipedia-Liste (La Presse vom 26. Februar 1895) und der Pariser Depesche vom 26. Februar; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
