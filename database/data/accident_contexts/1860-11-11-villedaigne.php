<?php

// Contexte sourcé de la collision en gare de Villedaigne du 11 novembre 1860 (recherche du 21/11/2026).
return [
    'slug' => '1860-11-11-villedaigne',
    'checked_on' => '2026-11-21',
    'sources' => [
        'wp-liste-1860' => [
            'provider' => 'wikipedia',
            'kind' => 'encyclopedia',
            'reliability' => 'encyclopedia',
            'title' => 'Liste d\'accidents ferroviaires en France dans les années 1860',
            'publisher' => 'Wikipédia en français',
            'author' => null,
            'published' => null,
            'locator' => '1860',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1860&oldid=239152631#1860',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après La Presse du 14 novembre 1860 : sur la ligne de Cette à Toulouse, vers 20 h 40, un train de Cette à Carcassonne est garé à Villedaigne pour laisser passer l\'express venant de Toulouse quand ses trois derniers wagons, encore sur la voie principale, sont pris en écharpe ; un serre-frein tué, vingt voyageurs blessés ; l\'accident est imputé à l\'extinction des signaux d\'arrêt par la tempête.',
        ],
        'ddb-1860-11-15-karlsruher' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Montpellier, 13. Nov. Der „Messager du Midi“ meldet ein Unglück, das sich auf der Südbahn ereignet hat',
            'publisher' => 'Karlsruher Anzeiger (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1860-11-15',
            'locator' => 'p. 1',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/AX5XTXHFRBM7RPHWXR2F65MAXRUYBPZ3?issuepage=1',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Montpellier du 13 novembre, d\'après le Messager du Midi : un train omnibus de Cette à Toulouse, arrêté en gare de Villedaigne (lue « Villebaigne »), est heurté sur ses dernières voitures par l\'express de Bordeaux ; deux morts, plusieurs blessés.',
        ],
        'ddb-1860-11-16-berlinische' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Der Messager du Midi meldet ein Unglück, das sich auf der Südbahn ereignet hat',
            'publisher' => 'Berlinische Nachrichten von Staats- und gelehrten Sachen (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1860-11-16',
            'locator' => 'p. 5',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/O2GRCSCA64XEPISTIHV53IGYFTSJ52GO?issuepage=5',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Même dépêche : train omnibus de Cette à Toulouse arrêté en gare, dernières voitures renversées par l\'express de Bordeaux ; deux morts, plusieurs blessés.',
        ],
        'ddb-1860-11-17-dresdner' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Der „Messager du Midi“ von gestern meldet ein auf der Südbahn vorgekommenes Unglück',
            'publisher' => 'Dresdner Journal (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1860-11-17',
            'locator' => 'p. 2',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/YWQVWCAWDVAG3OOC7U6JCJXSOU6WNBTR?issuepage=2',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Autre version de la dépêche : le train ordinaire de Cette à Toulouse s\'engage sur une mauvaise voie à Villedaigne au moment où passe l\'express de Bordeaux, qui arrache et renverse la queue du train ; deux morts, un certain nombre de voyageurs blessés.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le soir du 11 novembre 1860, vers 20 h 40, un train de voyageurs venant de Cette était en train de se garer en gare de Villedaigne (Aude), sur la ligne de Cette à Toulouse, pour laisser passer l\'express.',
                'en' => 'On the evening of 11 November 1860, at about 8.40 pm, a passenger train from Cette was moving into a siding at Villedaigne station (Aude), on the Cette to Toulouse line, to let the express pass.',
                'de' => 'Am Abend des 11. November 1860 gegen 20.40 Uhr wurde ein Personenzug aus Cette im Bahnhof Villedaigne (Aude) an der Strecke Cette-Toulouse auf ein Nebengleis gesetzt, um den Schnellzug vorbeizulassen.',
                'sources' => ['wp-liste-1860', 'ddb-1860-11-15-karlsruher'],
            ],
        ],
        'course' => [
            [
                'fr' => 'Les dernières voitures, encore engagées sur la voie principale, ont été prises en écharpe et renversées par l\'express.',
                'en' => 'The rear carriages, still foul of the main line, were struck side-on and overturned by the express.',
                'de' => 'Die letzten Wagen, die noch auf dem Hauptgleis standen, wurden vom Schnellzug seitlich erfasst und umgeworfen.',
                'sources' => ['wp-liste-1860', 'ddb-1860-11-15-karlsruher', 'ddb-1860-11-16-berlinische', 'ddb-1860-11-17-dresdner'],
            ],
        ],
        'causes' => [
            [
                'fr' => 'Selon la liste de Wikipédia, la tempête avait éteint les signaux d\'arrêt.',
                'en' => 'According to the Wikipedia list, the storm had blown out the stop signals.',
                'de' => 'Nach der Wikipedia-Liste hatte der Sturm die Haltesignale ausgelöscht.',
                'sources' => ['wp-liste-1860'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan diffère : un serre-frein tué et vingt voyageurs blessés selon la liste de Wikipédia, deux morts et plusieurs blessés selon les dépêches reprises par la presse allemande. L\'express venait de Toulouse selon la liste, de Bordeaux selon les dépêches, et le train heurté allait à Carcassonne ou à Toulouse.',
                'en' => 'The toll differs: one brakeman killed and twenty passengers injured according to the Wikipedia list, two dead and several injured according to the dispatches carried by the German press. The express came from Toulouse according to the list, from Bordeaux according to the dispatches, and the train that was hit was bound for Carcassonne or for Toulouse.',
                'de' => 'Die Bilanz weicht ab: ein getöteter Bremser und zwanzig verletzte Reisende nach der Wikipedia-Liste, zwei Tote und mehrere Verletzte nach den von der deutschen Presse übernommenen Depeschen. Der Schnellzug kam nach der Liste aus Toulouse, nach den Depeschen aus Bordeaux, und der getroffene Zug fuhr nach Carcassonne oder nach Toulouse.',
                'sources' => ['wp-liste-1860', 'ddb-1860-11-15-karlsruher', 'ddb-1860-11-17-dresdner'],
            ],
        ],
    ],
];
