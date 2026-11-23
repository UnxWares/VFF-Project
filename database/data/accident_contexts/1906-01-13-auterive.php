<?php

// Contexte sourcé du déraillement d'un train sur un aiguillage en gare d'Auterive, ligne de Portet-Saint-Simon à Puigcerda, de janvier 1906 (recherche du 21/11/2026).
return [
    'slug' => '1906-01-13-auterive',
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
            'locator' => 'Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1906&oldid=233590740#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 13 janvier 1906, d\'après Le Matin du 14 janvier : vers 15 h, un train de marchandises qui manœuvre en gare d\'Auterive pour laisser passer l\'express venant de Toulouse déraille sur un aiguillage ; les wagons télescopent la machine, qui verse, chaudière percée ; le chauffeur et le chef de train sont tués, le mécanicien est brûlé.',
        ],
        'vaderland-1906-01-15' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het vaderland du 15 janvier 1906',
            'publisher' => 'Het vaderland (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1906-01-15',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB23:001489034:mpeg21:a00029',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Brève : près de la gare d\'Auterive, le train de Toulouse à Foix est sorti des rails dans la nuit de dimanche ; douze wagons barrent la voie ; le mécanicien et le chauffeur ont été tués, plusieurs voyageurs blessés.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 13 janvier 1906, en gare d\'Auterive, sur la ligne de Portet-Saint-Simon à Puigcerda, un train a déraillé sur un aiguillage. Les wagons ont télescopé la machine, qui s\'est renversée chaudière percée, et douze wagons ont barré la voie.',
                'en' => 'On 13 January 1906, at Auterive station on the Portet-Saint-Simon to Puigcerda line, a train derailed on a set of points. The wagons telescoped into the engine, which overturned with its boiler holed, and twelve wagons blocked the line.',
                'de' => 'Am 13. Januar 1906 entgleiste im Bahnhof Auterive an der Strecke Portet-Saint-Simon-Puigcerda ein Zug an einer Weiche. Die Wagen schoben sich in die Lokomotive, die mit durchschlagenem Kessel umstürzte; zwölf Wagen versperrten das Gleis.',
                'sources' => ['wp-liste-1906', 'vaderland-1906-01-15'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux agents ont été tués : le chauffeur et le chef de train selon la liste de Wikipédia, le mécanicien et le chauffeur selon Het vaderland. La liste ajoute le mécanicien brûlé ; la presse néerlandaise parle de plusieurs voyageurs blessés.',
                'en' => 'Two railwaymen were killed: the stoker and the guard according to the Wikipedia list, the driver and the stoker according to Het vaderland. The list adds that the driver was burned; the Dutch press speaks of several injured passengers.',
                'de' => 'Zwei Eisenbahner kamen ums Leben, nach der Wikipedia-Liste Heizer und Zugführer, nach Het vaderland Lokomotivführer und Heizer. Die Liste nennt zusätzlich den verbrühten Lokomotivführer, die niederländische Presse mehrere verletzte Reisende.',
                'sources' => ['wp-liste-1906', 'vaderland-1906-01-15'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Selon la liste de Wikipédia, c\'est un train de marchandises en manœuvre qui a déraillé le samedi 13 vers 15 h ; selon Het vaderland, c\'est le train de Toulouse à Foix, dans la nuit de dimanche.',
                'en' => 'According to the Wikipedia list, a shunting goods train derailed on Saturday the 13th at about 3 pm; according to Het vaderland, it was the Toulouse to Foix train, on Sunday night.',
                'de' => 'Nach der Wikipedia-Liste entgleiste ein rangierender Güterzug am Samstag, dem 13., gegen 15 Uhr; nach Het vaderland war es der Zug Toulouse-Foix in der Sonntagnacht.',
                'sources' => ['wp-liste-1906', 'vaderland-1906-01-15'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1906', 'vaderland-1906-01-15'],
            'fr' => 'Deux agents tués selon la liste de Wikipédia (Le Matin du 14 janvier 1906) et Het vaderland du 15 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two railwaymen killed according to the Wikipedia list (Le Matin of 14 January 1906) and Het vaderland of the 15th; the record gave no number of deaths.',
            'de' => 'Zwei Eisenbahner getötet nach der Wikipedia-Liste (Le Matin vom 14. Januar 1906) und Het vaderland vom 15.; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
