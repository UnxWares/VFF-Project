<?php

// Contexte sourcé de la collision de Petit-Croix du 2 novembre 1876 (recherche du 21/11/2026).
return [
    'slug' => '1876-11-02-petit-croix',
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
            'locator' => '1876',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1876',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : entrée du 2 novembre 1876 à Petit-Croix (train-poste Bâle-Paris, erreur d\'aiguillage, un mort, une dizaine de blessés) ; suites judiciaires ; renvoie à Le Figaro du 4 et Le Temps du 5 novembre 1876.',
        ],
        'vdr-petit-croix-1876' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1876 - Manoeuvres dangereuses à Montreux-Château (90)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1876-manoeuvres-dangereuses-a-montreux-chateau/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Libéral de l\'Est du 5 novembre 1876 et une précision du Petit Journal du 6 novembre 1876.',
        ],
        'liberal-est-1876-11-05' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Libéral de l\'Est du 5 novembre 1876',
            'publisher' => 'Le Libéral de l\'Est',
            'author' => null,
            'published' => '1876-11-05',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bd6t51198000c/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'petit-journal-1876-11-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 6 novembre 1876',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1876-11-06',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k593096c/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 2 novembre 1876, à 18 h 52, le train-poste n° 42 entrait en gare de Petit-Croix en passant sous le passage supérieur du chemin de Petit-Croix à Montreux-Château. La voie descendante est longée par une voie de garage où l\'on fait chaque jour des manoeuvres, reliée à la voie principale par deux aiguilles près du passage supérieur, qui doivent être ouvertes au passage des trains.',
                'en' => 'On Thursday 2 November 1876, at 6.52 pm, mail train no. 42 was entering Petit-Croix station, passing under the overbridge of the road from Petit-Croix to Montreux-Château. The down line runs alongside a siding where shunting is done every day, linked to the main line by two sets of points near the overbridge, which must be open when trains pass.',
                'de' => 'Am Donnerstag, dem 2. November 1876, fuhr um 18.52 Uhr der Postzug Nr. 42 in den Bahnhof Petit-Croix ein und unterquerte dabei die Straßenbrücke des Wegs von Petit-Croix nach Montreux-Château. Neben dem Gleis in Fahrtrichtung liegt ein Abstellgleis, auf dem täglich rangiert wird und das nahe der Brücke durch zwei Weichen mit dem Hauptgleis verbunden ist; diese müssen bei der Durchfahrt von Zügen offen sein.',
                'sources' => ['vdr-petit-croix-1876'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La voie de garage était occupée par une file de wagons remorquée par une locomotive, qui allait croiser le train-poste. L\'aiguille était ouverte, mais l\'aiguilleur, croyant qu\'elle ne l\'était pas, s\'est jeté sur le levier et a dirigé le train de manoeuvre sur la voie principale. Malgré la contre-vapeur du mécanicien, les deux locomotives se sont rencontrées sur le croisement, au choc en biais, et les voitures du train-poste ont été fortement endommagées, notamment celles du milieu.',
                'en' => 'The siding was occupied by a line of wagons towed by a locomotive, which was about to cross the mail train. The points were open, but the pointsman, thinking they were not, threw himself on the lever and sent the shunting train onto the main line. Despite the driver putting his engine into reverse, the two locomotives met on the crossing in an oblique impact, and the mail train\'s coaches were badly damaged, particularly those in the middle.',
                'de' => 'Das Abstellgleis war von einer von einer Lokomotive gezogenen Wagenreihe besetzt, die den Postzug kreuzen sollte. Die Weiche war offen, doch der Weichenwärter, der glaubte, sie sei es nicht, warf sich auf den Hebel und leitete den Rangierzug auf das Hauptgleis. Trotz Gegendampf des Lokführers trafen die beiden Lokomotiven auf der Kreuzung in einem schrägen Stoß aufeinander, und die Wagen des Postzugs wurden schwer beschädigt, vor allem die mittleren.',
                'sources' => ['vdr-petit-croix-1876', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur de première classe est mort sur le coup et neuf personnes ont été blessées, dont un chauffeur assez grièvement : des voyageurs, le personnel du train-poste et le mécanicien de la machine de manoeuvre. Deux médecins qui voyageaient dans le train ont donné les premiers soins.',
                'en' => 'A first-class passenger died instantly and nine people were injured, one rather seriously, a fireman: passengers, the mail train\'s staff and the driver of the shunting engine. Two doctors travelling on the train gave first aid.',
                'de' => 'Ein Reisender erster Klasse starb sofort, und neun Personen wurden verletzt, ein Heizer eher schwer: Reisende, das Personal des Postzugs und der Lokführer der Rangierlokomotive. Zwei im Zug reisende Ärzte leisteten Erste Hilfe.',
                'sources' => ['vdr-petit-croix-1876'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le Libéral de l\'Est attribue la collision à l\'erreur de l\'aiguilleur, un employé réputé excellent qui a perdu la tête en croyant l\'aiguille mal tournée ; le journal écrit que personne d\'autre dans la gare ne pouvait prévoir son geste. L\'aiguilleur a été arrêté.',
                'en' => 'Le Libéral de l\'Est attributes the collision to the pointsman\'s error: an employee reputed excellent who lost his head, thinking the points were set wrongly; the paper writes that nobody else at the station could have foreseen his action. The pointsman was arrested.',
                'de' => 'Le Libéral de l\'Est führt die Kollision auf den Fehler des Weichenwärters zurück, eines als ausgezeichnet geltenden Angestellten, der den Kopf verlor, weil er die Weiche für falsch gestellt hielt; die Zeitung schreibt, niemand sonst im Bahnhof habe seine Handlung vorhersehen können. Der Weichenwärter wurde festgenommen.',
                'sources' => ['vdr-petit-croix-1876'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le tribunal correctionnel de Belfort a condamné deux mois plus tard le seul aiguilleur responsable à 25 francs d\'amende, en justifiant son indulgence par la surcharge de travail de cet employé par ailleurs bien noté.',
                'en' => 'According to the Wikipedia list, the criminal court of Belfort two months later fined the pointsman, the only person held responsible, 25 francs, justifying its leniency by the workload of this otherwise well-regarded employee.',
                'de' => 'Nach der Wikipedia-Liste verurteilte das Strafgericht von Belfort zwei Monate später den allein verantwortlich gemachten Weichenwärter zu 25 Francs Geldstrafe und begründete seine Milde mit der Arbeitsüberlastung dieses sonst gut beurteilten Angestellten.',
                'sources' => ['wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [],
];
