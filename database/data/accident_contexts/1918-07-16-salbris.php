<?php

// Contexte sourcé du déraillement entre Theillay et Salbris du 16 juillet 1918 (recherche du 21/11/2026).
return [
    'slug' => '1918-07-16-salbris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1918' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1918',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1918&oldid=238612092#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de juillet : ligne, trains, bilan de 15 morts et une trentaine de blessés ; cite La Presse du 17 juillet 1918, p. 1.',
        ],
        'theillay-temps-1918' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Histoire de Theillay (page reproduisant un extrait du Temps du 18 juillet 1918)',
            'publisher' => 'lemaire1957.net (site d\'histoire du Loir-et-Cher, de l\'Indre et du Cher)',
            'author' => null,
            'published' => null,
            'locator' => 'extrait du Temps, 18 juillet 1918',
            'url' => 'http://www.lemaire1957.net/pages/loir-et-cher-41/theillay/histoire-1-13.html',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Reprend en entier une brève du Temps : 15 morts, 20 blessés graves, cause non déterminée, voie unique entre Theillay et Salbris. Seule source lue qui date et décrit l\'accident par la presse de l\'époque.',
        ],
        'gallica-presse-1918' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse, 17 juillet 1918, p. 1',
            'publisher' => 'Gallica (BnF)',
            'author' => null,
            'published' => '1918-07-17',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k599357z.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lien cité par Wikipédia, non lu (Gallica inaccessible depuis la machine).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le soir du 16 juillet 1918, sur la ligne d\'Orléans à Vierzon, entre les gares de Theillay et de Salbris (Loir-et-Cher), quatre voitures du train-poste Le Mont-Dore - Paris déraillent.',
                'en' => 'On the evening of 16 July 1918, on the Orléans to Vierzon line, between Theillay and Salbris stations (Loir-et-Cher), four carriages of the Le Mont-Dore - Paris mail train derailed.',
                'de' => 'Am Abend des 16. Juli 1918 entgleisten auf der Strecke von Orléans nach Vierzon, zwischen den Bahnhöfen Theillay und Salbris (Loir-et-Cher), vier Wagen des Postzuges Le Mont-Dore - Paris.',
                'sources' => ['wp-liste-1918', 'theillay-temps-1918'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait 15 morts et une trentaine de blessés. Selon Le Temps, 20 blessés graves sont transportés dans les hôpitaux de Vierzon, et un certain nombre de blessés légers, pansés sur place, poursuivent leur route vers Paris.',
                'en' => 'The accident caused 15 deaths and about thirty injured. According to Le Temps, 20 seriously injured people were taken to the hospitals of Vierzon, and a number of slightly injured people, bandaged on the spot, continued their journey to Paris.',
                'de' => 'Bei dem Unglück starben 15 Menschen, etwa dreißig wurden verletzt. Nach Le Temps wurden 20 Schwerverletzte in die Krankenhäuser von Vierzon gebracht, und eine Reihe Leichtverletzter setzte nach einem Verband vor Ort die Fahrt nach Paris fort.',
                'sources' => ['wp-liste-1918', 'theillay-temps-1918'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause de l\'accident n\'était pas encore déterminée lors de la parution de la brève du Temps ; aucune source lue ne la donne ensuite. En temps de guerre, la presse n\'en parle que brièvement.',
                'en' => 'The cause of the accident had not yet been determined when the Le Temps item appeared; no source read gives it afterwards. In wartime the press covered it only briefly.',
                'de' => 'Die Unfallursache war zum Zeitpunkt der Kurzmeldung in Le Temps noch nicht bestimmt; keine gelesene Quelle nennt sie später. In Kriegszeiten berichtete die Presse nur kurz darüber.',
                'sources' => ['theillay-temps-1918', 'wp-liste-1918'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Un service à voie unique est mis en place entre Theillay et Salbris jusqu\'à nouvel ordre, ce qui entraîne des retards importants pour les trains circulant entre Vierzon et Paris.',
                'en' => 'A single-line service was put in place between Theillay and Salbris until further notice, causing significant delays to trains running between Vierzon and Paris.',
                'de' => 'Zwischen Theillay und Salbris wurde bis auf Weiteres ein eingleisiger Betrieb eingerichtet, was zu erheblichen Verspätungen der Züge zwischen Vierzon und Paris führte.',
                'sources' => ['theillay-temps-1918'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La date varie : la liste de Wikipédia place l\'accident au soir du 16 juillet 1918, alors que la brève du Temps, datée du 18 juillet, le situe « hier ». Le nombre de blessés diffère aussi selon qu\'on compte tous les blessés (environ 30) ou seulement les blessés graves (20).',
                'en' => 'The date varies: the Wikipedia list places the accident on the evening of 16 July 1918, whereas the Le Temps item, dated 18 July, places it "yesterday". The number of injured also differs depending on whether all injured (about 30) or only the seriously injured (20) are counted.',
                'de' => 'Das Datum schwankt: Die Wikipedia-Liste setzt das Unglück auf den Abend des 16. Juli 1918, während die Kurzmeldung in Le Temps vom 18. Juli es auf „gestern“ legt. Auch die Zahl der Verletzten unterscheidet sich, je nachdem, ob alle Verletzten (etwa 30) oder nur die Schwerverletzten (20) gezählt werden.',
                'sources' => ['wp-liste-1918', 'theillay-temps-1918'],
            ],
        ],
    ],
];
