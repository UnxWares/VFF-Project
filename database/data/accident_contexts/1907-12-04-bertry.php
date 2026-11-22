<?php

// Contexte sourcé de l'explosion de la machine d'un train de charbon pour Creil près de la gare de Bertry, ligne de Busigny à Somain, de décembre 1907 (recherche du 21/11/2026).
return [
    'slug' => '1907-12-04-bertry',
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
            'locator' => 'Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1907&oldid=238956681#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 4 décembre 1907, d\'après Le Petit Journal du 5 décembre : sur la ligne de Busigny à Somain, à 5 h, peu après la gare de Bertry, une première explosion dans le foyer de la machine d\'un train de charbon pour Creil tue le mécanicien et le chauffeur ; cent mètres plus loin, la chaudière explose et le convoi déraille ; le conducteur du fourgon de tête, grièvement blessé, fait arrêter à temps un train de voyageurs ; l\'enquête envisage des cartouches de dynamite mêlées au charbon du tender.',
        ],
        'ddb-1907-12-05-bielefeld' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Explosion einer Lokomotive',
            'publisher' => 'Bielefelder General-Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1907-12-05',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/4BKJ6URXULNXFICOUJPBBHWZVU6HRVQ7?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 5 décembre, d\'après Saint-Quentin : en gare de Bertry, l\'explosion d\'une locomotive a tué le mécanicien et le chauffeur et blessé le chef de train ; une usine électrique voisine a été fortement endommagée par les débris.',
        ],
        'ddb-1907-12-05-wuerttemberg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Infolge der Explosion einer Lokomotive',
            'publisher' => 'Württemberger Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1907-12-05',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/UOFCIOOFKIOPHP3C4KDEEXHLMUBBCQBK?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même nouvelle, qui place Bertry « près de Paris » : mécanicien et chauffeur tués, chef de train blessé, usine électrique voisine endommagée.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 4 décembre 1907, vers 5 h, la machine d\'un train de charbon allant à Creil a explosé peu après la gare de Bertry, sur la ligne de Busigny à Somain. Une première explosion a eu lieu dans le foyer ; une centaine de mètres plus loin, la chaudière a éclaté et le train a déraillé. Une usine électrique voisine a été endommagée par les débris.',
                'en' => 'On 4 December 1907, at about 5 am, the engine of a coal train bound for Creil exploded shortly after Bertry station on the Busigny to Somain line. A first explosion occurred in the firebox; about a hundred metres further on the boiler burst and the train derailed. A nearby electricity works was damaged by debris.',
                'de' => 'Am 4. Dezember 1907 gegen 5 Uhr explodierte kurz hinter dem Bahnhof Bertry an der Strecke Busigny-Somain die Lokomotive eines Kohlenzugs nach Creil. Eine erste Explosion ereignete sich in der Feuerbüchse; etwa hundert Meter weiter barst der Kessel und der Zug entgleiste. Ein nahes Elektrizitätswerk wurde durch Trümmer beschädigt.',
                'sources' => ['wp-liste-1907', 'ddb-1907-12-05-bielefeld'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chauffeur ont été tués. Un agent du train, grièvement blessé, a pu faire arrêter à temps un train de voyageurs qui approchait.',
                'en' => 'The driver and the stoker were killed. A member of the train crew, badly injured, managed to have an approaching passenger train stopped in time.',
                'de' => 'Lokomotivführer und Heizer wurden getötet. Ein schwer verletzter Zugbegleiter konnte einen herannahenden Personenzug noch rechtzeitig anhalten lassen.',
                'sources' => ['wp-liste-1907', 'ddb-1907-12-05-bielefeld', 'ddb-1907-12-05-wuerttemberg'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Lors de l\'enquête, on a envisagé que des cartouches de dynamite aient pu se trouver mêlées au charbon du tender.',
                'en' => 'During the inquiry it was suggested that dynamite cartridges might have been mixed in with the coal in the tender.',
                'de' => 'Bei der Untersuchung wurde erwogen, dass Dynamitpatronen in die Kohle des Tenders geraten sein könnten.',
                'sources' => ['wp-liste-1907'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1907', 'ddb-1907-12-05-bielefeld'],
            'fr' => 'Mécanicien et chauffeur tués selon la liste de Wikipédia (Le Petit Journal du 5 décembre 1907) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and stoker killed according to the Wikipedia list (Le Petit Journal of 5 December 1907) and the German press; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Heizer getötet nach der Wikipedia-Liste (Le Petit Journal vom 5. Dezember 1907) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '59074',
            'sources' => ['wp-liste-1907', 'ddb-1907-12-05-bielefeld'],
            'fr' => 'L\'explosion a eu lieu près de la gare de Bertry selon la liste de Wikipédia et la presse allemande ; la fiche portait Creil, destination du train.',
            'en' => 'The explosion happened near Bertry station according to the Wikipedia list and the German press; the record had Creil, the train\'s destination.',
            'de' => 'Die Explosion ereignete sich nach der Wikipedia-Liste und der deutschen Presse beim Bahnhof Bertry; der Eintrag nannte Creil, das Ziel des Zugs.',
        ],
    ],
];
