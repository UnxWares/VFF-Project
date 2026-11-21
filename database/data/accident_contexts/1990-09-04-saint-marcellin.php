<?php

// Contexte sourcé de la collision frontale à Saint-Marcellin du 4 septembre 1990 (recherche du 21/11/2026).
return [
    'slug' => '1990-09-04-saint-marcellin',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-marcellin' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frans treinongeluk met slachtoffers',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1990-09-05',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010642222:mpeg21:a0165',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1990' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1990',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1990',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1990&oldid=239974394#1990',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : nez à nez entre un train Corail et un Talgo Barcelone-Genève à la sortie de la gare de Saint-Marcellin, 1 mort et environ 70 blessés ; cite L\'Humanité des 4 et 6 septembre 1990.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mardi 4 septembre 1990, à la sortie de la gare de Saint-Marcellin, en Isère, deux trains se sont heurtés sur la voie unique. La liste de Wikipédia identifie le Corail 5640 Saint-Gervais - Nice, en retard, et le Talgo Barcelone - Genève, qui devaient se croiser en gare. Le Corail transportait environ 500 voyageurs et le Talgo près de 800.',
                'en' => 'On Tuesday 4 September 1990, at the exit of Saint-Marcellin station, in the Isère, two trains collided on the single track. The Wikipedia list identifies them as the Corail 5640 Saint-Gervais to Nice, running late, and the Talgo Barcelona to Geneva, which were due to cross at the station. The Corail carried about 500 passengers and the Talgo nearly 800.',
                'de' => 'Am Dienstag, dem 4. September 1990, stießen am Ausgang des Bahnhofs Saint-Marcellin im Département Isère zwei Züge auf der eingleisigen Strecke zusammen. Die Wikipedia-Liste nennt den verspäteten Corail 5640 Saint-Gervais - Nizza und den Talgo Barcelona - Genf, die sich im Bahnhof kreuzen sollten. Der Corail beförderte etwa 500 Reisende, der Talgo fast 800.',
                'sources' => ['presse-marcellin', 'wp-liste-1990'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le conducteur du Corail a compris à tort qu\'il avait l\'autorisation de repartir, sans voir que le sémaphore était fermé, et s\'est engagé sur la voie unique vers la gare de croisement habituelle. Le conducteur du Talgo ne l\'a vu venir que trop tard. Les deux locomotives se sont percutées violemment ; l\'une s\'est « levée » et l\'autre a pris feu, mais toutes les voitures sont restées sur les rails.',
                'en' => 'According to the Wikipedia list, the Corail driver wrongly understood that he had permission to leave, without seeing that the signal was closed, and entered the single track towards the usual crossing station. The Talgo driver saw him coming only too late. The two locomotives collided violently; one "rose up" and the other caught fire, but all the coaches stayed on the rails.',
                'de' => 'Nach der Wikipedia-Liste verstand der Lokführer des Corail irrtümlich, er habe die Erlaubnis zur Abfahrt, ohne zu sehen, dass das Signal geschlossen war, und fuhr auf der eingleisigen Strecke zum üblichen Kreuzungsbahnhof. Der Lokführer des Talgo sah ihn erst zu spät kommen. Die beiden Lokomotiven prallten heftig aufeinander; eine „hob sich“ und die andere fing Feuer, doch alle Wagen blieben auf den Schienen.',
                'sources' => ['wp-liste-1990'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Une personne est morte, un agent d\'un des deux trains (le conducteur du Corail selon la liste de Wikipédia), et environ soixante-dix personnes ont été blessées, légèrement selon la presse néerlandaise.',
                'en' => 'One person died, a crew member of one of the two trains (the Corail driver according to the Wikipedia list), and about seventy people were injured, slightly according to the Dutch press.',
                'de' => 'Eine Person starb, ein Bediensteter eines der beiden Züge (der Lokführer des Corail nach der Wikipedia-Liste), und etwa siebzig Personen wurden verletzt, nach der niederländischen Presse leicht.',
                'sources' => ['presse-marcellin', 'wp-liste-1990'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Selon la liste de Wikipédia, les sémaphores de voie unique ont ensuite été complétés par des crocodiles, puis progressivement par le dispositif d\'arrêt automatique des trains.',
                'en' => 'According to the Wikipedia list, single-track semaphores were afterwards backed up by crocodile contacts, then gradually by the automatic train stop device.',
                'de' => 'Nach der Wikipedia-Liste wurden die Signale der eingleisigen Strecken anschließend durch Krokodil-Kontakte und nach und nach durch die automatische Zugbeeinflussung ergänzt.',
                'sources' => ['wp-liste-1990'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 70,
            'sources' => ['presse-marcellin', 'wp-liste-1990'],
            'fr' => 'La presse néerlandaise du 5 septembre 1990 et la liste de Wikipédia donnent toutes deux environ soixante-dix blessés.',
            'en' => 'The Dutch press of 5 September 1990 and the Wikipedia list both give about seventy injured.',
            'de' => 'Die niederländische Presse vom 5. September 1990 und die Wikipedia-Liste nennen beide etwa siebzig Verletzte.',
        ],
    ],
];
