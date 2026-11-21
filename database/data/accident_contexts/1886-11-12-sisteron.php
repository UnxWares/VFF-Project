<?php

// Contexte sourcé de l'éboulement de Sisteron du 12 novembre 1886 (recherche du 21/11/2026).
return [
    'slug' => '1886-11-12-sisteron',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '12 novembre 1886, Sisteron',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1886',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Donne l\'heure (11 h 50), six morts et une vingtaine de blessés ; cite Le Temps du 14 novembre 1886.',
        ],
        'vdr-sisteron' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1886, La montagne s\'écroule au passage du train 429 à Sisteron (04)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1886-la-montagne-secroule-a-sisteron-au-passage-du-train-429/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Temps du 14 novembre 1886.',
        ],
        'le-temps-1886-11-14' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 14 novembre 1886',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1886-11-14',
            'locator' => 'p. 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k231214v/f2',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 12 novembre 1886, vers 11 h 50, le train 429 venant de Marseille approchait de Sisteron (Basses-Alpes) quand une partie de la montagne de Mongerves s\'est effondrée sur la voie, à deux kilomètres de la gare de Sisteron, entre cette gare et celle de Peipin. Selon Le Temps, l\'éboulement a couvert la voie d\'environ 300 000 mètres cubes de terre.',
                'en' => 'On 12 November 1886, at about 11.50, train 429 from Marseille was nearing Sisteron (Basses-Alpes) when part of the Mongerves mountain collapsed onto the line, two kilometres from Sisteron station, between that station and Peipin. According to Le Temps, the slide buried the line under about 300,000 cubic metres of earth.',
                'de' => 'Am 12. November 1886 gegen 11.50 Uhr näherte sich der Zug 429 aus Marseille Sisteron (Basses-Alpes), als ein Teil des Berges Mongerves auf die Strecke stürzte, zwei Kilometer vom Bahnhof Sisteron entfernt, zwischen diesem Bahnhof und Peipin. Laut Le Temps bedeckte der Erdrutsch die Strecke mit etwa 300 000 Kubikmetern Erde.',
                'sources' => ['vdr-sisteron', 'wp-liste-1880'],
            ],
            [
                'fr' => 'Le train 446, qui devient 429 à Pertuis, partait ce jour-là avec beaucoup moins de voyageurs que d\'habitude, car la rupture de la ligne de Gardanne supprimait les correspondances de Rognac. La dépêche note que la population était déjà effrayée par les inondations.',
                'en' => 'Train 446, which becomes 429 at Pertuis, left that day with far fewer passengers than usual, because the break in the line at Gardanne had removed the connections from Rognac. The dispatch notes that the population was already frightened by the floods.',
                'de' => 'Der Zug 446, der in Pertuis zum 429 wird, fuhr an diesem Tag mit weit weniger Reisenden als üblich ab, weil der Streckenbruch bei Gardanne die Anschlüsse aus Rognac unterbrochen hatte. Die Depesche vermerkt, dass die Bevölkerung wegen der Überschwemmungen ohnehin schon in Angst war.',
                'sources' => ['vdr-sisteron'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive a été renversée et enfoncée dans les terres, et une voiture de première classe a été brisée. Dans la soirée, vers cinq heures, un nouvel éboulement s\'est produit sans faire de victime. La route nationale et le télégraphe ont été coupés.',
                'en' => 'The locomotive was overturned and driven into the earth, and a first-class carriage was smashed. In the evening, at about five o\'clock, a new slide occurred without causing casualties. The main road and the telegraph line were cut.',
                'de' => 'Die Lokomotive wurde umgeworfen und in die Erde gedrückt, und ein Wagen erster Klasse wurde zertrümmert. Am Abend gegen fünf Uhr ereignete sich ein neuer Erdrutsch, der keine Opfer forderte. Die Nationalstraße und die Telegrafenleitung wurden unterbrochen.',
                'sources' => ['vdr-sisteron'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les premières dépêches comptaient trois morts sur le coup, le mécanicien, un employé du télégraphe de Marseille et une voyageuse de troisième classe, et une vingtaine de blessés dont huit très grièvement. Trois autres blessés sont morts ensuite, ce qui portait le bilan à six morts.',
                'en' => 'The first dispatches counted three dead at once, the driver, a Marseille telegraph employee and a third-class woman passenger, and about twenty injured, eight of them very seriously. Three more of the injured died afterwards, bringing the toll to six dead.',
                'de' => 'Die ersten Depeschen zählten drei sofort Tote, den Lokführer, einen Telegrafenangestellten aus Marseille und eine Reisende dritter Klasse, und etwa zwanzig Verletzte, acht davon sehr schwer. Drei weitere Verletzte starben danach, womit sich die Zahl auf sechs Tote erhöhte.',
                'sources' => ['vdr-sisteron', 'wp-liste-1880'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Les habitants de Sisteron et des villages voisins se sont rendus sur place pour organiser les secours, et le préfet est parti de Digne en train spécial avec un médecin. Un train spécial a ramené à Digne douze blessés, admis à l\'hôpital vers sept heures du soir.',
                'en' => 'The inhabitants of Sisteron and the neighbouring villages went to the site to organise relief, and the prefect left Digne by special train with a doctor. A special train brought twelve injured to Digne, where they were admitted to hospital at about seven in the evening.',
                'de' => 'Die Einwohner von Sisteron und der Nachbardörfer eilten zur Unglücksstelle, um Hilfe zu organisieren, und der Präfekt fuhr mit einem Arzt in einem Sonderzug aus Digne ab. Ein Sonderzug brachte zwölf Verletzte nach Digne, wo sie gegen sieben Uhr abends ins Krankenhaus aufgenommen wurden.',
                'sources' => ['vdr-sisteron'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 6,
            'sources' => ['vdr-sisteron', 'wp-liste-1880'],
            'fr' => 'Trois morts sur le coup et trois blessés décédés ensuite : six morts selon Le Temps du 14 novembre 1886 et la liste de Wikipédia.',
            'en' => 'Three dead at once and three of the injured died afterwards: six dead according to Le Temps of 14 November 1886 and the Wikipedia list.',
            'de' => 'Drei sofort Tote und drei später gestorbene Verletzte: sechs Tote laut Le Temps vom 14. November 1886 und der Wikipedia-Liste.',
        ],
    ],
];
