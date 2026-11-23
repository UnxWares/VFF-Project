<?php

// Contexte sourcé de l'accident de Saint-Just-en-Chaussée du 17 janvier 1932 (recherche du 21/11/2026).
return [
    'slug' => '1932-01-17-saint-just-en-chaussee',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-1932' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1932',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Janvier, 17 janvier 1932',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1932&oldid=236417113#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée sourcée par Le Petit Parisien du 18 janvier 1932 (p. 1) et Le Figaro du 19 janvier 1932 (p. 2), non lus ; seul document lu sur cet accident.',
        ],
        'ddb-1932-01-18-muenchner' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Münchner Neueste Nachrichten du 18 janvier 1932',
            'publisher' => 'Münchner Neueste Nachrichten (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1932-01-18',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/ARM2WDWMCOYMVQSHBNHLNHWO37B3CWRH?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Dépêche de Paris du 18 janvier : sur la ligne Paris-Abbeville, un train de voyageurs déraille dans le nord-est de la France ; 10 morts et 20 blessés graves dans le premier bilan.',
        ],
        'kb-1932-01-19-nieuwe-venlosche-courant' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'FRANKRIJK DE ONTSPORING BIJ ST. JUST-EN-CHAUSSEé.',
            'publisher' => 'Nieuwe Venlosche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1932-01-19',
            'locator' => 'article « FRANKRIJK DE ONTSPORING BIJ ST. JUST-EN-CHAUSSEé. », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMGAVL01:000013657:mpeg21:a0008:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Article du 19 janvier : déraillement d\'un train de voyageurs à Saint-Just-en-Chaussée, 10 morts et 20 blessés ; rupture d\'un essieu probable selon Havas ; quatre voitures déraillent juste avant la gare et une cabine d\'aiguilleur est heurtée.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 17 janvier 1932, vers 19 h 10, l\'omnibus Paris - Abbeville a déraillé à l\'entrée de la gare de Saint-Just-en-Chaussée (Oise), sur la ligne de Paris-Nord à Lille.',
                'en' => 'On 17 January 1932, at about 7.10 pm, the Paris - Abbeville stopping train derailed at the entrance to Saint-Just-en-Chaussée station (Oise), on the Paris-Nord to Lille line.',
                'de' => 'Am 17. Januar 1932 gegen 19.10 Uhr entgleiste der Personenzug Paris - Abbeville bei der Einfahrt in den Bahnhof Saint-Just-en-Chaussée (Oise) auf der Strecke von Paris-Nord nach Lille.',
                'sources' => ['wp-liste-1932'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les voitures de troisième classe ont été détruites et ont écrasé la cabine d\'aiguillage voisine.',
                'en' => 'The third-class coaches were destroyed and crushed the neighbouring signal box.',
                'de' => 'Die Wagen der dritten Klasse wurden zerstört und drückten das benachbarte Stellwerk ein.',
                'sources' => ['wp-liste-1932'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les Münchner Neueste Nachrichten du 18 janvier 1932 annoncent, dans une dépêche de Paris du 18, le déraillement d\'un train de voyageurs sur la ligne Paris-Abbeville avec 10 morts et 20 blessés graves dans le premier bilan, contre 11 morts dans la fiche. Date, lieu et nature sont confirmés.',
                'en' => 'The Münchner Neueste Nachrichten of 18 January 1932 report, in a Paris dispatch of the 18th, the derailment of a passenger train on the Paris-Abbeville line with 10 dead and 20 seriously injured in the first toll, against 11 dead in the record Date, place and nature are confirmed.',
                'de' => 'Die Münchner Neuesten Nachrichten vom 18. Januar 1932 melden in einer Pariser Meldung vom 18. die Entgleisung eines Reisezugs auf der Strecke Paris-Abbeville mit 10 Toten und 20 Schwerverletzten in der ersten Bilanz, gegenüber 11 Toten in der Datenbank Datum, Ort und Art sind bestätigt.',
                'sources' => ['ddb-1932-01-18-muenchner'],
            ],
            [
                'fr' => 'La Nieuwe Venlosche Courant du 19 janvier 1932 confirme le déraillement d\'un train de voyageurs à Saint-Just-en-Chaussée, avec quatre voitures sorties des rails juste avant la gare, 10 morts et 20 blessés et une rupture d\'essieu probable selon Havas, contre 11 morts dans la fiche. Lieu et nature sont confirmés ; le bilan diffère d\'un mort.',
                'en' => 'The Nieuwe Venlosche Courant of 19 January 1932 confirms the derailment of a passenger train at Saint-Just-en-Chaussée, with four carriages off the rails just before the station, 10 dead and 20 injured and a probable broken axle according to Havas, against 11 dead in the record. Place and nature are confirmed; the toll differs by one death.',
                'de' => 'Die Nieuwe Venlosche Courant vom 19. Januar 1932 bestätigt die Entgleisung eines Reisezugs in Saint-Just-en-Chaussée, mit vier Wagen, die kurz vor dem Bahnhof aus den Schienen sprangen, 10 Toten und 20 Verletzten und einem wahrscheinlichen Achsbruch nach Havas, gegenüber 11 Toten in der Datenbank. Ort und Art sind bestätigt; die Bilanz weicht um einen Toten ab.',
                'sources' => ['kb-1932-01-19-nieuwe-venlosche-courant'],
            ],
            [
                'fr' => 'Le bilan donné par la liste est de onze morts et une quinzaine de blessés.',
                'en' => 'The list gives a toll of eleven dead and about fifteen injured.',
                'de' => 'Die Liste nennt elf Tote und etwa fünfzehn Verletzte.',
                'sources' => ['wp-liste-1932'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'D\'après la liste, l\'enquête n\'a pas pu établir si la cause était une rupture d\'essieu ou un écartement de la voie.',
                'en' => 'According to the list, the inquiry could not establish whether the cause was a broken axle or a track gauge widening.',
                'de' => 'Laut der Liste konnte die Untersuchung nicht klären, ob die Ursache ein Achsbruch oder eine Spurerweiterung war.',
                'sources' => ['wp-liste-1932'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés (une quinzaine) figure dans la liste mais pas dans la fiche. Des dépêches étrangères de 1932 évoquent dix morts et trente blessés à « Saint-Just » ; elles n\'ont pas pu être lues et ne sont pas retenues. Un seul document lu, lui-même secondaire.',
                'en' => 'The injury count (about fifteen) appears in the list but not in the record. Foreign press reports of 1932 mention ten dead and thirty injured at "St. Just"; they could not be read and are not retained. Only one document was read, itself secondary.',
                'de' => 'Die Verletztenzahl (etwa fünfzehn) steht in der Liste, aber nicht im Datensatz. Ausländische Pressemeldungen von 1932 nennen zehn Tote und dreißig Verletzte in „St. Just“; sie konnten nicht gelesen werden und werden nicht berücksichtigt. Es wurde nur ein Dokument gelesen, das selbst sekundär ist.',
                'sources' => ['wp-liste-1932'],
            ],
        ],
    ],
    'corrections' => [],
];
