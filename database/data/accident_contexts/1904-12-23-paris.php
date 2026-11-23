<?php

// Contexte sourcé de l'accident de Paris (La Chapelle) du 23 décembre 1904 (recherche du 21/11/2026).
return [
    'slug' => '1904-12-23-paris',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1904' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1904',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1904&oldid=240831504#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 23 décembre 1904 ; elle renvoie à Le Petit Parisien (25 décembre 1904), Le Figaro (26 décembre 1904 et 2 janvier 1905), Le Radical (24 mars 1906) et L\'Est républicain (8 avril 1906). Seule source lisible sur cet accident.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 23 décembre 1904 à 23 h 10, à environ deux kilomètres avant la gare du Nord, près de la gare de La Chapelle, un rapide de Lille est arrêté dans le brouillard.',
                'en' => 'On 23 December 1904 at 11.10 p.m., about two kilometres short of the Gare du Nord, near La Chapelle station, an express from Lille was standing in fog.',
                'de' => 'Am 23. Dezember 1904 um 23.10 Uhr stand etwa zwei Kilometer vor dem Gare du Nord, nahe dem Bahnhof La Chapelle, ein Schnellzug aus Lille im Nebel.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Un train de marée venant de Boulogne le heurte à environ 60 km/h. Le fourgon de queue et la dernière voiture du rapide sont détruits.',
                'en' => 'A fish train from Boulogne ran into it at about 60 km/h. The rear van and the last coach of the express were destroyed.',
                'de' => 'Ein Fischzug aus Boulogne fuhr mit etwa 60 km/h auf ihn auf. Der Packwagen am Zugschluss und der letzte Wagen des Schnellzugs wurden zerstört.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'L\'accident fait quatorze morts et une vingtaine de blessés.',
                'en' => 'The accident killed fourteen people and injured about twenty.',
                'de' => 'Bei dem Unfall starben vierzehn Menschen, etwa zwanzig wurden verletzt.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'L\'enquête met en cause l\'agent chargé des signaux : le signal protégeant le train arrêté était fermé, mais le pétard de brouillard destiné à le renforcer ne s\'est pas déclenché, son mécanisme de pose sur le rail étant bloqué. Le conducteur du train de Boulogne invoquait l\'absence de visibilité due au brouillard.',
                'en' => 'The inquiry blamed the employee in charge of the signals: the signal protecting the stationary train was at danger, but the fog detonator meant to reinforce it did not go off because its mechanism for placing it on the rail was jammed. The driver of the Boulogne train pleaded that fog had left him no visibility.',
                'de' => 'Die Untersuchung belastete den für die Signale zuständigen Bediensteten: Das Signal zur Deckung des haltenden Zuges stand auf Halt, doch der zur Verstärkung vorgesehene Nebelsprengkörper zündete nicht, weil der Mechanismus zum Auflegen auf die Schiene klemmte. Der Fahrer des Zuges aus Boulogne machte geltend, der Nebel habe ihm jede Sicht genommen.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le tribunal correctionnel de la Seine a néanmoins condamné le conducteur du train de Boulogne pour défaut de maîtrise de sa vitesse, à six mois de prison avec sursis et 2 000 francs d\'amende.',
                'en' => 'The Seine criminal court nonetheless convicted the driver of the Boulogne train of failing to control his speed, with a six-month suspended prison sentence and a fine of 2,000 francs.',
                'de' => 'Das Strafgericht der Seine verurteilte den Fahrer des Zuges aus Boulogne dennoch wegen mangelnder Beherrschung seiner Geschwindigkeit zu sechs Monaten Haft auf Bewährung und 2000 Franc Geldstrafe.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La carte place l\'accident à la gare du Nord, position de la gare citée par la liste de Wikipédia. Le récit de la liste le situe à environ deux kilomètres avant la gare, près de La Chapelle : la gare du Nord est la gare de rattachement, pas le lieu du choc.',
                'en' => 'The map places the accident at the Gare du Nord, the position of the station named by the Wikipedia list. The list\'s own account puts it about two kilometres short of the station, near La Chapelle: the Gare du Nord is the station it is linked to, not the place of the impact.',
                'de' => 'Die Karte setzt den Unfall an die Gare du Nord, die Position des in der Wikipedia-Liste genannten Bahnhofs. Der Bericht der Liste verortet ihn etwa zwei Kilometer vor dem Bahnhof, bei La Chapelle: Die Gare du Nord ist der zugeordnete Bahnhof, nicht der Ort des Zusammenstoßes.',
                'sources' => ['wp-liste-1904'],
            ],
        ],
    ],
];
