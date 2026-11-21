<?php

// Contexte sourcé de l'accident de Rouen du 3 octobre 1917 (recherche du 21/11/2026).
return [
    'slug' => '1917-10-03-rouen',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1917' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1917',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1917&oldid=235357718#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : à Rouen, à la sortie du viaduc d\'Eauplet, la locomotive d\'un train de voyageurs vers la gare de Rouen-Saint-Sever déraille après avoir heurté une pièce de bois tombée d\'un train de marchandises, les voitures s\'encastrent, 3 morts et 2 blessés graves.',
        ],
        'longlongtrail-9th-ambulance-train' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => 'Fatal accident to 9th Ambulance Train, Rouen Rive-Gauche, 3 October 1917',
            'publisher' => 'The Long, Long Trail',
            'author' => 'Chris Baker',
            'published' => '2024-10-21',
            'locator' => null,
            'url' => 'https://www.longlongtrail.co.uk/fatal-accident-to-9th-ambulance-train-rouen-rive-gauche-3-october-1917/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Page d\'histoire militaire britannique : le 3 octobre 1917 vers 22 h 40, le train sanitaire n° 9, parti de Grévillers à 14 h 43 avec des malades et blessés pour les hôpitaux de Rouen, est accidenté en approchant de Rouen Rive-Gauche (Saint-Sever) ; trois soldats tués dans les compartiments télescopés.',
        ],
        'matin-1917-10-11' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 11 octobre 1917',
            'publisher' => 'Le Matin',
            'author' => null,
            'published' => '1917-10-11',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k572243c/f2.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de l\'accident, cité par Wikipédia.',
        ],
        'temps-1917-10-07' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 7 octobre 1917',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1917-10-07',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2430565/f3.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de l\'accident, cité par Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 octobre 1917, un train se dirigeant vers la gare de Rouen-Saint-Sever (Rouen Rive-Gauche) a été accidenté à la sortie du viaduc d\'Eauplet. Une source britannique en fait le train sanitaire n° 9, qui évacuait des malades et des blessés de l\'armée britannique vers les hôpitaux de base de Rouen, et situe l\'accident vers 22 h 40.',
                'en' => 'On 3 October 1917, a train heading for Rouen-Saint-Sever station (Rouen Rive-Gauche) was involved in an accident at the exit of the Eauplet viaduct. A British source identifies it as the 9th Ambulance Train, carrying sick and wounded British soldiers to the base hospitals at Rouen, and places the accident at about 10.40 pm.',
                'de' => 'Am 3. Oktober 1917 verunglückte ein Zug auf dem Weg zum Bahnhof Rouen-Saint-Sever (Rouen Rive-Gauche) am Ausgang des Viadukts von Eauplet. Eine britische Quelle identifiziert ihn als Lazarettzug Nr. 9, der kranke und verwundete britische Soldaten zu den Basislazaretten in Rouen brachte, und nennt als Zeitpunkt etwa 22.40 Uhr.',
                'sources' => ['wp-liste-1917', 'longlongtrail-9th-ambulance-train'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon Wikipédia, la locomotive a déraillé après avoir heurté une pièce de bois tombée d\'un train de marchandises, et les voitures qui la suivaient se sont encastrées. La source britannique décrit des compartiments télescopés ; le train endommagé a dû être évacué et la circulation déviée sur l\'ancien pont.',
                'en' => 'According to Wikipedia, the locomotive derailed after striking a piece of timber that had fallen from a goods train, and the coaches behind it ran into one another. The British source describes telescoped compartments; the damaged train had to be removed and traffic was diverted over the old bridge.',
                'de' => 'Laut Wikipedia entgleiste die Lokomotive, nachdem sie ein von einem Güterzug gefallenes Holzstück gerammt hatte, und die folgenden Wagen schoben sich ineinander. Die britische Quelle beschreibt ineinandergeschobene Abteile; der beschädigte Zug musste geräumt werden, und der Verkehr wurde über die alte Brücke umgeleitet.',
                'sources' => ['wp-liste-1917', 'longlongtrail-9th-ambulance-train'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les deux sources comptent trois morts, des soldats inhumés au cimetière de Saint-Sever. Wikipédia ajoute deux blessés graves ; la source britannique cite trois autres hommes blessés au journal de marche et de nombreux hommes choqués, avec des fractures.',
                'en' => 'Both sources count three dead, soldiers buried in Saint-Sever cemetery. Wikipedia adds two seriously injured; the British source cites three other injured men in the war diary and many men in shock, some with fractures.',
                'de' => 'Beide Quellen zählen drei Tote, Soldaten, die auf dem Friedhof Saint-Sever bestattet wurden. Wikipedia nennt zusätzlich zwei Schwerverletzte; die britische Quelle führt im Kriegstagebuch drei weitere Verletzte und viele unter Schock stehende Männer, teils mit Knochenbrüchen, auf.',
                'sources' => ['wp-liste-1917', 'longlongtrail-9th-ambulance-train'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La source britannique ne précise pas si l\'accident est un déraillement ou une collision ; Wikipédia parle d\'un déraillement de la locomotive suivi d\'un encastrement des voitures. Le nombre de blessés n\'est pas le même dans les deux sources.',
                'en' => 'The British source does not say whether the accident was a derailment or a collision; Wikipedia describes the locomotive derailing and the coaches running into one another. The number of injured differs between the two sources.',
                'de' => 'Die britische Quelle sagt nicht, ob es sich um eine Entgleisung oder eine Kollision handelte; Wikipedia beschreibt eine Entgleisung der Lokomotive, gefolgt vom Ineinanderschieben der Wagen. Die Zahl der Verletzten unterscheidet sich zwischen den beiden Quellen.',
                'sources' => ['wp-liste-1917', 'longlongtrail-9th-ambulance-train'],
            ],
        ],
    ],
];
