<?php

// Contexte sourcé du déraillement du train-poste Niort - Paris entre La Crèche et Saint-Maixent, en septembre 1866
// (recherche du 21/11/2026).
return [
    'slug' => '1866-09-06-poitiers',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1866, 6 septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1866',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après Le Petit Journal du 7 septembre 1866.',
        ],
        'vdr-saint-maixent' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1866, Déraillement du train-poste à Saint-Maixent (79)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => '2026-09-13',
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1866-deraillement-du-train-poste-a-saint-maixent-79/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Petit Journal du 7 septembre 1866, qui cite la Compagnie d\'Orléans et le Mémorial des Deux-Sèvres.',
        ],
        'petit-journal-1866-09-07' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 7 septembre 1866',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1866-09-07',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k589396q/f3.item',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le train-poste de Niort à Paris, sur la ligne de Poitiers à Niort de la Compagnie d\'Orléans, était parti de Niort à 20 h 40, d\'après le Mémorial des Deux-Sèvres.',
                'en' => 'The mail train from Niort to Paris, on the Orléans company\'s Poitiers to Niort line, had left Niort at 8.40 pm, according to the Mémorial des Deux-Sèvres.',
                'de' => 'Der Postzug von Niort nach Paris auf der Strecke Poitiers-Niort der Orléansbahn war laut dem Mémorial des Deux-Sèvres um 20.40 Uhr in Niort abgefahren.',
                'sources' => ['vdr-saint-maixent'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Entre La Crèche et Saint-Maixent, au lieu-dit Moulin de Pissay, cinq voitures ont déraillé et sont tombées d\'environ 3 m. La locomotive est restée sur la voie avec le fourgon à bagages, le wagon-poste et une voiture de première classe. Deux des voitures tombées ont été presque entièrement broyées.',
                'en' => 'Between La Crèche and Saint-Maixent, at the place called Moulin de Pissay, five coaches derailed and fell about 3 m. The locomotive stayed on the track with the luggage van, the mail van and a first-class carriage. Two of the fallen coaches were almost completely crushed.',
                'de' => 'Zwischen La Crèche und Saint-Maixent, am Ort Moulin de Pissay, entgleisten fünf Wagen und stürzten etwa 3 m tief. Die Lokomotive blieb mit dem Gepäckwagen, dem Postwagen und einem Wagen erster Klasse auf dem Gleis. Zwei der abgestürzten Wagen wurden fast vollständig zermalmt.',
                'sources' => ['vdr-saint-maixent', 'wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Six personnes ont été tuées. La Compagnie d\'Orléans annonçait d\'abord huit blessés, le Mémorial des Deux-Sèvres quinze ; le site Victimes du rail en recense dix-neuf.',
                'en' => 'Six people were killed. The Orléans company first reported eight injured, the Mémorial des Deux-Sèvres fifteen; the Victimes du rail website lists nineteen.',
                'de' => 'Sechs Menschen kamen ums Leben. Die Orléansbahn meldete zunächst acht Verletzte, das Mémorial des Deux-Sèvres fünfzehn; die Website Victimes du rail führt neunzehn auf.',
                'sources' => ['vdr-saint-maixent', 'wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La date diffère : le Mémorial des Deux-Sèvres donne le mardi 4 septembre 1866, la liste de Wikipédia le 6 septembre. La fiche est placée à Poitiers, nom de la ligne ; le déraillement a eu lieu entre La Crèche et Saint-Maixent (Deux-Sèvres), et aucune source lue ne donne la commune exacte.',
                'en' => 'The date differs: the Mémorial des Deux-Sèvres gives Tuesday 4 September 1866, the Wikipedia list 6 September. The record is placed at Poitiers, the name of the line; the derailment happened between La Crèche and Saint-Maixent (Deux-Sèvres), and no source read gives the exact commune.',
                'de' => 'Das Datum weicht ab: Das Mémorial des Deux-Sèvres nennt Dienstag, den 4. September 1866, die Wikipedia-Liste den 6. September. Der Eintrag ist in Poitiers verortet, dem Namen der Strecke; die Entgleisung ereignete sich zwischen La Crèche und Saint-Maixent (Deux-Sèvres), und keine gelesene Quelle nennt die genaue Gemeinde.',
                'sources' => ['vdr-saint-maixent', 'wp-liste-1860'],
            ],
        ],
    ],
    'corrections' => [],
];
