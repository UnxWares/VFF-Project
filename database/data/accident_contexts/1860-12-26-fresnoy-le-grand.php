<?php

// Contexte sourcé de la collision de la gare de Fresnoy-le-Grand de la nuit du 26 au 27 décembre 1860 (recherche du 21/11/2026).
return [
    'slug' => '1860-12-26-fresnoy-le-grand',
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
            'locator' => '1860, 26 décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1860',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de l\'époque (Gallica).',
        ],
        'vdr-fresnoy' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1860, Accident dû à la neige à Fresnoy-le-Grand (02)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => '2026-10-11',
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1860-accident-du-a-la-neige-a-fresnoy-le-grand/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit le Courrier de Saint-Quentin repris par le Journal des débats du 31 décembre 1860, et le Journal de la ville de Saint-Quentin du 30 décembre 1860.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Fin décembre 1860, des chutes de neige exceptionnelles encombraient les voies autour de Saint-Quentin, et il avait fallu employer la troupe pour les déblayer. Le soir du 26 décembre, le train de voyageurs n° 4 venant d\'Erquelinnes, sur la ligne de Belgique de la Compagnie du Nord, était arrêté depuis 45 minutes en gare de Fresnoy-le-Grand le temps de dégager la neige des rails.',
                'en' => 'In late December 1860, exceptional snowfalls were blocking the lines around Saint-Quentin, and troops had been needed to clear them. On the evening of 26 December, passenger train no. 4 from Erquelinnes, on the Nord company\'s Belgian line, had been standing at Fresnoy-le-Grand station for 45 minutes while snow was cleared from the rails.',
                'de' => 'Ende Dezember 1860 blockierten außergewöhnliche Schneefälle die Strecken um Saint-Quentin, und zum Räumen musste Militär eingesetzt werden. Am Abend des 26. Dezember stand der Personenzug Nr. 4 aus Erquelinnes auf der Belgienstrecke der Nordbahn seit 45 Minuten im Bahnhof Fresnoy-le-Grand, während der Schnee von den Schienen geräumt wurde.',
                'sources' => ['vdr-fresnoy', 'wp-liste-1860'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers minuit, le train de marchandises n° 310, dont le conducteur n\'avait pas vu les signaux, gênés par les intempéries, l\'a heurté par l\'arrière et a broyé ses trois dernières voitures de troisième classe.',
                'en' => 'At about midnight, freight train no. 310, whose driver had not seen the signals, hampered by the weather, ran into its rear and crushed its last three third-class coaches.',
                'de' => 'Gegen Mitternacht fuhr der Güterzug Nr. 310, dessen Führer die durch das Wetter beeinträchtigten Signale nicht gesehen hatte, auf sein Ende und zermalmte die letzten drei Wagen dritter Klasse.',
                'sources' => ['vdr-fresnoy', 'wp-liste-1860'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le chef de gare, un garde-barrière et le mécanicien du train tamponneur ont été inculpés ; seul ce dernier a été condamné, à 100 francs d\'amende.',
                'en' => 'The stationmaster, a crossing keeper and the driver of the colliding train were charged; only the driver was convicted, with a 100-franc fine.',
                'de' => 'Der Bahnhofsvorsteher, ein Schrankenwärter und der Lokführer des auffahrenden Zuges wurden angeklagt; nur Letzterer wurde verurteilt, zu 100 Franc Geldstrafe.',
                'sources' => ['wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La fiche retient cinq morts et six blessés d\'après Wikipédia. Le Journal de la ville de Saint-Quentin du 30 décembre 1860 comptait trois morts et plusieurs blessés, et situait le choc vers une heure du matin, dans la nuit du 26 au 27.',
                'en' => 'The record keeps five dead and six injured from Wikipedia. The Journal de la ville de Saint-Quentin of 30 December 1860 counted three dead and several injured, and put the collision at about 1 am, in the night of 26 to 27 December.',
                'de' => 'Der Eintrag nennt nach Wikipedia fünf Tote und sechs Verletzte. Das Journal de la ville de Saint-Quentin vom 30. Dezember 1860 zählte drei Tote und mehrere Verletzte und datierte den Zusammenstoß auf etwa 1 Uhr in der Nacht vom 26. auf den 27. Dezember.',
                'sources' => ['wp-liste-1860', 'vdr-fresnoy'],
            ],
        ],
    ],
    'corrections' => [],
];
