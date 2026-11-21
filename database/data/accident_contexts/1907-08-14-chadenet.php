<?php

// Contexte sourcé de la dérive d'un train de minerai sur le viaduc du Beyrac, ligne de Mende à La Bastide, d'août 1907 (recherche du 21/11/2026).
return [
    'slug' => '1907-08-14-chadenet',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1907' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1907',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1907&oldid=238956681#Août',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 14 août 1907, d\'après Le Temps du 16 août : après une avarie de sa machine vers Larzalier, un train de minerai repart en arrière et déraille sur le viaduc du Beyrac ; le mécanicien et le chef de train sont tués, le chauffeur grièvement blessé ; deux wagons vont jusqu\'à un passage à niveau près de Bagnols - Chadenet, où la garde-barrière arrête à temps le train Mende - La Bastide.',
        ],
        'ddb-1907-08-17-hamburg' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Unfälle im Eisenbahnbetriebe',
            'publisher' => 'Hamburger Fremdenblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1907-08-17',
            'locator' => 'p. 14',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/N4I4J5LW3FQNVJQ23HQT4GFNLXJNGWWH?issuepage=14',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 16 août, d\'après le Lokal-Anzeiger : le jeudi, faute de freins, un train de plomb des mines de Bleymard, locomotive en queue, a dévalé la pente entre Bagnols et Badaroux jusqu\'au pont de Beyrac, où le troisième wagon a déraillé ; deux wagons ont roulé encore cinq kilomètres ; le chef de train et le mécanicien sont morts avant l\'arrivée des médecins.',
        ],
        'ddb-1907-08-21-ratingen' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunfälle in Ungarn und Frankreich',
            'publisher' => 'Ratinger Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1907-08-21',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/RASFM7BHPEBV6OWFPSHLDGLKB573WSUB?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même récit, plus complet : le personnel grièvement blessé a attendu des heures un médecin, avec l\'aide de quelques moissonneurs ; la femme du garde-voie a averti à temps un train venant de Mende, qui aurait sinon heurté les deux wagons en dérive.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Un train de cinq wagons chargés de minerai de plomb des mines de Bleymard, la locomotive en queue, est parti en arrière sur la pente entre Bagnols et Badaroux, sur la ligne de Mende à La Bastide. Il a passé la gare d\'Allenc puis la courbe du viaduc du Beyrac, où le troisième wagon a déraillé. La locomotive et les deux derniers wagons se sont écrasés contre lui.',
                'en' => 'A train of five wagons loaded with lead ore from the Bleymard mines, with the engine at the rear, ran back down the gradient between Bagnols and Badaroux on the Mende to La Bastide line. It passed Allenc station and the curve on the Beyrac viaduct, where the third wagon derailed. The engine and the last two wagons smashed into it.',
                'de' => 'Ein Zug aus fünf mit Bleierz aus den Gruben von Bleymard beladenen Wagen, die Lokomotive am Schluss, rollte auf dem Gefälle zwischen Bagnols und Badaroux an der Strecke Mende-La Bastide zurück. Er durchfuhr den Bahnhof Allenc und die Kurve auf dem Viadukt von Beyrac, wo der dritte Wagen entgleiste. Die Lokomotive und die beiden letzten Wagen prallten gegen ihn.',
                'sources' => ['wp-liste-1907', 'ddb-1907-08-17-hamburg', 'ddb-1907-08-21-ratingen'],
            ],
            [
                'fr' => 'Les deux premiers wagons ont continué leur course. La garde-barrière d\'un passage à niveau proche de la gare de Bagnols - Chadenet a arrêté à temps le train de voyageurs venant de Mende, qui allait les rencontrer.',
                'en' => 'The first two wagons kept rolling. The crossing keeper at a level crossing near Bagnols - Chadenet station stopped the passenger train from Mende in time, before it could meet them.',
                'de' => 'Die beiden ersten Wagen rollten weiter. Die Schrankenwärterin eines Bahnübergangs nahe dem Bahnhof Bagnols-Chadenet hielt den aus Mende kommenden Personenzug rechtzeitig an, bevor er auf sie traf.',
                'sources' => ['wp-liste-1907', 'ddb-1907-08-21-ratingen'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'La liste de Wikipédia parle d\'une avarie de la machine dans la montée vers Larzalier, la presse allemande d\'un défaut des freins.',
                'en' => 'The Wikipedia list speaks of a failure of the engine on the climb towards Larzalier, the German press of a brake failure.',
                'de' => 'Die Wikipedia-Liste spricht von einem Schaden an der Maschine in der Steigung nach Larzalier, die deutsche Presse von versagenden Bremsen.',
                'sources' => ['wp-liste-1907', 'ddb-1907-08-17-hamburg'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien et le chef de train ont été tués. Selon la liste de Wikipédia, le chauffeur a été grièvement blessé et le garde-frein, qui avait sauté, s\'en est sorti indemne.',
                'en' => 'The driver and the guard were killed. According to the Wikipedia list, the stoker was badly hurt and the brakesman, who had jumped off, was unharmed.',
                'de' => 'Lokomotivführer und Zugführer wurden getötet. Nach der Wikipedia-Liste wurde der Heizer schwer verletzt, der abgesprungene Bremser blieb unverletzt.',
                'sources' => ['wp-liste-1907', 'ddb-1907-08-17-hamburg'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Date : la liste de Wikipédia donne le 14 août 1907 ; la dépêche de Paris du 16 août situe l\'accident « le jeudi », soit le 15 août.',
                'en' => 'Date: the Wikipedia list gives 14 August 1907; the Paris dispatch of 16 August places the accident "on Thursday", which was 15 August.',
                'de' => 'Datum: Die Wikipedia-Liste nennt den 14. August 1907; die Pariser Meldung vom 16. August legt den Unfall auf „Donnerstag“, also den 15. August.',
                'sources' => ['wp-liste-1907', 'ddb-1907-08-17-hamburg'],
            ],
            [
                'fr' => 'Selon la presse allemande, les deux premiers wagons se sont arrêtés après cinq kilomètres sur une rupture d\'essieu ; selon la liste, au passage à niveau près de Bagnols - Chadenet.',
                'en' => 'According to the German press, the first two wagons stopped after five kilometres when an axle broke; according to the list, at the level crossing near Bagnols - Chadenet.',
                'de' => 'Nach der deutschen Presse blieben die beiden ersten Wagen nach fünf Kilometern wegen eines Achsbruchs stehen, nach der Liste am Bahnübergang bei Bagnols-Chadenet.',
                'sources' => ['ddb-1907-08-21-ratingen', 'wp-liste-1907'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1907', 'ddb-1907-08-17-hamburg'],
            'fr' => 'Mécanicien et chef de train tués selon la liste de Wikipédia (Le Temps du 16 août 1907) et la dépêche allemande du 16 ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Driver and guard killed according to the Wikipedia list (Le Temps of 16 August 1907) and the German dispatch of the 16th; the record gave no number of deaths.',
            'de' => 'Lokomotivführer und Zugführer getötet nach der Wikipedia-Liste (Le Temps vom 16. August 1907) und der deutschen Meldung vom 16.; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
