<?php

// Contexte sourcé de la collision d'un autocar et d'un train au passage à niveau non gardé près de Cléry-sur-Somme, le 4 décembre 1938 (recherche du 21/11/2026).
return [
    'slug' => '1938-12-04-clery-sur-somme',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1938' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1938',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '4 décembre 1938, Cléry-sur-Somme',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1938&oldid=233504441#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Place l\'accident vers 20 h près de Cléry-sur-Somme, sur la ligne à voie métrique des Chemins de fer départementaux de la Somme entre Albert et Péronne : trois morts et dix blessés graves ; cite Le Matin du 5 décembre 1938.',
        ],
        'limburger-koerier-1938-12-09' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Limburger koerier du 9 décembre 1938',
            'publisher' => 'Limburger koerier',
            'author' => null,
            'published' => '1938-12-09',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010362957:mpeg21:a0066',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher) ; dépêche d\'agence. Article « Drie dooden op onbewaakten overweg » ; paru aussi dans d\'autres titres néerlandais.',
        ],
        'matin-1938-12-05' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Matin du 5 décembre 1938, page 3',
            'publisher' => 'Le Matin (numérisé sur Gallica)',
            'author' => null,
            'published' => '1938-12-05',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5861680',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Référence citée par la liste de Wikipédia ; à consulter, non lue.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Selon le Limburger koerier, l\'accident s\'est produit à un passage à niveau non gardé entre Péronne et Cléry, sur la ligne de Péronne à Albert, dans le nord de la France. La liste de Wikipédia le situe vers 20 h le 4 décembre 1938 près de Cléry-sur-Somme, sur la ligne à voie métrique des Chemins de fer départementaux de la Somme.',
                'en' => 'According to the Limburger koerier, the accident occurred at an unguarded level crossing between Péronne and Cléry, on the Péronne to Albert line in northern France. The Wikipedia list places it at about 8 pm on 4 December 1938 near Cléry-sur-Somme, on the metre-gauge line of the Somme departmental railways.',
                'de' => 'Laut dem Limburger koerier ereignete sich der Unfall an einem unbeschrankten Bahnübergang zwischen Péronne und Cléry auf der Strecke von Péronne nach Albert in Nordfrankreich. Die Wikipedia-Liste verortet ihn gegen 20 Uhr am 4. Dezember 1938 bei Cléry-sur-Somme auf der meterspurigen Strecke der Departementsbahnen der Somme.',
                'sources' => ['limburger-koerier-1938-12-09', 'wp-liste-1938'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un autocar du service de Saint-Quentin à Amiens, bondé de voyageurs, a été saisi par un train de marchandises et entièrement détruit ; le train a déraillé et la locomotive a basculé dans le talus le long de la voie.',
                'en' => 'A bus on the Saint-Quentin to Amiens service, packed with passengers, was hit by a goods train and completely destroyed; the train derailed and the locomotive toppled down the embankment beside the line.',
                'de' => 'Ein mit Reisenden vollgepackter Autobus der Linie Saint-Quentin-Amiens wurde von einem Güterzug erfasst und völlig zerstört; der Zug entgleiste, und die Lokomotive stürzte die Böschung neben der Strecke hinab.',
                'sources' => ['limburger-koerier-1938-12-09', 'wp-liste-1938'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Trois personnes ont été tuées et dix grièvement blessées, selon le journal néerlandais comme selon la liste de Wikipédia.',
                'en' => 'Three people were killed and ten seriously injured, according to both the Dutch newspaper and the Wikipedia list.',
                'de' => 'Drei Personen wurden getötet und zehn schwer verletzt, sowohl laut der niederländischen Zeitung als auch laut der Wikipedia-Liste.',
                'sources' => ['limburger-koerier-1938-12-09', 'wp-liste-1938'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'L\'entrée est placé à Amiens, destination de l\'autocar ; le journal et la liste situent l\'accident près de Cléry-sur-Somme.',
                'en' => 'The entry is placed at Amiens, the bus\'s destination; the newspaper and the list place the accident near Cléry-sur-Somme.',
                'de' => 'Der Eintrag ist in Amiens verortet, dem Ziel des Busses; die Zeitung und die Liste verorten den Unfall bei Cléry-sur-Somme.',
                'sources' => ['limburger-koerier-1938-12-09', 'wp-liste-1938'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '80199',
            'sources' => ['limburger-koerier-1938-12-09', 'wp-liste-1938'],
            'fr' => 'L\'accident a eu lieu près de Cléry-sur-Somme, entre Péronne et Albert, selon le Limburger koerier et la liste de Wikipédia.',
            'en' => 'The accident took place near Cléry-sur-Somme, between Péronne and Albert, according to the Limburger koerier and the Wikipedia list.',
            'de' => 'Der Unfall ereignete sich bei Cléry-sur-Somme zwischen Péronne und Albert, laut dem Limburger koerier und der Wikipedia-Liste.',
        ],
    ],
];
