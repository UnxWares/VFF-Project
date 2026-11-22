<?php

// Contexte sourcé du tamponnement de deux trains venant d'Avricourt, bloqués par la neige près de la gare de Bondy, par l'express n° 38, en décembre 1879 (recherche du 21/11/2026).
return [
    'slug' => '1879-12-05-bondy',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1879, 5 décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1879',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (un mort, cinq blessés graves et quatre légers) ; donne le jugement du tribunal correctionnel de Paris.',
        ],
        'vdr-bondy' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1879, Accident provoqué par la neige à Bondy (93)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1879-accident-provoque-par-la-neige-a-bondy/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit « La catastrophe de Bondy », Le Petit Journal du 7 décembre 1879.',
        ],
        'petit-journal-1879-12-07' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 7 décembre 1879',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1879-12-07',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k594223q/f1',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'la-presse-1880-02-14' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 14 février 1880',
            'publisher' => 'La Presse',
            'author' => null,
            'published' => '1880-02-14',
            'locator' => 'p. 4',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k543239v/f4.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Jugement du tribunal correctionnel de Paris, cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Sur la ligne de Paris à Strasbourg, la neige accumulée retardait les trains de plusieurs heures. Le train n° 44, attendu à Paris à 3 h 15, est entré en gare de Bondy à 9 heures du matin, suivi du train n° 36. Les deux trains venaient d\'Avricourt et avaient chacun deux machines ; on a détaché celles de tête du 36 pour les placer en queue du 44 et l\'aider à franchir la côte, où la neige atteignait 70 centimètres.',
                'en' => 'On the Paris to Strasbourg line, piled-up snow was delaying trains by several hours. Train no. 44, due in Paris at 3.15 am, came into Bondy station at 9 am, followed by train no. 36. Both trains came from Avricourt and each had two engines; the leading engines of no. 36 were detached and placed at the rear of no. 44 to help it over the gradient, where the snow was 70 centimetres deep.',
                'de' => 'Auf der Strecke Paris-Straßburg verspäteten sich die Züge wegen der Schneemassen um mehrere Stunden. Der Zug Nr. 44, in Paris um 3.15 Uhr erwartet, fuhr um 9 Uhr morgens in den Bahnhof Bondy ein, gefolgt vom Zug Nr. 36. Beide Züge kamen aus Avricourt und hatten je zwei Lokomotiven; die vorderen Lokomotiven des 36 wurden abgekuppelt und ans Ende des 44 gesetzt, um ihm über die Steigung zu helfen, wo der Schnee 70 Zentimeter hoch lag.',
                'sources' => ['vdr-bondy', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'À ce moment, l\'express n° 38 a heurté le train 36. Ses cinq premières voitures, prises entre les deux convois, ont été projetées contre le train de tête ; une voiture de seconde classe a été soulevée au-dessus des autres et mise en pièces, et quatre wagons sont restés défoncés hors des voies, à environ 150 mètres au-delà de la gare de Bondy.',
                'en' => 'At that moment express no. 38 ran into train no. 36. Its first five carriages, caught between the two trains, were thrown against the leading train; a second-class carriage was lifted above the others and smashed to pieces, and four wagons were left wrecked beside the track, about 150 metres beyond Bondy station.',
                'de' => 'In diesem Augenblick prallte der Schnellzug Nr. 38 auf den Zug 36. Dessen erste fünf Wagen wurden zwischen den beiden Zügen gegen den vorderen Zug geschleudert; ein Wagen zweiter Klasse wurde über die anderen gehoben und zertrümmert, und vier Wagen blieben zerstört neben den Gleisen liegen, etwa 150 Meter hinter dem Bahnhof Bondy.',
                'sources' => ['vdr-bondy', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un médecin qui voyageait dans l\'express a donné les premiers soins, et les blessés ont été portés à la gare de Bondy sur des brancards ou des chariots à bagages. Le Petit Journal comptait un mort et neuf blessés, dont trois légèrement contusionnés restés à Bondy ou au Raincy.',
                'en' => 'A doctor travelling on the express gave first aid, and the injured were carried to Bondy station on stretchers or luggage trolleys. Le Petit Journal counted one dead and nine injured, three of them only bruised, who stayed in Bondy or Le Raincy.',
                'de' => 'Ein im Schnellzug reisender Arzt leistete Erste Hilfe, und die Verletzten wurden auf Tragen oder Gepäckkarren zum Bahnhof Bondy gebracht. Le Petit Journal zählte einen Toten und neun Verletzte, darunter drei nur leicht geprellte, die in Bondy oder Le Raincy blieben.',
                'sources' => ['vdr-bondy', 'wp-liste-1870'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le Petit Journal rappelait que le chef de gare était responsable des deux trains présents en gare, que l\'aiguilleur devait vérifier le disque par mauvais temps et que les chefs de train devaient couvrir leurs convois. Selon Wikipédia, le tribunal correctionnel de Paris a condamné deux mois plus tard le chef de gare et un employé pour ne pas avoir couvert le convoi à l\'arrêt.',
                'en' => 'Le Petit Journal pointed out that the stationmaster was responsible for the two trains in the station, that the pointsman had to check the disc signal in bad weather and that the guards had to protect their trains. According to Wikipedia, two months later the Paris criminal court convicted the stationmaster and an employee for failing to protect the stopped train.',
                'de' => 'Le Petit Journal erinnerte daran, dass der Bahnhofsvorsteher für die beiden Züge im Bahnhof verantwortlich war, dass der Weichensteller bei schlechtem Wetter das Scheibensignal prüfen musste und dass die Zugführer ihre Züge decken mussten. Laut Wikipedia verurteilte das Strafgericht von Paris zwei Monate später den Bahnhofsvorsteher und einen Bediensteten, weil sie den haltenden Zug nicht gedeckt hatten.',
                'sources' => ['vdr-bondy', 'wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '93010',
            'sources' => ['vdr-bondy', 'wp-liste-1870'],
            'fr' => 'Avricourt était l\'origine des trains ; l\'accident a eu lieu juste après la gare de Bondy, sur la ligne de Paris à Strasbourg.',
            'en' => 'Avricourt was where the trains came from; the accident happened just beyond Bondy station, on the Paris to Strasbourg line.',
            'de' => 'Avricourt war der Ausgangsort der Züge; der Unfall ereignete sich kurz hinter dem Bahnhof Bondy an der Strecke Paris-Straßburg.',
        ],
    ],
];
