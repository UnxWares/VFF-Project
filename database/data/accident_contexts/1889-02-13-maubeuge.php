<?php

// Contexte sourcé de la collision des express Paris - Bruxelles et Bruxelles - Paris près de la gare de Maubeuge, ligne de Creil à Jeumont, de février 1889 (recherche du 21/11/2026).
return [
    'slug' => '1889-02-13-maubeuge',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1889',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1889',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 13 février 1889 : sur la ligne de Paris à Bruxelles, vers 20 h 15, près de Maubeuge, au passage d\'un aiguillage, deux express, l\'un pour Paris, l\'autre pour Bruxelles, se prennent en écharpe ; les deux machines déraillent et une voiture de première classe est enfoncée ; un voyageur norvégien est retiré mort, sans blessure apparente ; cinq autres personnes contusionnées.',
        ],
        'ddb-1889-02-14-gladbach' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Brüssel, 13. Febr.',
            'publisher' => 'Gladbacher Volkszeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1889-02-14',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/PFH6OULBU66LHNW3MLCQWFX2CS7BEHH2?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Bruxelles du 13 février : l\'express parti de Paris pour Bruxelles hier à 15 h 50 a heurté, en quittant la gare de Maubeuge, l\'express venant de Bruxelles ; une personne tuée, quatre blessées.',
        ],
        'ddb-1889-02-16-velbert' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Neues Eisenbahnunglück. Brüssel, 13. Febr.',
            'publisher' => 'Velberter Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1889-02-16',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/6JTZE7DPJ7DZM4CGTLYWPM7NSZBQDA5U?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Bruxelles du 13 février : les express Paris - Bruxelles et Bruxelles - Paris se sont heurtés hier soir à 20 h 40 près de la gare de Maubeuge, par suite d\'une mauvaise position d\'aiguille, dans un brouillard épais ; plusieurs voyageurs et deux agents grièvement blessés, un voyageur tué.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le soir de l\'accident, près de la gare de Maubeuge, sur la ligne de Paris à Bruxelles, l\'express parti de Paris à 15 h 50 pour Bruxelles et l\'express venant de Bruxelles se sont pris en écharpe au passage d\'un aiguillage mal placé, dans un brouillard épais. Les deux machines ont déraillé et une voiture de première classe a été enfoncée.',
                'en' => 'On the evening of the accident, near Maubeuge station on the Paris to Brussels line, the express that had left Paris at 3.50 pm for Brussels and the express from Brussels struck each other side-on at wrongly set points, in thick fog. Both engines derailed and a first class coach was crushed.',
                'de' => 'Am Abend des Unfalls stießen nahe dem Bahnhof Maubeuge an der Strecke Paris-Brüssel der um 15.50 Uhr in Paris nach Brüssel abgefahrene Expresszug und der Expresszug aus Brüssel an einer falsch gestellten Weiche bei dichtem Nebel seitlich zusammen. Beide Lokomotiven entgleisten, ein Wagen erster Klasse wurde eingedrückt.',
                'sources' => ['wp-liste-1880', 'ddb-1889-02-14-gladbach', 'ddb-1889-02-16-velbert'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur a été tué ; selon la liste de Wikipédia, il ne portait pas de blessure apparente. Le nombre de blessés varie : quatre selon une dépêche de Bruxelles, plusieurs voyageurs et deux agents grièvement blessés selon une autre, cinq personnes contusionnées selon la liste.',
                'en' => 'One passenger was killed; according to the Wikipedia list he had no visible injury. The number of injured varies: four according to one Brussels dispatch, several passengers and two railwaymen seriously injured according to another, five people bruised according to the list.',
                'de' => 'Ein Reisender wurde getötet; nach der Wikipedia-Liste hatte er keine sichtbare Verletzung. Die Zahl der Verletzten schwankt: vier nach einer Brüsseler Depesche, mehrere Reisende und zwei schwer verletzte Zugbeamte nach einer anderen, fünf Personen mit Prellungen nach der Liste.',
                'sources' => ['wp-liste-1880', 'ddb-1889-02-14-gladbach', 'ddb-1889-02-16-velbert'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les dépêches de Bruxelles du 13 février placent l\'accident « hier », soit le 12 février, la liste de Wikipédia le 13 février. L\'heure varie aussi : 20 h 15 dans la liste, 20 h 40 dans une dépêche.',
                'en' => 'The Brussels dispatches of 13 February place the accident "yesterday", that is 12 February; the Wikipedia list gives 13 February. The time also varies: 8.15 pm in the list, 8.40 pm in one dispatch.',
                'de' => 'Die Brüsseler Depeschen vom 13. Februar setzen den Unfall auf „gestern“, also den 12. Februar, die Wikipedia-Liste auf den 13. Februar. Auch die Uhrzeit schwankt: 20.15 Uhr in der Liste, 20.40 Uhr in einer Depesche.',
                'sources' => ['ddb-1889-02-14-gladbach', 'ddb-1889-02-16-velbert', 'wp-liste-1880'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1880', 'ddb-1889-02-14-gladbach', 'ddb-1889-02-16-velbert'],
            'fr' => 'Un voyageur tué selon la liste de Wikipédia et les dépêches de Bruxelles du 13 février 1889 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One passenger killed according to the Wikipedia list and the Brussels dispatches of 13 February 1889; the record gave no number of deaths.',
            'de' => 'Ein Reisender getötet nach der Wikipedia-Liste und den Brüsseler Depeschen vom 13. Februar 1889; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
