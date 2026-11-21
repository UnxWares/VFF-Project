<?php

// Contexte sourcé du déraillement d'un train spécial et de sa rencontre avec l'omnibus de Montereau en gare de Melun, en novembre 1852 (recherche du 21/11/2026).
return [
    'slug' => '1852-11-11-melun',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1850' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1850',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1852, 11 novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1850&oldid=233367195#1852',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (un mort et quatorze blessés), d\'après La Presse du 14 novembre 1852.',
        ],
        'vdr-melun' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1852, Déraillement en gare de Melun (77)',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1852-deraillement-en-gare-de-melun/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit La Presse du 14 novembre 1852 (d\'après le Napoléonien de Troyes) et le Journal des débats du 16 novembre 1852.',
        ],
        'la-presse-1852-11-14' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'La Presse du 14 novembre 1852',
            'publisher' => 'La Presse',
            'author' => null,
            'published' => '1852-11-14',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k476052m/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Selon le Journal des débats, un train spécial parti de Paris à 7 heures pour conduire le 5e bataillon de chasseurs à pied à Fontainebleau traversait la gare de Melun sans s\'arrêter vers 7 h 55. Il a heurté un wagon-écurie sur sa voie, et sa machine a déraillé en travers de la voie opposée. Au même moment arrivait l\'omnibus n° 2 de Montereau, dont la machine a heurté celle du train spécial et a déraillé avec son tender.',
                'en' => 'According to the Journal des débats, a special train that had left Paris at 7 am to take the 5th battalion of chasseurs à pied to Fontainebleau was running through Melun station without stopping at about 7.55 am. It struck a horse box on its track, and its engine derailed across the opposite line. At that moment stopping train no. 2 from Montereau arrived; its engine hit that of the special train and derailed together with its tender.',
                'de' => 'Laut dem Journal des débats fuhr ein Sonderzug, der um 7 Uhr in Paris abgefahren war, um das 5. Jägerbataillon zu Fuß nach Fontainebleau zu bringen, gegen 7.55 Uhr ohne Halt durch den Bahnhof Melun. Er rammte einen Pferdewagen auf seinem Gleis, und seine Lokomotive entgleiste quer über das Gegengleis. Im selben Augenblick traf der Personenzug Nr. 2 aus Montereau ein; dessen Lokomotive prallte gegen die des Sonderzugs und entgleiste samt Tender.',
                'sources' => ['vdr-melun'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le Journal des débats ne compte qu\'un chauffeur assez sérieusement blessé, dont l\'état s\'améliorait, et quelques voyageurs contusionnés qui ont pu repartir par le train suivant.',
                'en' => 'The Journal des débats counts only one fireman fairly seriously injured, whose condition was improving, and a few bruised passengers who were able to continue by the next train.',
                'de' => 'Das Journal des débats zählt nur einen recht schwer verletzten Heizer, dessen Zustand sich besserte, und einige Reisende mit Prellungen, die mit dem nächsten Zug weiterfahren konnten.',
                'sources' => ['vdr-melun'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le Journal des débats attribuait l\'accident à un brouillard intense qui empêchait de voir les signaux, et le présentait comme le premier sur le chemin de fer de Lyon depuis son ouverture.',
                'en' => 'The Journal des débats blamed the accident on thick fog that made the signals impossible to see, and described it as the first on the Lyon railway since it opened.',
                'de' => 'Das Journal des débats führte den Unfall auf dichten Nebel zurück, der die Signale unsichtbar machte, und bezeichnete ihn als den ersten auf der Bahn nach Lyon seit ihrer Eröffnung.',
                'sources' => ['vdr-melun'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'La Presse du 14 novembre, d\'après le Napoléonien de Troyes, et Wikipédia donnent un chauffeur tué et douze à quatorze blessés. Le Journal des débats du 16 novembre dément ces chiffres, qu\'il juge exagérés, et ne compte aucun mort ; le site Victimes du rail conclut lui aussi qu\'il n\'y a pas eu de décès.',
                'en' => 'La Presse of 14 November, quoting the Napoléonien de Troyes, and Wikipedia give one fireman killed and twelve to fourteen injured. The Journal des débats of 16 November rejects these figures as exaggerated and counts no dead; the Victimes du rail website also concludes that nobody died.',
                'de' => 'La Presse vom 14. November, nach dem Napoléonien de Troyes, und Wikipedia nennen einen getöteten Heizer und zwölf bis vierzehn Verletzte. Das Journal des débats vom 16. November weist diese Zahlen als übertrieben zurück und zählt keinen Toten; auch die Website Victimes du rail kommt zu dem Schluss, dass es keinen Todesfall gab.',
                'sources' => ['vdr-melun', 'wp-liste-1850'],
            ],
        ],
    ],
    'corrections' => [],
];
