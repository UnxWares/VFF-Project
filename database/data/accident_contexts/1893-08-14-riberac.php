<?php

// Contexte sourcé du déraillement d'un train de Périgueux à Ribérac près de Lisle, août 1893 (recherche du 22/11/2026).
return [
    'slug' => '1893-08-14-riberac',
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
            'locator' => '1893',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1893',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 14 août 1893, d\'après Le Figaro du 15 août : sur la ligne de Périgueux à Ribérac, le matin, le train pour Ribérac déraille à l\'entrée de la gare de Lisle ; le chauffeur est tué.',
        ],
        'anno-1893-08-16-nfp' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugsentgleisung',
            'publisher' => 'Neue Freie Presse, Vienne (ANNO, Österreichische Nationalbibliothek)',
            'author' => null,
            'published' => '1893-08-16',
            'locator' => 'p. 3',
            'url' => 'https://anno.onb.ac.at/cgi-content/anno?aid=nfp&datum=18930816&seite=3',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche de Paris : la veille, un train de la compagnie d\'Orléans a déraillé entre les stations de Périgueux et de Ribérac ; le chauffeur a été tué, plusieurs voyageurs grièvement blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 14 août 1893 au matin, sur la ligne de la compagnie d\'Orléans de Périgueux à Ribérac, le train pour Ribérac a déraillé. Selon la liste de Wikipédia, c\'était à l\'entrée de la gare de Lisle.',
                'en' => 'On the morning of 14 August 1893, on the Orléans company\'s line from Périgueux to Ribérac, the train for Ribérac derailed. According to the Wikipedia list, this happened at the entrance to Lisle station.',
                'de' => 'Am Morgen des 14. August 1893 entgleiste auf der Strecke Périgueux-Ribérac der Orléans-Bahn der Zug nach Ribérac. Nach der Wikipedia-Liste geschah es an der Einfahrt des Bahnhofs Lisle.',
                'sources' => ['wp-liste-1890', 'anno-1893-08-16-nfp'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chauffeur de la locomotive a été tué. La dépêche parue à Vienne ajoute que plusieurs voyageurs ont été grièvement blessés.',
                'en' => 'The fireman of the engine was killed. The dispatch printed in Vienna adds that several passengers were badly injured.',
                'de' => 'Der Heizer der Lokomotive wurde getötet. Die in Wien gedruckte Meldung fügt hinzu, dass mehrere Reisende schwer verletzt wurden.',
                'sources' => ['wp-liste-1890', 'anno-1893-08-16-nfp'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Lieu : la liste de Wikipédia nomme la gare de Lisle, la dépêche dit seulement « entre Périgueux et Ribérac ». La commune de la fiche (Ribérac) n\'est pas corrigée.',
                'en' => 'Place: the Wikipedia list names Lisle station, the dispatch only says "between Périgueux and Ribérac". The commune on the record (Ribérac) is not corrected.',
                'de' => 'Ort: Die Wikipedia-Liste nennt den Bahnhof Lisle, die Meldung sagt nur „zwischen Périgueux und Ribérac“. Die Gemeinde des Eintrags (Ribérac) wird nicht berichtigt.',
                'sources' => ['wp-liste-1890', 'anno-1893-08-16-nfp'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'anno-1893-08-16-nfp'],
            'fr' => 'Chauffeur tué selon la liste de Wikipédia (Le Figaro du 15 août 1893) et la Neue Freie Presse du 16 août ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Fireman killed according to the Wikipedia list (Le Figaro of 15 August 1893) and the Neue Freie Presse of 16 August; the record gave no number of deaths.',
            'de' => 'Heizer getötet nach der Wikipedia-Liste (Le Figaro vom 15. August 1893) und der Neuen Freien Presse vom 16. August; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
