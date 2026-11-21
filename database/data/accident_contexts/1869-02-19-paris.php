<?php

// Contexte sourcé de la collision à l'entrée du tunnel des Buttes-Chaumont du 19 février 1869 (recherche du 21/11/2026).
return [
    'slug' => '1869-02-19-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1869',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1869',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après Le Figaro du 20 février 1869 : sur la ligne de Petite Ceinture, vers 10 h, à l\'entrée du tunnel des Buttes-Chaumont, collision entre un train de marchandises venant de Bercy et un train de voyageurs venant de La Villette, dont le mécanicien et le chauffeur sont tués.',
        ],
        'ddb-1869-02-25-koelnische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 23. Febr. Am 19. d. Mts. gegen 9 Uhr Morgens stieß beim Eintritt in den Tunnel der Buttes Chaumont ein Güterzug',
            'publisher' => 'Kölnische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1869-02-25',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/YPIKJA5RUCCFQZH4ZBCMYLYAO7LYMQ5E?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Correspondance de Paris du 23 février : le 19 vers 9 h, à l\'entrée du tunnel des Buttes-Chaumont, un train de marchandises venant de Bercy heurte un train de voyageurs venant de La Villette ; mécanicien et chauffeur de ce train broyés ; sept à huit wagons empilés, voitures brisées ; secours avec des torches ; environ 33 blessés graves.',
        ],
        'ddb-1869-02-27-regensburger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 23. Febr. (Näheres über einen bereits telegraphisch gemeldeten Eisenbahnunfall)',
            'publisher' => 'Regensburger Tagblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1869-02-27',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/JZLJERRE2SGPQQCNE2W7Q5IL4CQG74MM?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même correspondance, reprise aussi par l\'Echo der Gegenwart du 26 février et le Münchener Tages-Anzeiger du 28 février : collision à l\'entrée du tunnel des Buttes-Chaumont, mécanicien et chauffeur du train de voyageurs broyés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 19 février 1869 au matin, sur la ligne de Petite Ceinture à Paris, un train de marchandises venant de Bercy et un train de voyageurs venant de La Villette se sont heurtés à l\'entrée du tunnel des Buttes-Chaumont.',
                'en' => 'On the morning of 19 February 1869, on the Petite Ceinture line in Paris, a goods train from Bercy and a passenger train from La Villette collided at the entrance of the Buttes-Chaumont tunnel.',
                'de' => 'Am Morgen des 19. Februar 1869 stießen auf der Pariser Petite Ceinture an der Einfahrt des Tunnels der Buttes-Chaumont ein Güterzug aus Bercy und ein Personenzug aus La Villette zusammen.',
                'sources' => ['wp-liste-1860', 'ddb-1869-02-25-koelnische', 'ddb-1869-02-27-regensburger'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Sept à huit wagons de marchandises se sont empilés et plusieurs voitures de voyageurs ont été brisées. Dans le tunnel, les voyageurs ont cherché la sortie dans l\'obscurité et la fumée, jusqu\'à ce que des secours entrent avec des torches.',
                'en' => 'Seven or eight goods wagons piled up and several passenger coaches were smashed. Inside the tunnel, passengers groped for the way out in darkness and smoke until rescuers came in with torches.',
                'de' => 'Sieben bis acht Güterwagen türmten sich aufeinander, mehrere Personenwagen wurden zertrümmert. Im Tunnel suchten die Reisenden in Dunkelheit und Rauch nach dem Ausgang, bis Helfer mit Fackeln eindrangen.',
                'sources' => ['ddb-1869-02-25-koelnische'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur du train de voyageurs ont été tués. La presse allemande estime à 33 le nombre de blessés graves et ajoute que presque tous les voyageurs ont été contusionnés.',
                'en' => 'The driver and the fireman of the passenger train were killed. The German press put the number of seriously injured at 33 and adds that almost all passengers were bruised.',
                'de' => 'Lokomotivführer und Heizer des Personenzuges wurden getötet. Die deutsche Presse schätzt die Zahl der Schwerverwundeten auf 33 und fügt hinzu, dass fast alle Reisenden Quetschungen davontrugen.',
                'sources' => ['wp-liste-1860', 'ddb-1869-02-25-koelnische', 'ddb-1869-02-27-regensburger'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'L\'heure diffère : vers 9 h dans la presse allemande, vers 10 h dans la liste de Wikipédia. La liste ne donne pas de nombre de blessés.',
                'en' => 'The time differs: about 9 am in the German press, about 10 am in the Wikipedia list. The list gives no number of injured.',
                'de' => 'Die Uhrzeit weicht ab: gegen 9 Uhr in der deutschen Presse, gegen 10 Uhr in der Wikipedia-Liste. Die Liste nennt keine Zahl von Verletzten.',
                'sources' => ['ddb-1869-02-25-koelnische', 'wp-liste-1860'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['ddb-1869-02-25-koelnische', 'wp-liste-1860'],
            'fr' => 'Deux morts, le mécanicien et le chauffeur du train de voyageurs, selon la liste de Wikipédia et la presse allemande du 25 février 1869 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two dead, the driver and the fireman of the passenger train, according to the Wikipedia list and the German press of 25 February 1869; the record gave no number of deaths.',
            'de' => 'Zwei Tote, Lokomotivführer und Heizer des Personenzuges, nach der Wikipedia-Liste und der deutschen Presse vom 25. Februar 1869; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
