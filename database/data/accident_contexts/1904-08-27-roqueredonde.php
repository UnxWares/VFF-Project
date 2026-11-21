<?php

// Contexte sourcé de la collision des Cabrils (Roqueredonde) du 27 août 1904 (recherche du 21/11/2026).
return [
    'slug' => '1904-08-27-roqueredonde',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1904' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1904',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1904&oldid=240831504#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Seule source lue qui décrit l\'accident : lieu, trains, bilan ; renvoie à Le Figaro du 29 août 1904 (p. 2) et Le Temps du 30 août 1904 (p. 3), non lus.',
        ],
        'wp-gare-cabrils' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Gare des Cabrils',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'sections Situation et Histoire',
            'url' => 'https://fr.wikipedia.org/wiki/Gare_des_Cabrils',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Situation de la gare et du tunnel, rôle de la gare pour les machines de renfort, travaux de ventilation de 1905 ; ne parle pas de l\'accident.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'L\'accident s\'est produit à la sortie du tunnel des Cabrils, sur la ligne à voie unique de Béziers à Neussargues. La gare des Cabrils, au lieu-dit du même nom sur la commune de Roqueredonde (Hérault), a été ouverte le 18 octobre 1874 par la Compagnie du Midi, avec le tronçon du Bousquet-d\'Orb à Millau. Le tunnel mesure 1 425 m.',
                'en' => 'The accident took place at the exit of the Cabrils tunnel, on the single-track line from Béziers to Neussargues. Les Cabrils station, at the place of the same name in the commune of Roqueredonde (Hérault), was opened on 18 October 1874 by the Compagnie du Midi, together with the section from Le Bousquet-d\'Orb to Millau. The tunnel is 1,425 m long.',
                'de' => 'Der Unfall ereignete sich am Ausgang des Tunnels von Les Cabrils auf der eingleisigen Strecke von Béziers nach Neussargues. Der Bahnhof Les Cabrils am gleichnamigen Ort in der Gemeinde Roqueredonde (Hérault) wurde am 18. Oktober 1874 von der Compagnie du Midi zusammen mit dem Abschnitt von Le Bousquet-d\'Orb nach Millau eröffnet. Der Tunnel ist 1 425 m lang.',
                'sources' => ['wp-gare-cabrils', 'wp-liste-1904'],
            ],
            [
                'fr' => 'La gare servait de terminus et de point de retournement aux locomotives de renfort, qui poussaient les trains sur la longue rampe entre Le Bousquet-d\'Orb et Les Cabrils : les trains de marchandises montaient jusqu\'aux Cabrils, où la locomotive auxiliaire se détachait pour redescendre chercher d\'autres wagons.',
                'en' => 'The station served as a terminus and turning point for the banking engines that pushed trains up the long gradient between Le Bousquet-d\'Orb and Les Cabrils: goods trains climbed to Les Cabrils, where the assisting locomotive uncoupled and went back down for more wagons.',
                'de' => 'Der Bahnhof diente als Endpunkt und Wendepunkt der Schiebelokomotiven, die die Züge auf der langen Steigung zwischen Le Bousquet-d\'Orb und Les Cabrils schoben: Güterzüge fuhren bis Les Cabrils, wo sich die Hilfslokomotive löste und talwärts zurückfuhr, um weitere Wagen zu holen.',
                'sources' => ['wp-gare-cabrils'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le 27 août 1904, à la sortie du tunnel des Cabrils, un train spécial de vendangeurs est entré en collision frontale avec une machine haut-le-pied (locomotive seule).',
                'en' => 'On 27 August 1904, at the exit of the Cabrils tunnel, a special train carrying grape-pickers collided head-on with a light engine (a locomotive running on its own).',
                'de' => 'Am 27. August 1904 stieß am Ausgang des Tunnels von Les Cabrils ein Sonderzug mit Weinlesearbeitern frontal mit einer allein fahrenden Lokomotive zusammen.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le bilan est de quatre morts (les deux mécaniciens et les deux chauffeurs) et d\'une quinzaine de blessés.',
                'en' => 'According to the Wikipedia list, the toll was four dead (the two drivers and the two firemen) and about fifteen injured.',
                'de' => 'Nach der Wikipedia-Liste forderte der Unfall vier Tote (die beiden Lokführer und die beiden Heizer) und etwa fünfzehn Verletzte.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
    ],
    'corrections' => [],
];
