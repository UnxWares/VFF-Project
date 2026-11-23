<?php

// Contexte sourcé de la collision à l'entrée de la gare de Belfort du 12 août 1881 (recherche du 23/11/2026).
return [
    'slug' => '1881-08-12-belfort',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1881, 12 août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1881',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Entrée du 12 août 1881 : à son entrée en gare de Belfort, l\'express Calais-Belfort percute un rapide pour Paris, quatre voitures sont écrasées, un mort et huit blessés graves. Cite Le Temps du 14 août 1881, p. 4.',
        ],
        'temps-1881-08-14' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 14 août 1881',
            'publisher' => 'Le Temps (numérisé sur Gallica)',
            'author' => null,
            'published' => '1881-08-14',
            'locator' => 'p. 4',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k230020s/f4.zoom',
            'access' => 'link_only',
            'consulted_on' => '2026-11-23',
            'note' => 'Page citée par la liste de Wikipédia ; non lue (Gallica refuse les accès automatiques).',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 12 août 1881, à l\'entrée en gare de Belfort, l\'express Calais-Belfort a percuté un rapide à destination de Paris. Quatre voitures ont été écrasées, d\'après la liste de Wikipédia, qui renvoie au Temps du 14 août 1881. Le lieu est l\'entrée de la gare, pas la gare elle-même ; la liste ne donne ni point kilométrique ni cause.',
                'en' => 'On 12 August 1881, at the entrance to Belfort station, the Calais-Belfort express hit a fast train bound for Paris. Four carriages were crushed, according to the Wikipedia list, which cites Le Temps of 14 August 1881. The place is the approach to the station, not the station itself; the list gives neither a kilometre point nor a cause.',
                'de' => 'Am 12. August 1881 stieß der Schnellzug Calais-Belfort bei der Einfahrt in den Bahnhof Belfort mit einem Schnellzug nach Paris zusammen. Vier Wagen wurden zertrümmert, nach der Wikipedia-Liste, die auf Le Temps vom 14. August 1881 verweist. Der Ort ist die Einfahrt des Bahnhofs, nicht der Bahnhof selbst; die Liste nennt weder eine Kilometrierung noch eine Ursache.',
                'sources' => ['wp-liste-1880'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'On a retiré des voitures écrasées un mort et huit blessés graves, selon la liste de Wikipédia.',
                'en' => 'One dead and eight seriously injured were pulled from the crushed carriages, according to the Wikipedia list.',
                'de' => 'Aus den zertrümmerten Wagen wurden nach der Wikipedia-Liste ein Toter und acht Schwerverletzte geborgen.',
                'sources' => ['wp-liste-1880'],
            ],
        ],
    ],
];
