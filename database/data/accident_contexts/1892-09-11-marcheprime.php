<?php

// Contexte sourcé de la collision de deux trains de marchandises en gare de Facture, ligne de Bordeaux à Dax, de septembre 1892 (recherche du 21/11/2026).
return [
    'slug' => '1892-09-11-marcheprime',
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
            'locator' => '1892',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1892',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 11 septembre 1892, d\'après Le Temps du 12 septembre : près de Facture, sur la ligne de Bordeaux à Dax, deux trains de marchandises se percutent de nuit ; un mécanicien et un chauffeur sont tués et restent longtemps coincés sous les décombres, la machine du train de secours ayant elle-même déraillé à Marcheprime.',
        ],
        'ddb-1892-09-14-dresden' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Der gestrige Tag hat mehrere schwere Eisenbahn-Unfälle gebracht',
            'publisher' => 'Dresdner Journal (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1892-09-14',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/3WODU2FI3XOFFJXTU44CP6CCFEXTYOJT?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Correspondance de Paris (même texte dans le Schwäbischer Merkur du 14 septembre) : en gare de Facture, à 37 km de Bordeaux, un train de marchandises a heurté un autre train de marchandises arrêté ; le chauffeur et le chef de train tués, le mécanicien gravement brûlé par la vapeur ; la cause serait une mauvaise position des signaux.',
        ],
        'ddb-1892-09-14-merkur' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris 12. Sept.',
            'publisher' => 'Schwäbischer Merkur (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1892-09-14',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/73EBPJQ6UIUBIRG7XEMKDO52BR7ZWJKM?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Correspondance de Paris du 12 septembre : même récit de la collision en gare de Facture, chauffeur et chef de train tués, mécanicien gravement brûlé, signal mal placé.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Dans la nuit, en gare de Facture, à 37 km de Bordeaux sur la ligne de Bordeaux à Dax, un train de marchandises a heurté un autre train de marchandises arrêté. La presse allemande attribue l\'accident à une mauvaise position des signaux.',
                'en' => 'During the night, at Facture station, 37 km from Bordeaux on the Bordeaux to Dax line, a goods train ran into another goods train that was standing there. The German press blames a wrongly set signal.',
                'de' => 'In der Nacht fuhr im Bahnhof Facture, 37 km von Bordeaux an der Strecke Bordeaux-Dax, ein Güterzug auf einen dort haltenden Güterzug auf. Die deutsche Presse führt den Unfall auf eine falsche Signalstellung zurück.',
                'sources' => ['wp-liste-1890', 'ddb-1892-09-14-dresden', 'ddb-1892-09-14-merkur'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux agents ont été tués : un mécanicien et un chauffeur selon la liste de Wikipédia, le chauffeur et le chef de train selon la presse allemande, qui ajoute le mécanicien gravement brûlé par la vapeur. Les corps sont restés longtemps sous les décombres.',
                'en' => 'Two railwaymen were killed: a driver and a stoker according to the Wikipedia list, the stoker and the guard according to the German press, which adds that the driver was badly scalded. The bodies stayed under the wreckage for a long time.',
                'de' => 'Zwei Bedienstete wurden getötet: nach der Wikipedia-Liste ein Lokführer und ein Heizer, nach der deutschen Presse der Heizer und der Zugführer; der Lokführer sei schwer verbrüht worden. Die Toten blieben lange unter den Trümmern.',
                'sources' => ['wp-liste-1890', 'ddb-1892-09-14-dresden', 'ddb-1892-09-14-merkur'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'La machine du train de secours a elle-même déraillé à Marcheprime, ce qui a retardé les secours.',
                'en' => 'The engine of the relief train itself derailed at Marcheprime, which delayed the rescue.',
                'de' => 'Die Lokomotive des Hilfszugs entgleiste ihrerseits in Marcheprime, was die Hilfe verzögerte.',
                'sources' => ['wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'ddb-1892-09-14-dresden', 'ddb-1892-09-14-merkur'],
            'fr' => 'Deux agents tués selon la liste de Wikipédia (Le Temps du 12 septembre 1892) et la correspondance de Paris, qui ne nomment pas les mêmes ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two railwaymen killed according to the Wikipedia list (Le Temps of 12 September 1892) and the Paris report, which do not name the same men; the record gave no number of deaths.',
            'de' => 'Zwei Bedienstete getötet nach der Wikipedia-Liste (Le Temps vom 12. September 1892) und dem Pariser Bericht, die nicht dieselben nennen; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1890', 'ddb-1892-09-14-dresden'],
            'fr' => 'Collision de deux trains de marchandises selon la liste de Wikipédia et la presse allemande ; la fiche la classait en déraillement, sans doute d\'après celui du train de secours.',
            'en' => 'Collision between two goods trains according to the Wikipedia list and the German press; the record classed it as a derailment, probably after that of the relief train.',
            'de' => 'Zusammenstoß zweier Güterzüge nach der Wikipedia-Liste und der deutschen Presse; der Eintrag führte ihn als Entgleisung, wohl nach der des Hilfszugs.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '33051',
            'sources' => ['wp-liste-1890', 'ddb-1892-09-14-dresden'],
            'fr' => 'Collision en gare de Facture (commune de Biganos) selon la liste de Wikipédia et la presse allemande ; la fiche la plaçait à Marcheprime, où seul le train de secours a déraillé.',
            'en' => 'Collision at Facture station (Biganos commune) according to the Wikipedia list and the German press; the record placed it at Marcheprime, where only the relief train derailed.',
            'de' => 'Zusammenstoß im Bahnhof Facture (Gemeinde Biganos) nach der Wikipedia-Liste und der deutschen Presse; der Eintrag verortete ihn in Marcheprime, wo nur der Hilfszug entgleiste.',
        ],
    ],
];
