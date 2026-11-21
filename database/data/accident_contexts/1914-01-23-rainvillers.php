<?php

// Contexte sourcé de la collision de wagons à la dérive en gare de Rainvillers, ligne de Beauvais à Gisors, de janvier 1914 (recherche du 21/11/2026).
return [
    'slug' => '1914-01-23-rainvillers',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1914' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1914',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1914&oldid=239623136#Janvier',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 23 janvier 1914, d\'après Le Temps du 25 janvier : sur la ligne de Beauvais à Gisors, après une rupture d\'attelage dans la rampe vers Labosse, onze wagons de charbon d\'un train de marchandises allant à Beauvais dévalent la pente sur neuf kilomètres et percutent en gare de Rainvillers un train de voyageurs de même sens ; le conducteur du train et un voyageur sont tués, le mécanicien, le chauffeur et six voyageurs grièvement blessés.',
        ],
        'ddb-1914-01-24-reichsanzeiger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 23. Januar (W. T. B.)',
            'publisher' => 'Deutscher Reichsanzeiger und Preußischer Staatsanzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1914-01-24',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/3ILCZDQGDSDGXF2XC2DYOYNVPVNXJKIJ?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche W. T. B. de Paris du 23 janvier : ce matin, sur la ligne Beauvais - Gisors, près de la station de Rainvillers, un train de voyageurs est entré en collision avec un train de charbon ; un agent du train et un voyageur tués, trois autres personnes grièvement blessées.',
        ],
        'ddb-1914-01-25-volksblatt' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Schwere Eisenbahnunfälle in Frankreich',
            'publisher' => 'Volksblatt, Halle (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1914-01-25',
            'locator' => 'p. 11',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/XBEYKFMZJQGNSVGKAPP5NEBGICZN6ZCW?issuepage=11',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Nouvelle de Beauvais : à 9 h du matin, à la halte de Rainvillers, trois wagons de charbon ont heurté un train de voyageurs en pleine marche ; plusieurs voitures détruites, le chef de train et un voyageur tués, plusieurs voyageurs blessés ; dégagement de la ligne prévu pour au moins cinq jours.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 23 janvier 1914, vers 9 h, un train de marchandises chargé de charbon montait vers Labosse sur la ligne de Beauvais à Gisors. Selon la liste de Wikipédia, un attelage s\'est rompu dans la rampe et onze wagons ont dévalé la pente sur neuf kilomètres en direction de Beauvais.',
                'en' => 'On 23 January 1914, at about 9 am, a goods train loaded with coal was climbing towards Labosse on the Beauvais to Gisors line. According to the Wikipedia list a coupling broke on the incline and eleven wagons ran back nine kilometres downhill towards Beauvais.',
                'de' => 'Am 23. Januar 1914 gegen 9 Uhr fuhr ein mit Kohle beladener Güterzug auf der Strecke Beauvais-Gisors bergauf in Richtung Labosse. Nach der Wikipedia-Liste riss auf der Steigung eine Kupplung, und elf Wagen rollten neun Kilometer bergab in Richtung Beauvais.',
                'sources' => ['wp-liste-1914', 'ddb-1914-01-25-volksblatt'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les wagons de charbon ont percuté à Rainvillers un train de voyageurs qui roulait dans le même sens. Plusieurs voitures ont été détruites, et la ligne devait rester coupée au moins cinq jours.',
                'en' => 'The coal wagons ran into a passenger train travelling in the same direction at Rainvillers. Several coaches were destroyed, and the line was expected to stay closed for at least five days.',
                'de' => 'Die Kohlenwagen prallten in Rainvillers auf einen in derselben Richtung fahrenden Personenzug. Mehrere Wagen wurden zertrümmert, die Räumung der Strecke sollte mindestens fünf Tage dauern.',
                'sources' => ['wp-liste-1914', 'ddb-1914-01-24-reichsanzeiger', 'ddb-1914-01-25-volksblatt'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent du train de voyageurs et un voyageur ont été tués. La dépêche de l\'agence Wolff compte trois blessés graves ; la liste de Wikipédia cite le mécanicien, le chauffeur et six voyageurs grièvement blessés.',
                'en' => 'A member of the passenger train crew and one passenger were killed. The Wolff agency dispatch counts three seriously injured; the Wikipedia list names the driver, the stoker and six passengers as seriously injured.',
                'de' => 'Ein Bediensteter des Personenzugs und ein Reisender wurden getötet. Die Depesche des Wolffschen Bureaus zählt drei Schwerverletzte; die Wikipedia-Liste nennt den Lokführer, den Heizer und sechs Reisende als schwer verletzt.',
                'sources' => ['ddb-1914-01-24-reichsanzeiger', 'ddb-1914-01-25-volksblatt', 'wp-liste-1914'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le nombre de blessés diffère : trois blessés graves dans la dépêche de l\'agence Wolff, « plusieurs » dans la nouvelle de Beauvais, huit dans la liste de Wikipédia, six dans la fiche. Les wagons sont onze selon la liste, trois selon la nouvelle de Beauvais ; la dépêche parle d\'un train de charbon.',
                'en' => 'The number of injured differs: three seriously injured in the Wolff agency dispatch, "several" in the Beauvais report, eight in the Wikipedia list, six in the record. There were eleven wagons according to the list and three according to the Beauvais report; the dispatch speaks of a coal train.',
                'de' => 'Die Zahl der Verletzten weicht ab: drei Schwerverletzte in der Depesche des Wolffschen Bureaus, „mehrere“ in der Meldung aus Beauvais, acht in der Wikipedia-Liste, sechs im Eintrag. Nach der Liste waren es elf Wagen, nach der Meldung aus Beauvais drei; die Depesche spricht von einem Kohlenzug.',
                'sources' => ['ddb-1914-01-24-reichsanzeiger', 'ddb-1914-01-25-volksblatt', 'wp-liste-1914'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1914', 'ddb-1914-01-24-reichsanzeiger'],
            'fr' => 'Wagons de charbon contre un train de voyageurs selon la liste de Wikipédia et la dépêche de l\'agence Wolff du 23 janvier 1914 ; la fiche parlait d\'un ouvrage qui cède.',
            'en' => 'Coal wagons against a passenger train according to the Wikipedia list and the Wolff agency dispatch of 23 January 1914; the record described a structure failure.',
            'de' => 'Kohlenwagen gegen einen Personenzug nach der Wikipedia-Liste und der Depesche des Wolffschen Bureaus vom 23. Januar 1914; der Eintrag sprach vom Versagen eines Bauwerks.',
        ],
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['ddb-1914-01-24-reichsanzeiger', 'ddb-1914-01-25-volksblatt', 'wp-liste-1914'],
            'fr' => 'Un agent du train et un voyageur tués selon les deux dépêches et la liste de Wikipédia ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'A train crew member and a passenger killed according to both dispatches and the Wikipedia list; the record gave no number of deaths.',
            'de' => 'Ein Zugbediensteter und ein Reisender getötet nach beiden Depeschen und der Wikipedia-Liste; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
