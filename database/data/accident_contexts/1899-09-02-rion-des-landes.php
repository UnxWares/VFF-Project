<?php

// Contexte sourcé du tamponnement d'un train de marchandises par le Sud-Express entre Rion-des-Landes et Laluque, septembre 1899 (recherche du 22/11/2026).
return [
    'slug' => '1899-09-02-rion-des-landes',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1890' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1890',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1899',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1899',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 2 septembre 1899, d\'après Le Petit Parisien du 3 septembre : sur la ligne de Bordeaux à Hendaye, peu après Morcenx, entre Rion-des-Landes et Laluque, vers 4 h, le Sud-Express heurte dans une courbe l\'arrière d\'un train de marchandises en retard ; le serre-frein du train tamponné est tué, le chauffeur et quelques voyageurs du Sud-Express blessés.',
        ],
        'ddb-1899-09-06-witten' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'San Sebastian, 4. Sept.',
            'publisher' => 'Wittener Volks-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1899-09-06',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/435AH7CCVSBU4LPHZ4LI7LRMHJFSUTPV?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Brève de Saint-Sébastien : quatorze personnes blessées dans la collision d\'un train de voyageurs et d\'un train de marchandises sur le parcours de Laluque, sur la relation vers Madrid ; pas d\'autres détails.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 2 septembre 1899 vers 4 h, sur la ligne de Bordeaux à Hendaye, entre les gares de Rion-des-Landes et de Laluque, le Sud-Express a heurté dans une courbe à faible visibilité l\'arrière d\'un train de marchandises en retard.',
                'en' => 'On 2 September 1899 at about 4 a.m., on the Bordeaux to Hendaye line between Rion-des-Landes and Laluque stations, the Sud-Express ran into the rear of a late-running goods train on a curve with poor visibility.',
                'de' => 'Am 2. September 1899 gegen 4 Uhr fuhr auf der Strecke Bordeaux-Hendaye zwischen den Bahnhöfen Rion-des-Landes und Laluque der Sud-Express in einer unübersichtlichen Kurve auf das Ende eines verspäteten Güterzugs auf.',
                'sources' => ['wp-liste-1890', 'ddb-1899-09-06-witten'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon la liste de Wikipédia, le serre-frein du train de marchandises a été tué, le chauffeur et quelques voyageurs du Sud-Express blessés. La brève venue de Saint-Sébastien compte quatorze blessés.',
                'en' => 'According to the Wikipedia list, the brakesman of the goods train was killed and the fireman and some passengers of the Sud-Express were injured. The brief from San Sebastián counts fourteen injured.',
                'de' => 'Nach der Wikipedia-Liste wurde der Bremser des Güterzugs getötet, der Heizer und einige Reisende des Sud-Express wurden verletzt. Die Kurzmeldung aus San Sebastián nennt vierzehn Verletzte.',
                'sources' => ['wp-liste-1890', 'ddb-1899-09-06-witten'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La brève de Saint-Sébastien ne parle d\'aucun mort ; la liste de Wikipédia, d\'après Le Petit Parisien, en compte un.',
                'en' => 'The San Sebastián brief mentions no deaths; the Wikipedia list, based on Le Petit Parisien, counts one.',
                'de' => 'Die Kurzmeldung aus San Sebastián erwähnt keinen Toten; die Wikipedia-Liste nennt nach Le Petit Parisien einen.',
                'sources' => ['ddb-1899-09-06-witten', 'wp-liste-1890'],
            ],
        ],
    ],
];
