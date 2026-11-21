<?php

// Contexte sourcé du double accident en gare de Saint-Pierre-le-Moûtier du 16 février 1935 (recherche du 21/11/2026).
return [
    'slug' => '1935-02-16-saint-pierre-le-moutier',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-spm-a' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Driedubbele treinbotsing',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1935-02-18',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB23:001976041:mpeg21:a00035',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-spm-b' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Dubbel spoorweg-ongeluk',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1935-02-18',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB23:001351097:mpeg21:a00076',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1935' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1935',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Février',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1935&oldid=236841665#Février',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : double accident en gare de Saint-Pierre-le-Moûtier vers minuit 45, un mort et six blessés dont quatre postiers ambulants ; cite Le Matin du 17 février 1935.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du 15 au 16 février 1935, vers minuit 45 selon la liste de Wikipédia, en gare de Saint-Pierre-le-Moûtier, sur la ligne de Paris à Clermont-Ferrand, un train de marchandises garé pour laisser passer deux trains de messageries s\'est engagé prématurément sur la voie principale après le passage du premier. Il a été pris en écharpe par le second.',
                'en' => 'On the night of 15 to 16 February 1935, at about 12.45 am according to the Wikipedia list, at Saint-Pierre-le-Moûtier station on the Paris to Clermont-Ferrand line, a goods train parked to let two parcel trains pass moved onto the main line too early after the first had gone by. It was hit side-on by the second.',
                'de' => 'In der Nacht vom 15. auf den 16. Februar 1935, nach der Wikipedia-Liste gegen 0.45 Uhr, fuhr im Bahnhof Saint-Pierre-le-Moûtier an der Strecke Paris-Clermont-Ferrand ein Güterzug, der abgestellt war, um zwei Eilgüterzüge vorbeizulassen, nach der Durchfahrt des ersten zu früh auf das Hauptgleis. Er wurde vom zweiten seitlich erfasst.',
                'sources' => ['wp-liste-1935'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive du second train a été projetée sur la voie montante vers Paris, où elle a été percutée par l\'express Clermont-Ferrand - Paris, dont les fourgons postaux ont été détruits. La presse néerlandaise parle d\'un double accident : l\'express Paris - Clermont-Ferrand a heurté la locomotive d\'un train qui avait lui-même heurté un train de marchandises.',
                'en' => 'The second train\'s locomotive was thrown onto the up line towards Paris, where it was hit by the Clermont-Ferrand to Paris express, whose mail vans were destroyed. The Dutch press speaks of a double accident: the Paris to Clermont-Ferrand express ran into the locomotive of a train that had itself hit a goods train.',
                'de' => 'Die Lokomotive des zweiten Zuges wurde auf das Gleis Richtung Paris geschleudert, wo sie vom Schnellzug Clermont-Ferrand - Paris gerammt wurde, dessen Postwagen zerstört wurden. Die niederländische Presse spricht von einem Doppelunfall: Der Schnellzug Paris - Clermont-Ferrand fuhr auf die Lokomotive eines Zuges auf, der seinerseits mit einem Güterzug zusammengestoßen war.',
                'sources' => ['wp-liste-1935', 'presse-spm-a', 'presse-spm-b'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un homme a été tué, selon la presse néerlandaise le mécanicien ou le chauffeur d\'un des trains, et cinq ou six personnes ont été blessées, dont quatre postiers ambulants selon la liste de Wikipédia.',
                'en' => 'One man was killed, according to the Dutch press the driver or fireman of one of the trains, and five or six people were injured, four of them travelling postal workers according to the Wikipedia list.',
                'de' => 'Ein Mann wurde getötet, nach der niederländischen Presse der Lokführer oder Heizer eines der Züge, und fünf oder sechs Personen wurden verletzt, davon nach der Wikipedia-Liste vier Bahnpostbeamte.',
                'sources' => ['presse-spm-a', 'presse-spm-b', 'wp-liste-1935'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
