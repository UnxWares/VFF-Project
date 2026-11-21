<?php

// Contexte sourcé de la collision du 1er février 1921 en gare des Bardys, au nord de Limoges (recherche du 21/11/2026).
return [
    'slug' => '1921-02-01-limoges',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1921' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1921',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Février, 1er février 1921',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1921&oldid=233501430#F%C3%A9vrier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de l\'époque (Gallica).',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Début février 1921, à la suite d\'un déraillement, une voie unique temporaire avait été établie sur la ligne de Paris à Limoges entre les gares de La Jonchère et des Bardys, à une vingtaine de kilomètres au nord de Limoges.',
                'en' => 'In early February 1921, after a derailment, a temporary single track had been set up on the Paris to Limoges line between La Jonchère and Les Bardys stations, about twenty kilometres north of Limoges.',
                'de' => 'Anfang Februar 1921 war nach einer Entgleisung auf der Strecke Paris-Limoges zwischen den Bahnhöfen La Jonchère und Les Bardys, etwa zwanzig Kilometer nördlich von Limoges, ein vorläufiger eingleisiger Betrieb eingerichtet worden.',
                'sources' => ['wp-liste-1921'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le 1er février vers 19 h, l\'omnibus Châteauroux - Limoges, qui l\'empruntait trop vite, a percuté un train de marchandises venant de Limoges qui manœuvrait en gare des Bardys pour lui laisser le passage. Cinq voitures du train de voyageurs se sont télescopées et disloquées. On a compté neuf morts et trente blessés.',
                'en' => 'On 1 February at about 7 pm, the Châteauroux to Limoges stopping train, taking it too fast, ran into a freight train from Limoges that was shunting at Les Bardys station to let it through. Five passenger coaches telescoped and broke up. Nine people were killed and thirty injured.',
                'de' => 'Am 1. Februar gegen 19 Uhr fuhr der Personenzug Châteauroux-Limoges, der den Abschnitt zu schnell befuhr, auf einen Güterzug aus Limoges, der im Bahnhof Les Bardys rangierte, um ihn passieren zu lassen. Fünf Wagen des Personenzugs schoben sich ineinander und zerbrachen. Es gab neun Tote und dreißig Verletzte.',
                'sources' => ['wp-liste-1921'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche est placée à Limoges, terminus du train ; la collision a eu lieu en gare des Bardys, à une vingtaine de kilomètres au nord, dont la commune n\'est pas donnée par la source lue.',
                'en' => 'The record is placed at Limoges, the train\'s destination; the collision happened at Les Bardys station, about twenty kilometres north, whose commune is not given by the source read.',
                'de' => 'Der Eintrag ist in Limoges verortet, dem Ziel des Zuges; der Zusammenstoß ereignete sich im Bahnhof Les Bardys etwa zwanzig Kilometer nördlich, dessen Gemeinde die gelesene Quelle nicht nennt.',
                'sources' => ['wp-liste-1921'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1921'],
            'fr' => 'L\'omnibus a heurté un train de marchandises en manœuvre : c\'est une collision.',
            'en' => 'The stopping train ran into a shunting freight train: this is a collision.',
            'de' => 'Der Personenzug fuhr auf einen rangierenden Güterzug: Es handelt sich um einen Zusammenstoß.',
        ],
    ],
];
