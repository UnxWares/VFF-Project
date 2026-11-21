<?php

// Contexte sourcé de la collision d'un omnibus Reims - Soissons et d'une machine haut-le-pied à la bifurcation de Bazoches-sur-Vesles, de novembre 1902 (recherche du 21/11/2026).
return [
    'slug' => '1902-11-04-soissons',
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
            'locator' => 'Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1902&oldid=233590581#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 4 novembre 1902, d\'après Le Petit Parisien des 5 et 7 novembre : à la bifurcation de Bazoches-sur-Vesles, près de Fismes, à 9 h 38, un omnibus de Reims à Soissons est pris en écharpe par une machine haut-le-pied venant de la ligne de Paris par La Ferté-Milon, dont le mécanicien a franchi un signal d\'arrêt, semble-t-il fermé trop tard ; douze blessés, dont un mourra le lendemain.',
        ],
        'ddb-1902-11-06-neckarbote' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Bazoches, 5. Nov.',
            'publisher' => 'Neckar-Bote (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-11-06',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/LBPW65BJWWOPBADCGKZO5JQ2PITWR4LQ?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Bazoches du 5 novembre : un conseiller général de la Seine, blessé dans l\'accident de chemin de fer, est mort dans la nuit.',
        ],
        'ddb-1902-11-06-duisburg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 5. November',
            'publisher' => 'General-Anzeiger für Duisburg, Ruhrort und Umgegend (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-11-06',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/DYCMMGFE4T3MN72LQ3NG775LTQ5KCCVI?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 5 novembre, d\'après Bazoches près de Reims : le conseiller général de la Seine blessé sur place est mort dans la nuit.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 4 novembre 1902, à 9 h 38, à la bifurcation de Bazoches-sur-Vesles, près de Fismes, un omnibus de Reims à Soissons a été pris en écharpe par une machine haut-le-pied venant de la ligne de Paris par La Ferté-Milon. Selon la liste de Wikipédia, le mécanicien de la machine avait franchi un signal d\'arrêt, qui semble avoir été fermé trop tard.',
                'en' => 'On 4 November 1902, at 9.38 am, at the Bazoches-sur-Vesles junction near Fismes, a Reims to Soissons stopping train was struck in the side by a light engine coming off the line from Paris via La Ferté-Milon. According to the Wikipedia list the engine\'s driver had passed a stop signal, which seems to have been set too late.',
                'de' => 'Am 4. November 1902 um 9.38 Uhr wurde an der Abzweigung Bazoches-sur-Vesles bei Fismes ein Personenzug Reims-Soissons von einer Leerlokomotive aus der Strecke von Paris über La Ferté-Milon seitlich erfasst. Nach der Wikipedia-Liste hatte deren Führer ein Haltsignal überfahren, das offenbar zu spät gestellt worden war.',
                'sources' => ['wp-liste-1902'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Douze personnes ont été blessées. L\'une d\'elles, un conseiller général de la Seine, est morte dans la nuit suivante.',
                'en' => 'Twelve people were injured. One of them, a member of the Seine departmental council, died the following night.',
                'de' => 'Zwölf Personen wurden verletzt. Eine von ihnen, ein Generalrat des Departements Seine, starb in der folgenden Nacht.',
                'sources' => ['wp-liste-1902', 'ddb-1902-11-06-neckarbote', 'ddb-1902-11-06-duisburg'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1902', 'ddb-1902-11-06-neckarbote'],
            'fr' => 'Un blessé mort la nuit suivante selon la liste de Wikipédia (Le Petit Parisien du 7 novembre 1902) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One injured person died the following night according to the Wikipedia list (Le Petit Parisien of 7 November 1902) and the German press; the record gave no number of deaths.',
            'de' => 'Ein Verletzter starb in der folgenden Nacht nach der Wikipedia-Liste (Le Petit Parisien vom 7. November 1902) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '02054',
            'sources' => ['wp-liste-1902', 'ddb-1902-11-06-neckarbote'],
            'fr' => 'La collision a eu lieu à la bifurcation de Bazoches-sur-Vesles (aujourd\'hui Bazoches-et-Saint-Thibaut) selon la liste de Wikipédia et la presse allemande ; la fiche portait Soissons, destination du train.',
            'en' => 'The collision happened at the Bazoches-sur-Vesles junction (now Bazoches-et-Saint-Thibaut) according to the Wikipedia list and the German press; the record had Soissons, the train\'s destination.',
            'de' => 'Der Zusammenstoß ereignete sich nach der Wikipedia-Liste und der deutschen Presse an der Abzweigung Bazoches-sur-Vesles (heute Bazoches-et-Saint-Thibaut); der Eintrag nannte Soissons, das Ziel des Zugs.',
        ],
    ],
];
