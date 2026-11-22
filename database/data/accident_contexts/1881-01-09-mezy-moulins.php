<?php

// Contexte sourcé de la collision d'un train venant des Ardennes et d'un train de marchandises en gare de Mézy, ligne de Paris à Strasbourg, de janvier 1881 (recherche du 21/11/2026).
return [
    'slug' => '1881-01-09-mezy-moulins',
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
            'locator' => '1881',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1881',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 9 janvier 1881 : sur la ligne de Paris à Strasbourg, vers 16 h, en gare de Mézy, un train de marchandises en manœuvre est percuté par un train direct venant d\'Épernay ; le chauffeur et le chef de train sont tués, le mécanicien blessé, ainsi qu\'une dizaine de voyageurs.',
        ],
        'ddb-1881-01-12-reichsanzeiger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 10. Januar. (Fr. Corr.)',
            'publisher' => 'Deutscher Reichsanzeiger und Preußischer Staatsanzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1881-01-12',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/XQH4TKI67HZ44PPXQR2GKVEFTRYPUPI2?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Correspondance de Paris du 10 janvier (même texte dans le Reichsbote du 15) : sur la ligne de l\'Est, en gare de Mézy, près de Château-Thierry, le train de voyageurs venant des Ardennes a heurté un train de marchandises ; dix voyageurs et trois agents des postes très grièvement blessés, le chauffeur retiré mort de sous un wagon.',
        ],
        'ddb-1881-01-15-stuttgart' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Straßburg, 13. Jan.',
            'publisher' => 'Neues Tagblatt und General-Anzeiger für Stuttgart und Württemberg (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1881-01-15',
            'locator' => 'p. 1',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/LFS4E5IOR2552GVHCP7DC43VBHTKSHWU?issuepage=1',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de Strasbourg du 13 janvier, d\'après le Elsässer Journal : en gare de Mézy, où l\'embranchement des Ardennes quitte la ligne de Paris, un train de marchandises passait d\'une voie à l\'autre quand le train 32 est arrivé des Ardennes ; le signal n\'avait pas été manœuvré ; le chef de train et le chauffeur tués sur le coup, le mécanicien grièvement blessé à la tête ; wagon postal brisé, deux agents des postes grièvement blessés ; aucun voyageur tué, au moins cinq blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 9 janvier 1881, en gare de Mézy, près de Château-Thierry, où l\'embranchement des Ardennes rejoint la ligne de Paris à Strasbourg, un train de marchandises en manœuvre passait d\'une voie à l\'autre.',
                'en' => 'On 9 January 1881, at Mézy station near Château-Thierry, where the Ardennes branch joins the Paris to Strasbourg line, a goods train was crossing from one track to the other while shunting.',
                'de' => 'Am 9. Januar 1881 wechselte im Bahnhof Mézy bei Château-Thierry, wo die Zweigbahn in die Ardennen von der Strecke Paris-Straßburg abgeht, ein rangierender Güterzug von einem Gleis auf das andere.',
                'sources' => ['wp-liste-1880', 'ddb-1881-01-15-stuttgart'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train de voyageurs 32, venant des Ardennes, est arrivé à ce moment. Le signal n\'avait pas été manœuvré et le mécanicien, qui croyait la voie libre, est entré dans le train de marchandises. La locomotive s\'est dressée et est retombée sur le fourgon, le wagon postal a été brisé et les voitures de voyageurs se sont chevauchées.',
                'en' => 'Passenger train 32, coming from the Ardennes, arrived at that moment. The signal had not been set and the driver, who believed the line was clear, ran into the goods train. The engine reared up and fell back onto the van, the mail van was smashed and the passenger coaches rode up on one another.',
                'de' => 'In diesem Augenblick kam der Personenzug 32 aus den Ardennen. Die Signalscheibe war nicht bedient worden, und der Lokführer, der das Gleis für frei hielt, fuhr in den Güterzug. Die Lokomotive bäumte sich auf und fiel auf den Gepäckwagen zurück, der Postwagen wurde zertrümmert, die Personenwagen schoben sich übereinander.',
                'sources' => ['ddb-1881-01-15-stuttgart', 'ddb-1881-01-12-reichsanzeiger'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chef de train et le chauffeur ont été tués et le mécanicien grièvement blessé. Deux ou trois agents des postes et plusieurs voyageurs ont été blessés.',
                'en' => 'The guard and the stoker were killed and the driver badly injured. Two or three postal workers and several passengers were injured.',
                'de' => 'Zugführer und Heizer wurden getötet, der Lokführer schwer verletzt. Zwei oder drei Postbeamte und mehrere Reisende wurden verletzt.',
                'sources' => ['wp-liste-1880', 'ddb-1881-01-15-stuttgart', 'ddb-1881-01-12-reichsanzeiger'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le rapide attendu à Nancy à 4 h du matin n\'y est arrivé qu\'à 13 h, et le courrier de Paris est arrivé en retard à Strasbourg.',
                'en' => 'The express due in Nancy at 4 am arrived only at 1 pm, and the Paris mail reached Strasbourg late.',
                'de' => 'Der um 4 Uhr morgens in Nancy erwartete Schnellzug traf erst um 13 Uhr ein, die Pariser Post erreichte Straßburg verspätet.',
                'sources' => ['ddb-1881-01-15-stuttgart'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia parle d\'un train venant d\'Épernay, la presse allemande d\'un train venant des Ardennes. Le nombre de blessés varie : une dizaine de voyageurs selon la liste, dix voyageurs et trois agents des postes selon la correspondance de Paris, au moins cinq voyageurs et deux agents des postes selon le récit de Strasbourg. La première dépêche ne cite que le chauffeur parmi les morts.',
                'en' => 'The Wikipedia list speaks of a train from Épernay, the German press of a train from the Ardennes. The number of injured varies: about ten passengers according to the list, ten passengers and three postal workers according to the Paris report, at least five passengers and two postal workers according to the Strasbourg account. The first dispatch names only the stoker among the dead.',
                'de' => 'Die Wikipedia-Liste spricht von einem Zug aus Épernay, die deutsche Presse von einem Zug aus den Ardennen. Die Zahl der Verletzten schwankt: etwa zehn Reisende nach der Liste, zehn Reisende und drei Postbeamte nach dem Pariser Bericht, mindestens fünf Reisende und zwei Postbeamte nach dem Straßburger Bericht. Die erste Meldung nennt nur den Heizer unter den Toten.',
                'sources' => ['wp-liste-1880', 'ddb-1881-01-12-reichsanzeiger', 'ddb-1881-01-15-stuttgart'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1880', 'ddb-1881-01-15-stuttgart'],
            'fr' => 'Chef de train et chauffeur tués selon la liste de Wikipédia et le récit de Strasbourg du 13 janvier 1881 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Guard and stoker killed according to the Wikipedia list and the Strasbourg account of 13 January 1881; the record gave no number of deaths.',
            'de' => 'Zugführer und Heizer getötet nach der Wikipedia-Liste und dem Straßburger Bericht vom 13. Januar 1881; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '02484',
            'sources' => ['wp-liste-1880', 'ddb-1881-01-12-reichsanzeiger', 'ddb-1881-01-15-stuttgart'],
            'fr' => 'Collision en gare de Mézy (aujourd\'hui Mézy-Moulins, Aisne), près de Château-Thierry, selon la liste de Wikipédia et la presse allemande ; la fiche la plaçait à Épernay, d\'où venait le train selon la liste.',
            'en' => 'Collision at Mézy station (now Mézy-Moulins, Aisne), near Château-Thierry, according to the Wikipedia list and the German press; the record placed it at Épernay, where the train came from according to the list.',
            'de' => 'Zusammenstoß im Bahnhof Mézy (heute Mézy-Moulins, Aisne) bei Château-Thierry nach der Wikipedia-Liste und der deutschen Presse; der Eintrag verortete ihn in Épernay, woher der Zug nach der Liste kam.',
        ],
    ],
];
