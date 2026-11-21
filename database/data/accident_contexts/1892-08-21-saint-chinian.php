<?php

// Contexte sourcé du déraillement d'un train de la ligne d'intérêt local de Béziers à Saint-Chinian sur le pont de l'Orb, entre Lignan et Maraussan, en août 1892 (recherche du 21/11/2026).
return [
    'slug' => '1892-08-21-saint-chinian',
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
            'locator' => '1892, 21 août',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1890&oldid=235357698#1892',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Source de la fiche (cinq morts et une quarantaine de blessés), d\'après Le Figaro du 23 août 1892.',
        ],
        'vdr-maraussan' => [
            'provider' => 'sites-web',
            'kind' => 'web',
            'reliability' => 'local_history',
            'title' => '1892, Les traverses du pont de Maraussan fléchissent au passage du train',
            'publisher' => 'Victimes du rail en France au XIXe siècle',
            'author' => null,
            'published' => null,
            'locator' => null,
            'url' => 'https://www.victimesdurail.fr/1892-les-traverses-du-pont-de-maraussan-flechissent-au-passage-du-train/',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Site de généalogie ; transcrit la dépêche de Béziers du 22 août 1892 publiée par Le Figaro du 23 août 1892.',
        ],
        'figaro-1892-08-23' => [
            'provider' => 'gallica',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le Figaro du 23 août 1892',
            'publisher' => 'Le Figaro',
            'author' => null,
            'published' => '1892-08-23',
            'locator' => 'p. 3',
            'url' => 'https://gallica.bnf.fr/ark:/12148/bpt6k2822676/f3',
            'access' => 'link_only',
            'consulted_on' => '2026-11-21',
            'note' => 'Lu seulement dans sa transcription.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le train était parti de Béziers à 16 h 30 sur la ligne d\'intérêt local de Béziers à Saint-Chinian, exploitée par la Compagnie de l\'Hérault. Il comptait deux wagons de fourrage en tête, puis cinq voitures de voyageurs, avec environ deux cents personnes, dont beaucoup allaient à la fête de Maraussan.',
                'en' => 'The train had left Béziers at 4.30 pm on the Béziers to Saint-Chinian local railway, run by the Compagnie de l\'Hérault. It had two fodder wagons at the front, then five passenger carriages carrying about two hundred people, many of them going to the Maraussan fête.',
                'de' => 'Der Zug war um 16.30 Uhr in Béziers auf der Lokalbahn Béziers-Saint-Chinian der Compagnie de l\'Hérault abgefahren. Er bestand vorn aus zwei Futterwagen und dahinter fünf Personenwagen mit etwa zweihundert Reisenden, von denen viele zum Fest in Maraussan wollten.',
                'sources' => ['vdr-maraussan', 'wp-liste-1890'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Sur le pont de l\'Orb, à 800 mètres de la gare de Lignan, une traverse a cédé et la voie s\'est écartée. La machine a déraillé au niveau de la seconde pile et s\'est enfoncée dans le tablier, en partie hors de la cage du pont. Les wagons de fourrage se sont brisés contre elle et trois voitures ont été défoncées.',
                'en' => 'On the bridge over the Orb, 800 metres from Lignan station, a sleeper gave way and the rails spread. The engine derailed at the second pier and sank into the deck, partly breaking out of the bridge girders. The fodder wagons were smashed against it and three carriages were wrecked.',
                'de' => 'Auf der Brücke über den Orb, 800 Meter vom Bahnhof Lignan entfernt, gab eine Schwelle nach und die Spur weitete sich. Die Lokomotive entgleiste am zweiten Pfeiler und brach in die Fahrbahn ein, teils über das Brückengitter hinaus. Die Futterwagen zerschellten an ihr, drei Personenwagen wurden zerstört.',
                'sources' => ['vdr-maraussan', 'wp-liste-1890'],
            ],
            [
                'fr' => 'L\'accident a eu lieu en pleine campagne, loin des habitations. Les habitants de Lignan et de Maraussan ont aidé le personnel à chercher les victimes dans l\'obscurité, au milieu des débris, avant leur transport à l\'hôpital de Béziers. Le correspondant du Figaro jugeait la compagnie mal préparée à ces secours.',
                'en' => 'The accident happened in open country, far from any houses. People from Lignan and Maraussan helped the staff search for the victims in the dark among the wreckage, before they were taken to Béziers hospital. Le Figaro\'s correspondent considered the company poorly prepared for the rescue.',
                'de' => 'Der Unfall ereignete sich auf freiem Feld, fern von Häusern. Einwohner von Lignan und Maraussan halfen dem Personal, in der Dunkelheit in den Trümmern nach den Opfern zu suchen, bevor diese ins Krankenhaus von Béziers gebracht wurden. Der Korrespondent des Figaro hielt die Gesellschaft für schlecht auf die Hilfe vorbereitet.',
                'sources' => ['vdr-maraussan'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le Figaro comptait une cinquantaine de victimes, dont cinq morts ; le garde-frein du train est mort en arrivant à Béziers. Les noms de vingt-six blessés étaient connus le lendemain, et Wikipédia retient une quarantaine de blessés.',
                'en' => 'Le Figaro counted about fifty casualties, five of them dead; the train\'s brakeman died on arrival in Béziers. The names of twenty-six injured were known the next day, and Wikipedia gives about forty injured.',
                'de' => 'Le Figaro zählte etwa fünfzig Opfer, darunter fünf Tote; der Bremser des Zuges starb bei der Ankunft in Béziers. Am nächsten Tag waren die Namen von sechsundzwanzig Verletzten bekannt; Wikipedia nennt etwa vierzig Verletzte.',
                'sources' => ['vdr-maraussan', 'wp-liste-1890'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Le correspondant du Figaro attribuait le déraillement au mauvais état des traverses en bois du pont. Le parquet de Béziers s\'est rendu sur place le lendemain matin pour établir si le pont était bien entretenu.',
                'en' => 'Le Figaro\'s correspondent blamed the derailment on the poor condition of the bridge\'s wooden sleepers. The Béziers public prosecutor\'s office went to the site the next morning to establish whether the bridge had been properly maintained.',
                'de' => 'Der Korrespondent des Figaro führte die Entgleisung auf den schlechten Zustand der Holzschwellen der Brücke zurück. Die Staatsanwaltschaft von Béziers begab sich am nächsten Morgen an den Unfallort, um zu klären, ob die Brücke ordnungsgemäß unterhalten war.',
                'sources' => ['vdr-maraussan'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie dans la dépêche elle-même : cinq morts, puis « à la dernière heure, quatre morts ». Le site Victimes du rail a retrouvé l\'acte de décès de cinq victimes, dont deux que les journaux ne citent pas.',
                'en' => 'The toll varies within the dispatch itself: five dead, then "at the last hour, four dead". The Victimes du rail website has found the death certificates of five victims, two of whom the newspapers do not mention.',
                'de' => 'Die Opferzahl schwankt schon in der Depesche selbst: fünf Tote, dann „in letzter Stunde vier Tote". Die Website Victimes du rail hat die Sterbeurkunden von fünf Opfern gefunden, von denen zwei in den Zeitungen nicht genannt werden.',
                'sources' => ['vdr-maraussan'],
            ],
            [
                'fr' => 'La fiche place l\'accident à Saint-Chinian, terminus de la ligne ; il a eu lieu à une vingtaine de kilomètres de là, sur le pont entre Lignan-sur-Orb et Maraussan, sans que la commune soit précisée.',
                'en' => 'The record places the accident at Saint-Chinian, the end of the line; it happened about twenty kilometres away, on the bridge between Lignan-sur-Orb and Maraussan, without the commune being specified.',
                'de' => 'Der Eintrag verortet den Unfall in Saint-Chinian, dem Endpunkt der Strecke; er ereignete sich jedoch etwa zwanzig Kilometer entfernt auf der Brücke zwischen Lignan-sur-Orb und Maraussan, ohne dass die Gemeinde genannt wird.',
                'sources' => ['vdr-maraussan', 'wp-liste-1890'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'kind',
            'value' => 'derailment',
            'sources' => ['vdr-maraussan', 'wp-liste-1890'],
            'fr' => 'Une traverse du pont a cédé, la voie s\'est écartée et la machine a déraillé : c\'est un déraillement, le pont lui-même ne s\'est pas effondré.',
            'en' => 'A sleeper on the bridge gave way, the rails spread and the engine derailed: this is a derailment, as the bridge itself did not collapse.',
            'de' => 'Eine Schwelle der Brücke gab nach, die Spur weitete sich und die Lokomotive entgleiste: Es handelt sich um eine Entgleisung, die Brücke selbst stürzte nicht ein.',
        ],
    ],
];
