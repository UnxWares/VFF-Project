<?php

// Contexte sourcé du tamponnement d'un omnibus par un express près de Saint-Vincent-de-Tyrosse du 2 avril 1938 (recherche du 21/11/2026).
return [
    'slug' => '1938-04-02-labenne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-labenne-a' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinbotsing in Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1938-04-03',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB04:000193267:mpeg21:a0026',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-labenne-b' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Laatste nieuws. Treinbotsing in Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1938-04-02',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:011154615:mpeg21:a0070',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1938' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1938',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Avril',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1938&oldid=233504441#Avril',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : omnibus Bayonne-Dax rattrapé près de Saint-Vincent-de-Tyrosse par l\'express Bayonne-Bordeaux, un mort et huit blessés ; cite Le Petit Parisien du 3 avril 1938.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 2 avril 1938, près de la gare de Saint-Vincent-de-Tyrosse, sur la ligne électrifiée Bordeaux - Bayonne, l\'omnibus Bayonne - Dax, qui marchait à vitesse réduite à la suite d\'une rupture de bandage, a été rattrapé par l\'express Bayonne - Bordeaux. La presse néerlandaise parle d\'un express Bordeaux - Hendaye qui a heurté un train local, d\'après les dépêches de l\'agence Havas.',
                'en' => 'On Saturday 2 April 1938, near Saint-Vincent-de-Tyrosse station, on the electrified Bordeaux to Bayonne line, the Bayonne to Dax local train, running at reduced speed after a tyre had broken, was caught up by the Bayonne to Bordeaux express. The Dutch press speaks of a Bordeaux to Hendaye express hitting a local train, according to Havas agency dispatches.',
                'de' => 'Am Samstag, dem 2. April 1938, wurde nahe dem Bahnhof Saint-Vincent-de-Tyrosse auf der elektrifizierten Strecke Bordeaux - Bayonne der Personenzug Bayonne - Dax, der nach einem Radreifenbruch mit verminderter Geschwindigkeit fuhr, vom Schnellzug Bayonne - Bordeaux eingeholt. Die niederländische Presse spricht nach Depeschen der Agentur Havas von einem Schnellzug Bordeaux - Hendaye, der einen Personenzug rammte.',
                'sources' => ['wp-liste-1938', 'presse-labenne-a', 'presse-labenne-b'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le conducteur de l\'express avait reçu de la gare de Labenne des indications erronées de marche prudente.',
                'en' => 'According to the Wikipedia list, the express driver had been given erroneous caution indications by Labenne station.',
                'de' => 'Nach der Wikipedia-Liste hatte der Lokführer des Schnellzugs vom Bahnhof Labenne fehlerhafte Angaben zur vorsichtigen Fahrt erhalten.',
                'sources' => ['wp-liste-1938'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les dépêches néerlandaises comptent huit blessés, dont cinq dans un état grave, puis dix blessés dans une dépêche ultérieure et dans un autre quotidien ; la liste de Wikipédia compte huit blessés et un mort.',
                'en' => 'The Dutch dispatches count eight injured, five of them seriously, then ten injured in a later dispatch and another daily; the Wikipedia list counts eight injured and one dead.',
                'de' => 'Die niederländischen Depeschen zählen acht Verletzte, davon fünf schwer, dann zehn Verletzte in einer späteren Depesche und einer anderen Tageszeitung; die Wikipedia-Liste zählt acht Verletzte und einen Toten.',
                'sources' => ['presse-labenne-a', 'presse-labenne-b', 'wp-liste-1938'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La presse néerlandaise des 2 au 4 avril 1938 ne mentionne aucun mort, alors que la liste de Wikipédia, d\'après Le Petit Parisien du 3 avril, compte un mort.',
                'en' => 'The Dutch press of 2 to 4 April 1938 mentions no death, whereas the Wikipedia list, citing Le Petit Parisien of 3 April, counts one dead.',
                'de' => 'Die niederländische Presse vom 2. bis 4. April 1938 erwähnt keinen Toten, während die Wikipedia-Liste unter Berufung auf Le Petit Parisien vom 3. April einen Toten zählt.',
                'sources' => ['presse-labenne-a', 'wp-liste-1938'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['presse-labenne-a', 'wp-liste-1938'],
            'fr' => 'Les deux sources décrivent un tamponnement entre deux trains ; la rupture de bandage n\'est que l\'origine de la marche à vitesse réduite de l\'omnibus.',
            'en' => 'Both sources describe a collision between two trains; the broken tyre is only the reason the local train was running slowly.',
            'de' => 'Beide Quellen beschreiben einen Zusammenstoß zweier Züge; der Radreifenbruch war nur der Grund für die langsame Fahrt des Personenzugs.',
        ],
    ],
];
