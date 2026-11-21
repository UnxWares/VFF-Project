<?php

// Contexte sourcé du tamponnement du rapide n° 121 par un train de marchandises dans la tempête de neige, entre Alzonne et Pezens, en janvier 1893 (recherche du 21/11/2026).
return [
    'slug' => '1893-01-16-pezens',
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
            'locator' => '1893, 16 janvier',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1893',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (un mort et quatre blessés graves), d\'après Le Petit Journal du 17 janvier 1893.',
        ],
        'vdr-pezens' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1893, La ligne de Toulouse, prise sous la neige',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1893-la-ligne-de-toulouse-prise-sous-la-neige/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit les dépêches de Carcassonne du 16 janvier 1893 publiées par Le Petit Journal et La Dépêche du 17 janvier 1893.',
        ],
        'petit-journal-1893-01-17' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Journal du 17 janvier 1893',
            'publisher' => 'Le Petit Journal',
            'author' => null,
            'published' => '1893-01-17',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k612673d/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Dans la nuit du 15 au 16 janvier 1893, la neige est tombée en telle abondance que la circulation a été interrompue sur la ligne de Toulouse à Cette. Un train de marchandises n\'a pas pu atteindre à temps la gare de Pezens, où il devait se garer pour laisser passer le rapide n° 121, qui a dû s\'arrêter derrière lui.',
                'en' => 'During the night of 15 to 16 January 1893 so much snow fell that traffic stopped on the Toulouse to Cette line. A goods train could not reach Pezens station in time, where it was to be put into a siding to let express no. 121 pass, and the express had to stop behind it.',
                'de' => 'In der Nacht vom 15. auf den 16. Januar 1893 fiel so viel Schnee, dass der Verkehr auf der Strecke Toulouse-Cette zum Erliegen kam. Ein Güterzug konnte den Bahnhof Pezens nicht rechtzeitig erreichen, wo er abgestellt werden sollte, um den Schnellzug Nr. 121 vorbeizulassen; dieser musste hinter ihm anhalten.',
                'sources' => ['vdr-pezens', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Vers 4 heures du matin, entre Alzonne et Pezens, en face de Villesèque, un second train de marchandises qui n\'avait pas vu les signaux d\'arrêt dans la tourmente a tamponné le rapide par l\'arrière. Les dernières voitures du rapide ont été réduites en miettes.',
                'en' => 'At about 4 am, between Alzonne and Pezens, opposite Villesèque, a second goods train that had not seen the stop signals in the blizzard ran into the rear of the express. The express\'s last carriages were smashed to pieces.',
                'de' => 'Gegen 4 Uhr morgens fuhr zwischen Alzonne und Pezens, gegenüber Villesèque, ein zweiter Güterzug, der im Schneesturm die Haltesignale nicht gesehen hatte, auf das Ende des Schnellzugs auf. Die letzten Wagen des Schnellzugs wurden zertrümmert.',
                'sources' => ['vdr-pezens', 'wp-liste-1890'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent a été tué et quatre voyageurs ont été grièvement blessés.',
                'en' => 'One railwayman was killed and four passengers were seriously injured.',
                'de' => 'Ein Bahnbediensteter wurde getötet, vier Reisende wurden schwer verletzt.',
                'sources' => ['vdr-pezens', 'wp-liste-1890'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le train de secours parti de Carcassonne avec trois médecins et les autorités a mis deux heures pour atteindre Pezens, à quatre kilomètres à l\'heure, puis a été arrêté par la neige amoncelée sur la voie ; un second train de secours est resté lui aussi en détresse. Des officiers de dragons n\'ont pas pu gagner Alzonne à cheval. Le service a été suspendu entre Carcassonne et Castelnaudary.',
                'en' => 'The relief train that left Carcassonne with three doctors and the authorities took two hours to reach Pezens, at four kilometres an hour, and was then stopped by snow piled on the track; a second relief train was also stranded. Dragoon officers were unable to reach Alzonne on horseback. Services were suspended between Carcassonne and Castelnaudary.',
                'de' => 'Der Hilfszug, der mit drei Ärzten und den Behörden aus Carcassonne abfuhr, brauchte zwei Stunden bis Pezens, mit vier Kilometern pro Stunde, und blieb dann im auf dem Gleis aufgetürmten Schnee stecken; ein zweiter Hilfszug blieb ebenfalls liegen. Dragoneroffiziere konnten Alzonne zu Pferd nicht erreichen. Der Verkehr zwischen Carcassonne und Castelnaudary wurde eingestellt.',
                'sources' => ['vdr-pezens'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Les dépêches ne s\'accordent pas : Le Petit Journal et Wikipédia donnent le chef de train comme seul mort et nomment le train tamponneur 1 103 ; La Dépêche parle du mécanicien du train tamponneur et du train n° 155. Le Petit Journal place le choc à trois kilomètres de la gare d\'Alzonne.',
                'en' => 'The dispatches do not agree: Le Petit Journal and Wikipedia give the guard as the only dead and call the colliding train no. 1103; La Dépêche speaks of the driver of the colliding train and of train no. 155. Le Petit Journal places the impact three kilometres from Alzonne station.',
                'de' => 'Die Meldungen widersprechen sich: Le Petit Journal und Wikipedia nennen den Zugführer als einzigen Toten und den auffahrenden Zug Nr. 1103; La Dépêche spricht vom Lokführer des auffahrenden Zuges und vom Zug Nr. 155. Le Petit Journal verortet den Aufprall drei Kilometer vom Bahnhof Alzonne entfernt.',
                'sources' => ['vdr-pezens', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['vdr-pezens', 'wp-liste-1890'],
            'fr' => 'Un train de marchandises a tamponné l\'arrière du rapide arrêté : c\'est une collision.',
            'en' => 'A goods train ran into the rear of the stopped express: this is a collision.',
            'de' => 'Ein Güterzug fuhr auf das Ende des haltenden Schnellzugs auf: Es handelt sich um einen Zusammenstoß.',
        ],
    ],
];
