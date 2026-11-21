<?php

// Contexte sourcé du déraillement d'Aingeray de septembre 1878 (recherche du 21/11/2026).
return [
    'slug' => '1878-09-24-aingeray',
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
            'locator' => '24 septembre 1878, Aingeray',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1878',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Date du 24 septembre 1878, vers 3 h, rupture de bandage, cinq voitures déraillées ; 2 morts et 9 blessés ; cite Le Petit Parisien.',
        ],
        'vdr-aingeray' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1878, Déraillement à Aingeray (54)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1878-deraillement-a-aingeray/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Républicain du Tarn du 29 septembre 1878.',
        ],
        'republicain-tarn-1878-09-29' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Républicain du Tarn du 29 septembre 1878',
            'publisher' => 'Le Républicain du Tarn (numérisé sur Gallica)',
            'author' => null,
            'published' => '1878-09-29',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bd6t535449393/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le train direct n° 39, parti de Paris à 20 h 35, a déraillé vers quatre heures du matin à la hauteur d\'Aingeray (Meurthe-et-Moselle), entre les stations de Fontenoy et de Liverdun. Le bandage des roues avait été vérifié à Commercy et le train ne présentait rien d\'anormal à Toul ; il était remorqué par deux locomotives et comptait quatorze wagons.',
                'en' => 'Through train no. 39, which left Paris at 8.35 pm, derailed at about four in the morning at Aingeray (Meurthe-et-Moselle), between Fontenoy and Liverdun stations. The wheel tyres had been checked at Commercy and the train showed nothing abnormal at Toul; it was hauled by two locomotives and had fourteen wagons.',
                'de' => 'Der Schnellzug Nr. 39, der um 20.35 Uhr in Paris abgefahren war, entgleiste gegen vier Uhr morgens bei Aingeray (Meurthe-et-Moselle), zwischen den Stationen Fontenoy und Liverdun. Die Radreifen waren in Commercy geprüft worden, und in Toul zeigte der Zug nichts Auffälliges; er wurde von zwei Lokomotiven gezogen und bestand aus vierzehn Wagen.',
                'sources' => ['vdr-aingeray', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Au poteau kilométrique 339, le bandage de la septième voiture, un wagon allemand du service Paris-Vienne, s\'est rompu et le wagon a été projeté hors de la voie. Il a entraîné cinq autres voitures dans une course de quatre à cinq cents mètres sur le ballast. L\'une a heurté un ponceau, arraché deux mètres de parapet et fini au fond du remblai, toit en terre ; une autre a été broyée.',
                'en' => 'At kilometre post 339, the tyre of the seventh coach, a German car on the Paris to Vienna service, broke and the car was thrown off the track. It dragged five other coaches along for four to five hundred metres on the ballast. One struck a culvert, tore out two metres of parapet and ended at the bottom of the embankment, roof down; another was crushed.',
                'de' => 'Am Kilometerstein 339 brach der Radreifen des siebten Wagens, eines deutschen Wagens im Dienst Paris-Wien, und der Wagen wurde aus dem Gleis geschleudert. Er riss fünf weitere Wagen auf einer Strecke von vier- bis fünfhundert Metern über den Schotter mit. Einer prallte gegen einen Durchlass, riss zwei Meter Brüstung heraus und landete am Fuß der Böschung auf dem Dach; ein anderer wurde zerdrückt.',
                'sources' => ['vdr-aingeray'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le journal compte deux morts et neuf blessés, presque tous de simples contusions, des voyageurs d\'Alsace-Lorraine, d\'Allemagne et de Bavière ; le train ne prenait pas de voyageurs à Nancy, ce qui explique selon lui qu\'aucune victime ne soit du département. Le maire d\'Aingeray et les habitants, réveillés par le bruit, ont dégagé et soigné les voyageurs.',
                'en' => 'The newspaper counts two dead and nine injured, almost all simple bruises, travellers from Alsace-Lorraine, Germany and Bavaria; the train took no passengers at Nancy, which it gives as the reason none of the victims was from the department. The mayor of Aingeray and the inhabitants, woken by the noise, freed and tended the passengers.',
                'de' => 'Die Zeitung zählt zwei Tote und neun Verletzte, fast alle mit einfachen Prellungen, Reisende aus Elsass-Lothringen, Deutschland und Bayern; der Zug nahm in Nancy keine Reisenden auf, was nach ihrer Darstellung erklärt, warum keines der Opfer aus dem Département stammte. Der Bürgermeister von Aingeray und die vom Lärm geweckten Einwohner befreiten und versorgten die Reisenden.',
                'sources' => ['vdr-aingeray', 'wp-liste-1870'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'La circulation a été rétablie sur la voie de droite dès six heures du matin et sur la voie de gauche à midi. Le train poste qui suivait de quelques minutes est entré à Nancy à l\'heure ordinaire.',
                'en' => 'Traffic was restored on the right-hand track from six in the morning and on the left-hand track at noon. The mail train following a few minutes behind reached Nancy at the usual time.',
                'de' => 'Der Verkehr wurde auf dem rechten Gleis ab sechs Uhr morgens und auf dem linken Gleis um Mittag wieder aufgenommen. Der wenige Minuten folgende Postzug erreichte Nancy zur gewohnten Zeit.',
                'sources' => ['vdr-aingeray'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia donne vers 3 h du matin, le Républicain du Tarn vers 4 h, et la date du 24 septembre vient de la liste seule, le journal du 29 septembre parlant de « ce matin » dans un article repris sans date.',
                'en' => 'The Wikipedia list gives about 3 am, the Républicain du Tarn about 4 am, and the date of 24 September comes from the list alone, the newspaper of 29 September speaking of "this morning" in an article reproduced without a date.',
                'de' => 'Die Wikipedia-Liste nennt gegen 3 Uhr morgens, das Républicain du Tarn gegen 4 Uhr, und das Datum 24. September stammt allein aus der Liste, da die Zeitung vom 29. September in einem ohne Datum übernommenen Artikel von „heute Morgen“ spricht.',
                'sources' => ['vdr-aingeray', 'wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [],
];
