<?php

// Contexte sourcé du déraillement d'un train de marchandises Nancy - Longuyon près de Mars-la-Tour, de juillet 1897 (recherche du 21/11/2026).
return [
    'slug' => '1897-07-08-mars-la-tour',
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
            'locator' => '1897',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1897',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 8 juillet 1897, d\'après Le Figaro du 10 juillet : sur la ligne de Lille à Thionville, près de Mars-la-Tour, un train de marchandises Nancy - Longuyon déraille ; la locomotive et le tender tombent dans un fossé ; le mécanicien et le chauffeur sont tués, le chef de train grièvement blessé.',
        ],
        'ddb-1897-07-12-merkur' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Aus Nancy wird gemeldet',
            'publisher' => 'Schwäbischer Merkur (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1897-07-12',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/5JL6SKIWRQ72SVQVJZUJHOCPYG4KDOJN?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Nouvelle de Nancy (même texte dans le Filder-Bote du 12 et la Schwäbische Tagwacht du 13 juillet) : un train de marchandises allant de Nancy à Longuyon a déraillé près de Mars-la-Tour ; la locomotive et le tender ont dévalé un talus ; le mécanicien et le chauffeur tués, le chef de train blessé en danger de mort ; la cause semble être une rupture d\'essieu.',
        ],
        'ddb-1897-07-13-tagwacht' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Aus Nancy wird gemeldet',
            'publisher' => 'Schwäbische Tagwacht (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1897-07-13',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/35AJORXTFZGCCIPQ37ATY2YWBDVCXNPG?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle de Nancy : déraillement près de Mars-la-Tour, mécanicien et chauffeur tués, chef de train grièvement blessé, rupture d\'essieu probable.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 8 juillet 1897, un train de marchandises allant de Nancy à Longuyon a déraillé près de Mars-la-Tour. La locomotive et le tender ont dévalé le talus et sont tombés dans un fossé.',
                'en' => 'On 8 July 1897 a goods train running from Nancy to Longuyon derailed near Mars-la-Tour. The engine and tender ran down the embankment into a ditch.',
                'de' => 'Am 8. Juli 1897 entgleiste ein Güterzug von Nancy nach Longuyon bei Mars-la-Tour. Lokomotive und Tender stürzten die Böschung hinab in einen Graben.',
                'sources' => ['wp-liste-1890', 'ddb-1897-07-12-merkur'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la presse allemande, la cause semblait être une rupture d\'essieu.',
                'en' => 'According to the German press the cause appeared to be a broken axle.',
                'de' => 'Nach der deutschen Presse schien ein Achsbruch die Ursache zu sein.',
                'sources' => ['ddb-1897-07-12-merkur', 'ddb-1897-07-13-tagwacht'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués. Le chef de train a été grièvement blessé.',
                'en' => 'The driver and the stoker were killed. The guard was seriously injured.',
                'de' => 'Lokführer und Heizer wurden getötet. Der Zugführer wurde lebensgefährlich verletzt.',
                'sources' => ['wp-liste-1890', 'ddb-1897-07-12-merkur', 'ddb-1897-07-13-tagwacht'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1890', 'ddb-1897-07-12-merkur', 'ddb-1897-07-13-tagwacht'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Figaro du 10 juillet 1897) et la nouvelle de Nancy ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Figaro of 10 July 1897) and the Nancy report; the record gave no number of deaths.',
            'de' => 'Lokführer und Heizer getötet nach der Wikipedia-Liste (Le Figaro vom 10. Juli 1897) und der Meldung aus Nancy; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1897-07-12-merkur'],
            'fr' => 'Chef de train grièvement blessé selon la liste de Wikipédia et la nouvelle de Nancy ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Guard seriously injured according to the Wikipedia list and the Nancy report; the record gave no number of injured.',
            'de' => 'Zugführer schwer verletzt nach der Wikipedia-Liste und der Meldung aus Nancy; der Eintrag nannte keine Zahl von Verletzten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '54353',
            'sources' => ['wp-liste-1890', 'ddb-1897-07-12-merkur', 'ddb-1897-07-13-tagwacht'],
            'fr' => 'Déraillement près de Mars-la-Tour selon la liste de Wikipédia et la presse allemande ; la fiche le plaçait à Nancy, d\'où venait le train.',
            'en' => 'Derailment near Mars-la-Tour according to the Wikipedia list and the German press; the record placed it at Nancy, where the train came from.',
            'de' => 'Entgleisung bei Mars-la-Tour nach der Wikipedia-Liste und der deutschen Presse; der Eintrag verortete sie in Nancy, woher der Zug kam.',
        ],
    ],
];
