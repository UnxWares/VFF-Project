<?php

// Contexte sourcé de l'accident d'Amiens du 13 août 1925 (recherche du 21/11/2026).
return [
    'slug' => '1925-08-13-amiens',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1925' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1925',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '13 août 1925, Amiens',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1925&oldid=235357727',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée détaillée (heure, vitesse, matériel, bilan, conclusion du directeur du contrôle) ; elle renvoie au Petit Parisien du 14 août 1925, non lu.',
        ],
        'amiens-jda-2025' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Il y a cent ans, le Paris-Boulogne déraille à Amiens',
            'publisher' => 'Amiens Métropole (Journal des Amiénois n° 1124)',
            'author' => 'Antoine Caux',
            'published' => '2025-07-08',
            'locator' => null,
            'url' => 'https://www.amiens.fr/Actualites/Il-y-a-cent-ans-le-Paris-Boulogne-deraille-a-Amiens',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article d\'anniversaire de la ville : heure, train, vitesse, matériel, secours, bilan. Ne rapporte ni procès ni commémoration.',
        ],
        'france3-2025' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Derrière un inimaginable chaos, cinq voitures sont les unes dans les autres : le 13 août 1925, le déraillement d\'un train fait 12 morts à Amiens',
            'publisher' => 'France 3 Hauts-de-France',
            'author' => 'Jennifer Alberts',
            'published' => '2025-08-13',
            'locator' => null,
            'url' => 'https://france3-regions.franceinfo.fr/hauts-de-france/somme/amiens/derriere-un-inimaginable-chaos-cinq-voitures-sont-les-unes-dans-les-autres-le-13-aout-1925-le-deraillement-d-un-train-fait-12-morts-a-amiens-3200472.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article d\'anniversaire détaillé (horaires, vitesses relevées, secours, mécanicien inculpé) ; pas de compte rendu de procès.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 13 août 1925, vers 16 h 21, le Paris - Boulogne, train supplémentaire mis en marche pour les fêtes du 15 août et chargé surtout de voyageurs de troisième classe partant en vacances, a déraillé à l\'entrée de la gare d\'Amiens (Somme), à environ 350 m des quais, sur un changement de voie. Il comptait 21 voitures.',
                'en' => 'On 13 August 1925, at about 4.21 pm, the Paris - Boulogne, an extra train run for the 15 August holiday and carrying mostly third-class passengers going on holiday, derailed at the entrance to Amiens station (Somme), about 350 m from the platforms, on a set of points. It had 21 coaches.',
                'de' => 'Am 13. August 1925 gegen 16.21 Uhr entgleiste der Paris - Boulogne, ein zum Feiertag am 15. August eingesetzter Verstärkungszug mit überwiegend Reisenden der dritten Klasse, bei der Einfahrt in den Bahnhof Amiens (Somme), etwa 350 m vor den Bahnsteigen, an einer Weiche. Er bestand aus 21 Wagen.',
                'sources' => ['wp-liste-1925', 'amiens-jda-2025', 'france3-2025'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon le tachymètre, le train entrait en gare à 92 km/h alors que la vitesse imposée sur l\'aiguille était de 40 km/h. Dix-huit des 21 voitures ont quitté les rails et se sont disloquées ; les voitures anciennes à deux essieux, éclairées au gaz, ont pris feu.',
                'en' => 'According to the tachograph, the train was entering the station at 92 km/h where the speed limit on the points was 40 km/h. Eighteen of the 21 coaches left the rails and broke up; the old two-axle coaches, lit by gas, caught fire.',
                'de' => 'Nach dem Tachographen fuhr der Zug mit 92 km/h in den Bahnhof ein, obwohl auf der Weiche 40 km/h vorgeschrieben waren. Achtzehn der 21 Wagen sprangen aus den Gleisen und zerbrachen; die alten zweiachsigen, mit Gas beleuchteten Wagen gerieten in Brand.',
                'sources' => ['wp-liste-1925', 'amiens-jda-2025', 'france3-2025'],
            ],
            [
                'fr' => 'Les secours ont mobilisé des cheminots, des pompiers, des employés des PTT et des soldats du 51e régiment d\'infanterie d\'Amiens ; une grue de 50 tonnes est venue de Longueau. Les blessés ont été conduits à l\'Hôtel-Dieu et dans une clinique de la ville. Le ministre des Travaux publics, Pierre Laval, s\'est rendu sur place le soir même.',
                'en' => 'The rescue involved railwaymen, firefighters, post office staff and soldiers of the 51st infantry regiment based in Amiens; a 50-tonne crane was brought from Longueau. The injured were taken to the Hôtel-Dieu and to a private clinic in the city. The Minister of Public Works, Pierre Laval, went to the site that evening.',
                'de' => 'Die Rettung mobilisierte Eisenbahner, Feuerwehrleute, Postangestellte und Soldaten des in Amiens liegenden 51. Infanterieregiments; aus Longueau wurde ein 50-Tonnen-Kran herangeschafft. Die Verletzten wurden ins Hôtel-Dieu und in eine Klinik der Stadt gebracht. Der Minister für öffentliche Arbeiten, Pierre Laval, begab sich noch am selben Abend zur Unfallstelle.',
                'sources' => ['amiens-jda-2025', 'france3-2025'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait 12 morts et environ 160 blessés, dont une soixantaine à 70 grièvement atteints selon France 3. Neuf corps ont été retirés d\'abord des décombres, dont celui du chef de train, et quatre victimes sont mortes après les premiers secours.',
                'en' => 'The accident left 12 dead and about 160 injured, around 70 of them seriously according to France 3. Nine bodies were first recovered from the wreckage, including the train manager, and four victims died after the first rescue.',
                'de' => 'Der Unfall forderte 12 Tote und etwa 160 Verletzte, davon nach France 3 rund 70 schwer. Zunächst wurden neun Leichen aus den Trümmern geborgen, darunter die des Zugführers; vier Opfer starben nach der ersten Rettung.',
                'sources' => ['wp-liste-1925', 'amiens-jda-2025', 'france3-2025'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La vitesse excessive a été identifiée très vite comme la cause principale. La gravité du bilan est attribuée à la composition du train : du matériel léger, ancien et prévu pour 40 à 50 km/h, éclairé au gaz, tracté par une locomotive capable de 120 km/h.',
                'en' => 'Excessive speed was quickly identified as the main cause. The severity of the toll is attributed to the makeup of the train: light, old rolling stock designed for 40 to 50 km/h, gas-lit, hauled by a locomotive able to reach 120 km/h.',
                'de' => 'Die überhöhte Geschwindigkeit wurde schnell als Hauptursache erkannt. Die Schwere der Bilanz wird der Zusammensetzung des Zuges zugeschrieben: leichtes, altes, für 40 bis 50 km/h ausgelegtes, gasbeleuchtetes Wagenmaterial, gezogen von einer Lokomotive, die 120 km/h erreichen konnte.',
                'sources' => ['wp-liste-1925', 'france3-2025'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le mécanicien, venu du dépôt de Calais, a été incarcéré à Amiens quelques heures après l\'accident puis inculpé d\'homicide involontaire sur la base du rapport du directeur du contrôle. Il niait avoir roulé trop vite et mettait en cause les freins de la locomotive. Les articles lus ne rapportent ni procès ni jugement.',
                'en' => 'The driver, from the Calais depot, was jailed in Amiens a few hours after the accident and then charged with involuntary manslaughter on the basis of the report of the head of the inspection service. He denied driving too fast and blamed the locomotive\'s brakes. The articles read report neither a trial nor a verdict.',
                'de' => 'Der Lokführer aus dem Depot Calais wurde wenige Stunden nach dem Unfall in Amiens inhaftiert und aufgrund des Berichts des Leiters der Aufsichtsbehörde wegen fahrlässiger Tötung angeklagt. Er bestritt, zu schnell gefahren zu sein, und machte die Bremsen der Lokomotive verantwortlich. Die gelesenen Artikel berichten weder von einem Prozess noch von einem Urteil.',
                'sources' => ['wp-liste-1925', 'france3-2025', 'amiens-jda-2025'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche ne donnait pas l\'heure : 16 h 21 d\'après la liste de Wikipédia et les deux articles de 2025. Le numéro du train diffère : 313 selon le journal municipal, 37 selon France 3. Une dépêche de presse étrangère de 1925 évoquait des chiffres plus bas (dix puis onze morts) ; elle n\'a pas pu être lue et n\'est pas retenue.',
                'en' => 'The record gave no time: 4.21 pm according to the Wikipedia list and both 2025 articles. The train number differs: 313 according to the municipal magazine, 37 according to France 3. A 1925 foreign press report mentioned lower figures (ten, then eleven dead); it could not be read and is not retained.',
                'de' => 'Der Datensatz enthielt keine Uhrzeit: 16.21 Uhr nach der Wikipedia-Liste und beiden Artikeln von 2025. Die Zugnummer weicht ab: 313 laut dem städtischen Magazin, 37 laut France 3. Eine ausländische Pressemeldung von 1925 nannte niedrigere Zahlen (zehn, dann elf Tote); sie konnte nicht gelesen werden und wird nicht berücksichtigt.',
                'sources' => ['wp-liste-1925', 'amiens-jda-2025', 'france3-2025'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'occurred_at_time',
            'value' => '16:21',
            'sources' => ['amiens-jda-2025', 'france3-2025'],
            'fr' => 'Heure indiquée par deux articles indépendants de 2025 et par la liste de Wikipédia : 16 h 21.',
            'en' => 'Time given by two independent 2025 articles and by the Wikipedia list: 4.21 pm.',
            'de' => 'Uhrzeit nach zwei unabhängigen Artikeln von 2025 und der Wikipedia-Liste: 16.21 Uhr.',
        ],
    ],
];
