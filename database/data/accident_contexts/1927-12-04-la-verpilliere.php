<?php

// Contexte sourcé du déraillement de La Verpillière du 4 décembre 1927 (recherche du 21/11/2026).
return [
    'slug' => '1927-12-04-la-verpilliere',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1927' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1927',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1927&oldid=233501538#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : 4 décembre vers 5 h, entre Saint-Quentin-Fallavier et La Verpillière, rapide Lyon-Grenoble à 90 km/h déraillé par affaissement de terrain, sept voitures sur huit dévalent le talus, 1 mort et 3 blessés.',
        ],
        'delpher-1927-12-07a' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Verzakking van de spoorbaan. Het jongste spoorwegongeluk in Frankrijk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1927-12-07',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB27:017907114:mpeg21:a00004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article du 7 décembre : l\'un des blessés du Lyon-Grenoble est mort, les trois autres sont rentrés chez eux, voitures entièrement métalliques, cause : affaissement de la voie après des pluies continues.',
        ],
        'delpher-1927-12-07b' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Frankrijk. Het jongste spoorwegongeluk',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1927-12-07',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010659071:mpeg21:a0103',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même information.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 4 décembre 1927 vers 5 heures du matin, le rapide Lyon-Grenoble a déraillé entre Saint-Quentin-Fallavier et La Verpillière, à environ 90 km/h selon la liste de Wikipédia ; sept des huit voitures ont dévalé le talus, le train étant presque vide.',
                'en' => 'On Sunday 4 December 1927 at about 5 a.m., the Lyon-Grenoble express derailed between Saint-Quentin-Fallavier and La Verpillière, at about 90 km/h according to the Wikipedia list; seven of the eight coaches rolled down the embankment, the train being almost empty.',
                'de' => 'Am Sonntag, dem 4. Dezember 1927, gegen 5 Uhr morgens, entgleiste der Schnellzug Lyon-Grenoble zwischen Saint-Quentin-Fallavier und La Verpillière, laut Wikipedia-Liste mit etwa 90 km/h; sieben der acht Wagen rollten die Böschung hinab, da der Zug fast leer war.',
                'sources' => ['wp-liste-1927', 'delpher-1927-12-07a'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La cause retenue est l\'affaissement de la voie, dû aux pluies continues.',
                'en' => 'The cause accepted was subsidence of the track, due to continuous rain.',
                'de' => 'Als Ursache gilt das Absacken des Bahnkörpers infolge anhaltender Regenfälle.',
                'sources' => ['delpher-1927-12-07a', 'delpher-1927-12-07b', 'wp-liste-1927'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un des blessés est mort des suites de ses blessures et les trois autres sont rentrés chez eux. La presse néerlandaise note que les voitures, entièrement métalliques, ont limité les conséquences de l\'accident.',
                'en' => 'One of the injured died of his injuries and the other three went home. The Dutch press notes that the all-metal coaches limited the consequences of the accident.',
                'de' => 'Einer der Verletzten starb an seinen Verletzungen, und die anderen drei konnten nach Hause zurückkehren. Die niederländische Presse bemerkt, dass die Ganzmetallwagen die Folgen des Unfalls begrenzten.',
                'sources' => ['delpher-1927-12-07a', 'delpher-1927-12-07b', 'wp-liste-1927'],
            ],
        ],
    ],
    'corrections' => [],
];
