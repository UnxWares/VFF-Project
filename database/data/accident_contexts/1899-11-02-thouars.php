<?php

// Contexte sourcé de la collision sur le viaduc de Thouars du 2 novembre 1899 (recherche du 21/11/2026).
return [
    'slug' => '1899-11-02-thouars',
    'checked_on' => '2026-11-21',
    'sources' => [
        'vdr-thouars' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1899, Tamponnement sur le viaduc de Thouars (79)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1899-thouars/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Petit Parisien du 3 novembre 1899.',
        ],
        'petit-parisien-1899-11-03' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Parisien du 3 novembre 1899',
            'publisher' => 'Le Petit Parisien',
            'author' => null,
            'published' => '1899-11-03',
            'locator' => 'p. 1',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k5190785/f1',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
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
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche : express dédoublé Bordeaux-Paris pris en écharpe à Thouars par un train de marchandises, deux morts et une quinzaine de blessés ; cite Le Petit Parisien du 3 novembre 1899.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le train de Bordeaux était un express spécial dédoublé qui avait quitté Bordeaux le 1er novembre au soir. Arrivé à Thouars avec quarante-cinq minutes de retard, il s\'est remis en marche après un court arrêt et a parcouru environ 1 500 mètres avant l\'aiguille qui précède l\'entrée du pont de Thouars. La Compagnie des chemins de fer de l\'État a communiqué le bilan le lendemain matin.',
                'en' => 'The Bordeaux train was a special relief express that had left Bordeaux on the evening of 1 November. Reaching Thouars forty-five minutes late, it set off again after a short stop and had gone about 1,500 metres to the points before the entrance to the Thouars bridge. The State railways company announced the toll the next morning.',
                'de' => 'Der Zug aus Bordeaux war ein doppelter Sonderschnellzug, der am Abend des 1. November Bordeaux verlassen hatte. In Thouars mit fünfundvierzig Minuten Verspätung eingetroffen, setzte er sich nach kurzem Halt wieder in Bewegung und hatte etwa 1 500 Meter bis zur Weiche vor der Einfahrt auf die Brücke von Thouars zurückgelegt. Die Staatsbahngesellschaft gab die Bilanz am folgenden Morgen bekannt.',
                'sources' => ['vdr-thouars', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Le 2 novembre 1899 vers deux heures du matin, l\'express a été pris en écharpe à la bifurcation de Thouars par un train de marchandises qui se dirigeait vers Les Sables-d\'Olonne ou Bressuire selon les dépêches. La machine du train de marchandises a heurté les deux voitures de première classe placées après le fourgon, qui ont été complètement brisées. La machine de l\'express a déraillé et est tombée au bas du remblai en entraînant les débris du premier wagon.',
                'en' => 'On 2 November 1899 at about two in the morning, the express was hit side-on at the Thouars junction by a goods train heading for Les Sables-d\'Olonne or Bressuire according to the dispatches. The goods train\'s engine struck the two first-class carriages placed after the luggage van, which were completely smashed. The express engine derailed and fell down the embankment, dragging the wreckage of the first carriage with it.',
                'de' => 'Am 2. November 1899 gegen zwei Uhr morgens wurde der Schnellzug am Abzweig von Thouars von einem Güterzug, der laut den Depeschen nach Les Sables-d\'Olonne oder Bressuire fuhr, seitlich erfasst. Die Lokomotive des Güterzugs traf die beiden Wagen der ersten Klasse hinter dem Gepäckwagen, die völlig zerbrachen. Die Lokomotive des Schnellzugs entgleiste und stürzte die Böschung hinab, wobei sie die Trümmer des ersten Wagens mitriss.',
                'sources' => ['vdr-thouars', 'wp-liste-1890'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux agents du train de marchandises, son mécanicien et son chef de train, ont été tués. Les blessés comptaient des agents des deux trains et des voyageurs, dont le député de la Charente Cunéo d\'Ornano, tombé de six mètres par-dessus un talus et qui avait les deux jambes fracturées. Les blessés ont été soignés à l\'hôpital de Thouars, puis les plus atteints ramenés à Paris par un fourgon aménagé en wagon-hôpital.',
                'en' => 'Two staff of the goods train, its driver and its guard, were killed. The injured included staff of both trains and passengers, among them the deputy for the Charente, Cunéo d\'Ornano, who fell six metres over an embankment and had both legs fractured. The injured were treated at Thouars hospital, then the most seriously hurt were brought back to Paris in a luggage van fitted out as a hospital car.',
                'de' => 'Zwei Bedienstete des Güterzugs, sein Lokführer und sein Zugführer, wurden getötet. Zu den Verletzten zählten Bedienstete beider Züge und Reisende, darunter der Abgeordnete der Charente Cunéo d\'Ornano, der sechs Meter tief über eine Böschung stürzte und sich beide Beine brach. Die Verletzten wurden im Krankenhaus von Thouars behandelt, die am schwersten Verletzten dann in einem als Lazarettwagen eingerichteten Gepäckwagen nach Paris zurückgebracht.',
                'sources' => ['vdr-thouars'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon les premières constatations rapportées par Le Petit Parisien, le mécanicien de l\'express aurait franchi les signaux d\'arrêt, fermés, placés à l\'entrée de la gare. Le journal précise qu\'on ne savait pas encore à qui incombait la responsabilité de l\'accident.',
                'en' => 'According to the first findings reported by Le Petit Parisien, the express driver is said to have passed the closed stop signals at the station entrance. The newspaper adds that it was not yet known who was responsible for the accident.',
                'de' => 'Nach den ersten Feststellungen, über die Le Petit Parisien berichtet, soll der Lokführer des Schnellzugs die geschlossenen Haltesignale am Bahnhofseingang überfahren haben. Die Zeitung fügt hinzu, man wisse noch nicht, wer für den Unfall verantwortlich sei.',
                'sources' => ['vdr-thouars', 'wp-liste-1890'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le directeur des chemins de fer de l\'État s\'est rendu sur place avec des représentants du ministère des Travaux publics, et le déblaiement a duré jusqu\'au lendemain soir. Le ministre des Travaux publics attendait les blessés à la gare Montparnasse à leur arrivée à Paris, peu après midi.',
                'en' => 'The director of the State railways went to the site with representatives of the ministry of public works, and clearing took until the following evening. The minister of public works awaited the injured at Montparnasse station when they reached Paris, shortly after noon.',
                'de' => 'Der Direktor der Staatsbahnen begab sich mit Vertretern des Ministeriums für öffentliche Arbeiten an die Unfallstelle, und die Räumung dauerte bis zum nächsten Abend. Der Minister für öffentliche Arbeiten erwartete die Verletzten bei ihrer Ankunft kurz nach Mittag am Bahnhof Montparnasse in Paris.',
                'sources' => ['vdr-thouars'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan des blessés varie dans l\'article : la note de la compagnie compte cinq voyageurs légèrement blessés, un télégramme de Bressuire dix blessés grièvement, et la liste nominative une quinzaine de personnes ; la liste de Wikipédia retient une quinzaine de blessés. Un télégramme ne mentionne que la mort du mécanicien du train de marchandises, alors que la note de la compagnie et la liste nominative comptent deux morts.',
                'en' => 'The injury toll varies within the article: the company\'s note counts five passengers slightly injured, a telegram from Bressuire ten seriously injured, and the named list about fifteen people; the Wikipedia list gives about fifteen injured. One telegram mentions only the death of the goods train driver, whereas the company\'s note and the named list count two dead.',
                'de' => 'Die Verletztenzahl schwankt innerhalb des Artikels: Die Notiz der Gesellschaft zählt fünf leicht verletzte Reisende, ein Telegramm aus Bressuire zehn Schwerverletzte und die namentliche Liste etwa fünfzehn Personen; die Wikipedia-Liste nennt etwa fünfzehn Verletzte. Ein Telegramm erwähnt nur den Tod des Lokführers des Güterzugs, während die Notiz der Gesellschaft und die namentliche Liste zwei Tote zählen.',
                'sources' => ['vdr-thouars', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
    ],
];
