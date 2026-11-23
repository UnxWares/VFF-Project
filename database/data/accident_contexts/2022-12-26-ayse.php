<?php

// Contexte sourcé de de la collision entre un TER et un véhicule utilitaire électrique au passage à niveau n° 13 d'Ayse (Haute-Savoie) du 26 décembre 2022 (recherche du 23/11/2026).
return [
    'slug' => '2022-12-26-ayse',
    'checked_on' => '2026-11-23',
    'sources' => [
        'bea-tt-2022-12-26-ayse' => [
            'provider' => 'bea-tt',
            'kind' => 'official',
            'reliability' => 'official',
            'title' => 'Collision entre un TER et un véhicule électrique sur le PN n° 13 à Ayse (74)',
            'publisher' => 'BEA-TT',
            'author' => null,
            'published' => null,
            'locator' => 'page de présentation de l\'enquête',
            'url' => 'https://www.bea-tt.developpement-durable.gouv.fr/ayse-74-26-12-2022-collision-entre-un-ter-et-un-r335.html',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Présentation de l\'enquête du BEA-TT : localisation, faits, ouverture ou conclusions de l\'enquête.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Un TER a percuté un véhicule utilitaire léger à propulsion électrique au passage à niveau n° 13. Les dégâts sur les installations ferroviaires sont peu importants et le train est légèrement endommagé, mais le véhicule a été entièrement détruit par le choc et par l\'incendie qui a suivi. Il n\'y a pas eu de blessé.',
                'en' => 'A regional train hit an electrically powered light utility vehicle at level crossing no. 13. The damage to railway installations is slight and the train was only lightly damaged, but the vehicle was completely destroyed by the impact and by the fire that followed. Nobody was injured.',
                'de' => 'Ein Regionalzug prallte am Bahnübergang Nr. 13 auf ein elektrisch angetriebenes leichtes Nutzfahrzeug. Die Schäden an den Bahnanlagen sind gering, und der Zug wurde nur leicht beschädigt, doch das Fahrzeug wurde durch den Aufprall und den folgenden Brand völlig zerstört. Es gab keine Verletzten.',
                'sources' => ['bea-tt-2022-12-26-ayse'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le BEA-TT a publié son rapport final le 9 décembre 2024, sur le thème des batteries et de l\'incendie, avec des recommandations adressées à SNCF Voyageurs.',
                'en' => 'The BEA-TT published its final report on 9 December 2024, on the theme of batteries and fire, with recommendations addressed to SNCF Voyageurs.',
                'de' => 'Die BEA-TT veröffentlichte ihren Abschlussbericht am 9. Dezember 2024 zum Thema Batterien und Brand, mit Empfehlungen an SNCF Voyageurs.',
                'sources' => ['bea-tt-2022-12-26-ayse'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'injuries',
            'value' => 0,
            'sources' => ['bea-tt-2022-12-26-ayse'],
            'fr' => 'Le BEA-TT indique qu\'il n\'y a pas de blessé.',
            'en' => 'The BEA-TT states that there were no injuries.',
            'de' => 'Die BEA-TT gibt an, dass es keine Verletzten gab.',
        ],
    ],
];
