<?php

// Contexte sourcé du déraillement d'un train pour Tours en gare de Beuxes, ligne des Sables-d'Olonne à Tours, de juillet 1895 (recherche du 21/11/2026).
return [
    'slug' => '1895-07-14-beuxes',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1895',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1895',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 14 juillet 1895, d\'après La Presse du 17 juillet : en gare de Beuxes, près de Chinon, sur la ligne des Sables-d\'Olonne à Tours, vers 23 h, un train pour Tours déraille à la suite d\'une erreur d\'aiguillage et sa locomotive se couche ; le mécanicien est tué, le chauffeur, un serre-frein et trois voyageurs blessés.',
        ],
        'ddb-1895-07-20-hamburg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Entgleisung eines Personenzuges. Loudun, 16. Juli. (Telegramm.)',
            'publisher' => 'Hamburger freie Presse (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1895-07-20',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/4O2LDMFY3DVU3NBQBY3M4D2QXLIIORZE?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme de Loudun du 16 juillet (même texte dans le Schwäbischer Merkur et Der Wächter du 20 juillet) : la nuit dernière, sur le réseau de l\'État, entre Loudun et Chinon, un train de voyageurs a déraillé dans une petite gare à cause d\'une mauvaise position d\'aiguille ; le mécanicien a été décapité, le chauffeur mortellement blessé, un serre-frein et plusieurs voyageurs légèrement blessés.',
        ],
        'ddb-1895-07-20-merkur' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Aus Loudun (Departement Vienne) 16. Juli',
            'publisher' => 'Schwäbischer Merkur (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1895-07-20',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/ZJLOFV2UNXLRICWJWS6USVSE6K4EBCXW?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même télégramme de Loudun du 16 juillet : déraillement entre Loudun et Chinon, mécanicien tué, chauffeur mortellement blessé, serre-frein et plusieurs voyageurs légèrement blessés, aiguille mal placée.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Dans la nuit du 14 juillet 1895, vers 23 h, un train de voyageurs pour Tours a déraillé en gare de Beuxes, près de Chinon, sur la ligne des Sables-d\'Olonne à Tours exploitée par l\'État, à cause d\'une aiguille mal placée. La locomotive s\'est couchée.',
                'en' => 'During the night of 14 July 1895, at about 11 pm, a passenger train for Tours derailed at Beuxes station near Chinon, on the State-run Les Sables-d\'Olonne to Tours line, because of wrongly set points. The engine fell on its side.',
                'de' => 'In der Nacht des 14. Juli 1895 gegen 23 Uhr entgleiste ein Personenzug nach Tours im Bahnhof Beuxes bei Chinon an der Staatsbahnstrecke Les Sables-d\'Olonne-Tours wegen einer falsch gestellten Weiche. Die Lokomotive stürzte um.',
                'sources' => ['wp-liste-1890', 'ddb-1895-07-20-hamburg', 'ddb-1895-07-20-merkur'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été tué. Le chauffeur a été grièvement blessé, mortellement selon le télégramme de Loudun ; un serre-frein et plusieurs voyageurs, trois selon la liste de Wikipédia, ont été légèrement blessés.',
                'en' => 'The driver was killed. The stoker was seriously injured, fatally according to the Loudun telegram; a brakeman and several passengers, three according to the Wikipedia list, were slightly hurt.',
                'de' => 'Der Lokführer wurde getötet. Der Heizer wurde schwer verletzt, nach dem Telegramm aus Loudun tödlich; ein Bremser und mehrere Reisende, nach der Wikipedia-Liste drei, wurden leicht verletzt.',
                'sources' => ['wp-liste-1890', 'ddb-1895-07-20-hamburg'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le télégramme de Loudun dit le chauffeur mortellement blessé ; la liste de Wikipédia le compte parmi les blessés et ne donne qu\'un mort.',
                'en' => 'The Loudun telegram says the stoker was fatally injured; the Wikipedia list counts him among the injured and gives only one death.',
                'de' => 'Das Telegramm aus Loudun nennt den Heizer tödlich verletzt; die Wikipedia-Liste zählt ihn zu den Verletzten und nennt nur einen Toten.',
                'sources' => ['ddb-1895-07-20-hamburg', 'wp-liste-1890'],
            ],
            [
                'fr' => 'Le télégramme de Loudun, daté du 16 juillet, place l\'accident « la nuit dernière », soit dans la nuit du 15 au 16 juillet ; la liste de Wikipédia le date du 14 juillet vers 23 h.',
                'en' => 'The Loudun telegram, dated 16 July, places the accident "last night", that is the night of 15 to 16 July; the Wikipedia list dates it 14 July at about 11 pm.',
                'de' => 'Das Telegramm aus Loudun vom 16. Juli setzt den Unfall in die „verflossene Nacht“, also die Nacht vom 15. auf den 16. Juli; die Wikipedia-Liste datiert ihn auf den 14. Juli gegen 23 Uhr.',
                'sources' => ['ddb-1895-07-20-hamburg', 'ddb-1895-07-20-merkur', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1895-07-20-hamburg', 'ddb-1895-07-20-merkur'],
            'fr' => 'Mécanicien tué selon la liste de Wikipédia (La Presse du 17 juillet 1895) et le télégramme de Loudun, qui dit aussi le chauffeur mortellement blessé ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver killed according to the Wikipedia list (La Presse of 17 July 1895) and the Loudun telegram, which also says the stoker was fatally injured; the record gave no number of deaths.',
            'de' => 'Lokführer getötet nach der Wikipedia-Liste (La Presse vom 17. Juli 1895) und dem Telegramm aus Loudun, das den Heizer außerdem tödlich verletzt nennt; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
