<?php

// Contexte sourcé de la collision entre Saint-Pierre-le-Viger et Luneray, ligne de Dieppe à Fécamp, de novembre 1902 (recherche du 21/11/2026).
return [
    'slug' => '1902-11-23-luneray',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1902' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1902',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1902&oldid=233590581#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 23 novembre 1902, d\'après Le Petit Parisien du 24 novembre : vers 8 h, sur la ligne à voie unique de Fécamp à Dieppe, dans un épais brouillard, entre Saint-Pierre-le-Viger et Luneray, un train de voyageurs pour Dieppe heurte de front un train de betteraves allant vers Fécamp ; les deux mécaniciens, un chauffeur et un conducteur-chef sont tués, l\'autre chauffeur, un autre conducteur-chef, un garde-frein et une douzaine de voyageurs blessés.',
        ],
        'ddb-1902-11-24-riesa' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gestern, Sonntag, früh 8 Uhr',
            'publisher' => 'Riesaer Tageblatt und Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-11-24',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/EEZ6THZMLX6PSPKUNC5CQ6MGSPHENRBT?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : dimanche, à 8 h du matin, sur la ligne Dieppe - Saint-Valery, près de Luneray, collision entre un train de voyageurs et un train de marchandises ; les deux mécaniciens et un chauffeur tués, trois autres agents et deux voyageurs blessés.',
        ],
        'ddb-1902-11-26-wittgenstein' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Paris, 24. Nov.',
            'publisher' => 'Wittgensteiner Kreisblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1902-11-26',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/J73KCEXWM6ZVQ56BBFFAWNS4QE4JP7GY?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 24 novembre : hier matin, sur la ligne Dieppe - Saint-Valery, près de Luneray, un train de voyageurs a heurté un train de marchandises ; les deux mécaniciens et un chauffeur tués, trois autres agents et deux voyageurs blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 23 novembre 1902, vers 8 h, un épais brouillard couvrait la ligne à voie unique de Fécamp à Dieppe. Un train de voyageurs allait vers Dieppe, un train de marchandises chargé de betteraves vers Fécamp.',
                'en' => 'On Sunday 23 November 1902, at about 8 am, thick fog lay over the single-track Fécamp to Dieppe line. A passenger train was heading for Dieppe and a goods train loaded with beet for Fécamp.',
                'de' => 'Am Sonntag, dem 23. November 1902, gegen 8 Uhr lag dichter Nebel über der eingleisigen Strecke Fécamp-Dieppe. Ein Personenzug fuhr nach Dieppe, ein mit Rüben beladener Güterzug nach Fécamp.',
                'sources' => ['wp-liste-1902', 'ddb-1902-11-24-riesa'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les deux trains se sont heurtés de front entre Saint-Pierre-le-Viger et Luneray.',
                'en' => 'The two trains met head-on between Saint-Pierre-le-Viger and Luneray.',
                'de' => 'Die beiden Züge stießen zwischen Saint-Pierre-le-Viger und Luneray frontal zusammen.',
                'sources' => ['wp-liste-1902', 'ddb-1902-11-24-riesa', 'ddb-1902-11-26-wittgenstein'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les deux mécaniciens et un chauffeur ont été tués ; la liste de Wikipédia ajoute un conducteur-chef. Les dépêches allemandes comptent trois autres agents et deux voyageurs blessés, la liste l\'autre chauffeur, un conducteur-chef, un garde-frein et une douzaine de voyageurs.',
                'en' => 'Both drivers and one stoker were killed; the Wikipedia list adds a head guard. The German dispatches count three other railwaymen and two passengers injured, the list the other stoker, a head guard, a brakeman and about a dozen passengers.',
                'de' => 'Beide Lokführer und ein Heizer wurden getötet; die Wikipedia-Liste nennt dazu einen Oberschaffner. Die deutschen Depeschen zählen drei weitere Bedienstete und zwei Reisende als verletzt, die Liste den anderen Heizer, einen Oberschaffner, einen Bremser und etwa ein Dutzend Reisende.',
                'sources' => ['ddb-1902-11-24-riesa', 'ddb-1902-11-26-wittgenstein', 'wp-liste-1902'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : trois morts et cinq blessés dans les dépêches allemandes, quatre morts et une quinzaine de blessés dans la liste de Wikipédia.',
                'en' => 'The toll differs: three dead and five injured in the German dispatches, four dead and about fifteen injured in the Wikipedia list.',
                'de' => 'Die Bilanz weicht ab: drei Tote und fünf Verletzte in den deutschen Depeschen, vier Tote und etwa fünfzehn Verletzte in der Wikipedia-Liste.',
                'sources' => ['ddb-1902-11-24-riesa', 'wp-liste-1902'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 4,
            'sources' => ['wp-liste-1902', 'ddb-1902-11-24-riesa'],
            'fr' => 'Quatre agents tués selon la liste de Wikipédia (Le Petit Parisien du 24 novembre 1902), dont les deux mécaniciens et un chauffeur cités aussi par les dépêches allemandes ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Four railwaymen killed according to the Wikipedia list (Le Petit Parisien of 24 November 1902), including the two drivers and one stoker also named by the German dispatches; the record gave no number of deaths.',
            'de' => 'Vier Bedienstete getötet nach der Wikipedia-Liste (Le Petit Parisien vom 24. November 1902), darunter die beiden Lokführer und ein Heizer, die auch die deutschen Depeschen nennen; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
