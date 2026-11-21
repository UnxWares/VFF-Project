<?php

// Contexte sourcé du déraillement par sabotage près de Demigny du 3 décembre 1943 (recherche du 21/11/2026).
return [
    'slug' => '1943-12-03-demigny',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1940' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1940',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => '1943, 3 décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1940&oldid=239153903#1943',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : près de Demigny, point kilométrique 8,7 de la ligne de Chagny à Dole-Ville, machine et quatre voitures d\'un train mixte déraillées par sabotage, 2 morts et de nombreux blessés.',
        ],
        'petit-parisien-1943-12-04' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 4 décembre 1943',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1943-12-04',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k685160w/f1.zoom.r=sabotage.langFR',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit cité par la liste de Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 3 décembre 1943, au matin, près de Demigny (Saône-et-Loire), au point kilométrique 8,7 de la ligne de Chagny à Dole-Ville, la locomotive et quatre voitures de voyageurs d\'un train mixte ont déraillé.',
                'en' => 'On the morning of 3 December 1943, near Demigny (Saône-et-Loire), at kilometre point 8.7 of the Chagny to Dole-Ville line, the locomotive and four passenger carriages of a mixed train derailed.',
                'de' => 'Am Morgen des 3. Dezember 1943 entgleisten bei Demigny (Saône-et-Loire) bei Kilometerpunkt 8,7 der Strecke von Chagny nach Dole-Ville die Lokomotive und vier Reisezugwagen eines gemischten Zuges.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le déraillement a fait deux morts et de nombreux blessés, dont le nombre n\'est pas précisé.',
                'en' => 'The derailment killed two people and injured many, whose number is not specified.',
                'de' => 'Bei der Entgleisung starben zwei Menschen, viele wurden verletzt; ihre Zahl wird nicht genannt.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La liste de Wikipédia attribue le déraillement à un sabotage.',
                'en' => 'The Wikipedia list attributes the derailment to sabotage.',
                'de' => 'Die Wikipedia-Liste führt die Entgleisung auf Sabotage zurück.',
                'sources' => ['wp-liste-1940'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'attack',
            'sources' => ['wp-liste-1940'],
            'fr' => 'La liste de Wikipédia, source de la fiche, attribue le déraillement à un sabotage. La première circonstance est l\'attentat.',
            'en' => 'The Wikipedia list, the source of the entry, attributes the derailment to sabotage. The first circumstance is the attack.',
            'de' => 'Die Wikipedia-Liste, Quelle des Eintrags, führt die Entgleisung auf Sabotage zurück. Erster Umstand ist der Anschlag.',
        ],
    ],
];
