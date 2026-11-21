<?php

// Contexte sourcé de l'accident d'Appilly du 9 septembre 1894 (recherche du 21/11/2026).
return [
    'slug' => '1894-09-09-appilly',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-appilly' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Accident ferroviaire d\'Appilly',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Accident_ferroviaire_d%27Appilly&oldid=240225274',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article détaillé (train, manœuvre, bilan, secours, enquête, suites) ; ses références de presse et d\'archives n\'ont pas été lues.',
        ],
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1894, Appilly',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1894',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée courte, sourcée par Le Petit Journal du 11 septembre 1894 (Gallica, non lu) ; renvoie à l\'article détaillé.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 9 septembre 1894, vers 14 h 15, le rapide n° 115 Paris - Bruxelles - Cologne, parti de la gare du Nord à 12 h 40 avec 75 voyageurs, a heurté en gare d\'Appilly (Oise), sur la ligne de Creil à Jeumont, une locomotive de manœuvre de la Compagnie du Nord qui traversait les voies principales. Le rapide roulait à environ 85 à 90 km/h.',
                'en' => 'On 9 September 1894, at about 2.15 pm, express no. 115 Paris - Brussels - Cologne, which had left the Gare du Nord at 12.40 pm with 75 passengers, struck at Appilly station (Oise), on the Creil to Jeumont line, a shunting locomotive of the Compagnie du Nord crossing the main tracks. The express was travelling at about 85 to 90 km/h.',
                'de' => 'Am 9. September 1894 gegen 14.15 Uhr stieß der Schnellzug Nr. 115 Paris - Brüssel - Köln, der um 12.40 Uhr mit 75 Reisenden den Pariser Nordbahnhof verlassen hatte, im Bahnhof Appilly (Oise) auf der Strecke von Creil nach Jeumont mit einer Rangierlokomotive der Compagnie du Nord zusammen, die die Hauptgleise querte. Der Schnellzug fuhr mit etwa 85 bis 90 km/h.',
                'sources' => ['wp-appilly', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Une petite locomotive-tender du dépôt de Tergnier devait déplacer sept wagons ; le chef de gare, seul agent de la station, était à bord. La manœuvre, prévue pour environ vingt-cinq minutes, avait été retardée par un train de voyageurs. Au moment du choc, elle traversait les voies principales avec trois wagons ; la locomotive s\'est renversée et les quatre premières voitures du rapide se sont entassées sur elle.',
                'en' => 'A small tank locomotive from the Tergnier depot was to move seven wagons; the stationmaster, the only railwayman at the station, was on board. The movement, planned to last about twenty-five minutes, had been delayed by a passenger train. At the moment of impact it was crossing the main tracks with three wagons; the locomotive overturned and the first four coaches of the express piled up on it.',
                'de' => 'Eine kleine Tenderlokomotive aus dem Depot Tergnier sollte sieben Güterwagen umsetzen; der Bahnhofsvorsteher, der einzige Bedienstete des Bahnhofs, war an Bord. Die auf etwa fünfundzwanzig Minuten angesetzte Rangierfahrt war durch einen Reisezug verzögert worden. Beim Zusammenstoß querte sie die Hauptgleise mit drei Wagen; die Lokomotive kippte um, und die ersten vier Wagen des Schnellzugs türmten sich auf ihr auf.',
                'sources' => ['wp-appilly'],
            ],
            [
                'fr' => 'Le chef de gare étant mort et seul capable d\'utiliser le télégraphe, il a fallu près de trois quarts d\'heure pour trouver quelqu\'un en mesure d\'envoyer une dépêche. Les premiers secours, assurés par des voyageurs indemnes et des riverains, sont arrivés environ trois heures après l\'accident ; les derniers corps ont été extraits vers 22 h et une voie unique provisoire a été rétablie vers 21 h. Le ministre des Travaux publics, Louis Barthou, s\'est rendu sur place.',
                'en' => 'As the stationmaster was dead and was the only person able to use the telegraph, it took nearly three quarters of an hour to find someone who could send a message. The first aid, given by uninjured passengers and local residents, arrived about three hours after the accident; the last bodies were recovered at about 10 pm and a temporary single track was restored at about 9 pm. The Minister of Public Works, Louis Barthou, went to the site.',
                'de' => 'Da der Bahnhofsvorsteher tot und als Einziger zur Bedienung des Telegrafen imstande war, dauerte es fast eine Dreiviertelstunde, bis jemand gefunden war, der eine Depesche absetzen konnte. Die erste Hilfe durch unverletzte Reisende und Anwohner traf etwa drei Stunden nach dem Unglück ein; die letzten Leichen wurden gegen 22 Uhr geborgen, ein provisorisches Einzelgleis war gegen 21 Uhr wiederhergestellt. Der Minister für öffentliche Arbeiten, Louis Barthou, begab sich zur Unfallstelle.',
                'sources' => ['wp-appilly'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de cinq morts, dont le chef de gare et quatre voyageurs, et d\'une vingtaine de blessés, dont douze graves. La presse avait d\'abord annoncé neuf morts.',
                'en' => 'The toll is five dead, including the stationmaster and four passengers, and about twenty injured, twelve of them seriously. The press had first reported nine dead.',
                'de' => 'Die Bilanz beträgt fünf Tote, darunter der Bahnhofsvorsteher und vier Reisende, und etwa zwanzig Verletzte, davon zwölf schwer. Die Presse hatte zunächst neun Tote gemeldet.',
                'sources' => ['wp-appilly', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le signal du rapide était ouvert : le mécanicien du train a été mis hors de cause, de même que la garde du sémaphore. Le contrôle a imputé la responsabilité au seul chef de gare, qui avait ordonné la manœuvre sans la couvrir et engagé les voies principales peu avant le passage du rapide, en contravention aux articles 11 et 12 du règlement de la Compagnie.',
                'en' => 'The express\'s signal was clear: the driver of the train was cleared, as was the signal-post keeper. The inspection service held the stationmaster alone responsible: he had ordered the movement without protecting it and had fouled the main tracks shortly before the express was due, contrary to articles 11 and 12 of the Company\'s regulations.',
                'de' => 'Das Signal für den Schnellzug stand auf Fahrt: Der Lokführer des Zuges wurde entlastet, ebenso die Signalwärterin. Die Aufsichtsbehörde machte allein den Bahnhofsvorsteher verantwortlich, der die Rangierfahrt ohne Deckung angeordnet und die Hauptgleise kurz vor der Durchfahrt des Schnellzugs belegt hatte, entgegen den Artikeln 11 und 12 der Vorschriften der Gesellschaft.',
                'sources' => ['wp-appilly'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le décès du chef de gare a mis fin aux poursuites pénales, mais les victimes ont obtenu des indemnités de la Compagnie du Nord. L\'opinion a attribué son erreur au surmenage ; l\'accident a relancé le débat sur les effectifs insuffisants des compagnies, dans la presse, à la Chambre des députés et au gouvernement. Une circulaire du 6 novembre 1894 a encadré les heures de service des chefs de gare.',
                'en' => 'The stationmaster\'s death ended the criminal proceedings, but the victims obtained compensation from the Compagnie du Nord. Public opinion put his error down to overwork; the accident revived the debate on the companies\' insufficient staffing in the press, the Chamber of Deputies and the government. A circular of 6 November 1894 regulated stationmasters\' working hours.',
                'de' => 'Der Tod des Bahnhofsvorstehers beendete die Strafverfolgung, doch die Opfer erhielten Entschädigungen von der Compagnie du Nord. Die Öffentlichkeit führte seinen Fehler auf Überarbeitung zurück; der Unfall belebte in Presse, Abgeordnetenkammer und Regierung die Debatte über die knappe Personalausstattung der Gesellschaften. Ein Rundschreiben vom 6. November 1894 regelte die Dienstzeiten der Bahnhofsvorsteher.',
                'sources' => ['wp-appilly'],
            ],
            [
                'fr' => 'La Compagnie du Nord a été critiquée pour la lenteur des secours, qu\'elle justifiait par la sécurité des sauveteurs, et pour son frein à vide direct, moins efficace que le frein automatique des autres compagnies ; le gouvernement lui a imposé de changer de système.',
                'en' => 'The Compagnie du Nord was criticised for the slowness of the rescue, which it justified by the safety of the rescuers, and for its direct vacuum brake, less effective than the automatic brake of the other companies; the government required it to change system.',
                'de' => 'Die Compagnie du Nord wurde für die langsame Rettung kritisiert, die sie mit der Sicherheit der Helfer begründete, sowie für ihre direkte Saugluftbremse, die weniger wirksam war als die selbsttätige Bremse der anderen Gesellschaften; die Regierung zwang sie, das System zu wechseln.',
                'sources' => ['wp-appilly'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés (une vingtaine, dont douze graves) ne figure pas dans la fiche. L\'article détaillé place l\'accident vers 14 h 15 ; la liste le situe vers 14 h et parle de quatre wagons de marchandises là où l\'article en cite sept, dont trois traversaient les voies. Une dépêche étrangère de 1894 évoquait douze morts ; elle n\'a pas pu être lue et n\'est pas retenue.',
                'en' => 'The injury count (about twenty, twelve of them serious) is not in the record. The detailed article places the accident at about 2.15 pm; the list puts it at about 2 pm and mentions four freight wagons where the article cites seven, of which three were crossing the tracks. A foreign press report of 1894 mentioned twelve dead; it could not be read and is not retained.',
                'de' => 'Die Verletztenzahl (etwa zwanzig, davon zwölf schwer) fehlt im Datensatz. Der ausführliche Artikel setzt das Unglück gegen 14.15 Uhr an; die Liste nennt etwa 14 Uhr und spricht von vier Güterwagen, wo der Artikel sieben nennt, von denen drei die Gleise querten. Eine ausländische Pressemeldung von 1894 nannte zwölf Tote; sie konnte nicht gelesen werden und wird nicht berücksichtigt.',
                'sources' => ['wp-appilly', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [],
];
