<?php

// Contexte sourcé de l'accident d'un train de plaisir Chartres - Dieppe au passage à niveau de Louviers, ligne de Rouen à Orléans, d'août 1909 (recherche du 21/11/2026).
return [
    'slug' => '1909-08-08-dieppe',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1909' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1909',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1909&oldid=241222447#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 8 août 1909, d\'après Le Figaro et Le Matin du 9 août : sur la ligne de Rouen à Orléans, vers 7 h 30, au passage à niveau de Louviers, un train de plaisir allant de Chartres à Dieppe heurte une automobile ; un enfant est tué, sa mère et sa grand-mère blessées.',
        ],
        'ddb-1909-08-11-correspondent' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Aus Paris wird uns darüber geschrieben',
            'publisher' => 'Hamburgischer Correspondent (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1909-08-11',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/ZBYYQJG2G4NRLYETPERKLVENQ6FQPGFQ?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Correspondance de Paris sur les accidents du dimanche précédent : près de la gare du Teil, un train de marchandises a heurté une locomotive en manœuvre, les deux mécaniciens tués sur le coup, les chauffeurs et deux autres agents grièvement blessés, à cause d\'un mauvais fonctionnement des freins ; un train de plaisir de Chartres à Dieppe a heurté près de Louviers un véhicule isolé où se trouvaient un enfant, sa mère et sa grand-mère ; l\'enfant a été tué, les deux femmes grièvement blessées.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le dimanche 8 août 1909, vers 7 h 30, un train de plaisir allant de Chartres à Dieppe a heurté un véhicule à Louviers, sur la ligne de Rouen à Orléans. Selon la liste de Wikipédia, c\'était une automobile engagée sur le passage à niveau.',
                'en' => 'On Sunday 8 August 1909, at about 7.30 am, an excursion train from Chartres to Dieppe struck a vehicle at Louviers on the Rouen to Orléans line. According to the Wikipedia list it was a motor car on the level crossing.',
                'de' => 'Am Sonntag, dem 8. August 1909, gegen 7.30 Uhr erfasste ein Vergnügungszug von Chartres nach Dieppe in Louviers an der Strecke Rouen-Orléans ein Fahrzeug. Nach der Wikipedia-Liste war es ein Automobil auf dem Bahnübergang.',
                'sources' => ['wp-liste-1909', 'ddb-1909-08-11-correspondent'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un enfant a été tué ; sa mère et sa grand-mère ont été blessées.',
                'en' => 'A child was killed; the child\'s mother and grandmother were injured.',
                'de' => 'Ein Kind wurde getötet, seine Mutter und Großmutter wurden verletzt.',
                'sources' => ['wp-liste-1909', 'ddb-1909-08-11-correspondent'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Selon la correspondance de Paris du Hamburgischer Correspondent, les trois victimes se trouvaient dans un wagon de chemin de fer isolé ; la liste de Wikipédia parle d\'une automobile au passage à niveau.',
                'en' => 'According to the Hamburgischer Correspondent\'s Paris correspondence the three victims were in a single railway wagon standing on its own; the Wikipedia list speaks of a motor car on the level crossing.',
                'de' => 'Nach dem Pariser Bericht des Hamburgischen Correspondenten saßen die drei Opfer in einem einzeln stehenden Eisenbahnwagen; die Wikipedia-Liste spricht von einem Automobil auf dem Bahnübergang.',
                'sources' => ['ddb-1909-08-11-correspondent', 'wp-liste-1909'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1909', 'ddb-1909-08-11-correspondent'],
            'fr' => 'Un enfant tué selon la liste de Wikipédia (Le Matin du 9 août 1909) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One child killed according to the Wikipedia list (Le Matin of 9 August 1909) and the German press; the record gave no number of deaths.',
            'de' => 'Ein Kind getötet nach der Wikipedia-Liste (Le Matin vom 9. August 1909) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '27375',
            'sources' => ['wp-liste-1909', 'ddb-1909-08-11-correspondent'],
            'fr' => 'L\'accident a eu lieu à Louviers selon la liste de Wikipédia et la presse allemande ; la fiche portait Dieppe, destination du train.',
            'en' => 'The accident happened at Louviers according to the Wikipedia list and the German press; the record had Dieppe, the train\'s destination.',
            'de' => 'Der Unfall ereignete sich nach der Wikipedia-Liste und der deutschen Presse in Louviers; der Eintrag nannte Dieppe, das Ziel des Zugs.',
        ],
    ],
];
