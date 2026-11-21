<?php

// Contexte sourcé du déraillement près du pont de la Gartempe, en septembre 1870 (recherche du 21/11/2026).
return [
    'slug' => '1870-09-18-la-brionne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '18 septembre 1870, entre La Brionne et Montaigut-le-Blanc',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1870',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Date du 18 septembre 1870, vers 16 h 30 ; deux morts, un blessé grave et quelques blessés légers ; cite La Presse du 21 septembre 1870.',
        ],
        'vdr-gartempe' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1870, Un train plonge dans la Gartempe',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1870-un-train-plonge-dans-la-gartempe/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit La Presse du 21 septembre 1870, qui reprend La Défense nationale de Limoges (récit d\'un voyageur).',
        ],
        'presse-1870-09-21' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 21 septembre 1870',
            'publisher' => 'La Presse (numérisée sur Gallica)',
            'author' => null,
            'published' => '1870-09-21',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k513477c/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le train parti de Guéret à quatre heures, sur la ligne de Guéret à Limoges, venait de dépasser la station de La Brionne lorsque, avant Montaigut, les voitures de voyageurs ont déraillé et ont été précipitées dans le remblai au-dessus du pont de la Gartempe, d\'une profondeur d\'environ 40 mètres. La liste de Wikipédia date l\'accident du 18 septembre 1870, vers 16 h 30.',
                'en' => 'The train that left Guéret at four o\'clock, on the Guéret to Limoges line, had just passed La Brionne station when, before Montaigut, the passenger coaches derailed and were thrown down the embankment above the Gartempe bridge, which is about 40 metres deep. The Wikipedia list dates the accident to 18 September 1870, at about 4.30 pm.',
                'de' => 'Der um vier Uhr in Guéret abgefahrene Zug auf der Strecke Guéret-Limoges hatte gerade den Bahnhof La Brionne passiert, als vor Montaigut die Reisezugwagen entgleisten und die Böschung oberhalb der Gartempe-Brücke, die etwa 40 Meter tief ist, hinabgeschleudert wurden. Die Wikipedia-Liste datiert das Unglück auf den 18. September 1870 gegen 16.30 Uhr.',
                'sources' => ['vdr-gartempe', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon le récit d\'un voyageur publié par La Défense nationale de Limoges, la voiture de troisième classe, brisée, a été arrêtée à mi-talus par quelques acacias, et une voiture de deuxième classe plus bas était moins endommagée. Une voiture de première classe, vide, gisait roues en l\'air. L\'avant du train, des wagons de charbon, était resté 100 à 200 mètres plus loin, sans aucune roue, dont quelques-unes étaient tombées dans la Gartempe.',
                'en' => 'According to a traveller\'s account published by La Défense nationale of Limoges, the third-class coach, smashed, had been stopped halfway down the slope by a few acacias, and a second-class coach below it was less damaged. A first-class coach, empty, lay with its wheels in the air. The front of the train, coal wagons, had stayed 100 to 200 metres further on, with no wheels at all, some of which had fallen into the Gartempe.',
                'de' => 'Nach dem Bericht eines Reisenden in La Défense nationale aus Limoges wurde der zertrümmerte Wagen dritter Klasse auf halber Böschung von einigen Akazien aufgehalten, und ein Wagen zweiter Klasse weiter unten war weniger beschädigt. Ein leerer Wagen erster Klasse lag mit den Rädern nach oben. Die Spitze des Zuges, Kohlenwagen, war 100 bis 200 Meter weiter stehen geblieben, ganz ohne Räder, von denen einige in die Gartempe gefallen waren.',
                'sources' => ['vdr-gartempe'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le voyageur compte deux morts, dont un graisseur mort dans sa chute entre deux wagons, un blessé dont l\'état inspire de sérieuses inquiétudes, et quelques autres plus ou moins contusionnés. Les voyageurs valides ont aidé à dégager les autres ; une femme évanouie a été transportée dans la maison du garde.',
                'en' => 'The traveller counts two dead, including a greaser killed in his fall between two wagons, one injured person whose condition gave serious concern, and a few others more or less bruised. The able passengers helped free the others; a woman who had fainted was carried to the keeper\'s house.',
                'de' => 'Der Reisende zählt zwei Tote, darunter einen Schmierer, der bei seinem Sturz zwischen zwei Wagen starb, einen Verletzten, dessen Zustand ernste Sorgen bereitete, und einige weitere mehr oder weniger Geprellte. Die unverletzten Reisenden halfen bei der Befreiung der anderen; eine ohnmächtige Frau wurde ins Haus des Wärters getragen.',
                'sources' => ['vdr-gartempe', 'wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [],
];
