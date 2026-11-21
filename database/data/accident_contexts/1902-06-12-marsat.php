<?php

// Contexte sourcé de l'explosion de la chaudière d'une locomotive en gare de Marsat, ligne d'intérêt local de Riom à Volvic, de juin 1902 (recherche du 21/11/2026).
return [
    'slug' => '1902-06-12-marsat',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1902' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1902',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juin',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1902&oldid=233590581#Juin',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 12 juin 1902, d\'après Le Temps du 14 juin : à 8 h 20, en gare de Marsat, sur la ligne d\'intérêt local à voie étroite de Riom à Volvic, la chaudière de la locomotive d\'un train de voyageurs explose, tuant le mécanicien et le chauffeur ; l\'unique voyageur du train est indemne.',
        ],
        'ddb-1902-06-14-riesa' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Nach einer Meldung aus Riom',
            'publisher' => 'Riesaer Tageblatt und Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-06-14',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/CSBQOSI7UTLODQI24VUG2DIL2XQDKLJZ?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Nouvelle de Riom : en gare de Marsat, un mécanicien voulut faire monter la pression pour rattraper le retard dû au mauvais fonctionnement de sa machine ; la chaudière a explosé, le mécanicien et le chauffeur ont été tués, un voyageur grièvement blessé.',
        ],
        'ddb-1902-06-15-grenzbote' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 13. Juni',
            'publisher' => 'Der Grenzbote (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-06-15',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/FLC2PRCPS4YICA5UQIB7FVMUB57SRAOK?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 13 juin, même texte : explosion de la chaudière en gare de Marsat, mécanicien et chauffeur tués, un voyageur grièvement blessé.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 12 juin 1902, vers 8 h 20, un train de voyageurs se trouvait en gare de Marsat, sur la ligne d\'intérêt local à voie étroite de Riom à Volvic. Selon la dépêche de Riom, le mécanicien a fait monter la pression pour rattraper le retard dû au mauvais fonctionnement de la machine, et la chaudière a explosé.',
                'en' => 'On 12 June 1902, at about 8.20 am, a passenger train was at Marsat station on the narrow-gauge local line from Riom to Volvic. According to the Riom dispatch the driver raised the steam pressure to make up time lost through the engine\'s poor running, and the boiler exploded.',
                'de' => 'Am 12. Juni 1902 gegen 8.20 Uhr stand ein Personenzug im Bahnhof Marsat an der schmalspurigen Lokalbahn Riom-Volvic. Nach der Meldung aus Riom erhöhte der Lokführer den Dampfdruck, um die durch den schlechten Lauf der Maschine verlorene Zeit aufzuholen, und der Kessel explodierte.',
                'sources' => ['wp-liste-1902', 'ddb-1902-06-14-riesa'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués.',
                'en' => 'The driver and the stoker were killed.',
                'de' => 'Lokführer und Heizer wurden getötet.',
                'sources' => ['wp-liste-1902', 'ddb-1902-06-14-riesa', 'ddb-1902-06-15-grenzbote'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La dépêche de Riom signale un voyageur grièvement blessé ; selon la liste de Wikipédia, l\'unique voyageur du train est sorti indemne.',
                'en' => 'The Riom dispatch reports one passenger seriously injured; according to the Wikipedia list the train\'s only passenger was unhurt.',
                'de' => 'Die Meldung aus Riom nennt einen schwer verletzten Reisenden; nach der Wikipedia-Liste blieb der einzige Reisende des Zuges unverletzt.',
                'sources' => ['ddb-1902-06-14-riesa', 'wp-liste-1902'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1902', 'ddb-1902-06-14-riesa', 'ddb-1902-06-15-grenzbote'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Temps du 14 juin 1902) et la dépêche de Riom ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Temps of 14 June 1902) and the Riom dispatch; the record gave no number of deaths.',
            'de' => 'Lokführer und Heizer getötet nach der Wikipedia-Liste (Le Temps vom 14. Juni 1902) und der Meldung aus Riom; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
