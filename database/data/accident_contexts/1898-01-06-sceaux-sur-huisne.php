<?php

// Contexte sourcé du déraillement d'un train de marchandises entre Sceaux-sur-Huisne et La Ferté-Bernard, ligne du Mans à Paris, de janvier 1898 (recherche du 21/11/2026).
return [
    'slug' => '1898-01-06-sceaux-sur-huisne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1898',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1898',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 6 janvier 1898, d\'après Le Figaro du 7 janvier : vers minuit, sur la ligne du Mans à Paris, entre les gares de Sceaux-sur-Huisne et de La Ferté-Bernard, un train de marchandises Brest - Paris déraille après avoir heurté un bœuf errant sur la voie ; le conducteur du train est tué ; sa veuve obtiendra plus tard la condamnation du propriétaire de l\'animal (arrêt de la cour d\'appel de Paris du 28 mars 1901).',
        ],
        'ddb-1898-01-07-hagen' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Mans, 7. Januar',
            'publisher' => 'Hagener Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1898-01-07',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/Y4GMKCQM5DOCWY7OLVNFL6K6NQDSFCPN?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du Mans du 7 janvier : la nuit dernière, entre la gare de Sceaux et celle de La Ferté-Bernard, la locomotive, le tender et 21 wagons d\'un train de marchandises ont déraillé ; le chef de train a été tué, le mécanicien est indemne, le chauffeur légèrement blessé.',
        ],
        'ddb-1898-01-09-reichsbote' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Mans, 6. Januar',
            'publisher' => 'Der Reichsbote (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1898-01-09',
            'locator' => 'p. 4',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/PHEGNZQ4B4ED4G66J6FZQQH2B2652Z6E?issuepage=4',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche du Mans datée du 6 janvier : entre Sceaux et La Ferté-Bernard, la locomotive, le tender et 21 wagons d\'un train de marchandises ont déraillé ; le chef de train tué, le chauffeur légèrement blessé.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du 6 au 7 janvier 1898, vers minuit, un train de marchandises de Brest à Paris roulait sur la ligne du Mans à Paris, entre les gares de Sceaux-sur-Huisne et de La Ferté-Bernard.',
                'en' => 'During the night of 6 to 7 January 1898, at about midnight, a Brest to Paris goods train was running on the Le Mans to Paris line between Sceaux-sur-Huisne and La Ferté-Bernard stations.',
                'de' => 'In der Nacht vom 6. auf den 7. Januar 1898 gegen Mitternacht fuhr ein Güterzug Brest-Paris auf der Strecke Le Mans-Paris zwischen den Bahnhöfen Sceaux-sur-Huisne und La Ferté-Bernard.',
                'sources' => ['wp-liste-1890', 'ddb-1898-01-07-hagen'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le train a heurté un bœuf errant sur la voie. La locomotive, le tender et 21 wagons ont déraillé.',
                'en' => 'According to the Wikipedia list the train struck an ox that had strayed onto the track. The engine, the tender and 21 wagons derailed.',
                'de' => 'Nach der Wikipedia-Liste erfasste der Zug einen auf das Gleis gelaufenen Ochsen. Lokomotive, Tender und 21 Wagen entgleisten.',
                'sources' => ['wp-liste-1890', 'ddb-1898-01-07-hagen', 'ddb-1898-01-09-reichsbote'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chef de train a été tué. Le chauffeur a été légèrement blessé, le mécanicien est sorti indemne.',
                'en' => 'The train guard was killed. The stoker was slightly injured and the driver was unhurt.',
                'de' => 'Der Zugführer wurde getötet. Der Heizer wurde leicht verletzt, der Lokführer blieb unverletzt.',
                'sources' => ['ddb-1898-01-07-hagen', 'ddb-1898-01-09-reichsbote', 'wp-liste-1890'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Selon la liste de Wikipédia, la veuve du chef de train a obtenu la condamnation du propriétaire de l\'animal à 10 000 francs de dommages et intérêts, par un arrêt de la cour d\'appel de Paris du 28 mars 1901.',
                'en' => 'According to the Wikipedia list the guard\'s widow obtained an order against the owner of the animal for 10,000 francs in damages, by a ruling of the Paris court of appeal of 28 March 1901.',
                'de' => 'Nach der Wikipedia-Liste erwirkte die Witwe des Zugführers mit einem Urteil des Pariser Berufungsgerichts vom 28. März 1901 die Verurteilung des Tierhalters zu 10 000 Francs Schadenersatz.',
                'sources' => ['wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1890', 'ddb-1898-01-07-hagen', 'ddb-1898-01-09-reichsbote'],
            'fr' => 'Chef de train tué selon la liste de Wikipédia (Le Figaro du 7 janvier 1898) et la dépêche du Mans ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Train guard killed according to the Wikipedia list (Le Figaro of 7 January 1898) and the Le Mans dispatch; the record gave no number of deaths.',
            'de' => 'Zugführer getötet nach der Wikipedia-Liste (Le Figaro vom 7. Januar 1898) und der Depesche aus Le Mans; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
