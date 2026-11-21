<?php

// Contexte sourcé du déraillement entre Gagny et Chelles de mars 1868 (recherche du 21/11/2026).
return [
    'slug' => '1868-03-20-gagny',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1868',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1868',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, datée du 20 mars 1868, d\'après Le Figaro du 23 mars 1868 : sur la ligne de Paris à Strasbourg, entre les gares de Gagny et de Chelles, un train de voyageurs pour Meaux heurte un bloc de pierre tombé d\'un train de marchandises, déraille et se disloque contre les parois d\'une tranchée ; le mécanicien est tué, plusieurs personnes sont blessées, dont 4 grièvement.',
        ],
        'ddb-1868-03-24-augsburger-abendzeitung' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 21. März. Diesen Morgen hat sich auf der Ostbahn ein schwerer Unfall ereignet',
            'publisher' => 'Augsburger Abendzeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1868-03-24',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/LXL3LLNSGPVE7MTJN4APLIPCE5ZAYBXE?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche datée de Paris, 21 mars, reprise de l\'Allgemeine Zeitung : « ce matin », grave accident sur le chemin de fer de l\'Est, le train parti de Paris à 11 h déraille avant la station de Chelles ; on parle de 17 morts ou grands blessés.',
        ],
        'ddb-1868-03-26-donau-zeitung' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 21. März. Diesen Morgen hat sich auf der Ostbahn ein schwerer Unfall ereignet',
            'publisher' => 'Donau-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1868-03-26',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/UPJ5NIVEFADCCQAWDA2GXLPYMAT6VNII?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche de Paris du 21 mars : train parti de Paris à 11 h, déraillé avant la station de Chelles ; on parle de 17 morts ou grands blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'En mars 1868, sur la ligne de Paris à Strasbourg, un train de voyageurs parti de Paris vers Meaux déraille entre les gares de Gagny et de Chelles.',
                'en' => 'In March 1868, on the Paris to Strasbourg line, a passenger train from Paris to Meaux derailed between Gagny and Chelles stations.',
                'de' => 'Im März 1868 entgleiste auf der Strecke Paris-Straßburg zwischen den Bahnhöfen Gagny und Chelles ein Personenzug von Paris nach Meaux.',
                'sources' => ['wp-liste-1860', 'ddb-1868-03-24-augsburger-abendzeitung', 'ddb-1868-03-26-donau-zeitung'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le train heurte un bloc de pierre tombé d\'un train de marchandises, déraille et se disloque contre les parois d\'une tranchée.',
                'en' => 'According to the Wikipedia list, the train struck a block of stone that had fallen from a goods train, derailed and broke up against the sides of a cutting.',
                'de' => 'Nach der Wikipedia-Liste fuhr der Zug auf einen von einem Güterzug gefallenen Steinblock, entgleiste und zerbrach an den Wänden eines Einschnitts.',
                'sources' => ['wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte le mécanicien tué et plusieurs blessés, dont 4 grièvement. La première dépêche de Paris parle, sur la foi de rumeurs, de 17 morts ou grands blessés.',
                'en' => 'The Wikipedia list counts the driver killed and several injured, four of them seriously. The first dispatch from Paris speaks, on the strength of rumour, of 17 dead or seriously injured.',
                'de' => 'Die Wikipedia-Liste zählt den getöteten Lokomotivführer und mehrere Verletzte, davon vier schwer. Die erste Pariser Depesche spricht gerüchteweise von 17 Toten oder Schwerverletzten.',
                'sources' => ['wp-liste-1860', 'ddb-1868-03-24-augsburger-abendzeitung', 'ddb-1868-03-26-donau-zeitung'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La date diffère : 20 mars selon la liste de Wikipédia, matin du 21 mars selon la dépêche de Paris datée de ce jour. Le bilan aussi : un mort et quelques blessés graves dans la liste, « 17 morts ou grands blessés » dans la dépêche.',
                'en' => 'The date differs: 20 March according to the Wikipedia list, the morning of 21 March according to the Paris dispatch dated that day. So does the toll: one dead and a few seriously injured in the list, "17 dead or seriously injured" in the dispatch.',
                'de' => 'Das Datum weicht ab: 20. März nach der Wikipedia-Liste, Morgen des 21. März nach der an diesem Tag datierten Pariser Depesche. Ebenso die Bilanz: ein Toter und einige Schwerverletzte in der Liste, „17 Tote oder schwer Verwundete“ in der Depesche.',
                'sources' => ['wp-liste-1860', 'ddb-1868-03-24-augsburger-abendzeitung'],
            ],
        ],
    ],
];
