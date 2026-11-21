<?php

// Contexte sourcé de l'accident d'autorail d'Auxerre-Migraines du 21 janvier 1927 (recherche du 21/11/2026).
return [
    'slug' => '1927-01-21-auxerre',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-ligne-joigny-auxerre' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Ligne de Joigny à Auxerre',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Histoire',
            'url' => 'https://fr.wikipedia.org/wiki/Ligne_de_Joigny_%C3%A0_Auxerre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Achat d\'une automotrice Renault NF en 1926, accident du 21 janvier 1927 à Auxerre-Migraines (un mort, sept blessés), engin réparé puis muté ; mentionne un rapport de l\'agent voyer en chef du 27 janvier conservé aux archives départementales de l\'Yonne, non consulté ici.',
        ],
        'wp-liste-1927' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1927',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1927&oldid=233501538#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Autorail déraillé en gare, deux wagons heurtés, un mort, sept blessés graves et quelques blessés légers.',
        ],
        'gallica-matin-1927-01-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 22 janvier 1927, page 3',
            'publisher' => 'Le Matin (Gallica, BnF)',
            'author' => null,
            'published' => '1927-01-22',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k575680p/f3.image.r=d%C3%A9raille.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence de presse citée par la liste de Wikipédia, à consulter ; non lue.',
        ],
        'gallica-humanite-1927-01-22' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'L\'Humanité du 22 janvier 1927, page 1',
            'publisher' => 'L\'Humanité (Gallica, BnF)',
            'author' => null,
            'published' => '1927-01-22',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k402322q.r=d%C3%A9raillement.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Seconde référence de presse citée par la liste de Wikipédia, à consulter ; non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'La ligne métrique de Joigny à Auxerre était exploitée par la Compagnie des chemins de fer départementaux depuis 1923. En 1926, celle-ci a acheté une automotrice Renault de type NF pour remplacer les trains à vapeur, lents et coûteux.',
                'en' => 'The metre-gauge line from Joigny to Auxerre had been operated by the Compagnie des chemins de fer départementaux since 1923. In 1926 the company bought a Renault NF railcar to replace the steam trains, which were slow and costly.',
                'de' => 'Die meterspurige Strecke von Joigny nach Auxerre wurde seit 1923 von der Compagnie des chemins de fer départementaux betrieben. 1926 kaufte diese einen Triebwagen Renault vom Typ NF, um die langsamen und teuren Dampfzüge zu ersetzen.',
                'sources' => ['wp-ligne-joigny-auxerre'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le 21 janvier 1927, cet autorail a été victime d\'un accident à Auxerre-Migraines. Selon la liste de Wikipédia, c\'était le matin, en gare : l\'autorail a déraillé, heurté deux wagons et s\'est couché.',
                'en' => 'On 21 January 1927 this railcar was involved in an accident at Auxerre-Migraines. According to the Wikipedia list, it happened in the morning, in the station: the railcar derailed, struck two wagons and fell on its side.',
                'de' => 'Am 21. Januar 1927 verunglückte dieser Triebwagen in Auxerre-Migraines. Nach der Wikipedia-Liste geschah dies am Morgen im Bahnhof: Der Triebwagen entgleiste, stiess gegen zwei Wagen und kippte um.',
                'sources' => ['wp-ligne-joigny-auxerre', 'wp-liste-1927'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident a fait un mort et sept blessés. La liste de Wikipédia précise que les sept blessés étaient graves et mentionne en plus quelques blessés légers.',
                'en' => 'The accident killed one person and injured seven. The Wikipedia list specifies that the seven were seriously hurt and adds a few slightly injured people.',
                'de' => 'Der Unfall forderte einen Toten und sieben Verletzte. Die Wikipedia-Liste präzisiert, dass die sieben schwer verletzt waren, und nennt zusätzlich einige Leichtverletzte.',
                'sources' => ['wp-ligne-joigny-auxerre', 'wp-liste-1927'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'L\'autorail a été réparé puis muté sur la ligne de Sens à Nogent-sur-Seine. Un rapport de l\'agent voyer en chef daté du 27 janvier, conservé aux archives départementales de l\'Yonne, détaille les circonstances et les causes, selon l\'article de Wikipédia, qui ne les reprend pas.',
                'en' => 'The railcar was repaired and then transferred to the Sens to Nogent-sur-Seine line. A report by the chief road surveyor dated 27 January, held in the Yonne departmental archives, details the circumstances and causes, according to the Wikipedia article, which does not reproduce them.',
                'de' => 'Der Triebwagen wurde repariert und dann auf die Strecke von Sens nach Nogent-sur-Seine versetzt. Ein auf den 27. Januar datierter Bericht des leitenden Straßenbauingenieurs („agent voyer en chef“) in den Departementsarchiven der Yonne beschreibt laut dem Wikipedia-Artikel die Umstände und Ursachen; der Artikel gibt sie nicht wieder.',
                'sources' => ['wp-ligne-joigny-auxerre'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia attribue la ligne à la Société des chemins de fer de l\'Yonne, alors que l\'article sur la ligne de Joigny à Auxerre indique une exploitation par la Compagnie des chemins de fer départementaux depuis 1923.',
                'en' => 'The Wikipedia list attributes the line to the Société des chemins de fer de l\'Yonne, whereas the article on the Joigny to Auxerre line states that it had been operated by the Compagnie des chemins de fer départementaux since 1923.',
                'de' => 'Die Wikipedia-Liste ordnet die Strecke der Société des chemins de fer de l\'Yonne zu, während der Artikel zur Strecke von Joigny nach Auxerre angibt, sie sei seit 1923 von der Compagnie des chemins de fer départementaux betrieben worden.',
                'sources' => ['wp-liste-1927', 'wp-ligne-joigny-auxerre'],
            ],
        ],
    ],
    'corrections' => [],
];
