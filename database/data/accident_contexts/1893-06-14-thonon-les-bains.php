<?php

// Contexte sourcé du déraillement d'un train de voyageurs entre Perrignier et Thonon-les-Bains, ligne de Bellegarde à Évian, de juin 1893 (recherche du 21/11/2026).
return [
    'slug' => '1893-06-14-thonon-les-bains',
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
            'locator' => '1893',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1893',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 14 juin 1893, d\'après Le Matin du 15 juin : sur la ligne de Bellegarde à Évian, vers 8 h, un train de voyageurs assurant à Bellegarde la correspondance du train de Paris déraille dans la descente entre les gares de Perrignier et de Thonon-les-Bains ; le chauffeur est tué, le mécanicien, le chef de train et quelques voyageurs blessés.',
        ],
        'ddb-1893-06-14-correspondent' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, den 14. Juni. (R. T.)',
            'publisher' => 'Hamburgischer Correspondent (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1893-06-14',
            'locator' => 'p. 1',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/YZ3MM5UKGVLAX7GOQGHMLACXLCJ2S5L7?issuepage=1',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Première dépêche de Paris du 14 juin, d\'après une nouvelle de Thonon : ce matin, un train allant de Bellegarde à Thonon a déraillé à trois kilomètres de Perrignier ; plusieurs personnes tuées et un certain nombre blessées.',
        ],
        'ddb-1893-06-15-echo' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 14. Juni. (R. T.)',
            'publisher' => 'Hamburger Echo (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1893-06-15',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/SINWFF56NPTUNHPPORFFSHJ4HH3GLTB6?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même première dépêche, suivie d\'une seconde dépêche de Paris du 14 juin : lors de l\'accident de Thonon, comme on l\'a maintenant établi, un agent a été tué et deux agents blessés ; plusieurs voyageurs légèrement blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 14 juin 1893, vers 8 h, un train de voyageurs de Bellegarde à Thonon, en correspondance à Bellegarde avec le train de Paris, a déraillé dans la descente entre les gares de Perrignier et de Thonon-les-Bains, à trois kilomètres de Perrignier.',
                'en' => 'On 14 June 1893, at about 8 am, a Bellegarde to Thonon passenger train, connecting at Bellegarde with the train from Paris, derailed on the descent between Perrignier and Thonon-les-Bains stations, three kilometres from Perrignier.',
                'de' => 'Am 14. Juni 1893 gegen 8 Uhr entgleiste ein Personenzug von Bellegarde nach Thonon, der in Bellegarde Anschluss an den Zug aus Paris hatte, im Gefälle zwischen den Bahnhöfen Perrignier und Thonon-les-Bains, drei Kilometer von Perrignier.',
                'sources' => ['wp-liste-1890', 'ddb-1893-06-14-correspondent', 'ddb-1893-06-15-echo'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent a été tué, le chauffeur selon la liste de Wikipédia. Deux agents, le mécanicien et le chef de train selon la liste, ont été blessés, ainsi que plusieurs voyageurs, légèrement.',
                'en' => 'One railwayman was killed, the stoker according to the Wikipedia list. Two railwaymen, the driver and the guard according to the list, were injured, as were several passengers, slightly.',
                'de' => 'Ein Bediensteter wurde getötet, nach der Wikipedia-Liste der Heizer. Zwei Bedienstete, nach der Liste der Lokführer und der Zugführer, wurden verletzt, dazu mehrere Reisende leicht.',
                'sources' => ['ddb-1893-06-15-echo', 'wp-liste-1890'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La première dépêche de Paris parlait de plusieurs morts ; une seconde dépêche du même jour a ramené le bilan à un agent tué.',
                'en' => 'The first Paris dispatch spoke of several dead; a second dispatch the same day brought the toll down to one railwayman killed.',
                'de' => 'Die erste Pariser Depesche sprach von mehreren Toten; eine zweite Depesche vom selben Tag nannte einen getöteten Bediensteten.',
                'sources' => ['ddb-1893-06-14-correspondent', 'ddb-1893-06-15-echo'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1893-06-15-echo'],
            'fr' => 'Un agent tué (le chauffeur) selon la liste de Wikipédia (Le Matin du 15 juin 1893) et la seconde dépêche de Paris du 14 juin ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One railwayman killed (the stoker) according to the Wikipedia list (Le Matin of 15 June 1893) and the second Paris dispatch of 14 June; the record gave no number of deaths.',
            'de' => 'Ein Bediensteter getötet (der Heizer) nach der Wikipedia-Liste (Le Matin vom 15. Juni 1893) und der zweiten Pariser Depesche vom 14. Juni; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
