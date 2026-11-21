<?php

// Contexte sourcé de la collision de la gare d'Orsay à Paris du 9 août 1917, classée à Juvisy-sur-Orge (recherche du 21/11/2026).
return [
    'slug' => '1917-08-09-juvisy-sur-orge',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1917' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1917',
            'publisher' => 'Wikipédia',
            'author' => null,
            'published' => null,
            'locator' => 'section Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1917&oldid=235357718#Ao%C3%BBt',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste : à Paris, vers 18 h 10, un train quittant la gare d\'Orsay pour Juvisy est pris en écharpe par une rame vide de vingt voitures, 3 morts et 14 blessés.',
        ],
        'excelsior-1917-08-10' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Excelsior du 10 août 1917 : « Grave accident à la gare du quai d\'Orsay »',
            'publisher' => 'Excelsior (numérisation de l\'Université Paris Nanterre, Argonnaute)',
            'author' => null,
            'published' => '1917-08-10',
            'locator' => 'p. 1',
            'url' => 'https://argonnaute.parisnanterre.fr/media/aaaef17d-90ad-45fc-98cc-e2caebb42848.pdf',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Article de une : le train de banlieue 271 parti de Paris-Orsay à 18 h 20 est télescopé après 150 m par une rame vide destinée à l\'express de Toulouse, 3 morts et 11 blessés, cause encore incertaine (erreur d\'aiguillage ou signal dépassé).',
        ],
        'petit-parisien-1917-08-10' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 10 août 1917',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1917-08-10',
            'locator' => 'p. 1 et 2',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k566253k/f1.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit de l\'accident, cité par Wikipédia.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 9 août 1917 en début de soirée, un train de banlieue en provenance de la gare d\'Orsay, à Paris, a été heurté peu après son départ, à 150 m environ des quais, par une rame vide qui rentrait en gare pour former l\'express de Toulouse. Le train de banlieue allait à Juvisy-sur-Orge.',
                'en' => 'On the early evening of 9 August 1917, a suburban train from Orsay station in Paris was struck shortly after leaving, about 150 m from the platforms, by an empty rake coming into the station to form the Toulouse express. The suburban train was bound for Juvisy-sur-Orge.',
                'de' => 'Am frühen Abend des 9. August 1917 wurde ein Vorortzug aus dem Bahnhof Orsay in Paris kurz nach der Abfahrt, etwa 150 m von den Bahnsteigen entfernt, von einer leeren Wagengarnitur gerammt, die zur Bildung des Toulouse-Schnellzugs in den Bahnhof einfuhr. Der Vorortzug fuhr nach Juvisy-sur-Orge.',
                'sources' => ['excelsior-1917-08-10', 'wp-liste-1917'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Selon Wikipédia, la rame vide de vingt voitures prenait en écharpe le train de banlieue au moment où il s\'engageait sur la voie principale, à faible vitesse ; les premières voitures du train de banlieue se sont disloquées contre la voûte du tunnel menant à la gare d\'Austerlitz. Excelsior décrit la locomotrice du train de banlieue gravement endommagée et la voiture de troisième classe qui la suivait mise en miettes.',
                'en' => 'According to Wikipedia, the empty rake of twenty coaches struck the suburban train at an angle as it was entering the main track, at low speed; the first coaches of the suburban train broke up against the roof of the tunnel leading to Austerlitz station. Excelsior describes the suburban train\'s motor unit as badly damaged and the third-class coach behind it as smashed to pieces.',
                'de' => 'Laut Wikipedia streifte die leere Garnitur aus zwanzig Wagen den Vorortzug beim Einfahren auf das Hauptgleis bei geringer Geschwindigkeit; die ersten Wagen des Vorortzugs zerbrachen am Gewölbe des Tunnels zum Bahnhof Austerlitz. Excelsior beschreibt den Triebwagen des Vorortzugs als schwer beschädigt und den folgenden Wagen dritter Klasse als in Stücke gerissen.',
                'sources' => ['wp-liste-1917', 'excelsior-1917-08-10'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Excelsior du 10 août compte trois morts et onze blessés ; la liste de Wikipédia, d\'après Le Petit Parisien, compte trois morts et quatorze blessés, dont trois employées du wagon-restaurant du train de Toulouse.',
                'en' => 'Excelsior of 10 August counts three dead and eleven injured; the Wikipedia list, based on Le Petit Parisien, counts three dead and fourteen injured, including three women staff of the Toulouse train\'s restaurant car.',
                'de' => 'Excelsior vom 10. August zählt drei Tote und elf Verletzte; die Wikipedia-Liste nennt nach Le Petit Parisien drei Tote und vierzehn Verletzte, darunter drei Angestellte des Speisewagens des Toulouse-Zugs.',
                'sources' => ['excelsior-1917-08-10', 'wp-liste-1917'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Excelsior du 10 août évoque deux hypothèses, une erreur d\'aiguillage ou un signal dépassé par le mécanicien, et retient la seconde comme la plus probable à ce stade.',
                'en' => 'Excelsior of 10 August raises two hypotheses, a pointing error or a signal passed by the driver, and treats the second as the more likely at that stage.',
                'de' => 'Excelsior vom 10. August nennt zwei Hypothesen, einen Weichenfehler oder ein vom Lokführer überfahrenes Signal, und hält zu diesem Zeitpunkt die zweite für wahrscheinlicher.',
                'sources' => ['excelsior-1917-08-10'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les heures et les bilans varient : départ à 18 h 20 pour Excelsior, accident vers 18 h 10 pour la liste de Wikipédia ; 11 blessés pour Excelsior, 14 pour la liste.',
                'en' => 'Times and tolls differ: departure at 6.20 pm for Excelsior, accident at about 6.10 pm for the Wikipedia list; 11 injured for Excelsior, 14 for the list.',
                'de' => 'Uhrzeiten und Bilanzen weichen ab: Abfahrt um 18.20 Uhr laut Excelsior, Unfall gegen 18.10 Uhr laut Wikipedia-Liste; 11 Verletzte laut Excelsior, 14 laut Liste.',
                'sources' => ['excelsior-1917-08-10', 'wp-liste-1917'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '75056',
            'sources' => ['excelsior-1917-08-10', 'wp-liste-1917'],
            'fr' => 'Excelsior du 10 août 1917 et la liste de Wikipédia situent l\'accident à Paris, aux abords de la gare d\'Orsay ; Juvisy-sur-Orge était la destination du train de banlieue.',
            'en' => 'Excelsior of 10 August 1917 and the Wikipedia list place the accident in Paris, near Orsay station; Juvisy-sur-Orge was the suburban train\'s destination.',
            'de' => 'Excelsior vom 10. August 1917 und die Wikipedia-Liste verorten den Unfall in Paris, nahe dem Bahnhof Orsay; Juvisy-sur-Orge war das Ziel des Vorortzugs.',
        ],
    ],
];
