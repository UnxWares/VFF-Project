<?php

// Contexte sourcé du déraillement à l'entrée de la gare de Paris-Lyon du 12 octobre 1926 (recherche du 23/11/2026).
return [
    'slug' => '1926-10-12-paris',
    'checked_on' => '2026-11-23',
    'sources' => [
        'wp-liste-1926' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1926',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1926, 12 octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1926&oldid=233501524#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Entrée de la liste : à l\'entrée de la gare de Paris-Lyon, à 7 heures, rupture d\'essieu, machine du rapide Lausanne-Paris déraillée contre un train de banlieue de Brunoy, un mort et vingt-trois blessés. Cite Le Petit Parisien du 13 octobre 1926.',
        ],
        'petit-parisien-1926-10-13' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 13 octobre 1926',
            'publisher' => 'Le Petit Parisien (numérisé sur Gallica)',
            'author' => null,
            'published' => '1926-10-13',
            'locator' => null,
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k606632h/f1.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-23',
            'note' => 'Page 1, citée par la liste de Wikipédia ; non lue.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 12 octobre 1926 à 7 heures, à l\'entrée de la gare de Paris-Lyon, une rupture d\'essieu a fait dérailler la machine du rapide Lausanne-Paris, qui a percuté et renversé les deux voitures de queue d\'un train de banlieue venu de Brunoy sur la voie voisine, d\'après la liste de Wikipédia.',
                'en' => 'On 12 October 1926 at 7 a.m., at the entrance to the Paris-Lyon station, a broken axle derailed the engine of the Lausanne-Paris express, which struck and overturned the last two carriages of a suburban train from Brunoy on the adjacent track, according to the Wikipedia list.',
                'de' => 'Am 12. Oktober 1926 um 7 Uhr entgleiste an der Einfahrt des Bahnhofs Paris-Lyon durch einen Achsbruch die Lokomotive des Schnellzugs Lausanne-Paris, die auf dem Nachbargleis die letzten beiden Wagen eines Vorortzugs aus Brunoy rammte und umwarf, so die Wikipedia-Liste.',
                'sources' => ['wp-liste-1926'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un banlieusard tué et vingt-trois blessés selon la liste de Wikipédia.',
                'en' => 'One suburban passenger killed and twenty-three injured according to the Wikipedia list.',
                'de' => 'Ein Vorortreisender getötet und dreiundzwanzig verletzt nach der Wikipedia-Liste.',
                'sources' => ['wp-liste-1926'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste de Wikipédia, une rupture d\'essieu de la machine du rapide.',
                'en' => 'According to the Wikipedia list, a broken axle on the express engine.',
                'de' => 'Laut der Wikipedia-Liste ein Achsbruch an der Lokomotive des Schnellzugs.',
                'sources' => ['wp-liste-1926'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La carte place l\'accident à la gare de Paris-Gare-de-Lyon, position de la gare citée par la liste de Wikipédia. Le texte de la liste le situe à l\'entrée de la gare, sur les voies d\'approche : la gare est la gare de rattachement, pas le lieu exact.',
                'en' => 'The map places the accident at Paris-Gare-de-Lyon station, the position of the station named by the Wikipedia list. The list\'s text puts it at the entrance to the station, on the approach tracks: the station is the one it is linked to, not the exact place.',
                'de' => 'Die Karte setzt den Unfall an den Bahnhof Paris-Gare-de-Lyon, die Position des in der Wikipedia-Liste genannten Bahnhofs. Der Text der Liste verortet ihn an der Einfahrt des Bahnhofs, auf den Zufahrtsgleisen: Der Bahnhof ist der zugeordnete Bahnhof, nicht der genaue Ort.',
                'sources' => ['wp-liste-1926'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'derailment',
            'sources' => ['wp-liste-1926'],
            'fr' => 'La liste décrit le déraillement d\'une machine après une rupture d\'essieu, suivi du choc contre un train de banlieue, et non la défaillance d\'un ouvrage d\'art comme la fiche le disait.',
            'en' => 'The list describes the derailment of an engine after an axle broke, followed by a collision with a suburban train, not the failure of a structure as the record said.',
            'de' => 'Die Liste beschreibt die Entgleisung einer Lokomotive nach einem Achsbruch mit anschließendem Zusammenstoß mit einem Vorortzug, nicht das Versagen eines Bauwerks, wie der Eintrag es angab.',
            'single_source' => true,
        ],
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1926'],
            'fr' => 'Un banlieusard tué selon la liste de Wikipédia ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One suburban passenger killed according to the Wikipedia list; the record gave no number of deaths.',
            'de' => 'Ein Vorortreisender getötet nach der Wikipedia-Liste; der Eintrag nannte keine Zahl von Toten.',
            'single_source' => true,
        ],
        [
            'field' => 'injuries',
            'value' => 23,
            'sources' => ['wp-liste-1926'],
            'fr' => 'Vingt-trois blessés selon la liste de Wikipédia ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Twenty-three injured according to the Wikipedia list; the record gave no number of injured.',
            'de' => 'Dreiundzwanzig Verletzte nach der Wikipedia-Liste; der Eintrag nannte keine Zahl von Verletzten.',
            'single_source' => true,
        ],
    ],
];
