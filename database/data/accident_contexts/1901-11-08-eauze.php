<?php

// Contexte sourcé de la collision d'un train de marchandises et d'une machine venue de Condom près d'Eauze, ligne de Port-Sainte-Marie à Riscle, novembre 1901 (recherche du 22/11/2026).
return [
    'slug' => '1901-11-08-eauze',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1901' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1901',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Novembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1901&oldid=241345719#Novembre',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 8 novembre 1901, d\'après Le Temps des 10 et 16 novembre et du 5 janvier 1902 : sur la voie unique de Port-Sainte-Marie à Riscle, près de la gare d\'Eauze, vers 12 h 30, un train de marchandises venant d\'Eauze rencontre une machine haut-le-pied venant de Condom ; quatre cheminots tués ; en janvier 1902, les chefs de gare d\'Eauze et de Bretagne-d\'Armagnac condamnés avec sursis pour homicide involontaire.',
        ],
        'elux-1901-11-09-independance' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dernière heure, service spécial',
            'publisher' => 'L\'Indépendance luxembourgeoise (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1901-11-09',
            'locator' => 'p. 3',
            'url' => 'https://persist.lu/ark:70795/42xr41/pages/3/articles/DTL45',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche Havas : à Eauze, la machine de renfort du train n° 154, venant de Condom, a tamponné le train n° 1372 ; les machines se sont télescopées, plusieurs wagons détruits ; trois morts et deux blessés.',
        ],
        'elux-1901-11-13-independance' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Le tamponnement d\'Eauze',
            'publisher' => 'L\'Indépendance luxembourgeoise (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1901-11-13',
            'locator' => 'p. 2',
            'url' => 'https://persist.lu/ark:70795/pqkqpc/pages/2/articles/DTL70',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Suite, par télégramme d\'un correspondant : enquête du parquet de Condom sur les responsabilités ; bandes télégraphiques des gares d\'Eauze et de Bretagne saisies ; les deux chefs de gare remplacés à leur poste ; obsèques de quatre des victimes en présence de la compagnie du Midi, du parquet et du conseil municipal.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 8 novembre 1901, sur la voie unique de Port-Sainte-Marie à Riscle (compagnie du Midi), près de la gare d\'Eauze, un train de marchandises parti d\'Eauze a rencontré une machine venue de Condom, la machine de renfort du train n° 154. Les deux machines se sont télescopées et plusieurs wagons ont été détruits.',
                'en' => 'On 8 November 1901, on the single-track Port-Sainte-Marie to Riscle line (Midi company), near Eauze station, a goods train that had left Eauze met an engine coming from Condom, the banking engine of train no. 154. The two engines telescoped and several wagons were wrecked.',
                'de' => 'Am 8. November 1901 traf auf der eingleisigen Strecke Port-Sainte-Marie-Riscle (Midi-Bahn) nahe dem Bahnhof Eauze ein in Eauze abgefahrener Güterzug auf eine aus Condom kommende Lokomotive, die Vorspannlok des Zuges Nr. 154. Die beiden Maschinen schoben sich ineinander, mehrere Wagen wurden zerstört.',
                'sources' => ['wp-liste-1901', 'elux-1901-11-09-independance'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Quatre cheminots ont été tués. Le premier télégramme ne comptait que trois morts et deux blessés ; cinq jours plus tard, la presse rapporte les obsèques de quatre victimes.',
                'en' => 'Four railwaymen were killed. The first telegram counted only three dead and two injured; five days later the press reported the funerals of four victims.',
                'de' => 'Vier Eisenbahner wurden getötet. Das erste Telegramm nannte nur drei Tote und zwei Verletzte; fünf Tage später berichtete die Presse von der Beerdigung von vier Opfern.',
                'sources' => ['wp-liste-1901', 'elux-1901-11-09-independance', 'elux-1901-11-13-independance'],
            ],
        ],
        'aftermath' => [
            [
                'fr' => 'Le parquet de Condom a ouvert une enquête ; les bandes télégraphiques des gares d\'Eauze et de Bretagne-d\'Armagnac ont été saisies et les deux chefs de gare remplacés. Selon la liste de Wikipédia, le tribunal correctionnel de Condom les a condamnés en janvier 1902 pour homicide involontaire, à trois mois et à un mois de prison avec sursis.',
                'en' => 'The Condom public prosecutor opened an inquiry; the telegraph tapes of Eauze and Bretagne-d\'Armagnac stations were seized and both stationmasters were replaced. According to the Wikipedia list, the Condom criminal court convicted them of manslaughter in January 1902, with suspended sentences of three months and one month.',
                'de' => 'Die Staatsanwaltschaft Condom leitete eine Untersuchung ein; die Telegrafenstreifen der Bahnhöfe Eauze und Bretagne-d\'Armagnac wurden beschlagnahmt, die beiden Bahnhofsvorsteher abgelöst. Nach der Wikipedia-Liste verurteilte das Strafgericht Condom sie im Januar 1902 wegen fahrlässiger Tötung zu drei Monaten und einem Monat Haft auf Bewährung.',
                'sources' => ['elux-1901-11-13-independance', 'wp-liste-1901'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Heure : vers 12 h 30 selon la liste, « ce matin » selon la dépêche Havas.',
                'en' => 'Time: about 12.30 pm according to the list, "this morning" according to the Havas dispatch.',
                'de' => 'Uhrzeit: gegen 12.30 Uhr nach der Liste, „heute Morgen“ nach der Havas-Meldung.',
                'sources' => ['wp-liste-1901', 'elux-1901-11-09-independance'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 4,
            'sources' => ['wp-liste-1901', 'elux-1901-11-13-independance'],
            'fr' => 'Quatre cheminots tués selon la liste de Wikipédia (Le Temps) et la presse luxembourgeoise du 13 novembre 1901 (obsèques de quatre victimes) ; la première dépêche en comptait trois, écart écrit ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Four railwaymen killed according to the Wikipedia list (Le Temps) and the Luxembourg press of 13 November 1901 (funerals of four victims); the first dispatch counted three, a difference recorded; the record gave no number of deaths.',
            'de' => 'Vier Eisenbahner getötet nach der Wikipedia-Liste (Le Temps) und der luxemburgischen Presse vom 13. November 1901 (Beerdigung von vier Opfern); die erste Meldung nannte drei, die Abweichung ist vermerkt; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'kind',
            'value' => 'collision',
            'sources' => ['wp-liste-1901', 'elux-1901-11-09-independance'],
            'fr' => 'Rencontre d\'un train de marchandises et d\'une machine sur la voie unique, selon la liste de Wikipédia et la dépêche Havas ; la fiche indiquait « autre ».',
            'en' => 'A goods train and an engine met on the single track, according to the Wikipedia list and the Havas dispatch; the record gave "other".',
            'de' => 'Zusammentreffen eines Güterzugs und einer Lokomotive auf dem eingleisigen Abschnitt nach der Wikipedia-Liste und der Havas-Meldung; der Eintrag nannte „Sonstiges“.',
        ],
    ],
];
