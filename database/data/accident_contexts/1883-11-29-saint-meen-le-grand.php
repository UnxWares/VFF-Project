<?php

// Contexte sourcé de la collision d'une locomotive poussant un wagon d'ouvriers et d'un train de wagons vides près de Saint-Méen, ligne de Ploërmel à La Brohinière en construction, de novembre 1883 (recherche du 21/11/2026).
return [
    'slug' => '1883-11-29-saint-meen-le-grand',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1883',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1883',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 29 novembre 1883, d\'après Le Petit Parisien des 3 et 5 décembre : sur la ligne de Ploërmel à La Brohinière, encore en construction par l\'État, vers 19 h, une locomotive revenant du chantier en poussant un wagon de ballast où avaient pris place 38 ouvriers heurte de front, près de Saint-Méen, un train de wagons vides en double traction ; dix-huit ouvriers tués sur le coup, quinze si grièvement blessés que plusieurs succomberont.',
        ],
        'ddb-1883-12-01-bonn' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 30. Nov.',
            'publisher' => 'Bonner Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1883-12-01',
            'locator' => 'p. 1',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/MJ37SGWLJTB75J5LPLI53OYOFHK646BH?issuepage=1',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 30 novembre, d\'après la Kölnische Zeitung (même texte dans la Bergische Zeitung du 4 décembre) : hier soir, près de Saint-Méen (Ille-et-Vilaine), sur l\'embranchement de Ploërmel, collision de deux trains ; 14 personnes tuées et 17 grièvement blessées.',
        ],
        'ddb-1883-12-01-waechter' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück',
            'publisher' => 'Der Wächter (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1883-12-01',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/WEG2ORXYHYWAMUYBRZICF56WBPLNA65J?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche du 30 novembre : collision de deux trains près de Saint-Méen sur l\'embranchement de Ploërmel, 14 morts et 17 blessés graves.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'La ligne de Ploërmel à La Brohinière était encore en construction par l\'État ; elle devait être remise à la Compagnie de l\'Ouest le 1er janvier 1884. Le 29 novembre 1883, vers 19 h, une locomotive revenait du chantier en poussant un wagon de ballast sur lequel 38 ouvriers rentraient du travail.',
                'en' => 'The Ploërmel to La Brohinière line was still being built by the State and was to be handed to the Ouest company on 1 January 1884. On 29 November 1883, at about 7 pm, an engine was returning from the worksite pushing a ballast wagon on which 38 workers were riding home.',
                'de' => 'Die Strecke Ploërmel-La Brohinière wurde noch vom Staat gebaut und sollte am 1. Januar 1884 an die Westbahngesellschaft übergeben werden. Am 29. November 1883 gegen 19 Uhr kehrte eine Lokomotive von der Baustelle zurück und schob einen Schotterwagen, auf dem 38 Arbeiter nach Hause fuhren.',
                'sources' => ['wp-liste-1880'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Près de Saint-Méen, elle a heurté de front un train de wagons vides tiré par deux locomotives.',
                'en' => 'Near Saint-Méen it collided head-on with a train of empty wagons hauled by two engines.',
                'de' => 'Bei Saint-Méen stieß sie frontal mit einem von zwei Lokomotiven gezogenen Zug leerer Wagen zusammen.',
                'sources' => ['wp-liste-1880', 'ddb-1883-12-01-bonn', 'ddb-1883-12-01-waechter'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste de Wikipédia, dix-huit ouvriers ont été tués sur le coup et quinze si grièvement blessés que plusieurs sont morts ensuite. La dépêche de Paris du lendemain compte 14 morts et 17 blessés graves.',
                'en' => 'According to the Wikipedia list eighteen workers were killed outright and fifteen so badly injured that several died later. The Paris dispatch of the next day counts 14 dead and 17 seriously injured.',
                'de' => 'Nach der Wikipedia-Liste wurden achtzehn Arbeiter sofort getötet und fünfzehn so schwer verletzt, dass mehrere später starben. Die Pariser Depesche vom nächsten Tag zählt 14 Tote und 17 Schwerverletzte.',
                'sources' => ['wp-liste-1880', 'ddb-1883-12-01-bonn', 'ddb-1883-12-01-waechter'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La dépêche de Paris du 30 novembre donne 14 morts et 17 blessés graves, la liste de Wikipédia, d\'après Le Petit Parisien des 3 et 5 décembre, 18 morts sur le coup et 15 blessés graves, sans compter les blessés morts ensuite.',
                'en' => 'The Paris dispatch of 30 November gives 14 dead and 17 seriously injured; the Wikipedia list, after Le Petit Parisien of 3 and 5 December, gives 18 killed outright and 15 seriously injured, not counting the injured who died later.',
                'de' => 'Die Pariser Depesche vom 30. November nennt 14 Tote und 17 Schwerverletzte, die Wikipedia-Liste nach Le Petit Parisien vom 3. und 5. Dezember 18 sofort Getötete und 15 Schwerverletzte, die später Verstorbenen nicht eingerechnet.',
                'sources' => ['ddb-1883-12-01-bonn', 'wp-liste-1880'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 18,
            'sources' => ['wp-liste-1880', 'ddb-1883-12-01-bonn'],
            'fr' => 'Dix-huit ouvriers tués sur le coup selon la liste de Wikipédia (Le Petit Parisien des 3 et 5 décembre 1883), plus des blessés morts ensuite ; la dépêche de Paris du 30 novembre en comptait 14 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Eighteen workers killed outright according to the Wikipedia list (Le Petit Parisien of 3 and 5 December 1883), plus injured who died later; the Paris dispatch of 30 November counted 14; the record gave no number of deaths.',
            'de' => 'Achtzehn Arbeiter sofort getötet nach der Wikipedia-Liste (Le Petit Parisien vom 3. und 5. Dezember 1883), dazu später verstorbene Verletzte; die Pariser Depesche vom 30. November zählte 14; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
