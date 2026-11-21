<?php

// Contexte sourcé de la collision dans le tunnel de Thiviers du 15 novembre 1862 (recherche du 21/11/2026).
return [
    'slug' => '1862-11-15-thiviers',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1862',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1862',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après Le Temps du 17 novembre 1862 : à la sortie du tunnel de Thiviers, sur la ligne de Limoges à Périgueux, vers 11 h, un train de voyageurs et un train de marchandises se heurtent de front ; un chauffeur est tué, d\'autres agents des deux trains sont blessés, les voyageurs sont indemnes.',
        ],
        'ddb-1862-11-22-koelnische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Am 15., Vormittags, stieß auf der Eisenbahn zwischen Périgueux und Limoges im Tunnel von Thiviers ein Personenzug',
            'publisher' => 'Kölnische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1862-11-22',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/TPHG5UNQDC2DOEK4XC3IQAEZT54OQ4PM?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : le 15 au matin, dans le tunnel de Thiviers, entre Périgueux et Limoges, un train de voyageurs rejoint et heurte un train de marchandises ; les voyageurs s\'en tirent avec la peur, des wagons de bœufs et de vin les séparant de la machine ; l\'un des machinistes est mort, l\'autre grièvement blessé.',
        ],
        'ddb-1862-11-25-siegkreis' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Am 15. d. M. Vormittags stieß auf der Eisenbahn zwischen Périgueux und Limoges im Tunnel von Thiviers ein Personenzug',
            'publisher' => 'Anzeiger des Siegkreises (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1862-11-25',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/SC2DYO72CDW4RJ7YHJEMPKZE4UYUOUTJ?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même brève : train de voyageurs contre un train de marchandises qu\'il avait rattrapé dans le tunnel de Thiviers ; voyageurs indemnes, un machiniste mort, l\'autre grièvement blessé.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 15 novembre 1862, vers 11 h, un train de voyageurs et un train de marchandises se sont heurtés dans le tunnel de Thiviers (Dordogne), ou à sa sortie, sur la ligne de Périgueux à Limoges.',
                'en' => 'On 15 November 1862, at about 11 am, a passenger train and a goods train collided in the Thiviers tunnel (Dordogne), or at its exit, on the Périgueux to Limoges line.',
                'de' => 'Am 15. November 1862 stießen gegen 11 Uhr im Tunnel von Thiviers (Dordogne) oder an dessen Ausfahrt auf der Strecke Périgueux-Limoges ein Personenzug und ein Güterzug zusammen.',
                'sources' => ['wp-liste-1860', 'ddb-1862-11-22-koelnische', 'ddb-1862-11-25-siegkreis'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La presse allemande décrit un train de voyageurs qui rattrape le train de marchandises et le heurte dans le tunnel ; la liste de Wikipédia parle d\'un choc de front. Les voyageurs n\'ont pas été touchés, des wagons chargés de bœufs et de vin les séparant de la machine.',
                'en' => 'The German press describes a passenger train that catches up with the goods train and runs into it in the tunnel; the Wikipedia list speaks of a head-on collision. The passengers were unhurt, as wagons loaded with oxen and wine separated them from the engine.',
                'de' => 'Die deutsche Presse beschreibt einen Personenzug, der den Güterzug im Tunnel einholt und auf ihn auffährt; die Wikipedia-Liste spricht von einem Frontalzusammenstoß. Die Reisenden blieben unverletzt, da Wagen mit Ochsen und Wein sie von der Maschine trennten.',
                'sources' => ['ddb-1862-11-22-koelnische', 'ddb-1862-11-25-siegkreis', 'wp-liste-1860'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent de conduite a été tué et un autre grièvement blessé ; les voyageurs sont indemnes. La liste de Wikipédia précise que le mort est un chauffeur et ajoute d\'autres agents blessés.',
                'en' => 'One member of a locomotive crew was killed and another seriously injured; the passengers were unhurt. The Wikipedia list says the dead man was a stoker and adds other injured staff.',
                'de' => 'Ein Lokomotivbediensteter wurde getötet und ein weiterer schwer verletzt; die Reisenden blieben unverletzt. Die Wikipedia-Liste präzisiert, dass der Tote ein Heizer war, und nennt weitere verletzte Bedienstete.',
                'sources' => ['ddb-1862-11-22-koelnische', 'ddb-1862-11-25-siegkreis', 'wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le déroulement diffère : choc de front selon la liste de Wikipédia, rattrapage d\'un train de marchandises par un train de voyageurs selon la presse allemande. Le nombre de blessés diffère aussi : un machiniste grièvement blessé dans la presse, plusieurs agents blessés dans la liste.',
                'en' => 'The sequence differs: a head-on collision according to the Wikipedia list, a passenger train catching up with a goods train according to the German press. The number of injured differs too: one seriously injured driver in the press, several injured staff in the list.',
                'de' => 'Der Hergang weicht ab: Frontalzusammenstoß nach der Wikipedia-Liste, Auffahren eines Personenzuges auf einen Güterzug nach der deutschen Presse. Auch die Zahl der Verletzten weicht ab: ein schwer verletzter Maschinist in der Presse, mehrere verletzte Bedienstete in der Liste.',
                'sources' => ['ddb-1862-11-25-siegkreis', 'wp-liste-1860'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['ddb-1862-11-22-koelnische', 'wp-liste-1860'],
            'fr' => 'Un mort selon la liste de Wikipédia et la presse allemande du 22 novembre 1862 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One dead according to the Wikipedia list and the German press of 22 November 1862; the record gave no number of deaths.',
            'de' => 'Ein Toter nach der Wikipedia-Liste und der deutschen Presse vom 22. November 1862; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
