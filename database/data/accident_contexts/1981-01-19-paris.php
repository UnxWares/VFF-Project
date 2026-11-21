<?php

// Contexte sourcé de la collision de deux trains du RER A à la gare d'Auber du 19 janvier 1981 (recherche du 21/11/2026).
return [
    'slug' => '1981-01-19-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-auber' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Botsing in metro Parijs eist dode',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1981-01-20',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:011204962:mpeg21:a0234',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1980' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1980',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1981',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1980&oldid=240619561#1981',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : collision d\'un train du RER A à quai en gare d\'Auber, 1 mort et 71 blessés dont 3 graves ; modification de la signalisation mise en cause.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le lundi 19 janvier 1981, vers 18 h, aux heures de pointe du soir, deux trains du RER A se sont heurtés à la gare d\'Auber, à Paris, près de l\'Opéra. Le premier train était à quai et sur le point de repartir quand le second est arrivé à pleine vitesse, selon la presse néerlandaise.',
                'en' => 'On Monday 19 January 1981, at about 6 pm, in the evening rush hour, two RER line A trains collided at Auber station in Paris, near the Opéra. The first train was at the platform and about to leave when the second arrived at full speed, according to the Dutch press.',
                'de' => 'Am Montag, dem 19. Januar 1981, gegen 18 Uhr, im Abendberufsverkehr, stießen im Bahnhof Auber in Paris, nahe der Oper, zwei Züge der RER-Linie A zusammen. Der erste Zug stand am Bahnsteig und wollte gerade abfahren, als der zweite nach Angaben der niederländischen Presse mit voller Geschwindigkeit einfuhr.',
                'sources' => ['presse-auber', 'wp-liste-1980'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un passager a été tué et 71 personnes ont été blessées, dont trois grièvement selon la liste de Wikipédia.',
                'en' => 'One passenger was killed and 71 people were injured, three of them seriously according to the Wikipedia list.',
                'de' => 'Ein Fahrgast wurde getötet und 71 Personen wurden verletzt, davon drei schwer nach der Wikipedia-Liste.',
                'sources' => ['presse-auber', 'wp-liste-1980'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste de Wikipédia, une modification de la signalisation et de la procédure d\'information a été mise en cause. Les syndicats de la RATP ont exigé un retour à la situation antérieure, sans succès. Les installations de signalisation de la ligne étaient de nature identique au block automatique lumineux de la SNCF.',
                'en' => 'According to the Wikipedia list, a change to the signalling and to the information procedure was blamed. RATP trade unions demanded a return to the earlier situation, without success. The line\'s signalling installations were of the same kind as SNCF\'s automatic luminous block.',
                'de' => 'Nach der Wikipedia-Liste wurde eine Änderung der Signalisierung und des Informationsverfahrens verantwortlich gemacht. Die Gewerkschaften der RATP forderten eine Rückkehr zum früheren Zustand, ohne Erfolg. Die Signalanlagen der Strecke entsprachen in ihrer Art dem automatischen Lichtblocksystem der SNCF.',
                'sources' => ['wp-liste-1980'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
