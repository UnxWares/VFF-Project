<?php

// Contexte sourcé de l'accident de passage à niveau près de la gare de Châteaudun, ligne de Brétigny à Tours, de décembre 1910 (recherche du 21/11/2026).
return [
    'slug' => '1910-12-25-chateaudun',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1910' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1910',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Décembre',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1910&oldid=233727870#D%C3%A9cembre',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée du 25 décembre 1910, d\'après Le Figaro du 26 décembre : vers 11 h 30, peu avant la gare de Châteaudun, sur la ligne de Paris à Tours par Châteaudun, l\'express Orléans - Le Mans heurte sur un passage à niveau resté ouvert une voiture transportant neuf personnes qui se rendaient à un baptême ; six tués, trois grièvement blessés.',
        ],
        'ddb-1910-12-29-annen' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Sechs Personen überfahren',
            'publisher' => 'Annener Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-12-29',
            'locator' => 'p. 7',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/CQMHDYKR7YVK3KLIKSIW6BHNZVG6H42M?issuepage=7',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : dimanche matin, une voiture occupée par neuf personnes a été happée par un train au passage d\'un passage à niveau, à deux kilomètres de Châteaudun ; six occupants tués, les trois autres plus ou moins grièvement blessés.',
        ],
        'ddb-1910-12-28-hohenstein' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Bei Chateaudun in Frankreich',
            'publisher' => 'Hohenstein-Ernstthaler Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1910-12-28',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/JGNGXFL4HNAYP5H4FYGFAWUKHZTKZJQE?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Brève : près de Châteaudun, une voiture a été écrasée par un train ; six morts, deux blessés graves.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le dimanche 25 décembre 1910, vers 11 h 30, une voiture transportant neuf personnes qui se rendaient à un baptême franchissait un passage à niveau resté ouvert, à environ deux kilomètres de la gare de Châteaudun, sur la ligne de Paris à Tours par Châteaudun.',
                'en' => 'On Sunday 25 December 1910, at about 11.30 am, a carriage carrying nine people on their way to a christening was crossing a level crossing that had been left open, about two kilometres from Châteaudun station on the Paris to Tours line via Châteaudun.',
                'de' => 'Am Sonntag, dem 25. Dezember 1910, gegen 11.30 Uhr überquerte ein Wagen mit neun Personen auf dem Weg zu einer Taufe einen offen gebliebenen Bahnübergang etwa zwei Kilometer vor dem Bahnhof Châteaudun an der Strecke Paris-Tours über Châteaudun.',
                'sources' => ['wp-liste-1910', 'ddb-1910-12-29-annen'],
            ],
        ],
        'course' => [
            [
                'fr' => 'L\'express d\'Orléans au Mans a heurté la voiture sur le passage à niveau.',
                'en' => 'The Orléans to Le Mans express struck the carriage on the crossing.',
                'de' => 'Der Expresszug Orléans-Le Mans erfasste den Wagen auf dem Übergang.',
                'sources' => ['wp-liste-1910', 'ddb-1910-12-29-annen', 'ddb-1910-12-28-hohenstein'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Six des neuf occupants ont été tués et les trois autres blessés, grièvement selon la liste de Wikipédia.',
                'en' => 'Six of the nine occupants were killed and the other three injured, seriously according to the Wikipedia list.',
                'de' => 'Sechs der neun Insassen wurden getötet, die drei anderen verletzt, nach der Wikipedia-Liste schwer.',
                'sources' => ['wp-liste-1910', 'ddb-1910-12-29-annen'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Une brève du Hohenstein-Ernstthaler Anzeiger compte deux blessés graves au lieu de trois blessés.',
                'en' => 'A short item in the Hohenstein-Ernstthaler Anzeiger counts two seriously injured instead of three injured.',
                'de' => 'Eine Kurzmeldung im Hohenstein-Ernstthaler Anzeiger zählt zwei Schwerverletzte statt drei Verletzter.',
                'sources' => ['ddb-1910-12-28-hohenstein', 'ddb-1910-12-29-annen'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 6,
            'sources' => ['wp-liste-1910', 'ddb-1910-12-29-annen', 'ddb-1910-12-28-hohenstein'],
            'fr' => 'Six tués selon la liste de Wikipédia (Le Figaro du 26 décembre 1910) et la presse allemande ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Six killed according to the Wikipedia list (Le Figaro of 26 December 1910) and the German press; the record gave no number of deaths.',
            'de' => 'Sechs Tote nach der Wikipedia-Liste (Le Figaro vom 26. Dezember 1910) und der deutschen Presse; der Eintrag nannte keine Zahl von Toten.',
        ],
        [
            'field' => 'injuries',
            'value' => 3,
            'sources' => ['wp-liste-1910', 'ddb-1910-12-29-annen'],
            'fr' => 'Trois blessés selon la liste de Wikipédia et l\'Annener Zeitung du 29 décembre 1910 ; la fiche ne donnait pas de nombre de blessés.',
            'en' => 'Three injured according to the Wikipedia list and the Annener Zeitung of 29 December 1910; the record gave no number of injured.',
            'de' => 'Drei Verletzte nach der Wikipedia-Liste und der Annener Zeitung vom 29. Dezember 1910; der Eintrag nannte keine Zahl von Verletzten.',
        ],
    ],
];
