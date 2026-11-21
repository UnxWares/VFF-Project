<?php

// Contexte sourcé du déraillement du viaduc de Dannemarie du 20 juillet 1872 (recherche du 21/11/2026).
return [
    'slug' => '1872-07-20-maubeuge',
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
            'note' => 'Entrée de la liste, d\'après Le Temps du 22 juillet 1872 : le 20 juillet 1872, à 11 h, sur le réseau d\'Alsace-Lorraine, le train-poste de Mulhouse à Paris déraille sur le viaduc de Dannemarie ; la locomotive et le tender tombent dans le vide, le reste du convoi demeure sur le viaduc ; le mécanicien et le chauffeur sont tués.',
        ],
        'ddb-1872-07-23-reichsanzeiger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Vorgestern Vormittag gegen 11 Uhr ist zwischen den Stationen Altkirchen und Dammerkirchen der Eilzug Basel-Belfort entgleist',
            'publisher' => 'Deutscher Reichsanzeiger und Preußischer Staatsanzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1872-07-23',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/5Y5QTLGPU3R3NYYIKWUKQAJXILQPDZFT?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : « avant-hier » vers 11 h, l\'express de Bâle à Belfort déraille entre les stations d\'Altkirch et de Dannemarie ; la machine, le tender et le fourgon tombent du talus, les autres voitures restent sur la voie ; le mécanicien est tué, le chauffeur gravement ébouillanté par la vapeur, un voyageur légèrement blessé ; la cause n\'est pas encore connue.',
        ],
        'ddb-1872-07-24-spenersche' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Am Samstag Morgen ist der von Mülhausen nach Paris gehende Postzug auf dem Viaduct von Dammerkirch (Dannemarie) entgleist',
            'publisher' => 'Spenersche Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1872-07-24',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/UXVFOW2D2TF562LH3HMIIBR3DDGTTLFF?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : samedi matin, le train-poste de Mulhouse à Paris déraille sur le viaduc de Dannemarie ; la locomotive et le tender tombent dans le vide, les autres voitures sont renversées sur le côté mais restent sur la plate-forme ; le mécanicien est tué sur le coup, le chauffeur grièvement blessé, le reste du personnel légèrement ; quelques voyageurs n\'auraient que de légères contusions.',
        ],
        'ddb-1872-07-24-schwaebischer-merkur' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dammerkirch den 20. Juli. Dem Niederrh. Kur. wird von hier berichtet',
            'publisher' => 'Schwäbischer Merkur (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1872-07-24',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/YDR6DIHOWLP2JJR2EMC4DCXWWAJBF6OK?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Lettre de Dannemarie du 20 juillet, d\'après le Niederrheinischer Kurier : l\'express de Paris parti de Mulhouse à 10 h 20 a déraillé « aujourd\'hui » entre la gare de Dannemarie et le grand viaduc ; un témoin décrit la locomotive couchée sur le dos au pied du remblai, juste avant le viaduc, le tender et deux fourgons brisés, les voitures de voyageurs séparées du reste du train, et le trafic interrompu.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 20 juillet 1872, vers 11 h, l\'express de Mulhouse à Paris, parti de Mulhouse à 10 h 20, déraille juste avant le viaduc de Dannemarie (Haut-Rhin, alors en Alsace-Lorraine annexée), entre Altkirch et Dannemarie.',
                'en' => 'On 20 July 1872, at about 11 am, the Mulhouse to Paris express, which had left Mulhouse at 10.20 am, derailed just before the Dannemarie viaduct (Haut-Rhin, then in annexed Alsace-Lorraine), between Altkirch and Dannemarie.',
                'de' => 'Am 20. Juli 1872 entgleiste gegen 11 Uhr der um 10.20 Uhr in Mülhausen abgefahrene Schnellzug Mülhausen-Paris kurz vor dem Viadukt von Dammerkirch (Dannemarie, Haut-Rhin, damals im annektierten Elsass-Lothringen) zwischen Altkirch und Dammerkirch.',
                'sources' => ['wp-liste-1870', 'ddb-1872-07-24-schwaebischer-merkur', 'ddb-1872-07-23-reichsanzeiger', 'ddb-1872-07-24-spenersche'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive, le tender et les fourgons tombent du remblai ; la machine finit sur le dos, roues en l\'air. Les voitures de voyageurs, séparées du reste du train, restent sur la plate-forme, certaines renversées sur le côté. La cause n\'était pas encore connue quelques jours plus tard.',
                'en' => 'The locomotive, tender and vans fell down the embankment; the engine ended up on its back, wheels in the air. The passenger carriages, separated from the rest of the train, stayed on the formation, some of them overturned on their side. The cause was still unknown a few days later.',
                'de' => 'Lokomotive, Tender und Packwagen stürzten die Böschung hinab; die Maschine lag schließlich auf dem Rücken, die Räder nach oben. Die vom übrigen Zug getrennten Personenwagen blieben auf dem Bahnkörper, einige auf die Seite geworfen. Die Ursache war einige Tage später noch nicht geklärt.',
                'sources' => ['ddb-1872-07-24-schwaebischer-merkur', 'ddb-1872-07-24-spenersche', 'ddb-1872-07-23-reichsanzeiger'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le mécanicien est tué sur le coup. Selon la presse allemande, le chauffeur est grièvement blessé, ébouillanté par la vapeur, et un voyageur légèrement blessé ; la liste de Wikipédia compte le chauffeur parmi les morts.',
                'en' => 'The driver was killed outright. According to the German press, the stoker was seriously injured, scalded by steam, and one passenger slightly hurt; the Wikipedia list counts the stoker among the dead.',
                'de' => 'Der Lokomotivführer war sofort tot. Nach der deutschen Presse wurde der Heizer durch Wasserdampf schwer verbrüht und ein Reisender leicht verletzt; die Wikipedia-Liste zählt den Heizer zu den Toten.',
                'sources' => ['ddb-1872-07-23-reichsanzeiger', 'ddb-1872-07-24-spenersche', 'wp-liste-1870'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : deux agents tués selon la liste de Wikipédia, un seul selon la presse allemande, qui donne le chauffeur pour blessé. Le jour aussi : 20 juillet (samedi) dans la liste, la lettre de Dannemarie et la Spenersche Zeitung, mais 21 juillet, ou dimanche, dans d\'autres journaux allemands, dont le Reichsanzeiger du 23 qui écrit « avant-hier ». Le train est l\'express de Mulhouse à Paris pour la plupart des sources, l\'express de Bâle à Belfort pour le Reichsanzeiger.',
                'en' => 'The toll differs: two railwaymen killed according to the Wikipedia list, only one according to the German press, which reports the stoker injured. So does the day: 20 July (a Saturday) in the list, the letter from Dannemarie and the Spenersche Zeitung, but 21 July, or Sunday, in other German papers, including the Reichsanzeiger of the 23rd, which writes "the day before yesterday". The train is the Mulhouse to Paris express in most sources, the Basel to Belfort express in the Reichsanzeiger.',
                'de' => 'Die Bilanz weicht ab: zwei getötete Bedienstete nach der Wikipedia-Liste, nur einer nach der deutschen Presse, die den Heizer als verletzt meldet. Ebenso der Tag: 20. Juli (ein Samstag) in der Liste, im Brief aus Dammerkirch und in der Spenerschen Zeitung, aber 21. Juli oder Sonntag in anderen deutschen Blättern, darunter der Reichsanzeiger vom 23., der „vorgestern“ schreibt. Der Zug ist in den meisten Quellen der Schnellzug Mülhausen-Paris, im Reichsanzeiger der Eilzug Basel-Belfort.',
                'sources' => ['wp-liste-1870', 'ddb-1872-07-23-reichsanzeiger', 'ddb-1872-07-24-spenersche', 'ddb-1872-07-24-schwaebischer-merkur'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '68068',
            'sources' => ['wp-liste-1870', 'ddb-1872-07-24-spenersche', 'ddb-1872-07-24-schwaebischer-merkur'],
            'fr' => 'La liste de Wikipédia et la presse allemande de juillet 1872 placent le déraillement au viaduc de Dannemarie (Haut-Rhin), sur la ligne de Mulhouse à Belfort ; la fiche le plaçait à Maubeuge (Nord).',
            'en' => 'The Wikipedia list and the German press of July 1872 place the derailment at the Dannemarie viaduct (Haut-Rhin), on the Mulhouse to Belfort line; the record placed it at Maubeuge (Nord).',
            'de' => 'Die Wikipedia-Liste und die deutsche Presse vom Juli 1872 verorten die Entgleisung am Viadukt von Dannemarie (Haut-Rhin) an der Strecke Mülhausen-Belfort; der Eintrag verortete sie in Maubeuge (Nord).',
        ],
    ],
];
