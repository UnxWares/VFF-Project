<?php

// Contexte sourcé du déraillement de l'express Paris - Toulouse à l'entrée de la gare des Aubrais, ligne de Paris à Bordeaux, de juillet 1902 (recherche du 21/11/2026).
return [
    'slug' => '1902-07-20-fleury-les-aubrais',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1902' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1902',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1902&oldid=233590581#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 20 juillet 1902, d\'après Le Figaro, Le Petit Journal et Le Temps du 21 juillet : vers 23 h, à l\'entrée de la gare des Aubrais, l\'express Paris - Toulouse déraille sur un aiguillage, sans doute abordé trop vite vu l\'état de la voie ; la locomotive et sept voitures quittent les rails ; le chauffeur est tué, le mécanicien et huit voyageurs blessés.',
        ],
        'ddb-1902-07-23-halle' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Ein Eisenbahnunglück',
            'publisher' => 'Hallesche Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-07-23',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/5TB77IFWKMVGSAG2GEXP5JCJHAGAP2BC?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : la nuit, sur la ligne Paris - Toulouse, à l\'entrée de l\'express en gare des Aubrais, la locomotive, le tender et sept voitures ont déraillé après la rupture d\'une pièce de la machine ; le chauffeur tué, sept personnes grièvement blessées, dont une femme morte à l\'hôpital d\'Orléans (même texte dans le Merseburger Korrespondent du 24 juillet).',
        ],
        'ddb-1902-07-23-hamburg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Toulouser Schnellzug',
            'publisher' => 'Hamburgischer Correspondent (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-07-23',
            'locator' => 'p. 14',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/QHH2LJGOVPCTHDXFXG3R2IMO6TLI6STL?issuepage=14',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : lors du déraillement de l\'express de Toulouse aux Aubrais, près d\'Orléans, dimanche, trois personnes ont perdu la vie, quatre ont été grièvement et six légèrement blessées.',
        ],
        'ddb-1902-07-23-badische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 21. Juli',
            'publisher' => 'Badische Presse (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-07-23',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/24TYYJ5NRMWUAKP4EUP5ZMMCX5YADNHI?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 21 juillet : près de la station des Aubrais, avant Orléans, un express a déraillé, plusieurs voitures se sont renversées les unes sur les autres ; un mécanicien tué, huit personnes grièvement blessées, une jeune femme mourante à l\'hôpital d\'Orléans.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le dimanche 20 juillet 1902, vers 23 h, l\'express de Paris à Toulouse a déraillé sur un aiguillage à l\'entrée de la gare des Aubrais, près d\'Orléans. La locomotive, le tender et sept voitures ont quitté les rails, plusieurs voitures se renversant les unes sur les autres.',
                'en' => 'On Sunday 20 July 1902, at about 11 pm, the Paris to Toulouse express derailed on points at the entrance to Les Aubrais station, near Orléans. The engine, the tender and seven coaches left the rails, several coaches piling on top of each other.',
                'de' => 'Am Sonntag, dem 20. Juli 1902, gegen 23 Uhr entgleiste der Schnellzug Paris-Toulouse an einer Weiche bei der Einfahrt in den Bahnhof Les Aubrais bei Orléans. Lokomotive, Tender und sieben Wagen sprangen aus den Schienen, mehrere Wagen stürzten übereinander.',
                'sources' => ['wp-liste-1902', 'ddb-1902-07-23-halle', 'ddb-1902-07-23-badische'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur a été tué. Le mécanicien et huit voyageurs ont été blessés selon la liste de Wikipédia. Selon la Hallesche Zeitung, une des voyageuses grièvement blessées est morte à l\'hôpital d\'Orléans.',
                'en' => 'The stoker was killed. The driver and eight passengers were injured according to the Wikipedia list. According to the Hallesche Zeitung, one of the seriously injured women died in hospital in Orléans.',
                'de' => 'Der Heizer wurde getötet. Nach der Wikipedia-Liste wurden der Lokführer und acht Reisende verletzt. Nach der Halleschen Zeitung starb eine der schwer verletzten Frauen im Krankenhaus von Orléans.',
                'sources' => ['wp-liste-1902', 'ddb-1902-07-23-halle'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La liste de Wikipédia avance une vitesse sans doute excessive sur l\'aiguillage vu l\'état de la voie ; la Hallesche Zeitung parle de la rupture d\'une pièce de la machine.',
                'en' => 'The Wikipedia list suggests the points were probably taken too fast given the state of the track; the Hallesche Zeitung speaks of a broken engine part.',
                'de' => 'Die Wikipedia-Liste nennt eine angesichts des Gleiszustands wohl zu hohe Geschwindigkeit an der Weiche; die Hallesche Zeitung spricht vom Bruch eines Maschinenteils.',
                'sources' => ['wp-liste-1902', 'ddb-1902-07-23-halle'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie : un mort dans la liste de Wikipédia, deux dans la Hallesche Zeitung (le chauffeur et une voyageuse morte à l\'hôpital), trois dans le Hamburgischer Correspondent. La Badische Presse dit le mécanicien tué. Le nombre de blessés graves va de quatre à huit selon les dépêches.',
                'en' => 'The toll varies: one dead in the Wikipedia list, two in the Hallesche Zeitung (the stoker and a woman passenger who died in hospital), three in the Hamburgischer Correspondent. The Badische Presse says the driver was killed. The number seriously injured ranges from four to eight across the dispatches.',
                'de' => 'Die Bilanz schwankt: ein Toter in der Wikipedia-Liste, zwei in der Halleschen Zeitung (der Heizer und eine im Krankenhaus gestorbene Reisende), drei im Hamburgischen Correspondenten. Die Badische Presse nennt den Lokführer tot. Die Zahl der Schwerverletzten liegt je nach Meldung zwischen vier und acht.',
                'sources' => ['wp-liste-1902', 'ddb-1902-07-23-halle', 'ddb-1902-07-23-hamburg', 'ddb-1902-07-23-badische'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1902'],
            'fr' => 'Le chauffeur tué selon la liste de Wikipédia ; la presse allemande parle aussi d\'une voyageuse morte à l\'hôpital et compte jusqu\'à trois morts ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'The stoker killed according to the Wikipedia list; the German press also speaks of a woman passenger who died in hospital and counts up to three dead; the record gave no number of deaths.',
            'de' => 'Der Heizer getötet nach der Wikipedia-Liste; die deutsche Presse spricht auch von einer im Krankenhaus gestorbenen Reisenden und zählt bis zu drei Tote; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
