<?php

// Contexte sourcé de la collision d'un train de marchandises venant d'Alès et d'une machine de manœuvre près de la gare du Teil, d'août 1909 (recherche du 21/11/2026).
return [
    'slug' => '1909-08-07-le-teil',
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
            'note' => 'Entrée du 7 août 1909, d\'après Le Matin du 9 août : sur la ligne du Teil à Alès, vers 22 h 30, peu avant la gare du Teil, collision entre un train de marchandises venant d\'Alès et une machine de manœuvre ; les deux mécaniciens sont tués, leurs chauffeurs et un employé des Postes blessés.',
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
                'fr' => 'Vers 22 h 30, peu avant la gare du Teil, sur la ligne du Teil à Alès, un train de marchandises venant d\'Alès a heurté une machine de manœuvre. Selon la presse allemande, ses freins fonctionnaient mal.',
                'en' => 'At about 10.30 pm, just before Le Teil station on the Le Teil to Alès line, a goods train from Alès ran into a shunting engine. According to the German press its brakes were not working properly.',
                'de' => 'Gegen 22.30 Uhr stieß kurz vor dem Bahnhof Le Teil an der Strecke Le Teil-Alès ein aus Alès kommender Güterzug mit einer Rangierlokomotive zusammen. Nach der deutschen Presse funktionierten seine Bremsen schlecht.',
                'sources' => ['wp-liste-1909', 'ddb-1909-08-11-correspondent'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les deux mécaniciens ont été tués sur le coup. Les deux chauffeurs et d\'autres agents ont été blessés.',
                'en' => 'Both drivers were killed outright. The two stokers and other staff were injured.',
                'de' => 'Beide Lokomotivführer waren sofort tot. Die beiden Heizer und weitere Bedienstete wurden verletzt.',
                'sources' => ['wp-liste-1909', 'ddb-1909-08-11-correspondent'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia date la collision du samedi 7 août 1909 ; la correspondance de Paris parue le 11 août la range parmi les accidents du dimanche. Parmi les autres blessés, la liste compte un employé des Postes, la presse allemande deux agents grièvement blessés.',
                'en' => 'The Wikipedia list dates the collision to Saturday 7 August 1909; the Paris correspondence published on 11 August lists it among Sunday\'s accidents. Among the other injured, the list counts a post office employee, the German press two seriously injured railwaymen.',
                'de' => 'Die Wikipedia-Liste datiert den Zusammenstoß auf Samstag, den 7. August 1909; der am 11. August erschienene Pariser Bericht zählt ihn zu den Unfällen des Sonntags. Unter den übrigen Verletzten nennt die Liste einen Postbediensteten, die deutsche Presse zwei schwer verletzte Bedienstete.',
                'sources' => ['wp-liste-1909', 'ddb-1909-08-11-correspondent'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1909', 'ddb-1909-08-11-correspondent'],
            'fr' => 'Les deux mécaniciens tués selon la liste de Wikipédia (Le Matin du 9 août 1909) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Both drivers killed according to the Wikipedia list (Le Matin of 9 August 1909) and the German press; the record gave no number of deaths.',
            'de' => 'Beide Lokomotivführer getötet nach der Wikipedia-Liste (Le Matin vom 9. August 1909) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
