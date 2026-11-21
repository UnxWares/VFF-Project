<?php

// Contexte sourcé de l'accident d'Attichy du 20 janvier 1944 (recherche du 21/11/2026).
return [
    'slug' => '1944-01-20-attichy',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '20 janvier 1944, Attichy',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1944',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée sourcée par Le Matin du 21 janvier 1944 (p. 2) et Le Petit Parisien du 22 janvier 1944 (p. 3), non lus ; seul document trouvé sur cet accident.',
        ],
        'r60-attichy' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Compte-rendu du groupe Libération d\'Attichy',
            'publisher' => 'Résistance 60 (Oise)',
            'author' => null,
            'published' => '1944-10-04',
            'locator' => null,
            'url' => 'https://www.resistance60.fr/libe-nord-secteur-attichy',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Compte rendu du délégué cantonal du 4 octobre 1944 et notices sur la Résistance du secteur d\'Attichy ; ne mentionne aucun déraillement ni sabotage de voie ferrée en janvier 1944 (seuls des sabotages de camions allemands y figurent).',
        ],
        'r60-ffi-est' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Le secteur Est des FFI de l\'Oise',
            'publisher' => 'Résistance 60 (Oise)',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.resistance60.fr/ffi-secteur-est',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Organisation des groupes FFI (dont le groupe d\'Attichy) ; le seul fait ferroviaire cité est l\'incendie de 27 wagons près de la gare de Vieux-Moulin le 18 août 1944 ; rien sur Attichy en janvier 1944.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 20 janvier 1944, vers 10 h 30, un train à destination de Soissons a déraillé au lieu-dit Le Buissonnet, sur la commune d\'Attichy (Oise), sur la ligne de Compiègne à Soissons.',
                'en' => 'On 20 January 1944, at about 10.30 am, a train bound for Soissons derailed at the place called Le Buissonnet, in the commune of Attichy (Oise), on the Compiègne to Soissons line.',
                'de' => 'Am 20. Januar 1944 gegen 10.30 Uhr entgleiste ein Zug in Richtung Soissons an der Stelle Le Buissonnet in der Gemeinde Attichy (Oise) auf der Strecke von Compiègne nach Soissons.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La liste de Wikipédia attribue le déraillement à un sabotage de la voie : un rail avait été déboulonné. Cette indication repose sur la presse de l\'époque citée par la liste ; aucun autre document n\'a été trouvé pour la confirmer ni pour identifier les auteurs.',
                'en' => 'The Wikipedia list attributes the derailment to sabotage of the track: a rail had been unbolted. This rests on the contemporary press cited by the list; no other document was found to confirm it or to identify those responsible.',
                'de' => 'Die Wikipedia-Liste führt die Entgleisung auf eine Sabotage des Gleises zurück: Eine Schiene war losgeschraubt worden. Das stützt sich auf die von der Liste zitierte zeitgenössische Presse; kein weiteres Dokument bestätigt dies oder nennt die Täter.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan donné par la liste est de douze morts et une quinzaine de blessés.',
                'en' => 'The list gives a toll of twelve dead and about fifteen injured.',
                'de' => 'Die Liste nennt zwölf Tote und etwa fünfzehn Verletzte.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche classe l\'événement en déraillement et ne retient pas le nombre de blessés ; la liste de Wikipédia donne une quinzaine de blessés et décrit un sabotage. La nature (déraillement dû à un acte de sabotage en temps de guerre) est conservée telle quelle, faute de document complémentaire. Les chiffres reposent sur une seule source secondaire. Deux pages d\'histoire de la Résistance de l\'Oise consultées ne mentionnent pas cet événement, et les auteurs du sabotage ne sont pas identifiés.',
                'en' => 'The record classifies the event as a derailment and holds no injury count; the Wikipedia list gives about fifteen injured and describes sabotage. The nature (a derailment caused by a wartime act of sabotage) is kept as it stands, for lack of further documents. The figures rest on a single secondary source. Two Oise Resistance history pages that were consulted do not mention the event, and those responsible for the sabotage are not identified.',
                'de' => 'Der Datensatz stuft das Ereignis als Entgleisung ein und enthält keine Verletztenzahl; die Wikipedia-Liste nennt etwa fünfzehn Verletzte und beschreibt eine Sabotage. Die Art (eine durch einen kriegszeitlichen Sabotageakt verursachte Entgleisung) bleibt mangels weiterer Dokumente unverändert. Die Zahlen stützen sich auf eine einzige Sekundärquelle. Zwei eingesehene Seiten zur Geschichte der Résistance im Oise erwähnen das Ereignis nicht, und die Urheber der Sabotage sind nicht bekannt.',
                'sources' => ['wp-liste-1940', 'r60-attichy', 'r60-ffi-est'],
            ],
        ],
    ],
    'corrections' => [],
];
