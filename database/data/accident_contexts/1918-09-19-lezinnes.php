<?php

// Contexte sourcé de l'accident du tunnel de Pacy (fiche Lézinnes) du 19 septembre 1918 (recherche du 21/11/2026).
return [
    'slug' => '1918-09-19-lezinnes',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-fr-liste-1918' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1918',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'section Septembre, entrée du 19 septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1918&oldid=238612092#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée brève qui cite Le Figaro et Le Petit Parisien du 20 septembre 1918, p. 1 ; seule source détaillée lue.',
        ],
        'wp-en-liste-1910' => [
            'provider' => 'wikipedia-en',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'List of rail accidents (1910-1919)',
            'publisher' => 'Wikipedia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1918, 19 septembre',
            'url' => 'https://en.wikipedia.org/wiki/List_of_rail_accidents_(1910%E2%80%931919)',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée très brève, appuyée sur Semmens (1994), p. 58 ; bilan approximatif de 30 morts et 100 blessés.',
        ],
        'wikiplm' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'association',
            'title' => '20 septembre 1918, Port-Sec-de-Pacy',
            'publisher' => 'WikiPLM (Rails d\'autrefois)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'http://wikiplm.railsdautrefois.fr/wikiPLM/index.php?title=20_septembre_1918%2C_Port-Sec-de-Pacy',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Fiche du wiki des chemins de fer PLM : le site n\'a pas pu être ouvert (certificat invalide) ; les extraits des résultats de recherche indiquent 20 septembre, 39 morts et 38 blessés ; à consulter.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 19 septembre 1918 à 4 h 30, un express Marseille-Paris circulait sur la ligne de Paris à Lyon entre les gares de Lézinnes et d\'Ancy-le-Franc, dans le département de l\'Yonne. Il s\'est immobilisé dans le tunnel de Pacy après la rupture d\'une conduite de frein.',
                'en' => 'On 19 September 1918 at 4.30 am, a Marseille to Paris express was running on the Paris to Lyon line between the stations of Lézinnes and Ancy-le-Franc, in the Yonne département. It came to a stop in the Pacy tunnel after a brake pipe broke.',
                'de' => 'Am 19. September 1918 um 4.30 Uhr fuhr ein Schnellzug Marseille-Paris auf der Strecke Paris-Lyon zwischen den Bahnhöfen Lézinnes und Ancy-le-Franc im Département Yonne. Er blieb im Tunnel von Pacy stehen, nachdem eine Bremsleitung gerissen war.',
                'sources' => ['wp-fr-liste-1918'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train arrêté a été heurté à pleine vitesse par le train qui le dédoublait, parti cinq minutes après lui. Les voitures disloquées ont été le lieu des pertes humaines.',
                'en' => 'The stopped train was struck at full speed by the train duplicating it, which had left five minutes behind it. The wrecked coaches were where the casualties occurred.',
                'de' => 'Der stehende Zug wurde mit voller Geschwindigkeit von dem Entlastungszug gerammt, der fünf Minuten nach ihm abgefahren war. In den zertrümmerten Wagen kam es zu den Opfern.',
                'sources' => ['wp-fr-liste-1918'],
            ],
            [
                'fr' => 'Une autre liste, anglophone, décrit la même catastrophe comme le détachement de trois voitures à l\'arrière d\'un train du PLM, suivi d\'une collision dans le tunnel de Pacy.',
                'en' => 'Another list, in English, describes the same disaster as three coaches breaking away from the rear of a PLM train, followed by a collision in the Pacy tunnel.',
                'de' => 'Eine andere, englischsprachige Liste beschreibt dieselbe Katastrophe als Abreißen von drei Wagen am Ende eines PLM-Zuges, gefolgt von einem Zusammenstoß im Tunnel von Pacy.',
                'sources' => ['wp-en-liste-1910'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan donné par la liste française est de 28 morts et 32 blessés ; la liste anglophone indique environ 30 morts et 100 blessés.',
                'en' => 'The toll given by the French list is 28 dead and 32 injured; the English list gives about 30 dead and 100 injured.',
                'de' => 'Die französische Liste nennt 28 Tote und 32 Verletzte; die englischsprachige Liste gibt etwa 30 Tote und 100 Verletzte an.',
                'sources' => ['wp-fr-liste-1918', 'wp-en-liste-1910'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les deux sources lues rattachent l\'accident à un incident de freinage ou de composition du premier train, qui l\'a immobilisé ou séparé en pleine voie. Aucun rapport d\'enquête n\'a pu être consulté : les circonstances précises (signalisation, espacement des trains) restent à documenter.',
                'en' => 'Both sources read link the accident to a braking or train-composition incident on the first train, which stopped it or split it on the open line. No inquiry report could be consulted: the precise circumstances (signalling, spacing of the trains) remain to be documented.',
                'de' => 'Beide gelesenen Quellen führen den Unfall auf einen Brems- oder Zugbildungsvorfall beim ersten Zug zurück, der ihn auf freier Strecke zum Stehen brachte oder trennte. Ein Untersuchungsbericht konnte nicht eingesehen werden: Die genauen Umstände (Signalisierung, Zugabstand) bleiben zu dokumentieren.',
                'sources' => ['wp-fr-liste-1918', 'wp-en-liste-1910'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche enregistre un ouvrage ferroviaire qui cède à Lézinnes, mais les deux listes lues décrivent une collision entre deux trains dans le tunnel de Pacy, entre les gares de Lézinnes et d\'Ancy-le-Franc. La nature de l\'accident est donc une collision, et non une défaillance d\'ouvrage.',
                'en' => 'The record lists a railway structure that gave way at Lézinnes, but the two lists read describe a collision between two trains in the Pacy tunnel, between the stations of Lézinnes and Ancy-le-Franc. The nature of the accident is therefore a collision, not a structural failure.',
                'de' => 'Der Eintrag verzeichnet ein Bauwerk, das bei Lézinnes versagt, doch beide gelesenen Listen beschreiben einen Zusammenstoß zweier Züge im Tunnel von Pacy zwischen den Bahnhöfen Lézinnes und Ancy-le-Franc. Die Art des Unfalls ist daher ein Zusammenstoß und kein Bauwerksversagen.',
                'sources' => ['wp-fr-liste-1918', 'wp-en-liste-1910'],
            ],
            [
                'fr' => 'Le bilan varie : 28 morts et 32 blessés d\'après la liste française, environ 30 morts et 100 blessés d\'après la liste anglophone.',
                'en' => 'The toll varies: 28 dead and 32 injured according to the French list, about 30 dead and 100 injured according to the English list.',
                'de' => 'Die Bilanz schwankt: 28 Tote und 32 Verletzte laut französischer Liste, etwa 30 Tote und 100 Verletzte laut englischsprachiger Liste.',
                'sources' => ['wp-fr-liste-1918', 'wp-en-liste-1910'],
            ],
        ],
    ],
];
