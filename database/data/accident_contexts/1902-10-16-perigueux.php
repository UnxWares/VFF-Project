<?php

// Contexte sourcé de la collision en gare de La Cave près de Périgueux du 16 octobre 1902 (recherche du 21/11/2026).
return [
    'slug' => '1902-10-16-perigueux',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1902' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1902',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1902&oldid=233590581#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Gare de La Cave, ligne Bordeaux-Périgueux, vers 10 h, un train pour Bordeaux prend en écharpe les dernières voitures du train de sens contraire ; un mort et une trentaine de blessés dont cinq très graves ; renvoie à Le Matin du 17 octobre 1902.',
        ],
        'delpher-noordbrabantsche-1902-10-18' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Buitenland. Frankrijk',
            'publisher' => 'Provinciale Noordbrabantsche en \'s Hertogenbossche Courant (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1902-10-18',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=MMSADB01:000013162:mpeg21:a0004',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Deux trains de voyageurs entrés en collision à la gare de La Cave près de Périgueux, plusieurs voyageurs blessés ou contusionnés, dégâts matériels très importants.',
        ],
        'delpher-rotterdamsch-1902-10-18' => [
            'provider' => 'delpher',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Gemengd Nieuws',
            'publisher' => 'Rotterdamsch Nieuwsblad (Delpher, Koninklijke Bibliotheek)',
            'author' => null,
            'published' => '1902-10-18',
            'locator' => null,
            'url' => 'http://resolver.kb.nl/resolve?urn=ddd:010178505:mpeg21:a0123',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche : deux trains de voyageurs, plusieurs blessés, dégâts matériels assez importants.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le jeudi 16 octobre 1902, vers 10 h, à la gare de La Cave, à quelques kilomètres de Périgueux sur la ligne de Bordeaux, là où la voie double redevient unique, deux trains de voyageurs sont entrés en collision, d\'après la liste de Wikipédia et la presse néerlandaise du 18 octobre.',
                'en' => 'On Thursday 16 October 1902, at about 10 am, at La Cave station, a few kilometres from Périgueux on the Bordeaux line, where the double track becomes single again, two passenger trains collided, according to the Wikipedia list and the Dutch press of 18 October.',
                'de' => 'Am Donnerstag, dem 16. Oktober 1902, stießen gegen 10 Uhr im Bahnhof La Cave, einige Kilometer von Périgueux an der Strecke nach Bordeaux, wo das zweigleisige Gleis wieder eingleisig wird, nach der Wikipedia-Liste und der niederländischen Presse vom 18. Oktober zwei Reisezüge zusammen.',
                'sources' => ['wp-liste-1902', 'delpher-noordbrabantsche-1902-10-18', 'delpher-rotterdamsch-1902-10-18'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon la liste de Wikipédia, un train pour Bordeaux a pris en écharpe, sur l\'aiguille d\'accès au tronçon à voie unique, les dernières voitures du train de sens contraire, de Coutras à Périgueux, qui ne l\'avaient pas encore complètement dégagée.',
                'en' => 'According to the Wikipedia list, a train for Bordeaux struck a glancing blow, on the points leading to the single-track section, to the last coaches of the train coming the other way, from Coutras to Périgueux, which had not yet fully cleared them.',
                'de' => 'Nach der Wikipedia-Liste streifte ein Zug nach Bordeaux an der Weiche zum eingleisigen Abschnitt die letzten Wagen des entgegenkommenden Zuges von Coutras nach Périgueux, die diese noch nicht ganz freigemacht hatten.',
                'sources' => ['wp-liste-1902'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'La presse néerlandaise mentionne plusieurs voyageurs blessés ou contusionnés et des dégâts matériels très importants. La liste de Wikipédia compte un mort et une trentaine de blessés dont cinq très graves.',
                'en' => 'The Dutch press mentions several injured or bruised passengers and very large material damage. The Wikipedia list counts one dead and about thirty injured, five of them very seriously.',
                'de' => 'Die niederländische Presse erwähnt mehrere verletzte oder geprellte Reisende und sehr hohen Sachschaden. Die Wikipedia-Liste zählt einen Toten und etwa dreißig Verletzte, davon fünf sehr schwer.',
                'sources' => ['delpher-noordbrabantsche-1902-10-18', 'delpher-rotterdamsch-1902-10-18', 'wp-liste-1902'],
            ],
        ],
    ],
    'corrections' => [],
];
