<?php

// Contexte sourcé du déraillement d'un train de voyageurs venant de Lille à l'entrée de la gare d'Hazebrouck, de septembre 1907 (recherche du 21/11/2026).
return [
    'slug' => '1907-09-24-hazebrouck',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1907' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1907',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1907&oldid=238956681#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 24 septembre 1907, d\'après Le Temps du 25 et Le Matin du 24 septembre : en gare d\'Hazebrouck, vers 1 h, un train de voyageurs venant de Lille déraille sur l\'aiguille d\'entrée ; la locomotive, le tender et le fourgon de tête se couchent sur les voies ; le mécanicien et le chauffeur sont tués, tous les voyageurs indemnes.',
        ],
        'ddb-1907-09-26-cannstatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Lille (Frankreich), 24. Sept.',
            'publisher' => 'Cannstatter Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1907-09-26',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/HSR66BMORF4XHX2SB32GYE6MEH7FMJBY?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Lille du 24 septembre : un train de voyageurs allant à Hazebrouck a déraillé la nuit précédente ; la locomotive et le tender se sont renversés ; le mécanicien et le chauffeur ont été tués, de nombreuses personnes blessées.',
        ],
        'ddb-1907-09-27-coburg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Lille',
            'publisher' => 'Coburger Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1907-09-27',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/AUAQO4ELCXHWWQ6AOHUCEBWXXPJLLKTM?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : un train de voyageurs allant de Lille à Hazebrouck a déraillé dans la nuit ; locomotive et tender renversés, mécanicien et chauffeur tués ; le nombre de voyageurs blessés n\'est pas encore connu.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 24 septembre 1907, vers 1 h du matin, un train de voyageurs venant de Lille a déraillé sur l\'aiguille d\'entrée de la gare d\'Hazebrouck. La locomotive, le tender et le fourgon de tête se sont couchés sur les voies.',
                'en' => 'On 24 September 1907, at about 1 am, a passenger train from Lille derailed on the entry points of Hazebrouck station. The engine, the tender and the leading van fell over onto the tracks.',
                'de' => 'Am 24. September 1907 gegen 1 Uhr nachts entgleiste ein aus Lille kommender Personenzug an der Einfahrweiche des Bahnhofs Hazebrouck. Lokomotive, Tender und vorderer Packwagen stürzten um.',
                'sources' => ['wp-liste-1907', 'ddb-1907-09-26-cannstatt', 'ddb-1907-09-27-coburg'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués.',
                'en' => 'The driver and the stoker were killed.',
                'de' => 'Lokomotivführer und Heizer wurden getötet.',
                'sources' => ['wp-liste-1907', 'ddb-1907-09-26-cannstatt', 'ddb-1907-09-27-coburg'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Selon la liste de Wikipédia, tous les voyageurs sont sortis indemnes. La dépêche de Lille du 24 septembre parle de nombreux blessés, une autre brève dit leur nombre encore inconnu.',
                'en' => 'According to the Wikipedia list all passengers were unhurt. The Lille dispatch of 24 September speaks of many injured, another short report says their number was not yet known.',
                'de' => 'Nach der Wikipedia-Liste blieben alle Reisenden unverletzt. Die Depesche aus Lille vom 24. September spricht von zahlreichen Verletzten, eine andere Meldung nennt ihre Zahl noch unbekannt.',
                'sources' => ['wp-liste-1907', 'ddb-1907-09-26-cannstatt', 'ddb-1907-09-27-coburg'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1907', 'ddb-1907-09-26-cannstatt'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Temps du 25 septembre 1907) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Temps of 25 September 1907) and the German press; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste (Le Temps vom 25. September 1907) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
