<?php

// Contexte sourcé de la collision d'un omnibus venant de Paris et d'un train de marchandises en gare de Dijon, octobre 1908 (recherche du 22/11/2026).
return [
    'slug' => '1908-10-05-dijon',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1908' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1908',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Octobre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1908&oldid=233618338#Octobre',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 5 octobre 1908, d\'après Le Figaro du 6 octobre : en gare de Dijon, vers 22 h, les travaux d\'agrandissement empêchant les itinéraires habituels, un omnibus bondé venant de Paris est dirigé par erreur sur une voie occupée par un train de marchandises en manœuvre ; un accrocheur est écrasé, de nombreux voyageurs blessés.',
        ],
        'elux-1908-10-06-independance' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dijon, 5 octobre',
            'publisher' => 'L\'Indépendance luxembourgeoise (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1908-10-06',
            'locator' => 'p. 3',
            'url' => 'https://persist.lu/ark:70795/6h347d/pages/3/articles/DTL93',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche datée de Dijon le 5 octobre : accident « hier » à 22 h en gare de Dijon ; le train n° 115, parti de Paris à 12 h 05, a pris en écharpe un train de marchandises ; un mort et trois blessés.',
        ],
        'letemps-jdg-1908-10-07' => [
            'provider' => 'letempsarchives',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Collision de trains à Dijon, Journal de Genève du 7 octobre 1908, page 2',
            'publisher' => 'Journal de Genève (Le Temps Archives)',
            'author' => null,
            'published' => '1908-10-07',
            'locator' => 'p. 2',
            'url' => 'https://www.letempsarchives.ch/page/JDG_1908_10_07/2',
            'access' => 'open',
            'consulted_on' => '2026-11-23',
            'note' => 'Nouvelles diverses, « Collision de trains à Dijon » : en gare et non sur la ligne de Lyon, « Dimanche à 10 heures » (donc le 4 octobre), l\'omnibus 115 de Paris prend en écharpe un train de marchandises d\'Épinac sur une voie dont l\'aiguillage provisoire était mal réglé ; un accrocheur meurt, plusieurs blessés dont trois connus. Faits relevés par VFF, aucun texte recopié.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Vers 22 h, en gare de Dijon, le train n° 115, un omnibus parti de Paris à 12 h 05, a pris en écharpe un train de marchandises en manœuvre. Selon la liste de Wikipédia, les travaux d\'agrandissement de la gare obligeaient à changer les itinéraires, et l\'omnibus a été dirigé par erreur sur la voie occupée.',
                'en' => 'At about 10 pm, in Dijon station, train no. 115, a stopping train that had left Paris at 12.05 pm, struck a shunting goods train side-on. According to the Wikipedia list, enlargement works at the station meant the usual routes could not be used, and the stopping train was sent onto the occupied track by mistake.',
                'de' => 'Gegen 22 Uhr rammte im Bahnhof Dijon der Zug Nr. 115, ein um 12.05 Uhr in Paris abgefahrener Personenzug, seitlich einen rangierenden Güterzug. Nach der Wikipedia-Liste ließen die Umbauarbeiten am Bahnhof die üblichen Fahrwege nicht zu, und der Personenzug wurde irrtümlich auf das besetzte Gleis geleitet.',
                'sources' => ['wp-liste-1908', 'elux-1908-10-06-independance', 'letemps-jdg-1908-10-07'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un accrocheur a été écrasé ; il a été sorti de dessous un wagon encore vivant mais est mort peu après, selon le Journal de Genève. La dépêche compte trois blessés, la liste et le Journal de Genève de nombreux voyageurs blessés, dont trois seulement étaient connus le 7 octobre.',
                'en' => 'A coupler was crushed; he was pulled alive from under a wagon but died shortly afterwards, according to the Journal de Genève. The dispatch counts three injured, the list and the Journal de Genève many injured passengers, of whom only three were known on 7 October.',
                'de' => 'Ein Rangierer wurde zerquetscht; nach dem Journal de Genève wurde er lebend unter einem Wagen geborgen, starb aber kurz darauf. Die Meldung nennt drei Verletzte, die Liste und das Journal de Genève zahlreiche verletzte Reisende, von denen am 7. Oktober nur drei bekannt waren.',
                'sources' => ['wp-liste-1908', 'elux-1908-10-06-independance', 'letemps-jdg-1908-10-07'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Date : la liste de Wikipédia donne le 5 octobre 1908 ; la dépêche datée de Dijon le 5 octobre parle d\'un accident survenu « hier », soit le 4, et le Journal de Genève du 7 octobre le place le dimanche à 10 heures, soit aussi le dimanche 4 octobre.',
                'en' => 'Date: the Wikipedia list gives 5 October 1908; the dispatch dated Dijon, 5 October, speaks of an accident "yesterday", which would be the 4th, and the Journal de Genève of 7 October places it on Sunday at 10 o\'clock, which is also Sunday 4 October.',
                'de' => 'Datum: Die Wikipedia-Liste nennt den 5. Oktober 1908; die aus Dijon vom 5. Oktober datierte Meldung spricht von einem Unfall „gestern“, also am 4., und das Journal de Genève vom 7. Oktober setzt ihn auf Sonntag um 10 Uhr, also ebenfalls Sonntag, den 4. Oktober.',
                'sources' => ['wp-liste-1908', 'elux-1908-10-06-independance', 'letemps-jdg-1908-10-07'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1908', 'elux-1908-10-06-independance', 'letemps-jdg-1908-10-07'],
            'fr' => 'Un mort, un accrocheur, selon la liste de Wikipédia (Le Figaro du 6 octobre 1908) et la dépêche de Dijon parue dans L\'Indépendance luxembourgeoise ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'One dead, a coupler, according to the Wikipedia list (Le Figaro of 6 October 1908) and the Dijon dispatch printed in L\'Indépendance luxembourgeoise; the record gave no number of deaths.',
            'de' => 'Ein Toter, ein Rangierer, nach der Wikipedia-Liste (Le Figaro vom 6. Oktober 1908) und der in L\'Indépendance luxembourgeoise gedruckten Meldung aus Dijon; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
