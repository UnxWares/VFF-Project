<?php

// Contexte sourcé de la collision d'un train du réseau du Cher avec un train Montluçon - Paris en gare de Saint-Amand-Montrond, de novembre 1906 (recherche du 21/11/2026).
return [
    'slug' => '1906-11-13-chateaumeillant',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1906' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1906',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1906&oldid=233590740#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 13 novembre 1906, d\'après Le Figaro du 14 novembre : en gare de Saint-Amand-Montrond, vers 20 h 30, un train de la ligne de La Guerche à Châteaumeillant du réseau à voie métrique du Cher, en panne de frein, ne peut s\'arrêter à la jonction des voies avec la compagnie d\'Orléans et heurte la locomotive d\'un train Montluçon - Paris, qui déraille ; dans le train tamponneur renversé, le mécanicien et le chauffeur sont tués, six voyageurs blessés.',
        ],
        'ddb-1906-11-14-halle' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Schwerer Eisenbahnunfall',
            'publisher' => 'Hallesche Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1906-11-14',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/AIYM27Q347XK2BYKB5GME4E4BNLBCISP?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : le lundi soir, près de la gare de Saint-Amand, un train de voyageurs et un train de chemin de fer d\'intérêt local se sont heurtés dans un épais brouillard ; la locomotive de ce dernier a été brisée ; le mécanicien et le chauffeur ont été tués sur le coup, plusieurs voyageurs blessés.',
        ],
        'ddb-1906-11-14-morgenpost' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Wieder ein Eisenbahnunglück',
            'publisher' => 'Berliner Morgenpost (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1906-11-14',
            'locator' => 'p. 13',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/2PXRM7FNCFVJD75OGO27T66EZTYNF2EC?issuepage=13',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle : collision près de la gare de Saint-Amand, en France, d\'un train de voyageurs et d\'un train d\'intérêt local dans un épais brouillard ; locomotive du train d\'intérêt local brisée, mécanicien et chauffeur tués sur le coup.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Vers 20 h 30, un train de la ligne à voie métrique de La Guerche à Châteaumeillant, du réseau du Cher, n\'a pas pu s\'arrêter à la jonction de ses voies avec celles de la compagnie d\'Orléans, en gare de Saint-Amand-Montrond. Il a heurté la locomotive d\'un train de Montluçon à Paris, qui a déraillé. Le train du réseau du Cher s\'est renversé.',
                'en' => 'At about 8.30 pm a train on the metre-gauge La Guerche to Châteaumeillant line of the Cher network failed to stop at the junction of its tracks with those of the Orléans company at Saint-Amand-Montrond station. It struck the engine of a Montluçon to Paris train, which derailed. The Cher network train overturned.',
                'de' => 'Gegen 20.30 Uhr konnte ein Zug der meterspurigen Strecke La Guerche-Châteaumeillant des Netzes des Departements Cher an der Verbindung seiner Gleise mit denen der Orléans-Bahn im Bahnhof Saint-Amand-Montrond nicht halten. Er stieß mit der Lokomotive eines Zugs Montluçon-Paris zusammen, die entgleiste. Der Zug des Cher-Netzes stürzte um.',
                'sources' => ['wp-liste-1906', 'ddb-1906-11-14-halle'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur du train du réseau du Cher ont été tués sur le coup. Plusieurs voyageurs ont été blessés, six selon la liste de Wikipédia.',
                'en' => 'The driver and the stoker of the Cher network train were killed outright. Several passengers were injured, six according to the Wikipedia list.',
                'de' => 'Lokomotivführer und Heizer des Zugs des Cher-Netzes waren sofort tot. Mehrere Reisende wurden verletzt, nach der Wikipedia-Liste sechs.',
                'sources' => ['wp-liste-1906', 'ddb-1906-11-14-halle', 'ddb-1906-11-14-morgenpost'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia attribue la collision à une panne de frein, la presse allemande à un épais brouillard. La liste date l\'accident du mardi 13 novembre 1906 ; la Hallesche Zeitung du 14 le place le lundi soir, soit le 12.',
                'en' => 'The Wikipedia list attributes the collision to a brake failure, the German press to thick fog. The list dates the accident to Tuesday 13 November 1906; the Hallesche Zeitung of the 14th places it on Monday evening, the 12th.',
                'de' => 'Die Wikipedia-Liste führt den Zusammenstoß auf ein Versagen der Bremse zurück, die deutsche Presse auf dichten Nebel. Die Liste datiert den Unfall auf Dienstag, den 13. November 1906; die Hallesche Zeitung vom 14. legt ihn auf Montagabend, also den 12.',
                'sources' => ['wp-liste-1906', 'ddb-1906-11-14-halle', 'ddb-1906-11-14-morgenpost'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1906', 'ddb-1906-11-14-halle'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Figaro du 14 novembre 1906) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Figaro of 14 November 1906) and the German press; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste (Le Figaro vom 14. November 1906) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '18197',
            'sources' => ['wp-liste-1906', 'ddb-1906-11-14-halle'],
            'fr' => 'La collision a eu lieu en gare de Saint-Amand-Montrond selon la liste de Wikipédia et la presse allemande ; la fiche portait Châteaumeillant, terminus de la ligne.',
            'en' => 'The collision happened at Saint-Amand-Montrond station according to the Wikipedia list and the German press; the record had Châteaumeillant, the end of the line.',
            'de' => 'Der Zusammenstoß ereignete sich nach der Wikipedia-Liste und der deutschen Presse im Bahnhof Saint-Amand-Montrond; der Eintrag nannte Châteaumeillant, den Endpunkt der Strecke.',
        ],
    ],
];
