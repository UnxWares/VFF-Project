<?php

// Contexte sourcé de la collision de Saint-Denis (La Briche) du 23 octobre 1922 (recherche du 21/11/2026).
return [
    'slug' => '1922-10-23-saint-denis',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1922' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1922',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1922&oldid=236708402#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 23 octobre, vers 23 h, à 1 800 m de la gare de Saint-Denis (bifurcation du fort de la Briche), express du Tréport contre un train de Persan-Beaumont dont le mécanicien n\'a pas respecté les signaux d\'arrêt, 2 morts dont le cheminot responsable et 18 blessés.',
        ],
        'delpher-1922-10-24' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Ernstig spoorwegongeluk in Frankrijk. Zeven wagons gederailleerd',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1922-10-24',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:011082611:mpeg21:a0009',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article du 24 octobre : collision à Saint-Denis à 23 h 15 entre le train du Tréport et celui de Beaumont, 7 voitures déraillées, 4 corps retirés, 22 blessés dont 12 graves.',
        ],
        'delpher-1922-10-25' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Een spoorwegongeluk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1922-10-25',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB12:000132245:mpeg21:a00034',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève du 25 octobre : deux trains en collision près d\'un fort sur le réseau du Nord, 4 morts et 12 blessés.',
        ],
        'delpher-1922-10-26' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Ernstig spoorwegongeluk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1922-10-26',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010933307:mpeg21:a0005',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Reprise du récit du 24 octobre.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le lundi 23 octobre 1922 vers 23 h 15, à Saint-Denis, au Fort de la Briche, à environ 1 800 m de la gare de Saint-Denis, le train venant du Tréport est entré en collision avec le train venant de Persan-Beaumont.',
                'en' => 'On Monday 23 October 1922 at about 11.15 p.m., at Saint-Denis, at the Fort de la Briche, about 1,800 m from Saint-Denis station, the train from Le Tréport collided with the train from Persan-Beaumont.',
                'de' => 'Am Montag, dem 23. Oktober 1922, gegen 23.15 Uhr stieß in Saint-Denis am Fort de la Briche, etwa 1 800 m vom Bahnhof Saint-Denis entfernt, der aus Le Tréport kommende Zug mit dem Zug aus Persan-Beaumont zusammen.',
                'sources' => ['wp-liste-1922', 'delpher-1922-10-24'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les deux locomotives se sont heurtées avec violence et sept voitures, quatre de voyageurs et trois de marchandises, ont déraillé. Selon la liste de Wikipédia, le mécanicien du train de Persan-Beaumont n\'avait pas respecté les signaux d\'arrêt.',
                'en' => 'The two locomotives collided violently and seven wagons, four passenger and three goods, derailed. According to the Wikipedia list, the driver of the Persan-Beaumont train had not obeyed the stop signals.',
                'de' => 'Die beiden Lokomotiven prallten heftig aufeinander, und sieben Wagen, vier Reise- und drei Güterwagen, entgleisten. Laut Wikipedia-Liste hatte der Lokführer des Zuges aus Persan-Beaumont die Haltsignale nicht beachtet.',
                'sources' => ['delpher-1922-10-24', 'delpher-1922-10-26', 'wp-liste-1922'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La liste de Wikipédia compte deux morts, dont le cheminot responsable, et dix-huit blessés. La presse néerlandaise des 24 et 26 octobre compte quatre corps retirés des débris, dont celui du mécanicien du train de Beaumont, et vingt-deux blessés dont douze graves, en craignant d\'autres corps sous les décombres ; une brève du 25 octobre donne quatre morts et douze blessés.',
                'en' => 'The Wikipedia list counts two dead, including the railwayman responsible, and eighteen injured. The Dutch press of 24 and 26 October counts four bodies recovered from the wreckage, including that of the driver of the Beaumont train, and twenty-two injured, twelve of them seriously, fearing more bodies under the debris; an item of 25 October gives four dead and twelve injured.',
                'de' => 'Die Wikipedia-Liste zählt zwei Tote, darunter den verantwortlichen Eisenbahner, und achtzehn Verletzte. Die niederländische Presse vom 24. und 26. Oktober zählt vier aus den Trümmern geborgene Leichen, darunter die des Lokführers des Zuges aus Beaumont, und zweiundzwanzig Verletzte, davon zwölf schwer, und befürchtet weitere Tote unter den Trümmern; eine Notiz vom 25. Oktober nennt vier Tote und zwölf Verletzte.',
                'sources' => ['wp-liste-1922', 'delpher-1922-10-24', 'delpher-1922-10-25', 'delpher-1922-10-26'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie : 2 morts et 18 blessés (liste de Wikipédia), 4 morts et 12 à 22 blessés (presse néerlandaise). Le lieu est Saint-Denis, et non Le Tréport, ville d\'origine d\'un des trains.',
                'en' => 'The toll varies: 2 dead and 18 injured (Wikipedia list), 4 dead and 12 to 22 injured (Dutch press). The place is Saint-Denis, not Le Tréport, the origin of one of the trains.',
                'de' => 'Die Bilanz schwankt: 2 Tote und 18 Verletzte (Wikipedia-Liste), 4 Tote und 12 bis 22 Verletzte (niederländische Presse). Der Ort ist Saint-Denis, nicht Le Tréport, der Herkunftsort eines der Züge.',
                'sources' => ['wp-liste-1922', 'delpher-1922-10-24', 'delpher-1922-10-25'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '93066',
            'sources' => ['wp-liste-1922', 'delpher-1922-10-24'],
            'fr' => 'La collision a eu lieu à Saint-Denis, au Fort de la Briche, et non au Tréport, d\'où venait l\'un des trains.',
            'en' => 'The collision took place at Saint-Denis, at the Fort de la Briche, not at Le Tréport, where one of the trains came from.',
            'de' => 'Die Kollision ereignete sich in Saint-Denis am Fort de la Briche, nicht in Le Tréport, dem Herkunftsort eines der Züge.',
        ],
    ],
];
