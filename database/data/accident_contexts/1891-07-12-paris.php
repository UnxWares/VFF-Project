<?php

// Contexte sourcé du tamponnement du train 46 de Lille par le Club Train dans l'avant-gare de Paris-Nord, en juillet 1891 (recherche du 21/11/2026).
return [
    'slug' => '1891-07-12-paris',
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
            'locator' => '1891, 12 juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1891',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (un mort et une quinzaine de blessés), d\'après Le Figaro du 14 juillet 1891 ; donne le jugement du 16 février 1892.',
        ],
        'vdr-la-chapelle' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1891, Accident à La Chapelle (banlieue Paris), vers gare du Nord',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1891-accident-a-la-chapelle-paris/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit Le Petit Courrier de Bar-sur-Seine du 17 juillet 1891.',
        ],
        'petit-courrier-bar-sur-seine-1891-07-17' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Petit Courrier de Bar-sur-Seine du 17 juillet 1891',
            'publisher' => 'Le Petit Courrier de Bar-sur-Seine',
            'author' => null,
            'published' => '1891-07-17',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k4462087z/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
        'figaro-1892-02-17' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 17 février 1892',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1892-02-17',
            'locator' => 'p. 5',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2817739/f5.image',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Jugement de l\'aiguilleur, cité par Wikipédia ; non lu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Pour la fête du 14 juillet, la Compagnie du Nord avait triplé la plupart de ses trains, dont le train 46 de Lille. Ce dimanche soir, ce train et le Club Train, train de luxe en correspondance avec le paquebot de Douvres à Calais, avaient tous deux quatorze minutes de retard ; le Club Train devait arriver à la gare du Nord à 22 h 55, six minutes après le train de Lille.',
                'en' => 'For the 14 July holiday the Compagnie du Nord had tripled most of its trains, including train 46 from Lille. That Sunday evening both this train and the Club Train, a luxury train connecting with the Dover to Calais steamer, were fourteen minutes late; the Club Train was due at the Gare du Nord at 10.55 pm, six minutes after the Lille train.',
                'de' => 'Zum Fest des 14. Juli hatte die Compagnie du Nord die meisten ihrer Züge verdreifacht, darunter den Zug 46 aus Lille. An diesem Sonntagabend hatten dieser Zug und der Club Train, ein Luxuszug mit Anschluss an das Dampfschiff Dover-Calais, beide vierzehn Minuten Verspätung; der Club Train sollte um 22.55 Uhr, sechs Minuten nach dem Zug aus Lille, in der Gare du Nord ankommen.',
                'sources' => ['vdr-la-chapelle', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'À 800 mètres du quai d\'arrivée, à la hauteur des rues Marcadet et Doudeauville, le train de Lille a trouvé la voie fermée et s\'est arrêté. Le mécanicien du Club Train a aperçu son arrière et a renversé la vapeur, sans pouvoir éviter le choc. Sa machine a soulevé le fourgon de queue, retombé sur la voiture de deuxième classe qui le précédait, et une troisième voiture a été brisée.',
                'en' => 'Eight hundred metres from the arrival platform, level with rue Marcadet and rue Doudeauville, the Lille train found the line closed and stopped. The Club Train\'s driver saw its rear and reversed steam, but could not avoid the impact. His engine lifted the rear van, which fell onto the second-class carriage in front of it, and a third carriage was wrecked.',
                'de' => 'Achthundert Meter vor dem Ankunftsbahnsteig, auf Höhe der Rue Marcadet und der Rue Doudeauville, fand der Zug aus Lille das Gleis gesperrt und hielt an. Der Lokführer des Club Train sah dessen Zugende und gab Gegendampf, konnte den Aufprall aber nicht verhindern. Seine Lokomotive hob den letzten Packwagen an, der auf den davor laufenden Wagen zweiter Klasse fiel, und ein dritter Wagen wurde zerstört.',
                'sources' => ['vdr-la-chapelle', 'wp-liste-1890'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les deux voyageurs de la voiture écrasée, un employé de la Compagnie et sa femme, ont été dégagés au bout de deux heures, les jambes broyées, et conduits à l\'hôpital Lariboisière, comme le conducteur du fourgon. Quinze autres blessés ne paraissaient pas en danger ; les voyageurs du Club Train n\'avaient que des contusions. Wikipédia compte un mort.',
                'en' => 'The two passengers in the crushed carriage, a company employee and his wife, were freed after two hours with crushed legs and taken to Lariboisière hospital, as was the guard from the van. Fifteen other injured did not seem to be in danger; the Club Train\'s passengers had only bruises. Wikipedia counts one dead.',
                'de' => 'Die beiden Reisenden des zerdrückten Wagens, ein Angestellter der Gesellschaft und seine Frau, wurden nach zwei Stunden mit zermalmten Beinen geborgen und wie der Zugbegleiter des Packwagens ins Krankenhaus Lariboisière gebracht. Fünfzehn weitere Verletzte schienen nicht in Gefahr; die Reisenden des Club Train erlitten nur Prellungen. Wikipedia zählt einen Toten.',
                'sources' => ['vdr-la-chapelle', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon l\'enquête du commissaire spécial, l\'aiguilleur de la passerelle avait demandé par téléphone à celui de la cabine située au-delà du pont Marcadet si les trains de Lille étaient passés ; sur sa réponse affirmative, il a fermé la voie alors que le train 46 n\'était pas encore arrivé. Selon Wikipédia, compte tenu de sa surcharge de travail, l\'aiguilleur responsable n\'a été condamné le 16 février 1892 qu\'à 25 francs d\'amende.',
                'en' => 'According to the special commissioner\'s inquiry, the pointsman on the footbridge had asked the one in the cabin beyond the Marcadet bridge by telephone whether the Lille trains had passed; on being told yes, he closed the line although train 46 had not yet arrived. According to Wikipedia, in view of his heavy workload the pointsman responsible was fined only 25 francs on 16 February 1892.',
                'de' => 'Nach den Ermittlungen des Sonderkommissars hatte der Weichensteller auf der Fußgängerbrücke beim Stellwerk jenseits der Marcadet-Brücke telefonisch nachgefragt, ob die Züge aus Lille durch seien; auf die Bejahung hin sperrte er das Gleis, obwohl der Zug 46 noch nicht angekommen war. Laut Wikipedia wurde der verantwortliche Weichensteller wegen seiner Arbeitsüberlastung am 16. Februar 1892 nur zu 25 Francs Geldstrafe verurteilt.',
                'sources' => ['vdr-la-chapelle', 'wp-liste-1890'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le Petit Courrier de Bar-sur-Seine, cinq jours après l\'accident, ne parle que de blessés ; Wikipédia compte un mort et situe le choc à 23 h 10.',
                'en' => 'Le Petit Courrier de Bar-sur-Seine, five days after the accident, speaks only of injured people; Wikipedia counts one dead and puts the impact at 11.10 pm.',
                'de' => 'Le Petit Courrier de Bar-sur-Seine spricht fünf Tage nach dem Unfall nur von Verletzten; Wikipedia zählt einen Toten und setzt den Aufprall auf 23.10 Uhr an.',
                'sources' => ['vdr-la-chapelle', 'wp-liste-1890'],
            ],
            [
                'fr' => 'La carte place l\'accident à la gare de Paris-Nord, position de la gare citée par la liste de Wikipédia. Le récit de la presse transcrit par le site de généalogie le situe à 800 mètres du quai d\'arrivée, à la hauteur des rues Marcadet et Doudeauville (La Chapelle) : la gare du Nord est la gare de rattachement, pas le lieu du choc.',
                'en' => 'The map places the accident at Paris-Nord station, the position of the station named by the Wikipedia list. The press account transcribed by the genealogy site puts it 800 metres from the arrival platform, level with rue Marcadet and rue Doudeauville (La Chapelle): Paris-Nord is the station it is linked to, not the place of the impact.',
                'de' => 'Die Karte setzt den Unfall an den Bahnhof Paris-Nord, die Position des in der Wikipedia-Liste genannten Bahnhofs. Der vom Genealogie-Portal wiedergegebene Pressebericht verortet ihn 800 Meter vom Ankunftsbahnsteig entfernt, auf Höhe der Rue Marcadet und der Rue Doudeauville (La Chapelle): Paris-Nord ist der zugeordnete Bahnhof, nicht der Ort des Zusammenstoßes.',
                'sources' => ['vdr-la-chapelle', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [],
];
