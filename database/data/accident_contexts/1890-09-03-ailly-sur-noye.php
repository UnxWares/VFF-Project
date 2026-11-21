<?php

// Contexte sourcé du déraillement de l'express Calais-Paris près d'Ailly-sur-Noye du 3 septembre 1890 (recherche du 21/11/2026).
return [
    'slug' => '1890-09-03-ailly-sur-noye',
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
            'locator' => '1890',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1890',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : entrée du 3 septembre 1890 (express Calais-Paris contre une poutrelle tombée d\'un train de marchandises, entre Ailly-sur-Noye et La Faloise, 1 mort, 5 blessés) ; renvoie à Le Figaro et Le Matin du 5 septembre 1890.',
        ],
        'vdr-ailly-1890' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1890 - Compagnie du Nord, accident à La Faloise',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1890-cie-du-nord-accident-a-la-faloise/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Matin du 5 septembre 1890.',
        ],
        'matin-1890-09-05' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 5 septembre 1890',
            'publisher' => 'Le Matin',
            'author' => null,
            'published' => '1890-09-05',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k554223r/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Selon Le Matin du 5 septembre 1890, la nuit précédente à 4 h 15 du matin, l\'express n° 10 de Calais à Paris, de la Compagnie du Nord, a déraillé entre les gares d\'Ailly-sur-Noye et de La Faloise.',
                'en' => 'According to Le Matin of 5 September 1890, during the previous night at 4.15 am, express no. 10 from Calais to Paris, of the Compagnie du Nord, derailed between Ailly-sur-Noye and La Faloise stations.',
                'de' => 'Nach Le Matin vom 5. September 1890 entgleiste in der vorangegangenen Nacht um 4.15 Uhr der Schnellzug Nr. 10 von Calais nach Paris der Compagnie du Nord zwischen den Bahnhöfen Ailly-sur-Noye und La Faloise.',
                'sources' => ['vdr-ailly-1890', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive est tombée dans un talus de dix mètres en entraînant le fourgon du conducteur, un wagon-poste et deux voitures. Selon Le Matin, un des wagons était vide et l\'autre ne contenait qu\'un seul voyageur, un agent de la Compagnie ; les voyageurs du reste du train ont attendu sur la voie le reste de la nuit et ne sont arrivés à Paris que vers dix heures du matin.',
                'en' => 'The locomotive fell down a ten-metre embankment, dragging the conductor\'s van, a mail wagon and two coaches with it. According to Le Matin, one of the wagons was empty and the other held only one passenger, a company employee; the passengers in the rest of the train waited on the track for the remainder of the night and reached Paris only at about ten in the morning.',
                'de' => 'Die Lokomotive stürzte eine zehn Meter hohe Böschung hinunter und riss den Packwagen des Zugführers, einen Postwagen und zwei Wagen mit. Nach Le Matin war einer der Wagen leer und im anderen saß nur ein Reisender, ein Angestellter der Gesellschaft; die Reisenden im übrigen Zug warteten den Rest der Nacht auf der Strecke und kamen erst gegen zehn Uhr morgens in Paris an.',
                'sources' => ['vdr-ailly-1890'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent de la Compagnie, seul voyageur d\'un des wagons, a été tué. Le chef de train a été grièvement blessé (une jambe fracturée) et quatre employés des postes ont été blessés, selon Le Matin. La note de la Compagnie du Nord communiquée à la presse parle d\'un agent tué, d\'un chef de train contusionné gravement et d\'un employé des postes légèrement contusionné.',
                'en' => 'A company employee, the only passenger in one of the wagons, was killed. The train conductor was seriously injured (a broken leg) and four postal employees were injured, according to Le Matin. The note from the Compagnie du Nord given to the press speaks of one employee killed, a seriously bruised conductor and one slightly bruised postal employee.',
                'de' => 'Ein Angestellter der Gesellschaft, der einzige Reisende in einem der Wagen, wurde getötet. Der Zugführer wurde schwer verletzt (ein gebrochenes Bein) und vier Postangestellte wurden verletzt, nach Le Matin. Die der Presse übermittelte Mitteilung der Compagnie du Nord spricht von einem getöteten Angestellten, einem schwer geprellten Zugführer und einem leicht geprellten Postangestellten.',
                'sources' => ['vdr-ailly-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon Le Matin, on attribue le déraillement à la chute d\'une poutre de fer tombée d\'un train de marchandises précédent et qui obstruait la voie. La note de la Compagnie du Nord dit que la cause du déraillement n\'était pas encore connue.',
                'en' => 'According to Le Matin, the derailment is attributed to an iron girder that had fallen from a preceding goods train and was blocking the track. The Compagnie du Nord\'s note says that the cause of the derailment was not yet known.',
                'de' => 'Nach Le Matin wird die Entgleisung auf einen Eisenträger zurückgeführt, der von einem vorausfahrenden Güterzug gefallen war und das Gleis versperrte. Die Mitteilung der Compagnie du Nord sagt, die Ursache der Entgleisung sei noch nicht bekannt.',
                'sources' => ['vdr-ailly-1890', 'wp-liste-1890'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le Matin du 5 septembre 1890 écrit que l\'accident a eu lieu « la nuit dernière », à 4 h 15, ce qui le placerait le 5 septembre ; la liste de Wikipédia donne le 3 septembre 1890 (à 4 h 15 aussi) en citant Le Figaro et Le Matin du 5 septembre. Le nombre de blessés diffère : cinq dans la liste, un conducteur grièvement blessé et quatre employés des postes selon Le Matin, deux blessés selon la note de la Compagnie du Nord.',
                'en' => 'Le Matin of 5 September 1890 writes that the accident took place "last night", at 4.15 am, which would put it on 5 September; the Wikipedia list gives 3 September 1890 (also at 4.15 am), citing Le Figaro and Le Matin of 5 September. The number of injured differs: five in the list, a seriously injured conductor and four postal employees according to Le Matin, two injured according to the Compagnie du Nord\'s note.',
                'de' => 'Le Matin vom 5. September 1890 schreibt, der Unfall habe sich „in der vergangenen Nacht“ um 4.15 Uhr ereignet, was ihn auf den 5. September legen würde; die Wikipedia-Liste nennt den 3. September 1890 (ebenfalls 4.15 Uhr) und beruft sich auf Le Figaro und Le Matin vom 5. September. Die Zahl der Verletzten weicht ab: fünf in der Liste, ein schwer verletzter Zugführer und vier Postangestellte nach Le Matin, zwei Verletzte nach der Mitteilung der Compagnie du Nord.',
                'sources' => ['vdr-ailly-1890', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [],
];
