<?php

// Contexte sourcé de la collision de Rang-du-Fliers du 2 décembre 1913 (recherche du 21/11/2026).
return [
    'slug' => '1913-12-02-rang-du-fliers',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1913' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1913',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1913&oldid=233727923#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Le soir, en gare de Rang-du-Fliers, un train transatlantique Boulogne-Paris prend en écharpe un convoi de betteraves circulant sur la ligne à voie métrique d\'Aires-sur-la-Lys à Berck.',
        ],
        'delpher-amstelbode-1913-12-04' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengde berichten',
            'publisher' => 'De Amstelbode (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1913-12-04',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMSAA06:165610133:mpeg21:a00016',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Express Paris-Boulogne contre un train de marchandises près de la gare de Rang-du-Fliers, deux voitures tombées du remblai, un chauffeur tué, voyageurs (surtout des Américains) indemnes.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le mardi 2 décembre 1913, le soir selon la liste de Wikipédia, en gare de Rang-du-Fliers, un train transatlantique de Boulogne à Paris a pris en écharpe un convoi de betteraves qui circulait sur la ligne à voie métrique d\'Aires-sur-la-Lys à Berck.',
                'en' => 'On Tuesday 2 December 1913, in the evening according to the Wikipedia list, at Rang-du-Fliers station, a transatlantic train from Boulogne to Paris struck a glancing blow to a beet train running on the metre-gauge line from Aires-sur-la-Lys to Berck.',
                'de' => 'Am Dienstag, dem 2. Dezember 1913, nach der Wikipedia-Liste abends, streifte im Bahnhof Rang-du-Fliers ein Transatlantik-Zug von Boulogne nach Paris einen Rübenzug, der auf der Meterspurstrecke von Aires-sur-la-Lys nach Berck verkehrte.',
                'sources' => ['wp-liste-1913', 'delpher-amstelbode-1913-12-04'],
            ],
        ],
        'course' => [
            [
                'fr' => 'De Amstelbode du 4 décembre 1913 écrit que l\'express de Paris à Boulogne, qui roulait à 90 kilomètres à l\'heure, a heurté de côté un train de marchandises près de la gare de Rang-du-Fliers ; deux voitures sont tombées du remblai.',
                'en' => 'De Amstelbode of 4 December 1913 writes that the express between Paris and Boulogne, running at 90 kilometres an hour, struck a goods train from the side near Rang-du-Fliers station; two coaches fell from the embankment.',
                'de' => 'De Amstelbode vom 4. Dezember 1913 schreibt, der Schnellzug zwischen Paris und Boulogne, der mit 90 Kilometern pro Stunde fuhr, sei nahe dem Bahnhof Rang-du-Fliers seitlich auf einen Güterzug geprallt; zwei Wagen stürzten vom Damm.',
                'sources' => ['delpher-amstelbode-1913-12-04'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un chauffeur a été tué sur le coup. Les voyageurs, en grande partie des Américains, sont restés indemnes, selon De Amstelbode.',
                'en' => 'A fireman was killed outright. The passengers, largely Americans, were unhurt, according to De Amstelbode.',
                'de' => 'Ein Heizer wurde auf der Stelle getötet. Die Reisenden, großenteils Amerikaner, blieben nach De Amstelbode unverletzt.',
                'sources' => ['delpher-amstelbode-1913-12-04'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La liste de Wikipédia nomme un train transatlantique de Boulogne à Paris, alors que De Amstelbode parle d\'un express de Paris à Boulogne ; la liste précise que le convoi heurté était un convoi de betteraves de la ligne à voie métrique, le journal néerlandais un train de marchandises. Le journal ne signale que le chauffeur tué ; la fiche indique un blessé.',
                'en' => 'The Wikipedia list names a transatlantic train from Boulogne to Paris, whereas De Amstelbode speaks of an express from Paris to Boulogne; the list says the struck train was a beet train of the metre-gauge line, the Dutch newspaper a goods train. The newspaper reports only the fireman killed; the record gives one injured.',
                'de' => 'Die Wikipedia-Liste nennt einen Transatlantik-Zug von Boulogne nach Paris, während De Amstelbode von einem Schnellzug von Paris nach Boulogne spricht; die Liste sagt, der gerammte Zug sei ein Rübenzug der Meterspurstrecke gewesen, die niederländische Zeitung nennt einen Güterzug. Die Zeitung meldet nur den getöteten Heizer; der Datensatz nennt einen Verletzten.',
                'sources' => ['wp-liste-1913', 'delpher-amstelbode-1913-12-04'],
            ],
        ],
    ],
    'corrections' => [],
];
