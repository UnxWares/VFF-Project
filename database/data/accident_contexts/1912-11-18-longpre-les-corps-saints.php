<?php

// Contexte sourcé de la collision de l'express Paris - Calais et d'une machine de manœuvre en gare de Longpré-les-Corps-Saints, de novembre 1912 (recherche du 21/11/2026).
return [
    'slug' => '1912-11-18-longpre-les-corps-saints',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1912' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1912',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1912&oldid=233727902#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 18 novembre 1912, d\'après Le Matin et Le Petit Parisien du 19 novembre : en gare de Longpré-les-Corps-Saints, vers 11 h 30, sur la ligne d\'Amiens à Calais, l\'express Paris - Calais heurte une machine de manœuvre et déraille ; les voyageurs sont indemnes, le mécanicien de la machine heurtée est tué, quatre autres cheminots blessés, dont un mortellement.',
        ],
        'ddb-1912-11-19-echo' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück in Frankreich',
            'publisher' => 'Echo der Gegenwart (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1912-11-19',
            'locator' => 'p. 19',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/RCNTFAJ6TQOOMKSLKXFWL5ZVM5DN2WCY?issuepage=19',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : l\'express Paris - Londres a heurté une locomotive lundi en gare de Longpré ; le mécanicien tué sur le coup, un agent aux deux jambes broyées dans un état désespéré à l\'hôpital d\'Amiens ; ligne coupée jusqu\'après minuit.',
        ],
        'ddb-1912-11-19-beobachter' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 19. Nov.',
            'publisher' => 'Badischer Beobachter (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1912-11-19',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/D6VKHWYP7ASJVAYUBTFBQNYEPMVKAY4E?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 19 novembre : même récit, l\'express a déraillé et le fourgon qui suivait la locomotive a été mis en pièces.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le lundi 18 novembre 1912, vers 11 h 30, l\'express de Paris à Calais, en correspondance pour Londres, a heurté une machine de manœuvre en gare de Longpré-les-Corps-Saints, sur la ligne d\'Amiens à Calais, puis a déraillé. Le fourgon placé derrière la locomotive a été mis en pièces et la ligne est restée coupée jusqu\'après minuit.',
                'en' => 'On Monday 18 November 1912, at about 11.30 am, the Paris to Calais express, connecting for London, struck a shunting engine at Longpré-les-Corps-Saints station on the Amiens to Calais line and then derailed. The van behind the engine was smashed and the line stayed closed until after midnight.',
                'de' => 'Am Montag, dem 18. November 1912, gegen 11.30 Uhr prallte der Expresszug Paris-Calais mit Anschluss nach London im Bahnhof Longpré-les-Corps-Saints an der Strecke Amiens-Calais auf eine Rangierlokomotive und entgleiste. Der Packwagen hinter der Lokomotive wurde zertrümmert, die Strecke blieb bis nach Mitternacht gesperrt.',
                'sources' => ['wp-liste-1912', 'ddb-1912-11-19-echo', 'ddb-1912-11-19-beobachter'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les voyageurs n\'ont pas été blessés. Un mécanicien a été tué sur le coup et un autre agent, les deux jambes broyées, a été transporté à l\'hôpital d\'Amiens dans un état désespéré ; la liste de Wikipédia compte quatre cheminots blessés, dont un mortellement.',
                'en' => 'No passenger was hurt. A driver was killed outright and another railwayman, both legs crushed, was taken to hospital in Amiens with no hope of recovery; the Wikipedia list counts four railwaymen injured, one fatally.',
                'de' => 'Die Reisenden blieben unverletzt. Ein Lokführer war sofort tot, ein anderer Bediensteter mit zerschmetterten Beinen wurde hoffnungslos ins Krankenhaus von Amiens gebracht; die Wikipedia-Liste zählt vier verletzte Eisenbahner, davon einer tödlich.',
                'sources' => ['wp-liste-1912', 'ddb-1912-11-19-echo'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1912', 'ddb-1912-11-19-echo'],
            'fr' => 'L\'express a heurté une machine de manœuvre avant de dérailler, selon la liste de Wikipédia et la presse allemande ; la fiche le classait en déraillement.',
            'en' => 'The express struck a shunting engine before derailing, according to the Wikipedia list and the German press; the record classed it as a derailment.',
            'de' => 'Der Expresszug prallte nach der Wikipedia-Liste und der deutschen Presse auf eine Rangierlokomotive, bevor er entgleiste; der Eintrag führte den Unfall als Entgleisung.',
        ],
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1912', 'ddb-1912-11-19-echo'],
            'fr' => 'Un mécanicien tué et un agent blessé mortellement selon la liste de Wikipédia ; la presse allemande donne un mort et un blessé dans un état désespéré ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One driver killed and one railwayman fatally injured according to the Wikipedia list; the German press gives one dead and one injured man with no hope of recovery; the record gave no number of deaths.',
            'de' => 'Ein Lokführer getötet und ein Bediensteter tödlich verletzt nach der Wikipedia-Liste; die deutsche Presse nennt einen Toten und einen hoffnungslos Verletzten; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
