<?php

// Contexte sourcé de la collision sur la Petite Ceinture à Paris du 25 janvier 1896 (recherche du 21/11/2026).
return [
    'slug' => '1896-01-25-paris',
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
            'locator' => '1896',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1896',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : entrée du 25 janvier 1896 (train à l\'arrêt percuté par le suivant sur la Petite Ceinture, entre Neuilly-Porte-Maillot et Courcelles-Levallois, 1 mort, 9 blessés) ; renvoie à La Presse du 25 janvier 1896.',
        ],
        'vdr-paris-1896' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1896 - Accident sur la Ceinture, Paris (75)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1896-accident-sur-la-ceinture-paris-75/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit La Presse du 25 janvier 1896.',
        ],
        'presse-1896-01-25' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 25 janvier 1896',
            'publisher' => 'La Presse',
            'author' => null,
            'published' => '1896-01-25',
            'locator' => 'p. 4',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k547965n/f4',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le samedi 25 janvier 1896, à 9 h 12 du matin, un brouillard très dense était tombé sur la ligne de Ceinture, exploitée par la Compagnie de l\'Ouest, et tous les trains roulaient très lentement. Le train 111, parti de Saint-Lazare à 8 h 51, était arrêté depuis sept à huit minutes avant la gare de la Porte-Maillot, dont la voie était occupée par un train venant du Nord.',
                'en' => 'On Saturday 25 January 1896, at 9.12 am, very dense fog had fallen on the Ceinture line, run by the Compagnie de l\'Ouest, and all trains were running very slowly. Train 111, which had left Saint-Lazare at 8.51, had been stopped for seven or eight minutes before Porte-Maillot station, whose track was occupied by a train coming from the Nord.',
                'de' => 'Am Samstag, dem 25. Januar 1896, um 9.12 Uhr war sehr dichter Nebel auf die von der Compagnie de l\'Ouest betriebene Ringbahn gefallen, und alle Züge fuhren sehr langsam. Der um 8.51 Uhr in Saint-Lazare abgefahrene Zug 111 stand seit sieben bis acht Minuten vor dem Bahnhof Porte-Maillot, dessen Gleis von einem Zug aus Richtung Nord besetzt war.',
                'sources' => ['vdr-paris-1896'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le train 231, parti de Saint-Lazare à 9 h, est arrivé sur le train 111 : son mécanicien a vu l\'arrière du train précédent trop tard, a renversé la vapeur, mais le choc a eu lieu. Le fourgon du train 111 a été éventré par la locomotive, deux voitures de première classe se sont télescopées, l\'une se coupant en deux et l\'autre étant projetée contre la voûte du pont de l\'avenue des Ternes. Les voyageurs des deux trains ont regagné à pied la station de Neuilly en suivant la voie dans le tunnel.',
                'en' => 'Train 231, which had left Saint-Lazare at 9 am, ran into train 111: its driver saw the rear of the train ahead too late and put the engine into reverse, but the impact took place. The van of train 111 was torn open by the locomotive, two first-class coaches telescoped, one being cut in two and the other thrown against the arch of the avenue des Ternes bridge. The passengers of both trains walked back to Neuilly station along the track in the tunnel.',
                'de' => 'Der um 9 Uhr in Saint-Lazare abgefahrene Zug 231 fuhr auf den Zug 111 auf: Sein Lokführer sah das Ende des vorausfahrenden Zuges zu spät und gab Gegendampf, doch es kam zum Aufprall. Der Packwagen des Zuges 111 wurde von der Lokomotive aufgerissen, zwei Wagen erster Klasse schoben sich ineinander, der eine wurde in zwei Teile gerissen, der andere gegen das Gewölbe der Brücke der Avenue des Ternes geschleudert. Die Reisenden beider Züge gingen zu Fuß im Tunnel entlang dem Gleis zur Station Neuilly zurück.',
                'sources' => ['vdr-paris-1896'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un voyageur de 30 ans a été tué. Neuf personnes ont été blessées : cinq voyageurs touchés par des éclats de verre ou à la tête, et quatre agents des deux trains (le conducteur arrière et le chef du train 111, le mécanicien et le chauffeur du train 231), avec des blessures internes ou des contusions. Les premiers récits avaient cru à un accident sans victimes.',
                'en' => 'A 30-year-old passenger was killed. Nine people were injured: five passengers hit by glass splinters or with head injuries, and four members of staff of both trains (the rear conductor and the head of train 111, the driver and fireman of train 231), with internal injuries or bruises. The first accounts had believed it was an accident without casualties.',
                'de' => 'Ein 30-jähriger Reisender wurde getötet. Neun Personen wurden verletzt: fünf Reisende durch Glassplitter oder mit Kopfverletzungen sowie vier Bedienstete beider Züge (der hintere Schaffner und der Zugführer des Zuges 111, der Lokführer und der Heizer des Zuges 231) mit inneren Verletzungen oder Prellungen. Die ersten Berichte hatten einen Unfall ohne Opfer angenommen.',
                'sources' => ['vdr-paris-1896', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La Presse attribue l\'accident au brouillard, qui n\'a pas permis au mécanicien du second train d\'apercevoir les feux du train qu\'il suivait. Un voyageur du second train dit n\'avoir entendu aucun pétard avertisseur. L\'administration du chemin de fer de l\'Ouest a ouvert une enquête.',
                'en' => 'La Presse attributes the accident to the fog, which prevented the driver of the second train from seeing the lights of the train he was following. A passenger on the second train says he heard no warning detonator. The administration of the Ouest railway opened an inquiry.',
                'de' => 'La Presse führt den Unfall auf den Nebel zurück, der dem Lokführer des zweiten Zuges nicht erlaubte, die Lichter des vor ihm fahrenden Zuges zu erkennen. Ein Reisender im zweiten Zug sagt, er habe keinen Warnknallkörper gehört. Die Verwaltung der Westbahn leitete eine Untersuchung ein.',
                'sources' => ['vdr-paris-1896'],
            ],
        ],
    ],
    'corrections' => [],
];
