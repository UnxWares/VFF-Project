<?php

// Contexte sourcé de la collision à la sortie de la gare de Chartres, le 30 octobre 1920 (recherche du 21/11/2026).
return [
    'slug' => '1920-10-30-chartres',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1920' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1920',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '30 octobre 1920, Chartres',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1920&oldid=236476563#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Collision vers 21 h 40 à la sortie de Chartres entre l\'express Paris-Brest et un train de marchandises venant de Niort ; un mort et une quarantaine de blessés ; cite Le Petit Parisien du 1er novembre 1920.',
        ],
        'vaderland-1920-11-01' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het Vaderland du 1 novembre 1920',
            'publisher' => 'Het Vaderland',
            'author' => null,
            'published' => '1920-11-01',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010006897:mpeg21:a0082',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Dépêche Havas de Chartres, par Reuter.',
        ],
        'petit-parisien-1920-11-01' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 1er novembre 1920, page 1',
            'publisher' => 'Le Petit Parisien (numérisé sur Gallica)',
            'author' => null,
            'published' => '1920-11-01',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k604459p',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 30 octobre 1920 au soir, un train parti de Paris à 19 h 30 est entré en collision avec un autre train à la gare de Chartres, selon une dépêche Havas relayée par Reuter. D\'après la liste de Wikipédia, l\'accident a eu lieu vers 21 h 40 à la sortie de la gare, à la bifurcation des lignes de Bretagne et du Sud-Ouest.',
                'en' => 'On the evening of Saturday 30 October 1920, a train that had left Paris at 7.30 pm collided with another train at Chartres station, according to a Havas dispatch relayed by Reuters. According to the Wikipedia list, the accident took place at about 9.40 pm at the exit from the station, at the junction of the Brittany and south-west lines.',
                'de' => 'Am Abend des Samstags, 30. Oktober 1920, stieß ein um 19.30 Uhr in Paris abgefahrener Zug laut einer von Reuter weitergegebenen Havas-Depesche im Bahnhof Chartres mit einem anderen Zug zusammen. Laut der Wikipedia-Liste ereignete sich das Unglück gegen 21.40 Uhr am Bahnhofsausgang an der Abzweigung der Bretagne- und der Südwest-Strecke.',
                'sources' => ['vaderland-1920-11-01', 'wp-liste-1920'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La liste de Wikipédia précise que l\'express Paris-Brest a été pris en écharpe par un train de marchandises venant de Niort dont le frein à air était défaillant ; la locomotive de ce train s\'est couchée après avoir écrasé deux wagons postaux et une voiture de voyageurs.',
                'en' => 'The Wikipedia list specifies that the Paris to Brest express was hit in the flank by a goods train from Niort whose air brake had failed; that train\'s locomotive overturned after crushing two mail vans and a passenger coach.',
                'de' => 'Die Wikipedia-Liste präzisiert, der Schnellzug Paris-Brest sei von einem Güterzug aus Niort, dessen Druckluftbremse ausgefallen war, seitlich erfasst worden; die Lokomotive dieses Zuges kippte um, nachdem sie zwei Postwagen und einen Reisezugwagen zerdrückt hatte.',
                'sources' => ['wp-liste-1920'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un chauffeur de locomotive a été tué et de nombreuses personnes ont été blessées selon la dépêche ; la liste de Wikipédia donne un mort et une quarantaine de blessés.',
                'en' => 'A locomotive fireman was killed and many people injured according to the dispatch; the Wikipedia list gives one dead and about forty injured.',
                'de' => 'Ein Heizer wurde getötet und zahlreiche Personen verletzt, laut der Depesche; die Wikipedia-Liste nennt einen Toten und etwa vierzig Verletzte.',
                'sources' => ['vaderland-1920-11-01', 'wp-liste-1920'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Une première dépêche annonçait qu\'un ministre se trouvait dans le train, ce qu\'un second message a démenti.',
                'en' => 'A first dispatch announced that a minister was on the train, which a second message denied.',
                'de' => 'Eine erste Depesche meldete, ein Minister befinde sich im Zug, was eine zweite Meldung dementierte.',
                'sources' => ['vaderland-1920-11-01'],
            ],
        ],
    ],
    'corrections' => [],
];
