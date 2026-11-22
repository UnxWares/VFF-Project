<?php

// Contexte sourcé du déraillement près de la gare de Cernay, ligne de Châtellerault à Loudun, juillet 1935 (recherche du 22/11/2026).
return [
    'slug' => '1935-07-01-cernay',
    'checked_on' => '2026-11-22',
    'sources' => [
        'wp-liste-1935' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France en 1935',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => 'Juillet',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_en_1935&oldid=236841665#Juillet',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Entrée du 1er juillet 1935, d\'après Le Matin du 2 juillet : sur la ligne de Châtellerault à Loudun, peu après la gare de Cernay, le fourgon de tête d\'un train de voyageurs pour Loudun déraille et se renverse, entraînant les trois voitures suivantes ; un mort et un blessé.',
        ],
        'ddb-1935-07-02-general-anzeiger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugunfall in Frankreich',
            'publisher' => 'General-Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1935-07-02',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/CWNUA7MESXRZCZUTJVCEJDZD4T2MU6V3?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Dépêche d\'agence : un train a déraillé en gare de Cernay ; un agent du chemin de fer tué, une femme des environs blessée ; dégâts importants, circulation interrompue.',
        ],
        'ddb-1935-07-02-rheinisch-bergische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Zugunfall in Frankreich (Paris, 1. Juli)',
            'publisher' => 'Rheinisch-Bergische Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1935-07-02',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/LB7VCQ7CLR52GRAGZ5XRS7KZJP2CI2WW?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-22',
            'note' => 'Même dépêche de Paris du 1er juillet : déraillement en gare de Cernay, un agent tué, une blessée ; d\'autres versions situent Cernay près de Châtellerault.',
        ],
    ],
    'sections' => [
        'course' => [
            [
                'fr' => 'Le 1er juillet 1935, sur la ligne de Châtellerault à Loudun, le fourgon de tête d\'un train de voyageurs pour Loudun a déraillé à la gare de Cernay ou juste après. Il s\'est renversé et a entraîné les trois voitures suivantes. La circulation a été interrompue.',
                'en' => 'On 1 July 1935, on the Châtellerault to Loudun line, the leading van of a passenger train for Loudun derailed at or just beyond Cernay station. It overturned and dragged the next three carriages with it. Traffic was halted.',
                'de' => 'Am 1. Juli 1935 entgleiste auf der Strecke Châtellerault-Loudun der vordere Gepäckwagen eines Personenzugs nach Loudun im Bahnhof Cernay oder kurz dahinter. Er stürzte um und riss die drei folgenden Wagen mit. Der Verkehr wurde unterbrochen.',
                'sources' => ['wp-liste-1935', 'ddb-1935-07-02-general-anzeiger', 'ddb-1935-07-02-rheinisch-bergische'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Un agent du chemin de fer a été tué et une femme blessée.',
                'en' => 'A railway employee was killed and a woman was injured.',
                'de' => 'Ein Bahnbediensteter wurde getötet, eine Frau verletzt.',
                'sources' => ['wp-liste-1935', 'ddb-1935-07-02-general-anzeiger'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Lieu : « peu après la gare de Cernay » selon la liste, « en gare de Cernay » selon la dépêche allemande.',
                'en' => 'Place: "shortly after Cernay station" according to the list, "in Cernay station" according to the German dispatch.',
                'de' => 'Ort: „kurz nach dem Bahnhof Cernay“ nach der Liste, „auf dem Bahnhof von Cernay“ nach der deutschen Meldung.',
                'sources' => ['wp-liste-1935', 'ddb-1935-07-02-general-anzeiger'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'commune_insee',
            'value' => '86047',
            'sources' => ['wp-liste-1935', 'ddb-1935-07-02-general-anzeiger'],
            'fr' => 'Déraillement à la gare de Cernay ou juste après, selon la liste de Wikipédia et la dépêche de Paris ; Loudun est la destination du train.',
            'en' => 'Derailment at or just beyond Cernay station, according to the Wikipedia list and the Paris dispatch; Loudun is where the train was going.',
            'de' => 'Entgleisung im Bahnhof Cernay oder kurz dahinter, nach der Wikipedia-Liste und der Pariser Meldung; Loudun ist das Ziel des Zuges.',
        ],
    ],
];
