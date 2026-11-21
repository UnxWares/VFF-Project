<?php

// Contexte sourcé de l'accident de Magny-Saint-Médard du 17 janvier 1925 (recherche du 21/11/2026).
return [
    'slug' => '1925-01-17-magny-saint-medard',
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
            'locator' => '17 janvier 1925, Magny-Saint-Médard',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1925&oldid=235357727#1925',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Donne l\'heure, la ligne, la panne de frein, le déraillement près du pont sur l\'Albane, 5 morts et 3 blessés graves ; cite Le Matin et Le Temps du 19 janvier 1925.',
        ],
        'cehm-vingeanne' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Ligne de la Vingeanne, de Dijon à Champlitte, 60 km',
            'publisher' => 'Site cehm.ville-st-apollinaire.fr (histoire locale, Saint-Apollinaire)',
            'author' => null,
            'published' => '2024',
            'locator' => 'Paragraphe sur la gare de Magny-Saint-Médard',
            'url' => 'https://cehm.ville-st-apollinaire.fr/wp-content/uploads/sites/33/2024/04/Ligne-de-la-Vingeanne.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brochure d\'histoire locale sur le « Tacot » ; s\'appuie sur Thévenot (Chemins de fer régionaux et urbains n° 155, 1979) et sur Artur, Bachet et Cheveau (2012). Date de publication déduite de l\'adresse du fichier.',
        ],
        'le-matin-1925-01-19' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 19 janvier 1925, pages 1 et 3',
            'publisher' => 'Le Matin (numérisé sur Gallica)',
            'author' => null,
            'published' => '1925-01-19',
            'locator' => 'pages 1 et 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k574947m',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 17 janvier 1925, vers 18 h 20, un train mixte roulait sur la ligne de Dijon à Champlitte des Chemins de fer départementaux de la Côte-d\'Or, le « Tacot » de la Vingeanne, dans la longue descente qui mène à Magny-Saint-Médard (Côte-d\'Or). La pente de cette rampe est décrite comme très forte.',
                'en' => 'On 17 January 1925, at about 6.20 pm, a mixed train was running on the Dijon to Champlitte line of the Côte-d\'Or departmental railways, the "Tacot" of the Vingeanne valley, down the long descent towards Magny-Saint-Médard (Côte-d\'Or). The gradient is described as very steep.',
                'de' => 'Am 17. Januar 1925 gegen 18.20 Uhr fuhr ein gemischter Zug auf der Strecke von Dijon nach Champlitte der Departementsbahnen der Côte-d\'Or, dem „Tacot“ des Vingeanne-Tals, das lange Gefälle nach Magny-Saint-Médard (Côte-d\'Or) hinunter. Das Gefälle wird als sehr steil beschrieben.',
                'sources' => ['wp-liste-1925', 'cehm-vingeanne'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La liste de Wikipédia et la brochure locale concordent sur la panne de frein : le frein n\'agissait plus et le train s\'est emballé. La locomotive a déraillé et a basculé au bas du talus ; le mécanicien et le chauffeur ont pu être sortis vivants de leur machine.',
                'en' => 'The Wikipedia list and the local brochure agree on the brake failure: the brake no longer worked and the train ran away. The locomotive derailed and tumbled down the embankment; the driver and the fireman were got out of their engine alive.',
                'de' => 'Die Wikipedia-Liste und die Heimatbroschüre stimmen beim Bremsversagen überein: Die Bremse wirkte nicht mehr, und der Zug geriet außer Kontrolle. Die Lokomotive entgleiste und stürzte die Böschung hinunter; Lokführer und Heizer konnten lebend aus ihrer Maschine geborgen werden.',
                'sources' => ['wp-liste-1925', 'cehm-vingeanne'],
            ],
            [
                'fr' => 'Selon la liste de Wikipédia, le déraillement s\'est produit dans la courbe précédant un pont sur l\'Albane ; le wagon-poste s\'est écrasé roues en l\'air dans la rivière. Selon la brochure, le premier wagon est resté à peu près sur la voie, le deuxième s\'est couché, vide, et le troisième est tombé dans l\'Albane, partiellement écrasé par le fourgon qui le suivait.',
                'en' => 'According to the Wikipedia list, the derailment occurred in the curve before a bridge over the Albane, and the mail van ended up upside down in the river. According to the brochure, the first wagon stayed more or less on the track, the second lay on its side, empty, and the third fell into the Albane, partly crushed by the van following it.',
                'de' => 'Laut der Wikipedia-Liste ereignete sich die Entgleisung in der Kurve vor einer Brücke über die Albane, und der Postwagen blieb mit den Rädern nach oben im Fluss liegen. Laut der Broschüre blieb der erste Wagen mehr oder weniger auf dem Gleis, der zweite kippte um und war leer, der dritte stürzte in die Albane und wurde teilweise vom nachfolgenden Gepäckwagen erdrückt.',
                'sources' => ['wp-liste-1925', 'cehm-vingeanne'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait cinq morts selon les deux sources. La brochure précise que le troisième véhicule comptait huit victimes, dont quatre sont mortes sur le coup et une cinquième peu après. Elle compte cinq blessés ; la liste de Wikipédia parle de trois blessés graves.',
                'en' => 'The accident killed five people according to both sources. The brochure states that the third vehicle held eight casualties, four of whom died at once and a fifth shortly afterwards. It counts five injured; the Wikipedia list speaks of three seriously injured.',
                'de' => 'Bei dem Unglück starben nach beiden Quellen fünf Menschen. Die Broschüre gibt an, dass im dritten Fahrzeug acht Opfer waren, von denen vier sofort und ein fünftes kurz danach starben. Sie zählt fünf Verletzte; die Wikipedia-Liste spricht von drei Schwerverletzten.',
                'sources' => ['wp-liste-1925', 'cehm-vingeanne'],
            ],
        ],
        'memory' => [
            [
                'fr' => 'Selon la brochure d\'histoire locale, c\'est l\'accident le plus lourd qu\'ait connu la ligne.',
                'en' => 'According to the local history brochure, it was the worst accident the line ever had.',
                'de' => 'Laut der heimatkundlichen Broschüre war es der schwerste Unfall, den die Strecke je erlebte.',
                'sources' => ['cehm-vingeanne'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le sens de marche diffère : la liste de Wikipédia parle d\'un train venant de Champlitte, la brochure d\'un train parti de Mirebeau et se dirigeant vers Dijon. Le nombre de blessés diffère aussi (trois blessés graves ou cinq blessés).',
                'en' => 'The direction of travel differs: the Wikipedia list speaks of a train coming from Champlitte, the brochure of a train leaving Mirebeau for Dijon. The number of injured also differs (three seriously injured or five injured).',
                'de' => 'Die Fahrtrichtung weicht ab: Die Wikipedia-Liste spricht von einem Zug aus Champlitte, die Broschüre von einem Zug, der in Mirebeau abgefahren war und nach Dijon fuhr. Auch die Zahl der Verletzten weicht ab (drei Schwerverletzte oder fünf Verletzte).',
                'sources' => ['wp-liste-1925', 'cehm-vingeanne'],
            ],
        ],
    ],
    'corrections' => [],
];
