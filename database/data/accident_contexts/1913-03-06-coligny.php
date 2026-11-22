<?php

// Contexte sourcé de l'explosion de la chaudière d'un train de marchandises près de Coligny, ligne de Bourg à Dijon par Saint-Amour, de mars 1913 (recherche du 21/11/2026).
return [
    'slug' => '1913-03-06-coligny',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1913' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1913',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Mars',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1913&oldid=233727923#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 6 mars 1913, d\'après Le Figaro et Le Matin du 7 mars : sur la ligne Bourg - Dijon par Saint-Amour, près de Coligny, la chaudière de la locomotive d\'un train de marchandises de 60 wagons explose, tuant le mécanicien et le chauffeur ; le convoi recule et le chef de train est blessé ; un rapide qui croisait le train a des voitures endommagées.',
        ],
        'ddb-1913-03-07-gladbach' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 7. März',
            'publisher' => 'Westdeutsche Landeszeitung (Gladbacher Volkszeitung) (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1913-03-07',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/DQMR7BLXG5HASQPGMJAQTUK42UQ5IJ57?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche Wolff de Paris du 7 mars : près de Coligny, une locomotive du réseau PLM a explosé ; le chauffeur et le mécanicien ont été tués.',
        ],
        'ddb-1913-03-08-morgenzeitung' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück in Frankreich',
            'publisher' => 'Berliner Morgen-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1913-03-08',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/7FPI72OTTBI6L3WB6NQMFK34424UEBGH?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Télégramme de Paris : près de Coligny, dans l\'Ain, la locomotive d\'un train de marchandises du PLM a explosé ; le chauffeur et un mécanicien tués.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 6 mars 1913, sur la ligne de Bourg à Dijon par Saint-Amour, près de Coligny, la chaudière de la locomotive d\'un train de marchandises de soixante wagons a explosé. Le train a reculé. Un rapide qui le croisait à ce moment a eu des voitures endommagées.',
                'en' => 'On 6 March 1913, on the Bourg to Dijon line via Saint-Amour near Coligny, the boiler of the engine of a sixty-wagon goods train exploded. The train ran backwards. A fast train passing it at that moment had coaches damaged.',
                'de' => 'Am 6. März 1913 explodierte an der Strecke Bourg-Dijon über Saint-Amour bei Coligny der Kessel der Lokomotive eines Güterzugs mit sechzig Wagen. Der Zug rollte zurück. An einem gerade kreuzenden Schnellzug wurden Wagen beschädigt.',
                'sources' => ['wp-liste-1913', 'ddb-1913-03-07-gladbach', 'ddb-1913-03-08-morgenzeitung'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués, le chef de train blessé.',
                'en' => 'The driver and the stoker were killed and the guard injured.',
                'de' => 'Lokomotivführer und Heizer wurden getötet, der Zugführer verletzt.',
                'sources' => ['wp-liste-1913', 'ddb-1913-03-07-gladbach', 'ddb-1913-03-08-morgenzeitung'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1913', 'ddb-1913-03-07-gladbach'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Matin du 7 mars 1913) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Matin of 7 March 1913) and the German press; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste (Le Matin vom 7. März 1913) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '01108',
            'sources' => ['wp-liste-1913', 'ddb-1913-03-07-gladbach'],
            'fr' => 'L\'explosion a eu lieu près de Coligny selon la liste de Wikipédia et la presse allemande ; la fiche portait Bourg-en-Bresse, origine de la ligne.',
            'en' => 'The explosion happened near Coligny according to the Wikipedia list and the German press; the record had Bourg-en-Bresse, where the line starts.',
            'de' => 'Die Explosion ereignete sich nach der Wikipedia-Liste und der deutschen Presse bei Coligny; der Eintrag nannte Bourg-en-Bresse, den Ausgangspunkt der Strecke.',
        ],
    ],
];
