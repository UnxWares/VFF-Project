<?php

// Contexte sourcé du déraillement de Jeugny du 16 décembre 1943 (recherche du 21/11/2026).
return [
    'slug' => '1943-12-16-jeugny',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940s' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section 1943',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1943',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : ligne de Saint-Florentin à Troyes, entre Jeugny et Saint-Phal - Chamoy, 10 h 20, train de marchandises déraillé après un sabotage (rail détirefonné et déséclissé), cinq morts dont le mécanicien et quatre occupants du fourgon, chauffeur grièvement brûlé. Cite les Archives historiques de la SNCF.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 16 décembre 1943, à 10 h 20, entre les gares de Jeugny et de Saint-Phal - Chamoy, sur la ligne de Saint-Florentin à Troyes, un train de marchandises venant de Troyes a déraillé à la suite d\'un sabotage : un rail avait été détirefonné et déséclissé.',
                'en' => 'On 16 December 1943, at 10.20 am, between Jeugny and Saint-Phal - Chamoy stations, on the Saint-Florentin to Troyes line, a goods train coming from Troyes derailed after sabotage: a rail had been unscrewed and unfished.',
                'de' => 'Am 16. Dezember 1943 um 10.20 Uhr entgleiste auf der Strecke Saint-Florentin - Troyes zwischen den Bahnhöfen Jeugny und Saint-Phal - Chamoy ein aus Troyes kommender Güterzug nach einer Sabotage: Eine Schiene war gelöst und die Laschen entfernt worden.',
                'sources' => ['wp-liste-1940s'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive et son tender se sont renversés ; le fourgon qui les suivait a été broyé par les wagons de tête.',
                'en' => 'The locomotive and its tender overturned; the brake van behind them was crushed by the leading wagons.',
                'de' => 'Die Lokomotive und ihr Tender kippten um; der folgende Packwagen wurde von den vorderen Wagen zerquetscht.',
                'sources' => ['wp-liste-1940s'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le bilan est de cinq morts : le mécanicien, qui a succombé à ses brûlures, et les quatre occupants du fourgon, dont un cheminot allemand. Le chauffeur a été grièvement brûlé.',
                'en' => 'The toll is five dead: the driver, who died of his burns, and the four occupants of the brake van, one of them a German railwayman. The fireman was seriously burned.',
                'de' => 'Die Bilanz lautet fünf Tote: der Lokführer, der seinen Verbrennungen erlag, und die vier Insassen des Packwagens, darunter ein deutscher Eisenbahner. Der Heizer wurde schwer verbrannt.',
                'sources' => ['wp-liste-1940s'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'attack',
            'sources' => ['wp-liste-1940s'],
            'fr' => 'La liste de Wikipédia, source de la fiche, attribue le déraillement à un sabotage : un rail avait été détirefonné et déséclissé. La première circonstance est l\'attentat.',
            'en' => 'The Wikipedia list, the source of the entry, attributes the derailment to sabotage: a rail had had its sleeper screws and fishplates removed. The first circumstance is the attack.',
            'de' => 'Die Wikipedia-Liste, Quelle des Eintrags, führt die Entgleisung auf Sabotage zurück: An einer Schiene waren Schwellenschrauben und Laschen entfernt worden. Erster Umstand ist der Anschlag.',
        ],
    ],
];
