<?php

// Contexte sourcé du déraillement de Laissey (Doubs) du 10 avril 1930 (recherche du 21/11/2026).
return [
    'slug' => '1930-04-10-laissey',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1930' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1930',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Avril, 10 avril 1930',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1930&oldid=233504283#Avril',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche, d\'après la presse de l\'époque (Gallica).',
        ],
        'kb-1930-04-11-werkliedenverbond' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinramp in Frankrijk',
            'publisher' => 'RK Werkliedenverbond (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1930-04-11',
            'locator' => 'article « Treinramp in Frankrijk », texte OCR',
            'url' => 'http://resolver.kb.nl/resolve?urn=MMKB19:000198015:mpeg21:a00035:ocr',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Article du 11 avril : « hier après-midi », déraillement d\'un train militaire à Laissey, près de Besançon ; huit morts jusque-là, bilan provisoire.',
            'facts' => ['date_relative' => 'previous_day', 'commune_insee' => '25323', 'deaths' => 8, 'deaths_provisional' => true],
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 10 avril 1930 vers 9 h 30, sur la ligne de Besançon à Belfort, un train spécial ramenait chez eux des réservistes après une période d\'instruction au camp du Valdahon. Son passage n\'avait pas été annoncé au responsable d\'un chantier de réfection de la voie à la sortie du tunnel de Laissey.',
                'en' => 'On 10 April 1930 at about 9.30 am, on the Besançon to Belfort line, a special train was taking reservists home after a training period at the Valdahon camp. Its passage had not been announced to the foreman of a track renewal site at the exit of the Laissey tunnel.',
                'de' => 'Am 10. April 1930 gegen 9.30 Uhr brachte auf der Strecke Besançon-Belfort ein Sonderzug Reservisten nach einer Übung im Lager Valdahon nach Hause. Seine Fahrt war dem Leiter einer Gleisbaustelle am Ausgang des Tunnels von Laissey nicht angekündigt worden.',
                'sources' => ['wp-liste-1930'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train est arrivé sur une portion de voie privée de ses rails et de ses traverses. La machine a labouré le ballast et s’est couchée contre la paroi rocheuse, et les deux voitures en bois qui la suivaient s’y sont encastrées. On a compté huit morts et cinquante-sept blessés.',
                'en' => 'The train ran onto a stretch of line stripped of its rails and sleepers. The engine ploughed through the ballast and fell against the rock face, and the two wooden coaches behind it were driven into it. Eight people were killed and fifty-seven injured.',
                'de' => 'Der Zug fuhr auf einen Abschnitt, dessen Schienen und Schwellen entfernt waren. Die Lokomotive pflügte durch den Schotter und kippte gegen die Felswand, und die beiden folgenden Holzwagen schoben sich in sie hinein. Es gab acht Tote und siebenundfünfzig Verletzte.',
                'sources' => ['wp-liste-1930'],
            ],
        ],
    ],
    'corrections' => [],
];
