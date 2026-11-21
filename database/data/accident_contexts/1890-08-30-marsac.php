<?php

// Contexte sourcé du déraillement d'un train mixte après la gare de Marsac, ligne de Montluçon à Saint-Sulpice-Laurière, d'août 1890 (recherche du 21/11/2026).
return [
    'slug' => '1890-08-30-marsac',
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
            'locator' => '1890',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1890',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 30 août 1890, d\'après la presse du 1er septembre : à 19 h, sur la ligne de Montluçon à Saint-Sulpice-Laurière, après la gare de Marsac, un train mixte déraille ; un conducteur et un militaire qui convoyait des chevaux sont tués.',
        ],
        'ddb-1890-09-02-koelnische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 1. Sept. (Telegr.)',
            'publisher' => 'Kölnische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1890-09-02',
            'locator' => 'p. 1',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/5SS2K4I77WSWY46FTZPLOCU4N2D6R6KB?issuepage=1',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme de Paris du 1er septembre, d\'après une dépêche de Guéret : hier, un train allant de Saint-Sulpice-Laurière à Montluçon a déraillé ; un voyageur tué, un autre mortellement blessé ; 17 wagons de marchandises chargés entièrement brisés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 30 août 1890, vers 19 h, un train mixte de Saint-Sulpice-Laurière à Montluçon a déraillé après la gare de Marsac. Dix-sept wagons de marchandises chargés ont été entièrement brisés.',
                'en' => 'On 30 August 1890, at about 7 pm, a mixed train from Saint-Sulpice-Laurière to Montluçon derailed after Marsac station. Seventeen loaded goods wagons were completely smashed.',
                'de' => 'Am 30. August 1890 gegen 19 Uhr entgleiste ein gemischter Zug von Saint-Sulpice-Laurière nach Montluçon hinter dem Bahnhof Marsac. Siebzehn beladene Güterwagen wurden völlig zertrümmert.',
                'sources' => ['wp-liste-1890', 'ddb-1890-09-02-koelnische'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux hommes ont été tués : un conducteur et un militaire qui convoyait des chevaux selon la liste de Wikipédia. La dépêche de Guéret parle d\'un voyageur tué et d\'un autre mortellement blessé.',
                'en' => 'Two men were killed: a conductor and a soldier escorting horses according to the Wikipedia list. The Guéret dispatch speaks of one passenger killed and another fatally injured.',
                'de' => 'Zwei Männer wurden getötet: nach der Wikipedia-Liste ein Zugbegleiter und ein Soldat, der Pferde begleitete. Die Depesche aus Guéret spricht von einem getöteten und einem tödlich verletzten Reisenden.',
                'sources' => ['wp-liste-1890', 'ddb-1890-09-02-koelnische'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'ddb-1890-09-02-koelnische'],
            'fr' => 'Deux morts selon la liste de Wikipédia (presse du 1er septembre 1890) ; un tué et un blessé mortellement selon la dépêche de Guéret ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two dead according to the Wikipedia list (press of 1 September 1890); one killed and one fatally injured according to the Guéret dispatch; the record gave no number of deaths.',
            'de' => 'Zwei Tote nach der Wikipedia-Liste (Presse vom 1. September 1890); ein Getöteter und ein tödlich Verletzter nach der Depesche aus Guéret; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
