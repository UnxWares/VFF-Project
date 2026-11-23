<?php

// Contexte sourcé de l'accident du passage à niveau de la gare de Maury, sur la ligne de Rivesaltes à Quillan,
// le 16 décembre 1932 (recherche du 21/11/2026).
return [
    'slug' => '1932-12-16-maury',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1932' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1932',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Décembre, 16 décembre 1932',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1932&oldid=236417113#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de décembre 1932 (Gallica).',
        ],
        'delpher-noorden-1932-12-17' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'TREINONTSPORING IN FRANKRIJK. Zeven dooden en 15 zwaar gewonden.',
            'publisher' => 'Nieuwsblad van het Noorden (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1932-12-17',
            'locator' => 'p. 1',
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010675517:mpeg21:a0011',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Le train a déraillé à Maury, arrondissement de Perpignan ; sept morts et quinze blessés graves.',
        ],
        'wikidata-maury' => [
            'provider' => 'wikidata',
            'kind' => 'data',
            'reliability' => 'encyclopedia',
            'title' => 'Maury (Q579141)',
            'publisher' => 'Wikidata',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.wikidata.org/wiki/Q579141',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Code INSEE 66107 de la commune de Maury (Pyrénées-Orientales).',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 16 décembre 1932, sur la ligne de Rivesaltes (Pyrénées-Orientales) à Quillan (Aude), un automobiliste a défoncé les barrières du passage à niveau de la gare de Maury juste avant le passage de l\'omnibus Quillan - Perpignan. Le train a déraillé sur les débris.',
                'en' => 'On 16 December 1932, on the line from Rivesaltes (Pyrénées-Orientales) to Quillan (Aude), a motorist smashed through the gates of the level crossing at Maury station just before the Quillan to Perpignan stopping train arrived. The train derailed on the wreckage.',
                'de' => 'Am 16. Dezember 1932 durchbrach auf der Strecke Rivesaltes (Pyrénées-Orientales)-Quillan (Aude) ein Autofahrer die Schranken des Bahnübergangs am Bahnhof Maury, kurz bevor der Personenzug Quillan-Perpignan eintraf. Der Zug entgleiste auf den Trümmern.',
                'sources' => ['wp-liste-1932'],
            ],
            [
                'fr' => 'Les deux locomotives se sont renversées et une voiture de troisième classe a été broyée. Le bilan est de sept morts et vingt blessés.',
                'en' => 'Both locomotives overturned and a third-class coach was crushed. The toll was seven dead and twenty injured.',
                'de' => 'Beide Lokomotiven stürzten um, und ein Wagen dritter Klasse wurde zermalmt. Die Bilanz lautet sieben Tote und zwanzig Verletzte.',
                'sources' => ['wp-liste-1932'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '66107',
            'sources' => ['wp-liste-1932', 'delpher-noorden-1932-12-17', 'wikidata-maury'],
            'fr' => 'Rivesaltes est l\'origine de la ligne ; l\'accident a eu lieu au passage à niveau de la gare de Maury.',
            'en' => 'Rivesaltes is where the line starts; the accident happened at the level crossing at Maury station.',
            'de' => 'Rivesaltes ist der Ausgangspunkt der Strecke; der Unfall ereignete sich am Bahnübergang des Bahnhofs Maury.',
        ],
    ],
];
