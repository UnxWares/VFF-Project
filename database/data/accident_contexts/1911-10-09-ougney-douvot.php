<?php

// Contexte sourcé du tamponnement de deux trains de marchandises près de la gare d'Ougney-Douvot, ligne de Dole à Belfort, d'octobre 1911 (recherche du 21/11/2026).
return [
    'slug' => '1911-10-09-ougney-douvot',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1911' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1911',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1911&oldid=241541118#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 9 octobre 1911, d\'après Le Figaro du 10 octobre : près de la gare d\'Ougney-Douvot, sur la ligne Dijon - Belfort, vers 2 h, un train de marchandises venant de Baume-les-Dames s\'arrête à temps devant des rochers tombés sur la voie, puis est heurté par le convoi suivant ; les deux chefs de train sont tués, d\'autres cheminots blessés.',
        ],
        'ddb-1911-10-10-tag' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zusammenstoß zweier Güterzüge in Frankreich',
            'publisher' => 'Der Tag (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1911-10-10',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/F3EUT4G7BDZ3OJC5SE6OHYUDMD5L44EE?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : un train de marchandises parti de Baume-les-Dames vers Besançon trouve la voie barrée par un énorme rocher près d\'Ougney et se couvre par des pétards ; dix minutes plus tard, un second train le heurte et brise dix à douze wagons ; le chauffeur et le chef de train du premier tués, le chauffeur et le mécanicien du second grièvement blessés ; le parquet de Baume enquête sur une possible malveillance.',
        ],
        'ddb-1911-10-12-niederrhein' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück in Frankreich',
            'publisher' => 'Niederrheinisches Tageblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1911-10-12',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/BAWPML5IIMBGH54ALFZU56DDBZ3BTNXD?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : même récit, le second train arrive une quinzaine de minutes après l\'arrêt du premier ; le chauffeur et le chef de train du premier train tués, le chauffeur et le chef de train du second grièvement blessés ; on croit que le rocher a été roulé sur les rails par des malfaiteurs.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Dans la nuit du 8 au 9 octobre 1911, vers 2 h, un train de marchandises parti de Baume-les-Dames vers Besançon a trouvé la voie barrée par un gros rocher près de la gare d\'Ougney-Douvot, sur la ligne de Dole à Belfort. Il s\'est arrêté et s\'est couvert par des pétards posés 500 mètres en arrière. Dix à quinze minutes plus tard, le train de marchandises suivant l\'a heurté et dix à douze wagons ont été brisés.',
                'en' => 'During the night of 8 to 9 October 1911, at about 2 am, a goods train that had left Baume-les-Dames for Besançon found the line blocked by a large rock near Ougney-Douvot station on the Dole to Belfort line. It stopped and was protected by detonators laid 500 metres behind it. Ten to fifteen minutes later the following goods train ran into it and ten to twelve wagons were smashed.',
                'de' => 'In der Nacht vom 8. auf den 9. Oktober 1911 gegen 2 Uhr fand ein in Baume-les-Dames nach Besançon abgefahrener Güterzug die Strecke Dole-Belfort nahe dem Bahnhof Ougney-Douvot durch einen großen Felsblock versperrt. Er hielt und wurde durch Knallkapseln 500 Meter rückwärts gedeckt. Zehn bis fünfzehn Minuten später fuhr der folgende Güterzug auf, zehn bis zwölf Wagen wurden zertrümmert.',
                'sources' => ['ddb-1911-10-10-tag', 'ddb-1911-10-12-niederrhein', 'wp-liste-1911'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Deux agents du premier train ont été tués. Deux agents du second train ont été grièvement blessés.',
                'en' => 'Two railwaymen of the first train were killed. Two men of the second train were seriously injured.',
                'de' => 'Zwei Bedienstete des ersten Zuges wurden getötet, zwei vom zweiten Zug schwer verletzt.',
                'sources' => ['ddb-1911-10-10-tag', 'ddb-1911-10-12-niederrhein', 'wp-liste-1911'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le parquet de Baume-les-Dames et la compagnie ont ouvert une enquête pour savoir si le rocher était tombé seul ou avait été placé sur les rails par malveillance.',
                'en' => 'The Baume-les-Dames public prosecutor and the railway opened an inquiry into whether the rock had fallen or had been put on the rails deliberately.',
                'de' => 'Die Staatsanwaltschaft von Baume-les-Dames und die Bahnverwaltung untersuchten, ob der Felsblock herabgestürzt oder böswillig auf die Schienen gelegt worden war.',
                'sources' => ['ddb-1911-10-10-tag', 'ddb-1911-10-12-niederrhein'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Selon la presse allemande, les morts sont le chauffeur et le chef de train du premier train ; la liste de Wikipédia parle des deux chefs de train. Les blessés du second train sont le chauffeur et le mécanicien dans Der Tag, le chauffeur et le chef de train dans le Niederrheinisches Tageblatt. La liste parle de rochers tombés, la presse allemande d\'un possible acte de malveillance.',
                'en' => 'According to the German press the dead were the stoker and the guard of the first train; the Wikipedia list speaks of the two guards. The injured of the second train are the stoker and the driver in Der Tag, the stoker and the guard in the Niederrheinisches Tageblatt. The list speaks of fallen rocks, the German press of possible sabotage.',
                'de' => 'Nach der deutschen Presse waren die Toten Heizer und Zugführer des ersten Zuges; die Wikipedia-Liste nennt die beiden Zugführer. Die Verletzten des zweiten Zuges sind in Der Tag Heizer und Lokomotivführer, im Niederrheinischen Tageblatt Heizer und Zugführer. Die Liste spricht von herabgestürzten Felsen, die deutsche Presse von möglicher Böswilligkeit.',
                'sources' => ['ddb-1911-10-10-tag', 'ddb-1911-10-12-niederrhein', 'wp-liste-1911'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 2,
            'sources' => ['wp-liste-1911', 'ddb-1911-10-10-tag'],
            'fr' => 'Deux agents du premier train tués selon la liste de Wikipédia (Le Figaro du 10 octobre 1911) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Two men of the first train killed according to the Wikipedia list (Le Figaro of 10 October 1911) and the German press; the record gave no number of deaths.',
            'de' => 'Zwei Bedienstete des ersten Zuges getötet nach der Wikipedia-Liste (Le Figaro vom 10. Oktober 1911) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
