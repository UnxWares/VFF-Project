<?php

// Contexte sourcé de la collision en gare de Piney du 14 février 1917 (recherche du 21/11/2026).
return [
    'slug' => '1917-02-14-piney',
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
            'locator' => 'section Février',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1917&oldid=235357718#F%C3%A9vrier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Troyes à Brienne-le-Château, en gare de Piney, vers 1 heure, collision entre un express venant de Vitry-le-François et un train en partance pour Saint-Dizier ; 2 morts et 32 blessés.',
        ],
        'delpher-1917-02-16' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Telegrammen',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1917-02-16',
            'locator' => 'De Nederlander',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB15:000670056:mpeg21:a00016',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Genève du 16 février : dans la nuit de mercredi (le 14), vers 3 heures, un rapide venant de Vitry-le-François heurte en gare de Piney un train qui y attendait ; 2 morts et 25 blessés. Même texte dans De standaard du 17 février et De nieuwe courant du 16 février.',
        ],
        'figaro-1917-02-15' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro, 15 février 1917, p. 2',
            'publisher' => 'Gallica (Bibliothèque nationale de France)',
            'author' => null,
            'published' => '1917-02-15',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k291319h/f2.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Article cité par la liste de Wikipédia, avec Le Petit Parisien du 15 février et Le Matin du 16 février 1917.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 14 février 1917, en pleine nuit, en gare de Piney, sur la ligne de Troyes à Brienne-le-Château, un express venant de Vitry-le-François heurte un train arrêté qui devait partir pour Saint-Dizier.',
                'en' => 'In the night of 14 February 1917, at Piney station on the Troyes to Brienne-le-Château line, an express coming from Vitry-le-François ran into a stationary train due to leave for Saint-Dizier.',
                'de' => 'In der Nacht des 14. Februar 1917 fuhr im Bahnhof Piney an der Strecke Troyes - Brienne-le-Château ein aus Vitry-le-François kommender Schnellzug auf einen stehenden Zug auf, der nach Saint-Dizier abfahren sollte.',
                'sources' => ['wp-liste-1917', 'delpher-1917-02-16'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La collision fait deux morts. La liste de Wikipédia compte 32 blessés, la dépêche reprise aux Pays-Bas 25.',
                'en' => 'The collision killed two people. The Wikipedia list counts 32 injured, the report carried in the Netherlands 25.',
                'de' => 'Der Zusammenstoß forderte zwei Tote. Die Wikipedia-Liste zählt 32 Verletzte, die in den Niederlanden abgedruckte Meldung 25.',
                'sources' => ['wp-liste-1917', 'delpher-1917-02-16'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'L\'heure diffère : vers 1 heure selon la liste de Wikipédia, vers 3 heures selon la dépêche de Genève. Le nombre de blessés aussi : 32 ou 25.',
                'en' => 'The time differs: about 1 am according to the Wikipedia list, about 3 am according to the report from Geneva. So does the number of injured: 32 or 25.',
                'de' => 'Die Uhrzeit weicht ab: gegen 1 Uhr laut Wikipedia-Liste, gegen 3 Uhr laut der Meldung aus Genf. Ebenso die Zahl der Verletzten: 32 oder 25.',
                'sources' => ['wp-liste-1917', 'delpher-1917-02-16'],
            ],
        ],
    ],
];
