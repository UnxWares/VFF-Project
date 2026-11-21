<?php

// Contexte sourcé de la collision de Roquebrune - Monte-Carlo du 10 mars 1886 (recherche du 21/11/2026).
return [
    'slug' => '1886-03-10-roquebrune-cap-martin',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '10 mars 1886, Roquebrune',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1886',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Donne l\'erreur du chef de gare de Roquebrune, une collision vers 16 h 45, trois morts et 23 blessés ; cite Le Petit Parisien du 13 mars 1886.',
        ],
        'vdr-monte-carlo' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1886, Deux trains se fracassent l\'un contre l\'autre dans un face à face mortel à Monte-Carlo',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1886-deux-trains-se-fracassent-lun-contre-lautre-dans-un-face-a-face-mortel-a-monaco/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit le Courrier de Saône-et-Loire du 13 mars 1886, p. 2 (dépêches du 11 mars et note de la Compagnie P.-L.-M.).',
        ],
        'petit-marseillais-1886' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Marseillais (liste des blessés)',
            'publisher' => 'Le Petit Marseillais',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k4901385d/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 10 mars 1886, le train 483, parti de Nice à 15 h 50, et le train 502, venant de Menton, se sont rencontrés sur la voie unique, entre Cabbé-Roquebrune et Monte-Carlo, à deux kilomètres de cette dernière station, dans un tournant où le rocher laisse place au vide au-dessus de la mer. Selon le Courrier de Saône-et-Loire, la rencontre a eu lieu vers huit heures du soir ; la liste de Wikipédia donne 16 h 45.',
                'en' => 'On 10 March 1886, train 483, which left Nice at 3.50 pm, and train 502, coming from Menton, met on the single track between Cabbé-Roquebrune and Monte-Carlo, two kilometres from the latter station, on a bend where the rock gives way to a drop over the sea. According to the Courrier de Saône-et-Loire, the meeting took place at about eight in the evening; the Wikipedia list gives 4.45 pm.',
                'de' => 'Am 10. März 1886 trafen der um 15.50 Uhr in Nizza abgefahrene Zug 483 und der aus Menton kommende Zug 502 auf dem eingleisigen Abschnitt zwischen Cabbé-Roquebrune und Monte-Carlo, zwei Kilometer von letzterem Bahnhof entfernt, in einer Kurve zusammen, in der der Fels in einen Abgrund über dem Meer übergeht. Laut dem Courrier de Saône-et-Loire geschah dies gegen acht Uhr abends; die Wikipedia-Liste nennt 16.45 Uhr.',
                'sources' => ['vdr-monte-carlo', 'wp-liste-1880'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les locomotives sont entrées l\'une dans l\'autre et les wagons se sont écrasés les uns sur les autres. Trois wagons ont franchi le parapet et sont tombés dans la mer d\'une hauteur de soixante-quinze mètres selon la dépêche ; la liste de Wikipédia parle de dix voitures télescopées dont quatre tombées au pied de la corniche. Le mécanicien et le chauffeur du train de Nice ont sauté et sont saufs, ceux du train de Vintimille sont restés près d\'une heure sous la machine renversée.',
                'en' => 'The locomotives drove into one another and the carriages crushed against each other. Three carriages went over the parapet and fell into the sea from a height of seventy-five metres according to the dispatch; the Wikipedia list speaks of ten carriages telescoped, four of which fell to the foot of the cliff road. The driver and fireman of the Nice train jumped and were unhurt; those of the Ventimiglia train stayed trapped for nearly an hour under the overturned engine.',
                'de' => 'Die Lokomotiven fuhren ineinander, und die Wagen wurden übereinandergeschoben. Drei Wagen durchbrachen die Brüstung und stürzten laut der Depesche aus fünfundsiebzig Metern Höhe ins Meer; die Wikipedia-Liste spricht von zehn ineinandergeschobenen Wagen, von denen vier am Fuß der Küstenstraße landeten. Lokführer und Heizer des Zuges aus Nizza sprangen ab und blieben unversehrt, die des Zuges nach Ventimiglia lagen fast eine Stunde unter der umgestürzten Lokomotive eingeklemmt.',
                'sources' => ['vdr-monte-carlo', 'wp-liste-1880'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une première note de la Compagnie P.-L.-M. du 11 mars comptait un voyageur et un mécanicien tués, un conducteur disparu et une vingtaine de blessés plus ou moins graves. Les dernières dépêches du journal annoncent sept morts et douze blessés graves. Les blessés ont été installés dans l\'annexe d\'un hôtel de Monte-Carlo transformée en ambulance.',
                'en' => 'A first note from the P.-L.-M. company of 11 March counted one passenger and one driver killed, one guard missing and about twenty injured to varying degrees. The newspaper\'s last dispatches announce seven dead and twelve seriously injured. The injured were placed in the annexe of a Monte-Carlo hotel turned into a temporary hospital.',
                'de' => 'Eine erste Mitteilung der P.-L.-M.-Gesellschaft vom 11. März zählte einen getöteten Reisenden und einen getöteten Lokführer, einen vermissten Schaffner und etwa zwanzig mehr oder weniger schwer Verletzte. Die letzten Depeschen der Zeitung melden sieben Tote und zwölf Schwerverletzte. Die Verletzten wurden in der zum Behelfslazarett umgewandelten Dependance eines Hotels in Monte-Carlo untergebracht.',
                'sources' => ['vdr-monte-carlo'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les deux trains devaient se croiser à Cabbé-Roquebrune. Le journal impute l\'accident à un faux signal du chef de gare de Roquebrune, qui, ignorant sans doute que le train venant de Nice avait été dédoublé, aurait laissé partir le train pour Nice ; la liste de Wikipédia retient aussi cette erreur. La dépêche indique que l\'enquête devait établir les responsabilités.',
                'en' => 'The two trains were due to cross at Cabbé-Roquebrune. The newspaper blames the accident on a wrong signal from the Roquebrune station master, who, probably unaware that the train from Nice had been split in two, let the train for Nice depart; the Wikipedia list also retains this error. The dispatch says the inquiry was to establish responsibility.',
                'de' => 'Die beiden Züge sollten sich in Cabbé-Roquebrune kreuzen. Die Zeitung führt das Unglück auf ein falsches Signal des Bahnhofsvorstehers von Roquebrune zurück, der wohl nicht wusste, dass der Zug aus Nizza geteilt worden war, und den Zug nach Nizza abfahren ließ; auch die Wikipedia-Liste nennt diesen Fehler. Die Depesche besagt, dass die Untersuchung die Verantwortung klären sollte.',
                'sources' => ['vdr-monte-carlo', 'wp-liste-1880'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de morts varie : trois dans la liste de Wikipédia et dans la fiche, sept dans les dernières dépêches, deux dans la première note de la Compagnie. Les blessés sont 23 selon la liste, une vingtaine selon la Compagnie. L\'heure est 16 h 45 selon la liste et vers huit heures du soir selon la dépêche.',
                'en' => 'The number of dead varies: three in the Wikipedia list and the record, seven in the last dispatches, two in the company\'s first note. The injured number 23 according to the list, about twenty according to the company. The time is 4.45 pm according to the list and about eight in the evening according to the dispatch.',
                'de' => 'Die Zahl der Toten schwankt: drei in der Wikipedia-Liste und im Eintrag, sieben in den letzten Depeschen, zwei in der ersten Mitteilung der Gesellschaft. Die Verletzten betragen laut der Liste 23, laut der Gesellschaft etwa zwanzig. Die Uhrzeit ist laut der Liste 16.45 Uhr und laut der Depesche gegen acht Uhr abends.',
                'sources' => ['vdr-monte-carlo', 'wp-liste-1880'],
            ],
        ],
    ],
    'corrections' => [],
];
