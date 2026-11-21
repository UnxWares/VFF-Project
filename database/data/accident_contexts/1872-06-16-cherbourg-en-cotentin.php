<?php

// Contexte sourcé du déraillement près de Cherbourg du 16 juin 1872 (recherche du 21/11/2026).
return [
    'slug' => '1872-06-16-cherbourg-en-cotentin',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1870' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1870',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1872',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1872',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (1 mort, 6 blessés) : bandage de la locomotive rompu, train de Paris de 7 h 15 ; renvoie à Le Temps du 20 juin 1872.',
        ],
        'vdr-cherbourg-1872' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1872 - Le cercle d\'une roue se détache non loin de Cherbourg',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1872-le-cercle-dune-roue-se-detache-non-loin-de-cherbourg/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Temps du 28 juin 1872, qui reprend le Phare de la Manche.',
        ],
        'temps-1872-06-28' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 28 juin 1872',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1872-06-28',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k225046w/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 16 juin 1872, le train de Paris arrivant à Cherbourg à 7 h 15 du matin est sorti des rails dans un tournant de la voie, à la hauteur d\'un pont que Le Temps nomme « Conmard ». La descente est très rapide à cet endroit.',
                'en' => 'On Sunday 16 June 1872, the train from Paris, due at Cherbourg at 7.15 am, left the rails at a bend in the line, at a bridge that Le Temps calls "Conmard". The descent is very steep at that spot.',
                'de' => 'Am Sonntag, dem 16. Juni 1872, entgleiste der um 7.15 Uhr in Cherbourg erwartete Zug aus Paris in einer Kurve bei einer Brücke, die Le Temps „Conmard“ nennt. Das Gefälle ist an dieser Stelle sehr steil.',
                'sources' => ['vdr-cherbourg-1872', 'wp-liste-1870'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le cercle (bandage) d\'une des roues de la locomotive s\'est détaché et la locomotive est tombée sur le côté. Seule une voiture de première classe a déraillé, mais les voyageurs ont ressenti un violent choc.',
                'en' => 'The tyre of one of the locomotive\'s wheels came off and the locomotive fell on its side. Only a first-class coach derailed, but the passengers felt a violent shock.',
                'de' => 'Der Radreifen eines Lokomotivrades löste sich, und die Lokomotive kippte zur Seite. Nur ein Wagen erster Klasse entgleiste, doch die Reisenden spürten einen heftigen Stoß.',
                'sources' => ['vdr-cherbourg-1872', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Six personnes ont été blessées : quatre agents de la Compagnie et deux voyageurs, une femme venant de Paris et son enfant de cinq ans, atteints légèrement. Le chef de train a succombé quelques instants après l\'accident.',
                'en' => 'Six people were injured: four company employees and two passengers, a woman from Paris and her five-year-old child, both slightly hurt. The train conductor died a few moments after the accident.',
                'de' => 'Sechs Personen wurden verletzt: vier Angestellte der Gesellschaft und zwei Reisende, eine Frau aus Paris und ihr fünfjähriges Kind, beide leicht. Der Zugführer starb wenige Augenblicke nach dem Unfall.',
                'sources' => ['vdr-cherbourg-1872', 'wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [],
];
