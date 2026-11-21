<?php

// Contexte sourcé du déraillement de l'express de Serquigny à Petit-Couronne du 21 août 1897 (recherche du 21/11/2026).
return [
    'slug' => '1897-08-21-petit-couronne',
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
            'note' => 'Source de la fiche : entrée du 21 août 1897 (express Serquigny-Rouen déraillé avant la gare de Petit-Couronne, vitesse excessive sur voie mal entretenue, 1 mort, 15 blessés) ; renvoie à Le Figaro et au Journal de Rouen du 22 août 1897.',
        ],
        'vdr-petit-couronne-1897' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1897 - Accident dans la courbe de Petit-Couronne (76)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1897-deraillement-de-petit-couronne/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Figaro du 22 août 1897 et La Gazette de France du 23 août 1897.',
        ],
        'figaro-1897-08-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 22 août 1897',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1897-08-22',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2841136/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 21 août 1897, le train express de Serquigny à Rouen, attendu à Rouen en début d\'après-midi (12 h 57 selon Le Figaro, 13 h 36 selon La Gazette de France), a déraillé entre Grand-Couronne et Petit-Couronne, dans une forte courbe, à 50 mètres de la gare de Petit-Couronne selon Le Figaro (500 mètres selon La Gazette de France). La station est sur la ligne de Rouen-Orléans à Elbeuf-Ville, qui appartenait à l\'État et avait été rachetée quatre ou cinq ans plus tôt par la Compagnie de l\'Ouest.',
                'en' => 'On Saturday 21 August 1897, the express from Serquigny to Rouen, due in Rouen in the early afternoon (12.57 pm according to Le Figaro, 1.36 pm according to La Gazette de France), derailed between Grand-Couronne and Petit-Couronne, in a sharp curve, 50 metres from Petit-Couronne station according to Le Figaro (500 metres according to La Gazette de France). The station is on the Rouen-Orléans to Elbeuf-Ville line, which had belonged to the State and had been bought four or five years earlier by the Compagnie de l\'Ouest.',
                'de' => 'Am Samstag, dem 21. August 1897, entgleiste der Schnellzug von Serquigny nach Rouen, der am frühen Nachmittag in Rouen erwartet wurde (12.57 Uhr nach Le Figaro, 13.36 Uhr nach La Gazette de France), zwischen Grand-Couronne und Petit-Couronne in einer starken Kurve, 50 Meter vom Bahnhof Petit-Couronne entfernt nach Le Figaro (500 Meter nach La Gazette de France). Die Station liegt an der Strecke Rouen-Orléans nach Elbeuf-Ville, die dem Staat gehört hatte und vier oder fünf Jahre zuvor von der Compagnie de l\'Ouest gekauft worden war.',
                'sources' => ['vdr-petit-couronne-1897', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive a déraillé et a basculé au bord d\'un talus, entraînant une voiture de seconde classe. D\'après La Gazette de France, la machine et le tender ont roulé au fond d\'un talus profond de quatre mètres, la première voiture de seconde classe a été mise en miettes, une voiture de première classe a été projetée à grande distance sur la voie et les trois autres voitures sont restées couchées près du remblai. Le mécanicien, projeté à quatre mètres devant la machine, et le chauffeur, enseveli sous le charbon, n\'ont eu que de légères blessures et ont aidé à secourir les blessés.',
                'en' => 'The locomotive derailed and toppled at the edge of an embankment, dragging a second-class coach with it. According to La Gazette de France, the engine and tender rolled to the bottom of a four-metre bank, the first second-class coach was smashed to pieces, a first-class coach was thrown a long way along the track and the other three coaches lay on their sides near the embankment. The driver, thrown four metres in front of the engine, and the fireman, buried under the coal, had only slight injuries and helped rescue the injured.',
                'de' => 'Die Lokomotive entgleiste und kippte am Rand einer Böschung um, wobei sie einen Wagen zweiter Klasse mitriss. Nach La Gazette de France rollten Maschine und Tender auf den Grund einer vier Meter tiefen Böschung, der erste Wagen zweiter Klasse wurde in Stücke zerlegt, ein Wagen erster Klasse weit über das Gleis geschleudert und die drei anderen Wagen blieben nahe dem Damm liegen. Der vier Meter vor die Maschine geschleuderte Lokführer und der unter der Kohle begrabene Heizer erlitten nur leichte Verletzungen und halfen bei der Rettung der Verletzten.',
                'sources' => ['vdr-petit-couronne-1897'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur a été tué sur le coup. Une quinzaine d\'autres voyageurs ont été blessés, plus ou moins gravement ; ils ont été transportés en voiture chez eux et soignés par des médecins de la région. Le président du Conseil, qui se rendait à Dieppe par ce train, a visité les blessés descendant à Rouen, selon Le Figaro.',
                'en' => 'One passenger was killed outright. About fifteen other passengers were injured, some more seriously than others; they were taken home by carriage and treated by local doctors. The President of the Council, who was travelling to Dieppe on that train, visited the injured arriving at Rouen, according to Le Figaro.',
                'de' => 'Ein Reisender wurde auf der Stelle getötet. Etwa fünfzehn weitere Reisende wurden mehr oder weniger schwer verletzt; sie wurden mit Wagen nach Hause gebracht und von Ärzten der Gegend versorgt. Der Ministerpräsident, der mit diesem Zug nach Dieppe reiste, besuchte nach Le Figaro die in Rouen aussteigenden Verletzten.',
                'sources' => ['vdr-petit-couronne-1897', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La Gazette de France attribue l\'accident à la trop grande vitesse du train et indique qu\'une enquête a été ouverte. La liste de Wikipédia ajoute que la voie était notoirement mal entretenue.',
                'en' => 'La Gazette de France attributes the accident to the train\'s excessive speed and says an inquiry was opened. The Wikipedia list adds that the track was notoriously poorly maintained.',
                'de' => 'La Gazette de France führt den Unfall auf die zu hohe Geschwindigkeit des Zuges zurück und gibt an, es sei eine Untersuchung eingeleitet worden. Die Wikipedia-Liste ergänzt, das Gleis sei bekanntermaßen schlecht unterhalten gewesen.',
                'sources' => ['vdr-petit-couronne-1897', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [],
];
