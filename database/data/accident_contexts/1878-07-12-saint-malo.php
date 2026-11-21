<?php

// Contexte sourcé du déraillement du train de Paris à Saint-Malo entre Vitré et Châteaubourg, du 12 juillet 1878 (recherche du 21/11/2026).
return [
    'slug' => '1878-07-12-saint-malo',
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
            'locator' => '1878',
            'url' => 'https://fr.wikipedia.org/w/index.php?title=Liste_d%27accidents_ferroviaires_en_France_dans_les_ann%C3%A9es_1870&oldid=235357691#1878',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Entrée de la liste, d\'après Le Figaro du 15 juillet 1878 : le train parti le matin de Paris-Saint-Lazare pour Saint-Malo déraille vers 18 h entre Vitré et Châteaubourg ; le chauffeur et le conducteur sont tués, ainsi que 6 des 25 voyageurs, et 12 personnes sont blessées.',
        ],
        'ddb-1878-07-16-hamburger' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Dépêche de Paris du 13 juillet (chemin de fer de l\'Ouest)',
            'publisher' => 'Hamburgischer Correspondent (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1878-07-16',
            'locator' => 'p. 13',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/I2IAO2V7FGKAMUKYVPVPD7DYVCDNTFV7?issuepage=13',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Dépêche de Paris du 13 juillet : un train de voyageurs de la ligne de Bretagne de la Compagnie de l\'Ouest déraille entre Vitré et Châteaubourg et tombe du remblai avec tous ses wagons ; trois voyageurs, un chauffeur et un conducteur tués, neuf blessés.',
        ],
        'ddb-1878-07-18-boersen' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Eisenbahnunglück auf der Westlinie zwischen Vitré und Châteaubourg',
            'publisher' => 'Berliner Börsen-Zeitung (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1878-07-18',
            'locator' => 'p. 7',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/BYWKQPI47P4ZYQH5OBZSHSJBH6ICVA7U?issuepage=7',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Récit tiré de journaux français : le train parti de Paris le matin déraille dans une courbe à grande vitesse, les voitures sont projetées au-delà de la locomotive, le chauffeur reste coincé sous la machine ; premier bilan de cinq morts (trois voyageurs, un conducteur, un chauffeur) et neuf blessés.',
        ],
        'ddb-1878-07-23-grossenhain' => [
            'provider' => 'presse',
            'kind' => 'press',
            'reliability' => 'press',
            'title' => 'Récit du Journal d\'Ille-et-Vilaine sur l\'accident entre Vitré et Châteaubourg',
            'publisher' => 'Großenhainer Unterhaltungs- & Anzeigeblatt (presse allemande numérisée, Deutsches Zeitungsportal)',
            'author' => null,
            'published' => '1878-07-23',
            'locator' => 'p. 3',
            'url' => 'https://www.deutsche-digitale-bibliothek.de/newspaper/item/CXKVSHULVRZFT4XCKHPWWXLZSK6FM7TZ?issuepage=3',
            'access' => 'open',
            'consulted_on' => '2026-11-21',
            'note' => 'Reprise du Journal d\'Ille-et-Vilaine : dépêche reçue à Rennes peu avant 18 h, train de secours envoyé sur place ; la machine se retrouve derrière le train, sur 25 voyageurs six sont tués et douze gravement blessés, le chauffeur meurt coincé sous le tender.',
        ],
    ],
    'sections' => [
        'circumstances' => [
            [
                'fr' => 'Le 12 juillet 1878, le train de voyageurs de Paris à Saint-Malo, parti dans la matinée, circulait sur la ligne de Bretagne de la Compagnie de l\'Ouest entre Vitré et Châteaubourg (Ille-et-Vilaine). Le lieu de l\'accident est donc entre ces deux gares, non à Saint-Malo, destination du train.',
                'en' => 'On 12 July 1878 the passenger train from Paris to Saint-Malo, which had left in the morning, was running on the Compagnie de l\'Ouest Brittany line between Vitré and Châteaubourg (Ille-et-Vilaine). The accident therefore happened between those two stations, not at Saint-Malo, the train\'s destination.',
                'de' => 'Am 12. Juli 1878 fuhr der am Vormittag in Paris abgefahrene Personenzug nach Saint-Malo auf der Bretagne-Linie der Compagnie de l\'Ouest zwischen Vitré und Châteaubourg (Ille-et-Vilaine). Der Unfall ereignete sich also zwischen diesen beiden Bahnhöfen, nicht in Saint-Malo, dem Ziel des Zuges.',
                'sources' => ['wp-liste-1870', 'ddb-1878-07-16-hamburger', 'ddb-1878-07-18-boersen'],
            ],
        ],
        'course' => [
            [
                'fr' => 'La locomotive a quitté les rails dans une courbe, alors que le train roulait à grande vitesse. Les voitures ont été projetées par-dessus la machine, qui s\'est retrouvée derrière elles, et plusieurs ont été détruites. Le télégraphe étant lui aussi endommagé, les secours ont tardé : la nouvelle est arrivée à Rennes peu avant 18 h et un train de secours est parti avec des ouvriers et des médecins. La cause n\'était pas connue au moment des premiers récits.',
                'en' => 'The locomotive left the rails in a curve while the train was running at speed. The coaches were thrown over the engine, which ended up behind them, and several were wrecked. The telegraph was damaged too, so help was slow: word reached Rennes shortly before 6 pm and a relief train left with workers and doctors. The cause was not known when the first accounts were written.',
                'de' => 'Die Lokomotive sprang in einer Kurve aus den Schienen, während der Zug mit hoher Geschwindigkeit fuhr. Die Wagen wurden über die Maschine hinausgeschleudert, die dann hinter ihnen lag, und mehrere wurden zertrümmert. Da auch der Telegraf beschädigt war, kam die Hilfe spät: Die Nachricht erreichte Rennes kurz vor 18 Uhr, und ein Hilfszug fuhr mit Arbeitern und Ärzten ab. Die Ursache war bei den ersten Berichten nicht bekannt.',
                'sources' => ['ddb-1878-07-18-boersen', 'ddb-1878-07-23-grossenhain'],
            ],
        ],
        'victims' => [
            [
                'fr' => 'Les premières dépêches comptaient cinq morts (trois voyageurs, un conducteur, un chauffeur) et neuf blessés. Le récit du Journal d\'Ille-et-Vilaine, repris le 23 juillet, donne six voyageurs tués sur 25, douze gravement blessés, et le chauffeur mort coincé sous le tender ; avec le conducteur, cela fait huit morts, comme dans la liste de Wikipédia.',
                'en' => 'The first dispatches counted five dead (three passengers, a guard, a stoker) and nine injured. The account of the Journal d\'Ille-et-Vilaine, reprinted on 23 July, gives six of 25 passengers killed, twelve seriously injured, and the stoker dead, trapped under the tender; with the guard that makes eight dead, as in the Wikipedia list.',
                'de' => 'Die ersten Depeschen zählten fünf Tote (drei Reisende, ein Schaffner, ein Heizer) und neun Verletzte. Der am 23. Juli nachgedruckte Bericht des Journal d\'Ille-et-Vilaine nennt sechs getötete von 25 Reisenden, zwölf Schwerverletzte und den unter dem Tender eingeklemmt gestorbenen Heizer; mit dem Schaffner sind das acht Tote, wie in der Wikipedia-Liste.',
                'sources' => ['ddb-1878-07-16-hamburger', 'ddb-1878-07-18-boersen', 'ddb-1878-07-23-grossenhain', 'wp-liste-1870'],
            ],
        ],
        'disagreements' => [
            [
                'fr' => 'Le bilan varie selon la date de la source : cinq morts et neuf ou dix blessés dans les dépêches des 13 et 16 juillet, huit morts et douze blessés dans le récit du 23 juillet et dans la liste de Wikipédia.',
                'en' => 'The toll varies with the date of the source: five dead and nine or ten injured in the dispatches of 13 and 16 July, eight dead and twelve injured in the account of 23 July and in the Wikipedia list.',
                'de' => 'Die Bilanz hängt vom Datum der Quelle ab: fünf Tote und neun oder zehn Verletzte in den Depeschen vom 13. und 16. Juli, acht Tote und zwölf Verletzte im Bericht vom 23. Juli und in der Wikipedia-Liste.',
                'sources' => ['ddb-1878-07-16-hamburger', 'ddb-1878-07-23-grossenhain', 'wp-liste-1870'],
            ],
        ],
    ],
    'corrections' => [
        [
            'field' => 'deaths',
            'value' => 8,
            'sources' => ['ddb-1878-07-23-grossenhain', 'wp-liste-1870'],
            'fr' => 'Six voyageurs et deux agents (chauffeur, conducteur) tués selon le récit du Journal d\'Ille-et-Vilaine du 23 juillet 1878 et la liste de Wikipédia ; la fiche ne donnait pas de nombre de morts.',
            'en' => 'Six passengers and two staff (stoker, guard) killed according to the Journal d\'Ille-et-Vilaine account of 23 July 1878 and the Wikipedia list; the record gave no number of deaths.',
            'de' => 'Sechs Reisende und zwei Bedienstete (Heizer, Schaffner) getötet nach dem Bericht des Journal d\'Ille-et-Vilaine vom 23. Juli 1878 und der Wikipedia-Liste; der Eintrag nannte keine Zahl von Toten.',
        ],
    ],
];
