<?php

// Contexte sourcé de la collision du train de voyageurs n° 32 et d'une locomotive sous le pont des Marots, près de la gare de Troyes, en octobre 1871 (recherche du 21/11/2026).
return [
    'slug' => '1871-10-04-troyes',
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
            'locator' => '1871, 4 octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1871',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (un mort et huit blessés), d\'après Le Temps et Le Figaro du 6 octobre 1871.',
        ],
        'vdr-troyes' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1871, Choc violent sous le pont des Marots près de Troyes',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1871-choc-violent-sous-le-pont-des-marots-pres-de-troyes/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit l\'article de L\'Aube repris par Le Temps du 6 octobre 1871.',
        ],
        'le-temps-1871-10-06' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Temps du 6 octobre 1871',
            'publisher' => 'Le Temps',
            'author' => null,
            'published' => '1871-10-06',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k224775f/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'À midi, le train de voyageurs n° 32 venant de Paris, qui avait une heure de retard, allait entrer en gare de Troyes. Sous le pont des Marots, à la jonction de la voie du dépôt des machines, il a heurté une locomotive qui venait s\'aiguiller et dont le mécanicien n\'avait pas vu les signaux d\'arrêt.',
                'en' => 'At midday passenger train no. 32 from Paris, running an hour late, was about to enter Troyes station. Under the Marots bridge, at the junction with the line to the engine shed, it struck a locomotive that was coming out to be switched and whose driver had not seen the stop signals.',
                'de' => 'Mittags war der Personenzug Nr. 32 aus Paris mit einer Stunde Verspätung im Begriff, in den Bahnhof Troyes einzufahren. Unter der Brücke Pont des Marots, an der Einmündung des Gleises vom Lokomotivschuppen, stieß er mit einer Lokomotive zusammen, die gerade umgesetzt werden sollte und deren Lokführer die Haltesignale nicht gesehen hatte.',
                'sources' => ['vdr-troyes', 'wp-liste-1870'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Selon L\'Aube, aucun voyageur n\'a été atteint. Le mécanicien de la locomotive, pris entre les tôles du garde-fou et rejeté sur la voie, a été grièvement blessé, et son état était jugé désespéré. Wikipédia compte un mort, ce mécanicien, et huit blessés.',
                'en' => 'According to L\'Aube, no passenger was hurt. The locomotive driver, caught between the plates of the guard rail and thrown onto the track, was seriously injured, and his condition was considered hopeless. Wikipedia counts one dead, this driver, and eight injured.',
                'de' => 'Laut L\'Aube wurde kein Reisender verletzt. Der Lokführer der Lokomotive, zwischen die Bleche des Geländers geklemmt und auf das Gleis geschleudert, wurde schwer verletzt; sein Zustand galt als hoffnungslos. Wikipedia zählt einen Toten, diesen Lokführer, und acht Verletzte.',
                'sources' => ['vdr-troyes', 'wp-liste-1870'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'La voie a été dégagée aussitôt, et le service n\'a pas été interrompu.',
                'en' => 'The line was cleared at once, and services were not interrupted.',
                'de' => 'Das Gleis wurde sofort geräumt, und der Betrieb wurde nicht unterbrochen.',
                'sources' => ['vdr-troyes'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'L\'Aube situe l\'accident un mardi, soit le 3 octobre 1871 ; Wikipédia donne le 4. Le site Victimes du rail n\'a trouvé aucun acte de décès du mécanicien à l\'état civil de Troyes.',
                'en' => 'L\'Aube places the accident on a Tuesday, that is 3 October 1871; Wikipedia gives the 4th. The Victimes du rail website found no death record for the driver in the Troyes civil registers.',
                'de' => 'L\'Aube verlegt den Unfall auf einen Dienstag, also den 3. Oktober 1871; Wikipedia nennt den 4. Die Website Victimes du rail fand im Standesamt von Troyes keine Sterbeurkunde des Lokführers.',
                'sources' => ['vdr-troyes', 'wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [],
];
