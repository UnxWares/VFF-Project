<?php

// Contexte sourcé de l'accident de Flers du 15 août 1879 (recherche du 21/11/2026).
return [
    'slug' => '1879-08-15-flers',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1879, 15 août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1879',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée sourcée par un numéro du Figaro du 16 août 1879 (p. 3) et par un traité de chemins de fer pour la condamnation ; ces deux documents n\'ont pas pu être lus. Seule source trouvée sur cet accident.',
        ],
        'wp-principaux' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste des principaux accidents ferroviaires',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '15 août 1879, Orne',
            'url' => 'https://fr.wikipedia.org/wiki/Liste_des_principaux_accidents_ferroviaires',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée brève : collision entre Flers et Montsecret, 10 morts ; référence générique, sans détail ; lien non permanent.',
        ],
        'manche-ocean-accidents' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Accidents (lignes de l\'Ouest et de l\'État)',
            'publisher' => 'manche-ocean.fr',
            'author' => null,
            'published' => '2020-02-08',
            'locator' => '15 août 1879',
            'url' => 'https://www.manche-ocean.fr/exploitation/accidents.htm',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Liste non exhaustive des accidents des lignes de l\'Ouest et de l\'État ; une ligne pour 1879 : déraillement d\'un train d\'Argentan à Granville entre Flers et Montsecret-Vassy, sans bilan. Auteur non indiqué.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 15 août 1879, vers 6 h 30, un train de voyageurs est entré en collision avec un train de marchandises entre les gares de Flers (Orne) et de Montsecret-Vassy, sur la ligne d\'Argentan à Granville.',
                'en' => 'On 15 August 1879, at about 6.30 am, a passenger train collided with a goods train between the stations of Flers (Orne) and Montsecret-Vassy, on the Argentan to Granville line.',
                'de' => 'Am 15. August 1879 stieß gegen 6.30 Uhr ein Personenzug zwischen den Bahnhöfen Flers (Orne) und Montsecret-Vassy auf der Strecke Argentan - Granville mit einem Güterzug zusammen.',
                'sources' => ['wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste de Wikipédia, la collision a fait treize morts et trente-six blessés.',
                'en' => 'According to the Wikipedia list, the collision killed thirteen people and injured thirty-six.',
                'de' => 'Laut der Wikipedia-Liste forderte der Zusammenstoß dreizehn Tote und sechsunddreißig Verletzte.',
                'sources' => ['wp-liste-1870'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le 13 septembre 1879, le sous-chef de la gare de Flers a été condamné à deux ans de prison et 300 francs d\'amende pour ne pas avoir retenu le train de voyageurs.',
                'en' => 'On 13 September 1879, the deputy stationmaster of Flers was sentenced to two years in prison and a fine of 300 francs for failing to hold back the passenger train.',
                'de' => 'Am 13. September 1879 wurde der stellvertretende Bahnhofsvorsteher von Flers zu zwei Jahren Gefängnis und 300 Francs Geldstrafe verurteilt, weil er den Personenzug nicht zurückgehalten hatte.',
                'sources' => ['wp-liste-1870'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie : 13 morts et 36 blessés dans la liste années 1870, 10 morts dans la liste générale des principaux accidents. La liste du site manche-ocean.fr parle d\'un déraillement et non d\'une collision, sans bilan. La nature (collision) et le bilan de la fiche ne reposent que sur la liste années 1870.',
                'en' => 'The toll varies: 13 dead and 36 injured in the 1870s list, 10 dead in the general list of major accidents. The manche-ocean.fr list speaks of a derailment rather than a collision, with no toll. The record\'s nature (collision) and toll rest only on the 1870s list.',
                'de' => 'Die Zahlen schwanken: 13 Tote und 36 Verletzte in der Liste der 1870er Jahre, 10 Tote in der allgemeinen Liste der wichtigsten Unfälle. Die Liste der Seite manche-ocean.fr spricht von einer Entgleisung statt einer Kollision, ohne Bilanz. Art (Kollision) und Bilanz der Karteikarte beruhen nur auf der Liste der 1870er Jahre.',
                'sources' => ['wp-liste-1870', 'wp-principaux', 'manche-ocean-accidents'],
            ],
            [
                'fr' => 'La liste de Wikipédia situe l\'accident entre Montsecret-Vassy et Flers, et non dans la gare de Flers : le lieu exact (commune, point kilométrique) n\'est pas établi. Le rattachement de la fiche à la commune de Flers reste donc approximatif.',
                'en' => 'The Wikipedia list places the accident between Montsecret-Vassy and Flers, not in Flers station itself: the exact location (commune, kilometre point) is not established. Attaching the record to the commune of Flers is therefore approximate.',
                'de' => 'Die Wikipedia-Liste verortet den Unfall zwischen Montsecret-Vassy und Flers und nicht im Bahnhof Flers selbst: Der genaue Ort (Gemeinde, Kilometerpunkt) ist nicht gesichert. Die Zuordnung der Karteikarte zur Gemeinde Flers ist daher nur ungefähr.',
                'sources' => ['wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [],
];
