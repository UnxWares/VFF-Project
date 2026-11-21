<?php

// Contexte sourcé du déraillement du rapide Paris - Hendaye près de Dax du 30 mars 1937 (recherche du 21/11/2026).
return [
    'slug' => '1937-03-30-dax',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-dax-a' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinontsporing bij Bordeaux. Drie dooden, zestig gewonden',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1937-03-31',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010854761:mpeg21:a0032',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-dax-b' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Sneltrein Parijs-Henday ontspoord. Drie wagens uit de rails gevlogen',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1937-03-31',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010673506:mpeg21:a0005',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'presse-dax-c' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'De ravage, welke veroorzaakt werd door de ontsporing bij Dax van den trein Parijs-Hendaye',
            'publisher' => 'Presse néerlandaise (Delpher)',
            'author' => null,
            'published' => '1937-04-01',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=MMKB04:000193085:mpeg21:a0006',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1937' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1937',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Mars',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1937&oldid=235357736#Mars',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : rapide Paris-Hendaye de 11 voitures déraillé à 120 km/h au lieu-dit La Cabane, un mort et 45 blessés ; cite Le Petit Parisien du 31 mars 1937.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mardi 30 mars 1937, le rapide Paris - Hendaye, parti de Bordeaux à 16 h 15, a déraillé vers 18 h 40 au passage à niveau de Cabannes, à environ trois kilomètres au nord de la gare de Dax, alors qu\'il roulait à environ 120 km/h. Le train, composé de onze voitures selon la liste de Wikipédia, était bien rempli de voyageurs.',
                'en' => 'On Tuesday 30 March 1937, the Paris to Hendaye express, which left Bordeaux at 4.15 pm, derailed at about 6.40 pm at the Cabannes level crossing, some three kilometres north of Dax station, while travelling at about 120 km/h. The train, made up of eleven carriages according to the Wikipedia list, was well filled with passengers.',
                'de' => 'Am Dienstag, dem 30. März 1937, entgleiste der um 16.15 Uhr in Bordeaux abgefahrene Schnellzug Paris - Hendaye gegen 18.40 Uhr am Bahnübergang von Cabannes, etwa drei Kilometer nördlich des Bahnhofs Dax, bei einer Geschwindigkeit von etwa 120 km/h. Der Zug, nach der Wikipedia-Liste aus elf Wagen bestehend, war dicht besetzt.',
                'sources' => ['presse-dax-a', 'presse-dax-b', 'wp-liste-1937'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les trois ou quatre dernières voitures sont sorties des rails : une est tombée sur le talus, deux autres de plusieurs mètres, dont deux dans le ruisseau ou le ravin qui longe la voie selon les dépêches. Les communications téléphoniques étant coupées, les nouvelles de Dax sont arrivées de façon incomplète. Pompiers, garde mobile, gendarmes, police de Dax et une centaine de sauveteurs ont dégagé les blessés, et le déblaiement s\'est fait aux flambeaux.',
                'en' => 'The last three or four carriages left the rails: one fell on the embankment, two others several metres down, two of them into the stream or ravine running beside the line according to the dispatches. As telephone links were cut, news from Dax arrived only incompletely. Firefighters, the mobile guard, gendarmes, Dax police and about a hundred rescuers freed the injured, and clearing was done by torchlight.',
                'de' => 'Die letzten drei oder vier Wagen entgleisten: einer fiel auf den Bahndamm, zwei weitere mehrere Meter in die Tiefe, zwei davon laut den Depeschen in den neben der Strecke fließenden Bach oder Graben. Da die Telefonverbindungen unterbrochen waren, trafen die Nachrichten aus Dax nur unvollständig ein. Feuerwehr, Mobilgarde, Gendarmen, die Polizei von Dax und etwa hundert Helfer befreiten die Verletzten, und die Räumung erfolgte bei Fackelschein.',
                'sources' => ['presse-dax-a', 'presse-dax-b', 'wp-liste-1937'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La compagnie a indiqué que le bilan exact était d\'un mort, une jeune femme, d\'un blessé grave et de 44 blessés légers. Les premières dépêches parlaient de trois morts et d\'environ soixante blessés, un journal néerlandais du lendemain donnant encore soixante blessés et une personne tuée.',
                'en' => 'The company stated that the exact toll was one dead, a young woman, one seriously injured and 44 slightly injured. The first dispatches spoke of three dead and about sixty injured, a Dutch paper of the following day still giving sixty injured and one person killed.',
                'de' => 'Die Gesellschaft gab an, die genaue Bilanz sei eine Tote, eine junge Frau, ein Schwerverletzter und 44 Leichtverletzte. Die ersten Depeschen sprachen von drei Toten und etwa sechzig Verletzten, eine niederländische Zeitung vom Folgetag nannte noch sechzig Verletzte und einen Toten.',
                'sources' => ['presse-dax-b', 'presse-dax-a', 'presse-dax-c', 'wp-liste-1937'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon le chef de gare de Bordeaux cité par la presse néerlandaise, l\'accident serait dû à la rupture d\'un attelage après la voiture-restaurant, qui était la sixième voiture du train.',
                'en' => 'According to the Bordeaux station master quoted by the Dutch press, the accident was due to the breaking of a coupling after the restaurant car, which was the sixth carriage of the train.',
                'de' => 'Nach dem von der niederländischen Presse zitierten Bahnhofsvorsteher von Bordeaux ist der Unfall auf den Bruch einer Kupplung hinter dem Speisewagen zurückzuführen, der der sechste Wagen des Zuges war.',
                'sources' => ['presse-dax-a', 'presse-dax-b'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie : trois morts et soixante blessés dans les premières dépêches, un mort et soixante blessés dans le journal du 1er avril, un mort et 45 blessés selon la compagnie et la liste de Wikipédia. Le lieu est donné comme le passage à niveau de Cabannes, à trois kilomètres de Dax, par la presse néerlandaise, et comme le lieu-dit « La Cabane », à deux kilomètres de Dax, par la liste de Wikipédia.',
                'en' => 'The toll varies: three dead and sixty injured in the first dispatches, one dead and sixty injured in the paper of 1 April, one dead and 45 injured according to the company and the Wikipedia list. The site is given as the Cabannes level crossing, three kilometres from Dax, by the Dutch press, and as the place called "La Cabane", two kilometres from Dax, by the Wikipedia list.',
                'de' => 'Die Bilanz schwankt: drei Tote und sechzig Verletzte in den ersten Depeschen, ein Toter und sechzig Verletzte in der Zeitung vom 1. April, ein Toter und 45 Verletzte nach der Gesellschaft und der Wikipedia-Liste. Der Ort wird von der niederländischen Presse als Bahnübergang Cabannes, drei Kilometer von Dax, und von der Wikipedia-Liste als Flurstück „La Cabane“, zwei Kilometer von Dax, angegeben.',
                'sources' => ['presse-dax-a', 'presse-dax-b', 'presse-dax-c', 'wp-liste-1937'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
