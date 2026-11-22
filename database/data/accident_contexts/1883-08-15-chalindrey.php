<?php

// Contexte sourcé du déraillement d'un train venant de Gray entre Maâtz et Culmont-Chalindrey, ligne de Gray à Culmont-Chalindrey, d'août 1883 (recherche du 21/11/2026).
return [
    'slug' => '1883-08-15-chalindrey',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1880' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1880',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1883',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1880&oldid=236846064#1883',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 15 août 1883 : à 16 h 20, sur la ligne de Gray à Culmont-Chalindrey, la locomotive et cinq voitures d\'un train de voyageurs venant de Gray déraillent entre Maâtz et Culmont-Chalindrey et dévalent le remblai ; le mécanicien est tué, le chauffeur, le chef de train et cinq voyageurs gravement blessés, vingt-cinq autres voyageurs contusionnés.',
        ],
        'ddb-1883-08-21-bautzen' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück',
            'publisher' => 'Bautzener Nachrichten (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1883-08-21',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/55VCUTZART4LALMXOXSQKYB2LE3HHHYZ?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : le 15 août dans l\'après-midi, sur la ligne de l\'Est menant de Paris à Gray, le train rapide a déraillé près de la station de Culmont-Chalindrey ; la locomotive et cinq voitures ont dévalé le remblai, assez raide à cet endroit ; le mécanicien tué sur place, le chauffeur, le chef de train et cinq voyageurs grièvement blessés, 25 voyageurs légèrement.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 15 août 1883, vers 16 h 20, un train de voyageurs venant de Gray a déraillé entre Maâtz et la gare de Culmont-Chalindrey. La locomotive et cinq voitures ont dévalé le remblai, assez raide à cet endroit.',
                'en' => 'On 15 August 1883, at about 4.20 pm, a passenger train from Gray derailed between Maâtz and Culmont-Chalindrey station. The engine and five coaches ran down the embankment, which is fairly steep there.',
                'de' => 'Am 15. August 1883 gegen 16.20 Uhr entgleiste ein Personenzug aus Gray zwischen Maâtz und dem Bahnhof Culmont-Chalindrey. Lokomotive und fünf Wagen stürzten den dort ziemlich steilen Damm hinab.',
                'sources' => ['wp-liste-1880', 'ddb-1883-08-21-bautzen'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien a été tué sur place. Le chauffeur, le chef de train et cinq voyageurs ont été grièvement blessés, vingt-cinq autres voyageurs plus légèrement.',
                'en' => 'The driver was killed on the spot. The stoker, the guard and five passengers were seriously injured, twenty-five other passengers less badly.',
                'de' => 'Der Lokführer war sofort tot. Heizer, Zugführer und fünf Reisende wurden schwer, fünfundzwanzig weitere Reisende leicht verletzt.',
                'sources' => ['wp-liste-1880', 'ddb-1883-08-21-bautzen'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1880', 'ddb-1883-08-21-bautzen'],
            'fr' => 'Mécanicien tué selon la liste de Wikipédia et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver killed according to the Wikipedia list and the German press; the record gave no number of deaths.',
            'de' => 'Lokführer getötet nach der Wikipedia-Liste und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 32,
            'sources' => ['wp-liste-1880', 'ddb-1883-08-21-bautzen'],
            'fr' => 'Sept blessés graves (chauffeur, chef de train, cinq voyageurs) et vingt-cinq voyageurs contusionnés selon la liste de Wikipédia et la presse allemande ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Seven seriously injured (stoker, guard, five passengers) and twenty-five passengers bruised according to the Wikipedia list and the German press; the record gave no number of injured.',
            'de' => 'Sieben Schwerverletzte (Heizer, Zugführer, fünf Reisende) und fünfundzwanzig leicht verletzte Reisende nach der Wikipedia-Liste und der deutschen Presse; der Eintrag nannte keine Zahl von Verletzten.',
        ],
        [
            'field' => 'commune_insee',
            'value' => '52093',
            'sources' => ['wp-liste-1880', 'ddb-1883-08-21-bautzen'],
            'fr' => 'Déraillement entre Maâtz et Culmont-Chalindrey selon la liste de Wikipédia, près de la station de Culmont-Chalindrey selon la presse allemande ; la fiche le plaçait à Gray, d\'où venait le train.',
            'en' => 'Derailment between Maâtz and Culmont-Chalindrey according to the Wikipedia list, near Culmont-Chalindrey station according to the German press; the record placed it at Gray, where the train came from.',
            'de' => 'Entgleisung zwischen Maâtz und Culmont-Chalindrey nach der Wikipedia-Liste, nahe der Station Culmont-Chalindrey nach der deutschen Presse; der Eintrag verortete sie in Gray, woher der Zug kam.',
        ],
    ],
];
