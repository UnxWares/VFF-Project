<?php

// Contexte sourcé de la collision de deux trains dans le brouillard avant la gare d'Argentan, ligne de Paris à Granville, d'octobre 1899 (recherche du 21/11/2026).
return [
    'slug' => '1899-10-22-almeneches',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1899',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1899',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 22 octobre 1899, d\'après Le Petit Parisien du 23 octobre : entre Surdon et Argentan, après la halte d\'Almenêches, dans le brouillard, vers 1 h 30, un train venant de Paris en percute un autre arrêté peu avant la gare d\'Argentan ; le mécanicien du train tamponneur est tué, trois agents du train tamponné sont blessés.',
        ],
        'opregte-haarlemsche-1899-10-24' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Opregte Haarlemsche Courant du 24 octobre 1899',
            'publisher' => 'Opregte Haarlemsche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1899-10-24',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB12:000059099:mpeg21:a00034',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. En partie à cause d\'un épais brouillard, un train venant de Paris a heurté un autre train à la gare d\'Argentan ; un mécanicien a été tué et les trains pour Caen ont eu de gros retards toute la matinée.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 22 octobre 1899 vers 1 h 30, dans un épais brouillard, un train venant de Paris a heurté un autre train arrêté peu avant la gare d\'Argentan, après la halte d\'Almenêches. Les trains pour Caen ont eu de gros retards toute la matinée.',
                'en' => 'At about 1.30 am on 22 October 1899, in thick fog, a train from Paris ran into another train standing just short of Argentan station, beyond the Almenêches halt. Trains for Caen were badly delayed all morning.',
                'de' => 'Am 22. Oktober 1899 gegen 1.30 Uhr fuhr bei dichtem Nebel ein Zug aus Paris hinter der Haltestelle Almenêches kurz vor dem Bahnhof Argentan auf einen haltenden Zug auf. Die Züge nach Caen hatten den ganzen Vormittag große Verspätung.',
                'sources' => ['wp-liste-1890', 'opregte-haarlemsche-1899-10-24'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien du train venant de Paris a été tué. Selon la liste de Wikipédia, trois agents du train heurté ont été blessés.',
                'en' => 'The driver of the train from Paris was killed. According to the Wikipedia list, three crew members of the train that was struck were injured.',
                'de' => 'Der Lokomotivführer des Zugs aus Paris wurde getötet. Nach der Wikipedia-Liste wurden drei Bedienstete des getroffenen Zugs verletzt.',
                'sources' => ['wp-liste-1890', 'opregte-haarlemsche-1899-10-24'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Lieu : la presse néerlandaise place la collision « à la gare d\'Argentan », la liste de Wikipédia peu avant cette gare, entre Surdon et Argentan.',
                'en' => 'Place: the Dutch press puts the collision "at Argentan station", the Wikipedia list just before that station, between Surdon and Argentan.',
                'de' => 'Ort: Die niederländische Presse verlegt den Zusammenstoß „an den Bahnhof Argentan“, die Wikipedia-Liste kurz davor, zwischen Surdon und Argentan.',
                'sources' => ['opregte-haarlemsche-1899-10-24', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'opregte-haarlemsche-1899-10-24'],
            'fr' => 'Un mécanicien tué selon la liste de Wikipédia (Le Petit Parisien du 23 octobre 1899) et la presse néerlandaise du 24 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One driver killed according to the Wikipedia list (Le Petit Parisien of 23 October 1899) and the Dutch press of the 24th; the record gave no number of deaths.',
            'de' => 'Ein Lokomotivführer getötet nach der Wikipedia-Liste (Le Petit Parisien vom 23. Oktober 1899) und der niederländischen Presse vom 24.; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
