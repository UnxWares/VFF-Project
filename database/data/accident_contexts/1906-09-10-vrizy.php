<?php

// Contexte sourcé du déraillement d'un train mixte en gare de Vrizy - Vandy, ligne d'Amagne - Lucquy à Revigny, septembre 1906 (recherche du 22/11/2026).
return [
    'slug' => '1906-09-10-vrizy',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1906' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1906',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Septembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1906&oldid=233590740#Septembre',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 10 septembre 1906, d\'après Le Temps du 12 septembre : sur la ligne d\'Amagne - Lucquy à Revigny, avant Vouziers, en gare de Vrizy - Vandy, vers 2 h 30, un train mixte avec deux voitures de voyageurs déraille ; le chef de train est tué.',
        ],
        'elux-1906-09-12-independance' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dernière heure, service spécial',
            'publisher' => 'L\'Indépendance luxembourgeoise (eluxemburgensia, Bibliothèque nationale du Luxembourg)',
            'author' => null,
            'published' => '1906-09-12',
            'locator' => 'p. 3',
            'url' => 'https://persist.lu/ark:70795/prkx28/pages/3/articles/DTL49',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche : sur une aiguille, un train mixte parti d\'Amagne la veille à 1 h 58 et attendu à Vouziers à 2 h 51 a déraillé en gare de Vrizy - Vandy ; deux voitures de voyageurs, celle de 1re classe brisée ; des cuirassiers de la garnison de Vouziers qui y avaient pris place n\'ont pas été blessés ; le chef de train a été tué sur le coup.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Un train mixte parti d\'Amagne à 1 h 58 et attendu à Vouziers à 2 h 51 a déraillé sur une aiguille en gare de Vrizy - Vandy, sur la ligne d\'Amagne - Lucquy à Revigny. Il comptait deux voitures de voyageurs ; celle de 1re classe a été brisée.',
                'en' => 'A mixed train that had left Amagne at 1.58 am and was due at Vouziers at 2.51 am derailed on a set of points in Vrizy - Vandy station, on the Amagne - Lucquy to Revigny line. It had two passenger carriages; the first-class one was smashed.',
                'de' => 'Ein gemischter Zug, der um 1.58 Uhr in Amagne abgefahren war und um 2.51 Uhr in Vouziers erwartet wurde, entgleiste im Bahnhof Vrizy-Vandy an der Strecke Amagne-Lucquy-Revigny auf einer Weiche. Er führte zwei Personenwagen; der Wagen erster Klasse wurde zertrümmert.',
                'sources' => ['wp-liste-1906', 'elux-1906-09-12-independance'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Le chef de train a été tué sur le coup. Des cuirassiers de la garnison de Vouziers, montés en 1re classe, n\'ont pas été blessés.',
                'en' => 'The guard was killed outright. Some cuirassiers from the Vouziers garrison, travelling in first class, were not injured.',
                'de' => 'Der Zugführer war sofort tot. Kürassiere der Garnison Vouziers, die in der ersten Klasse saßen, blieben unverletzt.',
                'sources' => ['wp-liste-1906', 'elux-1906-09-12-independance'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 1,
            'sources' => ['wp-liste-1906', 'elux-1906-09-12-independance'],
            'fr' => 'Chef de train tué selon la liste de Wikipédia (Le Temps du 12 septembre 1906) et L\'Indépendance luxembourgeoise du même jour ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Guard killed according to the Wikipedia list (Le Temps of 12 September 1906) and L\'Indépendance luxembourgeoise of the same day; the record gave no number of deaths.',
            'de' => 'Zugführer getötet nach der Wikipedia-Liste (Le Temps vom 12. September 1906) und L\'Indépendance luxembourgeoise vom selben Tag; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
