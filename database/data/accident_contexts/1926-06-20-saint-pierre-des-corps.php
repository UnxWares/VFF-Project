<?php

// Contexte sourcé du déraillement du train Paris - Bordeaux près de Vouvray le 20 juin 1926 (recherche du 21/11/2026).
return [
    'slug' => '1926-06-20-saint-pierre-des-corps',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1926' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1926',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juin, 20 juin 1926',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1926&oldid=233501524#Juin',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de l\'époque (Gallica).',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 20 juin 1926 à 15 h 20, une dizaine de kilomètres avant la gare de Saint-Pierre-des-Corps, un essieu de la locomotive du train Paris - Bordeaux s\'est rompu et le train a déraillé près de Vouvray.',
                'en' => 'On 20 June 1926 at 3.20 pm, about ten kilometres before Saint-Pierre-des-Corps station, an axle broke on the locomotive of the Paris to Bordeaux train and the train derailed near Vouvray.',
                'de' => 'Am 20. Juni 1926 um 15.20 Uhr, etwa zehn Kilometer vor dem Bahnhof Saint-Pierre-des-Corps, brach an der Lokomotive des Zuges Paris-Bordeaux eine Achse, und der Zug entgleiste bei Vouvray.',
                'sources' => ['wp-liste-1926'],
            ],
            [
                'fr' => 'Le fourgon et les deux premières voitures ont été entièrement disloqués. L\'accident a fait sept morts et une trentaine de blessés.',
                'en' => 'The van and the first two coaches were completely wrecked. The accident killed seven people and injured about thirty.',
                'de' => 'Der Packwagen und die ersten beiden Wagen wurden völlig zerstört. Der Unfall forderte sieben Tote und etwa dreißig Verletzte.',
                'sources' => ['wp-liste-1926'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche est placée à Saint-Pierre-des-Corps, gare suivante ; la source situe le déraillement près de Vouvray, à une dizaine de kilomètres, sans donner la commune exacte.',
                'en' => 'The record is placed at Saint-Pierre-des-Corps, the next station; the source places the derailment near Vouvray, about ten kilometres away, without giving the exact commune.',
                'de' => 'Der Eintrag ist in Saint-Pierre-des-Corps verortet, dem nächsten Bahnhof; die Quelle verortet die Entgleisung bei Vouvray, etwa zehn Kilometer entfernt, ohne die genaue Gemeinde zu nennen.',
                'sources' => ['wp-liste-1926'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'derailment',
            'sources' => ['wp-liste-1926'],
            'fr' => 'La rupture d\'un essieu de la locomotive a fait dérailler le train ; aucun ouvrage ne s\'est effondré.',
            'en' => 'A broken locomotive axle derailed the train; no structure collapsed.',
            'de' => 'Ein Achsbruch an der Lokomotive brachte den Zug zum Entgleisen; kein Bauwerk stürzte ein.',
        ],
    ],
];
