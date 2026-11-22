<?php

// Contexte sourcé de la collision de Dettwiller du 31 janvier 1940 (recherche du 21/11/2026).
return [
    'slug' => '1940-01-31-dettwiller',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1940',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1940',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : peu avant minuit, erreur d\'aiguillage, autorail Strasbourg-Saverne contre un train de marchandises en manoeuvre à la sortie de la gare de Dettwiller, 4 morts et 7 blessés.',
        ],
        'delpher-1940-02-01' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinongeluk in Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1940-02-01',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB27:021099053:mpeg21:a00098',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche : automotrice Diesel Strasbourg-Saverne contre un train de marchandises à l\'arrêt, mauvaise position d\'aiguille, 3 morts et 7 blessés.',
        ],
        'delpher-1940-02-02' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Spoorwegongeluk tengevolge van verkeerden wisselstand. Drie dooden en zeven gewonden',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1940-02-02',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010248798:mpeg21:a0111',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche : l\'autorail a heurté dans la nuit un train de marchandises à l\'arrêt en gare ; la gare est écrite « Bettwiller ».',
        ],
        'delpher-1940-02-03' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinbotsing in Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1940-02-03',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:011221283:mpeg21:a0035',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche Transocean : collision en gare de Dettwiller, ligne Strasbourg-Saverne, dans la nuit du mercredi au jeudi, 3 morts.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du mercredi 31 janvier au jeudi 1er février 1940, un peu avant minuit selon la liste de Wikipédia, un autorail (une « automotrice » ou un « autorail Diesel » selon les dépêches) circulant de Strasbourg vers Saverne a heurté un train de marchandises à l\'arrêt ou en manoeuvre à la sortie de la gare de Dettwiller, sur la ligne de Paris-Est à Strasbourg.',
                'en' => 'In the night from Wednesday 31 January to Thursday 1 February 1940, shortly before midnight according to the Wikipedia list, a railcar (an "electric train" or "Diesel railcar" according to the reports) running from Strasbourg towards Saverne struck a goods train that was standing or shunting at the exit of Dettwiller station, on the Paris-Est to Strasbourg line.',
                'de' => 'In der Nacht von Mittwoch, dem 31. Januar, auf Donnerstag, den 1. Februar 1940, laut Wikipedia-Liste kurz vor Mitternacht, prallte ein Triebwagen (laut den Meldungen „elektrischer Zug“ oder „Dieseltriebwagen“) auf der Fahrt von Straßburg nach Zabern am Ausfahrtsende des Bahnhofs Dettwiller auf einen stehenden oder rangierenden Güterzug, auf der Strecke Paris-Est nach Straßburg.',
                'sources' => ['wp-liste-1940', 'delpher-1940-02-01', 'delpher-1940-02-02', 'delpher-1940-02-03'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Les deux sources attribuent la collision à une erreur d\'aiguillage : la liste de Wikipédia parle d\'une erreur d\'aiguillage, les dépêches néerlandaises d\'une mauvaise position d\'aiguille.',
                'en' => 'Both kinds of source attribute the collision to a points error: the Wikipedia list speaks of a points error, the Dutch reports of a wrong points position.',
                'de' => 'Beide Quellenarten führen die Kollision auf einen Weichenfehler zurück: Die Wikipedia-Liste spricht von einem Weichenfehler, die niederländischen Meldungen von einer falschen Weichenstellung.',
                'sources' => ['wp-liste-1940', 'delpher-1940-02-01', 'delpher-1940-02-02'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les dépêches néerlandaises donnent trois morts et sept blessés ; la liste de Wikipédia donne quatre morts et sept blessés.',
                'en' => 'The Dutch reports give three dead and seven injured; the Wikipedia list gives four dead and seven injured.',
                'de' => 'Die niederländischen Meldungen nennen drei Tote und sieben Verletzte; die Wikipedia-Liste vier Tote und sieben Verletzte.',
                'sources' => ['delpher-1940-02-01', 'delpher-1940-02-02', 'wp-liste-1940'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie (3 morts dans les dépêches du 1er au 3 février, 4 dans la liste de Wikipédia). Le nom de la gare varie aussi : Dettwiller (liste de Wikipédia et dépêche du 3 février), « Bettwiller » (dépêche du 2 février, sans doute une erreur de transmission). Le lieu est donc Dettwiller, et non Saverne.',
                'en' => 'The toll varies (3 dead in the reports of 1 to 3 February, 4 in the Wikipedia list). The station name also varies: Dettwiller (Wikipedia list and report of 3 February), "Bettwiller" (report of 2 February, probably a transmission error). The place is therefore Dettwiller, not Saverne.',
                'de' => 'Die Bilanz schwankt (3 Tote in den Meldungen vom 1. bis 3. Februar, 4 in der Wikipedia-Liste). Auch der Bahnhofsname schwankt: Dettwiller (Wikipedia-Liste und Meldung vom 3. Februar), „Bettwiller“ (Meldung vom 2. Februar, wohl ein Übermittlungsfehler). Der Ort ist daher Dettwiller, nicht Zabern.',
                'sources' => ['wp-liste-1940', 'delpher-1940-02-02', 'delpher-1940-02-03'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '67089',
            'sources' => ['wp-liste-1940', 'delpher-1940-02-03'],
            'fr' => 'La collision a eu lieu en gare de Dettwiller (liste de Wikipédia et dépêche du 3 février 1940), et non à Saverne, destination de l\'autorail.',
            'en' => 'The collision took place at Dettwiller station (Wikipedia list and report of 3 February 1940), not at Saverne, the railcar\'s destination.',
            'de' => 'Die Kollision ereignete sich im Bahnhof Dettwiller (Wikipedia-Liste und Meldung vom 3. Februar 1940), nicht in Zabern, dem Ziel des Triebwagens.',
        ],
    ],
];
