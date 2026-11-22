<?php

// Contexte sourcé de la collision de Landrecies du 13 juin 1925 (recherche du 21/11/2026).
return [
    'slug' => '1925-06-13-landrecies',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1925' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1925',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'année 1925',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1925&oldid=235357727#1925',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 13 juin à 1 h 30, près de Landrecies, express Paris-Bruxelles tamponné par un train de marchandises qui écrase le fourgon postal, 1 mort et 9 blessés, tous ambulants postaux.',
        ],
        'delpher-1925-06-15' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Het ongeluk met den express Parijs-Berlijn',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1925-06-15',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010657608:mpeg21:a0159',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit : express Paris-Berlin tamponné par un train de marchandises près de Landrecies dans la nuit de vendredi à samedi, 1 mort et 8 blessés dont 3 graves parmi les agents du fourgon postal.',
        ],
        'delpher-1925-06-17' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Bibliothèque royale des Pays-Bas)',
            'author' => null,
            'published' => '1925-06-17',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:011168561:mpeg21:a0091',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même récit.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du vendredi 12 au samedi 13 juin 1925, vers 1 h 30, près de Landrecies (Nord), l\'express Paris-Berlin (Paris-Bruxelles selon la liste de Wikipédia) était arrêté sur la ligne de Creil à Jeumont quand un train de marchandises l\'a tamponné par l\'arrière.',
                'en' => 'In the night from Friday 12 to Saturday 13 June 1925, at about 1.30 a.m., near Landrecies (Nord), the Paris-Berlin express (Paris-Brussels according to the Wikipedia list) was standing on the Creil-Jeumont line when a goods train struck it from behind.',
                'de' => 'In der Nacht von Freitag, dem 12., auf Samstag, den 13. Juni 1925, gegen 1.30 Uhr, stand bei Landrecies (Nord) der Schnellzug Paris-Berlin (laut Wikipedia-Liste Paris-Brüssel) auf der Strecke Creil-Jeumont, als ein Güterzug von hinten auf ihn auffuhr.',
                'sources' => ['delpher-1925-06-15', 'delpher-1925-06-17', 'wp-liste-1925'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive du train de marchandises a broyé le fourgon à bagages et la voiture postale qui formaient la queue de l\'express. La liste de Wikipédia dit que l\'express roulait au ralenti devant un signal d\'avertissement.',
                'en' => 'The locomotive of the goods train smashed the baggage van and the mail coach that formed the rear of the express. The Wikipedia list says the express was running slowly at a warning signal.',
                'de' => 'Die Lokomotive des Güterzugs zertrümmerte den Gepäckwagen und den Postwagen am Ende des Schnellzugs. Die Wikipedia-Liste sagt, der Schnellzug sei langsam an einem Vorsignal gefahren.',
                'sources' => ['delpher-1925-06-15', 'wp-liste-1925'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent du wagon postal a été tué et huit ont été blessés, dont trois grièvement ; la liste de Wikipédia compte un mort et neuf blessés, tous ambulants postaux.',
                'en' => 'One employee of the mail van was killed and eight were injured, three of them seriously; the Wikipedia list counts one dead and nine injured, all railway post office staff.',
                'de' => 'Ein Bediensteter des Postwagens wurde getötet und acht wurden verletzt, davon drei schwer; die Wikipedia-Liste zählt einen Toten und neun Verletzte, alle Bahnpostbedienstete.',
                'sources' => ['delpher-1925-06-15', 'delpher-1925-06-17', 'wp-liste-1925'],
            ],
        ],
    ],
    'corrections' => [],
];
