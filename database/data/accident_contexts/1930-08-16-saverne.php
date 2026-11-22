<?php

// Contexte sourcé de la collision à l'entrée de la gare de Saverne d'août 1930 (recherche du 21/11/2026).
return [
    'slug' => '1930-08-16-saverne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'presse-saverne' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Treinbotsing op het station Zabern',
            'publisher' => 'Presse néerlandaise numérisée (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1930-08-18',
            'locator' => null,
            'url' => 'https://resolver.kb.nl/resolve?urn=ddd:010013053:mpeg21:a0102',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Presse néerlandaise numérisée par la Koninklijke Bibliotheek (Delpher).',
        ],
        'wp-liste-1930' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1930',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1930&oldid=233504283#Août',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : train de Molsheim heurté à l\'entrée de la gare de Saverne par une machine haut-le-pied en manoeuvre, un mort et plusieurs blessés ; cite Le Figaro du 17 août 1930.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 16 août 1930, vers 7 h, à l\'entrée de la gare de Saverne (Zabern en néerlandais), sur la ligne de Paris à Strasbourg, un train de voyageurs venant de Molsheim a été heurté par une machine haut-le-pied qui effectuait une manoeuvre. La presse néerlandaise parle d\'une locomotive de manoeuvre.',
                'en' => 'On Saturday 16 August 1930, at about 7 am, at the entrance of Saverne station (Zabern in Dutch), on the Paris to Strasbourg line, a passenger train coming from Molsheim was hit by a light engine that was shunting. The Dutch press speaks of a shunting locomotive.',
                'de' => 'Am Samstag, dem 16. August 1930, gegen 7 Uhr, wurde am Einfahrtsbereich des Bahnhofs Saverne (niederländisch Zabern) an der Strecke Paris-Straßburg ein aus Molsheim kommender Reisezug von einer rangierenden Leerlokomotive gerammt. Die niederländische Presse spricht von einer Rangierlokomotive.',
                'sources' => ['wp-liste-1930', 'presse-saverne'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les deux machines, un fourgon postal et deux voitures ont été détruits selon la liste de Wikipédia ; la presse néerlandaise parle de wagons fortement endommagés. La collision a fait six blessés parmi les voyageurs selon la presse néerlandaise, un mort et plusieurs blessés parmi les postiers et les voyageurs selon la liste de Wikipédia.',
                'en' => 'The two engines, a mail van and two carriages were destroyed according to the Wikipedia list; the Dutch press speaks of heavily damaged wagons. The collision injured six passengers according to the Dutch press, and killed one person and injured several postal workers and passengers according to the Wikipedia list.',
                'de' => 'Die beiden Lokomotiven, ein Postwagen und zwei Wagen wurden nach der Wikipedia-Liste zerstört; die niederländische Presse spricht von schwer beschädigten Wagen. Die Kollision verletzte nach der niederländischen Presse sechs Reisende, nach der Wikipedia-Liste kostete sie einen Menschen das Leben und verletzte mehrere Postbedienstete und Reisende.',
                'sources' => ['wp-liste-1930', 'presse-saverne'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les journaux néerlandais du 18 août 1930 ne mentionnent aucun mort et comptent six blessés, alors que la liste de Wikipédia, d\'après Le Figaro du 17 août, compte un mort et plusieurs blessés.',
                'en' => 'The Dutch newspapers of 18 August 1930 mention no death and count six injured, whereas the Wikipedia list, citing Le Figaro of 17 August, counts one dead and several injured.',
                'de' => 'Die niederländischen Zeitungen vom 18. August 1930 erwähnen keinen Toten und zählen sechs Verletzte, während die Wikipedia-Liste unter Berufung auf Le Figaro vom 17. August einen Toten und mehrere Verletzte zählt.',
                'sources' => ['presse-saverne', 'wp-liste-1930'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
