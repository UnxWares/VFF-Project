<?php

// Contexte sourcé du déraillement d'un train à la halte de Bures-sur-Dives, ligne de Caen à Trouville, d'août 1889 (recherche du 21/11/2026).
return [
    'slug' => '1889-08-11-troarn',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1889',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1889',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 11 août 1889, d\'après Le Figaro du 12 août : sur la ligne de Caen à Trouville, vers 8 h 30, juste après Troarn, un train déraille à la halte de Bures-sur-Dives ; le chef de train et la femme du garde-barrière sont tués, le mécanicien, le chauffeur et 4 des 20 voyageurs blessés.',
        ],
        'ddb-1889-08-16-solingen' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Aus Paris wird gemeldet',
            'publisher' => 'Solinger Kreis-Intelligenzblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1889-08-16',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/LOOP5DHNIA2NVOZQDLDD5SFGPXGOBOXA?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Nouvelle de Paris : un train de l\'Ouest allant de Caen à Dozulé a déraillé près de la gare de cette dernière ville ; deux agents tués, trois autres agents et trois voyageurs blessés.',
        ],
        'ddb-1889-08-15-aachener' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 12. August. (Erdstoß. Eisenbahnunfall.)',
            'publisher' => 'Aachener Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1889-08-15',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/YZMIIAH7TGZJB6R4E76STSCAAVKOP7FR?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche de Paris du 12 août : la veille, le train no 101 de l\'Ouest, allant de Caen à Dozulé, a déraillé près de la gare de cette dernière ville ; deux agents tués, trois autres agents et trois voyageurs blessés, dont un très grièvement.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 11 août 1889, vers 8 h 30, un train de la Compagnie de l\'Ouest allant de Caen vers Dozulé et Trouville a déraillé juste après Troarn, à la halte de Bures-sur-Dives.',
                'en' => 'On 11 August 1889, at about 8.30 am, an Ouest company train running from Caen towards Dozulé and Trouville derailed just after Troarn, at the Bures-sur-Dives halt.',
                'de' => 'Am 11. August 1889 gegen 8.30 Uhr entgleiste ein Zug der Westbahn von Caen in Richtung Dozulé und Trouville kurz hinter Troarn am Haltepunkt Bures-sur-Dives.',
                'sources' => ['wp-liste-1880', 'ddb-1889-08-16-solingen'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux personnes ont été tuées et six blessées. Selon la liste de Wikipédia, les morts sont le chef de train et la femme du garde-barrière, les blessés le mécanicien, le chauffeur et quatre des vingt voyageurs.',
                'en' => 'Two people were killed and six injured. According to the Wikipedia list the dead were the guard and the crossing keeper\'s wife, the injured the driver, the stoker and four of the twenty passengers.',
                'de' => 'Zwei Menschen wurden getötet und sechs verletzt. Nach der Wikipedia-Liste waren die Toten der Zugführer und die Frau des Schrankenwärters, die Verletzten Lokführer, Heizer und vier der zwanzig Reisenden.',
                'sources' => ['wp-liste-1880', 'ddb-1889-08-16-solingen'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La dépêche de Paris du 12 août place le déraillement du train no 101, qui allait de Caen à Dozulé, près de la gare de Dozulé, et compte deux agents tués, trois agents et trois voyageurs blessés. La liste de Wikipédia le place à la halte de Bures-sur-Dives, juste après Troarn ; Bures-sur-Dives est aujourd\'hui une commune associée de Troarn.',
                'en' => 'The Paris dispatch of 12 August places the derailment of train no. 101, running from Caen to Dozulé, near Dozulé station, and counts two railwaymen killed, three railwaymen and three passengers injured. The Wikipedia list places it at the Bures-sur-Dives halt, just after Troarn; Bures-sur-Dives is now an associated commune of Troarn.',
                'de' => 'Die Pariser Depesche vom 12. August verlegt die Entgleisung des Zuges Nr. 101 von Caen nach Dozulé in die Nähe des Bahnhofs Dozulé und zählt zwei getötete Bahnbedienstete sowie drei verletzte Bedienstete und drei verletzte Reisende. Die Wikipedia-Liste verlegt sie an den Haltepunkt Bures-sur-Dives kurz hinter Troarn; Bures-sur-Dives ist heute eine assoziierte Gemeinde von Troarn.',
                'sources' => ['ddb-1889-08-16-solingen', 'ddb-1889-08-15-aachener', 'wp-liste-1880'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1880', 'ddb-1889-08-16-solingen'],
            'fr' => 'Deux morts selon la liste de Wikipédia (Le Figaro du 12 août 1889) et la presse allemande, qui ne nomment pas les mêmes victimes ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two dead according to the Wikipedia list (Le Figaro of 12 August 1889) and the German press, which do not name the same victims; the record gave no number of deaths.',
            'de' => 'Zwei Tote nach der Wikipedia-Liste (Le Figaro vom 12. August 1889) und der deutschen Presse, die nicht dieselben Opfer nennen; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 6,
            'sources' => ['wp-liste-1880', 'ddb-1889-08-16-solingen'],
            'fr' => 'Six blessés selon la liste de Wikipédia et la presse allemande ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Six injured according to the Wikipedia list and the German press; the record gave no number of injured.',
            'de' => 'Sechs Verletzte nach der Wikipedia-Liste und der deutschen Presse; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
