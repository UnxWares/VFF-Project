<?php

// Contexte sourcé du tamponnement d'un omnibus par le rapide Belfort - Paris à Chamarandes-Choignes, ligne de Paris à Mulhouse, de janvier 1909 (recherche du 21/11/2026).
return [
    'slug' => '1909-01-01-chamarandes-choignes',
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
            'locator' => 'Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1909&oldid=241222447#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 1er janvier 1909, d\'après Le Petit Parisien du 3 janvier : sur la ligne Paris - Mulhouse, peu avant Chaumont, à Chamarandes-Choignes, le rapide Belfort - Paris rattrape et heurte un omnibus en retard qui devait être garé à Chaumont pour lui laisser le passage, et pulvérise ses trois fourgons de queue ; deux garde-freins sont tués, trois voyageurs blessés.',
        ],
        'ddb-1909-01-03-reichsbote' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Chaumont, 2. Januar. Ein schwerer Eisenbahn-Unfall',
            'publisher' => 'Der Reichsbote (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1909-01-03',
            'locator' => 'p. 12',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/T5R2COC4D544SY4FKN6C5WSGBGYONZUA?issuepage=12',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Chaumont du 2 janvier : la veille au soir, près de Chaumont, l\'express Bâle - Paris a heurté un train de voyageurs ; deux agents du chemin de fer et trois voyageurs ont été tués.',
        ],
        'ddb-1909-01-05-neckarbote' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Chaumont, 2. Jan.',
            'publisher' => 'Neckar-Bote (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1909-01-05',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/7TZRIIQEGHOKYGEQOERHDQWE2EHBBEV3?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche de Chaumont, où le train heurté est un train de marchandises : trois voyageurs et deux agents du chemin de fer tués.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le soir du 1er janvier 1909, sur la ligne de Paris à Mulhouse, à Chamarandes-Choignes, peu avant Chaumont, le rapide de Belfort à Paris a rattrapé et heurté un omnibus en retard, qui devait être garé à Chaumont pour lui laisser le passage. Les trois fourgons de queue de l\'omnibus ont été pulvérisés.',
                'en' => 'On the evening of 1 January 1909, on the Paris to Mulhouse line at Chamarandes-Choignes, just before Chaumont, the Belfort to Paris fast train caught up with and struck a late stopping train that was due to be put aside at Chaumont to let it pass. The stopping train\'s three rear vans were smashed.',
                'de' => 'Am Abend des 1. Januar 1909 holte an der Strecke Paris-Mülhausen in Chamarandes-Choignes kurz vor Chaumont der Schnellzug Belfort-Paris einen verspäteten Personenzug ein, der in Chaumont zum Überholen abgestellt werden sollte, und fuhr auf. Die drei Packwagen am Schluss des Personenzugs wurden zertrümmert.',
                'sources' => ['wp-liste-1909', 'ddb-1909-01-03-reichsbote'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les bilans divergent. La liste de Wikipédia compte deux garde-freins tués et trois voyageurs blessés. La dépêche de Chaumont du 2 janvier, reprise par la presse allemande, annonce deux agents et trois voyageurs tués. Le Neckar-Bote fait du train heurté un train de marchandises.',
                'en' => 'The tolls differ. The Wikipedia list counts two brakesmen killed and three passengers injured. The Chaumont dispatch of 2 January, carried by the German press, reports two railwaymen and three passengers killed. The Neckar-Bote makes the train that was struck a goods train.',
                'de' => 'Die Bilanzen weichen ab. Die Wikipedia-Liste zählt zwei getötete Bremser und drei verletzte Reisende. Die von der deutschen Presse übernommene Meldung aus Chaumont vom 2. Januar spricht von zwei getöteten Eisenbahnern und drei getöteten Reisenden. Im Neckar-Boten ist der getroffene Zug ein Güterzug.',
                'sources' => ['wp-liste-1909', 'ddb-1909-01-03-reichsbote', 'ddb-1909-01-05-neckarbote'],
            ],
        ],
    ],
];
